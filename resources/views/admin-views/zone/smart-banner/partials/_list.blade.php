<div class="table-responsive datatable-custom">
    <table id="columnSearchDatatable"
           class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
        <thead class="thead-light">
            <tr>
                <th class="border-0 fs-14">{{ translate('messages.SL') }}</th>
                <th class="border-0 fs-14">{{ translate('Banner information') }}</th>
                <th class="border-0 fs-14">
                    <div class="min-w-160px">{{ translate('messages.Duration') }}</div>
                </th>
                <th class="border-0 fs-14">{{ translate('messages.Module') }}</th>
                <th class="border-0 fs-14">{{ translate('messages.position') }}</th>
                <th class="border-0 fs-14">{{ translate('messages.Status') }}</th>
                <th class="border-0 fs-14 text-center">{{ translate('messages.Action') }}</th>
            </tr>
        </thead>
        <tbody id="set-rows">
            @foreach($banners as $key => $banner)
                <tr>
                    <td class="pl-4">{{ $key + $banners->firstItem() }}</td>
                    <td>
                        <div class="d-flex align-items-center gap-2 max-w-320px">
                            <img src="{{ $banner->image_full_url ?? asset('public/assets/admin/svg/illustrations/sorry.svg') }}"
                                 alt="banner" class="rounded" style="width: 56px; height: 56px; object-fit: cover;">
                            <div class="min-w-0">
                                <span class="d-block text-title fs-14 text-truncate" style="max-width: 240px;">{{ $banner->title }}</span>
                                <span class="d-block fs-12 text-muted text-truncate" style="max-width: 240px;">{{ $banner->subtitle }}</span>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="d-block fs-12">
                            {{ translate('messages.Date') }}:
                            @if($banner->active_days === 'everyday')
                                {{ translate('messages.everyday') }}
                            @else
                                {{ \App\CentralLogics\Helpers::date_format($banner->start_date) }} - {{ \App\CentralLogics\Helpers::date_format($banner->end_date) }}
                            @endif
                        </span>
                        <span class="d-block fs-12">
                            {{ translate('messages.Time') }}:
                            @if($banner->start_time)
                                {{ \App\CentralLogics\Helpers::time_format($banner->start_time) }} -
                                {{ $banner->end_time ? \App\CentralLogics\Helpers::time_format($banner->end_time) : translate('messages.Until you turn off') }}
                            @else
                                {{ translate('messages.All day') }}
                            @endif
                        </span>
                    </td>
                    <td>{{ $banner->module ? $banner->module->module_name : translate('All modules') }}</td>
                    <td>{{ translate(ucfirst($banner->position)) }} {{ translate('messages.position') }}</td>
                    <td>
                        <label class="toggle-switch toggle-switch-sm" for="status-{{ $banner['id'] }}">
                            <input type="checkbox" class="toggle-switch-input dynamic-checkbox"
                                   data-id="status-{{ $banner['id'] }}"
                                   data-type="status"
                                   data-image-on='{{ asset('public/assets/admin/img/status-ons.png') }}'
                                   data-image-off="{{ asset('public/assets/admin/img/status-ons.png') }}"
                                   data-title-on="{{ translate('messages.Want to turn on smart banner') }}"
                                   data-title-off="{{ translate('messages.Want to turn off smart banner') }}"
                                   data-text-on="<p>{{ translate('messages.This banner will become visible to customers.') }}</p>"
                                   data-text-off="<p>{{ translate('messages.This banner will be hidden from customers.') }}</p>"
                                   id="status-{{ $banner['id'] }}" {{ $banner->status ? 'checked' : '' }}>
                            <span class="toggle-switch-label">
                                <span class="toggle-switch-indicator"></span>
                            </span>
                        </label>
                        <form action="{{ route('admin.business-settings.zone.smart-banner.status', [$banner['id'], $banner->status ? 0 : 1]) }}"
                              method="get" id="status-{{ $banner['id'] }}_form">
                        </form>
                    </td>
                    <td>
                        <div class="btn--container justify-content-center">
                            <a class="btn action-btn action-btn--edit offcanvas-trigger smart-banner-edit-trigger"
                               href="javascript:"
                               data-id="{{ $banner['id'] }}"
                               data-url="{{ route('admin.business-settings.zone.smart-banner.edit', [$banner['id']]) }}"
                               data-target="#smartBannerForm_offcanvas"
                               title="{{ translate('Edit') }}">
                                <i class="tio-edit"></i>
                            </a>
                            <a class="btn action-btn action-btn--view offcanvas-trigger smart-banner-view-trigger"
                               href="javascript:"
                               data-id="{{ $banner['id'] }}"
                               data-url="{{ route('admin.business-settings.zone.smart-banner.view', [$banner['id']]) }}"
                               data-target="#smartBannerView_offcanvas"
                               title="{{ translate('messages.View') }}">
                                <i class="tio-visible-outlined"></i>
                            </a>
                            <a class="btn action-btn action-btn--delete form-alert"
                               href="javascript:"
                               data-id="smart-banner-{{ $banner['id'] }}"
                               data-message="{{ translate('Are you sure you want to delete this smart banner permanently?') }}"
                               title="{{ translate('messages.Delete') }}">
                                <i class="tio-delete-outlined"></i>
                            </a>
                            <form action="{{ route('admin.business-settings.zone.smart-banner.delete', [$banner['id']]) }}"
                                  method="post" id="smart-banner-{{ $banner['id'] }}">
                                @csrf @method('delete')
                            </form>
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@if (count($banners) !== 0)
    <hr>
@endif
<div class="page-area">
    {!! $banners->withQueryString()->links() !!}
</div>
@if (count($banners) === 0)
    <div class="empty--data">
        <img src="{{ asset('/public/assets/admin/svg/illustrations/sorry.svg') }}" alt="public">
        <h5>{{ translate('No data found') }}</h5>
    </div>
@endif
