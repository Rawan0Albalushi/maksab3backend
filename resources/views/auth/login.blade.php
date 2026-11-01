<!DOCTYPE html>
<?php

    $log_email_succ = session()->get('log_email_succ');
?>

<html dir="{{ $site_direction }}" lang="{{ $locale }}" class="{{ $site_direction === 'rtl'?'active':'' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>{{translate('messages.login')}}</title>

    <link rel="shortcut icon" href="{{asset('public/favicon.ico')}}">

    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{asset('public/assets/admin')}}/css/vendor.min.css">
    <link rel="stylesheet" href="{{asset('public/assets/admin')}}/vendor/icon-set/style.css">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/button-icons.css') }}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/bootstrap.min.css')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/theme.minc619.css?v=1.0')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/style.css')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/form-controls.css')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/buttons.css')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin')}}/css/toastr.css">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/app-toast.css')}}">
</head>

<body>
<main id="content" role="main" class="main">
    <div class="auth-wrapper">
        <div class="auth-wrapper-left">
            <div class="auth-left-cont">
                <img class="onerror-image"  data-onerror-image="{{asset('/public/assets/admin/img/favicon.png')}}"
                src="{{\App\CentralLogics\Helpers::logoFullUrl()}}"  alt="public/img">
                <h2 class="title">{{translate('Your')}} <span class="d-block">{{translate('All service')}}</span> <strong class="text--039D55">{{translate('in one field')}}....</strong></h2>
            </div>
        </div>
        <div class="auth-wrapper-right">
            <label class="badge badge-soft-success __login-badge">
                {{translate('messages.Software version')}} : {{env('SOFTWARE_VERSION')}}
            </label>

            <div class="auth-wrapper-form">
                <div class="d-sm-none flex-grow-1 mb-2">
                    <img class="w-50px img-fluid" class="onerror-image"  data-onerror-image="{{asset('/public/assets/admin/img/favicon.png')}}"
                        src="{{\App\CentralLogics\Helpers::logoFullUrl()}}"  alt="public/img">
                </div>
                <form class="" action="{{route('login_post')}}" method="post" id="form-id">
                    @csrf
                    <input type="hidden" name="role" value="{{  $role ?? null }}">
                    <div class="auth-header">
                        <div class="mb-5">
                            <h2 class="title">{{ translate($role) }} {{translate('messages.login')}}</h2>
                            <div>{{translate('messages.Welcome back login to your panel') }}.</div>
                        </div>
                    </div>

                    <div class="js-form-message form-group">
                        <label class="input-label text-capitalize" for="signinSrEmail">{{translate('messages.Your email')}}</label>

                        <input type="email" class="form-control form-control-lg" name="email" id="signinSrEmail"
                                tabindex="1" placeholder="email@address.com" value="{{ $email ?? '' }}" aria-label="email@address.com"
                                required data-msg="{{ translate('Please enter a valid email address.') }}">
                    </div>

                    <div class="js-form-message form-group mb-2">
                        <label class="input-label" for="signupSrPassword" tabindex="0">
                            <span class="d-flex justify-content-between align-items-center">
                                {{translate('messages.password')}}
                            </span>
                        </label>

                        <div class="input-group input-group-merge">
                            <input type="password" class="js-toggle-password form-control form-control-lg"
                                    name="password" id="signupSrPassword" placeholder="{{ translate('Minimum characters') }}: 6+" value="{{ $password ?? '' }}"
                                    aria-label="{{ translate('Minimum characters') }}: 6+" required
                                    data-msg="{{translate('messages.Invalid password warning')}}"
                                    data-hs-toggle-password-options='{
                                                "target": "#changePassTarget",
                                    "defaultClass": "tio-hidden-outlined",
                                    "showClass": "tio-visible-outlined",
                                    "classChangeTarget": "#changePassIcon"
                                    }'>
                            <div id="changePassTarget" class="input-group-append">
                                <a class="input-group-text" href="javascript:">
                                    <i id="changePassIcon" class="tio-visible-outlined"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mt-5">
                        <div class="form-group">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="termsCheckbox" {{ $password ? 'checked' : '' }}
                                        name="remember">
                                <label class="custom-control-label text-muted" for="termsCheckbox">
                                    {{translate('messages.Remember me')}}
                                </label>
                            </div>
                        </div>
                        <div class="form-group" id="forget-password" style="display: {{ $role == 'admin' ? '' : 'none' }};">
                            <div class="custom-control">
                                <span type="button" data-toggle="modal" class="text-primary text-hover--primary" data-target="#forgetPassModal">{{ translate('Forgot password') }}?</span>
                            </div>
                        </div>
                        <div class="form-group" id="forget-password1" style="display: {{ $role == 'vendor' ? '' : 'none' }};">
                            <div class="custom-control">
                                <span type="button" data-toggle="modal" class="text-primary text-hover--primary" data-target="#forgetPassModal1">{{ translate('Forgot password') }}?</span>
                            </div>
                        </div>
                    </div>

                    @include('admin-views.partials._recaptcha')

                    <button type="submit" class="btn btn-lg btn-block btn--primary mt-xxl-3" id="signInBtn"><i class="tio-sign-in"></i> {{translate('messages.login')}}</button>
                </form>
                @if(getEnvMode() == 'demo')
                @if (isset($role) && $role == 'admin')
                <div class="auto-fill-data-copy">
                    <div class="d-flex flex-wrap align-items-center justify-content-between">
                        <div>
                            <span class="d-block"><strong>Email</strong> : admin@admin.com</span>
                            <span class="d-block"><strong>Password</strong> : 12345678</span>
                        </div>
                        <div>
                            <button class="btn action-btn btn--primary m-0 copy_cred"><i class="tio-copy"></i>
                            </button>
                        </div>
                    </div>
                </div>
                @endif
                @if (isset($role) && $role == 'vendor')
                <div class="auto-fill-data-copy">
                    <div class="d-flex flex-wrap align-items-center justify-content-between">
                        <div>
                            <span class="d-block"><strong>Email</strong> : test.restaurant@gmail.com</span>
                            <span class="d-block"><strong>Password</strong> : 12345678</span>
                        </div>
                        <div>
                            <button class="btn action-btn btn--primary m-0 copy_cred2"><i class="tio-copy"></i>
                            </button>
                        </div>
                    </div>
                </div>
                @endif
                @endif
            </div>

        </div>
    </div>
