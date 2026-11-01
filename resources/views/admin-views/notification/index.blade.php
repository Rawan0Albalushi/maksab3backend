@extends('layouts.admin.app')

@section('title', translate('Push notification'))

@php($isProviderContext = config('module.current_module_type') === 'service')
@php($store_label = $isProviderContext ? translate('messages.Provider') : translate('messages.Store'))
@php($target_labels = [
    'customer' => translate('messages.Customer'),
    'deliveryman' => translate('Deliveryman'),
    'rider' => translate('Rider'),
    'store' => $store_label,
])
@php($target_icons = [
    'customer' => 'tio-user',
    'deliveryman' => 'tio-bike',
    'rider' => 'tio-car',
    'store' => 'tio-shop',
])
@php($targets = array_values(array_filter([
    [
        'value' => 'customer',
        'desc' => translate('Everyone who has the customer app installed.'),
    ],
    $isProviderContext ? null : [
        'value' => 'deliveryman',
        'desc' => translate('Deliverymen who are approved to deliver.'),
    ],
    addon_published_status('RideShare') && config('module.current_module_type') === 'ride-share' ? [
        'value' => 'rider',
        'desc' => translate('Riders who take trips through the app.'),
    ] : null,
    [
        'value' => 'store',
        'desc' => $isProviderContext ? translate('Providers and the staff on their account.') : translate('Store owners and the staff on their account.'),
    ],
])))
@php($app_name = \App\CentralLogics\Helpers::get_business_settings('business_name'))
@php($app_icon = \App\CentralLogics\Helpers::iconFullUrl())

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/third-party-setup.css') }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/view-pages/notification-form.css') }}">
@endpush

