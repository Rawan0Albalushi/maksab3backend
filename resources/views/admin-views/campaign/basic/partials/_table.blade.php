@foreach($campaigns as $key=>$campaign)
<tr>
    <td>{{$key+1}}</td>
    <td>
        <a href="{{route('admin.campaign.view',['basic',$campaign->id])}}" class="d-block text-body">{{Str::limit($campaign['title'],25, '...')}}</a>
    </td>
    <td>{{Str::limit($campaign->module->module_name, 15, '...')}}</td>
    <td>
        <span class="bg-gradient-light text-dark">{{$campaign->start_date?$campaign->start_date->format('d/M/Y'). ' - ' .$campaign->end_date->format('d/M/Y'): 'N/A'}}</span>
    </td>
    <td>
        <span class="bg-gradient-light text-dark text-uppercase">{{$campaign->start_time?date(config('timeformat'),strtotime($campaign->start_time)). ' - ' .date(config('timeformat'),strtotime($campaign->end_time)): 'N/A'}}</span>
    </td>
    <td>
        <label class="toggle-switch toggle-switch-sm" for="stocksCheckbox{{$campaign->id}}">
            <input type="checkbox" data-url="{{route('admin.campaign.status',['basic',$campaign['id'],$campaign->status?0:1])}}" class="toggle-switch-input redirect-url" id="stocksCheckbox{{$campaign->id}}" {{$campaign->status?'checked':''}}>
            <span class="toggle-switch-label">
                <span class="toggle-switch-indicator"></span>
            </span>
        </label>
    </td>
    <td>
        <div class="btn--container justify-content-center">
            <a class="btn action-btn action-btn--edit"
                href="{{route('admin.campaign.edit',['basic',$campaign['id']])}}" title="{{translate('messages.Edit campaign')}}"><i class="tio-edit"></i>
            </a>
            <a class="btn action-btn action-btn--delete form-alert" href="javascript:" data-id="campaign-{{$campaign['id']}}" data-message="{{translate('Want to delete this item?')}}"
               title="{{translate('messages.Delete campaign')}}"><i class="tio-delete-outlined"></i>
            </a>
            <form action="{{route('admin.campaign.delete',[$campaign['id']])}}"
                            method="post" id="campaign-{{$campaign['id']}}">
                @csrf @method('delete')
            </form>
        </div>
    </td>
</tr>
@endforeach
