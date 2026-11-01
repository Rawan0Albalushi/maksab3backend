@extends('layouts.admin.app')

@section('title', translate('messages.Profile settings'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/view-pages/admin-profile.css') }}">
@endpush

@section('content')
    @php
        $admin = auth('admin')->user();
        $adminImage = $admin->loadMissing('storage')->toArray()['image_full_url'];
        $fullName = trim(($admin->f_name ?? '') . ' ' . ($admin->l_name ?? ''));
        $roleName = $admin->role_id == 1 ? translate('messages.Master Admin') : ($admin->role?->name ?? translate('messages.admin'));
        $isDemo = getEnvMode() == 'demo';
        $passwordHint = translate('messages.Use at least one uppercase letter, one lowercase letter, one number and one symbol.') . ' ' . translate('messages.Minimum characters') . ': 8';
        $strengthLabels = [
            translate('messages.Enter a password'),
            translate('messages.Very weak'),
            translate('messages.Weak'),
            translate('messages.Fair'),
            translate('messages.Good'),
            translate('messages.Strong'),
        ];
    @endphp

    <div class="content container-fluid admin-profile-page" id="adminProfilePage">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-3 mb-sm-0">
                    <h1 class="page-header-title">
                        <i class="tio-user-outlined"></i>
                        <span>{{ translate('messages.Profile settings') }}</span>
                    </h1>
                    <p class="page-header-desc">{{ translate('Your own name, photograph, email and password.') }}</p>
                </div>

                <div class="col-sm-auto">
                    <a class="btn btn-primary ap-btn" href="{{ route('admin.dashboard') }}">
                        <i class="tio-home"></i> {{ translate('Dashboard') }}
                    </a>
                </div>
            </div>
        </div>

        <div class="ap-tabs" role="tablist">
            <button type="button" class="ap-tab is-active" data-ap-tab="generalDiv">
                <i class="tio-user-outlined"></i> {{ translate('Basic information') }}
            </button>
            <button type="button" class="ap-tab" data-ap-tab="passwordDiv">
                <i class="tio-lock-outlined"></i> {{ translate('messages.password') }}
            </button>
        </div>

        {{-- ------------------------------------------------------------------ --}}
        {{-- Identity + basic information. Both live in one form so the avatar   --}}
        {{-- is uploaded together with the name, email and phone.                --}}
        {{-- ------------------------------------------------------------------ --}}
        <form action="{{ !$isDemo ? route('admin.settings') : 'javascript:' }}" method="post"
            enctype="multipart/form-data" id="admin-settings-form">
            @csrf

            <div class="row" id="generalDiv" data-ap-section="generalDiv">
                <div class="col-xl-4 col-lg-5 mb-3">
                    <div class="ap-card ap-identity h-100">
                        <div class="ap-hero__cover"></div>

                        <div class="ap-hero__body">
                            <div class="ap-avatar">
                                <img id="adminAvatarPreview" class="ap-avatar__img onerror-image" src="{{ $adminImage }}"
                                    data-original-src="{{ $adminImage }}"
                                    data-onerror-image="{{ asset('public/assets/admin/img/160x160/img1.jpg') }}"
                                    alt="{{ $fullName ?: translate('messages.admin') }}">

                                <input type="file" name="image" class="ap-avatar__input" id="adminAvatarInput"
                                    accept=".webp, .jpg, .png, .jpeg, .gif, .bmp, .tif, .tiff|image/*">
                                <label class="ap-avatar__trigger" for="adminAvatarInput"
                                    title="{{ translate('messages.Change profile photo') }}">
                                    <i class="tio-photo-camera"></i>
                                    <span class="sr-only">{{ translate('messages.Change profile photo') }}</span>
                                </label>
                            </div>

                            <h2 class="ap-hero__name">{{ $fullName ?: translate('messages.admin') }}</h2>
                            <span class="ap-role"><i class="tio-security-on-outlined"></i> {{ $roleName }}</span>

                            <p class="ap-hero__hint">JPG, PNG, WEBP &mdash; 1:1 ratio</p>
                        </div>

                        <ul class="ap-facts">
                            <li>
                                <i class="tio-email-outlined"></i>
                                <span class="ap-facts__key">{{ translate('messages.email') }}</span>
                                <span class="ap-facts__value" title="{{ $admin->email }}">{{ $admin->email }}</span>
                            </li>
                            <li>
                                <i class="tio-call"></i>
                                <span class="ap-facts__key">{{ translate('Phone') }}</span>
                                <span class="ap-facts__value">{{ $admin->phone ?: '-' }}</span>
                            </li>
                            <li>
                                <i class="tio-calendar-month"></i>
                                <span class="ap-facts__key">{{ translate('messages.Joined') }}</span>
                                <span class="ap-facts__value">
                                    {{ $admin->created_at ? $admin->created_at->format('d M Y') : '-' }}
                                </span>
                            </li>
                            <li>
                                <i class="tio-history"></i>
                                <span class="ap-facts__key">{{ translate('messages.Last updated') }}</span>
                                <span class="ap-facts__value">
                                    {{ $admin->updated_at ? $admin->updated_at->format('d M Y') : '-' }}
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="col-xl-8 col-lg-7 mb-3">
                    <div class="ap-card h-100">
                        <div class="ap-card__head">
                            <div>
                                <h3 class="ap-card__title">
                                    <i class="tio-user-outlined"></i> {{ translate('Basic information') }}
                                </h3>
                                <p class="ap-card__subtitle">
                                    {{ translate('messages.This is how your name and contact details appear across the panel') }}
                                </p>
                            </div>
                        </div>

                        <div class="ap-card__body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="ap-field">
                                        <label class="ap-label" for="firstNameLabel">
                                            {{ translate('First name') }}
                                            <i class="tio-help-outlined" data-toggle="tooltip" data-placement="top"
                                                title="{{ translate('Display name') }}"></i>
                                        </label>
                                        <div class="ap-input-group">
                                            <i class="tio-user-outlined"></i>
                                            <input type="text" class="form-control" name="f_name" id="firstNameLabel"
                                                placeholder="{{ translate('messages.Your first name') }}"
                                                aria-label="{{ translate('messages.Your first name') }}"
                                                value="{{ $admin->f_name }}">
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="ap-field">
                                        <label class="ap-label" for="lastNameLabel">
                                            {{ translate('Last name') }}
                                        </label>
                                        <div class="ap-input-group">
                                            <i class="tio-user-outlined"></i>
                                            <input type="text" class="form-control" name="l_name" id="lastNameLabel"
                                                placeholder="{{ translate('messages.Your last name') }}"
                                                aria-label="{{ translate('messages.Your last name') }}"
                                                value="{{ $admin->l_name }}">
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="ap-field">
                                        <label class="ap-label"
                                            for="newEmailLabel">{{ translate('messages.email') }}</label>
                                        <div class="ap-input-group">
                                            <i class="tio-email-outlined"></i>
                                            <input type="email" class="form-control" name="email" id="newEmailLabel"
                                                value="{{ $admin->email }}"
                                                placeholder="{{ translate('messages.Enter new email address') }}"
                                                aria-label="{{ translate('messages.Enter new email address') }}">
                                        </div>
                                        <p class="ap-help">
                                            {{ translate('messages.Changing the email signs you out of other sessions') }}
                                        </p>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="ap-field">
                                        <label class="ap-label" for="phoneLabel">
                                            {{ translate('Phone') }}
                                        </label>
                                        <div class="ap-input-group">
                                            <i class="tio-call"></i>
                                            <input type="text" class="js-masked-input form-control" name="phone"
                                                id="phoneLabel" placeholder="+x(xxx)xxx-xx-xx"
                                                aria-label="+(xxx)xx-xxx-xxxxx" value="{{ $admin->phone }}"
                                                data-hs-mask-options='{
                                                   "template": "+(880)00-000-00000"
                                                 }'>
                                        </div>
                                        <p class="ap-help">
                                            {{ translate('messages.Used for account related notifications') }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="ap-card__foot">
                            <p class="ap-foot-note">
                                {{ translate('messages.Fields left unchanged are saved as they are') }}
                            </p>
                            <div class="ap-foot-actions d-flex flex-wrap gap-2">
                                <button type="button" class="btn ap-btn ap-btn-ghost" data-ap-reset>
                                    <i class="tio-refresh"></i> {{ translate('messages.Reset') }}
                                </button>
                                <button type="button" data-id="admin-settings-form"
                                    data-message="{{ translate('Want to update admin information?') }}"
                                    class="btn btn-primary ap-btn {{ !$isDemo ? 'form-alert' : 'call-demo' }}">
                                    <i class="tio-save"></i> {{ translate('messages.Save') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        {{-- ------------------------------------------------------------------ --}}
        {{-- Password --}}
        {{-- ------------------------------------------------------------------ --}}
        <div class="row" id="passwordDiv" data-ap-section="passwordDiv">
            <div class="col-xl-4 col-lg-5 mb-3">
                <div class="ap-card ap-guide h-100">
                    <div class="ap-card__head">
                        <div>
                            <h3 class="ap-card__title">
                                <i class="tio-security-on-outlined"></i>
                                {{ translate('messages.Password requirements') }}
                            </h3>
                            <p class="ap-card__subtitle">
                                {{ translate('messages.Every item has to be ticked before the password can be saved') }}
                            </p>
                        </div>
                    </div>

                    <div class="ap-card__body">
                        <ul class="ap-rules">
                            <li data-ap-rule="length">
                                <i class="tio-circle-outlined"></i>
                                At least 8 characters
                            </li>
                            <li data-ap-rule="lowercase">
                                <i class="tio-circle-outlined"></i>
                                {{ translate('messages.One lowercase letter') }}
                            </li>
                            <li data-ap-rule="uppercase">
                                <i class="tio-circle-outlined"></i>
                                {{ translate('messages.One uppercase letter') }}
                            </li>
                            <li data-ap-rule="number">
                                <i class="tio-circle-outlined"></i>
                                {{ translate('messages.One number') }}
                            </li>
                            <li data-ap-rule="symbol">
                                <i class="tio-circle-outlined"></i>
                                {{ translate('messages.One symbol') }}
                            </li>
                        </ul>

                        <div class="ap-note">
                            <i class="tio-info-outined"></i>
                            <span>{{ translate('messages.Use a password you do not reuse anywhere else') }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-8 col-lg-7 mb-3">
                <div class="ap-card h-100">
                    <div class="ap-card__head">
                        <div>
                            <h3 class="ap-card__title">
                                <i class="tio-lock-outlined"></i> {{ translate('messages.Change your password') }}
                            </h3>
                            <p class="ap-card__subtitle">
                                {{ translate('messages.Saving a new password signs you out of every other device') }}
                            </p>
                        </div>
                    </div>

                    <form id="changePasswordForm"
                        action="{{ !$isDemo ? route('admin.settings-password') : 'javascript:' }}" method="post"
                        enctype="multipart/form-data">
                        @csrf

                        <div class="ap-card__body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="ap-field">
                                        <label class="ap-label" for="adminNewPassword">
                                            {{ translate('New password') }}
                                            <i class="tio-help-outlined" data-toggle="tooltip" data-placement="top"
                                                title="{{ $passwordHint }}"></i>
                                        </label>
                                        <div class="ap-input-group ap-input-group--password">
                                            <i class="tio-lock-outlined"></i>
                                            <input type="password" class="form-control" name="password"
                                                id="adminNewPassword"
                                                placeholder="{{ translate('Minimum characters') }}: 8+"
                                                aria-label="{{ translate('New password') }}"
                                                autocomplete="new-password" required>
                                            <button type="button" class="ap-eye" data-ap-toggle="adminNewPassword"
                                                data-show-text="{{ translate('messages.Show password') }}"
                                                data-hide-text="{{ translate('messages.Hide password') }}"
                                                aria-label="{{ translate('messages.Show password') }}">
                                                <i class="tio-visible-outlined"></i>
                                            </button>
                                        </div>

                                        <div class="ap-strength" id="adminPasswordStrength" data-level="0"
                                            data-labels="{{ json_encode($strengthLabels) }}">
                                            <div class="ap-strength__bar">
                                                <span></span><span></span><span></span><span></span><span></span>
                                            </div>
                                            <div class="ap-strength__label" id="adminPasswordStrengthLabel">
                                                {{ translate('messages.Enter a password') }}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="ap-field">
                                        <label class="ap-label"
                                            for="adminConfirmPassword">{{ translate('Confirm password') }}</label>
                                        <div class="ap-input-group ap-input-group--password">
                                            <i class="tio-lock-outlined"></i>
                                            <input type="password" class="form-control" name="confirm_password"
                                                id="adminConfirmPassword"
                                                placeholder="{{ translate('Minimum characters') }}: 8+"
                                                aria-label="{{ translate('Confirm password') }}"
                                                autocomplete="new-password" required>
                                            <button type="button" class="ap-eye" data-ap-toggle="adminConfirmPassword"
                                                data-show-text="{{ translate('messages.Show password') }}"
                                                data-hide-text="{{ translate('messages.Hide password') }}"
                                                aria-label="{{ translate('messages.Show password') }}">
                                                <i class="tio-visible-outlined"></i>
                                            </button>
                                        </div>

                                        <span class="ap-match" id="adminPasswordMatch" aria-live="polite"
                                            data-ok-text="{{ translate('messages.Passwords match') }}"
                                            data-error-text="{{ translate('messages.Passwords do not match') }}">
                                            <i class="tio-clear-circle"></i>
                                            <span>{{ translate('messages.Passwords do not match') }}</span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="ap-card__foot">
                            <p class="ap-foot-note">
                                {{ translate('messages.You will need to sign in again with the new password') }}
                            </p>
                            <div class="ap-foot-actions d-flex flex-wrap gap-2">
                                {{-- Demo mode never submits, so the button stays clickable there to --}}
                                {{-- keep showing the "demo" notice; elsewhere it unlocks once every --}}
                                {{-- requirement is ticked. --}}
                                <button type="button" data-id="changePasswordForm"
                                    data-message="{{ translate('Want to update admin password?') }}"
                                    class="btn btn-primary ap-btn {{ !$isDemo ? 'form-alert disabled' : 'call-demo' }}"
                                    @if (!$isDemo) data-ap-password-submit disabled @endif>
                                    <i class="tio-save"></i> {{ translate('messages.Save') }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script_2')
    <script src="{{ asset('public/assets/admin/js/view-pages/admin-profile.js') }}"></script>
@endpush
