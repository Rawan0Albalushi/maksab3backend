@extends('layouts.admin.app')

@section('title',$store->name."'s ".translate('messages.Reviews'))

@push('css_or_js')
    <link href="{{asset('public/assets/admin/css/croppie.css')}}" rel="stylesheet">

@endpush

@section('content')
<div class="content container-fluid">
    @include('admin-views.vendor.view.partials._header',['store'=>$store])
    <div class="tab-content">
        <div class="tab-pane fade show active" id="product">
            <div class="resturant-review-top my-4" id="store_details">
                <div class="resturant-review-left mb-3">
                    <h1 class="title">{{ number_format($user_rating, 1)}}<span class="out-of">/5</span></h1>
                    @if ($user_rating == 5)
                    <div class="rating">
                        <span><i class="tio-star"></i></span>
                        <span><i class="tio-star"></i></span>
                        <span><i class="tio-star"></i></span>
                        <span><i class="tio-star"></i></span>
                        <span><i class="tio-star"></i></span>
                    </div>
                    @elseif ($user_rating < 5 && $user_rating >= 4.5)
                    <div class="rating">
                        <span><i class="tio-star"></i></span>
                        <span><i class="tio-star"></i></span>
                        <span><i class="tio-star"></i></span>
                        <span><i class="tio-star"></i></span>
                        <span><i class="tio-star-half"></i></span>
                    </div>
                    @elseif ($user_rating < 4.5 && $user_rating >= 4)
                    <div class="rating">
                        <span><i class="tio-star"></i></span>
                        <span><i class="tio-star"></i></span>
                        <span><i class="tio-star"></i></span>
                        <span><i class="tio-star"></i></span>
                        <span><i class="tio-star-outlined"></i></span>
                    </div>
                    @elseif ($user_rating < 4 && $user_rating >= 3.5)
                    <div class="rating">
                        <span><i class="tio-star"></i></span>
                        <span><i class="tio-star"></i></span>
                        <span><i class="tio-star"></i></span>
                        <span><i class="tio-star-half"></i></span>
                        <span><i class="tio-star-outlined"></i></span>
                    </div>
                    @elseif ($user_rating < 3.5 && $user_rating >= 3)
                    <div class="rating">
                        <span><i class="tio-star"></i></span>
                        <span><i class="tio-star"></i></span>
                        <span><i class="tio-star"></i></span>
                        <span><i class="tio-star-outlined"></i></span>
                        <span><i class="tio-star-outlined"></i></span>
                    </div>
                    @elseif ($user_rating < 3 && $user_rating >= 2.5)
                    <div class="rating">
                        <span><i class="tio-star"></i></span>
                        <span><i class="tio-star"></i></span>
                        <span><i class="tio-star-half"></i></span>
                        <span><i class="tio-star-outlined"></i></span>
                        <span><i class="tio-star-outlined"></i></span>
                    </div>
                    @elseif ($user_rating < 2.5 && $user_rating > 2)
                    <div class="rating">
                        <span><i class="tio-star"></i></span>
                        <span><i class="tio-star"></i></span>
                        <span><i class="tio-star-outlined"></i></span>
                        <span><i class="tio-star-outlined"></i></span>
                        <span><i class="tio-star-outlined"></i></span>
                    </div>
                    @elseif ($user_rating < 2 && $user_rating >= 1.5)
                    <div class="rating">
                        <span><i class="tio-star"></i></span>
                        <span><i class="tio-star-half"></i></span>
                        <span><i class="tio-star-outlined"></i></span>
                        <span><i class="tio-star-outlined"></i></span>
                        <span><i class="tio-star-outlined"></i></span>
                    </div>
                    @elseif ($user_rating < 1.5 && $user_rating > 1)
                    <div class="rating">
                        <span><i class="tio-star"></i></span>
                        <span><i class="tio-star-outlined"></i></span>
                        <span><i class="tio-star-outlined"></i></span>
                        <span><i class="tio-star-outlined"></i></span>
                        <span><i class="tio-star-outlined"></i></span>
                    </div>
                    @elseif ($user_rating < 1 && $user_rating > 0)
                    <div class="rating">
                        <span><i class="tio-star-half"></i></span>
                        <span><i class="tio-star-outlined"></i></span>
                        <span><i class="tio-star-outlined"></i></span>
                        <span><i class="tio-star-outlined"></i></span>
                        <span><i class="tio-star-outlined"></i></span>
                    </div>
                    @elseif ($user_rating == 1)
                    <div class="rating">
                        <span><i class="tio-star"></i></span>
                        <span><i class="tio-star-outlined"></i></span>
                        <span><i class="tio-star-outlined"></i></span>
                        <span><i class="tio-star-outlined"></i></span>
                        <span><i class="tio-star-outlined"></i></span>
                    </div>
                    @elseif ($user_rating == 0)
                    <div class="rating">
                        <span><i class="tio-star-outlined"></i></span>
                        <span><i class="tio-star-outlined"></i></span>
                        <span><i class="tio-star-outlined"></i></span>
                        <span><i class="tio-star-outlined"></i></span>
                        <span><i class="tio-star-outlined"></i></span>
                    </div>
                    @endif
                    <div class="info">
                        <span>{{$total_reviews}} {{translate('messages.Reviews')}}</span>
                    </div>
                </div>
                <div class="resturant-review-right">
                    <ul class="list-unstyled list-unstyled-py-2 mb-0">
                        <li class="d-flex align-items-center font-size-sm">
                            <span
                                class="progress-name mr-3">{{translate('messages.excellent')}}</span>
                            <div class="progress flex-grow-1">
                                <div class="progress-bar" role="progressbar"
                                        style="width: {{($five/$total_rating)*100}}%;"
                                        aria-valuenow="{{($five/$total_rating)*100}}"
                                        aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <span class="ml-3">{{$five}}</span>
                        </li>

                        <li class="d-flex align-items-center font-size-sm">
                            <span class="progress-name mr-3">{{translate('messages.good')}}</span>
                            <div class="progress flex-grow-1">
                                <div class="progress-bar" role="progressbar"
                                        style="width: {{($four/$total_rating)*100}}%;"
                                        aria-valuenow="{{($four/$total_rating)*100}}"
                                        aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <span class="ml-3">{{$four}}</span>
                        </li>

                        <li class="d-flex align-items-center font-size-sm">
                            <span class="progress-name mr-3">{{translate('messages.average')}}</span>
                            <div class="progress flex-grow-1">
                                <div class="progress-bar" role="progressbar"
                                        style="width: {{($three/$total_rating)*100}}%;"
                                        aria-valuenow="{{($three/$total_rating)*100}}"
                                        aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <span class="ml-3">{{$three}}</span>
                        </li>

                        <li class="d-flex align-items-center font-size-sm">
                            <span class="progress-name mr-3">{{translate('messages.Below average')}}</span>
                            <div class="progress flex-grow-1">
                                <div class="progress-bar" role="progressbar"
                                        style="width: {{($two/$total_rating)*100}}%;"
                                        aria-valuenow="{{($two/$total_rating)*100}}"
                                        aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <span class="ml-3">{{$two}}</span>
                        </li>

                        <li class="d-flex align-items-center font-size-sm">

                            <span class="progress-name mr-3">{{translate('messages.poor')}}</span>
                            <div class="progress flex-grow-1">
                                <div class="progress-bar" role="progressbar"
                                        style="width: {{($one/$total_rating)*100}}%;"
                                        aria-valuenow="{{($one/$total_rating)*100}}"
                                        aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <span class="ml-3">{{$one}}</span>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="card">
            <div class="card-header py-2">
                <div class="search--button-wrapper">

                    @include('partials._table-head', [
                        'title'    => translate('Review list'),
                        'subtitle' => translate('messages.Store review list subtitle'),
                        'count'    => $reviews->total(),
                        'count_id' => 'itemCount',
                    ])
                    <div class="hs-unfold mr-2">
                        <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle min-height-40" href="javascript:;"
                            data-hs-unfold-options='{
                                    "target": "#usersExportDropdown",
                                    "type": "css-animation"
                                }'>
                            <i class="tio-download-to mr-1"></i> {{ translate('messages.Export') }}
                        </a>

                        <div id="usersExportDropdown"
                            class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">

                            <span class="dropdown-header">{{ translate('messages.Download options') }}</span>
                            <a id="export-excel" class="dropdown-item" href="{{route('admin.store.store_wise_reviwe_export', ['type'=>'excel', 'id' => $store->id,request()->getQueryString()])}}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                    src="{{ asset('public/assets/admin') }}/svg/components/excel.svg"
                                    alt="Image Description">
                                Excel
                            </a>
                            <a id="export-csv" class="dropdown-item" href="{{route('admin.store.store_wise_reviwe_export', ['type'=>'csv','id' => $store->id,request()->getQueryString()])}}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                    src="{{ asset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg"
                                    alt="Image Description">
                                CSV
                            </a>

                        </div>
                    </div>
                </div>
            </div>

                <div class="card-body p-0 verticle-align-middle-table">
                    <div class="table-responsive datatable-custom">
                        <table id="columnSearchDatatable"
                               class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table"
                               data-hs-datatables-options='{
                            "order": [],
                            "orderCellsTop": true,
                            "paging":false
                        }'>
                            <thead class="thead-light">
                            <tr>
                                <th class="text-center max-90px">{{translate('messages.SL')}}</th>
                                <th>{{translate('Review ID')}}</th>
                                <th>{{translate('messages.item')}}</th>
                                <th class="pl-4">{{translate('Reviewer information')}}</th>
                                <th>{{translate('messages.review')}}</th>
                                <th>{{translate('messages.Date')}}</th>
                                <th class="w-30p text-center">{{translate('messages.Store reply')}}</th>
                                <th class="text-center w-100px">{{translate('Status')}}</th>
                            </tr>
                            </thead>

                            <tbody id="set-rows">

                            @foreach($reviews as $key=>$review)
                                <tr>
                                    <td class="text-center">{{$key+$reviews->firstItem()}}</td>
                                    <td>{{$review->review_id}}</td>
                                    <td>
                                        @if ($review->item)
                                            <div class="media align-items-center">
                                                <img class="avatar avatar-lg mr-2 onerror-image"

                                                     src="{{ $review?->item['image_full_url'] ?? asset('public/assets/admin/img/160x160/img1.jpg') }}"


                                                     data-onerror-image="{{asset('public/assets/admin/img/160x160/img1.jpg')}}" alt="{{$review->item->name}} image">
                                                <div class="media-body">
                                                    <a href="{{route('admin.item.view',[$review->item['id']])}}">
                                                        <h5 class="text-hover-primary mb-0">{{Str::limit($review->item['name'],10)}}</h5>
                                                    </a>
                                                    @if($review->order_id)
                                                    <a class="text-body" href="{{route('admin.order.details',['id'=>$review->order_id])}}">Order ID: {{$review->order_id}}</a>
                                                    @endif
                                                </div>
                                            </div>
                                        @else
                                            {{translate('messages.Food deleted!')}}
                                        @endif
                                    </td>
                                    <td>
                                        @if($review->customer)
                                            <a
                                                href="{{route('admin.users.customer.view',[$review['user_id']])}}">
                                                <div>
                                    <span class="d-block h5 text-hover-primary mb-0">{{Str::limit($review->customer['f_name']." ".$review->customer['l_name'], 15)}} </span>
                                                    <span class="d-block font-size-sm text-body">{{Str::limit($review->customer->phone)}}</span>
                                                </div>
                                            </a>
                                        @else
                                            {{translate('messages.customer_not_found')}}
                                        @endif
                                    </td>
                                    <td>
                                        <div class="text-wrap w-18rem">
                                    <span class="d-block rating">
                                        {{$review->rating}} <i class="tio-star"></i>
                                    </span>
                                            <small class="d-block" data-toggle="tooltip" data-placement="left"
                                                   data-original-title="{{ $review['comment']}}" >
                                                {{Str::limit($review['comment'], 80)}}
                                            </small>
                                        </div>
                                    </td>
                                    <td>
                                        {{ \App\CentralLogics\Helpers::time_date_format($review->created_at)  }}
                                    </td>
                                    <td>
                                        <p class="text-wrap text-center" data-toggle="tooltip" data-placement="top"
                                           data-original-title="{{ $review?->reply }}">{!! $review->reply?Str::limit($review->reply, 50, '...'): translate('Not replied yet') !!}</p>
                                    </td>

                                    <td>
                                        <label class="toggle-switch toggle-switch-sm" for="reviewCheckbox{{$review->id}}">
                                            <input type="checkbox" data-id="status-{{$review['id']}}" data-message="{{$review->status?translate('messages.You want to hide this review for customer'):translate('messages.You want to show this review for customer')}}" class="toggle-switch-input status_form_alert" id="reviewCheckbox{{$review->id}}" {{$review->status?'checked':''}}>
                                            <span class="toggle-switch-label">
                                        <span class="toggle-switch-indicator"></span>
                                    </span>
                                        </label>
                                        <form action="{{route('admin.item.reviews.status',[$review['id'],$review->status?0:1])}}" method="get" id="status-{{$review['id']}}">
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        </table>
                        @if(count($reviews) !== 0)
                            <hr>
                        @endif
                        <div class="page-area px-4 pb-3">
                            <div class="d-flex align-items-center justify-content-end">
                                <div>
                                    {!! $reviews->links() !!}
                                </div>
                            </div>
                        </div>
                        @if(count($reviews) === 0)
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
    </div>
</div>
@endsection

@push('script_2')
    <script>
        "use strict";

        $('#search-form').on('submit', function () {
            let formData = new FormData(this);
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
            $.post({
                url: '{{route('admin.item.search')}}',
                data: formData,
                cache: false,
                contentType: false,
                processData: false,
                beforeSend: function () {
                    $('#loading').show();
                },
                success: function (data) {
                    $('#set-rows').html(data.view);
                    $('.page-area').hide();
                },
                complete: function () {
                    $('#loading').hide();
                },
            });
        });

    </script>
@endpush
