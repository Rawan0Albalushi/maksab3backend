@foreach($withdraw_req as $k=>$wr)
<tr>
    <td scope="row">{{$k+1}}</td>
    <td>{{$wr['amount']}}</td>
    <td>
        @if($wr->vendor)
        <a class="deco-none"
            href="{{route('admin.store.view',[$wr->vendor['id'],'module_id'=>$wr->vendor->stores[0]->module_id])}}">{{ Str::limit($wr->vendor->stores[0]->name, 20, '...') }}</a>
        @else
        {{translate('messages.Store deleted') }}
        @endif
    </td>
    <td>{{date('Y-m-d '.config('timeformat'),strtotime($wr->created_at))}}</td>
    <td>
        @if($wr->approved==0)
            <label class="badge badge-primary">{{ translate('Pending') }}</label>
        @elseif($wr->approved==1)
            <label class="badge badge-success">{{ translate('Approved') }}</label>
        @else
            <label class="badge badge-danger">{{ translate('Denied') }}</label>
        @endif
    </td>
    <td>
        @if($wr->vendor)
        <a href="{{route('admin.transactions.store.withdraw_view',[$wr['id'],$wr->vendor['id']])}}"
            class="btn action-btn action-btn--view"><i class="tio-visible-outlined"></i>
        </a>
        @else
        {{translate('messages.Store deleted') }}
        @endif

    </td>
</tr>
@endforeach