</main>
<div class="modal fade" id="forgetPassModal">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header justify-content-end">
        <span type="button" class="close-modal-icon" data-dismiss="modal">
            <i class="tio-clear"></i>
        </span>
      </div>
      <div class="modal-body">
        <div class="forget-pass-content">
            <img src="{{asset('/public/assets/admin/img/send-mail.svg')}}" alt="">
            <h4>
                {{ translate('Send mail to your email') }} ?
            </h4>
            <p>
                @php($masked_email = isset($role) && $role == 'admin' ? \App\Models\Admin::where('role_id', 1)->first()?->masked_email : '')
                {{ translate('A mail will be sent to your registered email with a link to change password') }}
                @if ($masked_email)
                    <br>{{ translate('Email') }}: {{ $masked_email }}
                @endif
            </p>
            <form action="{{ route('reset-password') }}" method="post">
                @csrf
                <button type="submit" class="btn btn-lg btn-block btn--primary mt-3">
                    <i class="tio-send"></i> {{ translate('Send mail') }}
                </button>
            </form>
        </div>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="forgetPassModal1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header justify-content-end">
        <span type="button" class="close-modal-icon" data-dismiss="modal">
            <i class="tio-clear"></i>
        </span>
      </div>
      <div class="modal-body">
        <div class="forget-pass-content">
            <img src="{{asset('/public/assets/admin/img/send-mail.svg')}}" alt="">
            <h4>
                {{ translate('Send mail to your email') }} ?
            </h4>
            <form class="" action="{{ route('vendor-reset-password') }}" method="post">
                @csrf

                <input type="email" name="email" id="" class="form-control" placeholder="{{ translate('messages.Please enter your registered email') }}" required>
                <button type="submit" class="btn btn-lg btn-block btn--primary mt-3"><i class="tio-send"></i> {{ translate('Send mail') }}</button>
            </form>
        </div>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="successMailModal">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header justify-content-end">
          <span type="button" class="close-modal-icon" data-dismiss="modal">
              <i class="tio-clear"></i>
          </span>
        </div>
        <div class="modal-body">
          <div class="forget-pass-content">
              <img src="{{asset('/public/assets/admin/img/sent-mail.svg')}}" alt="">
              <h4>
                {{ translate('A mail has been sent to your registered email') }}!
              </h4>
              <p>
                {{ translate('Click the link in the mail description to change password') }}
              </p>
              <button class="btn btn-lg btn-block btn--primary mt-3" data-dismiss="modal">
                <i class="tio-checkmark-circle-outlined"></i> {{ translate('Got it') }}
              </button>
          </div>
        </div>
      </div>
    </div>
  </div>
