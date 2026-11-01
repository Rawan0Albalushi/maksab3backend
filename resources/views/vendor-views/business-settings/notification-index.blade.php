@extends('layouts.vendor.app')

@section('title', translate('Notification setup'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/third-party-setup.css') }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/view-pages/notification-setup.css') }}">
@endpush

@php($groups = [
    'orders' => match ($module_type) { 'rental' => translate('Trips'), 'service' => translate('Bookings'), default => translate('Orders') },
    'account' => translate('Account'),
    'wallet' => translate('Wallet'),
    'items' => translate('Items'),
    'promotions' => translate('Promotions'),
    'advertisements' => translate('Advertisements'),
    'subscription' => translate('Subscription'),
    'other' => translate('Other'),
])
@php($topics = [
    'order_notification' => ['group' => 'orders', 'title' => translate('Order updates'), 'hint' => translate('New orders and every change to their status.')],
    'trip_notification' => ['group' => 'orders', 'title' => translate('Trip updates'), 'hint' => translate('New trip requests and every change to their status.')],
    'booking_notification' => ['group' => 'orders', 'title' => translate('Booking updates'), 'hint' => translate('New bookings and every change to their status.')],
    'account_block' => ['group' => 'account', 'title' => translate('Account blocked'), 'hint' => translate('When the admin blocks your account.')],
    'account_unblock' => ['group' => 'account', 'title' => translate('Account unblocked'), 'hint' => translate('When the admin restores access to your account.')],
    'withdraw_approve' => ['group' => 'wallet', 'title' => translate('Withdrawal approved'), 'hint' => translate('When the admin approves one of your withdrawal requests.')],
    'withdraw_rejaction' => ['group' => 'wallet', 'title' => translate('Withdrawal rejected'), 'hint' => translate('When the admin rejects one of your withdrawal requests.')],
    'product_approve' => ['group' => 'items', 'title' => translate('Item approved'), 'hint' => translate('When the admin approves an item you submitted for review.')],
    'product_reject' => ['group' => 'items', 'title' => translate('Item rejected'), 'hint' => translate('When the admin rejects an item you submitted for review.')],
    'campaign_join_request' => ['group' => 'promotions', 'title' => translate('Campaign join request'), 'hint' => translate('When you send a request to join a campaign.')],
    'campaign_join_approval' => ['group' => 'promotions', 'title' => translate('Campaign request approved'), 'hint' => translate('When the admin accepts your request to join a campaign.')],
    'campaign_join_rejaction' => ['group' => 'promotions', 'title' => translate('Campaign request rejected'), 'hint' => translate('When the admin turns down your request to join a campaign.')],
    'bogo_offer_enrollment' => ['group' => 'promotions', 'title' => translate('BOGO offer enrollments'), 'hint' => translate('When you are invited to a BOGO offer, or your enrollment is approved or rejected.')],
    'happy_hour_enrollment' => ['group' => 'promotions', 'title' => translate('Happy hour enrollments'), 'hint' => translate('When you are invited to a happy hour, or your enrollment is approved or rejected.')],
    'advertisement_create_by_admin' => ['group' => 'advertisements', 'title' => translate('Advertisement created for you'), 'hint' => translate('When the admin creates an advertisement for your store.')],
    'advertisement_approval' => ['group' => 'advertisements', 'title' => translate('Advertisement request approved'), 'hint' => translate('When the admin approves your advertisement request.')],
    'advertisement_deny' => ['group' => 'advertisements', 'title' => translate('Advertisement request denied'), 'hint' => translate('When the admin denies your advertisement request.')],
    'advertisement_resume' => ['group' => 'advertisements', 'title' => translate('Advertisement running again'), 'hint' => translate('When a paused advertisement is resumed.')],
    'advertisement_pause' => ['group' => 'advertisements', 'title' => translate('Advertisement on hold'), 'hint' => translate('When a running advertisement is paused.')],
    'subscription_success' => ['group' => 'subscription', 'title' => translate('Subscription started'), 'hint' => translate('When a subscription payment succeeds and your plan starts.')],
    'subscription_renew' => ['group' => 'subscription', 'title' => translate('Subscription renewed'), 'hint' => translate('When your plan is renewed for another billing period.')],
    'subscription_shift' => ['group' => 'subscription', 'title' => translate('Subscription plan switched'), 'hint' => translate('When you switch to a different subscription plan.')],
    'subscription_cancel' => ['group' => 'subscription', 'title' => translate('Subscription canceled'), 'hint' => translate('When your subscription is canceled.')],
    'subscription_plan_update' => ['group' => 'subscription', 'title' => translate('Subscription plan updated'), 'hint' => translate('When the admin changes the plan you are subscribed to.')],
])
@php($topic_key = fn ($item) => preg_replace('/^(service_provider_|provider_|store_)/', '', $item->key))
@php($topic_of = fn ($item) => $topics[$topic_key($item)] ?? null)
@php($topic_order = array_flip(array_keys($topics)))
@php($data = $data->sortBy(fn ($item) => $topic_order[$topic_key($item)] ?? PHP_INT_MAX))
@php($admin_status = fn ($item, $column) => ($admin_notification_data[$item->key] ?? null)?->{$column} ?? 'disable')
@php($channels = array_map(fn ($channel) => $channel + [
    'available' => $data->filter(fn ($item) => $admin_status($item, $channel['column']) !== 'disable')->count(),
    'locked' => $data->filter(fn ($item) => $admin_status($item, $channel['column']) === 'inactive')->count(),
    'on' => $data->filter(fn ($item) => $admin_status($item, $channel['column']) === 'active' && $item->{$channel['column']} === 'active')->count(),
], [
    ['column' => 'push_notification_status', 'type' => 'push_notification', 'label' => translate('Push notification'), 'icon' => 'tio-notifications-on-outlined'],
    ['column' => 'mail_status', 'type' => 'Mail', 'label' => translate('Mail'), 'icon' => 'tio-email-outlined'],
    ['column' => 'sms_status', 'type' => 'SMS', 'label' => translate('SMS'), 'icon' => 'tio-sms'],
]))
@php($columns = array_filter($channels, fn ($channel) => $channel['available'] > 0))
@php($has_locked = collect($columns)->sum('locked') > 0)
@php($has_unused = collect($columns)->contains(fn ($channel) => $channel['available'] < $data->count()))

