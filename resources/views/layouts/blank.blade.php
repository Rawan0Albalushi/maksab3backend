<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title')</title>

    <link rel="shortcut icon" href="{{asset('public/assets/installation')}}/assets/img/favicon.svg">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{asset('public/assets/installation')}}/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="{{asset('public/assets/installation')}}/assets/css/style.css">
</head>

<body>
<section style="background-image: url('{{asset('public/assets/installation')}}/assets/img/page-bg.png')"
         class="w-100 min-vh-100 bg-img position-relative py-5">

    <div class="logo">
        <img src="{{asset('public/assets/installation')}}/assets/img/favicon.svg" alt="">
    </div>

    <div class="custom-container">
        {{-- Toastr::message() emits a bare toastr.*() call and this layout loads neither
             jQuery nor toastr.js, so every wizard error died as an undefined-function error
             in the console and the page simply re-appeared unchanged - which is exactly how
             a failed update looks like an update that never ran. Flash messages are drawn
             as plain markup instead: no JS, no extra assets, and they still show when the
             wizard is the only thing the app can boot. --}}
        @foreach (session('toastr::messages', []) as $installerMessage)
            <div class="alert alert-{{ ($installerMessage['type'] ?? 'info') === 'error' ? 'danger' : ($installerMessage['type'] ?? 'info') }} alert-dismissible fade show mt-3 mb-0" role="alert">
                {!! $installerMessage['message'] !!}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endforeach

        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show mt-3 mb-0" role="alert">
                {!! session('error') !!}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show mt-3 mb-0" role="alert">
                {!! session('success') !!}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        {{-- isset() guard: this layout is what renders when the app is degraded, and
             $errors only exists when the web group's ShareErrorsFromSession has run. A
             fatal here would replace the wizard with a 500 and hide the very message it
             is here to show. --}}
        @if (isset($errors) && $errors->any())
            <div class="alert alert-danger alert-dismissible fade show mt-3 mb-0" role="alert">
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $installerError)
                        <li>{{ $installerError }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

    @yield('content')

        <footer class="footer py-3 mt-4">
            <div class="d-flex flex-column flex-sm-row justify-content-between gap-2 align-items-center">
                <div class="footer-logo">
                    <img src="{{asset('public/assets/installation')}}/assets/img/logo.svg" alt="">
                </div>
                <p class="copyright-text mb-0">© {{date("Y")}} | All Rights Reserved</p>
            </div>
        </footer>
    </div>
</section>
</body>

<script src="{{asset('public/assets/installation')}}/assets/js/bootstrap.bundle.min.js"></script>
<script src="{{asset('public/assets/installation')}}/assets/js/script.js"></script>

</html>
