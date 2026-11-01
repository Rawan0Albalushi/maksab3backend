@extends('layouts.vendor.app')

@section('title',translate('Dashboard'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
    <div class="content container-fluid">


         @if($can_dashboard)
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm">
                    <h1 class="page-header-title">
                    <span class="page-header-icon">
                        <img src="{{asset('public/assets/admin/img/outline/category.svg')}}" class="w--26" alt="">
                    </span>
                        <span>{{translate('Dashboard')}}</span>
                    </h1>
                    <p class="page-header-desc">{{ translate('Today\'s orders, earnings and anything waiting on you.') }}</p>
                </div>
                <div class="col-sm ">
                    @if (isset($out_of_stock_count) &&   $out_of_stock_count  > 1 )
                            <div class="alert __alert-4 m-0 py-1 px-2  max-w-450px hide-warning d-none" role="alert">
                                <div class="alert-inner">
                                    <img class="rounded mr-1"  width="25" src="{{ asset('/public/assets/admin/img/invalid-icon.png') }}" alt="">
                                    <div class="cont">
                                        <h4 class="mb-2">{{ translate('warning') }} </h4>{{  ( $out_of_stock_count -1).'+ '.  translate('More products are low on stock.') }}
                                        <br>
                                        <a data-id="stock_out_reminder_close_btn"  class="text-primary text-underline reming_me_later">{{ translate('Remind me later') }}</a>  &nbsp; &nbsp; <a href="{{ route('vendor.item.stock-limit-list') }}" class="text-primary text-underline">{{ translate('Click to view') }}</a>
                                    </div>
                                </div>
                                <button class="position-absolute right-0 top-0 py-2 px-2 bg-transparent border-0 outline-none shadow-none reming_me_later"  type="button">
                                    <i class="tio-clear fz--18"></i>
                                </button>
                            </div>

                            @elseif (isset($out_of_stock_count)  &&  $out_of_stock_count  == 1  && isset($item))
                            <div class="alert __alert-4 m-0 py-1 px-2  max-w-450px hide-warning d-none" role="alert">
                                <div class="alert-inner">
                                    <img class="aspect-1-1 mr-1 object--contain rounded" width="100" src="{{ $item?->image_full_url ?? asset('/public/assets/admin/img/100x100/food-default-image.png') }}" alt="">
                                    <div class="cont">
                                        <h4 class="mb-2">{{ $item?->name }} </h4>{{  translate('This product is low stock.') }}
                                        <br>
                                        <a
                                        data-id="stock_out_reminder_close_btn"  class="text-primary text-underline reming_me_later">{{ translate('Remind me later') }}</a>  &nbsp; &nbsp; <a href="{{ route('vendor.item.stock-limit-list') }}" class="text-primary text-underline">{{ translate('Click to view') }}</a>
                                    </div>
                                </div>
                                <button class="position-absolute right-0 top-0 py-2 px-2 bg-transparent border-0 outline-none shadow-none reming_me_later"  type="button">
                                    <i class="tio-clear fz--18"></i>
                                </button>
                            </div>

                        @endif




                    <div class="promo-card-2">
                        <img src="{{asset('public/assets/admin/img/promo-arrow.png')}}" class="shapes" alt="">
                        <div class="left">
                            <img src="{{asset('public/assets/admin/img/promo.png')}}" width="40" class="mw-100" alt="">
                            <div class="inner">
                                <div class="d-flex flex-wrap flex-md-nowrap align-items-center justify-content-between gap-2">
                                    <div>
                                        <h4 class="m-0 text-white">{{ translate('Want to get highlighted?') }}</h4>
                                        <p class="m-0 text-white">
                                            {{ translate('Create ads to get highlighted on the app and web browser') }}
                                        </p>
                                    </div>
                                    <a href="{{ route('vendor.advertisement.create') }}" class="btn btn-white text-nowrap font-semibold text-dark"><i class="tio-add-circle"></i> {{ translate('Create advertisement') }}</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <div class="row gx-2 gx-lg-3 mb-2">
                    <div class="col-md-9">
                        <h4><i class="tio-chart-bar-4 fz-30px"></i>{{translate('messages.Dashboard order statistics')}}</h4>
                    </div>
                    <div class="col-md-3">
                        <select class="custom-select order_stats_update" name="statistics_type">
                            <option
                                value="overall" {{$params['statistics_type'] == 'overall'?'selected':''}}>
                                {{translate('Overall statistics')}}
                            </option>
                            <option
                                value="today" {{$params['statistics_type'] == 'today'?'selected':''}}>
                                {{translate('Today\'s statistics')}}
                            </option>
                            <option
                                value="this_month" {{$params['statistics_type'] == 'this_month'?'selected':''}}>
                                {{translate('This month\'s statistics')}}
                            </option>
                        </select>
                    </div>
                </div>
                <div class="py-2"></div>
                <div class="row g-2" id="order_stats">
                    @include('vendor-views.partials._dashboard-order-stats',['data'=>$data])
                </div>
            </div>
        </div>

        <div class="row gx-2 gx-lg-3">
            <div class="col-lg-12 mb-3 mb-lg-12">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="row mb-2 align-items-center">
                            <div class="col-sm mb-2 mb-sm-0">
                                <div class="d-flex flex-wrap justify-content-center align-items-center">
                                    @php($amount=array_sum($earning))
                                    <span class="h5 m-0 px-2 mr-3 fz--11 d-flex align-items-center mb-2 mb-md-0">
                                        <span class="legend-indicator chart-bg-2"></span>
                                        {{translate('messages.Total earning')}} : <span>{{\App\CentralLogics\Helpers::format_currency(array_sum($earning))}}</span>
                                    </span>
                                    <span class="h5  m-0 fz--11 d-flex align-items-center mb-2 mb-md-0">
                                        <span class="legend-indicator chart-bg-3"></span>
                                        {{translate('Commission given')}} : <span>{{\App\CentralLogics\Helpers::format_currency(array_sum($commission))}}</span>
                                    </span>
                                </div>

                            </div>

                            <div class="col-sm-auto align-self-sm-end">
                                <h5 class="text-center">
                                    {{translate('messages.Yearly statistics')}}
                                    <i class="tio-chart-bar-4 fz--40px"></i>
                                </h5>
                            </div>
                        </div>

                        <div class="chartjs-custom">
                            <canvas id="updatingData" class="h-20rem"
                                    data-hs-chartjs-options='{
                            "type": "bar",
                            "data": {
                              "labels": ["Jan","Feb","Mar","April","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"],
                              "datasets": [{
                                "data": [{{$earning[1]}},{{$earning[2]}},{{$earning[3]}},{{$earning[4]}},{{$earning[5]}},{{$earning[6]}},{{$earning[7]}},{{$earning[8]}},{{$earning[9]}},{{$earning[10]}},{{$earning[11]}},{{$earning[12]}}],
                                "backgroundColor": "#00AA96",
                                "hoverBackgroundColor": "#00AA96",
                                "borderColor": "#00AA96"
                              },
                              {
                                "data": [{{$commission[1]}},{{$commission[2]}},{{$commission[3]}},{{$commission[4]}},{{$commission[5]}},{{$commission[6]}},{{$commission[7]}},{{$commission[8]}},{{$commission[9]}},{{$commission[10]}},{{$commission[11]}},{{$commission[12]}}],
                                "backgroundColor": "#b9e0e0",
                                "borderColor": "#b9e0e0"
                              }]
                            },
                            "options": {
                              "scales": {
                                "yAxes": [{
                                  "gridLines": {
                                    "color": "#e7eaf3",
                                    "drawBorder": false,
                                    "zeroLineColor": "#e7eaf3"
                                  },
                                  "ticks": {
                                    "beginAtZero": true,
                                    "stepSize": {{$amount>1?20000:1}},
                                    "fontSize": 12,
                                    "fontColor": "#97a4af",
                                    "fontFamily": "Open Sans, sans-serif",
                                    "padding": 10,
                                    "postfix": " {{\App\CentralLogics\Helpers::currency_symbol()}}"
                                  }
                                }],
                                "xAxes": [{
                                  "gridLines": {
                                    "display": false,
                                    "drawBorder": false
                                  },
                                  "ticks": {
                                    "fontSize": 12,
                                    "fontColor": "#97a4af",
                                    "fontFamily": "Open Sans, sans-serif",
                                    "padding": 5
                                  },
                                  "categoryPercentage": 0.5,
                                  "maxBarThickness": "10"
                                }]
                              },
                              "cornerRadius": 2,
                              "tooltips": {
                                "prefix": " ",
                                "hasIndicator": true,
                                "mode": "index",
                                "intersect": false
                              },
                              "hover": {
                                "mode": "nearest",
                                "intersect": true
                              }
                            }
                          }'></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 mt-3">
                <div class="card h-100" id="top-selling-items-view">
                    @include('vendor-views.partials._top-selling-items',['top_sell'=>$data['top_sell']])
                </div>
            </div>

            <div class="col-lg-6 mt-3">
                <div class="card h-100" id="top-rated-items-view">
                    @include('vendor-views.partials._most-rated-items',['most_rated_items'=>$data['most_rated_items']])
                </div>
            </div>


        </div>
        @else
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-2 mb-sm-0">
                    <h1 class="page-header-title">{{translate('messages.welcome')}}, {{$employee_first_name}}.</h1>
                    <p class="page-header-desc">{{ translate('Today\'s orders, earnings and anything waiting on you.') }}</p>
                    <p class="page-header-text">{{translate('messages.Employee welcome message')}}</p>
                </div>
            </div>
        </div>
        @endif
    </div>



@endsection

@push('script')
    <script src="{{asset('public/assets/admin')}}/vendor/chart.js/dist/Chart.min.js"></script>
    <script src="{{asset('public/assets/admin')}}/vendor/chart.js.extensions/chartjs-extensions.js"></script>
    <script
        src="{{asset('public/assets/admin')}}/vendor/chartjs-plugin-datalabels/dist/chartjs-plugin-datalabels.min.js"></script>

@endpush


@push('script_2')
    <script>
"use strict";
        $('#free-trial-modal').modal('show');

        Chart.plugins.unregister(ChartDataLabels);

        $('.js-chart').each(function () {
            $.HSCore.components.HSChartJS.init($(this));
        });

        let updatingChart = $.HSCore.components.HSChartJS.init($('#updatingData'));

        $('.order_stats_update').on('change',function (){
            let type = $(this).val();
            order_stats_update(type);
        })

        function order_stats_update(type) {
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
            $.post({
                url: '{{route('vendor.dashboard.order-stats')}}',
                data: {
                    statistics_type: type
                },
                beforeSend: function () {
                    $('#loading').show()
                },
                success: function (data) {
                    insert_param('statistics_type',type);
                    $('#order_stats').html(data.view)
                },
                complete: function () {
                    $('#loading').hide()
                }
            });
        }

        function insert_param(key, value) {
            key = encodeURIComponent(key);
            value = encodeURIComponent(value);
            // kvp looks like ['key1=value1', 'key2=value2', ...]
            let kvp = document.location.search.substr(1).split('&');
            let i = 0;

            for (; i < kvp.length; i++) {
                if (kvp[i].startsWith(key + '=')) {
                    let pair = kvp[i].split('=');
                    pair[1] = value;
                    kvp[i] = pair.join('=');
                    break;
                }
            }
            if (i >= kvp.length) {
                kvp[kvp.length] = [key, value].join('=');
            }
            // can return this or...
            let params = kvp.join('&');
            // change url page with new params
            window.history.pushState('page2', 'Title', '{{url()->current()}}?' + params);
        }

        $(document).on('click', '.reming_me_later', function () {
            $('.hide-warning').hide();
            document.cookie = "stock_out_reminder_close_btn=accepted; path=/";
        });
        if(document.cookie.indexOf("stock_out_reminder_close_btn=accepted") === -1){
            $('.hide-warning').removeClass('d-none')
        }
        if(document.cookie.indexOf("stock_out_reminder_close_btn=accepted") !== -1){
            $('.hide-warning').hide();
        }

    </script>
@endpush
