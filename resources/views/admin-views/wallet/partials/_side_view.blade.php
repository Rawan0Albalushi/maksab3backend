

<div class="d-flex flex-column align-items-center gap-1 mb-3">
    <h3 class="mb-3">{{translate('Withdraw information')}}</h3>
    <div class="d-flex gap-2 align-items-center mb-1 flex-wrap">
        <span>{{translate('Withdraw amount')}}:</span>
        <span class="font-semibold">{{\App\CentralLogics\Helpers::format_currency($withdraw['amount'])}}</span>
@if ($withdraw->approved == 1)
<label class="badge badge-soft-success mb-0">{{translate('Approved')}}</label>

@elseif($withdraw->approved ==0)
<label class="badge badge-soft-primary mb-0">{{translate('Pending')}}</label>
@else

<label class="badge badge-soft-danger mb-0">{{translate('Denied')}}</label>
@endif


    </div>
    <div class="d-flex gap-2 align-items-center fs-12">
        <span>{{translate('Request time')}}:</span>
        <span>{{ \App\CentralLogics\Helpers::time_date_format($withdraw->created_at) }}</span>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">
        <h6 class="mb-0 font-medium">{{translate('Store information')}}</h6>
    </div>
    <div class="card-body">
        <div class="key-val-list d-flex flex-column gap-2" style="--min-width: 60px">
            <div class="key-val-list-item d-flex gap-3">
                <span>{{ translate('Name') }}:</span>
                <span>{{$withdraw->vendor->stores[0]->name}}</span>
            </div>
            <div class="key-val-list-item d-flex gap-3">
                <span>{{ translate('Address') }}:</span>
                <a href="https://www.google.com/maps/search/?api=1&query={{ data_get($withdraw->vendor->stores[0],'latitude',0)}},{{ data_get($withdraw->vendor->stores[0],'longitude',0)}}" target="_blank">{{ $withdraw->vendor->stores[0]['address'] }}</a>

            </div>
        </div>

        <div class="rounded bg-light p-3 mt-3">
            <div class="key-val-list-item d-flex gap-3">
                <span>{{ translate('Store balance') }}:</span>
                <span class="font-semibold text-primary fs-16"> {{ $withdraw->vendor->wallet->balance > 0 ? \App\CentralLogics\Helpers::format_currency($withdraw->vendor->wallet->balance) : 0}}</span>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">
        <h6 class="mb-0 font-medium">{{translate('Owner information')}}</h6>
    </div>
    <div class="card-body">
        <div class="key-val-list d-flex flex-column gap-2" style="--min-width: 60px">
            <div class="key-val-list-item d-flex gap-3">
                <span>{{ translate('Name') }}:</span>
                <span>{{$withdraw->vendor->f_name.' '.$withdraw->vendor->l_name}}</span>
            </div>
            <div class="key-val-list-item d-flex gap-3">
                <span>{{ translate('email') }}:</span>
                <a href="mailto:{{$withdraw->vendor->email}}" class="text-dark">{{$withdraw->vendor->email}}</a>
            </div>
            <div class="key-val-list-item d-flex gap-3">
                <span>{{ translate('Phone') }}:</span>
                <a href="tel:{{$withdraw->vendor->phone}}" class="text-dark">{{$withdraw->vendor->phone}}</a>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">
        <h6 class="mb-0 font-medium">{{translate('Payment information')}}</h6>
    </div>
    <div class="card-body">
        <div class="key-val-list d-flex flex-column gap-2" style="--min-width: 60px">
          <div class="key-val-list-item d-flex gap-3">
            <span>{{ translate('method') }}:</span>
            <span>{{ $withdraw->type === 'disbursement' ? $withdraw->disbursementMethod?->method_name : $withdraw->method?->method_name }}</span>
        </div>
        @if($withdraw?->withdrawal_method_fields)
        @foreach(json_decode($withdraw?->withdrawal_method_fields, true) as $key => $item)
            <div class="key-val-list-item d-flex gap-3">
                <span>{{ translate($key) }}:</span>
                <span>{{ is_array($item) ? '' : htmlspecialchars($item) }}</span>
            </div>
        @endforeach
        @else
            <h5 class="text-capitalize">{{ translate('No data found') }}</h5>
        @endif
        </div>
    </div>
</div>

    @if ($withdraw->approved == 1)
    <div class="">
        <h5 class="font-medium">{{translate('Approved note')}}</h5>
        <div class="rounded bg-light p-3">
            {{str_replace('_' ,' ' ,$withdraw->transaction_note)}}
        </div>
        @elseif($withdraw->approved == 2)
    </div> <div class="">
        <h5 class="font-medium">{{translate('Denial note')}}</h5>
        <div class="rounded bg-light p-3">
            {{str_replace('_' ,' ' ,$withdraw->transaction_note)}}
        </div>
    </div>
    @endif

    @if ($withdraw->approved == 0)
    <div class="mt-4 d-flex justify-content-center gap-3">
        <button type="button" data-id="{{$withdraw->id}}" class="btn btn-soft-danger min-w-100px show-deny-view"><i class="tio-clear-circle-outlined"></i> {{translate('Deny')}}</button>
        <button type="button" data-id="{{$withdraw->id}}" class="btn btn-success min-w-100px show-approve-view"><i class="tio-checkmark-circle-outlined"></i> {{translate('Approve')}}</button>
    </div>
    @endif
