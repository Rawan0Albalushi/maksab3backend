<?php

namespace App\Services\Promotion;

use App\Models\BogoOffer;
use App\Models\BogoOfferStore;
use App\Services\BaseService;
use App\Support\Storage\FileStorage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * The admin's side of BOGO: creating offers and deciding who runs them.
 *
 * An offer is a shell -- quantities, a window, caps -- and holds no items. Which items make up
 * the bundle is chosen per store at enrolment, because two stores joining the same offer will
 * price and staff it differently. That is why nothing here touches bogo_offer_items.
 */
class BogoOfferAdminService extends BaseService
{
    /**
     * The admin list, with the two counts the Store column shows.
     *
     * A denied request is not a store on the offer -- counting it made the column read higher
     * than the number of stores that could ever serve the bundle, with nothing on the page saying
     * so. Approved plus still-to-be-decided is what the number means, and the tooltip splits it so
     * the reader does not have to open the offer to find out which is which.
     *
     * withCount rather than a count per row: lazy loading throws outside production, and a per-row
     * count would be one query per offer on every page.
     */
    public function list(?string $search, ?int $moduleId, int $perPage): LengthAwarePaginator
    {
        return BogoOffer::withCount([
            'enrollments as enrollments_count' => fn ($q) => $q->whereIn('status', [
                BogoOfferStore::STATUS_APPROVED, BogoOfferStore::STATUS_PENDING,
            ]),
            'enrollments as approved_count' => fn ($q) => $q->where('status', BogoOfferStore::STATUS_APPROVED),
        ])
            ->when($moduleId, fn ($q) => $q->where('module_id', $moduleId))
            ->when($search, function ($query) use ($search) {
                // Trimmed and emptied out first: a padded search splits into empty pieces, each of
                // which becomes LIKE '%%' and matches every row -- so "  term  " answered with the
                // whole list instead of the one match. Grouped so the OR chain cannot escape the
                // module filter beside it.
                $query->where(function ($q) use ($search) {
                    foreach (array_filter(explode(' ', trim($search))) as $word) {
                        $q->orWhere('title', 'like', "%{$word}%");
                    }
                });
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function store(Request $request): BogoOffer
    {
        return DB::transaction(function () use ($request) {
            $offer = BogoOffer::create([
                // The panel's current module, not a posted field. Every admin screen is scoped by
                // the header's module switcher, so an offer belongs to whichever module the admin
                // was looking at -- the same way CampaignController stamps a campaign.
                'module_id' => Config::get('module.current_module_id'),
                // title[] and lang[] arrive as parallel arrays -- the house convention every
                // other multilingual form posts. The row itself carries the default-language
                // value; the alternatives go to the translations table via TranslationRepository.
                'title' => $this->defaultLangValue($request, 'title'),
                'description' => $this->defaultLangValue($request, 'description'),
                // 2 MB, and GIF is allowed on the offer image per the supplied design. Uploads of
                // jpg/jpeg/png are re-encoded to webp by FileStorage; a gif passes through.
                'image' => $request->hasFile('image')
                    ? FileStorage::upload('bogo_offer/', $request->file('image'), 2, 'jpg,jpeg,png,gif')
                    : null,
                'buy_qty' => (int) $request->input('buy_qty'),
                'get_qty' => (int) $request->input('get_qty'),
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date'),
                'usage_limit_total' => $request->input('usage_limit_total') ?: null,
                'usage_limit_per_customer' => $request->input('usage_limit_per_customer') ?: null,
                'order_types' => BogoOffer::orderTypesEnabled() ? $request->input('order_types') : null,
                'status' => 1,
                'admin_id' => auth('admin')->id(),
            ]);

            return $offer;
        });
    }

    public function update(BogoOffer $offer, Request $request): BogoOffer
    {
        return DB::transaction(function () use ($offer, $request) {
            $data = [
                // title[] and lang[] arrive as parallel arrays -- the house convention every
                // other multilingual form posts. The row itself carries the default-language
                // value; the alternatives go to the translations table via TranslationRepository.
                'title' => $this->defaultLangValue($request, 'title'),
                'description' => $this->defaultLangValue($request, 'description'),
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date'),
                'usage_limit_total' => $request->input('usage_limit_total') ?: null,
                'usage_limit_per_customer' => $request->input('usage_limit_per_customer') ?: null,
                'order_types' => BogoOffer::orderTypesEnabled() ? $request->input('order_types') : $offer->order_types,
            ];

            // Buy/get quantities are editable only until the first store enrols: after that the
            // enrolled item sets have been built to total them and could no longer be reconciled.
            if (! $offer->isQuantityLocked()) {
                $data['buy_qty'] = (int) $request->input('buy_qty');
                $data['get_qty'] = (int) $request->input('get_qty');
            }

            if ($request->hasFile('image')) {
                $data['image'] = FileStorage::update('bogo_offer/', $offer->image, $request->file('image'), 2, 'jpg,jpeg,png,gif');
            }

            $offer->update($data);

            return $offer;
        });
    }

    /**
     * Switching an offer off takes its bundles out of every cart holding them.
     *
     * A bundle nobody can order cannot sit in a cart priced at zero waiting to fail at checkout,
     * so the cart is corrected now rather than at the customer's next read.
     */
    public function setStatus(BogoOffer $offer, int $status): void
    {
        $offer->update(['status' => $status]);

        if (! $status) {
            $offer->strandCarts();
        }
    }

    public function delete(BogoOffer $offer): void
    {
        DB::transaction(function () use ($offer) {
            $offer->strandCarts();
            $offer->translations()->delete();
            // Enrolments and their frozen items cascade on the foreign key.
            $offer->delete();
        });
    }

    /**
     * The value posted for the default language.
     *
     * title[] and lang[] are parallel arrays, so the default entry is found by locating 'default'
     * in lang[] and reading the same index. Falls back to the first entry when a form posts a
     * plain string instead, which the no-language case does.
     */
    private function defaultLangValue(Request $request, string $key): ?string
    {
        $values = $request->input($key);

        if (! is_array($values)) {
            return $values;
        }

        $langs = (array) $request->input('lang', []);
        $index = array_search('default', $langs, true);

        return $index !== false ? ($values[$index] ?? null) : ($values[0] ?? null);
    }
}
