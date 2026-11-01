@foreach($items as $key=>$item)
        <tr>
            <td>{{$key+1}}</td>
            <td>
                <a class="media align-items-center" href="{{route('vendor.item.view',[$item['id']])}}">
                    <img class="avatar avatar-lg mr-3 onerror-image" src="{{ $item['image_full_url']}}"
                         data-onerror-image="{{asset('public/assets/admin/img/160x160/img2.jpg')}}" alt="{{$item->name}} image">
                    <div class="media-body">
                        <h5 class="text-hover-primary mb-0">{{Str::limit($item['name'],20,'...')}}</h5>
                    </div>
                </a>
            </td>
            <td>
                {{Str::limit($item->category?$item->category->name:translate('messages.Category deleted'),20,'...')}}
            </td>
            <td>
                <div class="mw--85px">
                    {{\App\CentralLogics\Helpers::format_currency($item['price'])}}
                </div>
            </td>
            <td>
                <div class="d-flex">
                    <div class="mx-auto">
                        <label class="toggle-switch toggle-switch-sm mr-2"  data-toggle="tooltip" data-placement="top" title="{{ translate('messages.Recommend to customers') }}" for="recCheckbox{{$item->id}}">
                            <input type="checkbox" data-url="{{route('vendor.item.recommended',[$item['id'],$item->recommended?0:1])}}" class="toggle-switch-input redirect-url" id="recCheckbox{{$item->id}}" {{$item->recommended?'checked':''}}>
                            <span class="toggle-switch-label">
                                                <span class="toggle-switch-indicator"></span>
                                            </span>
                        </label>
                    </div>
                </div>
            </td>
            <td>
                <label class="toggle-switch toggle-switch-sm" for="stocksCheckbox{{$item->id}}">
                    <input type="checkbox" data-url="{{route('vendor.item.status',[$item['id'],$item->status?0:1])}}" class="toggle-switch-input redirect-url" id="stocksCheckbox{{$item->id}}" {{$item->status?'checked':''}}>
                    <span class="toggle-switch-label mx-auto">
                                        <span class="toggle-switch-indicator"></span>
                                    </span>
                </label>
            </td>
            <td>
                <div class="btn--container justify-content-center">
                    <a class="btn btn-sm action-btn action-btn--edit"
                       href="{{route('vendor.item.edit',[$item['id']])}}" title="{{translate('Edit item')}}"><i class="tio-edit"></i>
                    </a>
                    <a class="btn btn-sm action-btn action-btn--delete form-alert" href="javascript:"
                       data-id="food-{{$item['id']}}" data-message="{{ translate('Want to delete this item?') }}" title="{{translate('messages.Delete item')}}"><i class="tio-delete-outlined"></i>
                    </a>
                </div>
                <form action="{{route('vendor.item.delete',[$item['id']])}}"
                      method="post" id="food-{{$item['id']}}">
                    @csrf @method('delete')
                </form>
            </td>
        </tr>
    @endforeach
