@php($item_count = $usageStats[$unit['id']]['items'] ?? 0)
@php($store_count = $usageStats[$unit['id']]['stores'] ?? 0)
@php($locales = $translatedLocales[$unit['id']] ?? [])
@php($modules = $moduleUsage[$unit['id']] ?? [])
<tr>
    <td>
        <div class="media align-items-center max-w-250">
            <div class="avatar avatar-sm avatar-circle avatar-soft-primary mr-2">
                <span class="avatar-initials">{{ strtoupper(mb_substr($unit['unit'] ?? '-', 0, 1)) }}</span>
            </div>
            <div class="media-body cell--truncate">
                <a class="font-weight-medium d-block" href="{{route('admin.unit.edit',[$unit['id']])}}" title="{{ $unit['unit'] }}">
                    {{ $unit['unit'] }}
                </a>
                <small class="d-block">#{{ $unit['id'] }}</small>
            </div>
        </div>
    </td>
    <td>
        @if(count($locales))
            <span class="cell-chips">
                @foreach($locales as $locale)
                    <span class="cell-chip" title="{{ \App\CentralLogics\Helpers::get_language_name($locale) }}">{{ strtoupper($locale) }}</span>
                @endforeach
            </span>
        @else
            <span class="text-muted font-size-sm">{{translate('messages.Default only')}}</span>
        @endif
    </td>
    <td class="col--numeric" data-order="{{ $item_count }}">
        <span class="badge badge-soft-{{ $item_count ? 'success' : 'secondary' }}"
              title="{{ $item_count ? translate('messages.Items') . ': ' . $item_count : translate('messages.Not used by any item yet') }}">
            {{ $item_count }}
        </span>
    </td>
    <td class="col--numeric" data-order="{{ $store_count }}">
        <span class="badge badge-soft-{{ $store_count ? 'info' : 'secondary' }}"
              title="{{ $store_count ? translate('messages.Stores') . ': ' . $store_count : translate('messages.No store sells items in this unit yet') }}">
            {{ $store_count }}
        </span>
    </td>
    <td>
        @if(count($modules))
            <span class="cell-chips">
                @foreach($modules as $module_name)
                    <span class="cell-chip">{{ $module_name }}</span>
                @endforeach
            </span>
        @else
            <span class="text-muted font-size-sm">{{translate('messages.Not in use')}}</span>
        @endif
    </td>
    <td data-order="{{ $unit['created_at'] }}">
        <span title="{{ \App\CentralLogics\Helpers::time_date_format($unit['created_at']) }}">
            {{ \App\CentralLogics\Helpers::date_format($unit['created_at']) }}
        </span>
    </td>
    <td data-order="{{ $unit['updated_at'] }}">
        <span title="{{ \App\CentralLogics\Helpers::time_date_format($unit['updated_at']) }}">
            {{ \App\CentralLogics\Helpers::date_format($unit['updated_at']) }}
        </span>
    </td>
    <td>
        <div class="btn--container justify-content-center">
            <a class="btn action-btn action-btn--edit" href="{{route('admin.unit.edit',[$unit['id']])}}" title="{{translate('Edit')}}"><i class="tio-edit"></i>
            </a>
            <a class="btn action-btn action-btn--delete form-alert" href="javascript:" data-id="unit-{{$unit['id']}}" data-message="{{ $item_count ? translate('messages.This unit is used by items. Deleting it will remove it from them. Continue?') . ' ' . translate('messages.Items') . ': ' . $item_count : translate('Want to delete this unit?') }}" title="{{translate('messages.Delete')}}"><i class="tio-delete-outlined"></i>
            </a>
            <form action="{{route('admin.unit.destroy',[$unit['id']])}}"
                    method="post" id="unit-{{$unit['id']}}"
                    data-ajax-form data-ajax-remove="closest:tr"
                    data-ajax-refresh="[data-ajax-region]">
                @csrf @method('delete')
            </form>
        </div>
    </td>
</tr>
