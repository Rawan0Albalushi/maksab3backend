<?php

namespace App\Http\Controllers\Admin\Promotion;

use App\CentralLogics\Helpers;
use App\Contracts\Repositories\TranslationRepositoryInterface;
use App\Exports\BogoOfferExport;
use App\Exports\BogoOfferStoreExport;
use App\Http\Controllers\Controller;
use App\Models\AddOn;
use App\Models\BogoOffer;
use App\Models\BogoOfferStore;
use App\Models\Store;
use App\Services\Promotion\BogoOfferAdminService;
use App\Services\Promotion\PromotionNotifier;
use App\Traits\Promotion\HandlesBogoEnrollment;
use App\Traits\Promotion\HandlesPromotionEnrollment;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use App\Rules\ImageFile;

/**
 * The admin's side of BOGO: publishing offers, and deciding which stores run them.
 *
 * The offer itself is a shell -- quantities, a window, caps -- and holds no items. WHICH items
 * make up the bundle is chosen per store, because two stores on one offer will price and staff it
 * differently. So half of what this controller does is not editing offers at all; it is the
 * enrolment conversation between the admin and a store, and the rules for who may do what next
 * live in HandlesPromotionEnrollment rather than being re-derived per endpoint.
 *
 * The drawer endpoints answer JSON because the screen never navigates: adding a store, reworking
 * a selection and loading an enrolment all happen beside the table. Everything else redirects
 * with a Toastr message, like the rest of the panel.
 */
class BogoOfferController extends Controller
{
    use HandlesBogoEnrollment;
    use HandlesPromotionEnrollment;

    public function __construct(
        private readonly BogoOfferAdminService $service,
        private readonly TranslationRepositoryInterface $translationRepo,
    ) {}

    /** The create form, which is its own page rather than a card stacked above the list. */
    public function index(): View
    {
        return view('admin-views.promotions.bogo-offer.index', [
            'language' => getWebConfig('language'),
        ]);
    }

    public function getListView(Request $request): View
    {
        return view('admin-views.promotions.bogo-offer.list', [
            'offers' => $this->service->list(
                search: $request->input('search'),
                moduleId: $this->currentModuleId(),
                perPage: config('default_pagination')
            ),
        ]);
    }

    public function add(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->rules(), $this->messages());

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $offer = $this->service->store($request);

        $this->saveTranslations($request, $offer, updating: false);

