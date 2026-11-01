<?php

namespace App\Http\Controllers\Vendor\Promotion;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\HappyHour;
use App\Models\HappyHourStore;
use App\Models\Store;
use App\Services\Promotion\HappyHourScheduleService;
use App\Services\Promotion\PromotionNotifier;
use App\Services\System\BusinessSettingService;
use App\Traits\Promotion\HandlesPromotionEnrollment;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The store's side of Happy Hour: join one, answer one the admin assigned, or leave.
 *
 * Simpler than BOGO in one decisive way -- there is no selection to build, so joining is a single
 * click and there is nothing to rework. A denied store cancels and joins again rather than
 * resubmitting, which is why HandlesPromotionEnrollment's $supportsResubmit is false here.
 *
 * The store comes from the session via Helpers::get_store_id(), never from the request.
 */
class HappyHourController extends Controller
{
    use HandlesPromotionEnrollment;

    public function __construct(private readonly HappyHourScheduleService $schedule) {}

    /**
     * Happy hours this store may see: switched on, in its module, and either still to come or one
     * it already joined.
     *
     * One it joined stays after it ends, marked expired and read-only, because removing it would
     * take away the only record of what ran.
     */
    public function index(Request $request): View
    {
        $store = $this->store();

        // Records that the store has now seen these rows. It no longer silences the dashboard
        // prompt -- see the note on VendorPromotionInvitations.
        HappyHourStore::where('store_id', $store->id)->where('checked', 0)->update(['checked' => 1]);

        // storage backs cover_image_full_url, which every row renders.
        $happyHours = HappyHour::with(['storage', 'enrollments' => fn ($q) => $q->where('store_id', $store->id)])
            ->where('module_id', $store->module_id)
            ->where('status', 1)
            ->where(function ($q) use ($store) {
                $q->notEnded()->orWhereHas('enrollments', fn ($e) => $e->where('store_id', $store->id));
            })
            ->when($request->input('search'), function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    foreach (array_filter(explode(' ', trim($request->input('search')))) as $word) {
                        $q->orWhere('title', 'like', "%{$word}%");
                    }
                });
            })
            ->latest()
            ->paginate(config('default_pagination'))
            ->withQueryString();

        $happyHours->getCollection()->transform(function (HappyHour $happyHour) use ($store) {
            $enrollment = $happyHour->enrollments->first();
            $ended = $happyHour->hasEnded();

            // What a CUSTOMER would see right now, which is a different question from where the
            // request stands with the admin.
            $happyHour->visibility = $this->happyHourCustomerVisibility($enrollment, $happyHour, $store);

            $happyHour->has_ended = $ended;
            $happyHour->is_running = $happyHour->isRunningNow();
            // No resubmit: a happy hour carries no selection to rework.
            $happyHour->actions = $this->happyHourActions($enrollment, $ended);
            $happyHour->state_label = $this->enrollmentStateLabel($enrollment, $ended);
            $happyHour->rejection_note = $this->enrollmentRejectionNote($enrollment, 'happy_hour');

            return $happyHour;
        });

        // Decides one line of the join confirmation: the promise that a Pro Member keeps their own
        // discount is only worth making where Pro Member is switched on.
        $proMemberEnabled = (bool) app(BusinessSettingService::class)->value('pro_member_status', false);

        return view('vendor-views.promotions.happy-hour.list', compact('happyHours', 'store', 'proMemberEnabled'));
    }

    /** Drawer body for one happy hour, with this store's own enrolment if it has one. */
    public function getDetailView(string $id): View
    {
        $store = $this->store();
        $happyHour = $this->findInStoreModule($id, $store);
        $happyHour->loadMissing('storage');

        $enrollment = HappyHourStore::where('happy_hour_id', $happyHour->id)
            ->where('store_id', $store->id)
            ->first();

        $ended = $happyHour->hasEnded();

        $happyHour->has_ended = $ended;
        $happyHour->is_running = $happyHour->isRunningNow();
        $happyHour->actions = $this->happyHourActions($enrollment, $ended);

        // "Approved" says the admin agreed; it does not say the discount is reaching customers.
        // A happy hour rides on the store card, so a closed store is not a problem here -- which
        // is what the false tells promotionStoreProblems().
        $visibility = $this->happyHourCustomerVisibility($enrollment, $happyHour, $store);
        $happyHour->visibility = $visibility;
        $hiddenReasons = $visibility['reasons'];

        return view('vendor-views.promotions.happy-hour.partials._detail_drawer', compact(
            'happyHour', 'enrollment', 'hiddenReasons'
        ));
    }

    /** Store-initiated join. Lands pending for the admin to approve. */
    public function join(string $id): RedirectResponse
    {
        $store = $this->store();
        $happyHour = $this->findInStoreModule($id, $store);

        if ($happyHour->hasEnded() || ! $happyHour->status) {
            Toastr::error(translate('This happy hour has already ended'));

            return back();
        }

        $existing = HappyHourStore::where('happy_hour_id', $happyHour->id)
            ->where('store_id', $store->id)
            ->first();

        if ($existing) {
            Toastr::warning(match ($this->enrollmentState($existing)) {
                'pending' => translate('messages.you have already requested to join this happy hour'),
                'approved' => translate('messages.you have already joined this happy hour'),
                default => translate('messages.you have already answered this happy hour'),
            });

            return back();
        }

        // Two happy hours the store is committed to must not run at once, or the pricing side
        // silently picks whichever it finds first.
        if ($clash = $this->overlapWithCommitted($happyHour, $store)) {
            Toastr::warning(translate('messages.This happy hour overlaps one you are already in')
                .' : '.$clash['title'].' ('.$clash['window'].')');

            return back();
        }

        HappyHourStore::create([
            'happy_hour_id' => $happyHour->id,
            'store_id' => $store->id,
            'status' => HappyHourStore::STATUS_PENDING,
            'requested_by' => 'store',
            'joined_at' => now(),
            'checked' => 0,
        ]);

        app(PromotionNotifier::class)->storeRequestedJoin(PromotionNotifier::HAPPY_HOUR, $happyHour, $store);

        Toastr::success(translate('messages.your request to join the happy hour has been sent'));

        return back();
    }

    /**
     * The store's answer to a happy hour the admin assigned it.
     *
     * Only an admin-initiated pending row can be answered -- a store cannot approve a request it
     * raised itself.
     */
    public function respond(Request $request, string $id, string $status): RedirectResponse
    {
        if (! in_array($status, [HappyHourStore::STATUS_APPROVED, HappyHourStore::STATUS_REJECTED], true)) {
            abort(404);
        }

        $store = $this->store();
        $happyHour = $this->findInStoreModule($id, $store);

        $enrollment = HappyHourStore::where('happy_hour_id', $happyHour->id)
            ->where('store_id', $store->id)
            ->firstOrFail();

        if ($blocked = $this->respondBlockedReason($enrollment, translate('messages.there is no pending admin request for this happy hour'))) {
            Toastr::error($blocked);

            return back();
        }

        if ($happyHour->hasEnded()) {
            Toastr::error(translate('This happy hour has already ended'));

            return back();
        }

        if ($status === HappyHourStore::STATUS_REJECTED) {
            $request->validate(['rejection_reason' => 'nullable|string|max:255']);

            $this->markRejected($enrollment, 'store', $request->input('rejection_reason'));

            app(PromotionNotifier::class)->storeDeclinedInvitation(PromotionNotifier::HAPPY_HOUR, $happyHour, $store);

            Toastr::success(translate('messages.you have declined the happy hour'));

            return back();
        }

        // Accepting is joining, so the same overlap rule applies as it does to a join.
        if ($clash = $this->overlapWithCommitted($happyHour, $store)) {
            Toastr::warning(translate('messages.This happy hour overlaps one you are already in')
                .' : '.$clash['title'].' ('.$clash['window'].')');

            return back();
        }

        $enrollment->status = HappyHourStore::STATUS_APPROVED;
        $enrollment->rejection_reason = null;
        $enrollment->rejected_by = null;
        $enrollment->joined_at ??= now();
        $enrollment->save();

        app(PromotionNotifier::class)->enrollmentWentLive(PromotionNotifier::HAPPY_HOUR, $happyHour, $store);

        Toastr::success(translate('messages.you have joined the happy hour'));

        return back();
    }

    /**
     * Backs both Cancel Request and Leave -- the same delete, differing only in what the store is
     * told. An unanswered admin request is neither: it is responded to.
     */
    public function leave(string $id): RedirectResponse
    {
        $store = $this->store();

        $enrollment = HappyHourStore::where('happy_hour_id', $id)
            ->where('store_id', $store->id)
            ->firstOrFail();

        if ($blocked = $this->storeDeleteBlockedReason($enrollment)) {
            Toastr::error($blocked);

            return back();
        }

        $wasApproved = $this->enrollmentState($enrollment) === HappyHourStore::STATUS_APPROVED;

        DB::transaction(fn () => $enrollment->delete());

        app(PromotionNotifier::class)->storeWithdrew(PromotionNotifier::HAPPY_HOUR, HappyHour::find($id), $store);

        Toastr::success($wasApproved
            ? translate('messages.you have left the happy hour')
            : translate('Your request has been canceled'));

        return back();
    }

    /**
     * What the store may do. Resubmit is off: a happy hour has no selection to rework, so a denied
     * store cancels and joins again.
     */
    private function happyHourActions(?HappyHourStore $enrollment, bool $ended): array
    {
        $actions = $this->enrollmentActions($enrollment, ! $ended, supportsResubmit: false);

        return array_merge($actions, [
            'can_join' => $actions['can_join'] && ! $ended,
            'can_respond' => $actions['can_respond'] && ! $ended,
        ]);
    }

    /** The happy hours this store is already committed to, compared against the one in hand. */
    private function overlapWithCommitted(HappyHour $happyHour, Store $store): ?array
    {
        $committed = HappyHourStore::where('store_id', $store->id)
            ->whereIn('status', [HappyHourStore::STATUS_PENDING, HappyHourStore::STATUS_APPROVED])
            ->where('happy_hour_id', '!=', $happyHour->id)
            ->pluck('happy_hour_id')
            ->all();

        return $this->schedule->overlapWith($happyHour, $committed);
    }

    private function findInStoreModule(string $id, Store $store): HappyHour
    {
        return HappyHour::where('module_id', $store->module_id)->findOrFail($id);
    }

    private function store(): Store
    {
        return Helpers::get_store_data() ?? abort(404);
    }
}
