<?php

namespace App\Http\Controllers\Admin\Promotion;

use App\CentralLogics\Helpers;
use App\Contracts\Repositories\TranslationRepositoryInterface;
use App\Exports\HappyHourExport;
use App\Exports\HappyHourStoreExport;
use App\Http\Controllers\Controller;
use App\Models\HappyHour;
use App\Models\HappyHourStore;
use App\Models\Store;
use App\Services\Promotion\HappyHourAdminService;
use App\Services\Promotion\PromotionNotifier;
use App\Services\Promotion\HappyHourConflict;
use App\Traits\Promotion\HandlesPromotionEnrollment;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use App\Rules\ImageFile;

/**
 * The admin's side of Happy Hour: publishing discount windows and deciding who runs them.
 *
 * Two things make this unlike the other promotion screens.
 *
 * First, saving is not a plain write. A happy hour must not overlap another in the same module,
 * the overlap is only knowable once the schedule is expanded into dates, and expanding needs the
 * saved row -- so the save, the check and the rollback all happen under one lock inside the
 * service. This controller's job on the way out is to tell an overlap (409, rendered as an
 * inline alert because a toast is too easy to miss) apart from a lost lock (also 409, but merely
 * retryable) apart from a real failure.
 *
 * Second, a running window is protected: it may not be switched off or deleted while customers
 * are mid-basket at its rate. Turning one ON is never blocked -- that only ever adds a discount.
 */
class HappyHourController extends Controller
{
    use HandlesPromotionEnrollment;

    public function __construct(
        private readonly HappyHourAdminService $service,
        private readonly TranslationRepositoryInterface $translationRepo,
    ) {}

    public function index(): View
    {
        return view('admin-views.promotions.happy-hour.index', $this->formData());
    }

    public function getListView(Request $request): View
    {
        return view('admin-views.promotions.happy-hour.list', [
            'happyHours' => $this->service->list(
                search: $request->input('search'),
                moduleId: $this->currentModuleId(),
                perPage: config('default_pagination')
            ),
        ]);
    }

    public function add(Request $request): JsonResponse
    {
        $validator = $this->buildValidator($request);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        try {
            $happyHour = $this->service->store($request);
        } catch (HappyHourConflict $e) {
            return $this->conflictResponse($e->getMessage());
        }

        if (! $happyHour) {
            return $this->lockResponse();
        }

        $this->saveTranslations($request, $happyHour, updating: false);

        return response()->json([], 200);
    }

