@extends('layouts.admin.app')
@section('title',translate('Account transaction information'))
@push('css_or_js')

@endpush

@section('content')
<div class="content container-fluid">
    <div class="page-header">
        <h1 class="page-header-title">
            <span class="page-header-icon">
                <img src="{{asset('public/assets/admin/img/outline/report.svg')}}" class="w--26" alt="">
            </span>
            <span>
                {{translate('messages.Account transaction information')}}
            </span>
        </h1>
        <p class="page-header-desc">{{ translate('Everything recorded against this cash transaction, and who it was collected from.') }}</p>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <h3 class="h3 mb-0  ">{{$account_transaction->from_type == 'store'?translate('messages.Store'):translate('Deliveryman information')}}</h3>
                </div>
                <div class="card-body">
                    <div class="col-md-8 mt-2">
                        <h4>{{translate('Name')}}: {{$account_transaction->from_type == 'store' ?($account_transaction->store? $account_transaction->store->name : translate('messages.Store deleted')):($account_transaction->deliveryman? $account_transaction->deliveryman->f_name.' '.$account_transaction->deliveryman->l_name : translate('No data found'))}}</h4>
                        <h6>{{translate('Phone')}}  : {{$account_transaction->from_type == 'store'?($account_transaction->store ? $account_transaction->store->phone : translate('messages.Store deleted')):($account_transaction->deliveryman ? $account_transaction->deliveryman->phone : translate('No data found'))}}</h6>
                        <h6>{{translate('Cash in hand')}} : {{\App\CentralLogics\Helpers::format_currency($account_transaction->from_type == 'store' ? ($account_transaction->store ? $account_transaction->store->vendor->wallet->collected_cash : 0): ($account_transaction->deliveryman ? $account_transaction->deliveryman->wallet->collected_cash : 0))}}</h6>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <h3 class="h3 mb-0  ">{{translate('messages.Transaction information')}} </h3>
                </div>
                <div class="card-body">
                    <h6>{{translate('Amount')}} : {{\App\CentralLogics\Helpers::format_currency($account_transaction->amount)}}</h6>
                    <h6 class="text-capitalize">{{translate('messages.Time')}} : {{$account_transaction->created_at->format('Y-m-d '.config('timeformat'))}}</h6>
                    <h6>{{translate('messages.method')}} : {{$account_transaction->method}}</h6>
                    <h6>{{translate('messages.reference')}} : {{$account_transaction->ref}}</h6>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('script')

@endpush
