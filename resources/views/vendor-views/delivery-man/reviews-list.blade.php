@extends('layouts.vendor.app')

@section('title','Review List')


@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-2 mb-sm-0">
                    <h1 class="page-header-title">
                        <span class="page-header-icon">
                            <img src="{{ asset('public/assets/admin/img/outline/rating.svg') }}" class="w--26" alt="">
                        </span>
                        <span>
                            {{translate('messages.Deliveryman reviews')}}
                        </span>
                    </h1>
                    <p class="page-header-desc">{{ translate('What customers said about the people delivering their orders.') }}</p>
                </div>
            </div>
        </div>
        <div class="row gx-2 gx-lg-3">
            <div class="col-sm-12 col-lg-12 mb-3 mb-lg-2">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-header-title"></h5>
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
                                <th>#</th>
                                <th class="w-30p">{{translate('Deliveryman')}}</th>
                                <th class="w-25p">{{translate('messages.Customer')}}</th>
                                <th>{{translate('messages.review')}}</th>
                                <th>{{translate('messages.Rating')}}</th>
                            </tr>
                            </thead>

                            <tbody>
                            @foreach($reviews as $key=>$review)
                                @if(isset($review->delivery_man))
                                    <tr>
                                        <td>{{$key+$reviews->firstItem()}}</td>
                                        <td>
                                        <span class="d-block font-size-sm text-body">
                                            <a href="{{route('vendor.delivery-man.preview',[$review['delivery_man_id']])}}">
                                                {{$review->delivery_man->f_name.' '.$review->delivery_man->l_name}}
                                            </a>
                                        </span>
                                        </td>
                                        <td>
                                            @if ($review->customer)
                                                {{$review->customer->f_name}} {{$review->customer?->l_name}}
                                            @else
                                                {{translate('No data found')}}
                                            @endif

                                        </td>
                                        <td>
                                            {{$review->comment}}
                                        </td>
                                        <td>
                                            <label class="badge badge-soft-info">
                                                {{$review->rating}} <i class="tio-star"></i>
                                            </label>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                            </tbody>
                        </table>
                        <hr>
                        <table>
                            <tfoot>
                            {!! $reviews->links() !!}
                            </tfoot>
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
                </div>
            </div>
        </div>
    </div>

@endsection

