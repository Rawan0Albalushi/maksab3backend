@extends('layouts.vendor.app')

@section('title', translate('messages.Profile settings'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/third-party-setup.css') }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/view-pages/vendor-profile.css') }}">
@endpush

@php($is_demo = getEnvMode() == 'demo')
@php($full_name = trim($profile_user->f_name . ' ' . $profile_user->l_name))
@php($role_name = $is_employee ? ($profile_user->role?->name ?? translate('messages.Employee')) : translate('Store owner'))
@php($placeholder_image = asset('public/assets/admin/img/160x160/img1.jpg'))
@php($password_rules = [
    'length' => translate('Minimum characters') . ': 8',
    'lower' => translate('Lowercase letter'),
    'upper' => translate('Uppercase letter'),
    'number' => translate('Number'),
    'symbol' => translate('Symbol'),
])

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <i class="tio-user-outlined"></i>
                <span>{{ translate('messages.Profile settings') }}</span>
            </h1>
            <p class="page-header-desc">{{ translate('Your own name, photograph, email and password.') }}</p>
        </div>

        <div class="tps vpf">
            <div class="row g-3">
                <div class="col-xl-4">
                    <div class="vpf-aside">
                        <div class="tps-card vpf-identity" data-empty-value="{{ translate('Not set yet') }}">
                            <div class="vpf-cover"></div>
                            <div class="vpf-identity__body">
                                <div class="vpf-avatar">
                                    <img class="vpf-avatar__img onerror-image" id="vpf_avatar"
                                        src="{{ $profile_user->image_full_url ?? $placeholder_image }}"
                                        data-onerror-image="{{ $placeholder_image }}" alt="">
                                    <input type="file" name="image" id="vpf_image" class="vpf-avatar__input"
                                        form="vendor-profile-form"
                                        accept=".webp, .jpg, .png, .jpeg, .gif, .bmp, .tif, .tiff|image/*"
                                        data-max-size="{{ MAX_FILE_SIZE }}"
                                        data-invalid-type="{{ translate('messages.Image must be a valid image file') }}"
                                        data-invalid-size="{{ translate('messages.Image must be less than') }} {{ MAX_FILE_SIZE }}mb">
                                    <label for="vpf_image" class="vpf-avatar__trigger" title="{{ translate('Change photo') }}">
                                        <i class="tio-photo-camera"></i>
                                        <span class="sr-only">{{ translate('Change photo') }}</span>
                                    </label>
                                </div>

                                <p class="vpf-name {{ $full_name === '' ? 'is-empty' : '' }}" data-preview="name">{{ $full_name ?: translate('Not set yet') }}</p>
                                <span class="vpf-role">
                                    <i class="{{ $is_employee ? 'tio-user-outlined' : 'tio-shop-outlined' }}"></i>
                                    {{ $role_name }}
                                </span>

                                <p class="vpf-photo-note" id="vpf_photo_note" hidden>
                                    <i class="tio-info-outined"></i>
                                    {{ translate('Photo selected. Save changes to apply it.') }}
                                </p>
                                <small class="vpf-photo-hint">
                                    {{ translate('A square photo works best.') }}<br>
                                    JPG, PNG, WEBP · ≤ {{ MAX_FILE_SIZE }} MB
                                </small>
                            </div>

                            <ul class="vpf-facts">
                                <li>
                                    <i class="tio-email-outlined"></i>
                                    <span class="vpf-facts__key">{{ translate('messages.email') }}</span>
                                    <span class="vpf-facts__value" data-preview="email">{{ $profile_user->email }}</span>
                                </li>
                                <li>
                                    <i class="tio-call"></i>
                                    <span class="vpf-facts__key">{{ translate('Phone') }}</span>
                                    <span class="vpf-facts__value" data-preview="phone">{{ $profile_user->phone }}</span>
                                </li>
                                @if ($store)
                                    <li>
                                        <i class="tio-shop-outlined"></i>
                                        <span class="vpf-facts__key">{{ translate('messages.Store') }}</span>
                                        <span class="vpf-facts__value" title="{{ $store->name }}">{{ $store->name }}</span>
                                    </li>
                                @endif
                                <li>
                                    <i class="tio-calendar-month"></i>
                                    <span class="vpf-facts__key">{{ translate('messages.Joined') }}</span>
                                    <span class="vpf-facts__value">{{ $profile_user->created_at?->translatedFormat('d M Y') ?? '—' }}</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-xl-8">
                    <div class="vpf-main">
                        <form action="{{ $is_demo ? 'javascript:' : route('vendor.profile.update') }}" method="post"
                            enctype="multipart/form-data" id="vendor-profile-form" class="tps-card custom-validation">
                            @csrf
                            <div class="tps-card__head">
                                <span class="tps-card__brand"><i class="tio-user-outlined"></i></span>
                                <div class="tps-card__titles">
                                    <h2 class="tps-card__title">{{ translate('Basic information') }}</h2>
                                    <p class="tps-card__subtitle">{{ translate('How your name and contact details appear across the panel.') }}</p>
                                </div>
                            </div>

                            <div class="tps-card__body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="tps-field">
                                            <div class="error-wrapper">
                                                <label class="tps-field__label" for="f_name">
                                                    {{ translate('First name') }}
                                                    <span class="tps-req" data-toggle="tooltip" data-placement="right"
                                                        data-original-title="{{ translate('messages.Required.') }}">*</span>
                                                </label>
                                                <input type="text" name="f_name" id="f_name" class="form-control"
                                                    value="{{ old('f_name', $profile_user->f_name) }}" maxlength="100"
                                                    autocomplete="given-name"
                                                    placeholder="{{ translate('messages.Ex') }}: John" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="tps-field">
                                            <div class="error-wrapper">
                                                <label class="tps-field__label" for="l_name">
                                                    {{ translate('Last name') }}
                                                    <span class="tps-opt">{{ translate('Optional') }}</span>
                                                </label>
                                                <input type="text" name="l_name" id="l_name" class="form-control"
                                                    value="{{ old('l_name', $profile_user->l_name) }}" maxlength="100"
                                                    autocomplete="family-name"
                                                    placeholder="{{ translate('messages.Ex') }}: Doe">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="tps-field">
                                            <div class="error-wrapper">
                                                <label class="tps-field__label" for="email">
                                                    {{ translate('messages.email') }}
                                                    <span class="tps-req" data-toggle="tooltip" data-placement="right"
                                                        data-original-title="{{ translate('messages.Required.') }}">*</span>
                                                </label>
                                                <input type="email" name="email" id="email" class="form-control"
                                                    value="{{ old('email', $profile_user->email) }}" maxlength="100"
                                                    autocomplete="email"
                                                    placeholder="{{ translate('messages.Ex') }}: ex@gmail.com" required>
                                            </div>
                                            @unless ($is_employee)
                                                <small class="tps-field__hint">{{ translate('Your store email changes with it.') }}</small>
                                            @endunless
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="tps-field">
                                            <div class="error-wrapper">
                                                <label class="tps-field__label" for="phone">
                                                    {{ translate('Phone') }}
                                                    <span class="tps-req" data-toggle="tooltip" data-placement="right"
                                                        data-original-title="{{ translate('messages.Required.') }}">*</span>
                                                </label>
                                                <input type="tel" name="phone" id="phone" class="form-control"
                                                    value="{{ old('phone', $profile_user->phone) }}" autocomplete="tel"
                                                    placeholder="{{ translate('messages.Ex') }}: +88017********" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="tps-card__foot">
                                <button type="reset" class="btn btn--reset"><i class="tio-refresh"></i> {{ translate('messages.Reset') }}</button>
                                <button type="{{ $is_demo ? 'button' : 'submit' }}" class="btn btn--primary {{ $is_demo ? 'call-demo' : '' }}">
                                    <i class="tio-save"></i> {{ translate('messages.Save changes') }}
                                </button>
                            </div>
                        </form>

                        <form action="{{ $is_demo ? 'javascript:' : route('vendor.profile.settings-password') }}" method="post"
                            id="vendor-password-form" class="tps-card custom-validation"
                            @unless ($is_demo) data-ajax-form data-ajax-reset @endunless>
                            @csrf
                            <div class="tps-card__head">
                                <span class="tps-card__brand"><i class="tio-lock-outlined"></i></span>
                                <div class="tps-card__titles">
                                    <h2 class="tps-card__title">{{ translate('messages.Change your password') }}</h2>
                                    <p class="tps-card__subtitle">{{ translate('Choose a strong password and type it twice to confirm.') }}</p>
                                </div>
                            </div>

                            <div class="tps-card__body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="tps-field">
                                            <div class="error-wrapper">
                                                <label class="tps-field__label" for="password">
                                                    {{ translate('New password') }}
                                                    <span class="tps-req" data-toggle="tooltip" data-placement="right"
                                                        data-original-title="{{ translate('messages.Required.') }}">*</span>
                                                </label>
                                                <div class="tps-input-wrap">
                                                    <input type="password" name="password" id="password" class="form-control"
                                                        autocomplete="new-password"
                                                        placeholder="{{ translate('Minimum characters') }}: 8" required>
                                                    <button type="button" class="tps-input-action tps-toggle-secret" data-target="#password"
                                                        aria-label="{{ translate('Show value') }}"><i class="tio-visible"></i></button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="tps-field">
                                            <div class="error-wrapper">
                                                <label class="tps-field__label" for="confirm_password">
                                                    {{ translate('Confirm password') }}
                                                    <span class="tps-req" data-toggle="tooltip" data-placement="right"
                                                        data-original-title="{{ translate('messages.Required.') }}">*</span>
                                                </label>
                                                <div class="tps-input-wrap">
                                                    <input type="password" name="confirm_password" id="confirm_password" class="form-control"
                                                        autocomplete="new-password"
                                                        data-mismatch="{{ translate('Passwords do not match') }}"
                                                        placeholder="{{ translate('Minimum characters') }}: 8" required>
                                                    <button type="button" class="tps-input-action tps-toggle-secret" data-target="#confirm_password"
                                                        aria-label="{{ translate('Show value') }}"><i class="tio-visible"></i></button>
                                                </div>
                                            </div>
                                            <small class="vpf-match" id="vpf_match" aria-live="polite" hidden>
                                                <i class="tio-checkmark-circle"></i> {{ translate('Passwords match') }}
                                            </small>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <ul class="vpf-rules" id="vpf_password_rules">
                                            @foreach ($password_rules as $rule => $label)
                                                <li data-rule="{{ $rule }}">
                                                    <i class="tio-checkmark-circle vpf-rules__met"></i>
                                                    <i class="tio-circle-outlined vpf-rules__unmet"></i>
                                                    {{ $label }}
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <div class="tps-card__foot">
                                <span class="tps-foot-note">{{ translate('Use a password you do not use anywhere else.') }}</span>
                                <button type="{{ $is_demo ? 'button' : 'submit' }}" class="btn btn--primary {{ $is_demo ? 'call-demo' : '' }}">
                                    <i class="tio-lock-outlined"></i> {{ translate('Update password') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script_2')
    @include('admin-views.business-settings.partials.third-party-scripts')
    <script src="{{ asset('public/assets/admin/js/view-pages/vendor/profile-index.js') }}"></script>
@endpush
