<?php

namespace App\Traits\Promotion;

use App\Services\Store\StoreScheduleService;
use App\Services\Store\StoreService;

/**
 * The enrolment state machine shared by BOGO and Happy Hour, on both panels and in the API.
 *
 * Both features hang a store off a promotion through the same pivot -- status, requested_by,
 * rejected_by -- so "what may each side do next" is one question, answered here once rather than
 * re-derived per panel.
 *
 * Two directions share the pivot and are told apart by requested_by:
 *   store -> pending, the admin decides      (the vendor panel says "Pending")
 *   admin -> pending, the store decides      (the vendor panel says "Admin Requested")
 *
 *   state             store                             admin
 *   not_joined        join                              add store
 *   admin_requested   approve, reject                   cancel
 *   pending           cancel                            approve, edit & approve, reject
 *   approved          edit & resubmit, leave            remove
 *   rejected          edit & resubmit, cancel           edit & resubmit, cancel
 *
 * Every removal is total, so rejoining is always a brand-new request. Edit & Resubmit is BOGO's
 * alone: Happy Hour has no item selection to rework.
 */
trait HandlesPromotionEnrollment
{
    /**
     * One word for what each side is looking at, so no caller has to combine status with
     * requested_by itself and get the admin-requested case wrong.
     */
    protected function enrollmentState($enrollment): string
    {
        if (! $enrollment) {
            return 'not_joined';
        }

        if ($enrollment->status === 'pending') {
            return $enrollment->requested_by === 'admin' ? 'admin_requested' : 'pending';
        }

        return $enrollment->status;
    }

    /**
     * What this store may do right now -- the vendor panel's buttons, as data.
     *
     * can_cancel and can_leave hit the same endpoint and both delete the row; they are separate
     * flags because they are separate words to the vendor.
     */
    protected function enrollmentActions($enrollment, bool $isEligible, bool $supportsResubmit = true): array
    {
        $state = $this->enrollmentState($enrollment);

        return [
            'can_join' => $state === 'not_joined' && $isEligible,
            // An admin request is answered as it stands, never edited: a store that wants
            // different items rejects it first and then reworks it.
            'can_respond' => $state === 'admin_requested',
            'can_resubmit' => $supportsResubmit
                && in_array($state, ['approved', 'rejected'], true)
                && $isEligible,
            'can_cancel' => in_array($state, ['pending', 'rejected'], true),
            'can_leave' => $state === 'approved',
        ];
    }

    /**
     * Why the store may not delete this row, or null when it may.
     *
     * An unanswered admin request is declined rather than deleted: dropping the row would discard
     * what the admin chose and leave no record that it was refused.
     */
    protected function storeDeleteBlockedReason($enrollment): ?string
    {
        if (! $enrollment) {
            return translate('No data found');
        }

        return $this->enrollmentState($enrollment) === 'admin_requested'
            ? translate('messages.Respond to the admin request instead of leaving')
            : null;
    }

    /** Only an admin-initiated pending row can be answered -- a store cannot approve its own request. */
    protected function respondBlockedReason($enrollment, string $notPendingMessage): ?string
    {
        return (! $enrollment || $this->enrollmentState($enrollment) !== 'admin_requested')
            ? $notPendingMessage
            : null;
    }

    /**
     * The store reworking its own selection: after a denial, or to change what is already running.
     *
     * An approved row goes back to pending and stops applying until the admin approves the new
     * selection -- only approved enrolments are live.
     */
    protected function storeResubmitBlockedReason($enrollment): ?string
    {
        if (! $enrollment) {
            return translate('No data found');
        }

        return in_array($this->enrollmentState($enrollment), ['approved', 'rejected'], true)
            ? null
            : translate('messages.Only a rejected or running enrolment can be edited');
    }

    /**
     * The admin decides only what the store asked for. Its own request waits on the store, and a
     * decided row is reworked or cancelled rather than re-decided.
     */
    protected function adminDecisionBlockedReason($enrollment): ?string
    {
        if (! $enrollment) {
            return translate('No data found');
        }

        return match ($this->enrollmentState($enrollment)) {
            'pending' => null,
            'admin_requested' => translate('messages.This request is waiting on the store'),
            default => translate('messages.Only a pending request can be approved or rejected'),
        };
    }

    /**
     * The admin reworking a denied enrolment. It goes back out as an admin request, so the store
     * still gets the last word on items it did not choose.
     */
    protected function adminResubmitBlockedReason($enrollment): ?string
    {
        if (! $enrollment) {
            return translate('No data found');
        }

        return $this->enrollmentState($enrollment) === 'rejected'
            ? null
            : translate('messages.Only a rejected enrolment can be resubmitted');
    }

    /**
     * Why the admin may not delete this row, or null when it may.
     *
     * A store's pending request is rejected, never deleted: the store is owed the refusal and its
     * reason.
     */
    protected function adminRemoveBlockedReason($enrollment): ?string
    {
        if (! $enrollment) {
            return translate('No data found');
        }

        return $this->enrollmentState($enrollment) === 'pending'
            ? translate('messages.Reject the request instead of removing it')
            : null;
    }

    /**
     * Rejecting records who said no.
     *
     * requested_by cannot answer that once both sides can resubmit, because a resubmit flips the
     * direction the row was raised from.
     */
    protected function markRejected($enrollment, string $by, ?string $reason): void
    {
        $enrollment->status = 'rejected';
        $enrollment->rejected_by = $by;
        // Trimmed, so a field of spaces stores as nothing rather than as whitespace.
        $enrollment->rejection_reason = $reason ? trim($reason) : null;
        $enrollment->save();
    }

