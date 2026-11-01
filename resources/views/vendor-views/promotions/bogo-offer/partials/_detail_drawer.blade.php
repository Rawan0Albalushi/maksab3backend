@php($state = ! $enrollment
    ? 'not_joined'
    : ($enrollment->status === 'pending'
        ? ($enrollment->requested_by === 'admin' ? 'admin_requested' : 'pending')
        : $enrollment->status))

@php($statusBadge = [
    'pending' => ['badge-soft-info', translate('messages.Pending')],
    'admin_requested' => ['badge-soft-secondary', translate('Admin requested')],
    'approved' => ['badge-soft-success', translate('messages.Approved')],
    'rejected' => ['badge-soft-danger', translate('messages.Rejected')],
][$state] ?? null)

<div class="d-flex flex-column" style="height:100%">
    <div class="custom-offcanvas-header d-flex justify-content-between align-items-center px-3 py-3">
        <div class="d-flex align-items-center gap-3">
            <h2 class="mb-0 fs-18 text-title font-bold">
                {{ translate('BOGO offer') }} #{{ $offer->id }}
            </h2>
            @if($offer->has_ended)
                <span class="badge badge-soft-secondary fs-13 fw-400 px-2 py-1">{{ translate('messages.Expired') }}</span>
            @elseif($statusBadge)
                <span class="badge {{ $statusBadge[0] }} fs-13 fw-400 px-2 py-1">{{ $statusBadge[1] }}</span>
            @endif

            {{-- The reasons are listed in the body, so the badge alone here. --}}
            @include('vendor-views.promotions.partials._live_state', [
                'visibility' => $offer->visibility,
                'inline' => true,
            ])
        </div>
        <button type="button" class="btn-close w-25px h-25px border rounded-circle d-center bg--secondary offcanvas-close fz-15px p-0"
                aria-label="{{ translate('Close') }}">&times;</button>
    </div>

    <div class="custom-offcanvas-body p-20 flex-grow-1">
        <img class="rounded w-100 mb-3 onerror-image" style="max-height:190px;object-fit:cover"
             data-onerror-image="{{ asset('public/assets/admin/img/900x400/img1.jpg') }}"
             src="{{ $offer->image_full_url }}" alt="{{ translate('BOGO offer') }}">

        <h4 class="fs-18 font-bold mb-2">{{ $offer->title }}</h4>

        @php($description = $offer->description ?? '')
        <p class="fs-14 opacity-75 mb-3">
            @if(Str::length($description) > 140)
                <span class="description-short">{{ Str::limit($description, 140, '...') }}</span>
                <span class="description-full d-none">{{ $description }}</span>
                <a href="javascript:" class="toggle-description">{{ translate('See more') }}</a>
            @else
                {{ $description }}
            @endif
        </p>

        <div class="bg-global-gray rounded-8 p-20 mb-20">
            @php($rows = [
                translate('Offer created') => $offer->created_at->format('d M, Y h:i A'),
                translate('messages.Validity') => ($offer->start_date ? $offer->start_date->format('d M, Y h:i A') : translate('N/A'))
                    .'  -  '.($offer->end_date ? $offer->end_date->format('d M, Y h:i A') : translate('N/A')),
                translate('Usage limit') => $offer->usage_limit_per_customer
                    ? translate('messages.Per person').' '.$offer->usage_limit_per_customer.' '.translate('messages.Order')
                    : translate('N/A'),
                translate('Usage limit total') => $offer->usage_limit_total
                    ? $offer->usage_limit_total.' '.translate('messages.Order')
                    : translate('N/A'),
            ])

            @foreach($rows as $label => $value)
                <div class="d-flex align-items-start fs-14 {{ $loop->last ? '' : 'mb-2' }}">
                    <span class="opacity-75" style="min-width:150px">{{ $label }}</span>
                    <span class="mr-2">:</span>
                    <strong>{{ $value }}</strong>
                </div>
            @endforeach
        </div>

        {{-- Approving is the admin's answer; it is not the customer's view of the bundle. The two
             come apart the moment an item sells out or the store closes, so the reason is named
             here rather than leaving the vendor to work it out from the app. --}}
        @if(! empty($hiddenReasons))
            <div class="alert-soft-warning alert--note rounded-8 p-3 mb-20 fs-14">
                <strong class="d-block mb-1">
                    {{ translate('messages.Why customers can not see this offer right now') }}
                </strong>
                <ul class="mb-0 pl-3">
                    @foreach($hiddenReasons as $reason)
                        <li>{{ $reason }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if($state === 'rejected' && $enrollment->rejection_reason)
            <div class="alert-soft-danger alert--note rounded-8 p-3 mb-20 fs-14">
                <strong class="d-block mb-1">
                    {{ $enrollment->rejected_by === 'store'
                        ? translate('messages.You declined this offer')
                        : translate('messages.The admin denied this request') }}
                </strong>
                <label class="input-label fs-12 mb-1">{{ translate('Rejection reason') }}</label>
                <textarea class="form-control" rows="3" disabled>{{ $enrollment->rejection_reason }}</textarea>
            </div>
        @endif

        @if($enrollment)
            <div class="bg-global-gray rounded-8 p-20">
                @include('partials.bogo._enrollment_items', [
                    'enrollment' => $enrollment,
                    'addOnNames' => $addOnNames,
                    'addOnPrices' => $addOnPrices ?? collect(),
                    'surface' => 'bg-global-gray',
                ])
            </div>
        @endif
    </div>

    <div class="offcanvas-footer bg-white p-3 d-flex align-items-center gap-3">
        @if($offer->actions['can_respond'])
            {{-- The admin chose these items, so both answers sit under them where they can be read. --}}
            <button type="button" class="btn btn-soft-danger h--45px flex-fill reject-enrollment"
                    data-url="{{ route('vendor.bogo-offer.respond', [$offer->id, 'rejected']) }}">
                {{ translate('messages.Deny') }}
            </button>
            <button type="button" class="btn btn--primary h--45px flex-fill respond-enrollment"
                    data-url="{{ route('vendor.bogo-offer.respond', [$offer->id, 'approved']) }}">
                {{ translate('messages.Approval') }}
            </button>
        @elseif($state === 'admin_requested')
            {{-- The invitation outlived the offer. The buttons stay so the vendor can see there
                 was a decision here at all, disabled because there is nothing left to accept. --}}
            <span class="flex-fill" data-toggle="tooltip" title="{{ $offer->locked_reason }}">
                <button type="button" class="btn btn-soft-danger h--45px w-100" disabled>
                    {{ translate('messages.Deny') }}
                </button>
            </span>
            <span class="flex-fill" data-toggle="tooltip" title="{{ $offer->locked_reason }}">
                <button type="button" class="btn btn--primary h--45px w-100" disabled>
                    {{ translate('messages.Approval') }}
                </button>
            </span>
        @else
            {{-- Each remaining action is asked for on its own rather than as a chain. They do not
                 arrive together: an admin's denial may only be reworked -- not cancelled, which
                 would erase the refusal and its reason -- so nesting Edit Items under the delete
                 hid the one thing that case still allows. --}}
            <button type="button" class="btn btn--reset h--45px flex-fill offcanvas-close">
                {{ translate('messages.Cancel') }}
            </button>

            @if($offer->actions['can_join'])
                <button type="button" class="btn btn--primary h--45px flex-fill join-offer"
                        data-id="{{ $offer->id }}" data-buy="{{ $offer->buy_qty }}" data-get="{{ $offer->get_qty }}">
                    {{ translate('messages.Join') }}
                </button>
            @endif

            @if($offer->actions['can_resubmit'])
                {{-- Reworking an approved selection drops it back to pending, so it stops applying
                     until the admin approves the new one. Opens on the selection already saved. --}}
                <button type="button" class="btn btn--reset h--45px flex-fill edit-items"
                        data-id="{{ $offer->id }}" data-buy="{{ $offer->buy_qty }}" data-get="{{ $offer->get_qty }}"
                        data-payload="{{ json_encode($enrollment->pickerPayload()) }}">
                    {{ translate('Edit items') }}
                </button>
            @endif

            @if($offer->actions['can_leave'] || $offer->actions['can_cancel'])
                <button type="button" class="btn btn--danger h--45px flex-fill form-alert"
                        data-id="drawer-leave-bogo-{{ $offer->id }}"
                        data-message="{{ $offer->actions['can_leave']
                            ? translate('Want to leave this BOGO offer?')
                            : translate('Want to cancel this request?') }}">
                    {{ $offer->actions['can_leave'] ? translate('messages.Leave BOGO') : translate('Cancel request') }}
                </button>
                <form action="{{ route('vendor.bogo-offer.leave', $offer->id) }}" method="post"
                      id="drawer-leave-bogo-{{ $offer->id }}">
                    @csrf @method('delete')
                </form>
            @endif

            {{-- Nothing left to do and a reason why -- an ended offer, or a denial that may only
                 be reworked once the offer it belonged to is over. --}}
            @if($offer->locked_reason && ! array_filter($offer->actions))
                <span class="flex-fill text-center fs-13 opacity-75 align-self-center">{{ $offer->locked_reason }}</span>
            @endif
        @endif
    </div>
</div>
