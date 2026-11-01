<!DOCTYPE html>
{{-- View data supplied by App\Services\VendorLayoutService (composed in AppServiceProvider). --}}
<html dir="{{ $site_direction }}" lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    class="{{ $site_direction === 'rtl' ? 'active' : '' }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" id="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title')</title>
    <link rel="shortcut icon" href="">
    <link rel="icon" type="image/x-icon"
        href="{{\App\CentralLogics\Helpers::iconFullUrl()}}">
    <link rel="preload" as="font" type="font/woff2" crossorigin
        href="{{ asset('public/assets/admin/vendor/icon-set/fonts/The-Icon-of9a76.woff2') }}?ww946b">
    <link href="{{asset('public/assets/admin/css/fonts.css')}}" rel="stylesheet">
    <link rel="stylesheet" href="{{asset('public/assets/admin')}}/css/vendor.min.css">
    <link rel="stylesheet" href="{{asset('public/assets/admin')}}/vendor/icon-set/style.css">
    <link rel="stylesheet" href="{{asset('public/assets/admin')}}/css/bootstrap.min.css">
    <link rel="stylesheet" href="{{asset('public/assets/admin')}}/css/theme.minc619.css?v=1.0">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/emogi-area.css')}}">
    @if(addon_published_status('Service'))
        <link rel="stylesheet" href="{{ asset('Modules/Service/public/assets/css/service.css') }}">
    @endif
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/style.css')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/app-toast.css')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/intltelinput/css/intlTelInput.css')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/owl.min.css')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/upload-single-image.css')}}">
    @php
        $fcmEnabled = ! empty($fcmCredentials['apiKey'])
            && ! empty($fcmCredentials['projectId'])
            && ! empty($fcmCredentials['messagingSenderId'])
            && ! empty($fcmCredentials['appId']);
    @endphp

        @if($use_v2_chrome)
        <link rel="stylesheet" href="{{ asset('public/assets/admin/css/admin-v2.css') }}?v={{ @filemtime(public_path('assets/admin/css/admin-v2.css')) }}">
    @endif
    @if(addon_published_status('ReelsModule'))
        <link rel="stylesheet" href="{{ asset('Modules/ReelsModule/public/assets/css/reels.css') }}">
    @endif
    {{-- After admin-v2.css: the search palette adapts to whichever chrome is active. --}}
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/global-search.css') }}">
    {{-- Same reason: the new-order alert picks up the v2 tokens when they exist. --}}
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/new-order-alert.css') }}">
    {{-- Last of the base sheets, and after the module ones: it gives every
         `.nav-tabs` the third-party-setup tab strip look, and several module
         stylesheets restyle tabs at the same specificity. Still ahead of
         `css_or_js` so a page can opt out with `.nav-tabs--plain`. --}}
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/admin-tabs.css') }}">
    {{-- Same placement rule: it restyles the theme's own list-table classes
         (`.datatable-custom`, `.card-table`, `.thead-light`), so it has to come
         after style.css / theme.min / bootstrap.min but stay ahead of
         `css_or_js` so a page can still override a column. --}}
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/admin-tables.css') }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/ajax-framework.css') }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/filter-drawer.css') }}">
    {{-- Pairs a tio glyph with a button label (`<i class="tio-save"></i> Save`).
         Scoped under `.btn`, and after style.css so its alignment and RTL
         mirroring win on equal specificity. --}}
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/button-icons.css') }}">
    {{-- The page trail above every screen. After button-icons.css and the v2
         sheet so it can read the `--v2-*` tokens, and after style.css so
         `.bcx ~ .content` reclaims that container's top padding on equal
         specificity. --}}
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/breadcrumb.css') }}">
    {{-- Restyles the theme's own `.page-header-icon` into the tinted badge and
         adds the `.page-header-desc` summary line. After style.css and
         breadcrumb.css so it wins on equal specificity, and ahead of
         `css_or_js` so a page can still override its own header. --}}
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/page-head.css') }}">
    {{-- The one field shape every screen shares. Last of the framework sheets so
         it outranks bootstrap.min / theme.min / style.css on equal specificity,
         and ahead of `css_or_js` so a page sheet still owns its own fields. --}}
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/form-controls.css') }}">
    {{-- The one button shape, state and focus ring every screen shares. Directly
         after form-controls.css: a button and the field beside it are one
         control pair, and both have to outrank bootstrap.min / theme.min /
         style.css while staying ahead of `css_or_js`. --}}
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/buttons.css') }}">
    @stack('css_or_js')

    <link rel="stylesheet" href="{{asset('public/assets/admin')}}/css/toastr.css">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/page-transition.css')}}?v={{ @filemtime(public_path('assets/admin/css/page-transition.css')) }}">
