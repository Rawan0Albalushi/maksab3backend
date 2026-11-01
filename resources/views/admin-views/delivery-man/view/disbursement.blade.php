@extends('layouts.admin.app')

@section('title', translate('Deliveryman preview'))

@push('css_or_js')

@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            @include('admin-views.delivery-man.partials._page_header')

            <div class="">
                @include('admin-views.delivery-man.partials._tab_menu')
            </div>
        </div>

        <div class="card mb-3 mb-lg-5 mt-2">
            <div class="card-header border-0 py-2">
                <div class="search--button-wrapper">
                    <h5 class="card-title">
                        {{ translate('Total disbursements') }} <span class="badge badge-soft-secondary ml-2"
                            id="countItems">{{ $disbursements->total() }}</span>
                    </h5>
                    <form class="search-form">
                        <div class="input--group input-group input-group-merge input-group-flush">
                            <input class="form-control" value="{{ request()?->search ?? null }}"
                                placeholder="{{ translate('Search by disbursement id') }}" name="search">
                            <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                        </div>
                    </form>
                    <div class="hs-unfold ml-3">
                        <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle btn export-btn btn-outline-primary btn--primary font--sm"
                            href="javascript:;" data-hs-unfold-options='{
                                "target": "#usersExportDropdown",
                                "type": "css-animation"
                            }'>
                            <i class="tio-download-to mr-1"></i> {{translate('messages.Export')}}
                        </a>
                        <div id="usersExportDropdown"
                            class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">
                            <span class="dropdown-header">{{translate('messages.Download options')}}</span>
                            <a id="export-excel" class="dropdown-item"
                                href="{{route('admin.users.delivery-man.disbursement-export', ['id' => $deliveryMan->id, 'type' => 'excel', request()->getQueryString()])}}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                    src="{{asset('public/assets/admin')}}/svg/components/excel.svg" alt="Image Description">
                                Excel
                            </a>
                            <a id="export-csv" class="dropdown-item"
                                href="{{route('admin.users.delivery-man.disbursement-export', ['id' => $deliveryMan->id, 'type' => 'excel', request()->getQueryString()])}}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                    src="{{asset('public/assets/admin')}}/svg/components/placeholder-csv-format.svg"
                                    alt="Image Description">
                                CSV
                            </a>
                        </div>
                    </div>

                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-thead-bordered table-align-middle card-table">
                        <thead>
                            <tr>
                                <th>{{ translate('SL') }}</th>
                                <th>ID</th>
                                <th>{{ translate('Disburse amount') }}</th>
                                <th>{{ translate('Payment method') }}</th>
                                <th>{{ translate('Status') }}</th>
                                <th>
                                    <div class="text-center">
                                        {{ translate('Action') }}
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($disbursements as $key => $disbursement)
                                <tr>
                                    <td>
                                        <span class="font-weight-bold">{{ $key + $disbursements->firstItem() }}</span>
                                    </td>
                                    <td>
                                        #{{ $disbursement->disbursement_id }}
                                    </td>
                                    <td>
                                        {{\App\CentralLogics\Helpers::format_currency($disbursement['disbursement_amount'])}}
                                    </td>
                                    <td>
                                        <div>
                                            {{$disbursement?->withdraw_method?->method_name ?? translate('messages.N/A')}}
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-soft-primary">{{$disbursement->status}}</span>
                                    </td>
                                    <td>
                                        <div class="btn--container justify-content-center">
                                            <a class="btn btn-sm action-btn action-btn--view"
                                                data-toggle="modal" data-target="#payment-info-{{$disbursement->id}}"
                                                title="View Details">
                                                <i class="tio-visible-outlined"></i>
                                            </a>
                                        </div>
                                    </td>
                                    <div class="modal fade" id="payment-info-{{$disbursement->id}}">
                                        <div class="modal-dialog modal-xl">
                                            <div class="modal-content">
                                                <div class="modal-header pb-4">
                                                    <button type="button"
                                                        class="payment-modal-close btn-close border-0 outline-0 bg-transparent"
                                                        data-dismiss="modal">
                                                        <i class="tio-clear"></i>
                                                    </button>
                                                    <div class="w-100 text-center">
                                                        <h2 class="mb-2">{{ translate('Payment information') }}</h2>
                                                        <div>
                                                            <span class="mr-2">{{ translate('Disbursement ID') }}</span>
                                                            <strong>#{{$disbursement->disbursement_id}}</strong>
                                                        </div>
                                                        <div class="mt-2">
                                                            <span class="mr-2">{{ translate('Status') }}</span>
                                                            <span
                                                                class="badge badge-soft-primary">{{$disbursement->status}}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="card shadow--card-2">
                                                        <div class="card-body">
                                                            <div class="d-flex flex-wrap payment-info-modal-info p-xl-4">
                                                                <div class="item">
                                                                    <h5>{{ translate('Deliveryman information') }}</h5>
                                                                    <ul class="item-list">
                                                                        <li class="d-flex flex-wrap">
                                                                            <span class="name">{{ translate('Name') }}</span>
                                                                            <span>:</span>
                                                                            <strong>{{$disbursement->delivery_man->f_name . ' ' . $disbursement->delivery_man->l_name}}</strong>
                                                                        </li>
                                                                        <li class="d-flex flex-wrap">
                                                                            <span class="name">{{ translate('Contact') }}</span>
                                                                            <span>:</span>
                                                                            <strong>{{$disbursement?->delivery_man?->phone}}</strong>
                                                                        </li>
                                                                    </ul>
                                                                </div>
                                                                <div class="item">

                                                                </div>
                                                                <div class="item w-100">
                                                                    <h5>{{ translate('Account information') }}</h5>
                                                                    <ul class="item-list">
                                                                        <li class="d-flex flex-wrap">
                                                                            <span
                                                                                class="name">{{ translate('Payment method') }}</span>
                                                                            <strong>{{$disbursement?->withdraw_method?->method_name ?? translate('messages.N/A')}}</strong>
                                                                        </li>
                                                                        <li class="d-flex flex-wrap">
                                                                            <span class="name">{{ translate('Amount') }}</span>
                                                                            <strong>{{\App\CentralLogics\Helpers::format_currency($disbursement['disbursement_amount'])}}</strong>
                                                                        </li>
                                                                        @if ($disbursement?->withdraw_method?->method_fields)
                                                                            @forelse(json_decode($disbursement->withdraw_method?->method_fields, true) as $key => $item)
                                                                                <li class="d-flex flex-wrap">
                                                                                    <span class="name">{{  translate($key) }}</span>
                                                                                    <strong>{{$item}}</strong>
                                                                                </li>
                                                                            @empty

                                                                            @endforelse

                                                                        @endif

                                                                    </ul>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @if (count($disbursements) === 0)
                        <div class="empty--data">
                            <img src="{{ asset('/public/assets/admin/svg/illustrations/sorry.svg') }}" alt="public">
                            <h5>
                                {{translate('No data found')}}
                            </h5>
                        </div>
                    @endif
                </div>
            </div>
            <div class="page-area px-4 pb-3">
                <div class="d-flex align-items-center justify-content-end">
                    <div>
                        {!!$disbursements->links()!!}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script_2')

@endpush