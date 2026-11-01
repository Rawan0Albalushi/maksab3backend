@extends('layouts.admin.app')
@section('title',translate('Employee edit'))
@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('employee_list')
active
@endsection

@section('content')
<div class="content container-fluid">
    <div class="page-header">
        <h1 class="page-header-title">
            <span class="page-header-icon">
                <img src="{{asset('public/assets/admin/img/edit.png')}}" class="w--26" alt="">
            </span>
            <span>
                {{translate('messages.Employee update')}}
            </span>
        </h1>
        <p class="page-header-desc">{{ translate('Change this employee\'s details, their role or whether they can still sign in.') }}</p>
    </div>
    <form action="{{route('admin.users.employee.update',[$employee['id']])}}" method="post" enctype="multipart/form-data" class="js-validate">
        @csrf

        <div class="card">
            <div class="card-header">
                <h5 class="card-title">
                    <span class="card-header-icon">
                        <i class="tio-user"></i>
                    </span>
                    <span>{{translate('General information')}}</span>
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="input-label qcont" for="name">{{translate('First name')}} <span class="form-label-secondary text-danger"
                            data-toggle="tooltip" data-placement="right"
                            data-original-title="{{ translate('messages.Required.')}}"> *
                            </span> </label>
                                <input type="text" name="f_name" value="{{$employee['f_name']}}" class="form-control" id="f_name"
                                        placeholder="{{translate('First name')}}" required>
                            </div>
                            <div class="col-sm-6">
                                <label class="input-label qcont" for="name">{{translate('Last name')}} <span class="form-label-secondary text-danger"
                            data-toggle="tooltip" data-placement="right"
                            data-original-title="{{ translate('messages.Required.')}}"> *
                            </span> </label>
                                <input type="text" name="l_name" value="{{$employee['l_name']}}" class="form-control" id="l_name"
                                        placeholder="{{translate('Last name')}}">
                            </div>
                            <div class="col-sm-6">
                                <div>
                                    <label class="input-label" for="title">{{translate('messages.Zone')}} <span class="form-label-secondary text-danger"
                            data-toggle="tooltip" data-placement="right"
                            data-original-title="{{ translate('messages.Required.')}}"> *
                            </span> </label>
                                    <select name="zone_id" id="zone_id" class="form-control js-select2-custom">
                                        @if(!auth('admin')?->user()?->zone_id)
                                            <option value="" {{!$employee->zone_id?'selected':''}}>{{translate('All')}}</option>
                                        @endif
                                        @foreach($zones as $zone)
                                            <option value="{{$zone['id']}}" {{$employee->zone_id == $zone->id?'selected':''}}>{{$zone['name']}}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div>
                                    <label class="input-label qcont" for="name">{{translate('messages.Role')}} <span class="form-label-secondary text-danger"
                            data-toggle="tooltip" data-placement="right"
                            data-original-title="{{ translate('messages.Required.')}}"> *
                            </span> </label>
                                    <select class="form-control js-select2-custom w-100" name="role_id" id="role_id">
                                        <option value="" selected disabled>{{translate('Select role')}}</option>
                                        @foreach($roles as $role)
                                        <option value="{{$role->id}}" {{$role['id']==$employee['role_id']?'selected':''}}>{{$role->name}}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <label class="input-label qcont" for="name">{{translate('Phone')}} <span class="form-label-secondary text-danger"
                            data-toggle="tooltip" data-placement="right"
                            data-original-title="{{ translate('messages.Required.')}}"> *
                            </span> </label>
                                <input type="tel" value="{{$employee['phone']}}" required name="phone" class="form-control" id="phone"
                                        placeholder="{{ translate('messages.Ex') }}: +88017********">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="h-100 d-flex flex-column">


                            <div class="text-center input-label qcont py-3 my-auto">
                                {{ translate('messages.Employee image') }} <small  class="text-danger"> ( {{ translate('messages.Ratio') }} 1:1 )</small>

                            </div>
                            <div class="text-center py-3 my-auto">
                                <img class="img--100 onerror-image" id="viewer"
                                data-onerror-image="{{asset('/public/assets/admin/img/admin.png')}}"
                                src="{{ $employee['image_full_url'] }}" alt="Employee thumbnail"/>
                            </div>
                            <div class="custom-file">
                                <input type="file" name="image" id="customFileUpload" class="custom-file-input"
                                    accept=".webp, .jpg, .png, .jpeg, .gif, .bmp, .tif, .tiff|image/*">
                                <span class="custom-file-label">{{translate('Choose file')}}</span>
                            </div>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title">
                    <span class="card-header-icon">
                        <i class="tio-user"></i>
                    </span>
                    <span>{{translate('Account information')}}</span>
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <label class="input-label qcont" for="name">{{translate('messages.email')}} <span class="form-label-secondary text-danger"
                            data-toggle="tooltip" data-placement="right"
                            data-original-title="{{ translate('messages.Required.')}}"> *
                            </span> </label>
                        <input type="email" value="{{$employee['email']}}" name="email" class="form-control" id="email"
                                placeholder="{{ translate('messages.Ex') }}: ex@gmail.com">
                    </div>
                    <div class="col-md-4">
                        <div class="js-form-message form-group mb-0">
                            <label class="input-label" for="signupSrPassword">{{translate('messages.password')}}<span class="form-label-secondary" data-toggle="tooltip" data-placement="top"
        data-original-title="{{ translate('Use at least one uppercase letter, one lowercase letter, one number and one symbol.') }} {{ translate('Minimum characters') }}: 8"><img src="{{ asset('/public/assets/admin/img/info-circle.svg') }}" alt="{{ translate('Use at least one uppercase letter, one lowercase letter, one number and one symbol.') }} {{ translate('Minimum characters') }}: 8"></span></label>

                            <div class="input-group input-group-merge">
                                <input type="password" class="js-toggle-password form-control" name="password" id="signupSrPassword" pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}" title="{{ translate('Use at least one uppercase letter, one lowercase letter, one number and one symbol.') }} {{ translate('Minimum characters') }}: 8"
                                placeholder="{{ translate('Minimum characters') }}: 8+"
                                aria-label="8+ characters required"
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
                            <label class="input-label" for="signupSrConfirmPassword">{{translate('Confirm password')}}  </label>
                            <div class="input-group input-group-merge">
                            <input type="password" class="js-toggle-password form-control" name="confirmPassword" id="signupSrConfirmPassword" pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}" title="{{ translate('Use at least one uppercase letter, one lowercase letter, one number and one symbol.') }} {{ translate('Minimum characters') }}: 8"
                            placeholder="{{ translate('Minimum characters') }}: 8+"
                            aria-label="8+ characters required"
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
            <button type="reset" id="reset_btn" class="btn btn--reset"><i class="tio-refresh"></i> {{translate('messages.Reset')}}</button>
            <button type="submit" class="btn btn--primary"><i class="tio-save"></i> {{translate('Update')}}</button>
        </div>
    </form>
</div>
@endsection

@push('script_2')
    <script src="{{asset('public/assets/admin')}}/js/view-pages/employee.js"></script>
<script>
    "use strict";
    $(document).on('ready', function () {
        $('.js-toggle-password').each(function () {
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
        $('#reset_btn').click(function(){
            $('#viewer').attr('src', "{{ $employee->image_full_url }}");
            $('#customFileUpload').val(null);
            $('#zone_id').val("{{ $employee->zone_id  }}").trigger('change');
            $('#role_id').val("{{ $employee['role_id'] }}").trigger('change');
        })
    </script>
@endpush
