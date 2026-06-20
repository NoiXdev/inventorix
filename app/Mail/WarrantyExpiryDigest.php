<?php

namespace App\Mail;

use App\DataObjects\WarrantyScanResult;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Collection;

class WarrantyExpiryDigest extends Mailable
{
    /** Display order: expired first, then ascending lead days. */
    private const SECTION_ORDER = ['expired', '7', '30', '90'];

    /** @param Collection<int, WarrantyScanResult> $results */
    public function __construct(public Collection $results) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: trans('warranty.mail.subject', ['count' => $this->results->count()]),
        );
    }

    public function content(): Content
    {
        // groupBy on a numeric-string milestone (e.g. '7') produces integer PHP array keys.
        // Normalise all keys to strings up front to make comparisons reliable.
        $grouped = $this->results->groupBy(fn ($r): string => (string) $r->milestone);
        // $grouped->keys() may contain integers (PHP coerces numeric strings), so cast to strings.
        $stringKeys = $grouped->keys()->map(fn ($k): string => (string) $k)->all();

        $sections = collect($this->orderedKeys($stringKeys))
            ->map(fn (string $key): array => [
                'key' => $key,
                'title' => $key === 'expired'
                    ? trans('warranty.mail.section.expired')
                    : trans('warranty.mail.section.lead', ['days' => $key]),
                // Use (int)$key as the lookup since the underlying PHP array has an integer key for numeric strings.
                'rows' => is_numeric($key) ? $grouped->get((int) $key) : $grouped->get($key),
            ])
            ->all();

        return new Content(
            markdown: 'emails.warranty-expiry-digest',
            with: ['sections' => $sections],
        );
    }

    /**
     * @param  array<int, string>  $keys  already normalised to strings
     * @return array<int, string>
     */
    private function orderedKeys(array $keys): array
    {
        $known = array_values(array_filter(self::SECTION_ORDER, fn (string $k): bool => in_array($k, $keys, true)));
        // Any custom lead-day values not in SECTION_ORDER, appended in ascending numeric order.
        $extra = collect($keys)
            ->reject(fn (string $k): bool => in_array($k, self::SECTION_ORDER, true))
            ->sortBy(fn (string $k): int => (int) $k)
            ->values()
            ->all();

        return array_merge($known, $extra);
    }
}
