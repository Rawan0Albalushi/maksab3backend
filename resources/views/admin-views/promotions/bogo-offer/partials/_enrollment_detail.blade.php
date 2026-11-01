@php
    // One word for the enrolment. An admin assignment waits on the store, so the admin
    // may only cancel it - the Approve/Reject pair belongs to requests the store raised.
    $state = $enrollment->status === 'pending'
        ? ($enrollment->requested_by === 'admin' ? 'admin_requested' : 'pending')
        : $enrollment->status;

    $statusBadge = [
        'pending'         => ['badge-soft-info', translate('messages.Pending')],
        'admin_requested' => ['badge-soft-secondary', translate('Waiting on store')],
        'approved'        => ['badge-soft-success', translate('messages.Approved')],
        'rejected'        => ['badge-soft-danger', translate('messages.Denied')],
    ][$state] ?? ['badge-soft-secondary', translate($enrollment->status)];

    // Only an approved enrolment has a customer-facing state to report: everything else is
    // still waiting on somebody, which the status badge already says.
    $live = $state !== 'approved'
        ? null
        : (empty($hiddenReasons ?? [])
            ? ['badge-soft-success', translate('messages.Running now')]
            : ['badge-soft-warning', translate('messages.Not visible to customers')]);

    // The frozen selection plus what only this panel needs. Both edit buttons post to the same
    // endpoint, which decides the outcome from the state: a pending request is approved, a
    // denied one goes back out as an admin request.
    $editPayload = [
        'store_id' => $enrollment->store_id,
        // The add drawer's dropdown only lists stores that have not joined yet, so the
        // one being edited is never among them - the name lets us inject its option.
        'store_name' => $enrollment->store->name ?? '',
        'enrollment_id' => $enrollment->id,
        'url' => route('admin.bogo-offer.update-enrollment', [$offer->id, $enrollment->id]),
        'is_resubmit' => $state === 'rejected',
        'submit_label' => $state === 'rejected'
            ? translate('Edit & resubmit')
            : translate('Edit & approve'),
    ] + $enrollment->pickerPayload();
@endphp