        return response()->json([], 200);
    }

    public function getUpdateView(string $id): View
    {
        $offer = $this->findInCurrentModule($id, translations: true);

        return view('admin-views.promotions.bogo-offer.edit', [
            'offer' => $offer,
            'language' => getWebConfig('language'),
            // Buy and get quantities freeze once a store has built a bundle to total them: the
            // frozen item sets could no longer be reconciled against a new shape.
            'quantity_locked' => $offer->isQuantityLocked(),
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $offer = $this->findInCurrentModule($id);

        $validator = Validator::make(
            $request->all(),
            $this->rules(updating: true, quantityLocked: $offer->isQuantityLocked(), offer: $offer),
            $this->messages()
        );

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $this->service->update($offer, $request);

        $this->saveTranslations($request, $offer, updating: true);

        return response()->json([], 200);
    }

    /**
     * The offer detail screen and its store list.
     *
     * Three things are resolved for the whole page rather than per row, because each of them is
     * otherwise a query per enrolment: the visibility diagnostic, the add-on names and prices the
     * frozen lines refer to by id alone, and the stores still eligible to be invited.
     */
    public function view(Request $request, string $id): View
    {
        // Counted for the delete warning, so the admin is told how many stores go with the offer
        // before it is taken away from them. A denied request is not a store on the offer.
        // storage backs image_full_url, which the page header renders.
        $offer = BogoOffer::with('storage')->withCount([
            'enrollments as joined_count' => fn ($q) => $q->whereIn('status', [
                BogoOfferStore::STATUS_APPROVED, BogoOfferStore::STATUS_PENDING,
            ]),
        ])
            ->where('module_id', $this->currentModuleId())
            ->findOrFail($id);

        $enrollments = $this->enrollmentQuery($offer, $request->input('search'))
            ->paginate(config('default_pagination'))
            ->withQueryString();

        // Approving is not publishing, and the row said only "Approved" -- so an admin asked "why
        // is our bundle not showing?" had to open every drawer in turn to find out. The same
        // check the drawer runs, asked for the whole page at once, lets each row carry a warning
        // that names the reason on hover. Only approved rows have a customer-facing state to
        // report; every other status is still waiting on somebody, which the badge already says.
        $hiddenReasons = $this->customerVisibilityProblemsFor(
            $enrollments->getCollection()
                ->where('status', BogoOfferStore::STATUS_APPROVED)
                // The offer is the page's own and the store is loaded with the row, so neither is
                // fetched again per enrolment.
                ->each(fn (BogoOfferStore $enrollment) => $enrollment->setRelation('bogoOffer', $offer)),
            // Every enrolment on this page belongs to this one offer, so its module is already
            // known -- passing it lets primeEnrollmentItems() skip re-fetching the module once
            // per item instead of once for the page. Loaded without translations: this module is
            // only compared by id inside the visibility check below, never rendered.
            $offer->load(['module' => fn ($q) => $q->withoutTranslation()])->module
        );

        [$addOnNames, $addOnPrices] = $this->addOnLabels($enrollments->getCollection());

        return view('admin-views.promotions.bogo-offer.view', [
            'offer' => $offer,
            'enrollments' => $enrollments,
            'addOnNames' => $addOnNames,
            'addOnPrices' => $addOnPrices,
            'hiddenReasons' => $hiddenReasons,
            // A store enrols in a given offer once only, so anyone already on it is kept out of
            // the Add Store picker. Scoped to the offer's module, which is the panel's own: the
            // bundle is built from one store's items and an out-of-module store has none of them.
            'availableStores' => Store::active()
                ->where('module_id', $offer->module_id)
                ->whereNotIn('id', $offer->enrollments()->pluck('store_id'))
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    /** Drawer body for one enrolment, opened by the eye action and by the "N Items" link. */
    public function getEnrollmentView(string $id, string $enrollmentId): View
    {
        $offer = $this->findInCurrentModule($id);

        // item.storage for the same reason the list query loads it: the drawer renders every
        // frozen line, so without it each one resolves its image disk with a query of its own.
        $enrollment = BogoOfferStore::with([
            'store.storage',
            'items.item.module', 'items.item.storage',
            'buyItems.item.module', 'buyItems.item.storage',
            'getItems.item.module', 'getItems.item.storage',
        ])
            ->where('bogo_offer_id', $offer->id)
            ->findOrFail($enrollmentId);

        [$addOnNames, $addOnPrices] = $this->addOnLabels(collect([$enrollment]));

        return view('admin-views.promotions.bogo-offer.partials._enrollment_detail', [
            'offer' => $offer,
            'enrollment' => $enrollment,
            'addOnNames' => $addOnNames,
            'addOnPrices' => $addOnPrices,
            // The vendor panel already worked this out and the admin panel did not, which left
            // the admin with nothing to answer "why is our bundle not showing?" with.
            'hiddenReasons' => $enrollment->status === BogoOfferStore::STATUS_APPROVED
                ? $this->customerVisibilityProblems($enrollment, $offer)
                : [],
        ]);
    }

    public function updateStatus(string $id, int $status): RedirectResponse
    {
        $offer = $this->findInCurrentModule($id);

        $this->service->setStatus($offer, $status);

        // Switching an expired offer back on changes nothing a customer can see -- the read
        // endpoints filter on the date window -- so say so, rather than leaving the admin with a
        // success message and an offer that never appears.
        if ($status && $this->bogoOfferHasEnded($offer)) {
            Toastr::warning(translate('messages.this offer has already ended set a new duration to make it available again'));

            return back();
        }

        Toastr::success(translate('messages.BOGO offer status updated'));

        return back();
    }

    public function delete(string $id): RedirectResponse
    {
        $this->service->delete($this->findInCurrentModule($id));

        Toastr::success(translate('Deleted successfully'));

        // Not back(): deleting from the detail page would send the admin straight back to the
        // record that no longer exists, and findOrFail would 404 there.
        return redirect()->route('admin.bogo-offer.list');
    }

    /**
     * The admin putting a store on the offer, having chosen the items itself.
     *
     * It lands PENDING rather than approved. The store still gets a say -- its panel shows the
     * row as "Admin Requested", where it accepts, declines, or reworks the picks and sends them
     * back -- which is what requested_by = 'admin' distinguishes.
     */
    public function addStore(Request $request, string $id): JsonResponse
    {
        $offer = $this->findInCurrentModule($id);

        // The Add Store button is already disabled for an ended offer; this is the rule itself,
        // for anything that reaches the endpoint another way.
        if ($this->bogoOfferHasEnded($offer)) {
            return $this->enrollmentError('offer', translate('This offer has already ended'));
        }

        $validator = Validator::make($request->all(), [
            'store_id' => 'required|integer',
            'buy_items' => 'required|array|min:1',
            'get_items' => 'required|array|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $storeId = (int) $request->input('store_id');

        if (BogoOfferStore::where('bogo_offer_id', $offer->id)->where('store_id', $storeId)->exists()) {
            return $this->enrollmentError('store', translate('messages.store already enrolled in this offer'));
        }

        if ($rejection = $this->rejectSelection($offer, $request, $storeId)) {
            return $rejection;
        }

        $signature = $this->buildCombinationSignature($request->input('buy_items'), $request->input('get_items'));

        DB::transaction(function () use ($offer, $request, $storeId, $signature) {
            $enrollment = BogoOfferStore::create([
                'bogo_offer_id' => $offer->id,
                'store_id' => $storeId,
                'status' => BogoOfferStore::STATUS_PENDING,
                'requested_by' => 'admin',
                'joined_at' => now(),
                'combination_signature' => $signature,
                'checked' => 0,
            ]);

            $this->syncEnrollmentItems($enrollment, $request->input('buy_items'), $request->input('get_items'));
        });

        // After the transaction, never inside it: the invitation is a fact once it is committed,
        // and a mail server that is down must not roll one back.
        app(PromotionNotifier::class)->adminInvitedStore(PromotionNotifier::BOGO, $offer, $storeId);

        return response()->json([], 200);
    }

    /**
     * Backs both edit buttons. They are the same save with different outcomes, decided by the
     * enrolment's state rather than by the caller:
     *
     *   pending  -> "Edit & Approve"  : the store asked, the admin reworks the picks and approves.
     *   rejected -> "Edit & Resubmit" : the admin reworks a denied enrolment and sends it back as
     *                                   its own request, so the store still gets the last word on
     *                                   items it did not choose.
     *
     * Both re-run the full validation and re-freeze the snapshot, so an edited enrolment is held
     * to exactly the rules a new one is.
     */
    public function updateEnrollment(Request $request, string $id, string $enrollmentId): JsonResponse
    {
        $offer = $this->findInCurrentModule($id);
        $enrollment = BogoOfferStore::where('bogo_offer_id', $offer->id)->findOrFail($enrollmentId);

        $state = $this->enrollmentState($enrollment);

        // An admin request the store has not answered is not the admin's to re-decide, and an
        // approved enrolment is the store's own selection to change.
        if (! in_array($state, ['pending', 'rejected'], true)) {
            return $this->enrollmentError('status', translate('messages.only a pending or rejected enrolment can be edited here'));
        }

        $validator = Validator::make($request->all(), [
            'buy_items' => 'required|array|min:1',
            'get_items' => 'required|array|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        if ($rejection = $this->rejectSelection($offer, $request, (int) $enrollment->store_id, $enrollment->id)) {
            return $rejection;
        }

        $signature = $this->buildCombinationSignature($request->input('buy_items'), $request->input('get_items'));

        // A reworked denial goes back out as an admin request; a reworked pending request is the
        // admin approving what it has just edited.
        $resubmitting = $state === 'rejected';

        DB::transaction(function () use ($enrollment, $request, $signature, $resubmitting) {
            $this->syncEnrollmentItems($enrollment, $request->input('buy_items'), $request->input('get_items'));

            $enrollment->combination_signature = $signature;
            $enrollment->status = $resubmitting ? BogoOfferStore::STATUS_PENDING : BogoOfferStore::STATUS_APPROVED;
            $enrollment->requested_by = $resubmitting ? 'admin' : $enrollment->requested_by;
            // A reworked denial is a fresh invitation, so it is unread again and the vendor
            // dashboard announces it -- even if the store had already seen the old one.
            $enrollment->checked = $resubmitting ? 0 : $enrollment->checked;
            $enrollment->rejection_reason = null;
            $enrollment->rejected_by = null;
            $enrollment->save();
        });

        return response()->json([], 200);
    }

    /**
     * The admin's answer to a store's request.
     *
     * Its own request waits on the store, and a decided enrolment is reworked or removed rather
     * than re-decided -- so neither can be confirmed here.
     */
    public function updateEnrollmentStatus(Request $request, string $id, string $enrollmentId, string $status): RedirectResponse
    {
        $offer = $this->findInCurrentModule($id);
        $enrollment = BogoOfferStore::with(['store.storage', 'items.item.module'])
            ->where('bogo_offer_id', $offer->id)
            ->findOrFail($enrollmentId);

        if ($blocked = $this->adminDecisionBlockedReason($enrollment)) {
            Toastr::error($blocked);

            return back();
        }

        if ($status === BogoOfferStore::STATUS_REJECTED) {
            // A refusal owes the store a reason, so one is required and must survive trimming --
            // a field of spaces is an empty field.
            //
            // 255, matching the column and Happy Hour's identical rule. It was 1000, which the
            // textarea also allowed, and `rejection_reason` is varchar(255): with sql_mode not
            // strict on this server, a longer reason was accepted, silently truncated on write,
            // and shown back to the store cut off mid-sentence.
            $validator = Validator::make($request->all(), [
                'rejection_reason' => 'required|string|max:255',
            ], [
                'rejection_reason.required' => translate('messages.Rejection reason is required'),
            ]);

            if ($validator->fails() || trim((string) $request->input('rejection_reason')) === '') {
                Toastr::error($validator->fails()
                    ? $validator->errors()->first()
                    : translate('messages.Rejection reason is required'));

                return back();
            }

            $this->markRejected($enrollment, 'admin', $request->input('rejection_reason'));

            // A denied store is no longer selling the bundle, so no cart should still hold one of
            // its copies.
            $enrollment->strandCarts();

            app(PromotionNotifier::class)->adminRejected(PromotionNotifier::BOGO, $offer, $enrollment->store_id);

            Toastr::success(translate('messages.BOGO join request rejected'));

            return back();
        }

        if ($status !== BogoOfferStore::STATUS_APPROVED) {
            Toastr::error(translate('messages.Invalid status'));

            return back();
        }

        // The selection was validated when it was submitted, not now. Between then and this click
        // the item can have been deleted or its variation or add-on dropped, and approving that
        // publishes a bundle nobody can assemble.
        $problems = $this->enrollmentIntegrityProblems($enrollment);

        if ($problems['blocking']) {
            Toastr::error(translate('messages.this selection can no longer be built').' '.implode(' | ', $problems['blocking']));

            return back();
        }

        $enrollment->status = BogoOfferStore::STATUS_APPROVED;
        $enrollment->rejection_reason = null;
        $enrollment->rejected_by = null;
        $enrollment->joined_at ??= now();
        $enrollment->save();

        app(PromotionNotifier::class)->adminApproved(PromotionNotifier::BOGO, $offer, $enrollment->store_id);

        // Approved, but the customer will not see it until the store puts the item back -- so say
        // which item, rather than leaving the admin to wonder why a live offer is nowhere in the
        // app.
        if ($problems['warnings']) {
            Toastr::warning(translate('messages.this offer stays hidden until the item is available again')
                .' '.implode(' | ', $problems['warnings']));
        }

        Toastr::success(translate('messages.store added to bogo offer'));

        return back();
    }

    /**
     * Cancels an admin request or a denied enrolment, and removes an approved store.
     *
     * A store's pending request is rejected instead -- it is owed the refusal and a reason.
     */
    public function removeStore(string $id, string $enrollmentId): RedirectResponse
    {
        $enrollment = BogoOfferStore::where('bogo_offer_id', $id)->findOrFail($enrollmentId);

        if ($blocked = $this->adminRemoveBlockedReason($enrollment)) {
            Toastr::error($blocked);

            return back();
        }

        // Read before the delete, and only an approved store is told: cancelling an invitation
        // nobody accepted, or clearing a request already refused, is not news to the store.
        $wasApproved = $enrollment->status === BogoOfferStore::STATUS_APPROVED;
        $removedStoreId = $enrollment->store_id;

        DB::transaction(function () use ($enrollment) {
            // Nobody is left to serve these bundles, so they go with the enrolment -- the same
            // reasoning the whole-offer delete applies to every cart holding one.
            $enrollment->strandCarts();
            $enrollment->items()->delete();
            $enrollment->delete();
        });

        if ($wasApproved) {
            app(PromotionNotifier::class)->adminRemovedStore(
                PromotionNotifier::BOGO,
                BogoOffer::find($id),
                $removedStoreId
            );
        }

        Toastr::success(translate('messages.store removed from bogo offer'));

        return back();
    }

    /**
     * Feeds the "Select Item" picker in the Add Store drawer.
     *
     * Scoped to the chosen store, and flags unavailable items rather than hiding them so the
     * picker can grey them out.
     */
    public function getStoreItems(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), ['store_id' => 'required|integer']);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        // Scoped to the current module as well as to the store: the two agree for any store the
        // Add Store picker offered, and a store_id typed in by hand must not reach another
        // module's menu.
        return response()->json(
            $this->storeItemPickerOptions((int) $request->input('store_id'), $request->input('search'), $this->currentModuleId()),
            200
        );
    }

    public function exportList(Request $request)
    {
        // Same counting rule as the list, or the export would contradict the screen it came from.
        $offers = BogoOffer::withCount([
            'enrollments as enrollments_count' => fn ($q) => $q->whereIn('status', [
                BogoOfferStore::STATUS_APPROVED, BogoOfferStore::STATUS_PENDING,
            ]),
        ])
            ->where('module_id', $this->currentModuleId())
            ->when($request->input('search'), function ($query) use ($request) {
                // Grouped so the OR chain cannot escape the module filter beside it.
                $query->where(function ($q) use ($request) {
                    foreach ($this->searchWords($request->input('search')) as $word) {
                        $q->orWhere('title', 'like', "%{$word}%");
                    }
                });
            })
            ->latest()
            ->get();

        return Excel::download(
            new BogoOfferExport(['data' => $offers, 'search' => $request->input('search')]),
            'BogoOffer.'.($request->input('type') === 'csv' ? 'csv' : 'xlsx')
        );
    }

    public function exportEnrollmentList(Request $request, string $id)
    {
        $offer = $this->findInCurrentModule($id);

        $enrollments = $this->enrollmentQuery($offer, $request->input('search'))->get();

        return Excel::download(
            new BogoOfferStoreExport([
                'offer' => $offer,
                'enrollments' => $enrollments,
                'search' => $request->input('search'),
            ]),
            'BogoOfferStore.'.($request->input('type') === 'csv' ? 'csv' : 'xlsx')
        );
    }

    /**
     * The enrolment list the detail screen and its export both read.
     *
     * Shared rather than duplicated so an export can never disagree with the table it was
     * downloaded from, including the search.
     */
    private function enrollmentQuery(BogoOffer $offer, ?string $search)
    {
        // store.storage backs logo_full_url, which every row renders. Without it the table is
        // one lazy load per store, and lazy loading throws outside production.
        //
        // item.storage backs each frozen line's item_image_full_url the same way: without it
        // DescribesFrozenItemLine::sourceImageDisk() falls through to a raw `storages` lookup
        // per line, which is a query per item rendered on the page rather than one for the lot.
        // Same relation list BundleService already loads for its own frozen lines.
        //
        // `items` itself is bare -- primeEnrollmentItems() (called on this same collection, for
        // the visibility check below) re-fetches and overwrites every line's `item` relation from
        // scratch, so eager-loading `items.item.*` here would only be discarded unread.
        return BogoOfferStore::with([
            'store.storage',
            'items',
            'buyItems.item.module', 'buyItems.item.storage',
            'getItems.item.module', 'getItems.item.storage',
        ])
            ->where('bogo_offer_id', $offer->id)
            ->when($search, function ($query) use ($search) {
                $query->whereHas('store', function ($q) use ($search) {
                    foreach ($this->searchWords($search) as $word) {
                        $q->orWhere('name', 'like', "%{$word}%");
                    }
                });
            })
            ->latest();
    }

    /**
     * Trimmed and emptied out first: a padded search splits into empty pieces, each of which
     * becomes LIKE '%%' and matches every row -- so "  term  " answered with the whole list
     * instead of the one match.
     *
     * @return string[]
     */
    private function searchWords(?string $search): array
    {
        return array_values(array_filter(explode(' ', trim((string) $search))));
    }

    /**
     * Add-on names and prices for a page of enrolments, resolved once.
     *
     * The frozen lines keep add-on ids only, and an add-on shown by name alone says nothing about
     * what it adds -- so both come back together.
     *
     * @return array{0: \Illuminate\Support\Collection, 1: \Illuminate\Support\Collection}
     */
    private function addOnLabels($enrollments): array
    {
        $ids = collect($enrollments)
            ->flatMap(fn (BogoOfferStore $enrollment) => $enrollment->items)
            ->flatMap(fn ($line) => $line->add_on_ids ?? [])
            ->unique()
            ->all();

        if (! $ids) {
            return [collect(), collect()];
        }

        $addOns = AddOn::with('translations')->whereIn('id', $ids)->get(['id', 'name', 'price']);

        return [$addOns->pluck('name', 'id'), $addOns->pluck('price', 'id')];
    }

    /**
     * The two selection rules that reject an enrolment, in the order the drawer reports them.
     *
     * Returns a JSON response to hand straight back, or null when the selection is acceptable.
     */
    private function rejectSelection(BogoOffer $offer, Request $request, int $storeId, ?int $ignoreEnrollmentId = null): ?JsonResponse
    {
        $errors = $this->validateEnrollmentItems(
            $offer,
            $request->input('buy_items'),
            $request->input('get_items'),
            $storeId
        );

        if ($errors) {
            return response()->json(['errors' => $errors], 403);
        }

        $signature = $this->buildCombinationSignature($request->input('buy_items'), $request->input('get_items'));

        if ($this->combinationTaken($storeId, $signature, $ignoreEnrollmentId)) {
            return $this->enrollmentError('combination', translate('messages.this buy get combination is already used by the store'));
        }

        return null;
    }

    private function enrollmentError(string $code, string $message): JsonResponse
    {
        return response()->json(['errors' => [['code' => $code, 'message' => $message]]], 403);
    }

    /**
     * The alternate-language title and description rows.
     *
     * TranslationRepository indexes into $request[$attribute] directly, so an optional field the
     * form did not post would fatal there rather than simply having nothing to write.
     */
    private function saveTranslations(Request $request, BogoOffer $offer, bool $updating): void
    {
        foreach (['title', 'description'] as $attribute) {
            if (! is_array($request->input($attribute))) {
                continue;
            }

            $updating
                ? $this->translationRepo->updateByModel(request: $request, model: $offer, modelPath: BogoOffer::class, attribute: $attribute)
                : $this->translationRepo->addByModel(request: $request, model: $offer, modelPath: BogoOffer::class, attribute: $attribute);
        }
    }

    /**
     * The module the panel is currently in.
     *
     * Every admin screen is scoped by the header's module switcher -- CurrentModule middleware
     * puts the choice in the session and in config -- so an offer belongs to whichever module the
     * admin was looking at, exactly as a campaign or a coupon does. There is no module field on
     * the form: posting one would switch the panel rather than set an attribute, since the
     * middleware treats a module_id in the request as the switcher being used.
     */
    private function currentModuleId(): ?int
    {
        return Config::get('module.current_module_id');
    }

    /**
     * An offer of the current module, or a 404.
     *
     * Scoping the read rather than only the list is what stops an id typed into the address bar
     * reaching an offer belonging to a module the admin has not switched to -- the same rule
     * CouponService applies when it 404s a coupon from another module.
     */
    private function findInCurrentModule(string $id, bool $translations = false): BogoOffer
    {
        return BogoOffer::when($translations, fn ($q) => $q->withAllTranslations())
            ->where('module_id', $this->currentModuleId())
            ->findOrFail($id);
    }

    private function rules(bool $updating = false, bool $quantityLocked = false, ?BogoOffer $offer = null): array
    {
        $earliest = $this->earliestAllowedDate($offer);

        $rules = [
            // title[] and lang[] are parallel arrays, so the default entry is validated by index
            // -- the same shape every other multilingual form on the panel posts.
            // Not `required`: the .0 rule below already fires when the whole array is missing,
            // and both being required produced two messages for one empty field.
            'title' => 'array',
            'title.0' => 'required|string|max:30',
            'title.*' => 'nullable|string|max:30',
            'description' => 'nullable|array',
            'description.*' => 'nullable|string|max:150',
            'lang' => 'required|array',
            'image' => ImageFile::rules($updating ? 'nullable' : 'required'),
            // A window that has already passed can never serve a bundle. An edit keeps whatever
            // start the offer already had as its floor, or a run that began last week could not
            // be touched without first being dragged forward -- the same allowance Happy Hour
            // makes in earliestAllowedDate().
            'start_date' => 'required|date|after_or_equal:'.$earliest,
            // after, not after_or_equal. These carry a time, so an equal start and end is a window
            // of zero length -- an offer that is never running, which stores can still be invited
            // to and enrol in. Happy Hour's range is dates alone, where start == end is one
            // legitimate day, so the two rules differ on purpose.
            'end_date' => 'required|date|after:start_date|after_or_equal:'.$earliest,
            'usage_limit_total' => 'nullable|integer|min:1',
            'usage_limit_per_customer' => 'nullable|integer|min:1',
            // Two order types, not three. 6amMart has no dine-in concept -- PlaceOrderRequest
            // validates in:take_away,delivery,parcel -- and parcel carries nothing to bundle.
            'order_types' => (BogoOffer::orderTypesEnabled() ? 'required' : 'nullable').'|array',
            'order_types.*' => 'in:delivery,take_away',
        ];

        if (! $quantityLocked) {
            $rules['buy_qty'] = 'required|integer|min:1';
            $rules['get_qty'] = 'required|integer|min:1';
        }

        return $rules;
    }

    /**
     * Earliest date a submission may name: today, or an existing offer's own start if it began
     * before today.
     */
    private function earliestAllowedDate(?BogoOffer $offer): string
    {
        $today = now()->format('Y-m-d');
        $stored = $offer?->start_date?->format('Y-m-d');

        return $stored && $stored < $today ? $stored : $today;
    }

    private function messages(): array
    {
        return [
            'start_date.after_or_equal' => translate('messages.The offer cannot start in the past'),
            'end_date.after_or_equal' => translate('messages.The offer cannot end before it starts or in the past'),
            'title.0.required' => translate('messages.Default title is required'),
            'order_types.required' => translate('messages.At least one order type must be selected'),
            'order_types.*.in' => translate('messages.Unsupported order type'),
        ];
    }
}
