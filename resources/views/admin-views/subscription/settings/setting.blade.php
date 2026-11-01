@extends('layouts.admin.app')

@section('title',translate('Subscription settings'))

@section('subscription_settings')
active
@endsection

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/third-party-setup.css') }}">
@endpush

@section('content')

    @php
        $trial_on = data_get($settings, 'subscription_free_trial_status') == 1;
        $trial_type = data_get($settings, 'subscription_free_trial_type');
        $trial_days = data_get($settings, 'subscription_free_trial_days');

        if ($trial_type == 'year') {
            $trial_period = $trial_days > 0 ? $trial_days / 365 : 0;
        } elseif ($trial_type == 'month') {
            $trial_period = $trial_days > 0 ? $trial_days / 30 : 0;
        } else {
            $trial_period = $trial_days > 0 ? $trial_days : null;
        }
    @endphp

    <div class="content container-fluid tps">
        <div class="tps-head">
            <div class="tps-head__title">
                <span class="tps-head__icon"><i class="tio-settings-outlined"></i></span>
                <span class="tps-head__text">
                    <h1>{{ translate('Subscription settings') }}</h1>
                    <p>{{ translate('How subscriptions renew, when stores are reminded and what happens when one lapses.') }}</p>
                </span>
            </div>
        </div>

        <div class="tps-masonry">
            <form action="{{ route('admin.business-settings.subscriptionackage.settingUpdate') }}" method="post">
                @csrf
                <div class="tps-card">
                    <div class="tps-card__head">
                        <span class="tps-card__brand"><i class="tio-gift"></i></span>
                        <div class="tps-card__titles">
                            <h2 class="tps-card__title d-flex align-items-center gap-2">
                                {{ translate('Offer free trial') }}
                                <span class="tps-pill tps-pill--{{ $trial_on ? 'on' : 'off' }}">
                                    {{ $trial_on ? translate('Enabled') : translate('Disabled') }}
                                </span>
                            </h2>
                            <p class="tps-card__subtitle">
                                {{ translate('You can offer vendors a free trial to experience the system overall') }}
                            </p>
                        </div>
                        <div class="tps-card__aside">
                            <label class="toggle-switch toggle-switch-sm p-0 m-0">
                                <input type="checkbox"
                                    data-url="{{ route('admin.business-settings.subscriptionackage.trialStatus') }}"
                                    data-title="{{ $trial_on ? translate('Are you sure to disable the free trial option?') : translate('Are you sure to enable the free trial option?') }}"
                                    data-message="{{ $trial_on ? translate('If disabled, the store can\'t get the experience without any business plan.') : translate('If enabled, the store can experience the services at no cost for a limited time.') }}"
                                    class="toggle-switch-input status_change_alert" {{ $trial_on ? 'checked' : '' }}>
                                <span class="toggle-switch-label p-0">
                                    <span class="toggle-switch-indicator"></span>
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="tps-card__body">
                        @unless ($trial_on)
                            <div class="tps-note tps-note--muted mb-4">
                                <i class="tio-info-outined"></i>
                                <div>
                                    {{ translate('The free trial is off. Set the period now and switch it on when ready.') }}
                                </div>
                            </div>
                        @endunless

                        <div class="row g-3">
                            <div class="col-sm-7">
                                <div class="tps-field">
                                    <label class="tps-field__label" for="subscription_free_trial_days">
                                        {{ translate('Free trial period') }} <span class="tps-req">*</span>
                                    </label>
                                    <input type="number" required min="0" max="999" step="any"
                                        id="subscription_free_trial_days" name="subscription_free_trial_days"
                                        value="{{ $trial_period }}" class="form-control" placeholder="14">
                                    <small class="tps-field__hint">{{ translate('How long a new store can use its package before the first payment is due.') }}</small>
                                </div>
                            </div>
                            <div class="col-sm-5">
                                <div class="tps-field">
                                    <label class="tps-field__label" for="subscription_free_trial_type">{{ translate('Unit') }}</label>
                                    <select id="subscription_free_trial_type" name="subscription_free_trial_type" class="form-control">
                                        <option value="day" {{ $trial_type == 'day' ? 'selected' : '' }}>{{ translate('day') }}</option>
                                        <option value="month" {{ $trial_type == 'month' ? 'selected' : '' }}>{{ translate('month') }}</option>
                                        <option value="year" {{ $trial_type == 'year' ? 'selected' : '' }}>{{ translate('year') }}</option>
                                    </select>
                                    <small class="tps-field__hint">{{ translate('Stored in days.') }} {{ translate('Days in a month') }}: 30 · {{ translate('Days in a year') }}: 365</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tps-card__foot">
                        <button type="submit" class="btn btn--primary"><i class="tio-save"></i> {{ translate('messages.Save') }}</button>
                    </div>
                </div>
            </form>

            <form action="{{ route('admin.business-settings.subscriptionackage.settingUpdate') }}" method="post">
                @csrf
                <div class="tps-card">
                    <div class="tps-card__head">
                        <span class="tps-card__brand"><i class="tio-warning-outlined"></i></span>
                        <div class="tps-card__titles">
                            <h2 class="tps-card__title">{{ translate('Show deadline warning') }}</h2>
                            <p class="tps-card__subtitle">
                                {{ translate('Select the number of days before the warning will be shown with a countdown to the end of all packages') }}
                            </p>
                        </div>
                    </div>

                    <div class="tps-card__body">
                        <div class="tps-field">
                            <label class="tps-field__label" for="subscription_deadline_warning_days">
                                {{ translate('Select days') }} <span class="tps-req">*</span>
                            </label>
                            <input type="number" required min="1" max="99999999"
                                id="subscription_deadline_warning_days" name="subscription_deadline_warning_days"
                                value="{{ data_get($settings, 'subscription_deadline_warning_days') }}"
                                class="form-control" placeholder="7">
                            <small class="tps-field__hint">{{ translate('The same window marks a subscriber as expiring soon in the subscriber list.') }}</small>
                        </div>

                        <div class="tps-field">
                            <label class="tps-field__label" for="subscription_deadline_warning_message">
                                {{ translate('Type message') }} <span class="tps-req">*</span>
                            </label>
                            <input type="text" required maxlength="254"
                                id="subscription_deadline_warning_message" name="subscription_deadline_warning_message"
                                value="{{ data_get($settings, 'subscription_deadline_warning_message') }}"
                                class="form-control" placeholder="{{ translate('Your subscription is ending soon.') }}">
                            <small class="tps-field__hint">{{ translate('Shown to the vendor in their panel for the whole countdown.') }}</small>
                        </div>
                    </div>

                    <div class="tps-card__foot">
                        <button type="submit" class="btn btn--primary"><i class="tio-save"></i> {{ translate('messages.Save') }}</button>
                    </div>
                </div>
            </form>

            <form action="{{ route('admin.business-settings.subscriptionackage.settingUpdate') }}" method="post">
                @csrf
                <div class="tps-card">
                    <div class="tps-card__head">
                        <span class="tps-card__brand"><i class="tio-restore"></i></span>
                        <div class="tps-card__titles">
                            <h2 class="tps-card__title">{{ translate('Return money restriction') }}</h2>
                            <p class="tps-card__subtitle">
                                {{ translate('Setup the amount after which if any store change / migrate the subscription plan you won\'t return any money back') }}
                            </p>
                        </div>
                    </div>

                    <div class="tps-card__body">
                        <div class="tps-field">
                            <label class="tps-field__label" for="subscription_usage_max_time">
                                {{ translate('Select subscription usage time') }} (%) <span class="tps-req">*</span>
                            </label>
                            <input type="number" required min="1" max="99"
                                id="subscription_usage_max_time" name="subscription_usage_max_time"
                                value="{{ data_get($settings, 'subscription_usage_max_time') }}"
                                class="form-control" placeholder="50">
                            <small class="tps-field__hint">{{ translate('Past this share of the package validity, switching plans refunds nothing.') }}</small>
                        </div>
                    </div>

                    <div class="tps-card__foot">
                        <button type="submit" class="btn btn--primary"><i class="tio-save"></i> {{ translate('messages.Save') }}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

@endsection

@push('script_2')
<script>
    "use strict";

    $('.status_change_alert').on('click', function (event) {
        let title = $(this).data('title');
        let url = $(this).data('url');
        let message = $(this).data('message');
        status_change_alert(title, url, message, event)
    })

    function status_change_alert(title, url, message, e) {
        e.preventDefault();
        Swal.fire({
            title: title,
            text: message,
            type: 'warning',
            showCancelButton: true,
            cancelButtonColor: 'default',
            confirmButtonColor: '#FC6A57',
            cancelButtonText: '{{ translate('No') }}',
            confirmButtonText: '{{ translate('Yes') }}',
            reverseButtons: true
        }).then((result) => {
            if (result.value) {
                location.href = url;
            }
        })
    }
</script>
@endpush
