@php use App\CentralLogics\Helpers; @endphp
@extends('layouts.admin.app')

@section('title',translate('Review list'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title text-break">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/outline/delivery-man.svg')}}" class="w--26" alt="">
                </span>
                <span>
                    {{translate('messages.Deliveryman reviews')}}
                    <span class="badge badge-soft-dark ml-2" id="itemCount">
                        {{$reviews->total()}}
                    </span>
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('What customers said about the people delivering their orders.') }}</p>
        </div>

        {{-- Same filters as before (Delivery Man, Sort By), restyled to the boxed panel the
             customer list uses: its own card, a labeled row g-3/col-md-4 grid, and an
             explicit Filter submit button -- replacing the inline .min--240 dropdowns that
             auto-submitted via .set-filter/data-filter/data-url. No filter fields added or
             removed, no option values changed.

             $deliveryMen comes from DeliveryManController::getReviewListView(); this view
             used to import App\Models\DeliveryMan and run the dropdown query itself. --}}
        <div class="card mb-3">
            <div class="card-body">
                <form>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('Deliveryman') }}</label>
                            <select name="deliveryman_id" class="form-control js-select2-custom">
                                <option value="all">{{ translate('All deliveryman') }}</option>
                                @foreach($deliveryMen ?? [] as $deliveryMan)
                                    <option value="{{ $deliveryMan->id }}" @selected($deliveryMan->id == request('deliveryman_id'))>
                                        {{ $deliveryMan->full_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('Sort by') }}</label>
                            <select name="order_by" class="form-control js-select2-custom">
                                <option>{{ translate('messages.Latest ratings') }}</option>
                                <option value="desc" @selected(request('order_by') === 'desc')>{{ translate('messages.Top ratings') }}</option>
                                <option value="asc" @selected(request('order_by') === 'asc')>{{ translate('messages.Low ratings') }}</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <div class="btn--container justify-content-end">
                                <button type="submit" class="btn btn--primary"><i class="tio-filter-list"></i> {{ translate('messages.Filter') }}</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <div class="row gx-2 gx-lg-3">
            <div class="col-sm-12 col-lg-12 mb-3 mb-lg-2">
                <div class="card">
                    <div class="card-header py-2 border-0">
                        <span class="card-header-title"></span>
                        <div class="search--button-wrapper justify-content-end">
                            @include('partials._table-head', [
                                'subtitle' => translate('messages.Ratings and comments customers left for delivery men.'),
                            ])

                            <form class="search-form theme-style">
                                <div class="input-group input--group">
                                    <input id="datatableSearch" name="search" type="search" class="form-control"
                                           placeholder="{{ translate('Search by deliveryman, customer, order ID or review') }}"
                                           value="{{ request()->input('search') }}"
                                           aria-label="{{ translate('Search by deliveryman, customer, order ID or review') }}">
                                    <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                                </div>
                            </form>
                            @if(request()->input('search'))
                                <button type="reset" class="btn btn--primary ml-2 location-reload-to-base"
                                        data-url="{{url()->full()}}"><i class="tio-refresh"></i> {{translate('messages.Reset')}}</button>
                            @endif

                            <div class="hs-unfold mr-2">
                                <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle min-height-40"
                                   href="javascript:"
                                   data-hs-unfold-options='{
                                            "target": "#usersExportDropdown",
                                            "type": "css-animation"
                                        }'>
                                    <i class="tio-download-to mr-1"></i> {{ translate('messages.Export') }}
                                </a>

                                <div id="usersExportDropdown"
                                     class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">
                                    <span class="dropdown-header">{{ translate('messages.Download options') }}</span>
                                    <a id="export-excel" class="dropdown-item"
                                       href="{{route('admin.users.delivery-man.reviews.export', ['type'=>'excel',request()->getQueryString()])}}">
                                        <img class="avatar avatar-xss avatar-4by3 mr-2"
                                             src="{{ asset('public/assets/admin/svg/components/excel.svg') }}"
                                             alt="Image Description">
                                        Excel
                                    </a>
                                    <a id="export-csv" class="dropdown-item"
                                       href="{{route('admin.users.delivery-man.reviews.export', ['type'=>'csv',request()->getQueryString()])}}">
                                        <img class="avatar avatar-xss avatar-4by3 mr-2"
                                             src="{{ asset('public/assets/admin/svg/components/placeholder-csv-format.svg') }}"
                                             alt="Image Description">
                                        CSV
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive datatable-custom">
                        <table id="columnSearchDatatable"
                               class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table"
                               data-hs-datatables-options='{
                                 "order": [],
                                 "orderCellsTop": true,
                                 "paging": false
                               }'>
                            <thead class="thead-light">
                            <tr>
                                <th class="border-0">{{translate('Order ID')}}</th>
                                <th class="border-0">{{translate('Deliveryman')}}</th>
                                <th class="border-0">{{translate('messages.Customer')}}</th>
                                <th class="border-0">{{translate('messages.Rating')}}</th>
                                <th class="border-0">{{translate('messages.review')}}</th>
                                <th class="border-0">{{translate('messages.Date')}}</th>
                                <th class="border-0">{{translate('messages.Visibility')}}</th>
                                <th class="border-0 text-center">{{translate('messages.Action')}}</th>
                            </tr>
                            </thead>

                            <tbody id="set-rows">
                            @foreach($reviews as $review)
                                <tr>
                                    <td>
                                        {{-- Parcel orders live on their own details screen; the order
                                             relation can be missing for a deleted order, hence ?->. --}}
                                        @if($review->order?->order_type === 'parcel')
                                            <a class="text-dark"
                                               href="{{ route('admin.parcel.order.details', [$review->order_id, 'module_id' => $review->order?->module_id]) }}">{{ $review->order_id }}</a>
                                        @else
                                            <a class="text-dark"
                                               href="{{ route('admin.order.details', [$review->order_id, 'module_id' => $review->order?->module_id]) }}">{{ $review->order_id }}</a>
                                        @endif
                                    </td>
                                    <td>
                                        @if($review->delivery_man)
                                            <span class="d-block font-size-sm text-body">
                                                <a href="{{ route('admin.users.delivery-man.preview', [$review->delivery_man_id]) }}"
                                                   class="media gap-2 align-items-center text-dark">
                                                    <img src="{{ $review->delivery_man->image_full_url }}"
                                                         class="rounded-circle object-cover" width="48" height="48"
                                                         alt="{{ $review->delivery_man->full_name }}">
                                                    <div class="meida-body">
                                                        <div title="{{ $review->delivery_man->full_name }}">{{ $review->delivery_man->full_name }}</div>
                                                        <div>{{ $review->delivery_man->phone }}</div>
                                                    </div>
                                                </a>
                                            </span>
                                        @else
                                            <div class="text-muted">{{ translate('No data found') }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        @if($review->customer)
                                            <a href="{{route('admin.users.customer.view',[$review->user_id])}}"
                                               class="text-dark">
                                                {{ $review->customer->full_name }}
                                            </a>
                                        @else
                                            <div
                                                class="text-muted">{{translate('No data found')}}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex">
                                            <label
                                                class="badge badge-soft-warning mb-0 d-flex align-items-center gap-1 justify-content-center">
                                                <span class="d-inline-block mt-3px">{{$review->rating}}</span>
                                                <i class="tio-star"></i>
                                            </label>
                                        </div>
                                    </td>
                                    <td>
                                        @if($review->comment)
                                            <div class="cursor-pointer text-wrap max-349 min-w-100px max-text-2-line"
                                                 data-toggle="tooltip" data-placement="top"
                                                 title="{{$review->comment}}">
                                                {{$review->comment}}
                                            </div>
                                        @else
                                            <span class="text-muted font-size-sm font-italic">{{translate('messages.No comment left')}}</span>
                                        @endif
                                        @if($review->attachment_count)
                                            <span class="cell-chips d-block mt-1">
                                                <span class="cell-chip" title="{{ translate('messages.Review attachment') }}"><i class="tio-image"></i> {{ $review->attachment_count }}</span>
                                            </span>
                                        @endif
                                    </td>
                                    <td data-order="{{ $review->created_at }}">
                                        <span class="table-when">
                                            <span class="table-when__day">{{ Helpers::date_format($review->created_at) }}</span>
                                            <span class="table-when__ago text-uppercase" title="{{ Helpers::time_date_format($review->created_at) }}">
                                                {{ Helpers::time_format($review->created_at) }}
                                            </span>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="status-toggle" data-status="{{ $review->status ? 1 : 0 }}">
                                            <label class="toggle-switch toggle-switch-sm" for="dmReviewCheckbox{{ $review->id }}">
                                                <input type="checkbox" id="dmReviewCheckbox{{ $review->id }}"
                                                       class="toggle-switch-input status_form_alert"
                                                       data-id="dm-review-status-{{ $review->id }}"
                                                       data-label-on="{{ translate('messages.Visible') }}"
                                                       data-label-off="{{ translate('messages.Hidden') }}"
                                                       data-message="{{ $review->status ? translate('messages.You want to hide this review for customer') : translate('messages.You want to show this review for customer') }}"
                                                       @checked($review->status)>
                                                <span class="toggle-switch-label">
                                                    <span class="toggle-switch-indicator"></span>
                                                </span>
                                            </label>
                                            <span class="status-toggle__text" aria-live="polite">
                                                {{ $review->status ? translate('messages.Visible') : translate('messages.Hidden') }}
                                            </span>
                                        </div>
                                        <form action="{{ route('admin.users.delivery-man.reviews.status', [$review->id, $review->status ? 0 : 1]) }}" method="get" id="dm-review-status-{{ $review->id }}"></form>
                                    </td>
                                    <td>
                                        <div class="btn--container justify-content-center">
                                            {{-- data-attachments is single-quoted on purpose: @json() escapes
                                                 apostrophes but not the structural double quotes. --}}
                                            <a class="btn action-btn action-btn--view" id="view-details" href="#"
                                               title="{{ translate('messages.View') }}" data-order_id="{{$review->order_id}}"
                                               data-date="{{ Helpers::time_date_format($review->created_at) }}"
                                               data-name="{{ $review->delivery_man?->full_name ?? translate('No data found') }}"
                                               data-image="{{ $review->delivery_man?->image_full_url }}"
                                               data-phone="{{ $review->delivery_man?->phone }}"
                                               data-rating="{{$review->rating}}"
                                               data-customer="{{ $review->customer?->full_name ?? translate('No data found') }}"
                                               data-attachments='@json($review->attachment_full_url)'
                                               data-comment="{{$review->comment}}" >
                                                <i class="tio-visible-outlined"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>

                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if($reviews->isNotEmpty())
                        <hr>
                    @endif
                    <div class="page-area">
                        {!! $reviews->links() !!}
                    </div>
                    @if($reviews->isEmpty())
                        <div class="empty--data">
                            <img src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="public">
                            <h5>
                                {{translate('No data found')}}
                            </h5>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deliverymanReviewModal" tabindex="-1" role="dialog"
         aria-labelledby="deliverymanReviewModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="{{ translate('messages.Close') }}">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body pt-0">
                    <div class="text-center d-flex flex-column align-items-center mb-3">
                        <h5 id="deliverymanReviewModalLabel">{{translate('Deliveryman Review')}}</h5>
                        <div class="fs-12 mb-1">{{ translate('Order') }} <span id="order-id" class="font-semibold text-dark"></span></div>
                        <div id="date" class="text-muted fs-12"></div>
                    </div>

                    <div class="p-3 card rounded mb-3">
                        <div class="media gap-3">
                            <img width="100" height="100" class="rounded object-cover"
                                 src="" alt="{{ translate('Deliveryman') }}">
                            <div class="media-body">
                                <h5 id="name"></h5>
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <i class="tio-android-phone"></i>
                                    <a href="tel:" id="phone" class="text-dark"></a>
                                </div>
                                <div class="d-flex">
                                    <label
                                        class="badge badge-soft-warning mb-0 d-flex align-items-center gap-1 justify-content-center">
                                        <span class="d-inline-block mt-3px" id="rating"></span>
                                        <i class="tio-star"></i>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="p-3 card rounded mb-3">
                        <div class="fs-12 text-muted mb-1">{{ translate('messages.Reviewed by') }}</div>
                        <div id="customer" class="font-semibold text-dark"></div>
                    </div>
                    <div class="p-3 card rounded">
                        <h5 class="text-warning">{{translate('review')}}</h5>
                        <p id="comment" class="mb-0"></p>
                        <div id="attachments" class="d-flex flex-wrap gap-2 mt-3"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
@push('script_2')
    <script>
        "use strict";
        $(document).on('click', '#view-details', function () {
            let data = $(this).data();
            let modal = $('#deliverymanReviewModal');
            modal.find('#order-id').text(data.order_id);
            modal.find('#date').text(data.date);
            modal.find('.media img').attr('src', data.image || '');
            modal.find('#name').text(data.name);
            modal.find('#phone').text(data.phone || '').attr('href', 'tel:' + (data.phone || ''));
            modal.find('#rating').text(data.rating);
            modal.find('#customer').text(data.customer);
            modal.find('#comment').text(data.comment || '{{ translate('messages.No comment left') }}');

            let attachments = modal.find('#attachments').empty();
            $.each(data.attachments || [], function (index, url) {
                $('<a>', {href: url, target: '_blank', rel: 'noopener'})
                    .append($('<img>', {
                        src: url,
                        width: 64,
                        height: 64,
                        class: 'rounded object-cover',
                        alt: '{{ translate('messages.Review attachment') }}'
                    }))
                    .appendTo(attachments);
            });

            modal.modal('show');
        });
    </script>
@endpush
