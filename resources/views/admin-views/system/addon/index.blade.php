@use('App\Support\Addon\AddonCompatibility')
@extends('layouts.admin.app')

@section('title', translate('System addons'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/view-pages/system-addon.css') }}">
@endpush

@section('content')
    @php
        // ---------------------------------------------------------------------
        // Server readiness: the same limits the upload actually depends on, so
        // an admin sees why a 20MB zip would fail before they try to send one.
        // ---------------------------------------------------------------------
        $requiredBytes = ADDON_MAX_FILE_SIZE * 1024 * 1024;
        $iniToBytes = function ($value) {
            $value = trim((string) $value);
            if ($value === '') {
                return 0;
            }
            $number = (float) $value;
            return (int) match (strtolower(substr($value, -1))) {
                'g' => $number * 1024 * 1024 * 1024,
                'm' => $number * 1024 * 1024,
                'k' => $number * 1024,
                default => $number,
            };
        };

        $uploadMax = ini_get('upload_max_filesize');
        $postMax = ini_get('post_max_size');
        $modulesWritable = is_writable(base_path('Modules'));
        $zipAvailable = class_exists('ZipArchive');

        $requirements = [
            [
                'label' => translate('PHP upload max filesize'),
                'hint' => translate('Minimum required') . ': ' . ADDON_MAX_FILE_SIZE . 'MB',
                'value' => $uploadMax . 'B',
                'passed' => $iniToBytes($uploadMax) >= $requiredBytes,
            ],
            [
                'label' => translate('PHP post max size'),
                'hint' => translate('Minimum required') . ': ' . ADDON_MAX_FILE_SIZE . 'MB',
                'value' => $postMax . 'B',
                'passed' => $iniToBytes($postMax) >= $requiredBytes,
            ],
            [
                'label' => translate('Modules directory is writable'),
                'hint' => translate('Uploaded add-ons are extracted into the Modules folder'),
                'value' => $modulesWritable ? translate('Writable') : translate('Read only'),
                'passed' => $modulesWritable,
            ],
            [
                'label' => translate('ZipArchive extension'),
                'hint' => translate('Required to extract the uploaded package'),
                'value' => $zipAvailable ? translate('Enabled') : translate('Missing'),
                'passed' => $zipAvailable,
            ],
        ];
        $failedRequirements = count(array_filter($requirements, fn ($item) => ! $item['passed']));

        // ---------------------------------------------------------------------
        // Read every installed add-on once, so the grid, the counters and the
        // modals all work off the same normalised list.
        // ---------------------------------------------------------------------
        $addonItems = [];
        foreach ($addons as $addonPath) {
            $info = include $addonPath . '/Addon/info.php';
            $info = is_array($info) ? $info : [];
            $name = $info['name'] ?? basename($addonPath);

            $addonItems[] = [
                'path' => $addonPath,
                'name' => $name,
                'label' => $name === 'Builder' ? translate('Vendor website builder') : $name,
                'version' => $info['version'] ?? null,
                'required_version' => AddonCompatibility::requiredVersion(name: $name),
                'compatible' => AddonCompatibility::isCompatible(name: $name),
                'is_published' => (int) (! empty($info['is_published']) && AddonCompatibility::isCompatible(name: $name)),
                'licensed' => ! empty($info['purchase_code']) && ! empty($info['username']),
            ];
        }
        usort($addonItems, fn ($a, $b) => [$b['is_published'], $a['label']] <=> [$a['is_published'], $b['label']]);

        $activeCount = count(array_filter($addonItems, fn ($item) => $item['is_published']));
        $inactiveCount = count($addonItems) - $activeCount;
        $builderItem = collect($addonItems)->firstWhere('name', 'Builder');
    @endphp

    <div class="content container-fluid addon-page" id="addonPage"
         data-upload-url="{{ route('admin.business-settings.system-addon.upload') }}"
         data-publish-url="{{ route('admin.business-settings.system-addon.publish') }}"
         data-delete-url="{{ route('admin.business-settings.system-addon.delete') }}"
         data-activated-message="{{ translate('Add-on is now active') }}"
         data-deactivated-message="{{ translate('Add-on has been turned off') }}"
         data-upload-error-message="{{ translate('The upload could not be completed. Please try again.') }}"
         data-invalid-file-message="{{ translate('Only a single .zip add-on package can be uploaded. Folders and other file types are not accepted.') }}"
         data-error-message="{{ translate('The server did not respond. Nothing was changed — please try again.') }}"
         data-show-builder-requirements="{{ session('builder_requirements_issues') ? 1 : 0 }}">

        <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h1 class="page-header-title">
                    <span class="page-header-icon">
                        <img src="{{ asset('public/assets/admin/img/addon.png') }}" alt="">
                    </span>
                    <span>{{ translate('System addons') }}</span>
                </h1>
                <p class="page-header-desc">{{ translate('Extra features you have bought, and whether each one is switched on.') }}</p>
            </div>
            <div class="page-header-actions">
                <button type="button" class="addon-info-trigger text--primary-2 d-flex flex-wrap align-items-center"
                        data-toggle="modal" data-target="#settingModal">
                    <strong class="mr-2">{{ translate('How it works') }}</strong>
                    <span class="blinkings">
                        <i class="tio-info-outined"></i>
                    </span>
                </button>
            </div>
        </div>

        {{-- ---------------------------------------------------------------- --}}
        {{-- Upload --}}
        {{-- ---------------------------------------------------------------- --}}
        <div class="card mb-4">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2 py-3">
                <div>
                    <h4 class="mb-1 text-capitalize">{{ translate('Upload addon') }}</h4>
                    <p class="mb-0 fs-12 color-656565">
                        {{ translate('Install a new add-on by uploading the package you downloaded from CodeCanyon') }}
                    </p>
                </div>
                <span class="addon-chip">
                    <i class="tio-attachment"></i>
                    .zip only &middot; Max {{ ADDON_MAX_FILE_SIZE . 'MB' }}
                </span>
            </div>

            <div class="card-body">
                <div class="row g-4">
                    <div class="col-lg-5">
                        <form enctype="multipart/form-data" id="theme_form">
                            <label class="addon-dropzone" id="addonDropzone" for="inputFile">
                                <input type="file" name="file_upload" id="inputFile" class="read-file" accept=".zip"
                                       aria-label="{{ translate('Select add-on zip file') }}">
                                <span class="addon-dropzone__icon">
                                    <i class="tio-folder-add"></i>
                                </span>
                                <span class="addon-dropzone__title">
                                    {{ translate('Drag & drop the add-on file here') }}
                                </span>
                                <span class="addon-dropzone__hint">
                                    {{ translate('Or') }} <u>{{ translate('Browse from your device') }}</u>
                                </span>
                            </label>
                        </form>

                        <div class="addon-file mt-3 d--none" id="progress-bar">
                            <span class="addon-file__icon"><i class="tio-file-outlined"></i></span>
                            <div class="addon-file__meta">
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                    <span id="name_of_file" class="text-truncate fz-12 font-weight-bold"></span>
                                    <span class="fs-12 color-656565">
                                        <span id="size_of_file"></span>
                                        <span class="text-muted">&middot;</span>
                                        <span id="progress-label">0%</span>
                                    </span>
                                </div>
                                <div class="addon-progress">
                                    <div class="addon-progress__bar" id="uploadProgress"></div>
                                </div>
                            </div>
                            <button type="button" class="addon-icon-btn" id="remove_file"
                                    title="{{ translate('Remove file') }}" aria-label="{{ translate('Remove file') }}">
                                <i class="tio-clear"></i>
                            </button>
                        </div>

                        <button type="button" id="upload_theme" disabled
                                class="btn btn--primary px-4 mt-3 w-100 {{ getEnvMode() == 'demo' ? 'call-demo' : 'zip-upload' }}">
                            <i class="tio-upload"></i> {{ translate('Upload') }}
                        </button>
                    </div>

                    <div class="col-lg-7">
                        <div class="addon-req">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
                                <h5 class="mb-0">{{ translate('Server Readiness') }}</h5>
                                @if($failedRequirements)
                                    <span class="addon-req__status addon-req__status--warn">
                                        <i class="tio-warning"></i>
                                        {{ translate('Issues need attention') }}: {{ $failedRequirements }}
                                    </span>
                                @else
                                    <span class="addon-req__status addon-req__status--ok">
                                        <i class="tio-checkmark-circle"></i>
                                        {{ translate('Ready to install') }}
                                    </span>
                                @endif
                            </div>

                            @foreach($requirements as $requirement)
                                <div class="addon-req__item">
                                    <span class="addon-req__icon {{ $requirement['passed'] ? 'text-success' : 'text-danger' }}">
                                        <i class="{{ $requirement['passed'] ? 'tio-checkmark-circle' : 'tio-error-outlined' }}"></i>
                                    </span>
                                    <div class="flex-grow-1">
                                        <div class="addon-req__label">{{ $requirement['label'] }}</div>
                                        <div class="addon-req__hint">{{ $requirement['hint'] }}</div>
                                    </div>
                                    <span class="addon-req__value">{{ $requirement['value'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Toggling an add-on adds or removes menu entries across the panel, and
             those are rendered by the layout — so they only change on the next
             page load. Say so instead of forcing a reload after every switch. --}}
        <div class="addon-refresh mb-4 d-none" data-addon-refresh-notice>
            <span class="addon-refresh__icon"><i class="tio-refresh"></i></span>
            <span class="flex-grow-1">
                {{ translate('Menus and settings for the add-ons you just changed appear after the page is refreshed.') }}
            </span>
            <button type="button" class="btn btn-sm btn--primary flex-shrink-0" data-addon-refresh-now>
                <i class="tio-refresh"></i> {{ translate('Refresh now') }}
            </button>
        </div>

        @if($builderItem)
            @php($appHost = preg_replace('/^www\./i', '', parse_url(config('app.url'), PHP_URL_HOST) ?: 'yourdomain.com'))
            @php($hostParts = explode('.', $appHost))
            @php($secondLevelTlds = ['co', 'com', 'net', 'org', 'gov', 'edu', 'ac'])
            @php($appHost = count($hostParts) > 2 ? implode('.', array_slice($hostParts, -(in_array($hostParts[count($hostParts) - 2], $secondLevelTlds) ? 3 : 2))) : $appHost)
            {{-- Always rendered so the switch can reveal it in place; hidden while
                 Builder is off. --}}
            <div class="addon-alert mb-4 {{ $builderItem['is_published'] ? '' : 'd-none' }}" data-builder-alert>
                <span class="text-info fs-16 lh-1">
                    <i class="tio-light-on"></i>
                </span>
                <span>
                    <strong>{{ translate('Builder addon is active') }}.</strong>
                    {{ translate('To let vendors open their storefronts on their own sub-domains, configure a wildcard domain on your server') }}
                    (<code>*.{{ $appHost }}</code>)
                    {{ translate('Pointing to this server with a matching wildcard SSL certificate, or vendor sub-domains will not resolve.') }}
                </span>
            </div>
        @endif

        {{-- ---------------------------------------------------------------- --}}
        {{-- Installed add-ons --}}
        {{-- ---------------------------------------------------------------- --}}
        <div class="addon-section-head">
            <h2>{{ translate('Installed Add-ons') }}</h2>
            <div class="addon-count-chips">
                <span class="addon-count-chip">
                    <b>{{ count($addonItems) }}</b> {{ translate('installed') }}
                </span>
                <span class="addon-count-chip addon-count-chip--active">
                    <span class="dot"></span> <b data-addon-count="active">{{ $activeCount }}</b> {{ translate('Active') }}
                </span>
                <span class="addon-count-chip addon-count-chip--inactive">
                    <span class="dot"></span> <b data-addon-count="inactive">{{ $inactiveCount }}</b> {{ translate('Inactive') }}
                </span>
            </div>
        </div>

        @if(count($addonItems))
            <div class="row g-3">
                @foreach($addonItems as $key => $addonItem)
                    <div class="col-12 col-sm-6 col-lg-4 col-xxl-3">
                        <div class="addon-card" data-addon-card data-key="{{ $key }}"
                             data-path="{{ $addonItem['path'] }}"
                             data-label="{{ $addonItem['label'] }}">
                            <div class="addon-card__media">
                                <img class="onerror-image"
                                     data-onerror-image="{{ asset('public/assets/admin/img/placeholder.png') }}"
                                     src="{{ asset($addonItem['path'] . '/public/addon.png') }}"
                                     alt="{{ $addonItem['label'] }}">
                                <span class="addon-badge {{ $addonItem['is_published'] ? 'addon-badge--active' : 'addon-badge--inactive' }}"
                                      data-addon-badge
                                      data-text-on="{{ translate('Active') }}"
                                      data-text-off="{{ translate('Inactive') }}">
                                    <span class="dot"></span>
                                    <span data-addon-badge-text>{{ $addonItem['is_published'] ? translate('Active') : translate('Inactive') }}</span>
                                </span>
                            </div>

                            <div class="addon-card__body">
                                <h3 class="addon-card__title" title="{{ $addonItem['label'] }}">
                                    {{ $addonItem['label'] }}
                                </h3>
                                <div class="addon-chips">
                                    @if($addonItem['version'])
                                        <span class="addon-chip">v{{ $addonItem['version'] }}</span>
                                    @endif
                                    @if(! $addonItem['compatible'])
                                        <span class="addon-chip addon-chip--warn"
                                              title="{{ translate('This add-on stays off until you upload a compatible version.') }}">
                                            <i class="tio-warning"></i> {{ translate('Requires version :version or newer', ['version' => $addonItem['required_version']]) }}
                                        </span>
                                    @endif
                                    {{-- Both licence chips are rendered so activating from the
                                         modal can swap them without a reload. --}}
                                    <span class="addon-chip addon-chip--ok {{ $addonItem['licensed'] ? '' : 'd-none' }}"
                                          data-addon-licensed
                                          title="{{ translate('This add-on is registered to your purchase code on this domain.') }}">
                                        <i class="tio-checkmark-circle"></i> {{ translate('Licensed') }}
                                    </span>
                                    <span class="addon-chip addon-chip--warn {{ $addonItem['licensed'] ? 'd-none' : '' }}"
                                          data-addon-unlicensed
                                          title="{{ translate('Enter your CodeCanyon purchase code the first time you turn this add-on on.') }}">
                                        <i class="tio-warning"></i> {{ translate('License required') }}
                                    </span>
                                </div>
                            </div>

                            <div class="addon-card__footer">
                                <button type="button" class="addon-switch" role="switch"
                                        aria-checked="{{ $addonItem['is_published'] ? 'true' : 'false' }}"
                                        data-addon-switch
                                        data-toggle="modal"
                                        data-target="#shiftThemeModal_{{ $key }}"
                                        data-text-on="{{ translate('Active') }}"
                                        data-text-off="{{ translate('Inactive') }}"
                                        data-title-on="{{ translate('Turn OFF') . ' ' . $addonItem['label'] }}"
                                        data-title-off="{{ translate('Turn ON') . ' ' . $addonItem['label'] }}"
                                        title="{{ ($addonItem['is_published'] ? translate('Turn OFF') : translate('Turn ON')) . ' ' . $addonItem['label'] }}">
                                    <span class="addon-switch__track">
                                        <span class="addon-switch__thumb"></span>
                                    </span>
                                    <span data-addon-switch-text>{{ $addonItem['is_published'] ? translate('Active') : translate('Inactive') }}</span>
                                </button>

                                {{-- Rendered for every add-on, hidden while it is on: an
                                     in-place switch has to be able to reveal it. --}}
                                <button type="button"
                                        class="addon-icon-btn {{ $addonItem['is_published'] ? 'd-none' : '' }}"
                                        data-addon-delete-btn
                                        data-toggle="modal"
                                        data-target="#deleteThemeModal_{{ $key }}"
                                        title="{{ translate('Delete') }}"
                                        aria-label="{{ translate('Delete') . ' ' . $addonItem['label'] }}">
                                    <i class="tio-delete-outlined"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="addon-empty">
                <img width="90" src="{{ asset('public/assets/admin/img/empty-box.png') }}" alt="" class="mb-3">
                <h4 class="mb-1">{{ translate('No add-on installed yet') }}</h4>
                <p class="mb-0 fs-12 color-656565">
                    {{ translate('Upload an add-on package above to get started') }}
                </p>
            </div>
        @endif
    </div>

    {{-- ------------------------------------------------------------------ --}}
    {{-- Modals — kept outside the cards so the hover transform on a card    --}}
    {{-- never becomes their containing block.                              --}}
    {{-- ------------------------------------------------------------------ --}}
    @foreach($addonItems as $key => $addonItem)
        <div class="modal fade" id="shiftThemeModal_{{ $key }}" tabindex="-1"
             aria-labelledby="shiftThemeModalLabel_{{ $key }}" aria-hidden="true"
             data-addon-modal data-key="{{ $key }}"
             data-title-on="{{ translate('Turn off add-on') }}: {{ $addonItem['label'] }}"
             data-title-off="{{ translate('Turn on add-on') }}: {{ $addonItem['label'] }}"
             data-text-on="{{ translate('Menus, settings and features are hidden until you turn it back on. Nothing is uninstalled.') }}"
             data-text-off="{{ translate('Menus, settings and features become available. Required database tables are created on first start.') }}"
             data-confirm-on="{{ translate('Turn OFF') }}"
             data-confirm-off="{{ translate('Turn ON') }}">
            <div class="modal-dialog status-warning-modal modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header border-0 pb-0 d-flex justify-content-end">
                        <button type="button" class="close border-0" data-dismiss="modal" aria-label="{{ translate('Close') }}">
                            <i class="tio-clear"></i>
                        </button>
                    </div>
                    <div class="modal-body px-4 pt-0 px-sm-5 text-center">
                        <div class="mb-3 text-center">
                            <img width="75" src="{{ asset('public/assets/admin/img/shift.png') }}" alt="">
                        </div>
                        <h3 id="shiftThemeModalLabel_{{ $key }}" data-addon-modal-title>
                            {{ $addonItem['is_published'] ? translate('Turn off add-on') : translate('Turn on add-on') }}: {{ $addonItem['label'] }}
                        </h3>
                        <p class="mb-5" data-addon-modal-text>
                            {{ $addonItem['is_published']
                                ? translate('Menus, settings and features are hidden until you turn it back on. Nothing is uninstalled.')
                                : translate('Menus, settings and features become available. Required database tables are created on first start.') }}
                        </p>
                        <div class="btn--container justify-content-center mb-3">
                            <button type="button" class="fs-16 btn btn-secondary px-sm-5" data-dismiss="modal"><i class="tio-clear-circle-outlined"></i> {{ translate('Cancel') }}</button>
                            <button type="button" class="fs-16 btn btn--primary px-sm-5 publish-addon"
                                    data-addon-modal-confirm
                                    data-path="{{ $addonItem['path'] }}" data-dismiss="modal">
                                <i class="{{ $addonItem['is_published'] ? 'tio-toggle-off' : 'tio-toggle-on' }}"></i> {{ $addonItem['is_published'] ? translate('Turn OFF') : translate('Turn ON') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="deleteThemeModal_{{ $key }}" tabindex="-1"
             aria-labelledby="deleteThemeModalLabel_{{ $key }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header border-0 pb-0 d-flex justify-content-end">
                        <button type="button" class="btn-close border-0" data-dismiss="modal" aria-label="{{ translate('Close') }}">
                            <i class="tio-clear"></i>
                        </button>
                    </div>
                    <div class="modal-body px-4 px-sm-5 text-center">
                        <div class="mb-3 text-center">
                            <img width="75" src="{{ asset('public/assets/admin/img/delete.png') }}" alt="">
                        </div>
                        <h3 id="deleteThemeModalLabel_{{ $key }}">
                            {{ translate('Delete addon') }}: {{ $addonItem['label'] }}
                        </h3>
                        <p class="mb-5">
                            {{ translate('Files are deleted permanently. Reinstalling needs the original package and your purchase code.') }}
                        </p>
                        <div class="btn--container justify-content-center mb-3">
                            <button type="button" class="fs-16 btn btn-secondary px-sm-5" data-dismiss="modal"><i class="tio-clear-circle-outlined"></i> {{ translate('Cancel') }}</button>
                            <button type="button" class="fs-16 btn btn-danger px-sm-5 theme-delete" data-dismiss="modal"
                                    data-path="{{ $addonItem['path'] }}"><i class="tio-delete-outlined"></i> {{ translate('Delete') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    <div class="modal fade" id="settingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header align-items-center">
                    <h4 class="modal-title mb-0">{{ translate('How add-ons work') }}</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="{{ translate('Close') }}">
                        <span aria-hidden="true" class="tio-clear"></span>
                    </button>
                </div>
                <div class="modal-body pb-5">
                    <img src="{{ asset('public/assets/admin/img/addon_setting.png') }}" loading="lazy" alt=""
                         class="rounded mb-4 mw-100">
                    <div class="d-flex flex-column gap-3">
                        <div class="addon-guide__step">
                            <span class="addon-guide__num">1</span>
                            <span>
                                {{ translate('Buy the add-on on CodeCanyon, then open') }}
                                <strong>{{ translate('Downloads') }}</strong>
                                {{ translate('on your CodeCanyon account and download it.') }}
                            </span>
                        </div>
                        <div class="addon-guide__step">
                            <span class="addon-guide__num">2</span>
                            <span>{{ translate('The download arrives as a zip archive.') }}</span>
                        </div>
                        <div class="addon-guide__step">
                            <span class="addon-guide__num">3</span>
                            <span>{{ translate('Extract it — inside is a second zip named after the add-on. That inner file is the one to upload.') }}</span>
                        </div>
                        <div class="addon-guide__step">
                            <span class="addon-guide__num">4</span>
                            <span>{{ translate('Upload it here. The add-on is installed but stays off until you turn it on.') }}</span>
                        </div>
                        <div class="addon-guide__step">
                            <span class="addon-guide__num">5</span>
                            <span>{{ translate('First activation asks for your CodeCanyon username and purchase code. Refresh afterwards to see the new menus.') }}</span>
                        </div>
                    </div>
                    <div class="d-flex justify-content-center mt-4">
                        <button class="btn btn--primary px-10" data-dismiss="modal"><i class="tio-checkmark-circle-outlined"></i> {{ translate('Got it') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('admin-views.system.addon.partials.activation-modal')
    @include('admin-views.system.addon.partials.builder-requirements-modal')
@endsection

@push('script_2')
    <script src="{{ asset('public/assets/admin/js/view-pages/system-addon.js') }}"></script>
@endpush
