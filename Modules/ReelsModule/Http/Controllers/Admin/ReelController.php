<?php

namespace Modules\ReelsModule\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\Exceptions\InvalidUploadException;
use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Store;
use Brian2694\Toastr\Facades\Toastr;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Modules\ReelsModule\Entities\Reel;
use Modules\ReelsModule\Services\Reel\ReelService;
use Modules\ReelsModule\Entities\ReelEngagement;
use Modules\ReelsModule\Http\Requests\Admin\ReelStoreRequest;
use Modules\ReelsModule\Http\Requests\Admin\ReelUpdateRequest;
use Modules\ReelsModule\Support\ReelModuleConfig;
use Modules\ReelsModule\Support\ReelProductableResolver;

class ReelController extends Controller
{
    /**
     * Whether getFilteredQuery() narrowed the reel list beyond the module scope.
     * Set there, read to decide if the module-wide total can be taken from the paginator.
     */
    private bool $listIsFiltered = false;

    public function index(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->guardAccessibleModule()) {
            return $redirect;
        }

        $stores = $this->getFilterStores();
        $filteredQuery = $this->getFilteredQuery($request);
        $analyticsQuery = clone $filteredQuery;

        $reels = $this->applySorting(clone $filteredQuery, $request)
            ->paginate(config('default_pagination'))
            ->appends($request->query());

        $analytics = $this->buildAnalytics($analyticsQuery, $request);
        $filterCount = $this->getFilterCount($request);
        $engagementTotals = $this->getModuleEngagementTotals();
        $overview = [
            // Module-wide total, like every other card. Unfiltered, the paginator has
            // already counted exactly those rows.
            'total_reels' => $this->listIsFiltered ? Reel::moduleWise()->count() : $reels->total(),
            'total_views' => $this->engagementCount($engagementTotals, ReelEngagement::TYPE_VIEW),
            'total_likes' => $this->engagementCount($engagementTotals, ReelEngagement::TYPE_LIKE),
            'total_store_visits' => $this->engagementCount($engagementTotals, ReelEngagement::TYPE_VISIT),
            'total_sale' => $this->engagementCount($engagementTotals, ReelEngagement::TYPE_ORDER),
            'total_sale_amount' => (float) ($engagementTotals->get(ReelEngagement::TYPE_ORDER)?->total_amount ?? 0),
        ];
        $overviewCards = $this->buildOverviewCards($overview, $analytics);

