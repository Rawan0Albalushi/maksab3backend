<div>
    <div class="table-responsive">
        <table id="datatable"
            class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
            <thead class="thead-light">
                <tr>
                    <th class="border-0">{{translate('SL')}}</th>
                    <th class="border-0">{{translate('messages.Received at')}}</th>
                    <th class="border-0">{{translate('messages.Balance before transaction')}}</th>
                    <th class="border-0">{{translate('Amount')}}</th>
                    <th class="border-0">{{translate('messages.reference')}}</th>
                </tr>
            </thead>
            <tbody>
            @php($account_transaction = $transactions)
            @foreach($account_transaction as $k=>$at)
                <tr>
                    <td>{{$k+$account_transaction->firstItem()}}</td>
                    <td>{{$at->created_at->format('Y-m-d '.config('timeformat'))}}</td>
                    <td>{{\App\CentralLogics\Helpers::format_currency($at['current_balance'])}}</td>
                    <td>{{\App\CentralLogics\Helpers::format_currency($at['amount'])}}</td>
                    <td>{{translate($at['ref'])}}</td>
                    <td>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@if(count($account_transaction) !== 0)
<hr>
@endif
<div class="page-area">
    {!! $account_transaction->links() !!}
</div>
@if(count($account_transaction) === 0)
<div class="empty--data">
    <img src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="public">
    <h5>
        {{translate('No data found')}}
    </h5>
</div>
@endif
