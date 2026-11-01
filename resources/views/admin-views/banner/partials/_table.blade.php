@php($banner_type_labels = [
    'store_wise' => translate('messages.store_wise'),
    'item_wise' => translate('messages.item_wise'),
    'default' => translate('Default'),
])
@foreach($banners as $banner)
    <tr>
        <td>
            <span class="media align-items-center">
                <img class="img--ratio-3 w-auto h--50px rounded mr-2 onerror-image" src="{{ $banner['image_full_url'] }}"
                        data-onerror-image="{{asset('/public/assets/admin/img/900x400/img1.jpg')}}" alt="{{$banner->title}}">
                <div class="media-body max-w-200px">
                    <h5 title="{{ $banner['title'] }}" class="text-hover-primary mb-0">{{Str::limit($banner['title'], 25, '...')}}</h5>
                    <span class="d-block fs-12 text-muted">ID:{{$banner->id}}</span>
                </div>
            </span>
        </td>
        <td>
            <span class="d-block text-title">{{ $banner_type_labels[$banner['type']] ?? $banner['type'] }}</span>
            @if($banner['type'] === 'store_wise' && $banner->store)
                <span class="d-block fs-12 text-muted" title="{{ $banner->store->name }}">{{ Str::limit($banner->store->name, 22, '...') }}</span>
            @elseif($banner['type'] === 'default' && $banner->default_link)
                <span class="d-block fs-12 text-muted" title="{{ $banner->default_link }}">{{ Str::limit($banner->default_link, 28, '...') }}</span>
            @endif
        </td>
        <td>
            {{ $banner->zone ? $banner->zone->name : translate('messages.Zone deleted') }}
        </td>
        <td data-order="{{ $banner->created_at }}">
            <span class="table-when">
                <span class="table-when__day">{{\App\CentralLogics\Helpers::date_format($banner->created_at)}}</span>
                <span class="table-when__ago" title="{{\App\CentralLogics\Helpers::time_date_format($banner->created_at)}}">
                    {{ $banner->created_at?->diffForHumans() }}
                </span>
            </span>
        </td>
        <td class="text-center">
            <div class="status-toggle">
                <label class="toggle-switch toggle-switch-sm" for="featuredCheckbox{{$banner->id}}">
                    <input type="checkbox" data-url="{{route('admin.banner.featured',[$banner['id'],$banner->featured?0:1])}}" class="toggle-switch-input redirect-url" id="featuredCheckbox{{$banner->id}}" {{$banner->featured?'checked':''}}>
                    <span class="toggle-switch-label">
                        <span class="toggle-switch-indicator"></span>
                    </span>
                </label>
            </div>
        </td>
        <td class="text-center">
            <div class="status-toggle" data-status="{{$banner->status?1:0}}">
                <label class="toggle-switch toggle-switch-sm" for="statusCheckbox{{$banner->id}}">
                    <input type="checkbox" data-url="{{route('admin.banner.status',[$banner['id'],$banner->status?0:1])}}" class="toggle-switch-input redirect-url" id="statusCheckbox{{$banner->id}}" {{$banner->status?'checked':''}}>
                    <span class="toggle-switch-label">
                        <span class="toggle-switch-indicator"></span>
                    </span>
                </label>
                <span class="status-toggle__text" aria-live="polite">
                    {{$banner->status ? translate('messages.Active') : translate('messages.Inactive')}}
                </span>
            </div>
        </td>
        <td>
            <div class="btn--container justify-content-center">
                <a class="btn action-btn action-btn--edit" href="{{route('admin.banner.edit',[$banner['id']])}}" title="{{translate('Edit banner')}}"><i class="tio-edit"></i>
                </a>
                <a class="btn action-btn action-btn--delete form-alert" href="javascript:" data-id="banner-{{$banner['id']}}" data-message="{{ translate('Want to delete this banner?') }}" title="{{translate('Delete banner')}}"><i class="tio-delete-outlined"></i>
                </a>
                <form action="{{route('admin.banner.delete',[$banner['id']])}}"
                            method="post" id="banner-{{$banner['id']}}">
                        @csrf @method('delete')
                </form>
            </div>
        </td>
    </tr>
@endforeach
