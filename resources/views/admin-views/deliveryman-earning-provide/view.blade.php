@extends('layouts.admin.app')
@section('title',translate('Account transaction information'))
@push('css_or_js')

@endpush

@section('content')
<div class="content container-fluid">
    <div class="d-sm-flex row align-items-center justify-content-between mb-2">
        <div class="col-md-6">
             <h4 class=" mb-0 text-black-50">{{translate('messages.Deliverymen earning provide information')}}</h4>
        </div>
    </div>
    <div class="row mt-3">

        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="h3 mb-0  ">{{$account_transaction->restaurant?translate('messages.Store'):translate('Deliveryman information')}}</h3>
                </div>
                <div class="card-body">
                    <div class="col-md-8 mt-2">
                        <h4>{{translate('Name')}}: {{$account_transaction->restaurant ? $account_transaction->restaurant->name : $account_transaction->deliveryman->f_name.' '.$account_transaction->deliveryman->l_name}}</h4>
                        <h6>{{translate('Phone')}}  : {{$account_transaction->restaurant ? $account_transaction->restaurant->phone : $account_transaction->deliveryman->phone}}</h6>
                        <h6>{{translate('messages.collected_cash')}} : {{\App\CentralLogics\Helpers::format_currency($account_transaction->restaurant ? $account_transaction->restaurant->vendor->wallet->collected_cash : $account_transaction->deliveryman->wallet->collected_cash)}}</h6>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">

            <div class="card">
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
