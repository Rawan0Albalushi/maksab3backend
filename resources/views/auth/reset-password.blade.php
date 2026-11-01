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

            <div class="reset-password">
                <div class="mb-3 text-center">
                    <img src="{{asset('/public/assets/admin/img/lock.svg')}}" alt="">
                </div>
                <div class="mt-4">
                    <form action="{{ route('reset-password-submit') }}" method="POST">
                        @csrf
                        <input type="hidden" name="reset_token" value="{{ $token }}">
                        <div class="js-form-message form-group mb-4">
                            <label class="input-label">
                                {{translate('New password')}}
                                <span class="form-label-secondary" data-toggle="tooltip" data-placement="right"
                                data-original-title="{{ translate('Use at least one uppercase letter, one lowercase letter, one number and one symbol.') }} {{ translate('Minimum characters') }}: 8"><img src="{{ asset('/public/assets/admin/img/info-circle.svg') }}" alt="{{ translate('Use at least one uppercase letter, one lowercase letter, one number and one symbol.') }} {{ translate('Minimum characters') }}: 8"></span>
                            </label>
                            <div class="input-group input-group-merge">
                                <input type="password" class="js-toggle-password form-control form-control-lg"
                                        name="password" id="signupSrPassword" placeholder="{{ translate('Minimum characters') }}: 6+"
                                        aria-label="{{ translate('Minimum characters') }}: 6+" required
                                        data-msg="{{translate('messages.Invalid password warning')}}"
                                        data-hs-toggle-password-options='{
                                                    "target": "#new-pass",
                                        "defaultClass": "tio-hidden-outlined",
                                        "showClass": "tio-visible-outlined",
                                        "classChangeTarget": "#new-pass-icon"
                                        }'>
                                <div id="new-pass" class="input-group-append">
                                    <a class="input-group-text" href="javascript:">
                                        <i id="new-pass-icon" class="tio-visible-outlined"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="js-form-message form-group mb-4">
                            <label class="input-label">
                                <span class="d-flex justify-content-between align-items-center">
                                    {{translate('Confirm password')}}
                                </span>
                            </label>
                            <div class="input-group input-group-merge">
                                <input type="password" class="js-toggle-password form-control form-control-lg"
                                        name="confirm_password" id="signupSrPassword" placeholder="{{ translate('Minimum characters') }}: 6+"
                                        aria-label="{{ translate('Minimum characters') }}: 6+" required
                                        data-msg="{{translate('messages.Invalid password warning')}}"
                                        data-hs-toggle-password-options='{
                                                    "target": "#conf-pass",
                                        "defaultClass": "tio-hidden-outlined",
                                        "showClass": "tio-visible-outlined",
                                        "classChangeTarget": "#conf-pass-icon"
                                        }'>
                                <div id="conf-pass" class="input-group-append">
                                    <a class="input-group-text" href="javascript:">
                                        <i id="conf-pass-icon" class="tio-visible-outlined"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-block btn--primary"><i class="tio-lock"></i> {{translate('Change password')}}</button>
                    </form>
                </div>
            </div>

        </div>
    </div>
</main>

<script src="{{asset('public/assets/admin')}}/js/vendor.min.js"></script>

<script src="{{asset('public/assets/admin')}}/js/theme.min.js"></script>
<script src="{{asset('public/assets/admin')}}/js/toastr.js"></script>
<script src="{{asset('public/assets/admin/js/app-toast.js')}}"></script>
{!! Toastr::message() !!}

@if ($errors->any())
    <script>
        "use strict";
        @foreach($errors->all() as $error)
        toastr.error('{{$error}}', Error, {
            CloseButton: true,
            ProgressBar: true
        });
        @endforeach
    </script>
@endif

<script>
    "use strict";
    $(document).on('ready', function () {
        $('.js-toggle-password').each(function () {
            new HSTogglePassword(this).init()
        });

        $('.js-validate').each(function () {
            $.HSCore.components.HSValidation.init($(this));
        });
    });
</script>

@if(isset($recaptcha) && $recaptcha['status'] == 1)
    <script type="text/javascript">
    "use strict";
        var onloadCallback = function () {
            grecaptcha.render('recaptcha_element', {
                'sitekey': '{{ \App\CentralLogics\Helpers::get_business_settings('recaptcha')['site_key'] }}'
            });
        };
    </script>
    <script src="https://www.google.com/recaptcha/api.js?onload=onloadCallback&render=explicit" async defer></script>
    <script>
        "use strict";
        $("#form-id").on('submit',function(e) {
            var response = grecaptcha.getResponse();

            if (response.length === 0) {
                e.preventDefault();
                toastr.error("{{translate('messages.Please check the recaptcha')}}");
            }
        });
    </script>
@endif

<script>
    "use strict";
        function reloadCaptcha() {
            $.ajax({
                url: "{{ route('reload-captcha') }}",
                type: "GET",
                dataType: 'json',
                beforeSend: function () {
                    $('#loading').show()
                },
                success: function(data) {
                    $('#reload-captcha').html(data.view);
                },
                complete: function () {
                    $('#loading').hide()
                }
            });
        }
        $(document).ready(function() {
            $('.onerror-image').on('error', function() {
                let img = $(this).data('onerror-image')
                $(this).attr('src', img);
            });
        });
</script>

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
    </script>
@endif

</body>
</html>
