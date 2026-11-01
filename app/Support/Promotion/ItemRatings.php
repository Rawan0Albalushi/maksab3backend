<?php

namespace App\Support\Promotion;

use App\Models\Review;
use Illuminate\Pagination\AbstractCursorPaginator;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Service\Entities\Service;

/**
 * `avg_rating` and `rating_count` for a set of frozen promotion lines, keyed by the line's id.
 *
 * A frozen line carries the item's NAME and PRICE as they were when the bundle was built, and
 * deliberately nothing else -- that is what freezing is for. A rating is the opposite kind of
 * fact: it is whatever customers say today, so it is read live from the item rather than
 * remembered, exactly as ItemResource reads it for an ordinary product card.
 *
 * ONE QUERY PER SOURCE, whatever it is handed. Every caller renders a PAGE of enrolments, each
 * holding several lines, so resolving per line would be an N+1 squared across the list
 * (architecture rule 11). Pass the whole page in.
 *
 * The two sources are not the same shape and cannot be merged into one query: an ITEM's rating is
 * an aggregate over `reviews`, while a SERVICE keeps `avg_rating` and `rating_count` as columns
 * on its own row. Both answer under the same two keys so a client reads one shape.
 */
class ItemRatings
{
    /** What a line with no ratings reports, so every line carries both keys (N9). */
    private const NONE = ['avg_rating' => 0.0, 'rating_count' => 0];

    /**
     * @param  iterable<int, object>  $lines  frozen lines carrying item_id / service_id
     * @return array<int, array{avg_rating: float, rating_count: int}>  line id => rating
     */
    public static function forLines(iterable $lines): array
    {
        $lines = self::rows($lines);

        if ($lines->isEmpty()) {
            return [];
        }

        $itemRatings = self::itemRatings($lines->pluck('item_id')->unique()->filter()->all());
        $serviceRatings = self::serviceRatings($lines->pluck('service_id')->unique()->filter()->all());

        return $lines->mapWithKeys(fn ($line) => [
            $line->id => $line->service_id
                ? ($serviceRatings[(int) $line->service_id] ?? self::NONE)
                : ($itemRatings[(int) $line->item_id] ?? self::NONE),
        ])->all();
    }

    /**
     * The same, for parents that hold their lines in `items` — a Bundle, a BOGO enrolment.
     *
     * @param  iterable<int, object>  $parents
     * @return array<int, array{avg_rating: float, rating_count: int}>
     */
    public static function forParents(iterable $parents): array
    {
        return self::forLines(
            self::rows($parents)->flatMap(fn ($parent) => $parent->items ?? [])
        );
    }

    /**
     * An item's rating, aggregated over its approved reviews.
     *
     * `status = 1` is the same filter ItemRelationsTrait puts on the `rating` relation every
     * product card is built from, so a bundle line cannot quote a score the item's own page
     * disagrees with -- unapproved reviews count towards neither.
     */
    private static function itemRatings(array $itemIds): array
    {
        if (empty($itemIds)) {
            return [];
        }

        return Review::query()
            ->whereIn('item_id', $itemIds)
            ->where('status', 1)
            ->groupBy('item_id')
            ->select('item_id', DB::raw('AVG(rating) as average'), DB::raw('COUNT(*) as rating_count'))
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->item_id => [
                'avg_rating' => round((float) $row->average, 1),
                'rating_count' => (int) $row->rating_count,
            ]])
            ->all();
    }

    /**
     * A service's rating, read off its own row.
     *
     * Guarded on the addon being installed AND the table existing: the Service module is optional,
     * and a BOGO never holds service lines anyway (a service cannot be bought one and given one
     * free), so this is here for the bundle payloads that share this resolver.
     */
    private static function serviceRatings(array $serviceIds): array
    {
        if (empty($serviceIds) || ! class_exists(Service::class) || ! Schema::hasTable('services')) {
            return [];
        }

        return Service::withoutGlobalScopes()
            ->whereIn('id', $serviceIds)
            ->get(['id', 'avg_rating', 'rating_count'])
            ->mapWithKeys(fn ($service) => [(int) $service->id => [
                'avg_rating' => round((float) $service->avg_rating, 1),
                'rating_count' => (int) $service->rating_count,
            ]])
            ->all();
    }

    /** Accepts a collection, an array or a paginator, the way every listing hands its page over. */
    private static function rows(iterable $rows): Collection
    {
        if ($rows instanceof AbstractPaginator || $rows instanceof AbstractCursorPaginator) {
            $rows = $rows->getCollection();
        }

        return collect($rows)->filter()->values();
    }
}
