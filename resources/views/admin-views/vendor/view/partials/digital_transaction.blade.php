<div>
    <div class="table-responsive">
        <table id="datatable"
            class="table table-thead-bordered table-align-middle card-table">
            <thead class="thead-light">
                <tr>
                    <th class="w--1 border-0">{{translate('SL')}}</th>
                    <th class="w--1 border-0">{{translate('Order ID')}}</th>
                    <th class="w--2 border-0">{{translate('Total order amount')}}</th>
                    <th class="w--3 border-0">{{translate('messages.store_earned')}}</th>
                    <th class="w--1 border-0">{{translate('Admin earned')}}</th>
                    <th class="w--1 border-0">{{translate('Delivery fee')}}</th>
                    <th class="w--1 border-0">{{translate('VAT/tax')}}</th>
                </tr>
            </thead>
            <tbody>
            @php($digital_transaction = $transactions)
            @foreach($digital_transaction as $k=>$dt)
                <tr>
                    <td scope="row">{{$k+$digital_transaction->firstItem()}}</td>
                    <td><a href="{{route('admin.order.details',$dt->order_id)}}">{{$dt->order_id}}</a></td>
                    <td>{{\App\CentralLogics\Helpers::format_currency($dt->order_amount)}}</td>
                    <td>{{\App\CentralLogics\Helpers::format_currency($dt->store_amount - $dt->tax)}}</td>
                    <td>{{\App\CentralLogics\Helpers::format_currency($dt->admin_commission)}}</td>
                    <td>{{\App\CentralLogics\Helpers::format_currency($dt->delivery_charge + ($dt->pro_delivery_discount ?? 0))}}</td>
                    <td>{{\App\CentralLogics\Helpers::format_currency($dt->tax)}}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@if(count($digital_transaction) !== 0)
<hr>
@endif
<div class="page-area">
    {!! $digital_transaction->links() !!}
</div>
@if(count($digital_transaction) === 0)
<div class="empty--data">
    <img src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="public">
    <h5>
        {{translate('No data found')}}
    </h5>
</div>
@endif