@section('content')
    <div class="content container-fluid tps ntf">
        <div class="page-header mb-20 pb-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h1 class="page-header-title">
                    <span class="page-header-icon">
                        <img src="{{ asset('public/assets/admin/img/notification.png') }}" class="w--26" alt="">
                    </span>
                    <span>
                        {{ translate('Push notification') }}
                    </span>
                </h1>
                <p class="page-header-desc">{{ translate('Send a message straight to the phones of the users you choose in a zone.') }}</p>
            </div>
            <button type="button" class="tps-help" data-toggle="modal" data-target="#notification-how-it-works">
                <i class="tio-help-outlined"></i>
                <span>{{ translate('How it works') }}</span>
            </button>
        </div>

        <div class="tps-note tps-note--warn mb-20">
            <i class="tio-info-outined"></i>
            <p>
                {{ translate('Set up push notification messages for customers. Notifications only work once this page is set up') }}: <a target="_blank" href="{{ route('admin.business-settings.fcm-config') }}" class="font-semibold text-info">{{ translate('messages.Firebase Configuration') }}</a>
            </p>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-xl-8">
                <form action="{{ route('admin.notification.store') }}" method="post" enctype="multipart/form-data" id="notification">
                    @csrf
                    <div class="tps-card">
                        <div class="tps-card__body">

                            <div class="tps-group">
                                <h3 class="tps-group__label">{{ translate('Audience') }}</h3>

                                <div class="tps-field">
                                    <label class="tps-field__label">
                                        {{ translate('messages.Targeted user') }} <span class="tps-req">*</span>
                                    </label>
                                    <div class="ntf-targets">
                                        @foreach ($targets as $key => $target_option)
                                            <label class="tps-choice">
                                                <input type="radio" name="tergat" value="{{ $target_option['value'] }}"
                                                    data-label="{{ $target_labels[$target_option['value']] }}"
                                                    {{ $key === 0 ? 'checked' : '' }} required>
                                                <span class="tps-choice__box">
                                                    <span class="ntf-target__icon"><i class="{{ $target_icons[$target_option['value']] }}"></i></span>
                                                    <span>
                                                        <span class="tps-choice__title">{{ $target_labels[$target_option['value']] }}</span>
                                                        <span class="tps-choice__desc">{{ $target_option['desc'] }}</span>
                                                    </span>
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="tps-field mt-3">
                                    <label class="tps-field__label" for="zone">
                                        {{ translate('messages.zones') }} <span class="tps-req">*</span>
                                    </label>
                                    <select name="zone" id="zone" class="form-control js-select2-custom"
                                        data-search-placeholder="{{ translate('messages.Search zone') }}">
                                        <option value="all">{{ translate('All') }}</option>
                                        @foreach ($zones as $zone)
                                            <option value="{{ $zone['id'] }}">{{ $zone['name'] }}</option>
                                        @endforeach
                                    </select>
                                    <small class="tps-field__hint">{{ translate('Only users inside the zone get the message. Choose all to reach every zone at once.') }}</small>
                                </div>
                            </div>

                            <div class="tps-group">
                                <h3 class="tps-group__label">{{ translate('messages.message') }}</h3>

                                <div class="tps-field">
                                    <label class="tps-field__label" for="notification_title">
                                        {{ translate('messages.Title') }} <span class="tps-req">*</span>
                                    </label>
                                    <textarea name="notification_title" id="notification_title" class="form-control" maxlength="100" rows="1"
                                        placeholder="{{ translate('Type title') }}" required></textarea>
                                    <div class="ntf-field-foot">
                                        <small class="tps-field__hint">{{ translate('The bold line on the lock screen. Short titles survive every screen size.') }}</small>
                                        <span class="ntf-counter text-counting">0/100</span>
                                    </div>
                                </div>

                                <div class="tps-field mt-3">
                                    <label class="tps-field__label" for="description">
                                        {{ translate('messages.Description') }} <span class="tps-req">*</span>
                                    </label>
                                    <textarea name="description" id="description" class="form-control" maxlength="200" rows="3"
                                        placeholder="{{ translate('messages.Type about the description') }}" required></textarea>
                                    <div class="ntf-field-foot">
                                        <small class="tps-field__hint">{{ translate('The line under the title. Phones trim anything past two lines until the notification is opened.') }}</small>
                                        <span class="ntf-counter text-counting">0/200</span>
                                    </div>
                                </div>
                            </div>

                            <div class="tps-group">
                                <h3 class="tps-group__label">{{ translate('messages.Image') }}</h3>

                                <div class="ntf-drop">
                                    <div class="ntf-drop__media" id="image-input-wrap">
                                        @include('admin-views.partials._image-uploader', [
                                            'id' => 'image-input',
                                            'name' => 'image',
                                            'ratio' => '2:1',
                                            'isRequired' => false,
                                            'existingImage' => null,
                                            'imageExtension' => IMAGE_EXTENSION,
                                            'imageFormat' => IMAGE_FORMAT,
                                            'maxSize' => MAX_FILE_SIZE,
                                            'textPosition' => 'bottom',
                                        ])
                                    </div>
                                    <p class="tps-field__hint m-0 text-center">
                                        {{ translate('Optional. The phone shows it only once the notification is pulled open, so the title and message still have to carry the message on their own.') }}
                                    </p>
                                </div>
                            </div>

                        </div>

                        <div class="tps-card__foot">
                            <span class="tps-foot-note">{{ translate('Sending is instant and cannot be taken back.') }}</span>
                            <button type="reset" id="reset_btn" class="btn btn--reset">
                                <i class="tio-refresh"></i> {{ translate('messages.Reset') }}
                            </button>
                            <button type="submit" id="submit" class="btn btn--primary">
                                <i class="tio-send"></i> {{ translate('messages.Save & Send') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="col-xl-4">
                <div class="ntf-aside">
                    <div class="tps-card">
                        <div class="tps-card__head">
                            <span class="tps-card__brand"><i class="tio-notifications-on-outlined"></i></span>
                            <div class="tps-card__titles">
                                <h2 class="tps-card__title">{{ translate('Lock screen preview') }}</h2>
                                <p class="tps-card__subtitle">{{ translate('How the message lands on a phone.') }}</p>
                            </div>
                        </div>
                        <div class="tps-card__body">
                            <div class="ntf-stage">
                                <div class="ntf-phone">
                                    <div class="ntf-phone__status">
                                        <span>{{ now()->format('g:i') }}</span>
                                        <span class="d-inline-flex align-items-center gap-1">
                                            <span class="ntf-phone__signal"><span></span><span></span><span></span></span>
                                            <span class="ntf-phone__battery"><span></span></span>
                                        </span>
                                    </div>
                                    <div class="ntf-phone__clock">
                                        <span class="ntf-phone__hour">{{ now()->format('g:i') }}</span>
                                        <span class="ntf-phone__date">{{ \App\CentralLogics\Helpers::date_format(now()) }}</span>
                                    </div>
                                    <div class="ntf-push" id="notification-preview">
                                        <div class="ntf-push__head">
                                            <span class="ntf-push__app">
                                                <img class="onerror-image" src="{{ $app_icon }}"
                                                    data-onerror-image="{{ asset('public/assets/admin/img/notification.png') }}" alt="">
                                            </span>
                                            <span class="ntf-push__name">{{ $app_name }}</span>
                                            <span class="ntf-push__ago">{{ translate('now') }}</span>
                                        </div>
                                        <p class="ntf-push__title" data-preview="title">{{ translate('Your title shows here') }}</p>
                                        <p class="ntf-push__body" data-preview="description">{{ translate('Your message shows here') }}</p>
                                        <div class="ntf-push__media">
                                            <img src="" data-preview="image" alt="">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <dl class="ntf-facts">
                                <div>
                                    <dt>{{ translate('messages.Targeted user') }}</dt>
                                    <dd data-preview-fact="target"></dd>
                                </div>
                                <div>
                                    <dt>{{ translate('messages.zones') }}</dt>
                                    <dd data-preview-fact="zone"></dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <div class="tps-note tps-note--info mt-3">
                        <i class="tio-info-outined"></i>
                        <p>{{ translate('A push only reaches someone who has the app installed and notifications switched on for it.') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header py-2 border-0">
                <div class="search--button-wrapper">
                    @include('partials._table-head', [
                        'title' => translate('Notification History'),
                        'subtitle' => translate('Every push sent from this module, newest first.'),
                        'count' => $notifications->total(),
                        'count_id' => 'itemCount',
                    ])

                    <div>
                        <select name="target" class="form-control custom-select max-w-200px min-w-100-mobile" id="filter_form"
                            data-placeholder="{{ translate('messages.Select target') }}">
                            <option value="all" {{ $target == 'all' ? 'selected' : '' }}>{{ translate('All') }}</option>
                            @foreach ($targets as $target_option)
                                <option value="{{ $target_option['value'] }}" {{ $target == $target_option['value'] ? 'selected' : '' }}>
                                    {{ $target_labels[$target_option['value']] }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <form class="search-form min--270" id="history_form">
                        <div class="input-group input--group">
                            <input id="history_search" type="search" name="search" class="form-control"
                                value="{{ request()?->search ?? null }}"
                                placeholder="{{ translate('messages.Search notification') }}"
                                aria-label="{{ translate('Search') }}">
                            <button type="submit" class="btn btn--secondary">
                                <i class="tio-search"></i>
                            </button>
                        </div>
                    </form>

                    <div class="hs-unfold mr-2">
                        <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle min-height-40"
                            href="javascript:;"
                            data-hs-unfold-options='{
                                "target": "#usersExportDropdown",
                                "type": "css-animation"
                            }'>
                            <i class="tio-download-to mr-1"></i> {{ translate('messages.Export') }}
                        </a>

                        <div id="usersExportDropdown"
                            class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">

                            <span class="dropdown-header">{{ translate('messages.Download options') }}</span>

                            <a id="export-excel" class="dropdown-item"
                                href="{{ route('admin.notification.export', ['type' => 'excel', request()->getQueryString()]) }}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                    src="{{ asset('public/assets/admin') }}/svg/components/excel.svg"
                                    alt="Excel">
                                Excel
                            </a>
                            <a id="export-csv" class="dropdown-item"
                                href="{{ route('admin.notification.export', ['type' => 'csv', request()->getQueryString()]) }}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                    src="{{ asset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg"
                                    alt="CSV">
                                CSV
                            </a>

                        </div>
                    </div>
                </div>
            </div>

            <div class="table-responsive datatable-custom">
                <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                    <thead class="thead-light">
                        <tr>
                            <th class="border-0">{{ translate('messages.Title') }}</th>
                            <th class="border-0">{{ translate('messages.Description') }}</th>
                            <th class="border-0">{{ translate('messages.target') }}</th>
                            <th class="border-0">{{ translate('messages.zones') }}</th>
                            <th class="border-0">{{ translate('Sent') }}</th>
                            <th class="text-center border-0">{{ translate('messages.Status') }}</th>
                            <th class="text-center border-0">{{ translate('messages.Action') }}</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($notifications as $notification)
                            @php($target_label = $target_labels[$notification['tergat']] ?? $notification['tergat'])
                            @php($zone_label = $notification->zone_id == null ? translate('All') : ($notification->zone ? $notification->zone->name : translate('messages.Zone deleted')))
                            @php($sent_at = $notification->created_at ? \Carbon\Carbon::parse($notification->created_at) : null)
                            <tr>
                                <td>
                                    <span class="table-rest-info">
                                        @if ($notification['image'] != null)
                                            <img class="img--60 rounded onerror-image" src="{{ $notification['image_full_url'] }}"
                                                data-onerror-image="{{ asset('public/assets/admin/img/160x160/img2.jpg') }}" alt="{{ $notification['title'] }}">
                                        @else
                                            <img class="img--60 rounded" src="{{ asset('public/assets/admin/img/160x160/img2.jpg') }}" alt="{{ translate('No image') }}">
                                        @endif
                                        <span class="info max-w-200px">
                                            <span class="d-block text--title line--limit-2" title="{{ $notification['title'] }}">{{ $notification['title'] }}</span>
                                            <span class="d-block font-light">ID:{{ $notification['id'] }}</span>
                                        </span>
                                    </span>
                                </td>
                                <td>
                                    <span class="max-w-280 line--limit-2 d-block" data-toggle="tooltip"
                                        data-title="{{ $notification['description'] }}">{{ $notification['description'] }}</span>
                                </td>
                                <td>
                                    <span class="ntf-target-cell">
                                        <i class="{{ $target_icons[$notification['tergat']] ?? 'tio-user' }}"></i>
                                        {{ $target_label }}
                                    </span>
                                </td>
                                <td>{{ $zone_label }}</td>
                                <td data-order="{{ $notification->created_at }}">
                                    @if ($sent_at)
                                        <span class="table-when">
                                            <span class="table-when__day">{{ \App\CentralLogics\Helpers::date_format($sent_at) }}</span>
                                            <span class="table-when__ago" title="{{ \App\CentralLogics\Helpers::time_date_format($sent_at) }}">
                                                {{ $sent_at->diffForHumans() }}
                                            </span>
                                        </span>
                                    @else
                                        <span class="text-muted font-size-sm">{{ translate('messages.N/A') }}</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <div class="status-toggle" data-status="{{ $notification->status ? 1 : 0 }}">
                                        <label class="toggle-switch toggle-switch-sm mb-0"
                                            for="stocksCheckbox{{ $notification->id }}">
                                            <input type="checkbox"
                                                data-url="{{ route('admin.notification.status', [$notification['id'], $notification->status ? 0 : 1]) }}"
                                                class="toggle-switch-input redirect-url"
                                                id="stocksCheckbox{{ $notification->id }}"
                                                {{ $notification->status ? 'checked' : '' }} hidden>
                                            <span class="toggle-switch-label">
                                                <span class="toggle-switch-indicator"></span>
                                            </span>
                                        </label>
                                        <span class="status-toggle__text" aria-live="polite">
                                            {{ $notification->status ? translate('messages.Active') : translate('messages.Inactive') }}
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <div class="btn--container justify-content-center">
                                        <button type="button" class="btn action-btn action-btn--view"
                                            data-toggle="modal" data-target="#notification-view-modal"
                                            title="{{ translate('messages.View notification') }}"
                                            data-title="{{ $notification['title'] }}"
                                            data-description="{{ $notification['description'] }}"
                                            data-image="{{ $notification->image ? $notification['image_full_url'] : '' }}"
                                            data-zone="{{ $zone_label }}"
                                            data-sent="{{ $sent_at ? \App\CentralLogics\Helpers::time_date_format($sent_at) : translate('messages.N/A') }}"
                                            data-audience="{{ $target_label }}"><i class="tio-visible-outlined"></i>
                                        </button>
                                        <button type="button"
                                            class="btn action-btn action-btn--edit offcanvas-trigger edit-btn"
                                            data-target="#notification-update-offcanvas"
                                            title="{{ translate('messages.Edit notification') }}"
                                            data-id="{{ $notification['id'] }}"
                                            data-title="{{ $notification['title'] }}"
                                            data-description="{{ $notification['description'] }}"
                                            data-raw-image="{{ $notification->image ? $notification['image_full_url'] : '' }}"
                                            data-zone-id="{{ $notification->zone_id }}"
                                            data-tergat="{{ $notification['tergat'] }}"><i class="tio-edit"></i>
                                        </button>
                                        <a class="btn action-btn action-btn--delete form-alert"
                                            href="javascript:" data-id="notification-{{ $notification['id'] }}"
                                            data-message="{{ translate('Want to delete this notification?') }}"
                                            title="{{ translate('messages.Delete notification') }}"><i
                                                class="tio-delete-outlined"></i>
                                        </a>
                                        <form action="{{ route('admin.notification.delete', [$notification['id']]) }}"
                                            method="post" id="notification-{{ $notification['id'] }}">
                                            @csrf @method('delete')
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if (count($notifications) !== 0)
                <hr>
            @endif
            <div class="page-area">
                {!! $notifications->links() !!}
            </div>
            @if (count($notifications) === 0)
                <div class="empty--data">
                    <img src="{{ asset('/public/assets/admin/svg/illustrations/sorry.svg') }}" alt="public">
                    <h5>
                        {{ translate('No data found') }}
                    </h5>
                </div>
            @endif
        </div>

    </div>

    <div id="notification-update-offcanvas" class="custom-offcanvas d-flex flex-column justify-content-between tps ntf">
        <form action="" method="post" enctype="multipart/form-data" id="update-notification-form">
            @csrf
            <div>
                <div class="custom-offcanvas-header bg--secondary d-flex justify-content-end align-items-center gap-3 px-3 py-3">
                    <div class="py-1 flex-grow-1">
                        <h3 class="mb-0">{{ translate('messages.Edit Send Notification') }}</h3>
                    </div>
                    <button type="submit"
                        class="btn btn--primary btn-outline-primary btn-sm px-3 d-flex gap-2 align-items-center offcanvas-close">
                        <i class="tio-redo"></i> {{ translate('Resend') }}
                    </button>
                    <button type="button"
                        class="btn-close w-25px h-25px border bg-white rounded-circle d-center text-dark flex-shrink-0 fz-15px p-0 offcanvas-close"
                        aria-label="{{ translate('messages.Close') }}">
                        &times;
                    </button>
                </div>
                <div class="custom-offcanvas-body custom-offcanvas-body-100 p-20">
                    <div class="mb-9">
                        <div class="tps-card mb-3">
                            <div class="tps-card__body">
                                <div class="tps-group">
                                    <h3 class="tps-group__label">{{ translate('messages.Image') }}</h3>
                                    <div class="ntf-drop">
                                        <div class="ntf-drop__media">
                                            @include('admin-views.partials._image-uploader', [
                                                'id' => 'image-input-u',
                                                'name' => 'image',
                                                'ratio' => '2:1',
                                                'isRequired' => false,
                                                'existingImage' => '',
                                                'imageExtension' => IMAGE_EXTENSION,
                                                'imageFormat' => IMAGE_FORMAT,
                                                'maxSize' => MAX_FILE_SIZE,
                                                'textPosition' => 'none',
                                            ])
                                        </div>
                                    </div>
                                </div>

                                <div class="tps-group">
                                    <h3 class="tps-group__label">{{ translate('messages.message') }}</h3>

                                    <div class="tps-field">
                                        <label class="tps-field__label" for="notification_title_u">
                                            {{ translate('messages.Title') }} <span class="tps-req">*</span>
                                        </label>
                                        <textarea name="notification_title" id="notification_title_u" class="form-control" maxlength="100" rows="1"
                                            placeholder="{{ translate('Type title') }}" required></textarea>
                                        <span class="ntf-counter text-counting">0/100</span>
                                    </div>

                                    <div class="tps-field mt-3">
                                        <label class="tps-field__label" for="description_u">
                                            {{ translate('messages.Description') }} <span class="tps-req">*</span>
                                        </label>
                                        <textarea name="description" id="description_u" class="form-control" maxlength="200" rows="3"
                                            placeholder="{{ translate('messages.Type about the description') }}" required></textarea>
                                        <span class="ntf-counter text-counting">0/200</span>
                                    </div>
                                </div>

                                <div class="tps-group">
                                    <h3 class="tps-group__label">{{ translate('Audience') }}</h3>

                                    <div class="tps-field">
                                        <label class="tps-field__label" for="zone_u">
                                            {{ translate('messages.zones') }} <span class="tps-req">*</span>
                                        </label>
                                        <select name="zone" id="zone_u" class="form-control custom-select js-select2-custom"
                                            data-search-placeholder="{{ translate('messages.Search zone') }}">
                                            <option value="all">{{ translate('All zone') }}</option>
                                            @foreach ($zones as $zone)
                                                <option value="{{ $zone['id'] }}">{{ $zone['name'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="tps-field mt-3">
                                        <label class="tps-field__label" for="tergat_u">
                                            {{ translate('messages.Targeted user') }} <span class="tps-req">*</span>
                                        </label>
                                        <select name="tergat" class="form-control custom-select" id="tergat_u"
                                            data-placeholder="{{ translate('messages.Select target') }}" required>
                                            @foreach ($targets as $target_option)
                                                <option value="{{ $target_option['value'] }}">{{ $target_labels[$target_option['value']] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="tps-note tps-note--warn">
                            <i class="tio-info-outined"></i>
                            <p>{{ translate('Saving sends the notification again to everyone the audience covers.') }}</p>
                        </div>
                    </div>
                </div>
                <div class="offcanvas-footer d-flex gap-3 justify-content-center align-items-center bg-white bottom-0 mt-auto p-3">
                    <button type="button" id="update_reset_btn" class="btn btn--reset w-100">
                        <i class="tio-refresh"></i> {{ translate('Reset') }}
                    </button>
                    <button type="submit" class="btn btn--primary w-100">
                        <i class="tio-redo"></i> {{ translate('Resend') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
    <div id="offcanvasOverlay" class="offcanvas-overlay"></div>

    <div class="modal fade" id="notification-view-modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" role="document">
            <div class="modal-content tps ntf">
                <div class="modal-header px-2 pt-2">
                    <h3 class="mb-0 px-2">{{ translate('Notification details') }}</h3>
                    <button type="button" class="close btn btn--reset btn-circle" data-dismiss="modal"
                        aria-label="{{ translate('messages.Close') }}">
                        <span aria-hidden="true" class="tio-clear fs-20 opacity-70"></span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="ntf-stage">
                        <div class="ntf-phone">
                            <div class="ntf-phone__status">
                                <span>{{ now()->format('g:i') }}</span>
                                <span class="d-inline-flex align-items-center gap-1">
                                    <span class="ntf-phone__signal"><span></span><span></span><span></span></span>
                                    <span class="ntf-phone__battery"><span></span></span>
                                </span>
                            </div>
                            <div class="ntf-push">
                                <div class="ntf-push__head">
                                    <span class="ntf-push__app">
                                        <img class="onerror-image" src="{{ $app_icon }}"
                                            data-onerror-image="{{ asset('public/assets/admin/img/notification.png') }}" alt="">
                                    </span>
                                    <span class="ntf-push__name">{{ $app_name }}</span>
                                    <span class="ntf-push__ago">{{ translate('now') }}</span>
                                </div>
                                <p class="ntf-push__title" data-view="title"></p>
                                <p class="ntf-push__body" data-view="description"></p>
                                <div class="ntf-push__media">
                                    <img src="" data-view="image" alt="">
                                </div>
                            </div>
                        </div>
                    </div>

                    <dl class="ntf-modal-facts">
                        <div class="ntf-modal-fact">
                            <dt>{{ translate('messages.Targeted user') }}</dt>
                            <dd data-view="target"></dd>
                        </div>
                        <div class="ntf-modal-fact">
                            <dt>{{ translate('messages.zones') }}</dt>
                            <dd data-view="zone"></dd>
                        </div>
                        <div class="ntf-modal-fact">
                            <dt>{{ translate('Sent') }}</dt>
                            <dd data-view="sent"></dd>
                        </div>
                    </dl>
                </div>
                <div class="modal-footer border-0 shadow">
                    <div class="btn--container justify-content-end">
                        <button data-dismiss="modal" class="btn btn--reset min-w-120">
                            <i class="tio-clear"></i> {{ translate('Close') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="notification-how-it-works" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content tps">
                <div class="modal-header">
                    <h5 class="modal-title">{{ translate('Send notification') }}</h5>
                    <button type="button" class="close btn btn--reset btn-circle" data-dismiss="modal"
                        aria-label="{{ translate('messages.Close') }}">
                        <span aria-hidden="true" class="tio-clear fs-20 opacity-70"></span>
                    </button>
                </div>
                <div class="modal-body">
                    <ol class="tps-steps mb-3">
                        <li>{{ translate('Choose who the message goes to, and the zone they order in.') }}</li>
                        <li>{{ translate('Write the title and the message. The preview beside the form is what a phone shows.') }}</li>
                        <li>{{ translate('Add an image if it helps. It only appears once the notification is pulled open.') }}</li>
                        <li>{{ translate('Save & send delivers it straight away. Send an old one again from its row in the history.') }}</li>
                    </ol>
                    <div class="tps-note tps-note--info">
                        <i class="tio-info-outined"></i>
                        <div>{{ translate('Switching a history row off stops it being counted as active. It does not recall a message that has already been delivered.') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('script_2')
    @php($notificationFormConfig = [
        'submitUrl' => route('admin.notification.store'),
        'updateUrl' => route('admin.notification.update', '__id__'),
        'historyUrl' => route('admin.notification.add-new'),
        'successMessage' => translate('messages.Notification sent successfully'),
        'confirmImage' => asset('public/assets/admin/img/off-danger.png'),
        'lang' => [
            'notSet' => translate('messages.Not set'),
            'previewTitle' => translate('Your title shows here'),
            'previewBody' => translate('Your message shows here'),
            'confirmTitle' => translate('messages.Are you sure?'),
            'confirmText' => translate('This notification goes out straight away to the audience you picked'),
            'cancel' => translate('messages.No'),
            'send' => translate('messages.send'),
        ],
    ])
    <script>
        "use strict";

        window.notificationFormConfig = @json($notificationFormConfig);
    </script>
    <script src="{{ asset('public/assets/admin') }}/js/view-pages/notification-form.js"></script>
@endpush
