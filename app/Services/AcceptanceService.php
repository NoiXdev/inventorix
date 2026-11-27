<?php

namespace App\Services;

use App\Mail\AcceptanceResponse;
use App\Mail\ItemCheckedIn;
use App\Mail\ItemCheckedOut;
use App\Models\Accessory;
use App\Models\Asset;
use App\Models\Category;
use App\Models\CheckoutAcceptance;
use App\Models\Consumable;
use App\Models\License;
use App\Models\User;
use App\Settings\AlertSettings;
use App\Settings\GeneralSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use InvalidArgumentException;

/**
 * Acceptances and the check-out / check-in mails (Snipe-IT's checkout
 * acceptances and checkout/checkin notifications), driven by the item's
 * category:
 * - "require acceptance": the user gets a signed link (no login) to accept
 *   or decline the item, and the IT team may be told the answer;
 * - a EULA (the category's, or the default one): sent with the check-out;
 * - "email on check-in": the user is told about check-out and check-in.
 * Mails are queued and sent after the surrounding transaction commits, so a
 * mail server failure never undoes or breaks the check-out itself.
 */
class AcceptanceService
{
    public const LINK_DAYS = 30;

    public function __construct(private readonly GeneralSettings $general, private readonly AlertSettings $alerts) {}

    /** After an item was checked out to a user: ask for acceptance and/or send the check-out mail. */
    public function checkedOut(Asset|Accessory|License|Consumable $item, User $user, ?string $checkoutId = null): ?CheckoutAcceptance
    {
        $category = $this->category($item);
        if ($category === null) {
            return null;
        }
        $eula = $this->eula($category);

        $acceptance = null;
        if ($category->require_acceptance) {
            $acceptance = CheckoutAcceptance::create([
                'user_id' => $user->id,
                'item_type' => $this->type($item),
                'item_id' => $item->id,
                'checkout_id' => $checkoutId,
                'item_name' => $this->name($item),
                'eula_text' => $eula,
                'sent_at' => filled($user->email) ? now() : null,
            ]);
            if ($item instanceof Asset) {
                Asset::query()->whereKey($item->id)->update(['accepted' => 'pending']);
            }
        }

        if (filled($user->email) && ($acceptance || $eula !== null || $category->checkin_email)) {
            $url = $acceptance ? $this->link($acceptance) : null;
            $name = $this->name($item);
            Mail::to($user->email)->queue((new ItemCheckedOut($user, $name, $eula, $url))->afterCommit());
        }

        return $acceptance;
    }

    /** After an item came back from a user: open acceptances lapse; tell the user if the category says so. */
    public function checkedIn(Asset|Accessory|License $item, ?User $user, ?string $checkoutId = null, bool $dropPending = true): void
    {
        if ($dropPending) {
            CheckoutAcceptance::query()->pending()
                ->where('item_type', $this->type($item))->where('item_id', $item->id)
                ->when($user, fn ($q) => $q->where('user_id', $user->id))
                ->when($checkoutId, fn ($q) => $q->where('checkout_id', $checkoutId))
                ->delete();
        }
        if ($item instanceof Asset) {
            Asset::query()->whereKey($item->id)->update(['accepted' => null]);
        }

        $category = $this->category($item);
        if ($user !== null && filled($user->email) && $category?->checkin_email) {
            $name = $this->name($item);
            Mail::to($user->email)->queue((new ItemCheckedIn($user, $name))->afterCommit());
        }
    }

    /**
     * A signed handover counts as acceptance (no mail): the acceptance is
     * recorded as accepted with a pointer to the handover.
     */
    public function acceptedByHandover(Asset $asset, User $user, string $handoverId): void
    {
        $category = $this->category($asset);
        if (! $category?->require_acceptance) {
            return;
        }
        $acceptance = CheckoutAcceptance::create([
            'user_id' => $user->id,
            'item_type' => 'asset',
            'item_id' => $asset->id,
            'item_name' => $this->name($asset),
            'eula_text' => $this->eula($category),
        ]);
        $acceptance->forceFill(['accepted_at' => now(), 'note' => "Signed handover {$handoverId}"])->save();
        Asset::query()->whereKey($asset->id)->update(['accepted' => 'accepted']);
        $this->log($acceptance, 'accepted', 'Signed handover');
    }

    /** The signed link to the acceptance page. */
    public function link(CheckoutAcceptance $acceptance): string
    {
        return URL::temporarySignedRoute('acceptances.show', now()->addDays(self::LINK_DAYS), ['acceptance' => $acceptance->id]);
    }

