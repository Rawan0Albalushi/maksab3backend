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
                {{ translate('Happy hour') }} #{{ $happyHour->id }}
            </h2>
            @if($happyHour->has_ended)
                <span class="badge badge-soft-secondary fs-13 fw-400 px-2 py-1">{{ translate('messages.Expired') }}</span>
            @elseif($statusBadge)
                <span class="badge {{ $statusBadge[0] }} fs-13 fw-400 px-2 py-1">{{ $statusBadge[1] }}</span>
            @endif

            {{-- The reasons are listed in the body, so the badge alone here. --}}
            @include('vendor-views.promotions.partials._live_state', [
                'visibility' => $happyHour->visibility,
                'inline' => true,
            ])
        </div>
        <button type="button" class="btn-close w-25px h-25px border rounded-circle d-center bg--secondary offcanvas-close fz-15px p-0"
                aria-label="{{ translate('Close') }}">&times;</button>
    </div>

    <div class="custom-offcanvas-body p-20 flex-grow-1">
        <img class="rounded w-100 mb-3 onerror-image" style="max-height:190px;object-fit:cover"
             data-onerror-image="{{ asset('public/assets/admin/img/900x400/img1.jpg') }}"
             src="{{ $happyHour->cover_image_full_url }}" alt="{{ translate('Happy hour') }}">

        <h4 class="fs-18 font-bold mb-2">{{ $happyHour->title }}</h4>

        @php($description = $happyHour->short_description ?? '')
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
            <?php
                // Label : value rows, per design. Built in PHP because which rows apply depends on
                // the schedule shape: a custom run names its day count, a permanent one never ends.
                $rows = [
                    translate('messages.Discount') => $happyHour->discount.'%',
                    translate('Min order amount') => $happyHour->min_order_amount
                        ? \App\CentralLogics\Helpers::format_currency($happyHour->min_order_amount)
                        : translate('N/A'),
                ];

                if ($happyHour->start_time && $happyHour->end_time) {
                    $rows[translate('Offer active')] =
                        \App\CentralLogics\Helpers::time_format($happyHour->start_time)
                        .' - '.\App\CentralLogics\Helpers::time_format($happyHour->end_time)
                        .' ('.\App\Models\HappyHour::DURATION_MINUTES.' '.translate('messages.Minutes').')';
                }

                if ($happyHour->duration_type === \App\Models\HappyHour::DURATION_WEEKLY && $happyHour->weekly_days) {
                    $rows[translate('messages.Weekly')] = implode(', ', $happyHour->weekly_days);
                }

                if ($happyHour->duration_type === \App\Models\HappyHour::DURATION_CUSTOM) {
                    $rows[translate('messages.days_selected')] = count($happyHour->custom_days ?? []);
                } else {
                    $rows[translate('Schedule date')] = $happyHour->is_permanent
                        ? translate('messages.Permanent')
                        : ($happyHour->start_date ? $happyHour->start_date->format('d M Y') : translate('N/A'))
                            .' - '.($happyHour->end_date ? $happyHour->end_date->format('d M Y') : translate('N/A'));
                }
            ?>

            @foreach($rows as $label => $value)
                <div class="d-flex align-items-start fs-14 {{ $loop->last ? '' : 'mb-2' }}">
                    <span class="opacity-75" style="min-width:150px">{{ $label }}</span>
                    <span class="mr-2">:</span>
                    <strong>{{ $value }}</strong>
                </div>
            @endforeach
        </div>

        {{-- Approved does not mean the discount is reaching customers: a switched-off store, or one
             out of subscription orders, hides it while that lasts. --}}
        @if(! empty($hiddenReasons))
            <div class="alert-soft-warning alert--note rounded-8 p-3 mb-20 fs-14">
                <strong class="d-block mb-1">
                    {{ translate('messages.Why customers can not see this happy hour right now') }}
                </strong>
                <ul class="mb-0 pl-3">
                    @foreach($hiddenReasons as $reason)
                        <li>{{ $reason }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if($state === 'rejected' && $enrollment->rejection_reason)
            <div class="alert-soft-danger alert--note rounded-8 p-3 fs-14">
                <strong class="d-block mb-1">
                    {{ $enrollment->rejected_by === 'store'
                        ? translate('messages.You declined this happy hour')
                        : translate('messages.The admin denied this request') }}
                </strong>
                <label class="input-label fs-12 mb-1">{{ translate('Rejection reason') }}</label>
                <textarea class="form-control" rows="3" disabled>{{ $enrollment->rejection_reason }}</textarea>
            </div>
        @endif
    </div>

    <div class="offcanvas-footer bg-white p-3 d-flex align-items-center gap-3">
        @if($happyHour->actions['can_respond'])
            <button type="button" class="btn btn-soft-danger h--45px flex-fill reject-enrollment"
                    data-url="{{ route('vendor.happy-hour.respond', [$happyHour->id, 'rejected']) }}">
                {{ translate('messages.Deny') }}
            </button>
            <button type="button" class="btn btn--primary h--45px flex-fill happy-hour-join"
                    data-url="{{ route('vendor.happy-hour.respond', [$happyHour->id, 'approved']) }}">
                {{ translate('messages.Approval') }}
            </button>
        @elseif($happyHour->actions['can_leave'] || $happyHour->actions['can_cancel'])
            {{-- Per the design: a neutral Cancel closes the drawer, and Leave is the destructive
                 action -- so the red button is the one that actually ends the enrolment. --}}
            <button type="button" class="btn btn--reset h--45px flex-fill offcanvas-close">
                {{ translate('messages.Cancel') }}
            </button>
            <button type="button" class="btn btn--danger h--45px flex-fill form-alert"
                    data-id="drawer-leave-hh-{{ $happyHour->id }}"
                    data-message="{{ $happyHour->actions['can_leave']
                        ? translate('Want to leave this happy hour?')
                        : translate('Want to cancel this request?') }}">
                {{ $happyHour->actions['can_leave'] ? translate('messages.Leave') : translate('Cancel request') }}
            </button>
            <form action="{{ route('vendor.happy-hour.leave', $happyHour->id) }}" method="post"
                  id="drawer-leave-hh-{{ $happyHour->id }}">
                @csrf @method('delete')
            </form>
        @elseif($happyHour->actions['can_join'])
            <button type="button" class="btn btn--reset h--45px flex-fill offcanvas-close">
                {{ translate('messages.Cancel') }}
            </button>
            {{-- The same commitment modal the list row raises: what a happy hour replaces, and
                 who pays for it, are not on this screen anywhere else. --}}
            <button type="button" class="btn btn--primary h--45px flex-fill happy-hour-join"
                    data-form="drawer-join-hh-{{ $happyHour->id }}">
                {{ translate('messages.Join') }}
            </button>
            <form action="{{ route('vendor.happy-hour.join', $happyHour->id) }}" method="post"
                  id="drawer-join-hh-{{ $happyHour->id }}">
                @csrf
            </form>
        @elseif($state === 'admin_requested')
            {{-- The invitation outlived the window. Kept visible and disabled, so the vendor sees
                 there was a decision here rather than an empty footer. --}}
            @php($lockedReason = translate('This happy hour has already ended'))
            <span class="flex-fill" data-toggle="tooltip" title="{{ $lockedReason }}">
                <button type="button" class="btn btn-soft-danger h--45px w-100" disabled>
                    {{ translate('messages.Deny') }}
                </button>
            </span>
            <span class="flex-fill" data-toggle="tooltip" title="{{ $lockedReason }}">
                <button type="button" class="btn btn--primary h--45px w-100" disabled>
                    {{ translate('messages.Approval') }}
                </button>
            </span>
        @else
            <button type="button" class="btn btn--reset h--45px flex-fill offcanvas-close">
                {{ translate('Close') }}
            </button>
        @endif
    </div>
</div>
