@php
    $metrics = $metrics ?? [];
    $typeMeta = config('module.module_type_meta', []);
@endphp

@foreach($modules as $module)
    @php
        $row = ($metrics[$module['id']] ?? []) + ['stores' => 0, 'active_stores' => 0, 'vendors' => 0, 'items' => 0, 'active_items' => 0, 'zones' => 0];
        $storeLess = in_array($module['module_type'], ['parcel', 'ride-share']);
        $catalogueLess = $storeLess || $module['module_type'] === 'rental';
        $meta = $typeMeta[$module['module_type']] ?? [];
        $badge = $meta['tone'] ?? 'secondary';
        $typeIcon = $meta['icon'] ?? 'tio-layers-outlined';
        $editUrl = route('admin.business-settings.module.edit', [$module['id']]);
        $notApplicable = translate('messages.Not applicable for this module type');
    @endphp
    <tr>
        <td>
            <a class="mds-module" href="{{$editUrl}}">
                <img class="mds-module__icon onerror-image"
                     src="{{$module['icon_full_url']}}"
                     data-onerror-image="{{asset('public/assets/admin/img/100x100/2.jpg')}}"
                     alt="{{$module['module_name']}}">
                <span class="mds-module__body">
                    <span class="mds-module__head">
                        <span class="mds-module__name" title="{{$module['module_name']}}">{{$module['module_name']}}</span>
                        <span class="badge badge-soft-{{$badge}} text-capitalize mds-type-chip">
                            <i class="{{$typeIcon}}"></i> {{translate($module['module_type'])}}
                        </span>
                    </span>
                    @if($module['short_description'])
                        <span class="mds-module__desc" title="{{$module['short_description']}}">{{$module['short_description']}}</span>
                    @endif
                </span>
            </a>
        </td>
        <td>
            @if($module['all_zone_service'])
                <span class="badge badge-soft-success">{{translate('All zones')}}</span>
            @elseif($row['zones'])
                <span class="mds-metric__value" title="{{translate('messages.zones') . ': ' . $row['zones']}}">{{$row['zones']}}</span>
            @else
                <span class="mds-metric__value mds-metric__value--zero" title="{{translate('messages.Not added to any zone')}}">0</span>
            @endif
        </td>
        <td>
            @if($storeLess)
                <span class="mds-metric__na" title="{{$notApplicable}}">{{translate('messages.N/A')}}</span>
            @else
                <span class="mds-metric">
                    <span class="mds-metric__line">
                        <span class="mds-metric__value">{{number_format($row['stores'])}}</span>
                        <span class="mds-metric__unit">{{translate('messages.Stores')}}</span>
                        <span class="mds-metric__dot"></span>
                        <span class="mds-metric__value">{{number_format($row['vendors'])}}</span>
                        <span class="mds-metric__unit">{{translate('messages.vendors')}}</span>
                    </span>
                    <span class="mds-metric__sub" title="{{translate('messages.Active stores')}}">{{translate('Active')}}: {{$row['active_stores']}}</span>
                </span>
            @endif
        </td>
        <td>
            @if($catalogueLess)
                <span class="mds-metric__na" title="{{$notApplicable}}">{{translate('messages.N/A')}}</span>
            @elseif($row['items'])
                <span class="mds-metric">
                    <span class="mds-metric__value">{{number_format($row['items'])}}</span>
                    <span class="mds-metric__sub" title="{{translate('Active items')}}">{{translate('Active')}}: {{$row['active_items']}}</span>
                </span>
            @else
                <span class="mds-metric__value mds-metric__value--zero" title="{{translate('messages.No item has been added to this module yet')}}">0</span>
            @endif
        </td>
        <td>
            <div class="mds-state">
                <div class="status-toggle" data-status="{{$module->status?1:0}}">
                    <label class="toggle-switch toggle-switch-sm" for="status-{{$module['id']}}">
                        <input type="checkbox" class="toggle-switch-input dynamic-checkbox"
                               data-id="status-{{$module['id']}}"
                               data-type="status"
                               data-image-on='{{asset('/public/assets/admin/img/modal')}}/module-on.png'
                               data-image-off="{{asset('/public/assets/admin/img/modal')}}/module-off.png"
                               data-title-on="{{translate('Want to activate this')}} <strong>{{translate('Business module?')}}</strong>"
                               data-title-off="{{translate('Want to deactivate this')}} <strong>{{translate('Business module?')}}</strong>"
                               data-text-on="<p>{{translate('If you activate this business module, all its features and functionalities will be available and accessible to all users.')}}</p>"
                               data-text-off="<p>{{translate('If you deactivate this business module, all its features and functionalities will be disabled and hidden from users.')}}</p>"
                               aria-label="{{translate('messages.Status')}}"
                               id="status-{{$module['id']}}" {{$module->status?'checked':''}}>
                        <span class="toggle-switch-label">
                            <span class="toggle-switch-indicator"></span>
                        </span>
                    </label>
                    <span class="status-toggle__text" aria-live="polite">
                        {{$module->status ? translate('messages.Active') : translate('messages.Inactive')}}
                    </span>
                </div>
                @if($module->created_at)
                    <span class="mds-state__added">{{translate('Added')}}: {{\App\CentralLogics\Helpers::date_format($module->created_at)}}</span>
                @endif
            </div>
            <form action="{{route('admin.business-settings.module.status',[$module['id'],$module->status?0:1])}}" method="get" id="status-{{$module['id']}}_form">
            </form>
        </td>
        <td>
            <div class="btn--container justify-content-center">
                <a class="btn action-btn action-btn--edit"
                   href="{{$editUrl}}"
                   title="{{translate('messages.edit Business Module')}}"><i class="tio-edit"></i>
                </a>
            </div>
        </td>
    </tr>
@endforeach