    /** Send the link again (a new one, valid for another period). */
    public function remind(CheckoutAcceptance $acceptance): bool
    {
        $user = $acceptance->user;
        if (! $acceptance->isPending() || $user === null || blank($user->email)) {
            return false;
        }
        Mail::to($user->email)->queue(new ItemCheckedOut($user, $acceptance->item_name, $acceptance->eula_text, $this->link($acceptance), reminder: true));
        $acceptance->forceFill(['reminded_at' => now()])->save();

        return true;
    }

    /** @throws InvalidArgumentException for an unreadable signature */
    public function respond(CheckoutAcceptance $acceptance, bool $accept, ?string $signaturePng, ?string $note, ?string $ip, ?string $userAgent): void
    {
        $stored = null;
        try {
            $this->storeResponse($acceptance, $accept, $signaturePng, $note, $ip, $userAgent, $stored);
        } catch (\Throwable $e) {
            // The answer was rolled back: its signature file goes too.
            if ($stored !== null) {
                Storage::delete($stored);
            }
            throw $e;
        }
    }

    private function storeResponse(CheckoutAcceptance $acceptance, bool $accept, ?string $signaturePng, ?string $note, ?string $ip, ?string $userAgent, ?string &$stored): void
    {
        DB::transaction(function () use ($acceptance, $accept, $signaturePng, $note, $ip, $userAgent, &$stored): void {
            $locked = CheckoutAcceptance::query()->whereKey($acceptance->id)->lockForUpdate()->firstOrFail();
            if (! $locked->isPending()) {
                throw new InvalidArgumentException('This has already been answered.');
            }

            $path = null;
            if ($signaturePng !== null && $signaturePng !== '') {
                $path = "acceptances/{$locked->id}/signature.png";
                Storage::put($path, $this->decodeSignature($signaturePng));
                $stored = $path;
            }

            $locked->forceFill([
                $accept ? 'accepted_at' : 'declined_at' => now(),
                'note' => $note !== null && trim($note) !== '' ? $note : null,
                'signature_path' => $path,
                'response_ip' => $ip,
                'response_user_agent' => $userAgent ? mb_substr($userAgent, 0, 1000) : null,
            ])->save();

            if ($locked->item_type === 'asset') {
                Asset::query()->whereKey($locked->item_id)->update(['accepted' => $accept ? 'accepted' : 'declined']);
            }
            $this->log($locked, $accept ? 'accepted' : 'declined', $locked->note);

            $class = CheckoutAcceptance::ITEM_TYPES[$locked->item_type] ?? null;
            $item = $class ? $class::withTrashed()->find($locked->item_id) : null;
            $category = $item ? $this->category($item) : null;
            if ($category?->alert_on_response && $this->alerts->recipients !== []) {
                $recipients = $this->alerts->recipients;
                Mail::to($recipients)->queue((new AcceptanceResponse($locked->fresh('user')))->afterCommit());
            }

            $acceptance->setRawAttributes($locked->getAttributes(), true);
        });
    }

    public function category(Asset|Accessory|License|Consumable $item): ?Category
    {
        if ($item instanceof Asset) {
            $item->loadMissing('model.category');

            return $item->model?->category;
        }

        return $item->category()->first();
    }

    private function eula(Category $category): ?string
    {
        $text = $category->use_default_eula ? $this->general->default_eula : $category->eula_text;

        return filled($text) ? $text : null;
    }

    private function type(Asset|Accessory|License|Consumable $item): string
    {
        return array_search($item::class, CheckoutAcceptance::ITEM_TYPES, true);
    }

    private function name(Asset|Accessory|License|Consumable $item): string
    {
        return $item instanceof Asset ? $item->displayName() : (string) $item->name;
    }

    private function log(CheckoutAcceptance $acceptance, string $event, ?string $note): void
    {
        $class = CheckoutAcceptance::ITEM_TYPES[$acceptance->item_type] ?? null;
        $item = $class ? $class::withTrashed()->find($acceptance->item_id) : null;
        if ($item === null) {
            return;
        }
        $logger = activity()->performedOn($item)->event($event);
        // The user answered (no login), so they are who did it.
        if ($acceptance->user !== null) {
            $logger->causedBy($acceptance->user);
        }
        $logger
            ->withProperties(array_filter([
                'target' => 'User: '.($acceptance->user?->name ?? $acceptance->user?->email ?? '—'),
                'note' => $note,
            ], fn ($v) => $v !== null))
            ->log($event);
    }

    private function decodeSignature(string $base64): string
    {
        $decoded = base64_decode(preg_replace('#^data:image/png;base64,#', '', $base64), true);
        if ($decoded === false || strlen($decoded) > (int) config('handover.signature.max_bytes', 512000)
            || substr($decoded, 0, 8) !== "\x89PNG\r\n\x1a\n") {
            throw new InvalidArgumentException('The signature could not be read.');
        }

        return $decoded;
    }
}