@section('content')
    <div class="content container-fluid">

        <div class="page-header">
            <h1 class="page-header-title">
                <i class="tio-notifications-on-outlined"></i>
                <span>{{ translate('Notification setup') }}</span>
            </h1>
            <p class="page-header-desc">{{ translate('Choose which events reach you, and by which channel.') }}</p>
        </div>

        <div class="tps nts">
            <div class="nts-summary" id="notificationSummary">
                @foreach ($channels as $channel)
                    <div class="nts-channel {{ $channel['available'] ? '' : 'is-unavailable' }}">
                        <span class="nts-channel__icon"><i class="{{ $channel['icon'] }}" aria-hidden="true"></i></span>
                        <div class="nts-channel__body">
                            <span class="nts-channel__name">{{ $channel['label'] }}</span>
                            @if ($channel['available'])
                                <span class="nts-channel__value">{{ translate('on') }}: {{ $channel['on'] }}/{{ $channel['available'] }}</span>
                                <span class="nts-channel__bar" role="presentation"><span style="inline-size: {{ round($channel['on'] / $channel['available'] * 100) }}%"></span></span>
                                @if ($channel['locked'])
                                    <p class="nts-channel__note"><i class="tio-lock-outlined" aria-hidden="true"></i>{{ translate('Turned off by admin') }}: {{ $channel['locked'] }}</p>
                                @endif
                            @else
                                <span class="nts-channel__value">{{ translate('Not offered') }}</span>
                                <p class="nts-channel__note">{{ translate('The admin has not made this channel available.') }}</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="tps-card">
                @if ($data->isEmpty() || empty($columns))
                    <div class="empty--data">
                        <img src="{{ asset('public/assets/admin/svg/illustrations/sorry.svg') }}" alt="">
                        <h5>{{ translate('No data found') }}</h5>
                    </div>
                @else
                    <div class="table-responsive datatable-custom">
                        <table class="table table-borderless table-thead-bordered table-align-middle card-table nts-table">
                            <thead class="thead-light">
                                <tr>
                                    <th scope="col">{{ translate('Topics') }}</th>
                                    @foreach ($columns as $channel)
                                        <th scope="col" class="nts-col-channel"><i class="{{ $channel['icon'] }}" aria-hidden="true"></i>{{ $channel['label'] }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            @foreach ($groups as $group => $group_label)
                                @php($group_items = $data->filter(fn ($item) => ($topic_of($item)['group'] ?? 'other') === $group))
                                @continue($group_items->isEmpty())
                                <tbody>
                                    <tr class="nts-group">
                                        <th scope="colgroup" colspan="{{ count($columns) + 1 }}">{{ $group_label }}</th>
                                    </tr>
                                    @foreach ($group_items as $item)
                                        @php($topic = $topic_of($item))
                                        @php($topic_title = $topic['title'] ?? $item->title)
                                        <tr>
                                            <td class="nts-topic">
                                                <span class="nts-topic__title">{{ $topic_title }}</span>
                                                <span class="nts-topic__hint">{{ $topic['hint'] ?? $item->sub_title }}</span>
                                            </td>
                                            @foreach ($columns as $channel)
                                                @php($status = $admin_status($item, $channel['column']))
                                                <td class="nts-col-channel">
                                                    @if ($status === 'disable')
                                                        <span class="nts-state nts-state--na" title="{{ translate('This channel is not used for this topic.') }}">
                                                            <span aria-hidden="true">&mdash;</span>
                                                            <span class="sr-only">{{ translate('Not available') }}</span>
                                                        </span>
                                                    @elseif ($status === 'inactive')
                                                        <span class="nts-state nts-state--locked" title="{{ translate('The admin has turned this channel off, so you cannot change it.') }}">
                                                            <i class="tio-lock-outlined" aria-hidden="true"></i>{{ translate('Off by admin') }}
                                                        </span>
                                                    @else
                                                        @php($is_on = $item->{$channel['column']} === 'active')
                                                        <div class="status-toggle" data-status="{{ $is_on ? 1 : 0 }}">
                                                            <label class="toggle-switch toggle-switch-sm" for="notification-{{ $channel['type'] }}-{{ $item->key }}">
                                                                <input type="checkbox" id="notification-{{ $channel['type'] }}-{{ $item->key }}"
                                                                       class="toggle-switch-input" data-status-toggle data-url-fixed
                                                                       data-url="{{ route('vendor.business-settings.notification_status_change', ['key' => $item->key, 'type' => $channel['type']]) }}"
                                                                       data-label-on="{{ translate('on') }}" data-label-off="{{ translate('off') }}"
                                                                       data-ajax-refresh="#notificationSummary"
                                                                       aria-label="{{ $topic_title }}: {{ $channel['label'] }}"
                                                                       @checked($is_on)>
                                                                <span class="toggle-switch-label">
                                                                    <span class="toggle-switch-indicator"></span>
                                                                </span>
                                                            </label>
                                                            <span class="status-toggle__text" aria-live="polite">{{ $is_on ? translate('on') : translate('off') }}</span>
                                                        </div>
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            @endforeach
                        </table>
                    </div>
                    <div class="tps-card__foot nts-legend">
                        @if ($has_locked)
                            <span class="nts-legend__item">
                                <span class="nts-state nts-state--locked"><i class="tio-lock-outlined" aria-hidden="true"></i>{{ translate('Off by admin') }}</span>
                                {{ translate('The admin has turned this channel off, so you cannot change it.') }}
                            </span>
                        @endif
                        @if ($has_unused)
                            <span class="nts-legend__item">
                                <span class="nts-state nts-state--na" aria-hidden="true">&mdash;</span>
                                {{ translate('This channel is not used for this topic.') }}
                            </span>
                        @endif
                        <span class="nts-legend__item nts-legend__item--save">
                            <i class="tio-checkmark-circle-outlined" aria-hidden="true"></i>{{ translate('Changes save as soon as you flip a switch.') }}
                        </span>
                    </div>
                @endif
            </div>
        </div>

    </div>
@endsection