<script src="{{asset('public/assets/admin')}}/js/vendor.min.js"></script>

<script src="{{asset('public/assets/admin')}}/js/theme.min.js"></script>
<script src="{{asset('public/assets/admin')}}/js/toastr.js"></script>
<script src="{{asset('public/assets/admin/js/app-toast.js')}}"></script>
{!! Toastr::message() !!}

@if ($errors->any())
    <script>
        "use strict";
        @foreach($errors->all() as $error)
        toastr.error('{{translate($error)}}', Error, {
            CloseButton: true,
            ProgressBar: true
        });
        @endforeach
    </script>
@endif
@if ($log_email_succ)
@php(session()->forget('log_email_succ'))
    <script>
        "use strict";
        $('#successMailModal').modal('show');
    </script>
@endif

<script>
    "use strict";
        $("#role-select").change(function() {
            var selectValue = $(this).val();
            if (selectValue == "admin") {
            $("#forget-password").show();
            $("#forget-password1").hide();
            } else if(selectValue == "vendor") {
            $("#forget-password").hide();
            $("#forget-password1").show();
            }
            else {
            $("#forget-password").hide();
            $("#forget-password1").hide();
            }
        });

    $(document).on('ready', function () {
        $('.js-toggle-password').each(function () {
            new HSTogglePassword(this).init()
        });

        $('.js-validate').each(function () {
            $.HSCore.components.HSValidation.init($(this));
        });
    });
    $(document).ready(function() {
        $('.onerror-image').on('error', function() {
            let img = $(this).data('onerror-image')
            $(this).attr('src', img);
        });
    });
</script>

<script>
    $(document).on('click', '.reloadCaptcha', function () {
        $.ajax({
            url: "{{ route('reload-captcha') }}",
            type: "GET",
            dataType: 'json',
            beforeSend: function () {
                $('#loading').show()
                $('.capcha-spin').addClass('active')
            },
            success: function (data) {
                $('#reload-captcha').html(data.view);
            },
            complete: function () {
                $('#loading').hide()
                $('.capcha-spin').removeClass('active')
            }
        });
    });

</script>

@if(isset($recaptcha) && $recaptcha['status'] == 1)
    <script src="https://www.google.com/recaptcha/api.js?render={{$recaptcha['site_key']}}"></script>
@endif
@if(isset($recaptcha) && $recaptcha['status'] == 1)
    <script>
        $(document).ready(function () {
            $('#signInBtn').click(function (e) {
                if ($('#set_default_captcha_value').val() == 1) {
                    $('#form-id').submit();
                    return true;
                }
                e.preventDefault();
                if (typeof grecaptcha === 'undefined') {
                    toastr.error('Invalid recaptcha key provided. Please check the recaptcha configuration.');
                    $('#reload-captcha').removeClass('d-none');
                    $('#set_default_captcha_value').val('1');

                    return;
                }
                grecaptcha.ready(function () {
                    grecaptcha.execute('{{$recaptcha['site_key']}}', { action: 'submit' }).then(function (token) {
                        $('#g-recaptcha-response').val(token);
                        $('#form-id').submit();
                    });
                });
                window.onerror = function (message) {
                    var errorMessage = 'An unexpected error occurred. Please check the recaptcha configuration';
                    if (message.includes('Invalid site key')) {
                        errorMessage = 'Invalid site key provided. Please check the recaptcha configuration.';
                    } else if (message.includes('not loaded in api.js')) {
                        errorMessage = 'reCAPTCHA API could not be loaded. Please check the recaptcha API configuration.';
                    }
                    $('#reload-captcha').removeClass('d-none');
                    $('#set_default_captcha_value').val('1');
                    toastr.error(errorMessage)
                    return true;
                };
            });
        });
    </script>
@endif




@if(getEnvMode()=='demo')
    <script>
        "use strict";
        $('.copy_cred').on('click', function () {
            $('#signinSrEmail').val('admin@admin.com');
            $('#signupSrPassword').val('12345678');
            toastr.success('Copied successfully!', 'Success!', {
                CloseButton: true,
                ProgressBar: true
            });
        })
        $('.copy_cred2').on('click', function () {
            $('#signinSrEmail').val('test.restaurant@gmail.com');
            $('#signupSrPassword').val('12345678');
            toastr.success('Copied successfully!', 'Success!', {
                CloseButton: true,
                ProgressBar: true
            });
        })
    </script>
@endif

</body>
</html>
