@foreach($items as $key=>$item)
<tr>
    <td>{{$key+1}}</td>
    <td>
        <a class="media align-items-center" href="{{route('admin.item.view',[$item['id'],'module_id'=>$item['module_id']])}}">
            <img class="avatar avatar-lg mr-3 onerror-image"
            src="{{ $item['image_full_url'] ?? asset('public/assets/admin/img/160x160/img2.jpg') }}"

            data-onerror-image="{{asset('public/assets/admin/img/160x160/img2.jpg')}}" alt="{{$item->name}} image">
            <div class="media-body">
                <h5 class="text-hover-primary mb-0 max-width-200px word-break line--limit-2">{{$item['name']}}</h5>
            </div>
        </a>
    </td>
    <td>
        @if($item->store)
        {{Str::limit($item->store->name,25,'...')}}
        @else
        {{translate('messages.Store deleted')}}
        @endif
    </td>
    <td>
        @if($item->store)
        {{$item->store->zone->name}}
        @else
        {{translate('No data found')}}
        @endif
    </td>
    <td>
        {{ max((int) $item->stock, 0) }}
    </td>

    <td>
        <a class="btn action-btn action-btn--edit update-quantity" href="javascript:" title="{{translate('messages.Edit quantity')}}" data-id="{{ $item->id }}" data-toggle="modal" data-target="#update-quantity"><i class="tio-edit"></i>
        </a>
    </td>
</tr>
@endforeach
