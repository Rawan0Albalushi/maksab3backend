<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <title>{{translate('messages.error')}} 404 | {{\App\CentralLogics\Helpers::get_business_settings('business_name', false)??'6amMart'}}</title>

    <link rel="shortcut icon" href="favicon.ico">

    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&amp;display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{asset('public/assets/admin')}}/css/vendor.min.css">
    <link rel="stylesheet" href="{{asset('public/assets/admin')}}/vendor/icon-set/style.css">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/button-icons.css') }}">

    <link rel="stylesheet" href="{{asset('public/assets/admin/css/bootstrap.min.css')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/theme.minc619.css?v=1.0')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/style.css')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/buttons.css')}}">
</head>

<body>

<div class="container">
    <div class="footer-height-offset d-flex justify-content-center align-items-center flex-column">
        <div class="row align-items-sm-center w-100">
            <div class="col-sm-6">
                <div class="text-center text-sm-right mr-sm-4 mb-5 mb-sm-0">
                    <img class="w-60 w-sm-100 mx-auto mw-15rem"
                         src="{{asset('public/assets/admin')}}/svg/illustrations/think.svg" alt="Image Description">
                </div>
            </div>

            <div class="col-sm-6 col-md-4 text-center text-sm-left">
                <h1 class="display-1 mb-0">404</h1>
                <p class="lead">{{translate('messages.Sorry, the page you are looking for could not be found.')}}</p>
                {{--
                    This page renders in states where the panel routes are not registered -- during
                    a software update the RouteServiceProvider only loads routes/update.php, and a
                    host that does not match APP_HOST_DOMAIN matches none of the domain-grouped
                    route files. route() would then throw and replace this 404 with a 500, so the
                    route is only named when the router actually has it.
                --}}
                @php
                    $dashboardRoute = auth('vendor')->check() ? 'vendor.dashboard' : 'admin.dashboard';
                @endphp
                <a class="btn btn-primary" href="{{ app('router')->has($dashboardRoute) ? route($dashboardRoute) : url('/') }}"><i class="tio-dashboard-outlined"></i> {{translate('Dashboard')}}</a>
            </div>
        </div>
    </div>
</div>

<div class="footer text-center">
    <ul class="list-inline list-separator">
        @if(app('router')->has('contact-us'))
            <li class="list-inline-item">
                <a class="list-separator-link" target="_blank" href="{{route('contact-us')}}">{{\App\CentralLogics\Helpers::get_business_settings('business_name', false)??'6amMart'}} {{translate('messages.Support')}}</a>
            </li>
        @endif
    </ul>
</div>


<script src="{{asset('public/assets/admin')}}/js/theme.min.js"></script>
</body>

</html>