</head>

<body class="{{ $layoutBodyClass }}">
    <script>
        (function () {
            if (window.localStorage.getItem('hs-navbar-vertical-aside-mini')) {
                document.body.classList.add('navbar-vertical-aside-mini-mode');
            }
        })();
    </script>


    <div id="page-progress-bar"></div>

    @if (getEnvMode() == 'demo')
        <div class="direction-toggle">
            <i class="tio-settings"></i>
            <span></span>
        </div>
    @endif

    <div id="app-toast-container" class="app-toast-container"></div>

    {{-- Global blocking loader, toggled everywhere with $('#loading').show() / .hide() --}}
    <div id="loading" class="initial-hidden" role="status" aria-live="polite">
        <div class="loader--inner">
            <span class="app-loader__spinner" aria-hidden="true"></span>
            <span class="sr-only">{{ translate('messages.loading') }}</span>
        </div>
    </div>

    @include('layouts.vendor.partials._header')

    @if(isset($moduleType) && $moduleType == 'rental')
        @include("rental::provider.partials._sidebar_{$moduleType}")
    @elseif(isset($moduleType) && $moduleType == 'service')
        @include('service::vendor.partials._sidebar_service')
    @else
        @include('layouts.vendor.partials._sidebar')
    @endif

    @if($use_v2_chrome ?? false)
        @include('layouts.vendor.partials._header_v2')
        @if($moduleType === 'rental')
            @include('rental::provider.partials._sidebar_v2_rental')
        @elseif($moduleType === 'service')
            @include('service::vendor.partials._sidebar_v2_service')
        @else
            @include('layouts.vendor.partials._sidebar_v2')
        @endif
    @endif

    <main id="content" role="main" class="main pointer-event">
    {{-- Rendered here, not per page: the trail is derived from the route,
         so a new screen is covered the moment it is routed. --}}
    @include('partials._breadcrumb')

        @yield('content')


        @include('layouts.vendor.partials._footer')

        {{-- Dashboard only, and only for an invitation the store has not opened yet. It used to
             render on every vendor page, so answering it was a race against the next page load
             putting it back up. --}}
        @if(request()->routeIs('vendor.dashboard'))
            @include('layouts.vendor.partials._promotion_invitations')
        @endif

        <div class="d-none" id="text-validate-translate" data-required="{{ translate('This field is required.') }}"
            data-something-went-wrong="{{ translate('Something went wrong') }}"
            data-max-limit-crossed="{{ translate('Max limit crossed') }}"
            data-file-size-larger="{{ translate('File size is larger') }}"
            data-passwords-do-not-match="{{ translate('Passwords do not match') }}"
            data-valid-email="{{ translate('Please enter a valid email') }}"
            data-password-validation="{{ translate('Use at least one uppercase letter, one lowercase letter, one number and one symbol.') }} {{ translate('Minimum characters') }}: 8">
        </div>


        <div class="modal fade" id="toggle-modal">
            <div class="modal-dialog status-warning-modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">
                            <span aria-hidden="true" class="tio-clear"></span>
                        </button>
                    </div>
                    <div class="modal-body pb-5 pt-0">
                        <div class="max-349 mx-auto mb-20">
                            <div>
                                <div class="text-center">
                                    <img id="toggle-image" alt="" class="mb-20">
                                    <h5 class="modal-title" id="toggle-title"></h5>
                                </div>
                                <div class="text-center" id="toggle-message">
                                </div>
                            </div>
                            <div class="btn--container justify-content-center">
                                <button type="button" id="toggle-ok-button"
                                    class="btn btn--primary min-w-120 confirm-Toggle"
                                    data-dismiss="modal"><i class="tio-checkmark-circle-outlined"></i> {{translate('OK')}}</button>
                                <button id="reset_btn" type="reset" class="btn btn--cancel min-w-120"
                                    data-dismiss="modal">
                                    <i class="tio-clear-circle-outlined"></i> {{translate("Cancel")}}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="toggle-status-modal">
            <div class="modal-dialog status-warning-modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">
                            <span aria-hidden="true" class="tio-clear"></span>
                        </button>
                    </div>
                    <div class="modal-body pb-5 pt-0">
                        <div class="max-349 mx-auto mb-20">
                            <div>
                                <div class="text-center">
                                    <img id="toggle-status-image" alt="" class="mb-20">
                                    <h5 class="modal-title" id="toggle-status-title"></h5>
                                </div>
                                <div class="text-center" id="toggle-status-message">
                                </div>
                            </div>
                            <div class="btn--container justify-content-center">
                                <button type="button" id="toggle-status-ok-button"
                                    class="btn btn--primary min-w-120 confirm-Status-Toggle"
                                    data-dismiss="modal"><i class="tio-checkmark-circle-outlined"></i> {{translate('OK')}}</button>
                                <button id="reset_btn" type="reset" class="btn btn--cancel min-w-120"
                                    data-dismiss="modal">
                                    <i class="tio-clear-circle-outlined"></i> {{translate("Cancel")}}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        {{-- The heading text is swapped by the notification handlers below
             (order / trip / booking / approved bid), so it stays a bare text
             node — the icon lives outside it and survives the swap. --}}
        <div class="modal fade noa-modal" id="popup-modal" tabindex="-1" role="dialog"
            aria-labelledby="popup-modal-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered noa-dialog" role="document">
                <div class="modal-content noa">
                    <button type="button" class="noa-close" data-dismiss="modal"
                        aria-label="{{ translate('Close') }}">
                        <i class="tio-clear" aria-hidden="true"></i>
                    </button>
                    <div class="modal-body noa-body">
                        <div class="noa-icon" aria-hidden="true">
                            <span class="noa-ping"></span>
                            <span class="noa-ping noa-ping--slow"></span>
                            <span class="noa-icon-core"><i class="tio-shopping-cart-outlined"></i></span>
                        </div>
                        <span class="noa-eyebrow">{{ translate('New notification') }}</span>
                        <h2 class="noa-title update_notification_text" id="popup-modal-title">
                            {{ translate('You have new order, check please.') }}
                        </h2>
                        <p class="noa-text">
                            {{ translate('messages.Review the details and take action right away.') }}
                        </p>
                        <button type="button" class="btn noa-btn check-order">
                            {{ translate('Ok, let me check') }}
                            <i class="tio-chevron-right noa-btn-arrow" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        @include('layouts.partials._video-preview-modal')

        @if ($verifiedBadgePopupShow)
            <div class="modal fade" id="verified-badge-popup-modal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header border-0 pb-0">
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body pt-0 pb-4 px-4">
                            <div class="text-center">
                                <img src="{{ asset('public/assets/admin/img/badge-big.png') }}" alt="Verified badge"
                                    class="mb-3" style="max-width: 110px;">
                                <h3 class="mb-2">{{ translate('Congratulations!') }}</h3>
                                <p class="mb-0">{{ translate('You have received a verified badge.') }}
                                    {{ translate('It will appear next to your') }} {{ $verifiedBadgePopupLabel }}
                                    {{ translate('Name to build customer trust.') }}
                                </p>
                            </div>
                        </div>
                        <div class="modal-footer justify-content-center border-0 pt-0 pb-4">
                            <button type="button" class="btn btn--primary min-w-120px"
                                data-dismiss="modal"><i class="tio-checkmark-circle-outlined"></i> {{ translate('Okay') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if(addon_published_status('ReelsModule'))
            <script src="{{ asset('Modules/ReelsModule/public/assets/js/reel-upload.js') }}"></script>
        @endif


        <div class="modal fade" id="new-dynamic-submit-model">
            <div class="modal-dialog modal-dialog-centered status-warning-modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">
                            <span aria-hidden="true" class="tio-clear"></span>
                        </button>
                    </div>
                    <div class="modal-body pb-5 pt-0">
                        <div class="max-349 mx-auto mb-20">
                            <div>
                                <div class="text-center">
                                    <img id="image-src" class="mb-20">
                                    <h5 class="modal-title" id="toggle-title"></h5>
                                </div>
                                <div class="text-center" id="toggle-message">
                                    <h3 id="modal-title"></h3>
                                    <div id="modal-text"></div>
                                </div>

                            </div>
                            <div class="mb-4 d-none" id="note-data">
                                <textarea class="form-control" placeholder="{{ translate('Enter a note') }}"
                                    id="get-text-note" cols="5"></textarea>
                            </div>
                            <div class="btn--container justify-content-center">
                                <div id="hide-buttons">
                                    <div class="d-flex justify-content-center flex-wrap gap-3">
                                        <button data-dismiss="modal" id="cancel_btn_text"
                                            class="btn btn--cancel min-w-120"><i class="tio-time"></i> {{translate('Not now')}}</button>
                                        <button type="button" id="new-dynamic-ok-button"
                                            class="btn btn-primary confirm-model min-w-120"><i class="tio-checkmark-circle-outlined"></i> {{translate('Yes')}}</button>
                                    </div>
                                </div>

                                <button data-dismiss="modal" type="button" id="new-dynamic-ok-button-show"
                                    class="btn btn--primary  d-none min-w-120"><i class="tio-checkmark-circle-outlined"></i> {{translate('Okay')}}</button>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="imageModal" class="imageModal modal fade" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header justify-content-end gap-3 border-0 p-2">
                        <button type="button"
                            class="modal_img-btn border-0 btn-circle rounded-circle bg-section2 shadow-none fs-8 m-0"
                            data-dismiss="modal" aria-label="Close">
                            <i class="tio-clear"></i>
                        </button>
                    </div>
                    <div class="modal-body text-center p-3 pt-0">
                        <div class="imageModal_img_wrapper">
                            <img src="" class="img-fluid imageModal_img" alt="{{ translate('Preview image') }}">
                            <div class="imageModal_btn_wrapper m-1">
                                <a href="javascript:" class="btn icon-btn px-1 py-1 download_btn"
                                    title="{{ translate('Download') }}" download>
                                    <i class="tio-arrow-large-downward"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-none" id="default-text-data"
            data-default-image-src="{{ asset('public/assets/admin/img/upload-img.png') }}"></div>
    </main>

    <script src="{{asset('public/assets/admin')}}/js/custom.js"></script>
    @if($fcmEnabled)
        <script src="{{asset('public/assets/admin')}}/js/firebase.min.js"></script>
    @endif

    @stack('script')

    <script src="{{asset('public/assets/admin')}}/js/vendor.min.js"></script>
    <script src="{{asset('public/assets/admin')}}/js/theme.min.js"></script>
    <script src="{{asset('public/assets/admin')}}/js/verified-select2.js"></script>
    <script src="{{asset('public/assets/admin')}}/js/sweet_alert.js"></script>
    <script>
        window.APP_TOAST_I18N = {
            success: "{{ translate('messages.success') }}",
            info: "{{ translate('Information') }}",
            warning: "{{ translate('messages.warning') }}",
            danger: "{{ translate('messages.error') }}",
            close: "{{ translate('messages.Close') }}"
        };
    </script>
    <script src="{{asset('public/assets/admin')}}/js/toastr.js"></script>
    <script src="{{asset('public/assets/admin/js/app-toast.js')}}"></script>
    <script src="{{asset('public/assets/admin/js/field-error-toast.js')}}"></script>
    <script src="{{asset('public/assets/admin')}}/js/emogi-area.js"></script>
    <script src="{{asset('public/assets/admin/js/owl.min.js')}}"></script>
    <script src="{{asset('public/assets/admin/js/app-blade/vendor.js')}}"></script>
    {!! Toastr::message() !!}
    <script src="{{asset('public/assets/admin/intltelinput/js/intlTelInput.min.js')}}"></script>
    <script src="{{asset('public/assets/admin/js/form-validate.js')}}"></script>
    <script src="{{asset('public/assets/admin/js/upload-single-image.js')}}"></script>


    @if ($errors->any())

        <script>
            "use strict";
            @foreach ($errors->all() as $error)
                toastr.error('{{ translate($error) }}', Error, {
                    CloseButton: true,
                    ProgressBar: true
                });
            @endforeach
        </script>
    @endif

    @stack('script_2')
    <audio id="myAudio">
        <source src="{{asset('public/assets/admin/sound/notification.mp3')}}" type="audio/mpeg">
    </audio>
    <script src="{{asset('public/assets/admin/js/view-pages/common.js')}}"></script>
    @include('partials._status-toggle-lang')
    {{-- After common.js: it hands `window.StatusToggle` to the two handlers
         there (`.redirect-url`, `.confirm-Status-Toggle`). --}}
    <script src="{{asset('public/assets/admin/js/status-toggle.js')}}"></script>
    {{-- The row priority selects, alongside status-toggle.js: same shared
         layer, same `StatusToggleResponse` round trip. --}}
    @include('partials._priority-select-lang')
    <script src="{{asset('public/assets/admin/js/priority-select.js')}}"></script>
    @include('partials._ajax-framework-lang')
    <script src="{{asset('public/assets/admin/js/ajax-framework.js')}}"></script>
    <script src="{{asset('public/assets/admin/js/filter-drawer.js')}}"></script>
    {{-- After the v2 header script, which owns the rail buttons a section
         crumb clicks and the `v2-drawer-open` body class it checks. --}}
    <script src="{{asset('public/assets/admin/js/breadcrumb.js')}}"></script>
    {{-- The horizontal tab strips — see the note in layouts/admin/app.blade.php. --}}
    <script src="{{asset('public/assets/admin/js/tab-scroller.js')}}"></script>
    <script src="{{asset('public/assets/admin/js/keyword-highlighted.js')}}"></script>
    <script src="{{asset('public/assets/admin/js/global-search.js')}}"></script>
    <script src="{{ asset('public/assets/admin') }}/js/offcanvas.js"></script>
    <script src="{{ asset('public/assets/admin/js/page-transition.js') }}?v={{ @filemtime(public_path('assets/admin/js/page-transition.js')) }}"></script>
    <script src="{{ asset('public/assets/admin/js/fullscreen.js') }}?v={{ @filemtime(public_path('assets/admin/js/fullscreen.js')) }}"></script>



    <script>
        var audio = document.getElementById("myAudio");

        function playAudio() {
            audio.play();
        }

        function pauseAudio() {
            audio.pause();
        }
        "use strict";


        $(window).on('load', function () {
            $('main > .container-fluid.content').prepend($('#renew-badge'));
        })



        $(document).on('ready', function () {
            // $('body').css('overflow','')
            $(".direction-toggle").on("click", function () {
                if ($('html').hasClass('active')) {
                    $('html').removeClass('active')
                    setDirection(1);
                } else {
                    setDirection(0);
                    $('html').addClass('active')
                }
            });
            if ($('html').attr('dir') === "rtl") {
                $(".direction-toggle").find('span').text('Toggle LTR')
            } else {
                $(".direction-toggle").find('span').text('Toggle RTL')
            }

            function setDirection(status) {
                if (status === 1) {
                    $("html").attr('dir', 'ltr');
                    $(".direction-toggle").find('span').text('Toggle RTL')
                } else {
                    $("html").attr('dir', 'rtl');
                    $(".direction-toggle").find('span').text('Toggle LTR')
                }
                $.get({
                    url: '{{ route('vendor.site_direction') }}',
                    dataType: 'json',
                    data: {
                        status: status,
                    },
                    success: function () {
                    },

                });
            }
        });


        function route_alert(route, message) {
            Swal.fire({
                title: '{{ translate('messages.Are you sure?') }}',
                text: message,
                type: 'warning',
                showCancelButton: true,
                cancelButtonColor: 'default',
                confirmButtonColor: '#FC6A57',
                cancelButtonText: '{{ translate('messages.No') }}',
                confirmButtonText: '{{ translate('messages.Yes') }}',
                reverseButtons: true
            }).then((result) => {
                if (result.value) {
                    location.href = route;
                }
            })
        }

        $(document).on('click', '.form-alert', function () {
            let id = $(this).data('id')
            let message = $(this).data('message')
            Swal.fire({
                title: '{{ translate('messages.Are you sure?') }}',
                text: message,
                type: 'warning',
                showCancelButton: true,
                cancelButtonColor: 'default',
                confirmButtonColor: '#FC6A57',
                cancelButtonText: '{{ translate('messages.No') }}',
                confirmButtonText: '{{ translate('messages.Yes') }}',
                reverseButtons: true
            }).then((result) => {
                if (result.value) {
                    $('#' + id).submit()
                }
            })
        })


        function set_filter(url, id, filter_by) {
            let nurl = new URL(url);
            nurl.searchParams.set(filter_by, id);
            location.href = nurl;
        }

        @if($fcmEnabled)
                let firebaseConfig = {
            apiKey: "{{isset($fcmCredentials['apiKey']) ? $fcmCredentials['apiKey'] : ''}}",
            authDomain: "{{isset($fcmCredentials['authDomain']) ? $fcmCredentials['authDomain'] : ''}}",
            projectId: "{{isset($fcmCredentials['projectId']) ? $fcmCredentials['projectId'] : ''}}",
            storageBucket: "{{isset($fcmCredentials['storageBucket']) ? $fcmCredentials['storageBucket'] : ''}}",
            messagingSenderId: "{{isset($fcmCredentials['messagingSenderId']) ? $fcmCredentials['messagingSenderId'] : ''}}",
            appId: "{{isset($fcmCredentials['appId']) ? $fcmCredentials['appId'] : ''}}",
            measurementId: "{{isset($fcmCredentials['measurementId']) ? $fcmCredentials['measurementId'] : ''}}"
        };
        firebase.initializeApp(firebaseConfig);
        const messaging = firebase.messaging();

        function startFCM() {
            messaging
                .requestPermission()
                .then(function () {
                    return messaging.getToken();
                })
                .then(function (token) {
                                        // Send the token to your backend to subscribe to topic
                    subscribeTokenToBackend(token, 'store_panel_{{$storeId}}_message');
                }).catch(function (error) {
                    console.error('Error getting permission or token:', error);
                });
        }
        @endif

        // FCM topic subscriptions are persistent on Google's side, so re-sending an
        // unchanged token on every page load just burns two Google API round trips
        // per request. Re-assert weekly to cover token rotation.
        const FCM_SUB_TTL = 7 * 24 * 60 * 60 * 1000;

        function fcmAlreadySubscribed(token, topic) {
            try {
                let cached = JSON.parse(localStorage.getItem(`fcm_sub_${topic}`) || 'null');
                return cached && cached.token === token && (Date.now() - cached.at) < FCM_SUB_TTL;
            } catch (e) {
                return false;
            }
        }

        function subscribeTokenToBackend(token, topic) {
            if (fcmAlreadySubscribed(token, topic)) {
                return;
            }
            fetch('{{url('/')}}/subscribeToTopic', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ token: token, topic: topic })
            }).then(response => {
                if (response.status < 200 || response.status >= 400) {
                    return response.text().then(text => {
                        throw new Error(`Error subscribing to topic: ${response.status} - ${text}`);
                    });
                }
                try {
                    localStorage.setItem(`fcm_sub_${topic}`, JSON.stringify({ token: token, at: Date.now() }));
                } catch (e) {}
            }).catch(error => {
                console.error('Subscription error:', error);
            });
        }
        function getUrlParameter(sParam) {
            let sPageURL = window.location.search.substring(1);
            let sURLletiables = sPageURL.split('&');
            for (let i = 0; i < sURLletiables.length; i++) {
                let sParameterName = sURLletiables[i].split('=');
                if (sParameterName[0] === sParam) {
                    return sParameterName[1];
                }
            }
        }

        function conversationList() {
            $.ajax({
                url: "{{ route('vendor.message.list') }}",
                success: function (data) {
                    $('#conversation-list').empty();
                    $("#conversation-list").append(data.html);
                    let user_id = getUrlParameter('user');
                    $('.customer-list').removeClass('conv-active');
                    $('#customer-' + user_id).addClass('conv-active');
                }
            })
        }

        function conversationView() {
            let conversation_id = getUrlParameter('conversation');
            let user_id = getUrlParameter('user');
            let url = '{{url('/')}}/vendor-panel/message/view/' + conversation_id + '/' + user_id;
            $.ajax({
                url: url,
                success: function (data) {
                    $('#view-conversation').html(data.view);
                }
            })
        }

        let order_type = 'all';
        let is_trip = false;
        let is_service = false;
        let is_bid_approved = false;
        @if($fcmEnabled)
        messaging.onMessage(function (payload) {
            if (payload.data.order_id && payload.data.type === 'new_order') {
                @if(\App\CentralLogics\Helpers::employee_module_permission_check('order') && $admin_order_notification && $order_notification_type == 'firebase')
                    order_type = payload.data.order_type
                    is_trip = false;
                    is_service = false;
                    is_bid_approved = false;
                    if (order_type === 'trip') {
                        document.querySelector('.update_notification_text').textContent = "{{translate('You have new trip, check please.')}}";
                        is_trip = true;
                    }
                    if (order_type === 'service_booking') {
                        document.querySelector('.update_notification_text').textContent = "{{translate('You have new booking, check please.')}}";
                        is_service = true;
                    }
                    if (order_type === 'bid_approved') {
                        document.querySelector('.update_notification_text').textContent = "{{translate('Your bid was approved, check please.')}}";
                        is_bid_approved = true;
                    }
                    playAudio();
                    $('#popup-modal').appendTo("body").modal('show');
                @endif
            } else if (payload.data.type === 'message') {
                if (window.location.pathname.includes('message/list') && getUrlParameter('conversation')) {
                    let conversation_id = getUrlParameter('conversation');
                    let user_id = getUrlParameter('user');
                    let url = '{{url('/')}}/vendor-panel/message/view/' + conversation_id + '/' + user_id;
                    $.ajax({
                        url: url,
                        success: function (data) {
                            $('#vendor-view-conversation').html(data.view);
                        }
                    })
                }
                toastr.success('{{ translate('messages.New message arrived') }}', {
                    CloseButton: true,
                    ProgressBar: true
                });
                if ($('#conversation-list').scrollTop() === 0) {
                    conversationList();
                }
            } else if (payload.data.type === 'custom_service_request') {
                // Firebase route for the new-service-request popup. The handler itself lives
                // in the service module's poller partial, which registers window.serviceCsrPoll;
                // it is absent outside the service context, hence the guard. Routed through
                // this existing observer rather than a second messaging.onMessage(), which
                // would replace it.
                if (typeof window.serviceCsrPoll === 'function') {
                    window.serviceCsrPoll();
                }
            }
        });
        @endif

        @if(\App\CentralLogics\Helpers::employee_module_permission_check('order') && $admin_order_notification && $order_notification_type == 'manual')
            setInterval(function () {
                $.get({
                    url: '{{route('vendor.get-store-data')}}',
                    dataType: 'json',
                    success: function (response) {
                        let data = response.data;

                        if (data.order_type === 'trip') {
                            document.querySelector('.update_notification_text').textContent = "{{translate('You have new trip, check please.')}}";
                            is_trip = true;
                        }
                        if (data.order_type === 'service_booking') {
                            document.querySelector('.update_notification_text').textContent = "{{translate('You have new booking, check please.')}}";
                            is_service = true;
                        }

                        if (data.new_pending_order > 0) {
                            order_type = 'pending';
                            playAudio();
                            $('#popup-modal').appendTo("body").modal('show');
                        }
                        else if (data.new_confirmed_order > 0) {
                            order_type = 'confirmed';
                            playAudio();
                            $('#popup-modal').appendTo("body").modal('show');
                        }
                    },
                });
            }, 10000);
        @endif

            @if ($verifiedBadgePopupShow)
                let verifiedBadgePopupSeen = false;
                $(window).on('load', function () {
                    $('#verified-badge-popup-modal').appendTo("body").modal('show');
                });
                $(document).on('hidden.bs.modal', '#verified-badge-popup-modal', function () {
                    if (verifiedBadgePopupSeen) {
                        return;
                    }
                    verifiedBadgePopupSeen = true;
                    $.ajax({
                        url: '{{ route('vendor.verified-badge-popup-seen') }}',
                        type: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}'
                        }
                    });
                });
            @endif

        $('.check-order').on('click', function () {
            if (order_type) {
                if (is_trip === true) {
                    location.href = '{{url('/')}}/vendor-panel/trip?status=all';
                } else if (is_service === true) {
                    location.href = '{{url('/')}}/vendor-panel/service/booking/list';
                } else if (is_bid_approved === true) {
                    location.href = '{{url('/')}}/vendor-panel/service/custom-request/my-bids';
                } else {
                    location.href = '{{url('/')}}/vendor-panel/order/list/' + order_type;

                }
            }
        });
        @if($fcmEnabled)
        startFCM();
        @endif
        @if(\App\CentralLogics\Helpers::employee_module_permission_check('chat'))
        conversationList();
        @endif
        if (getUrlParameter('conversation')) {
            conversationView();
        }

        function initTelInputs() {
            const inputs = document.querySelectorAll('input[type="tel"]');

            inputs.forEach(input => {

                if (window.intlTelInputGlobals && window.intlTelInputGlobals.getInstance(input)) {
                    return;
                }

                const iti = window.intlTelInput(input, {
                    initialCountry: "{{$countryCode}}",
                    utilsScript: "{{ asset('public/assets/admin/intltelinput/js/utils.js') }}",
                    autoInsertDialCode: true,
                    nationalMode: false,
                    formatOnDisplay: false,
                    strictMode: true,
                    @if (\App\CentralLogics\Helpers::get_business_settings('country_picker_status') != 1)
                        onlyCountries: ["{{$countryCode}}"],
                    @endif
                });

            const restoreDialCode = () => {
                if (input.value.trim() === '') {
                    input.value = '+' + iti.getSelectedCountryData().dialCode;
                }
            };

            input.addEventListener('blur', restoreDialCode);
            input.closest('form')?.addEventListener('submit', restoreDialCode);
        });

        $(document).off('keyup.telinput').on('keyup.telinput', 'input[type="tel"]', function () {
            const iti = window.intlTelInputGlobals.getInstance(this);
            if (!iti) return;

            let val = $(this).val();
            if (val.trim() === '') {
                val = '+' + iti.getSelectedCountryData().dialCode;
            } else {
                const plus = val.startsWith('+') ? '+' : '';
                val = plus + val.replace(/[^\d]/g, '');
            }

            $(this).val(val);
        });
        }


        initTelInputs();
    </script>
    
    <script>
        /* ----------------------------------------------------------------------
           Global search palette. Markup lives in
           layouts/partials/_global_search_modal.blade.php; the shared rendering
           + keyboard behaviour in js/global-search.js. Only the routes and
           translations are panel-specific, so they stay here.
           ---------------------------------------------------------------------- */
        @php
            // Grouped into one array on purpose: @json() splits its argument on
            // commas, so a translated string containing one cannot be passed to
            // the directive directly.
            $gs_labels = [
                'pages' => translate('Pages & actions'),
                'records' => translate('Records'),
                'recent' => translate('Recent searches'),
                'page' => translate('Page'),
                'record' => translate('Record'),
                'noResultTitle' => translate('No data found'),
                'noResultText' => translate('Try another keyword, or search by ID, name, phone or email.'),
                'idleTitle' => translate('Search anything'),
                'idleText' => translate('Jump to any page, or look up an order, item, customer and more.'),
                'errorTitle' => translate('Something went wrong'),
                'errorText' => translate('We could not load the results. Please try again.'),
            ];

            $gs_notes = [
                'limit' => translate('Showing the closest matches only. Refine your keyword to narrow the list.'),
            ];
        @endphp

        GlobalSearch.configure({
            labels: @json($gs_labels),
            images: {
                noResult: @json(asset('/public/assets/admin/img/no-search-found.png'))
            }
        });

        $(document).ready(function () {
            var $modal = $('#staticBackdrop');
            var $form = $('#searchForm');
            var $input = $('#searchInput');
            var $results = $('#searchResults');

            var searchDebounce = null;
            var searchRequest = null;
            var recentRequest = null;

            var resultLimit = {{ config('search.result_limit', 50) }};
            var limitNote = @json($gs_notes)['limit'];

            // Delegated: the result list is re-rendered on every keystroke.
            $results.on('click', '.search-list-item', function () {
                $.ajax({
                    type: 'POST',
                    url: '{{ route('vendor.store.clicked.route') }}',
                    data: {
                        routeName: $(this).data('route-name'),
                        routeUri: $(this).data('route-uri'),
                        routeFullUrl: $(this).data('route-full-url'),
                        searchKeyword: $input.val().trim(),
                        _token: $form.find('input[name="_token"]').val()
                    },
                    error: function (xhr) {
                        console.error(xhr.responseText);
                    }
                });
            });

            function abortPending() {
                clearTimeout(searchDebounce);

                if (searchRequest) {
                    searchRequest.abort();
                    searchRequest = null;
                }

                if (recentRequest) {
                    recentRequest.abort();
                    recentRequest = null;
                }
            }

            // Keep the previous rows on screen while retyping; the progress bar
            // already signals that a request is in flight.
            function showPlaceholder(rows) {
                if (!$results.find('.gsearch-item').length) {
                    $results.html(GlobalSearch.skeleton(rows));
                }
            }

            function runGlobalSearch(searchKeyword) {
                searchRequest = $.ajax({
                    type: 'POST',
                    url: $form.attr('action'),
                    data: { search: searchKeyword, _token: $form.find('input[name="_token"]').val() },
                    success: function (response) {
                        if (!response.length) {
                            $results.html(GlobalSearch.noResult());
                            return;
                        }

                        $results.html(GlobalSearch.results(response, searchKeyword, {
                            footnote: response.length >= resultLimit ? limitNote : null
                        }));
                    },
                    error: function (xhr, status) {
                        if (status !== 'abort') {
                            console.error(xhr.responseText);
                            $results.html(GlobalSearch.error());
                        }
                    },
                    complete: function (xhr, status) {
                        if (status !== 'abort') {
                            GlobalSearch.loading(false);
                        }
                    }
                });
            }

            function getRecentSearch() {
                GlobalSearch.loading(true);
                showPlaceholder(5);

                recentRequest = $.ajax({
                    type: 'GET',
                    url: '{{ route('vendor.recent.search') }}',
                    success: function (response) {
                        $results.html(response.length ? GlobalSearch.recent(response) : GlobalSearch.idle());
                    },
                    error: function (xhr, status) {
                        if (status !== 'abort') {
                            console.error(xhr.responseText);
                            $results.html(GlobalSearch.error());
                        }
                    },
                    complete: function (xhr, status) {
                        if (status !== 'abort') {
                            GlobalSearch.loading(false);
                        }
                    }
                });
            }

            $form.find('input[name="search"]').on('input', function () {
                var searchKeyword = $(this).val().trim();

                abortPending();

                if (searchKeyword.length < 1) {
                    getRecentSearch();
                    return;
                }

                GlobalSearch.loading(true);
                showPlaceholder(4);

                searchDebounce = setTimeout(function () {
                    runGlobalSearch(searchKeyword);
                }, 300);
            });

            $form.on('submit', function (event) {
                event.preventDefault();
            });

            $modal.on('shown.bs.modal', function () {
                getRecentSearch();
            });

            $modal.on('hidden.bs.modal', function () {
                abortPending();
                $results.empty();
            });
        });

        document.addEventListener('keydown', function (event) {
            if ((event.ctrlKey || event.metaKey) && (event.key === 'k' || event.key === 'K')) {
                event.preventDefault();
                document.getElementById('modalOpener').click();
            }
        });


    </script>

    @include('layouts.partials._logout_confirm')

</body>

</html>
