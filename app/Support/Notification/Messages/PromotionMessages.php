<?php

namespace App\Support\Notification\Messages;

use App\Models\NotificationMessage;

/**
 * Copy for the Happy Hour and BOGO enrolment transitions.
 *
 * Unlike its neighbours, this one reads the wording out of `notification_messages` rather than
 * holding it inline: P1 seeded 17 rows for these transitions, keyed by (key, module_type), and an
 * operator edits them in the notification-settings screen. Hard-coding the strings here would make
 * those rows decorative and the editor a lie.
 *
 * The seeded copy carries {offerTitle} and {storeName} placeholders, which are substituted here.
 * A missing row falls back to the caller's default, so a module seeded after these migrations ran
 * still notifies rather than pushing an empty body.
 */
trait PromotionMessages
{
    /** Memoised per (key, module_type); one enrolment decision can ask for the same row twice. */
    private static array $promotionCopy = [];

    /**
     * @param  string  $key   the seeded `notification_messages` row -- what to SAY
     * @param  string|null  $payloadType  what the app branches on. Defaults to $key for callers
     *                      that have no separate notion of the two.
     *
     * The two were the same string until StackFood parity: this class put the transition key
     * straight into `type`, so a vendor payload announced itself as `store_bogo_invitation` while
     * StackFood's announced `bogo_offer`. An app serving both products needed two vocabularies for
     * one event. They are now separable -- the key still selects the copy and gates the settings
     * row, and `type` carries the promotion.
     */
    public static function promotionEnrollment(
        string $key,
        ?string $moduleType,
        array $replace = [],
        string $title = '',
        array $extra = [],
        ?string $payloadType = null,
    ): array {
        $body = self::promotionCopyFor($key, $moduleType);

        foreach ($replace as $token => $value) {
            $body = str_replace('{'.$token.'}', (string) $value, $body);
        }

        return self::make(
            $title !== '' ? $title : translate('messages.Promotion'),
            $body,
            $extra + [
                'type' => $payloadType ?? $key,
                // The transition, kept because `type` no longer names it. StackFood has no
                // equivalent and its app reads the body text instead; this is additive, so a
                // client written against StackFood ignores it while one written against this
                // platform can still route the seven vendor events apart.
                'notification_key' => $key,
            ],
        );
    }

    /** The seeded row for this module, the 'all' row, or the key humanised as a last resort. */
    private static function promotionCopyFor(string $key, ?string $moduleType): string
    {
        $cacheKey = $key.'|'.($moduleType ?? 'all');

        if (array_key_exists($cacheKey, self::$promotionCopy)) {
            return self::$promotionCopy[$cacheKey];
        }

        $row = NotificationMessage::where('key', $key)
            ->when($moduleType, fn ($q) => $q->whereIn('module_type', [$moduleType, 'all']))
            ->orderByRaw("CASE WHEN module_type = ? THEN 0 ELSE 1 END", [$moduleType ?? 'all'])
            ->first();

        $message = $row && $row->status && filled($row->message)
            ? $row->message
            : ucfirst(str_replace('_', ' ', $key));

        return self::$promotionCopy[$cacheKey] = $message;
    }

    /** Test seam: the memo is static and a test that edits a row would otherwise see the old copy. */
    public static function forgetPromotionCopy(): void
    {
        self::$promotionCopy = [];
    }
}
