@extends('layouts.landing.app')
@section('title', translate('messages.Vendor registration'))
@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/toastr.css') }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/view-pages/vendor-registration.css') }}">
    <link rel="stylesheet" href="{{ asset('public/assets/landing/css/select2.min.css') }}"/>
@endpush
@section('content')
    <section class="page-hero">
        <div class="container">
            <h1>{{ translate('messages.vendor') }} {{ translate('messages.registration') }}</h1>
            <div class="breadcrumb">
                <a href="{{ route('home') }}">{{ translate('messages.home') }}</a> / {{ translate('messages.vendor') }} {{ translate('messages.registration') }}
            </div>
        </div>
    </section>

    <section class="reg-section">
        <div class="reg-container" style="max-width:1060px">

            <div class="stepper" style="display:flex;align-items:center;justify-content:center;gap:20px;margin-bottom:32px;flex-wrap:wrap">
                <div class="stepper-step completed">
                    <div class="stepper-circle">1</div>
                    <div class="stepper-label">{{ translate('General information') }}</div>
                </div>
                <div class="stepper-connector"></div>
                <div class="stepper-step active">
                    <div class="stepper-circle">2</div>
                    <div class="stepper-label">{{ translate('Business plan') }}</div>
                </div>
                <div class="stepper-connector"></div>
                <div class="stepper-step">
                    <div class="stepper-circle">3</div>
                    <div class="stepper-label">{{ translate('Complete') }}</div>
                </div>
            </div>


            <form class="reg-form js-validate" action="{{ route('restaurant.payment') }}" method="post" id="paymentForm">
                @csrf
                @method('post')
                <input type="hidden" name="store_id" value="{{ $store_id }}">
                <input type="hidden" name="package_id" value="{{ $package_id }}">
                <div class="card __card mb-3">
                    <div class="card-body p-4">

                        <div class="text-center mb-4">
                            <h5 class="card-title mb-1">{{ translate('Make payment for your business plan') }}</h5>
                            <p class="fs-13 text-muted mb-0">{{ translate('Choose your preferred payment method to continue') }}</p>
                        </div>

                        <?php
                        if( data_get($free_trial_settings, 'subscription_free_trial_type') == 'year'){
                                $trial_period = data_get($free_trial_settings, 'subscription_free_trial_days') > 0 ? data_get($free_trial_settings, 'subscription_free_trial_days') / 365 : 0;
                            } else if( data_get($free_trial_settings, 'subscription_free_trial_type') == 'month'){
                                $trial_period = data_get($free_trial_settings, 'subscription_free_trial_days') > 0 ? data_get($free_trial_settings, 'subscription_free_trial_days') / 30 : 0;
                            } else{
                                $trial_period = data_get($free_trial_settings, 'subscription_free_trial_days') > 0 ? data_get($free_trial_settings, 'subscription_free_trial_days') : 0;
                            }
                        ?>

                        @if (data_get($free_trial_settings,'subscription_free_trial_status') == 1 && data_get($free_trial_settings,'subscription_free_trial_days') > 0)
                            <label class="payment-item payment-free-trial mb-4">
                                <input type="radio" class="d-none" checked value="free_trial" name="payment">
                                <div class="payment-item-inner justify-content-center">
                                    <div class="check">
                                        <img src="{{ asset('public/assets/admin/img/check-1.png') }}" class="uncheck" alt="">
                                        <img src="{{ asset('public/assets/admin/img/check-2.png') }}" class="check" alt="">
                                    </div>
                                    <div>
                                        <span class="fw-bold">{{ translate('Continue with free trial') }}: {{ (match (data_get($free_trial_settings, 'subscription_free_trial_type')) { 'year' => \Carbon\CarbonInterval::years($trial_period), 'month' => \Carbon\CarbonInterval::months($trial_period), default => \Carbon\CarbonInterval::days($trial_period) })->forHumans(['skip' => ['week']]) }}</span>
                                        <small class="d-block text-muted mt-1">{{ translate('No payment required during trial period') }}</small>
                                    </div>
                                </div>
                            </label>

                            <div class="payment-divider">
                                <span>{{ translate('Or') }}</span>
                            </div>
                        @endif

                        <div class="mb-3 mt-4">
                            <h6 class="payment-section-title">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                                {{ translate('Pay via online') }}
                                <span class="payment-section-subtitle">({{ translate('Faster & secure way to pay bill') }})</span>
                            </h6>
                        </div>
                        <div class="row g-3">
                            @foreach ($payment_methods as $item)
                            <div class="col-sm-6 col-lg-4">
                                <label class="payment-item">
                                    <input type="radio" class="d-none" value="{{ $item['gateway'] }}" name="payment">
                                    <div class="payment-item-inner">
                                        <div class="check">
                                            <img src="{{ asset('public/assets/admin/img/check-1.png') }}" class="uncheck" alt="">
                                            <img src="{{ asset('public/assets/admin/img/check-2.png') }}" class="check" alt="">
                                        </div>
                                        <span>{{ $item['gateway_title'] }}</span>
                                        <img class="ms-auto payment-gateway-icon"
                                             src="{{ \App\CentralLogics\Helpers::get_full_url('payment_modules/gateway_image',$item['gateway_image'],$item['storage'] ?? 'public') }}"
                                             alt="{{ $item['gateway_title'] }}">
                                    </div>
                                </label>
                            </div>
                            @endforeach
                        </div>

                        <div class="text-end pt-4 mt-2 d-flex flex-wrap justify-content-end gap-3">
                            <button type="submit" class="btn-next" id="paymentNextBtn">
                                <i class="tio-arrow-forward"></i> <span class="btn-text">{{ translate('Next') }}</span>
                                <span class="btn-loader d-none"><span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span></span>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </section>

    @endsection
    @push('script_2')
    <script>
        $(document).ready(function() {
            $('#paymentForm').on('submit', function() {
                var btn = $('#paymentNextBtn');
                btn.prop('disabled', true);
                btn.find('.btn-text').addClass('d-none');
                btn.find('.btn-loader').removeClass('d-none');
            });
        });
    </script>
    @endpush