        return view('reelsmodule::admin.reels.index', compact('reels', 'stores', 'overview', 'overviewCards', 'analytics', 'filterCount'));
    }

    private function buildOverviewCards(array $overview, array $analytics): array
    {
        $isServiceModule = config('module.current_module_type') == 'service';

        return [
            [
                'value' => $overview['total_reels'],
                'label' => translate('Total reels'),
                'icon' => 'tio-video-camera-outlined',
                'color' => 'text-purple',
                'bg' => 'bg-purple bg-opacity-10',
            ],
            [
                'value' => $overview['total_views'],
                'label' => translate('Total views'),
                'icon' => 'tio-invisible',
                'color' => 'text-info',
                'bg' => 'bg-info bg-opacity-10',
                'note' => $analytics['weekly_change']['views'] . ' ' . translate('This week'),
            ],
            [
                'value' => $overview['total_likes'],
                'label' => translate('Total likes'),
                'icon' => 'tio-heart-outlined',
                'color' => 'text-danger',
                'bg' => 'bg-danger bg-opacity-10',
                'note' => $analytics['weekly_change']['likes'] . ' ' . translate('This week'),
            ],
            [
                'value' => $overview['total_store_visits'],
                'label' => config('module.current_module_type') == 'service' ? translate('Provider Visits') : translate('Store visits'),
                'icon' => 'tio-home-vs-2-outlined',
                'color' => 'text-success',
                'bg' => 'bg-success bg-opacity-10',
                'note' => $analytics['weekly_change']['visits'] . ' ' . translate('This week'),
            ],
            [
                'value' => Helpers::format_currency($overview['total_sale_amount'] ?? 0),
                'label' => $isServiceModule ? translate('Total booking amount') : translate('Total sale amount'),
                'tooltip' => $isServiceModule ? translate('Total booking value from reel book now bookings') : translate('Total order value from reel order now purchases'),
                'icon' => 'tio-money',
                'color' => 'text-primary',
                'bg' => 'bg-primary bg-opacity-10',
                'note' => ($analytics['weekly_change']['sale_amount'] ?? '0%') . ' ' . translate('This week'),
            ],
            [
                'value' => $overview['total_sale'] ?? 0,
                'label' => $isServiceModule ? translate('Total booking') : translate('Total sale'),
                'tooltip' => $isServiceModule ? translate('Total bookings placed using the reel book now button') : translate('Total orders placed using the reel order now button'),
                'icon' => 'tio-shopping-cart',
                'color' => 'text-warning',
                'bg' => 'bg-warning bg-opacity-10',
                'note' => ($analytics['weekly_change']['sale'] ?? '0%') . ' ' . translate('This week'),
            ],
        ];
    }

    public function create(): View|RedirectResponse
    {
        if ($redirect = $this->guardAccessibleModule()) {
            return $redirect;
        }

        $language = getWebConfig('language') ?? [];
        $defaultLang = str_replace('_', '-', app()->getLocale());
        $stores = $this->getStores();
        $previewStore = null;
        $reel = new Reel([
            'is_always_visible' => false,
            'status' => true,
        ]);
        $items = collect();
        $selectedProductId = null;
        $productLabel = $this->productLabel();
        $actionLabel = $this->actionLabel();

        return view('reelsmodule::admin.reels.create', compact('language', 'defaultLang', 'stores', 'previewStore', 'reel', 'items', 'selectedProductId', 'productLabel', 'actionLabel'));
    }

    public function store(ReelStoreRequest $request)
    {
        if ($redirect = $this->guardAccessibleModule()) {
            return response()->json(['message' => translate('messages.This feature is not available for the selected module')], 403);
        }

        if(getEnvMode() === 'demo') {
            return response()->json(['message' => translate('Uploads are disabled in demo mode')], 403);
        }

        $store = $this->resolveStore($request->store_id);
        if (!$store) {
            return response()->json(['errors' => [['message' => 'Please select a valid ' . Helpers::getStoreLabelByModuleType(config('module.current_module_type'), true)]]], 422);
        }

        $reel = new Reel();

        try {
            $this->fillAndPersistReel($reel, $request, $store);
        } catch (InvalidUploadException $exception) {
            return response()->json(['errors' => [['message' => $exception->getMessage()]]], 422);
        }

        Toastr::success(translate('Added successfully'));

        return response()->json([
            'message' => translate('Added successfully'),
            'redirect' => route('admin.reels.index'),
        ]);
    }

    public function edit(int $id): View|RedirectResponse
    {
        if ($redirect = $this->guardAccessibleModule()) {
            return $redirect;
        }

        $reel = $this->findAccessibleReel($id);
        if (!$reel) {
            Toastr::error(translate('No data found'));

            return redirect()->back();
        }

        $language = getWebConfig('language') ?? [];
        $defaultLang = str_replace('_', '-', app()->getLocale());
        $stores = $this->getStores();
        $previewStore = $this->previewStore($reel->store_id);
        $items = $this->getStoreItems($reel->store_id);
        $selectedProductId = $reel->productable_id;
        $productLabel = $this->productLabel();
        $actionLabel = $this->actionLabel();

        return view('reelsmodule::admin.reels.edit', compact('language', 'defaultLang', 'stores', 'previewStore', 'reel', 'items', 'selectedProductId', 'productLabel', 'actionLabel'));
    }

    public function update(ReelUpdateRequest $request, int $id)
    {
        if ($redirect = $this->guardAccessibleModule()) {
            return response()->json(['message' => translate('messages.This feature is not available for the selected module')], 403);
        }
        if(getEnvMode() === 'demo') {
            return response()->json(['message' => translate('Uploads are disabled in demo mode')], 403);
        }
        
        $reel = $this->findAccessibleReel($id);
        if (!$reel) {
            return response()->json(['errors' => [['message' => translate('No data found')]]], 404);
        }

        $store = $this->resolveStore($request->store_id);
        if (!$store) {
            return response()->json(['errors' => [['message' => 'Please select a valid ' . Helpers::getStoreLabelByModuleType(config('module.current_module_type'), true)]]], 422);
        }

        try {
            $this->fillAndPersistReel($reel, $request, $store);
        } catch (InvalidUploadException $exception) {
            return response()->json(['errors' => [['message' => $exception->getMessage()]]], 422);
        }

        Toastr::success(translate('Updated successfully'));

        return response()->json([
            'message' => translate('Updated successfully'),
            'redirect' => route('admin.reels.index'),
        ]);
    }

    public function destroy(int $id): RedirectResponse
    {
        if ($redirect = $this->guardAccessibleModule()) {
            return $redirect;
        }

        $reel = $this->findAccessibleReel($id);
        if (!$reel) {
            Toastr::error(translate('No data found'));

            return redirect()->back();
        }

        app(ReelService::class)->deleteAsset($reel->thumbnail);
        app(ReelService::class)->deleteAsset($reel->video);
        $reel->translations()->delete();
        $reel->storage()->delete();
        $reel->delete();

        Toastr::success(translate('Deleted successfully'));

        return redirect()->back();
    }

    public function status(int $id, int $status): RedirectResponse
    {
        if ($redirect = $this->guardAccessibleModule()) {
            return $redirect;
        }

        $reel = $this->findAccessibleReel($id);
        if (!$reel) {
            Toastr::error(translate('No data found'));

            return redirect()->back();
        }

        $reel->status = $status;
        $reel->save();

        Toastr::success(translate('Updated successfully'));

        return back();
    }

    private function guardAccessibleModule(): ?RedirectResponse
    {
        if (!addon_published_status('ReelsModule')) {
            abort(404);
        }

        if (!ReelModuleConfig::isMultiModule()) {
            return null;
        }

        $currentModuleType = config('module.current_module_type');
        $currentModuleId = config('module.current_module_id');

        if (!ReelModuleConfig::isAllowedType($currentModuleType) || !is_numeric($currentModuleId)) {
            Toastr::error(translate('messages.This feature is not available for the selected module'));

            return redirect()->back();
        }

        return null;
    }

    /**
     * Hard ceiling on the form's store picker.
     *
     * The picker is a plain <select> rendered server-side, so its size is bounded by what a
     * browser and the memory limit can take, not by the query. At the store counts this schema
     * supports the unbounded list exhausted 512MB before the page rendered. This keeps the page
     * alive; a store beyond the ceiling cannot be chosen until the picker becomes a searchable
     * ajax select, which is the actual fix -- the items() endpoint below is the pattern for it.
     */
    private const STORE_PICKER_LIMIT = 1000;

    /**
     * Stores offered in the reel form's picker. Carries the storage relation because each
     * option renders a data-logo attribute the form's preview script reads.
     */
    private function getStores()
    {
        return $this->storeOptionQuery()
            ->with('storage')
            ->limit(self::STORE_PICKER_LIMIT)
            ->get(['id', 'name', 'logo', 'module_id']);
    }

    /**
     * Stores offered in the list screen's filter. Narrowed to those that actually have a reel,
     * because filtering by any other store returns nothing -- and the full list is 25k stores
     * per module, which exhausted the memory limit before the page could render.
     */
    private function getFilterStores()
    {
        return $this->storeOptionQuery()
            ->whereIn('id', Reel::moduleWise()->toBase()->select('store_id')->whereNotNull('store_id'))
            ->get(['id', 'name', 'module_id']);
    }

    /**
     * Deliberately without the storage relation -- callers that render a logo add it. The
     * filter list does not, and eager-loading storage across every store was most of what
     * made this page exhaust the memory limit.
     */
    private function storeOptionQuery()
    {
        return Store::withoutGlobalScopes()
            ->where('status', 1)
            ->with(['translations' => function ($query) {
                return $query->where('locale', app()->getLocale());
            }])
            ->when(ReelModuleConfig::isMultiModule(), fn ($query) => $query->where('module_id', config('module.current_module_id')))
            ->latest();
    }

    /** The single store whose logo and name the form previews, or null when none is chosen. */
    private function previewStore(?int $storeId): ?Store
    {
        if (! $storeId) {
            return null;
        }

        return Store::withoutGlobalScopes()->with('storage')->find($storeId, ['id', 'name', 'logo']);
    }

    public function items(Request $request): JsonResponse
    {
        return response()->json([
            'items' => $this->getStoreItems((int) $request->input('store_id'))->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'price' => (float) $item->price,
            ])->values(),
        ]);
    }

    private function isRentalModule(): bool
    {
        return config('module.current_module_type') === 'rental';
    }

    private function isServiceModule(): bool
    {
        return config('module.current_module_type') === 'service';
    }

    private function productLabel(): string
    {
        if ($this->isRentalModule()) {
            return translate('messages.Vehicle');
        }

        if ($this->isServiceModule()) {
            return translate('messages.Service');
        }

        return translate('messages.Product');
    }

    private function actionLabel(): string
    {
        return $this->isRentalModule() || $this->isServiceModule()
            ? translate('messages.Book Now')
            : translate('messages.Order now');
    }

    private function getStoreItems(?int $storeId): Collection
    {
        if (!$storeId) {
            return collect();
        }

        if ($this->isRentalModule()) {
            if (!class_exists(\Modules\Rental\Entities\Vehicle::class)) {
                return collect();
            }

            return \Modules\Rental\Entities\Vehicle::withoutGlobalScopes()
                // name is translated, so reading it off each row reaches for translations.
                ->with('translations')
                ->where('provider_id', $storeId)
                ->where('status', 1)
                ->orderBy('name')
                ->get(['id', 'name', 'day_wise_price', 'hourly_price', 'distance_price'])
                ->map(fn ($vehicle) => (object) [
                    'id' => $vehicle->id,
                    'name' => $vehicle->name,
                    'price' => (float) ($vehicle->day_wise_price ?: $vehicle->hourly_price ?: $vehicle->distance_price),
                ]);
        }

        if ($this->isServiceModule()) {
            if (!class_exists(\Modules\Service\Entities\Service::class)) {
                return collect();
            }

            return \Modules\Service\Entities\Service::withoutGlobalScopes()
                ->with('translations')
                ->where('store_id', $storeId)
                ->where('status', 1)
                ->where('is_approved', 1)
                ->when(ReelModuleConfig::isMultiModule(), fn ($query) => $query->where('module_id', config('module.current_module_id')))
                ->orderBy('name')
                ->get(['id', 'name', 'base_price'])
                ->map(fn ($service) => (object) [
                    'id' => $service->id,
                    'name' => $service->name,
                    'price' => (float) $service->base_price,
                ]);
        }

        return Item::withoutGlobalScopes()
            ->with(['translations' => function ($query) {
                $query->where('locale', app()->getLocale());
            }])
            ->where('store_id', $storeId)
            ->where('status', 1)
            ->where('is_approved', 1)
            ->when(ReelModuleConfig::isMultiModule(), fn ($query) => $query->where('module_id', config('module.current_module_id')))
            ->orderBy('name')
            ->get(['id', 'name', 'price']);
    }

    private function resolveStore(int|string|null $storeId): ?Store
    {
        return Store::withoutGlobalScopes()
            ->with(['translations' => function ($query) {
                return $query->where('locale', app()->getLocale());
            }, 'storage'])
            ->where('id', $storeId)
            ->when(ReelModuleConfig::isMultiModule(), fn ($query) => $query->where('module_id', config('module.current_module_id')))
            ->first();
    }

    private function findAccessibleReel(int $id): ?Reel
    {
        return Reel::withoutGlobalScope('translate')
            ->with(['store.storage', 'storage', 'translations'])
            ->moduleWise()
            ->find($id);
    }

    private function getFilteredQuery(Request $request)
    {
        $keywords = array_filter(explode(' ', (string) $request->input('search', '')));
        $storeIds = array_filter(array_map('intval', (array) $request->input('store_ids', [])));
        if (!$storeIds && $request->filled('store_id')) {
            $storeIds = [(int) $request->input('store_id')];
        }

        $reelStatuses = array_values(array_filter((array) $request->input('reel_status', [])));
        $today = Carbon::today()->toDateString();

        $query = Reel::with(['store.storage', 'storage', 'productable'])
            ->withCount([
                'engagements as total_views' => fn (Builder $builder) => $builder->where('type', ReelEngagement::TYPE_VIEW),
                'engagements as total_likes' => fn (Builder $builder) => $builder->where('type', ReelEngagement::TYPE_LIKE),
                'engagements as total_store_visits' => fn (Builder $builder) => $builder->where('type', ReelEngagement::TYPE_VISIT),
            ])
            ->moduleWise();

        $moduleOnlyWheres = count($query->getQuery()->wheres);

        $query->when(!empty($storeIds), function ($builder) use ($storeIds) {
                $builder->whereIn('store_id', $storeIds);
            })
            ->when($request->filled('status_filter'), function ($builder) use ($request) {
                $builder->where('status', $request->status_filter === 'active' ? 1 : 0);
            })
            ->when(!empty($keywords), function ($builder) use ($keywords) {
                foreach ($keywords as $value) {
                    $builder->where(function ($subQuery) use ($value) {
                        $subQuery->where('id', 'like', "%{$value}%")
                            ->orWhere('description', 'like', "%{$value}%")
                            ->orWhereHas('store', function ($storeQuery) use ($value) {
                                $storeQuery->where('name', 'like', "%{$value}%");
                            })
                            ->orWhereHas('translations', function ($translationQuery) use ($value) {
                                $translationQuery->where('key', 'description')
                                    ->where('value', 'like', "%{$value}%");
                            });
                    });
                }
            });

        $this->applyReelStatusFilter($query, $reelStatuses, $today);
        $this->applyUploadDateFilter($query, $request);

        // Every filter above adds at least one where clause, so comparing the count against
        // the module-only baseline answers "was the list narrowed?" without maintaining a
        // second list of filter inputs that a future filter could drift out of sync with.
        $this->listIsFiltered = count($query->getQuery()->wheres) > $moduleOnlyWheres;

        return $query;
    }

    private function applySorting($query, Request $request)
    {
        return match ($request->input('sort_by')) {
            'most_viewed' => $query->orderByDesc('total_views')->latest('id'),
            'most_liked' => $query->orderByDesc('total_likes')->latest('id'),
            'most_store_visit' => $query->orderByDesc('total_store_visits')->latest('id'),
            default => $query->latest(),
        };
    }

    private function applyReelStatusFilter($query, array $statuses, string $today): void
    {
        $statuses = array_values(array_diff($statuses, ['all']));
        if (empty($statuses)) {
            return;
        }

        $query->where(function ($builder) use ($statuses, $today) {
            foreach ($statuses as $status) {
                if ($status === 'deactivated') {
                    $builder->orWhere('status', 0);
                }

                if ($status === 'live') {
                    $builder->orWhere(function ($subQuery) use ($today) {
                        $subQuery->where('status', 1)
                            ->where(function ($liveQuery) use ($today) {
                                $liveQuery->where('is_always_visible', 1)
                                    ->orWhere(function ($dateQuery) use ($today) {
                                        $dateQuery->where('is_always_visible', 0)
                                            ->whereDate('start_date', '<=', $today)
                                            ->whereDate('end_date', '>=', $today);
                                    });
                            });
                    });
                }

                if ($status === 'upcoming') {
                    $builder->orWhere(function ($subQuery) use ($today) {
                        $subQuery->where('status', 1)
                            ->where('is_always_visible', 0)
                            ->whereDate('start_date', '>', $today);
                    });
                }

                if ($status === 'expired') {
                    $builder->orWhere(function ($subQuery) use ($today) {
                        $subQuery->where('status', 1)
                            ->where('is_always_visible', 0)
                            ->whereDate('end_date', '<', $today);
                    });
                }
            }
        });
    }

    private function applyUploadDateFilter($query, Request $request): void
    {
        $filterDate = $request->input('filter_date', 'all_time');

        match ($filterDate) {
            'this_week' => $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]),
            'this_month' => $query->whereBetween('created_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()]),
            'custom' => $this->applyCustomDateFilter($query, $request),
            default => null,
        };
    }

    private function applyCustomDateFilter($query, Request $request): void
    {
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }
    }

    private function buildAnalytics($query, Request $request): array
    {
        // pluck() goes through the base query: no model hydration, so the list query's
        // eager loads, appends and withCount subselects are not repeated just to get ids.
        $reelIds = (clone $query)->pluck('id')->filter()->values();

        if ($reelIds->isEmpty()) {
            $monthlyRange = CarbonPeriod::create(Carbon::now()->subMonths(11)->startOfMonth(), '1 month', Carbon::now()->startOfMonth());

            return [
                'view_trend_categories' => collect($monthlyRange)->map(fn ($month) => $month->format('M'))->values()->all(),
                'view_trend_values' => array_fill(0, 12, 0),
                'chart_series' => [
                    'views' => array_fill(0, 12, 0),
                    'likes' => array_fill(0, 12, 0),
                    'visits' => array_fill(0, 12, 0),
                    'sell' => array_fill(0, 12, 0),
                ],
                'engagement_series' => [0, 0, 0, 0],
                'weekly_change' => [
                    'views' => '0%',
                    'likes' => '0%',
                    'visits' => '0%',
                    'sale' => '0%',
                    'sale_amount' => '0%',
                ],
            ];
        }

        $engagements = ReelEngagement::query()
            ->select(['type', 'amount', 'created_at'])
            ->whereIn('reel_id', $reelIds)
            ->get();

        $currentStart = Carbon::now()->startOfWeek();
        $currentEnd = Carbon::now()->endOfWeek();
        $previousStart = Carbon::now()->subWeek()->startOfWeek();
        $previousEnd = Carbon::now()->subWeek()->endOfWeek();

        $currentPeriod = $engagements->filter(fn ($engagement) => Carbon::parse($engagement->created_at)->between($currentStart, $currentEnd));
        $previousPeriod = $engagements->filter(fn ($engagement) => Carbon::parse($engagement->created_at)->between($previousStart, $previousEnd));

        $monthlyRange = CarbonPeriod::create(Carbon::now()->subMonths(11)->startOfMonth(), '1 month', Carbon::now()->startOfMonth());
        $chartCategories = [];
        $viewSeries = [];
        $likeSeries = [];
        $visitSeries = [];
        $sellSeries = [];

        foreach ($monthlyRange as $month) {
            $monthKey = $month->format('Y-m');
            $chartCategories[] = $month->format('M');
            $monthly = $engagements->filter(fn ($engagement) => Carbon::parse($engagement->created_at)->format('Y-m') === $monthKey);
            $viewSeries[] = $monthly->where('type', ReelEngagement::TYPE_VIEW)->count();
            $likeSeries[] = $monthly->where('type', ReelEngagement::TYPE_LIKE)->count();
            $visitSeries[] = $monthly->where('type', ReelEngagement::TYPE_VISIT)->count();
            $sellSeries[] = $monthly->where('type', ReelEngagement::TYPE_ORDER)->count();
        }

        $totalViews = $engagements->where('type', ReelEngagement::TYPE_VIEW)->count();
        $totalLikes = $engagements->where('type', ReelEngagement::TYPE_LIKE)->count();
        $totalVisits = $engagements->where('type', ReelEngagement::TYPE_VISIT)->count();
        $totalSales = $engagements->where('type', ReelEngagement::TYPE_ORDER)->count();

        return [
            'view_trend_categories' => $chartCategories,
            'view_trend_values' => $viewSeries,
            'chart_series' => [
                'views' => $viewSeries,
                'likes' => $likeSeries,
                'visits' => $visitSeries,
                'sell' => $sellSeries,
            ],
            'engagement_series' => [
                $totalViews,
                $totalLikes,
                $totalVisits,
                $totalSales,
            ],
            'weekly_change' => [
                'views' => $this->calculatePercentChange(
                    (float) $previousPeriod->where('type', ReelEngagement::TYPE_VIEW)->count(),
                    (float) $currentPeriod->where('type', ReelEngagement::TYPE_VIEW)->count()
                ),
                'likes' => $this->calculatePercentChange(
                    (float) $previousPeriod->where('type', ReelEngagement::TYPE_LIKE)->count(),
                    (float) $currentPeriod->where('type', ReelEngagement::TYPE_LIKE)->count()
                ),
                'visits' => $this->calculatePercentChange(
                    (float) $previousPeriod->where('type', ReelEngagement::TYPE_VISIT)->count(),
                    (float) $currentPeriod->where('type', ReelEngagement::TYPE_VISIT)->count()
                ),
                'sale' => $this->calculatePercentChange(
                    (float) $previousPeriod->where('type', ReelEngagement::TYPE_ORDER)->count(),
                    (float) $currentPeriod->where('type', ReelEngagement::TYPE_ORDER)->count()
                ),
                'sale_amount' => $this->calculatePercentChange(
                    (float) $previousPeriod->where('type', ReelEngagement::TYPE_ORDER)->sum('amount'),
                    (float) $currentPeriod->where('type', ReelEngagement::TYPE_ORDER)->sum('amount')
                ),
            ],
        ];
    }

    /**
     * Every overview card is an aggregate over the same module-wide engagement rows, so
     * group them once instead of running a count per type plus a separate sum.
     */
    private function getModuleEngagementTotals(): Collection
    {
        return ReelEngagement::query()
            ->whereHas('reel', function (Builder $builder) {
                $builder->moduleWise();
            })
            ->selectRaw('type, COUNT(*) as total_count, COALESCE(SUM(amount), 0) as total_amount')
            ->groupBy('type')
            ->get()
            ->keyBy('type');
    }

    private function engagementCount(Collection $totals, string $type): int
    {
        return (int) ($totals->get($type)?->total_count ?? 0);
    }

    private function calculatePercentChange(float $previous, float $current): string
    {
        if ($previous <= 0 && $current <= 0) {
            return '0%';
        }

        if ($previous <= 0) {
            return '+100%';
        }

        $change = (($current - $previous) / $previous) * 100;
        $prefix = $change > 0 ? '+' : '';

        return $prefix . number_format($change, 1) . '%';
    }

    private function getFilterCount(Request $request): int
    {
        $count = 0;

        if ($request->filled('status_filter')) {
            $count++;
        }

        if (!empty(array_filter((array) $request->input('store_ids', [])))) {
            $count++;
        }

        if (!empty(array_diff(array_filter((array) $request->input('reel_status', [])), ['all']))) {
            $count++;
        }

        if ($request->filled('sort_by') && $request->input('sort_by') !== 'all') {
            $count++;
        }

        if ($request->filled('filter_date') && $request->input('filter_date') !== 'all_time') {
            $count++;
        }

        if ($request->filled('search')) {
            $count++;
        }

        return $count;
    }

    private function fillAndPersistReel(Reel $reel, Request $request, Store $store): void
    {
        [$startDate, $endDate] = $this->parseDateRange($request);

        $reel->store_id = $store->id;
        $reel->module_id = ReelModuleConfig::isMultiModule()
            ? (int) config('module.current_module_id')
            : ReelModuleConfig::defaultModuleId();
        $reel->module_type = ReelModuleConfig::isMultiModule()
            ? (string) config('module.current_module_type')
            : ReelModuleConfig::defaultModuleType();
        $product = ReelProductableResolver::resolve($store, $request->input('product_id'));
        $reel->productable_type = $product['type'];
        $reel->productable_id = $product['id'];
        $reel->order_now_button = $request->boolean('order_now_button');
        $reel->description = $request->description[array_search('default', $request->lang)] ?? $request->description[0];
        $reel->is_always_visible = $request->boolean('is_always_visible');
        $reel->start_date = $reel->is_always_visible ? null : $startDate;
        $reel->end_date = $reel->is_always_visible ? null : $endDate;
        $reel->created_by_id = $reel->created_by_id ?? auth('admin')->id();
        $reel->created_by_type = $reel->created_by_type ?? 'App\\Models\\Admin';

        if ($request->hasFile('thumbnail')) {
            if ($reel->thumbnail) {
                app(ReelService::class)->deleteAsset($reel->thumbnail);
            }

            $reel->thumbnail = app(ReelService::class)->storeAsset($request->file('thumbnail'), 'thumbnail');
        }

        if ($request->hasFile('video')) {
            if ($reel->video) {
                app(ReelService::class)->deleteAsset($reel->video);
            }

            $reel->video = app(ReelService::class)->storeAsset($request->file('video'), 'video');
        }

        $reel->save();

        Helpers::add_or_update_translations(
            request: $request,
            key_data: 'description',
            name_field: 'description',
            model_name: Reel::class,
            data_id: $reel->id,
            data_value: $reel->description,
            model_class: true
        );
    }

    private function parseDateRange(Request $request): array
    {
        if ($request->boolean('is_always_visible') || !$request->filled('dates')) {
            return [null, null];
        }

        [$startDate, $endDate] = array_map('trim', explode(' - ', $request->dates));

        return [
            Carbon::createFromFormat('m/d/Y', $startDate)->startOfDay(),
            Carbon::createFromFormat('m/d/Y', $endDate)->endOfDay(),
        ];
    }


}
