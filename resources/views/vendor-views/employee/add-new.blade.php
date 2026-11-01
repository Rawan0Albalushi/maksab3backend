@extends('layouts.vendor.app')
@section('title', translate('Employee add'))
@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/third-party-setup.css') }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/view-pages/employee-form.css') }}">
@endpush

@php($selected_role = (string) old('role_id'))
@php($can_manage_roles = \App\CentralLogics\Helpers::employee_module_permission_check('role'))
@php($role_permissions = $rls->mapWithKeys(fn ($role) => [$role->id => array_values(array_intersect(array_keys($permission_labels), (array) json_decode($role->modules)))]))
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
                <span class="page-header-icon">
                    <img src="{{ asset('public/assets/admin/img/role.png') }}" class="w--26" alt="">
                </span>
                <span>{{ translate('messages.Add new employee') }}</span>
            </h1>
            <p class="page-header-desc">{{ translate('Add someone to your team and pick the role that decides what they can open.') }}</p>
        </div>

        <div class="tps empf">
            <form action="{{ route('vendor.employee.add-new') }}" method="post" enctype="multipart/form-data"
                class="custom-validation" id="employee_form">
                @csrf
                <div class="row g-3">
                    <div class="col-xl-8">
                        <div class="tps-card">
                            <div class="tps-card__body">
                                <div class="tps-group">
                                    <p class="tps-group__label">{{ translate('Personal details') }}</p>
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
                                                        value="{{ old('f_name') }}" maxlength="100" autocomplete="off"
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
                                                        value="{{ old('l_name') }}" maxlength="100" autocomplete="off"
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
                                                        value="{{ old('email') }}" maxlength="100" autocomplete="off"
                                                        placeholder="{{ translate('messages.Ex') }}: ex@gmail.com" required>
                                                </div>
                                                <small class="tps-field__hint">{{ translate('They sign in with this email and the password below.') }}</small>
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
                                                        value="{{ old('phone') }}"
                                                        placeholder="{{ translate('messages.Ex') }}: +88017********" required>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="tps-group">
                                    <p class="tps-group__label">{{ translate('Sign-in details') }}</p>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="tps-field">
                                                <div class="error-wrapper">
                                                    <label class="tps-field__label" for="password">
                                                        {{ translate('messages.password') }}
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
                                                        <input type="password" name="confirmPassword" id="confirm_password" class="form-control"
                                                            autocomplete="new-password"
                                                            placeholder="{{ translate('Minimum characters') }}: 8" required>
                                                        <button type="button" class="tps-input-action tps-toggle-secret" data-target="#confirm_password"
                                                            aria-label="{{ translate('Show value') }}"><i class="tio-visible"></i></button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <ul class="empf-rules" id="password_rules">
                                                @foreach ($password_rules as $rule => $label)
                                                    <li data-rule="{{ $rule }}">
                                                        <i class="tio-checkmark-circle empf-rules__met"></i>
                                                        <i class="tio-circle-outlined empf-rules__unmet"></i>
                                                        {{ $label }}
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    </div>
                                </div>

                                <div class="tps-group">
                                    <p class="tps-group__label">{{ translate('Role and access') }}</p>
                                    @if ($rls->isEmpty())
                                        <div class="tps-note tps-note--warn">
                                            <i class="tio-warning-outlined"></i>
                                            <div>
                                                <p>{{ translate('You have no roles yet. Create one first, then come back to add this employee.') }}</p>
                                                @if ($can_manage_roles)
                                                    <a href="{{ route('vendor.custom-role.create') }}" class="btn btn-sm btn--primary mt-2">
                                                        <i class="tio-add"></i> {{ translate('Add new role') }}
                                                    </a>
                                                @endif
                                            </div>
                                        </div>
                                    @else
                                        <div class="tps-field">
                                            <div class="error-wrapper">
                                                <label class="tps-field__label" for="role_id">
                                                    {{ translate('messages.Role') }}
                                                    <span class="tps-req" data-toggle="tooltip" data-placement="right"
                                                        data-original-title="{{ translate('messages.Required.') }}">*</span>
                                                </label>
                                                <select name="role_id" id="role_id" class="custom-select js-select2-custom" required>
                                                    <option value="" disabled {{ $selected_role === '' ? 'selected' : '' }}>{{ translate('Select role') }}</option>
                                                    @foreach ($rls as $role)
                                                        <option value="{{ $role->id }}" {{ $selected_role === (string) $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>

                                        <div class="empf-access" data-role="" @if ($selected_role !== '') hidden @endif>
                                            <p class="empf-access__empty">
                                                <i class="tio-lock-outlined"></i>
                                                {{ translate('Pick a role to see what they will be able to open.') }}
                                            </p>
                                        </div>
                                        @foreach ($rls as $role)
                                            @php($permissions = $role_permissions[$role->id])
                                            <div class="empf-access" data-role="{{ $role->id }}" @if ($selected_role !== (string) $role->id) hidden @endif>
                                                <div class="empf-access__head">
                                                    <span class="empf-access__title">{{ translate('What this role can open') }}</span>
                                                    <span class="empf-access__count">{{ translate('Permissions') }}: {{ count($permissions) }}/{{ $permission_total }}</span>
                                                </div>
                                                @if ($permissions)
                                                    <div class="empf-access__chips">
                                                        @foreach ($permissions as $key)
                                                            <span class="empf-chip">{{ $permission_labels[$key] }}</span>
                                                        @endforeach
                                                    </div>
                                                @else
                                                    <p class="empf-access__empty">{{ translate('An employee with no permission can still sign in, but every section stays locked.') }}</p>
                                                @endif
                                                @if ($can_manage_roles)
                                                    <a class="empf-access__edit" href="{{ route('vendor.custom-role.edit', [$role->id]) }}" target="_blank" rel="noopener">
                                                        {{ translate('Edit role') }} <i class="tio-open-in-new"></i>
                                                    </a>
                                                @endif
                                            </div>
                                        @endforeach
                                    @endif
                                </div>
                            </div>
                            <div class="tps-card__foot">
                                <span class="tps-foot-note">{{ translate('They can sign in as soon as you save.') }}</span>
                                <button type="reset" class="btn btn--reset"><i class="tio-refresh"></i> {{ translate('messages.Reset') }}</button>
                                <button type="submit" class="btn btn--primary" @disabled($rls->isEmpty())><i class="tio-add-circle"></i> {{ translate('Add employee') }}</button>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4">
                        <div class="empf-aside">
                            <div class="tps-card">
                                <div class="tps-card__body empf-identity"
                                    data-empty-name="{{ translate('New employee') }}"
                                    data-empty-role="{{ translate('No role picked') }}"
                                    data-empty-value="{{ translate('Not set yet') }}">
                                    <p class="tps-group__label">{{ translate('How they appear on your employee list') }}</p>
                                    <div class="error-wrapper">
                                        <div class="empf-photo">
                                            <img class="empf-photo__img" alt="">
                                            <span class="empf-photo__empty">
                                                <i class="tio-photo-camera"></i>
                                                {{ translate('Upload photo') }}
                                            </span>
                                            <span class="empf-photo__change">{{ translate('Change photo') }}</span>
                                            <input type="file" name="image" id="employee_image"
                                                accept=".webp, .jpg, .png, .jpeg, .gif, .bmp, .tif, .tiff|image/*"
                                                aria-label="{{ translate('Profile photo') }}" required>
                                        </div>
                                    </div>
                                    <small class="empf-photo-hint">
                                        {{ translate('A square photo works best.') }}<br>
                                        JPG, PNG, WEBP · ≤ 2 MB
                                    </small>

                                    <p class="empf-name is-empty" data-preview="name">{{ translate('New employee') }}</p>
                                    <span class="empf-role is-empty" data-preview="role">{{ translate('No role picked') }}</span>

                                    <ul class="empf-contact">
                                        <li><i class="tio-email-outlined"></i><span class="is-empty" data-preview="email">{{ translate('Not set yet') }}</span></li>
                                        <li><i class="tio-call"></i><span class="is-empty" data-preview="phone">{{ translate('Not set yet') }}</span></li>
                                    </ul>
                                </div>
                            </div>

                            @if ($login_url)
                                <div class="tps-card">
                                    <div class="tps-card__body">
                                        <p class="tps-group__label">{{ translate('Sign-in link') }}</p>
                                        <div class="tps-readonly">
                                            <span class="tps-readonly__value" id="employee_login_url">{{ $login_url }}</span>
                                            <button type="button" class="tps-readonly__copy tps-copy" data-target="#employee_login_url">
                                                <i class="tio-copy"></i> {{ translate('Copy') }}
                                            </button>
                                        </div>
                                        <small class="tps-field__hint">{{ translate('Send it to them with their email and password once you save.') }}</small>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('script_2')
    @include('admin-views.business-settings.partials.third-party-scripts')
    <script src="{{ asset('public/assets/admin/js/view-pages/employee-form.js') }}"></script>
@endpush