    /**
     * The words the panels put on screen for a state, so the apps say them too.
     *
     * $expired replaces the whole label rather than qualifying it: the state is a live
     * negotiation, and reading one beside a finished promotion sends the vendor chasing something
     * already over.
     */
    protected function enrollmentStateLabel($enrollment, bool $expired = false): string
    {
        if ($expired) {
            return translate('messages.Expired');
        }

        return match ($this->enrollmentState($enrollment)) {
            'not_joined' => translate('messages.Not joined yet'),
            'admin_requested' => translate('Admin requested'),
            'pending' => translate('messages.Pending'),
            'approved' => translate('messages.Approved'),
            'rejected' => translate('messages.Rejected'),
            default => translate($enrollment->status),
        };
    }

    /**
     * Who said no, and why, in the one line the panels put on a denied row.
     *
     * The two directions land in the same state and need different words -- a denial the store
     * issued itself must not read as the admin's. $promotion only decides how the store's own
     * refusal is worded; the admin's reads the same either way.
     */
    protected function enrollmentRejectionNote($enrollment, string $promotion = 'bogo'): ?string
    {
        if (! $enrollment || $this->enrollmentState($enrollment) !== 'rejected') {
            return null;
        }

        // Written for the STORE, because that is who reads this note: it is served to the vendor
        // panel and the vendor API. It used to say "You denied this request" for an
        // admin-rejected enrolment — the admin's own wording, borrowed from the admin store
        // table — so a vendor was told they had denied their own request. The vendor blade
        // already said "The admin denied this request"; the API did not.
        $who = $enrollment->rejected_by === 'store'
            ? ($promotion === 'happy_hour'
                ? translate('messages.The store declined this happy hour')
                : translate('messages.The store declined this offer'))
            : translate('messages.The admin denied this request');

        return trim($who.($enrollment->rejection_reason ? ' : '.$enrollment->rejection_reason : ''));
    }

    /**
     * The store side of "can a customer see anything this store is running".
     *
     * Every condition here is on the STORE rather than on the promotion, so both features ask it
     * -- but they do not ask the same thing about a closed store, which is why that one is a
     * parameter:
     *
     *   BOGO       hides the offer outright while the store is closed, because the catalog
     *              filters servable stores on whether they are trading.
     *   Happy Hour does not -- the discount rides on the store card and a closed store is still
     *              listed -- so reporting it would name a problem that is not one.
     *
     * @return string[]
     */
    protected function promotionStoreProblems($store, bool $hiddenWhenClosed): array
    {
        $reasons = [];

        if (! $store->status) {
            $reasons[] = translate('messages.The store is switched off');
        } elseif (! app(StoreService::class)->hasOrderAllowance($store)) {
            // Store::active() -- which every customer-side query runs the store through -- asks
            // this too, and so does Item::active() for each item. Without naming it here the
            // panel blames the items instead: a store out of subscription orders had every one
            // of its items reported "currently unavailable", which sent the vendor looking at a
            // menu that was perfectly fine.
            $reasons[] = translate('messages.The stores subscription has no orders left');
        }

        if ($hiddenWhenClosed && ! app(StoreScheduleService::class)->isOpenNow($store)) {
            $reasons[] = translate('messages.The store is closed right now');
        }

        return $reasons;
    }

    /**
     * The same live state for a happy hour.
     *
     * Simpler than BOGO's because a happy hour has no item selection to go stale: what can hide an
     * approved window is the store itself, plus the window's own dates. A closed store is NOT a
     * problem here -- the discount rides on the store card, and a closed store is still listed --
     * which is what the false says.
     *
     * @return array{status: string, label: string|null, reasons: string[]}
     */
    protected function happyHourCustomerVisibility($enrollment, $happyHour, $store): array
    {
        if (! $enrollment || $enrollment->status !== 'approved') {
            return $this->promotionVisibility('not_applicable');
        }

        if ($happyHour->hasEnded()) {
            return $this->promotionVisibility('ended', [translate('messages.This happy hour has already ended')]);
        }

        if ($problems = $this->promotionStoreProblems($store, hiddenWhenClosed: false)) {
            return $this->promotionVisibility('not_visible', $problems);
        }

        // In date and nothing in the way, but not this window's turn until its hours come round.
        return $happyHour->isRunningNow()
            ? $this->promotionVisibility('running')
            : $this->promotionVisibility('scheduled');
    }

    /**
     * One word for what a customer would see of this enrolment right now, with the reasons behind
     * it.
     *
     * Deliberately separate from enrollmentState(), which says where the request stands between
     * the admin and the store. "Approved" is not "running".
     *
     *   not_applicable - not approved, so there is nothing to be visible yet; the enrolment
     *                    state is the whole story and reasons stay empty
     *   ended          - the promotion's own window is behind us
     *   scheduled      - nothing is wrong, it is simply not this promotion's turn yet
     *   not_visible    - approved and in date, and a customer still cannot see it
     *   running        - a customer can see it right now
     *
     * @param  string[]  $reasons
     * @return array{status: string, label: string|null, reasons: string[]}
     */
    protected function promotionVisibility(string $status, array $reasons = []): array
    {
        return [
            'status' => $status,
            'label' => match ($status) {
                'running' => translate('messages.Running now'),
                'scheduled' => translate('messages.Scheduled'),
                'ended' => translate('messages.Ended'),
                'not_visible' => translate('messages.Not visible to customers'),
                default => null,
            },
            'reasons' => array_values($reasons),
        ];
    }
}
