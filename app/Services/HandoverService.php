<?php

namespace App\Services;

use App\DataObjects\HandoverData;
use App\Exceptions\HandoverStateConflictException;
use App\Jobs\GenerateHandoverPdf;
use App\Models\Asset;
use App\Models\Handover;
use App\Models\StatusLabel;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HandoverService
{
    public function commit(HandoverData $data): Handover
    {
        return DB::transaction(function () use ($data): Handover {
            $assets = Asset::query()
                ->whereIn('id', $data->assetIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $allowedFrom = StatusLabel::systemIds($data->type->allowedStateFrom());

            $conflicting = [];
            foreach ($data->assetIds as $assetId) {
                $asset = $assets[$assetId] ?? null;
                if ($asset === null || ! in_array($asset->status_id, $allowedFrom, true)) {
                    $conflicting[] = $assetId;
                }
            }

            if (! empty($conflicting)) {
                throw new HandoverStateConflictException($conflicting);
            }

            $handoverId = (string) Str::uuid();
            $disk = config('handover.disk');
            $signaturePath = "handovers/{$handoverId}/signature.png";

            $signatureBytes = $this->decodeSignature($data->signaturePngBase64);
            Storage::disk($disk)->put($signaturePath, $signatureBytes);

            try {
                $handover = Handover::create([
                    'id' => $handoverId,
                    'type' => $data->type->value,
                    'recipient_kind' => $data->recipientKind->value,
                    'recipient_user_id' => $data->recipientUserId,
                    'recipient_name' => $data->recipientName,
                    'recipient_email' => $data->recipientEmail,
                    'accessories' => $data->accessories,
                    'condition_notes' => $data->conditionNotes,
                    'terms_text' => $data->termsText,
                    'signature_path' => $signaturePath,
                    'signature_ip' => $data->signatureIp,
                    'signature_user_agent' => $data->signatureUserAgent,
                    'signed_at' => now(),
                    'created_by' => $data->createdById,
                ]);

                $stateTo = StatusLabel::system($data->type->stateTo())->id;
                $ownerTo = $data->type->assignsRecipientAsOwner() ? $data->recipientUserId : null;

                foreach ($data->assetIds as $assetId) {
                    $asset = $assets[$assetId];
                    $stateFrom = $asset->status_id;
                    // The pivot records users only: an asset assigned to a
                    // location or another asset had no owning user.
                    $ownerFrom = $asset->assignedUserId();

                    $handover->assets()->attach($assetId, [
                        'id' => (string) Str::uuid(),
                        'status_from_id' => $stateFrom,
                        'status_to_id' => $stateTo,
                        'owner_from_id' => $ownerFrom,
                        'owner_to_id' => $ownerTo,
                    ]);

                    $asset->status_id = $stateTo;
                    $asset->assignee_type = $ownerTo ? User::class : null;
                    $asset->assignee_id = $ownerTo;
                    // Issuing or lending checks the asset out, a return checks it in.
                    if ($data->type->assignsRecipientAsOwner()) {
                        $asset->last_checkout = now();
                        $asset->checkout_counter++;
                    } else {
                        $asset->last_checkin = now();
                        $asset->checkin_counter++;
                        $asset->expected_checkin = null;
                    }
                    $asset->save();

                    activity('asset')
                        ->performedOn($asset->fresh())
                        ->causedBy(Auth::id() ?: $data->createdById)
                        ->withProperties([
                            'handover_id' => $handover->id,
                            'type' => $data->type->value,
                            'recipient_name' => $data->recipientName,
                        ])
                        ->log('handover_completed');
                }

                GenerateHandoverPdf::dispatch($handover->id)
                    ->afterCommit();

                return $handover;
            } catch (\Throwable $e) {
                Storage::disk($disk)->delete($signaturePath);
                throw $e;
            }
        });
    }

    private function decodeSignature(string $base64): string
    {
        $decoded = base64_decode($base64, true);
        if ($decoded === false) {
            throw new \InvalidArgumentException('Signature payload is not valid base64.');
        }

        if (strlen($decoded) > (int) config('handover.signature.max_bytes')) {
            throw new \InvalidArgumentException('Signature exceeds max bytes.');
        }

        if (substr($decoded, 0, 8) !== "\x89PNG\r\n\x1a\n") {
            throw new \InvalidArgumentException('Signature is not a PNG.');
        }

        return $decoded;
    }
}
