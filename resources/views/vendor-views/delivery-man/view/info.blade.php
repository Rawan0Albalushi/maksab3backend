@extends('layouts.vendor.app')

@section('title',translate('Deliveryman preview'))

@push('css_or_js')

@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/outline/deliveryman.svg')}}" class="w--26" alt="">
                </span>
                <span>
                    {{translate('Deliveryman details')}}
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('This deliveryman\'s deliveries, earnings and account state in one place.') }}</p>
            <div class="js-nav-scroller hs-nav-scroller-horizontal">
                <ul class="nav nav-tabs mb-3 border-0 nav--tabs">
                    <li class="nav-item">
                        <a class="nav-link active" href="{{route('vendor.delivery-man.preview', ['id'=>$dm->id, 'tab'=> 'info'])}}"  aria-disabled="true">{{translate('messages.Information')}}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{route('vendor.delivery-man.preview', ['id'=>$dm->id, 'tab'=> 'transaction'])}}"  aria-disabled="true">{{translate('messages.Transaction')}}</a>
                    </li>
                </ul>
            </div>
        </div>

        <div class="row mb-3 g-3 justify-content-center">
            <div class="col-sm-6 col-md-4">
                <div class="resturant-card card--bg-1">
                    <h2 class="title">
                        {{$dm->orders_count}}
                    </h2>
                    <h5 class="subtitle">
                        {{translate('messages.Total delivered orders')}}
                    </h5>
                    <img class="resturant-icon w--30" src="{{asset('public/assets/admin/img/tick.png')}}" alt="img">
                </div>
            </div>

            <div class="col-sm-6 col-md-4">
                <div class="resturant-card card--bg-2">
                    <h2 class="title">
                        {{\App\CentralLogics\Helpers::format_currency($dm->wallet?$dm->wallet->collected_cash:0.0)}}
                    </h2>
                    <h5 class="subtitle">
                        {{translate('Cash in hand')}}
                    </h5>
                    <img class="resturant-icon w--30" src="{{asset('public/assets/admin/img/withdraw-amount.png')}}" alt="img">
                </div>
            </div>

            <div class="col-sm-6 col-md-4">
                <div class="resturant-card card--bg-3">
                    <h2 class="title">
                        {{\App\CentralLogics\Helpers::format_currency($dm->wallet?$dm->wallet->total_earning:0.00)}}
                    </h2>
                    <h5 class="subtitle">
                        {{translate('messages.Total earning')}}
                    </h5>
                    <img class="resturant-icon w--30" src="{{asset('public/assets/admin/img/pending.png')}}" alt="img">
                </div>
            </div>

        </div>
        <div class="card mb-3 mb-lg-5">
            <div class="card-header py-2">
                <div class="search--button-wrapper">
                    @include('partials._table-head', [
                        'title'    => translate('Review list'),
                        'subtitle' => translate('Ratings and comments customers left for this deliveryman.'),
                        'count'    => null,
                    ])

                    <a  href="javascript:"
                        data-url="{{route('vendor.delivery-man.status',[$dm['id'],$dm->status?0:1])}}"
                        data-title="{{translate('Are you sure?')}}"
                        data-message="{{$dm->status?'Want to suspend this deliveryman ?':'Want to unsuspend this deliveryman'}}"
                        class="btn {{$dm->status?'btn-danger':'btn-success'}}  route-alert">
                            <i class="{{ $dm->status ? 'tio-block' : 'tio-checkmark-circle-outlined' }}"></i> {{$dm->status?translate('Suspend this deliveryman'):translate('messages.Unsuspend this delivery man')}}
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row gy-3 align-items-center">
                    <div class="col-md-6">
                        <div class="d-flex align-items-center justify-content-center">
                            <img class="avatar avatar-xxl avatar-4by3 mr-4 img--120 onerror-image"
                                 data-onerror-image="{{asset('public/assets/admin/img/160x160/img1.jpg')}}"
                                 src="{{ $dm['image_full_url'] }}"
                                 alt="Image Description">
                                 <div class="d-block">
                                    <div class="rating--review">
                                        <h1 class="title">{{count($dm->rating)>0?number_format($dm->rating[0]->average, 1, '.', ' '):0}}<span class="out-of">/5</span></h1>
                                        @if (count($dm->rating)>0)
                                        @if ($dm->rating[0]->average == 5)
                                        <div class="rating">
                                            <span><i class="tio-star"></i></span>
                                            <span><i class="tio-star"></i></span>
                                            <span><i class="tio-star"></i></span>
                                            <span><i class="tio-star"></i></span>
                                            <span><i class="tio-star"></i></span>
                                        </div>
                                        @elseif ($dm->rating[0]->average < 5 && $dm->rating[0]->average >= 4.5)
                                        <div class="rating">
                                            <span><i class="tio-star"></i></span>
                                            <span><i class="tio-star"></i></span>
                                            <span><i class="tio-star"></i></span>
                                            <span><i class="tio-star"></i></span>
                                            <span><i class="tio-star-half"></i></span>
                                        </div>
                                        @elseif ($dm->rating[0]->average < 4.5 && $dm->rating[0]->average >= 4)
                                        <div class="rating">
                                            <span><i class="tio-star"></i></span>
                                            <span><i class="tio-star"></i></span>
                                            <span><i class="tio-star"></i></span>
                                            <span><i class="tio-star"></i></span>
                                            <span><i class="tio-star-outlined"></i></span>
                                        </div>
                                        @elseif ($dm->rating[0]->average < 4 && $dm->rating[0]->average >= 3.5)
                                        <div class="rating">
                                            <span><i class="tio-star"></i></span>
                                            <span><i class="tio-star"></i></span>
                                            <span><i class="tio-star"></i></span>
                                            <span><i class="tio-star-half"></i></span>
                                            <span><i class="tio-star-outlined"></i></span>
                                        </div>
                                        @elseif ($dm->rating[0]->average < 3.5 && $dm->rating[0]->average >= 3)
                                        <div class="rating">
                                            <span><i class="tio-star"></i></span>
                                            <span><i class="tio-star"></i></span>
                                            <span><i class="tio-star"></i></span>
                                            <span><i class="tio-star-outlined"></i></span>
                                            <span><i class="tio-star-outlined"></i></span>
                                        </div>
                                        @elseif ($dm->rating[0]->average < 3 && $dm->rating[0]->average >= 2.5)
                                        <div class="rating">
                                            <span><i class="tio-star"></i></span>
                                            <span><i class="tio-star"></i></span>
                                            <span><i class="tio-star-half"></i></span>
                                            <span><i class="tio-star-outlined"></i></span>
                                            <span><i class="tio-star-outlined"></i></span>
                                        </div>
                                        @elseif ($dm->rating[0]->average < 2.5 && $dm->rating[0]->average > 2)
                                        <div class="rating">
                                            <span><i class="tio-star"></i></span>
                                            <span><i class="tio-star"></i></span>
                                            <span><i class="tio-star-outlined"></i></span>
                                            <span><i class="tio-star-outlined"></i></span>
                                            <span><i class="tio-star-outlined"></i></span>
                                        </div>
                                        @elseif ($dm->rating[0]->average < 2 && $dm->rating[0]->average >= 1.5)
                                        <div class="rating">
                                            <span><i class="tio-star"></i></span>
                                            <span><i class="tio-star-half"></i></span>
                                            <span><i class="tio-star-outlined"></i></span>
                                            <span><i class="tio-star-outlined"></i></span>
                                            <span><i class="tio-star-outlined"></i></span>
                                        </div>
                                        @elseif ($dm->rating[0]->average < 1.5 && $dm->rating[0]->average > 1)
                                        <div class="rating">
                                            <span><i class="tio-star"></i></span>
                                            <span><i class="tio-star-outlined"></i></span>
                                            <span><i class="tio-star-outlined"></i></span>
                                            <span><i class="tio-star-outlined"></i></span>
                                            <span><i class="tio-star-outlined"></i></span>
                                        </div>
                                        @elseif ($dm->rating[0]->average < 1 && $dm->rating[0]->average > 0)
                                        <div class="rating">
                                            <span><i class="tio-star-half"></i></span>
                                            <span><i class="tio-star-outlined"></i></span>
                                            <span><i class="tio-star-outlined"></i></span>
                                            <span><i class="tio-star-outlined"></i></span>
                                            <span><i class="tio-star-outlined"></i></span>
                                        </div>
                                        @elseif ($dm->rating[0]->average == 1)
                                        <div class="rating">
                                            <span><i class="tio-star"></i></span>
                                            <span><i class="tio-star-outlined"></i></span>
                                            <span><i class="tio-star-outlined"></i></span>
                                            <span><i class="tio-star-outlined"></i></span>
                                            <span><i class="tio-star-outlined"></i></span>
                                        </div>
                                        @elseif ($dm->rating[0]->average == 0)
                                        <div class="rating">
                                            <span><i class="tio-star-outlined"></i></span>
                                            <span><i class="tio-star-outlined"></i></span>
                                            <span><i class="tio-star-outlined"></i></span>
                                            <span><i class="tio-star-outlined"></i></span>
                                            <span><i class="tio-star-outlined"></i></span>
                                        </div>
                                        @endif
                                    @endif
                                    <div class="info">

                                        <span>{{$review_total}} {{translate('messages.Reviews')}}</span>
                                    </div>
                                    </div>
                                </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <ul class="list-unstyled list-unstyled-py-2 mb-0 rating--review-right py-3">

                            <li class="d-flex align-items-center font-size-sm">
                                <span
                                    class="progress-name mr-3">{{translate('excellent')}}</span>
                                <div class="progress flex-grow-1">
                                    <div class="progress-bar" role="progressbar"
                                         style="width: {{$review_total==0?0:($rating_breakdown[5]/$review_total)*100}}%;"
                                         aria-valuenow="{{$review_total==0?0:($rating_breakdown[5]/$review_total)*100}}"
                                         aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <span class="ml-3">{{$rating_breakdown[5]}}</span>
                            </li>

                            <li class="d-flex align-items-center font-size-sm">
                                <span class="progress-name mr-3">{{translate('Good')}}</span>
                                <div class="progress flex-grow-1">
                                    <div class="progress-bar" role="progressbar"
                                         style="width: {{$review_total==0?0:($rating_breakdown[4]/$review_total)*100}}%;"
                                         aria-valuenow="{{$review_total==0?0:($rating_breakdown[4]/$review_total)*100}}"
                                         aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <span class="ml-3">{{$rating_breakdown[4]}}</span>
                            </li>

                            <li class="d-flex align-items-center font-size-sm">
                                <span class="progress-name mr-3">{{translate('average')}}</span>
                                <div class="progress flex-grow-1">
                                    <div class="progress-bar" role="progressbar"
                                         style="width: {{$review_total==0?0:($rating_breakdown[3]/$review_total)*100}}%;"
                                         aria-valuenow="{{$review_total==0?0:($rating_breakdown[3]/$review_total)*100}}"
                                         aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <span class="ml-3">{{$rating_breakdown[3]}}</span>
                            </li>

                            <li class="d-flex align-items-center font-size-sm">
                                <span class="progress-name mr-3">{{translate('Below average')}}</span>
                                <div class="progress flex-grow-1">
                                    <div class="progress-bar" role="progressbar"
                                         style="width: {{$review_total==0?0:($rating_breakdown[2]/$review_total)*100}}%;"
                                         aria-valuenow="{{$review_total==0?0:($rating_breakdown[2]/$review_total)*100}}"
                                         aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <span class="ml-3">{{$rating_breakdown[2]}}</span>
                            </li>

                            <li class="d-flex align-items-center font-size-sm">
                                <span class="progress-name mr-3">{{translate('poor')}}</span>
                                <div class="progress flex-grow-1">
                                    <div class="progress-bar" role="progressbar"
                                         style="width: {{$review_total==0?0:($rating_breakdown[1]/$review_total)*100}}%;"
                                         aria-valuenow="{{$review_total==0?0:($rating_breakdown[1]/$review_total)*100}}"
                                         aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <span class="ml-3">{{$rating_breakdown[1]}}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>


        @if ($store->review_permission)

        <div class="card">

            <div class="table-responsive datatable-custom">
                <table id="datatable" class="table table-borderless table-thead-bordered table-nowrap card-table"
                       data-hs-datatables-options='{
                     "columnDefs": [{
                        "targets": [0, 3],
                        "orderable": false
                      }],
                     "order": [],
                     "info": {
                       "totalQty": "#datatableWithPaginationInfoTotalQty"
                     },
                     "search": "#datatableSearch",
                     "entries": "#datatableEntries",
                     "pageLength": 25,
                     "isResponsive": false,
                     "isShowPaging": false,
                     "pagination": "datatablePagination"
                   }'>
                    <thead class="thead-light">
                    <tr>
                        <th class="border-0">{{translate('messages.Reviewer')}}</th>
                        <th class="border-0">{{translate('messages.review')}}</th>
                        <th class="border-0">{{translate('Attachment')}}</th>
                        <th class="border-0">{{translate('messages.Date')}}</th>
                    </tr>
                    </thead>

                    <tbody>

                    @foreach($reviews as $review)
                        <tr>
                            <td>
                                @if ($review->customer)
                                    <div class="d-flex align-items-center">
                                        @include('partials._user-avatar', [
                                            'imageUrl'  => $review->customer->image_full_url,
                                            'proStatus' => $review->customer->pro_status ?? false,
                                            'size'      => 75,
                                        ])
                                        <div class="ml-3">
                                        <span class="d-block h5 text-hover-primary mb-0">{{$review->customer['f_name']." ".$review->customer['l_name']}} </span>
                                            <span class="d-block font-size-sm text-body">{{$review->customer->email}}</span>
                                        </div>
                                    </div>
                                @else
                                    {{translate('No data found')}}
                                @endif
                            </td>
                            <td>
                                <div class="text-wrap w-18rem" >
                                    <div class="d-flex mb-2">
                                        <label class="badge badge-soft-info">
                                            {{$review->rating}} <i class="tio-star"></i>
                                        </label>
                                    </div>
                                    <p>
                                        {{$review['comment']}}
                                    </p>
                                </div>
                            </td>
                            <td>
                                @foreach(json_decode($review['attachment'],true) as $attachment)
                                @php($attachment = is_array($attachment)?$attachment:['img'=>$attachment,'storage'=>'public'])
                                    <img width="100" class="onerror-image" data-onerror-image="{{asset('public/assets/admin/img/160x160/img2.jpg')}}"  src="{{\App\CentralLogics\Helpers::get_full_url($attachment['img'],$attachment['img'],$attachment['storage'] ?? 'public') }}"
                                    alt="image">
                                @endforeach
                            </td>
                            <td>
                                {{date('d M Y '. config('timeformat'),strtotime($review['created_at']))}}
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>



            @if(count($reviews) === 0)
                <div class="empty--data">
                    <img src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="public">
                    <h5>
                        {{translate('No data found')}}
                    </h5>
                </div>
            @endif

            <div class="card-footer">
                <div class="row justify-content-center justify-content-sm-between align-items-sm-center">
                    <div class="col-12">
                        {!! $reviews->links() !!}
                    </div>
                </div>
            </div>
        </div>

        @endif
    </div>
@endsection


