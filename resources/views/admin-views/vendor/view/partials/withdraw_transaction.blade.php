<div>
    <div class="table-responsive">
        <table id="datatable" class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
            <thead class="thead-light">
                <tr>
                    <th class="border-0">{{translate('messages.SL')}}</th>
                    <th class="border-0">{{translate('Created at')}}</th>
                    <th class="border-0">{{translate('Amount')}}</th>
                    <th class="border-0">{{translate('Status')}}</th>
                    <th class="border-0">{{translate('messages.Action')}}</th>
                </tr>
            </thead>
            <tbody>
            @php($withdraw_transaction = $transactions)
            @foreach($withdraw_transaction as $k=>$wt)
                <tr>
                    <td scope="row">{{$k+$withdraw_transaction->firstItem()}}</td>
                    <td>{{date('Y-m-d '.config('timeformat'), strtotime($wt->created_at))}}</td>
                    <td>{{\App\CentralLogics\Helpers::format_currency($wt->amount)}}</td>
                    <td>
                        @if($wt->approved==0)
                            <label class="badge badge-primary">{{ translate('Pending') }}</label>
                        @elseif($wt->approved==1)
                            <label class="badge badge-success">{{ translate('Approved') }}</label>
                        @else
                            <label class="badge badge-danger">{{ translate('Denied') }}</label>
                        @endif
                    </td>
                    <td>
                        <a href="{{route('admin.transactions.store.withdraw_view',[$wt['id'],$store->vendor['id']])}}"
                            class="btn action-btn action-btn--view"><i class="tio-visible-outlined"></i>
                        </a>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@if(count($withdraw_transaction) !== 0)
<hr>
@endif
<div class="page-area">
    {!! $withdraw_transaction->links() !!}
</div>
@if(count($withdraw_transaction) === 0)
<div class="empty--data">
    <img src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="public">
    <h5>
        {{translate('No data found')}}
    </h5>
</div>
@endif