    public function getUpdateView(string $id): View|RedirectResponse
    {
        $happyHour = HappyHour::withAllTranslations()
            ->with('module')
            ->where('module_id', $this->currentModuleId())
            ->findOrFail($id);

        // Guarded like update() and delete(). Opening the form on a live window invites an edit
        // that cannot be saved, so the refusal happens here rather than after the admin has
        // reworked the whole schedule.
        if ($happyHour->isRunningNow()) {
            Toastr::error(translate('An ongoing happy hour cannot be edited'));

            return redirect()->route('admin.happy-hour.list');
        }

        return view('admin-views.promotions.happy-hour.edit', $this->formData() + [
            'happyHour' => $happyHour,
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $happyHour = $this->findInCurrentModule($id);

        if ($happyHour->isRunningNow()) {
            return response()->json(['errors' => [[
                'code' => 'status',
                'message' => translate('An ongoing happy hour cannot be edited'),
            ]]], 403);
        }

        $validator = $this->buildValidator($request, $happyHour);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        try {
            $saved = $this->service->update($happyHour, $request);
        } catch (HappyHourConflict $e) {
            return $this->conflictResponse($e->getMessage());
        }

        if (! $saved) {
            return $this->lockResponse();
        }

        $this->saveTranslations($request, $happyHour, updating: true);

        return response()->json([], 200);
    }

    public function view(Request $request, string $id): View
    {
        // storage backs cover_image_full_url, which the view's banner renders.
        $happyHour = HappyHour::with(['module', 'dates', 'storage'])
            ->where('module_id', $this->currentModuleId())
            ->findOrFail($id);

        $enrollments = $this->enrollmentQuery($happyHour, $request->input('search'))
            ->paginate(config('default_pagination'))
            ->withQueryString();

        return view('admin-views.promotions.happy-hour.view', [
            'happyHour' => $happyHour,
            'enrollments' => $enrollments,
            // A happy hour applies to its own module, so any store in that module can be enrolled
            // -- and which zones the window ends up reaching follows from which of them do. Anyone
            // already on it is kept out of the picker: the pivot is unique per (happy_hour, store).
            'availableStores' => Store::active()
                ->where('module_id', $happyHour->module_id)
                ->whereNotIn('id', $happyHour->enrollments()->pluck('store_id'))
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function updateStatus(string $id, int $status): RedirectResponse
    {
        $happyHour = $this->findInCurrentModule($id);

        // Switching a live window off drops every store in it back to full price mid-session,
        // while customers are holding baskets priced at the happy hour rate. Turning one on is
        // left alone.
        //
        // isRunningNow() reads the schedule alone, so a switched-off window inside its hours still
        // answers true -- which is why the guard is on the DIRECTION of the change, not on the
        // schedule by itself.
        if (! $status && $happyHour->status && $happyHour->isRunningNow()) {
            Toastr::error(translate('An ongoing happy hour cannot be turned off'));

            return back();
        }

        $this->service->setStatus($happyHour, $status);

        Toastr::success(translate('messages.Happy hour status updated'));

        return back();
    }

    public function delete(string $id): RedirectResponse
    {
        $happyHour = $this->findInCurrentModule($id);

        // Same protection as updateStatus, and for the same reason: deleting a live window takes
        // its enrolments with it, so every store in it jumps back to full price mid-session and
        // the promotion leaves no trace of having run.
        if ($happyHour->isRunningNow()) {
            Toastr::error(translate('An ongoing happy hour cannot be deleted'));

            return back();
        }

        $this->service->delete($happyHour);

        Toastr::success(translate('Deleted successfully'));

        // Not back(): deleting from the detail page would send the admin straight back to the
        // record that no longer exists, and findOrFail would 404 there.
        return redirect()->route('admin.happy-hour.list');
    }

    /**
     * Enrol one or more stores.
     *
     * The picker is multi-select, because joining a happy hour needs no per-store setup -- unlike
     * BOGO, where joining means building a bundle. So a batch is the normal case and a single pick
     * is a batch of one.
     *
     * A batch is deliberately not all-or-nothing: an admin selecting twelve stores should not lose
     * the eleven good ones because the twelfth was enrolled by its vendor in the meantime. The
     * ineligible picks are named back and the rest go in.
     */
    public function addStore(Request $request, string $id): JsonResponse
    {
        $happyHour = $this->findInCurrentModule($id);

        // Nothing can be enrolled into a happy hour that is already over -- it would never
        // discount a single order. hasEnded() rather than end_date, because a custom schedule has
        // no end_date at all and every one of them would otherwise read as live.
        if ($happyHour->hasEnded()) {
            return response()->json(['errors' => [[
                'code' => 'happy_hour',
                'message' => translate('This happy hour has already ended'),
            ]]], 403);
        }

        $validator = Validator::make($request->all(), [
            'store_ids' => 'required|array|min:1',
            'store_ids.*' => 'required|integer',
        ], [
            'store_ids.required' => translate('messages.Please select a store'),
            'store_ids.array' => translate('messages.Please select a store'),
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $ids = array_values(array_unique(array_map('intval', $request->input('store_ids'))));

        // One query each: the loop below decides per store but must not issue a query per store
        // to do it.
        $stores = Store::whereIn('id', $ids)->get(['id', 'name', 'module_id'])->keyBy('id');
        $enrolled = HappyHourStore::where('happy_hour_id', $happyHour->id)
            ->whereIn('store_id', $ids)
            ->pluck('store_id')
            ->all();

        $addable = [];
        $skipped = [];

        foreach ($ids as $storeId) {
            $store = $stores->get($storeId);

            if (! $store) {
                $skipped[] = ['name' => '#'.$storeId, 'message' => translate('No data found')];

                continue;
            }

            // A happy hour belongs to one module, so a pick outside it is refused here rather than
            // silently enrolled into something the window can never apply to.
            if ((int) $store->module_id !== (int) $happyHour->module_id) {
                $skipped[] = ['name' => $store->name, 'message' => translate('messages.store is not in this happy hours module')];

                continue;
            }

            if (in_array($store->id, $enrolled)) {
                $skipped[] = ['name' => $store->name, 'message' => translate('messages.store already enrolled in this happy hour')];

                continue;
            }

            $addable[] = $store;
        }

        // Nothing survived, so the whole batch is an error rather than a partial success -- the
        // admin gets the same shaped response a single bad pick produces.
        if (! $addable) {
            return response()->json([
                'errors' => array_map(
                    fn ($row) => ['code' => 'store', 'message' => $row['name'].' - '.$row['message']],
                    $skipped
                ),
            ], 403);
        }

        DB::transaction(function () use ($addable, $happyHour) {
            foreach ($addable as $store) {
                // The store gets a say: an admin assignment lands pending and shows in the vendor
                // panel as "Admin Requested" for it to accept or deny.
                HappyHourStore::create([
                    'happy_hour_id' => $happyHour->id,
                    'store_id' => $store->id,
                    'status' => HappyHourStore::STATUS_PENDING,
                    'requested_by' => 'admin',
                    'joined_at' => now(),
                    'checked' => 0,
                ]);
            }
        });

        // After the transaction, never inside it: the invitations are facts once committed, and a
        // mail server that is down must not roll them back.
        foreach ($addable as $store) {
            app(PromotionNotifier::class)->adminInvitedStore(PromotionNotifier::HAPPY_HOUR, $happyHour, $store->id);
        }

        return response()->json(['added' => count($addable), 'skipped' => $skipped], 200);
    }

    /**
     * The admin's answer to a store's request.
     *
     * Its own request waits on the store, and a decided enrolment is cancelled rather than
     * re-decided -- so neither can be confirmed here. There is nothing to rework in a happy hour,
     * so a denied store cancels and joins again rather than resubmitting.
     */
    public function updateEnrollmentStatus(Request $request, string $id, string $enrollmentId, string $status): RedirectResponse
    {
        $enrollment = HappyHourStore::where('happy_hour_id', $id)->findOrFail($enrollmentId);

        if ($blocked = $this->adminDecisionBlockedReason($enrollment)) {
            Toastr::error($blocked);

            return back();
        }

        if ($status === HappyHourStore::STATUS_REJECTED) {
            // A refusal owes the store a reason, so one is required and must survive trimming --
            // a field of spaces is an empty field.
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

            $applied = $this->service->applyPendingDecision($enrollment, [
                'status' => HappyHourStore::STATUS_REJECTED,
                'rejected_by' => 'admin',
                'rejection_reason' => trim((string) $request->input('rejection_reason')) ?: null,
            ]);
        } elseif ($status === HappyHourStore::STATUS_APPROVED) {
            $applied = $this->service->applyPendingDecision($enrollment, [
                'status' => HappyHourStore::STATUS_APPROVED,
                'rejection_reason' => null,
                'rejected_by' => null,
                'joined_at' => $enrollment->joined_at ?? now(),
            ]);
        } else {
            Toastr::error(translate('messages.Invalid status'));

            return back();
        }

        // Somebody answered this request between the check above and the write. The row is
        // whatever they made it, so this admin is shown the state rather than a success they did
        // not cause -- and the store is not told twice.
        if (! $applied) {
            Toastr::error(translate('messages.Only a pending request can be approved or rejected'));

            return back();
        }

        // Below the $applied guard on purpose: that is what makes the store told exactly once.
        //
        // $happyHour used to be referenced here and was never defined in this method, so BOTH
        // branches raised "Undefined variable $happyHour" AFTER the status had already been
        // written: the enrolment flipped, the admin got a 500 instead of the success toast, and
        // the store was never told. Taken from the enrolment's own relation, which is the offer
        // the decision was made on by definition. (BogoOfferController passes its $offer here.)
        $happyHour = $enrollment->happyHour;

        $notifier = app(PromotionNotifier::class);
        $status === HappyHourStore::STATUS_REJECTED
            ? $notifier->adminRejected(PromotionNotifier::HAPPY_HOUR, $happyHour, $enrollment->store_id)
            : $notifier->adminApproved(PromotionNotifier::HAPPY_HOUR, $happyHour, $enrollment->store_id);

        Toastr::success($status === HappyHourStore::STATUS_REJECTED
            ? translate('messages.Happy hour join request rejected')
            : translate('messages.store added to happy hour'));

        return back();
    }

    /**
     * Cancels an admin request or a denied enrolment, and removes an approved store.
     *
     * A store's pending request is rejected instead -- it is owed the refusal and a reason.
     */
    public function removeStore(string $id, string $enrollmentId): RedirectResponse
    {
        $enrollment = HappyHourStore::where('happy_hour_id', $id)->findOrFail($enrollmentId);

        if ($blocked = $this->adminRemoveBlockedReason($enrollment)) {
            Toastr::error($blocked);

            return back();
        }

        // Read before the delete, and only an approved store is told: cancelling an invitation
        // nobody accepted, or clearing a request already refused, is not news to the store.
        $wasApproved = $enrollment->status === HappyHourStore::STATUS_APPROVED;
        $removedStoreId = $enrollment->store_id;

        $enrollment->delete();

        if ($wasApproved) {
            app(PromotionNotifier::class)->adminRemovedStore(
                PromotionNotifier::HAPPY_HOUR,
                HappyHour::find($id),
                $removedStoreId
            );
        }

        Toastr::success(translate('messages.store removed from happy hour'));

        return back();
    }

    public function exportList(Request $request)
    {
        // The export is the whole list rather than the page the admin happened to be on, but the
        // same counting rule as the screen -- otherwise the file would contradict what it was
        // downloaded from.
        $happyHours = HappyHour::withCount([
            'enrollments as enrollments_count' => fn ($q) => $q->whereIn('status', [
                HappyHourStore::STATUS_APPROVED, HappyHourStore::STATUS_PENDING,
            ]),
        ])
            ->with('module')
            ->where('module_id', $this->currentModuleId())
            ->when($request->input('search'), function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    foreach (array_filter(explode(' ', trim($request->input('search')))) as $word) {
                        $q->orWhere('title', 'like', "%{$word}%");
                    }
                });
            })
            ->latest()
            ->get();

        return Excel::download(
            new HappyHourExport(['data' => $happyHours, 'search' => $request->input('search')]),
            'HappyHour.'.($request->input('type') === 'csv' ? 'csv' : 'xlsx')
        );
    }

    public function exportEnrollmentList(Request $request, string $id)
    {
        $happyHour = $this->findInCurrentModule($id);

        return Excel::download(
            new HappyHourStoreExport([
                'happyHour' => $happyHour,
                'enrollments' => $this->enrollmentQuery($happyHour, $request->input('search'))->get(),
                'search' => $request->input('search'),
            ]),
            'HappyHourStore.'.($request->input('type') === 'csv' ? 'csv' : 'xlsx')
        );
    }

    /**
     * The enrolment list the detail screen and its export both read.
     *
     * Shared rather than duplicated so an export can never disagree with the table it was
     * downloaded from -- including the search.
     */
    private function enrollmentQuery(HappyHour $happyHour, ?string $search)
    {
        // store.storage backs logo_full_url, which every row renders. Without it the table is
        // one lazy load per store, and lazy loading throws outside production.
        return HappyHourStore::with('store.storage')
            ->where('happy_hour_id', $happyHour->id)
            ->when($search, function ($query) use ($search) {
                $query->whereHas('store', function ($q) use ($search) {
                    // Trimmed and emptied out first: a padded search splits into empty pieces,
                    // each of which becomes LIKE '%%' and matches every row.
                    foreach (array_filter(explode(' ', trim($search))) as $word) {
                        $q->orWhere('name', 'like', "%{$word}%");
                    }
                });
            })
            ->latest();
    }

    /**
     * Everything both the create and the edit form need beyond the record itself.
     *
     * Neither a module nor a zone picker. Module comes from the header's switcher, which scopes
     * every screen in the panel; zone is not part of a happy hour at all, since how far a window
     * reaches follows from which stores enrol in it.
     */
    private function formData(): array
    {
        return ['language' => getWebConfig('language')];
    }

    private function currentModuleId(): ?int
    {
        return Config::get('module.current_module_id');
    }

    /**
     * A happy hour of the current module, or a 404.
     *
     * Scoping the read rather than only the list is what stops an id typed into the address bar
     * reaching a record belonging to a module the admin has not switched to.
     */
    private function findInCurrentModule(string $id): HappyHour
    {
        return HappyHour::where('module_id', $this->currentModuleId())->findOrFail($id);
    }

    /**
     * The alternate-language title and description rows.
     *
     * TranslationRepository indexes into $request[$attribute] directly, so an optional field the
     * form did not post would fatal there rather than simply having nothing to write.
     */
    private function saveTranslations(Request $request, HappyHour $happyHour, bool $updating): void
    {
        foreach (['title', 'short_description'] as $attribute) {
            if (! is_array($request->input($attribute))) {
                continue;
            }

            $updating
                ? $this->translationRepo->updateByModel(request: $request, model: $happyHour, modelPath: HappyHour::class, attribute: $attribute)
                : $this->translationRepo->addByModel(request: $request, model: $happyHour, modelPath: HappyHour::class, attribute: $attribute);
        }
    }

    /**
     * One validator for both save paths, because they are the same form.
     *
     * The schedule builder posts flat hidden fields rather than three sets of columns, so which
     * of them is required depends on the duration type -- and the two per-entry checks that
     * cannot be expressed as field rules (custom's parallel day/time lists, and the range's own
     * ordering) run in the after() hook.
     */
    private function buildValidator(Request $request, ?HappyHour $existing = null)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'array',
            'title.0' => 'required|string|max:100',
            'title.*' => 'nullable|string|max:100',
            // Matches the form's own maxlength, so a value that got past the browser -- a
            // translation tab filled by script, a replayed request -- is refused here.
            'short_description' => 'nullable|array',
            'short_description.*' => 'nullable|string|max:150',
            'lang' => 'required|array',
            'cover_image' => ImageFile::rules($existing ? 'nullable' : 'required'),
            'icon' => ImageFile::rules($existing ? 'nullable' : 'required'),
            // gt:0, not min:0. A nought per cent happy hour takes nothing off, but every store
            // that enrols still has its own standing discount REPLACED by it -- so saving one
            // quietly puts those stores back to full price for the window.
            'discount' => 'required|numeric|gt:0|max:100',
            // Required only when the toggle is on: min_order_enabled carries that state, since the
            // checkbox itself posts nothing when off. Without this the form saved a happy hour
            // whose minimum was switched on and empty.
            'min_order_amount' => 'required_if:min_order_enabled,1|nullable|numeric|min:0',
            'duration_type' => 'required|in:daily,weekly,custom',
            // The expanded rows are per date, so a window has to end on the day it starts. 23:00
            // itself is out: the window is an hour long, so it would wrap to 00:00:00, which then
            // sorts before start_time and makes every "is it running" comparison false. The latest
            // usable start is therefore 22:59.
            'start_time' => 'required_unless:duration_type,custom|nullable|date_format:H:i|before:23:00',
            // Custom carries its dates in custom_days, and a permanent weekly rule has no range at
            // all -- it matches on weekday alone -- so neither can require one.
            'date_range' => [
                Rule::requiredIf(fn () => $request->input('duration_type') !== HappyHour::DURATION_CUSTOM
                    && ! $request->boolean('is_permanent')),
                'nullable',
                'string',
            ],
            // Comma-joined by the "Select Days" modal.
            'weekly_days' => 'required_if:duration_type,weekly|nullable|string',
            'custom_days' => 'required_if:duration_type,custom|nullable|string',
            'custom_times' => 'required_if:duration_type,custom|nullable|string',
        ], [
            'title.0.required' => translate('messages.Default title is required'),
            'start_time.before' => translate('messages.Latest start time') . ': ' . Helpers::time_format('23:00'),
        ]);

        $validator->after(function ($v) use ($request, $existing) {
            $this->validateDateRangeOrder($v, $request, $existing);

            if ($request->input('duration_type') === HappyHour::DURATION_CUSTOM) {
                $this->validateCustomSchedule($v, $request, $existing);
            }
        });

        return $validator;
    }

    private function validateDateRangeOrder($validator, Request $request, ?HappyHour $existing = null): void
    {
        if ($request->input('duration_type') === HappyHour::DURATION_CUSTOM
            || $request->boolean('is_permanent')
            || ! $request->input('date_range')) {
            return;
        }

        [$start, $end] = array_pad(explode(' - ', (string) $request->input('date_range')), 2, null);

        // A reversed range expands to nothing, which would save a happy hour that owns no dates
        // and can therefore never run.
        if ($start && $end && strtotime($end) < strtotime($start)) {
            $validator->errors()->add('date_range', translate('messages.the end date must be on or after the start date'));
        }

        $earliest = $this->earliestAllowedDate($existing);

        // Expanding into dates that have already passed writes rows that can never fire.
        if ($start && date('Y-m-d', strtotime($start)) < $earliest) {
            $validator->errors()->add('date_range', translate('messages.the schedule cannot start in the past'));
        }

        if ($end && date('Y-m-d', strtotime($end)) < $earliest) {
            $validator->errors()->add('date_range', translate('messages.the schedule cannot end in the past'));
        }
    }

    private function validateCustomSchedule($validator, Request $request, ?HappyHour $existing = null): void
    {
        $days = array_values(array_filter(explode(',', (string) $request->input('custom_days'))));
        $times = array_values(array_filter(explode(',', (string) $request->input('custom_times'))));

        // Mismatched lengths would silently drop the unpaired dates during expansion.
        if (count($days) !== count($times)) {
            $validator->errors()->add('custom_times', translate('messages.every selected day needs its own time'));

            return;
        }

        if (count($days) !== count(array_unique($days))) {
            $validator->errors()->add('custom_days', translate('messages.the same day cannot be selected twice'));
        }

        $today = now()->toDateString();
        // Dates the record already held stay valid on edit; only newly added ones have to be
        // today or later.
        $alreadyStored = array_map(fn ($d) => date('Y-m-d', strtotime($d)), $existing?->custom_days ?? []);

        foreach ($days as $day) {
            if (strtotime($day) === false) {
                $validator->errors()->add('custom_days', translate('messages.the selected days contain an invalid date'));

                break;
            }

            if (date('Y-m-d', strtotime($day)) < $today && ! in_array(date('Y-m-d', strtotime($day)), $alreadyStored, true)) {
                $validator->errors()->add('custom_days', translate('messages.the selected days cannot be in the past'));

                break;
            }
        }

        foreach ($times as $time) {
            if (! preg_match('/^\d{1,2}:\d{2}$/', $time) || strtotime($time) === false) {
                $validator->errors()->add('custom_times', translate('messages.the selected days contain an invalid time'));

                break;
            }

            if (strtotime($time) >= strtotime('23:00')) {
                $validator->errors()->add('custom_times', translate('messages.Latest start time') . ': ' . Helpers::time_format('23:00'));

                break;
            }
        }
    }

    /**
     * Earliest date a submission may name.
     *
     * Normally today, but an edit keeps whatever start the record already had -- otherwise a run
     * that began last week could not be touched without first being dragged forward.
     */
    private function earliestAllowedDate(?HappyHour $existing): string
    {
        $today = now()->toDateString();
        $stored = $existing?->start_date?->format('Y-m-d');

        return $stored && $stored < $today ? $stored : $today;
    }

    /** Code "conflict" is what the form's inline overlap alert keys on; anything else toasts. */
    private function conflictResponse(string $message): JsonResponse
    {
        return response()->json(['errors' => [['code' => 'conflict', 'message' => $message]]], 409);
    }

    /**
     * Somebody else is mid-save for this module.
     *
     * Not an overlap -- there may well be none -- so it does not get the schedule alert. It is
     * simply worth trying again in a moment, which is what the message says.
     */
    private function lockResponse(): JsonResponse
    {
        return response()->json(['errors' => [[
            'code' => 'lock',
            'message' => translate('messages.another happy hour for this module is being saved right now please try again'),
        ]]], 409);
    }
}
