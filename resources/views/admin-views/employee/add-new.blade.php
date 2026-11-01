@extends('layouts.admin.app')
@section('title', translate('Employee add'))
@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{ asset('public/assets/admin/img/role.png') }}" class="w--26" alt="">
                </span>
                <span>
                    {{ translate('messages.Add new employee') }}
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('Add someone to your team and pick the role that decides what they can open.') }}</p>
        </div>
        <form action="{{ route('admin.users.employee.store') }}" method="post" enctype="multipart/form-data"
            class="js-validate">
            @csrf
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title">
                        <span class="card-header-icon">
                            <i class="tio-user"></i>
                        </span>
                        <span>{{ translate('General information') }}</span>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <label class="input-label qcont"
                                        for="fname">{{ translate('First name') }}<span
                                            class="form-label-secondary text-danger" data-toggle="tooltip"
                                            data-placement="right"
                                            data-original-title="{{ translate('messages.Required.') }}"> *
                                        </span>
                                    </label>
                                    <input type="text" name="f_name" class="form-control" id="fname"
                                        placeholder="{{ translate('First name') }}" value="{{ old('f_name') }}"
                                        required>
                                </div>
                                <div class="col-sm-6">
                                    <label class="input-label qcont"
                                        for="lname">{{ translate('Last name') }}<span
                                            class="form-label-secondary text-danger" data-toggle="tooltip"
                                            data-placement="right"
                                            data-original-title="{{ translate('messages.Required.') }}"> *
                                        </span>
                                    </label>
                                    <input type="text" name="l_name" class="form-control" id="lname"
                                        value="{{ old('l_name') }}" placeholder="{{ translate('Last name') }}"
                                        value="{{ old('name') }}">
                                </div>
                                <div class="col-sm-6">
                                    <div>
                                        <label class="input-label" for="title">{{ translate('messages.Zone') }}<span
                                                class="form-label-secondary text-danger" data-toggle="tooltip"
                                                data-placement="right"
                                                data-original-title="{{ translate('messages.Required.') }}"> *
                                            </span>
                                        </label>
                                        <select name="zone_id" id="zone_id" class="form-control js-select2-custom">
                                            @if (!auth('admin')?->user()?->zone_id)
                                                <option value="" {{ !isset($e->zone_id) ? 'selected' : '' }}>
                                                    {{ translate('All') }}</option>
                                            @endif
                                            @foreach ($zones as $zone)
                                                <option value="{{ $zone['id'] }}">{{ $zone['name'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div>
                                        <label class="input-label qcont"
                                            for="role_id">{{ translate('messages.Role') }}<span
                                                class="form-label-secondary text-danger" data-toggle="tooltip"
                                                data-placement="right"
                                                data-original-title="{{ translate('messages.Required.') }}"> *
                                            </span>
                                        </label>
                                        <select class="form-control js-select2-custom w-100" name="role_id" id="role_id"
                                            required>
                                            <option value="" selected disabled>
                                                {{ translate('Select role') }}</option>
                                            @foreach ($roles as $role)
                                                <option value="{{ $role->id }}">{{ $role->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="input-label qcont" for="phone">{{ translate('Phone') }}<span
                                            class="form-label-secondary text-danger" data-toggle="tooltip"
                                            data-placement="right"
                                            data-original-title="{{ translate('messages.Required.') }}"> *
                                        </span>
                                    </label>
                                    <input type="tel" name="phone" value="{{ old('phone') }}" class="form-control"
                                        id="phone" placeholder="{{ translate('messages.Ex') }}: +88017********"
                                        required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="bg-light2 rounded h-100 d-center">
                                <div class="d-flex flex-column h-100 mt-5">
                                    <label class="text-center">{{ translate('messages.Employee image') }} <small
                                            class="text-danger">* ( {{ translate('messages.Ratio') }} 1:1 )</small>
                                    </label>
                                    <div class="mx-auto text-center">
                                        @include('admin-views.partials._image-uploader', [
                                            'id' => 'image-input',
                                            'name' => 'image',
                                            'ratio' => '1:1',
                                            'isRequired' => true,
                                            'existingImage' => '',
                                            'imageExtension' => IMAGE_EXTENSION,
                                            'imageFormat' => IMAGE_FORMAT,
                                            'maxSize' => MAX_FILE_SIZE,
                                        ])
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">
                        <span class="card-header-icon">
                            <i class="tio-user"></i>
                        </span>
                        <span>{{ translate('Account information') }}</span>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="input-label qcont" for="email">{{ translate('messages.email') }} <span
                                    class="form-label-secondary text-danger" data-toggle="tooltip" data-placement="right"
                                    data-original-title="{{ translate('messages.Required.') }}"> *
                                </span>
                            </label>
                            <input type="email" name="email" value="{{ old('email') }}" class="form-control"
                                id="email" placeholder="{{ translate('messages.Ex') }}: ex@gmail.com" required>
                        </div>
                        <div class="col-md-4">
                            <div class="js-form-message form-group mb-0">
                                <label class="input-label"
                                    for="signupSrPassword">{{ translate('messages.password') }}<span
                                        class="form-label-secondary" data-toggle="tooltip" data-placement="top"
                                        data-original-title="{{ translate('Use at least one uppercase letter, one lowercase letter, one number and one symbol.') }} {{ translate('Minimum characters') }}: 8"><img
                                            src="{{ asset('/public/assets/admin/img/info-circle.svg') }}"
                                            alt="{{ translate('Use at least one uppercase letter, one lowercase letter, one number and one symbol.') }} {{ translate('Minimum characters') }}: 8"></span>
                                    <span class="form-label-secondary text-danger" data-toggle="tooltip"
                                        data-placement="top" data-original-title="{{ translate('messages.Required.') }}">
                                        *
                                    </span> </label>

                                <div class="input-group input-group-merge">
                                    <input type="password" class="js-toggle-password form-control" name="password"
                                        id="signupSrPassword" pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}"
                                        title="{{ translate('Use at least one uppercase letter, one lowercase letter, one number and one symbol.') }} {{ translate('Minimum characters') }}: 8"
                                        placeholder="{{ translate('Minimum characters') }}: 8+"
                                        aria-label="8+ characters required" required
                                        data-msg="Your password is invalid. Please try again."
                                        data-hs-toggle-password-options='{
                                "target": [".js-toggle-password-target-1", ".js-toggle-password-target-2"],
                                "defaultClass": "tio-hidden-outlined",
                                "showClass": "tio-visible-outlined",
                                "classChangeTarget": ".js-toggle-passowrd-show-icon-1"
                                }'>
                                    <div class="js-toggle-password-target-1 input-group-append">
                                        <a class="input-group-text" href="javascript:">
                                            <i class="js-toggle-passowrd-show-icon-1 tio-visible-outlined"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="js-form-message form-group mb-0">
                                <label class="input-label"
                                    for="signupSrConfirmPassword">{{ translate('Confirm password') }} <span
                                        class="form-label-secondary text-danger" data-toggle="tooltip"
                                        data-placement="right"
                                        data-original-title="{{ translate('messages.Required.') }}"> *
                                    </span> </label>
                                <div class="input-group input-group-merge">
                                    <input type="password" class="js-toggle-password form-control" name="confirmPassword"
                                        id="signupSrConfirmPassword" pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}"
                                        title="{{ translate('Use at least one uppercase letter, one lowercase letter, one number and one symbol.') }} {{ translate('Minimum characters') }}: 8"
                                        placeholder="{{ translate('Minimum characters') }}: 8+"
                                        aria-label="8+ characters required" required
                                        data-msg="Password does not match the confirm password."
                                        data-hs-toggle-password-options='{
                                    "target": [".js-toggle-password-target-1", ".js-toggle-password-target-2"],
                                    "defaultClass": "tio-hidden-outlined",
                                    "showClass": "tio-visible-outlined",
                                    "classChangeTarget": ".js-toggle-passowrd-show-icon-2"
                                    }'>
                                    <div class="js-toggle-password-target-2 input-group-append">
                                        <a class="input-group-text" href="javascript:">
                                            <i class="js-toggle-passowrd-show-icon-2 tio-visible-outlined"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="btn--container justify-content-end mt-4">
                <button type="reset" id="reset_btn" class="btn btn--reset"><i class="tio-refresh"></i> {{ translate('messages.Reset') }}</button>
                <button type="submit" class="btn btn--primary"><i class="tio-checkmark-circle-outlined"></i> {{ translate('messages.Submit') }}</button>
            </div>
        </form>
    </div>
@endsection

@push('script_2')
    <script src="{{ asset('public/assets/admin') }}/js/view-pages/employee.js"></script>
    <script>
        "use strict";
        $(document).on('ready', function() {
            $('.js-toggle-password').each(function() {
                new HSTogglePassword(this).init()
            });


            $('.js-validate').each(function() {
                $.HSCore.components.HSValidation.init($(this), {
                    rules: {
                        confirmPassword: {
                            equalTo: '#signupSrPassword'
                        }
                    }
                });
            });
        });
        $('#reset_btn').click(function() {
            // Image preview reset is handled globally for every .upload-file_custom on
            // button[type=reset] click -- see upload-single-image.js.
            $('#zone_id').val(null).trigger('change');
            $('#role_id').val(null).trigger('change');
        })
    </script>
@endpush