<div class="d-flex flex-column" style="height:100%">
    <div class="custom-offcanvas-header d-flex justify-content-between align-items-center px-3 py-3">
        <div class="d-flex align-items-center gap-3">
            <h2 class="mb-0 fs-18 text-title font-bold">
                {{ translate('messages.Store') }} #{{ $enrollment->id }}
            </h2>
            <span class="badge {{ $statusBadge[0] }} fs-13 fw-400 px-2 py-1">{{ $statusBadge[1] }}</span>
            @if($live)
                <span class="badge {{ $live[0] }} fs-13 fw-400 px-2 py-1">{{ $live[1] }}</span>
            @endif
        </div>
        <button type="button"
                class="btn-close w-25px h-25px border rounded-circle d-center bg--secondary offcanvas-close fz-15px p-0"
                aria-label="Close">&times;</button>
    </div>

    <div class="custom-offcanvas-body p-20 flex-grow-1">
        <div class="bg-global-gray rounded-8 p-20 mb-20">
            <h6 class="fs-16 font-bold mb-3">{{ translate('Store information') }}</h6>

            @if($enrollment->store)
                <div class="d-flex align-items-center gap-3">
                    <img class="rounded-circle onerror-image flex-shrink-0"
                         style="width:56px;height:56px;object-fit:cover"
                         data-onerror-image="{{ asset('public/assets/admin/img/100x100/1.png') }}"
                         src="{{ $enrollment->store->logo_full_url }}" alt="store">
                    <div style="min-width:0">
                        <strong class="d-block fs-16 font-bold mb-1">{{ $enrollment->store->name }}</strong>
                        <span class="d-block fs-13 text-muted">
                            {{ $enrollment->store->phone }} | {{ $enrollment->store->email }}
                        </span>
                        <span class="d-block fs-13 text-muted">
                            {{ translate('messages.Joined at') }} :
                            {{ $enrollment->joined_at ? $enrollment->joined_at->format('n/j/Y, g:iA') : 'N/A' }}
                        </span>
                    </div>
                </div>
            @else
                <span class="fs-14 text-muted">{{ translate('messages.store deleted') }}</span>
            @endif
        </div>

        @if(! empty($hiddenReasons ?? []))
            <div class="alert-soft-warning alert--note rounded-8 p-3 mb-20 fs-14">
                <strong class="d-block mb-1">{{ translate('messages.Why customers can not see this offer right now') }}</strong>
                <ul class="mb-0 pl-3">
                    @foreach($hiddenReasons as $reason)
                        <li>{{ $reason }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if($state === 'rejected')
            {{-- Name who said no: the admin's own denial and a store declining an admin
                 request land in the same state and need different words. --}}
            <div class="alert-soft-danger alert--note rounded-8 p-3 mb-20 fs-14">
                <strong class="d-block mb-1">
                    {{ $enrollment->rejected_by === 'store'
                        ? translate('messages.The store declined this offer')
                        : translate('messages.You denied this request') }}
                </strong>
                @if($enrollment->rejection_reason)
                    {{-- A reason can run to the 1000 characters the form allows, and one unbroken
                         string of that length - a pasted link, say - pushed the drawer sideways
                         when it was printed inline. A read-only field wraps it and scrolls on its
                         own instead of dragging the panel with it. --}}
                    <label class="input-label fs-12 mb-1">{{ translate('Rejection reason') }}</label>
                    <textarea class="form-control bogo-rejection-reason" rows="3" disabled>{{ $enrollment->rejection_reason }}</textarea>
                @endif
            </div>
        @endif

        <div class="bg-global-gray rounded-8 p-20">
            @include('partials.bogo._enrollment_items', [
                'enrollment' => $enrollment,
                'addOnNames' => $addOnNames,
                'addOnPrices' => $addOnPrices ?? collect(),
                'surface' => 'bg-global-gray',
            ])
        </div>
    </div>

    <div class="offcanvas-footer bg-white p-3 d-flex align-items-center gap-3">
        @if($state === 'pending')
            {{-- The store asked: deny it, rework it and approve, or approve as it
                 stands. Removing it outright is not on offer - the store is owed the
                 refusal and its reason. --}}
            <button type="button" class="btn btn-soft-danger h--45px flex-fill reject-enrollment"
                    data-url="{{ route('admin.bogo-offer.store-confirmation', [$offer->id, $enrollment->id, 'rejected']) }}">
                {{ translate('messages.Reject') }}
            </button>

            <button type="button" class="btn btn--reset h--45px flex-fill edit-and-approve"
                    data-payload="{{ json_encode($editPayload) }}">
                {{ translate('Edit & approve') }}
            </button>

            <button type="button" class="btn btn--primary h--45px flex-fill approve-enrollment"
                    data-url="{{ route('admin.bogo-offer.store-confirmation', [$offer->id, $enrollment->id, 'approved']) }}">
                {{ translate('messages.Approve') }}
            </button>
        @else
            <button type="button" class="btn btn--reset h--45px flex-fill offcanvas-close">
                {{ translate('messages.Back') }}
            </button>

            {{-- A denied enrolment can be reworked and sent back out as an admin request, so
                 the store still gets the last word on foods it did not choose. --}}
            @if($state === 'rejected')
                <button type="button" class="btn btn--reset h--45px flex-fill edit-and-approve"
                        data-payload="{{ json_encode($editPayload) }}">
                    {{ translate('Edit & resubmit') }}
                </button>
            @endif

            {{-- The same delete either way; only the word changes. Withdrawing a request that
                 never went live is a cancel, dropping a running store is a delete. --}}
            <button type="button" class="btn btn--danger h--45px flex-fill form-alert"
                    data-id="drawer-enrollment-{{ $enrollment->id }}"
                    data-message="{{ $state === 'approved'
                        ? translate('Want to remove this store from the offer?')
                        : translate('Want to cancel this request?') }}">
                {{ $state === 'approved' ? translate('messages.Delete') : translate('Cancel request') }}
            </button>
        @endif
    </div>

    @if($state !== 'pending')
        {{-- Kept outside the footer so it never takes a slot in the button flex row. --}}
        <form action="{{ route('admin.bogo-offer.remove-store', [$offer->id, $enrollment->id]) }}"
              method="post" id="drawer-enrollment-{{ $enrollment->id }}">
            @csrf @method('delete')
        </form>
    @endif
</div>
