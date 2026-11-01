@extends('layouts.admin.app')

@section('title', translate('messages.Storage Connection'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/third-party-setup.css') }}">
@endpush

@section('content')
    @php
        $local_storage = \App\CentralLogics\Helpers::get_business_settings('local_storage') ?? 1;
        $third_party_storage = \App\CentralLogics\Helpers::get_business_settings('3rd_party_storage');
        $s3 = \App\CentralLogics\Helpers::get_business_settings('s3_credential');
        $s3 = is_array($s3) ? $s3 : [];

        $is_demo = getEnvMode() == 'demo';
        $local_on = (int) $local_storage === 1;
        $third_party_on = (int) $third_party_storage === 1;

        $s3_fields = [
            ['name' => 'key', 'label' => translate('messages.Key'), 'secret' => false, 'placeholder' => 'AKIA…', 'hint' => translate('The Access Key ID that identifies your storage user.')],
            ['name' => 'secret', 'label' => translate('messages.Secret'), 'secret' => true, 'placeholder' => '••••••••', 'hint' => translate('Secret Access Key. Shown only once when the key pair is created.')],
            ['name' => 'region', 'label' => translate('messages.region'), 'secret' => false, 'placeholder' => 'us-east-1', 'hint' => translate('Region code of the bucket, not its display name.')],
            ['name' => 'bucket', 'label' => translate('messages.bucket'), 'secret' => false, 'placeholder' => 'my-bucket', 'hint' => translate('Exact bucket name, case sensitive.')],
            ['name' => 'url', 'label' => 'URL', 'secret' => false, 'placeholder' => 'https://cdn.example.com', 'hint' => translate('Public base URL used to build image links.')],
            ['name' => 'end_point', 'label' => translate('messages.End point'), 'secret' => false, 'placeholder' => 'https://s3.us-east-1.amazonaws.com', 'hint' => translate('API endpoint. Change it when using a provider other than Amazon.')],
        ];
    @endphp

    <div class="content container-fluid tps">
        @include('admin-views.business-settings.partials.third-party-header', [
            'icon' => 'tio-cloud',
            'title' => translate('messages.Storage connection credentials setup'),
            'summary' => translate('Decide where uploaded images and files are kept, and connect your storage bucket.'),
        ])

        <div class="tps-note tps-note--info mb-3">
            <i class="tio-info"></i>
            <div>
                {{ translate('Only one storage destination is active at a time. Existing files are not moved.') }}
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <form action="{{ route('admin.business-settings.third-party.storage_connection_update', ['local_storage']) }}"
                      method="post" id="local_storage_status_form">
                    @csrf
                    <input type="hidden" name="toggle_type" value="local_storage">
                    <div class="tps-card">
                        <div class="tps-card__head border-bottom-0">
                            <span class="tps-card__brand"><i class="tio-folder"></i></span>
                            <div class="tps-card__titles">
                                <h2 class="tps-card__title d-flex align-items-center gap-2">
                                    {{ translate('Local Storage') }}
                                    @if ($local_on)
                                        <span class="tps-pill tps-pill--on">{{ translate('In Use') }}</span>
                                    @endif
                                </h2>
                                <p class="tps-card__subtitle">
                                    {{ translate('If enabled, System will store all files and images to local storage') }}
                                </p>
                            </div>
                            <div class="tps-card__aside">
                                <label class="toggle-switch toggle-switch-sm p-0 m-0">
                                    <input type="checkbox" id="local_storage_status"
                                           data-id="local_storage_status"
                                           data-type="status"
                                           data-image-on="{{ asset('/public/assets/admin/img/modal/local_storage.png') }}"
                                           data-image-off="{{ asset('/public/assets/admin/img/modal/local_storage.png') }}"
                                           data-title-on="{{ translate('By Turning ON Local Storage Option') }}"
                                           data-title-off="{{ translate('By Turning OFF Local Storage Option') }}"
                                           data-text-on="<p>{{ translate('System will store all files and images to local storage') }}</p>"
                                           data-text-off="<p>{{ translate('System will not store all files and images to local storage') }}</p>"
                                           class="status toggle-switch-input dynamic-checkbox"
                                           name="status" value="1" {{ $local_on ? 'checked' : '' }}>
                                    <span class="toggle-switch-label text p-0">
                                        <span class="toggle-switch-indicator"></span>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="col-md-6">
                <form action="{{ route('admin.business-settings.third-party.storage_connection_update', ['3rd_party_storage']) }}"
                      method="post" id="3rd_party_storage_status_form">
                    @csrf
                    <input type="hidden" name="toggle_type" value="3rd_party_storage">
                    <div class="tps-card">
                        <div class="tps-card__head border-bottom-0">
                            <span class="tps-card__brand"><i class="tio-cloud-on"></i></span>
                            <div class="tps-card__titles">
                                <h2 class="tps-card__title d-flex align-items-center gap-2">
                                    {{ translate('Third-party storage') }}
                                    @if ($third_party_on)
                                        <span class="tps-pill tps-pill--on">{{ translate('In Use') }}</span>
                                    @endif
                                </h2>
                                <p class="tps-card__subtitle">
                                    {{ translate('If enabled, System will store all files and images to third-party storage') }}
                                </p>
                            </div>
                            <div class="tps-card__aside">
                                <label class="toggle-switch toggle-switch-sm p-0 m-0">
                                    <input type="checkbox" id="3rd_party_storage_status"
                                           data-id="3rd_party_storage_status"
                                           data-type="status"
                                           data-image-on="{{ asset('/public/assets/admin/img/modal/3rd_party_storage.png') }}"
                                           data-image-off="{{ asset('/public/assets/admin/img/modal/3rd_party_storage.png') }}"
                                           data-title-on="{{ translate('By turning on the third-party storage option') }}"
                                           data-title-off="{{ translate('By turning off the third-party storage option') }}"
                                           data-text-on="<p>{{ translate('System will store all files and images to third-party storage') }}</p>"
                                           data-text-off="<p>{{ translate('System will not store all files and images to third-party storage') }}</p>"
                                           class="status toggle-switch-input dynamic-checkbox"
                                           name="status" value="1" {{ $third_party_on ? 'checked' : '' }}>
                                    <span class="toggle-switch-label text p-0">
                                        <span class="toggle-switch-indicator"></span>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <form action="{{ ! $is_demo ? route('admin.business-settings.third-party.storage_connection_update', ['storage_connection']) : 'javascript:' }}"
              method="post">
            @csrf
            <div class="tps-card">
                <div class="tps-card__head">
                    <span class="tps-card__brand"><i class="tio-key"></i></span>
                    <div class="tps-card__titles">
                        <h2 class="tps-card__title">{{ translate('Storage credentials') }} (S3)</h2>
                        <p class="tps-card__subtitle">
                            {{ translate('The Access Key ID is a publicly accessible identifier used to authenticate storage requests.') }}
                            <a target="_blank" rel="noopener" href="https://aws.amazon.com/s3/">{{ translate('Learn More') }}</a>
                        </p>
                    </div>
                </div>

                <div class="tps-card__body">
                    @unless ($third_party_on)
                        <div class="tps-note tps-note--muted mb-4">
                            <i class="tio-info-outined"></i>
                            <div>
                                {{ translate('Third-party storage is off. Save these credentials now and switch over when ready.') }}
                            </div>
                        </div>
                    @endunless

                    <div class="row g-3">
                        @foreach ($s3_fields as $field)
                            <div class="col-md-6">
                                <div class="tps-field">
                                    <label for="{{ $field['name'] }}" class="tps-field__label">
                                        {{ $field['label'] }} <span class="tps-req">*</span>
                                    </label>
                                    <div class="tps-input-wrap {{ $field['secret'] ? 'has-two-actions' : '' }}">
                                        <input required id="{{ $field['name'] }}" name="{{ $field['name'] }}"
                                               class="form-control" autocomplete="off"
                                               type="{{ $field['secret'] ? 'password' : 'text' }}"
                                               placeholder="{{ $field['placeholder'] }}"
                                               value="{{ ! $is_demo ? $s3[$field['name']] ?? '' : '' }}">
                                        <button type="button" class="tps-input-action tps-copy"
                                                data-target="#{{ $field['name'] }}" aria-label="{{ translate('Copy') }}">
                                            <i class="tio-copy"></i>
                                        </button>
                                        @if ($field['secret'])
                                            <button type="button" class="tps-input-action tps-toggle-secret"
                                                    data-target="#{{ $field['name'] }}" aria-label="{{ translate('Show value') }}">
                                                <i class="tio-visible"></i>
                                            </button>
                                        @endif
                                    </div>
                                    <small class="tps-field__hint">{{ $field['hint'] }}</small>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="tps-card__foot">
                    <button type="reset" class="btn btn--reset"><i class="tio-refresh"></i> {{ translate('messages.Reset') }}</button>
                    <button type="{{ ! $is_demo ? 'submit' : 'button' }}" class="btn btn--primary call-demo">
                        <i class="tio-save"></i> {{ translate('messages.Save') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection

@push('script_2')
    @include('admin-views.business-settings.partials.third-party-scripts')
@endpush
