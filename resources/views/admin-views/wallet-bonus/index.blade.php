@extends('layouts.admin.app')

@section('title', translate('messages.Bonus'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/third-party-setup.css') }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/wallet-bonus.css') }}">
@endpush

@section('content')

@php
    $currencySymbol = \App\CentralLogics\Helpers::currency_symbol();
    $currencyPosition = \App\CentralLogics\Helpers::get_business_settings('currency_symbol_position') ?? 'left';
    $today = \Carbon\Carbon::today();
@endphp

<div class="content container-fluid tps wbn">
    <div class="tps-head">
        <div class="tps-head__title">
            <span class="tps-head__icon">
                <img src="{{ asset('public/assets/admin/img/outline/wallet.svg') }}" alt="">
            </span>
            <span class="tps-head__text">
                <h1>{{ translate('messages.Wallet bonus setup') }}</h1>
                <p>{{ translate('Give customers extra credit when they top their wallet up.') }}</p>
            </span>
        </div>

        <button type="button" class="tps-help" data-toggle="modal" data-target="#how-it-works">
            <i class="tio-help-outlined"></i>
            <span>{{ translate('How it works') }}</span>
        </button>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-xl-8">
            <form action="{{ route('admin.users.customer.wallet.bonus.store') }}" method="POST">
                @csrf
                @include('admin-views.wallet-bonus.partials._form', [
                    'bonus'         => null,
                    'submitLabel'   => translate('messages.Submit'),
                    'submitIcon'    => 'tio-checkmark-circle-outlined',
                ])
            </form>
        </div>

        <div class="col-xl-4">
            @include('admin-views.wallet-bonus.partials._preview')
        </div>
    </div>

    <div class="tps-card">
        <div class="tps-card__head">
            <div class="tps-card__titles">
                <div class="tps-card__title">
                    {{ translate('messages.Bonus list') }}
                    <span class="badge badge-soft-dark ml-1" id="itemCount">{{ $bonuses->total() }}</span>
                </div>
                <div class="tps-card__subtitle">{{ translate('messages.Bonus offers applied when customers top up their wallet.') }}</div>
            </div>
            <div class="tps-card__aside">
                <form class="search-form min--270">
                    <div class="input-group input--group">
                        <input id="datatableSearch" value="{{ request()?->search ?? null }}" type="search" name="search"
                            class="form-control" aria-label="{{ translate('Search') }}"
                            placeholder="{{ translate('messages.Ex') . ' : ' . translate('messages.Search by bonus title') }}">
                        <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                    </div>
                </form>
            </div>
        </div>

        <div class="tps-card__body p-0">
            @if (count($bonuses))
                <div class="table-responsive datatable-custom">
                    <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                        <thead class="thead-light">
                            <tr>
                                <th>{{ translate('SL') }}</th>
                                <th>{{ translate('Bonus title') }}</th>
                                <th>{{ translate('Bonus amount') }}</th>
                                <th class="col--numeric">{{ translate('Minimum add money amount') }}</th>
                                <th class="col--numeric">{{ translate('Maximum bonus') }}</th>
                                <th>{{ translate('Runs') }}</th>
                                <th>{{ translate('messages.Status') }}</th>
                                <th class="text-center">{{ translate('messages.Action') }}</th>
                            </tr>
                        </thead>

                        <tbody id="set-rows">
                            @foreach ($bonuses as $key => $bonus)
                                @php
                                    $expired = $bonus->end_date && $bonus->end_date->lt($today);
                                    $scheduled = $bonus->start_date && $bonus->start_date->gt($today);
                                @endphp
                                <tr>
                                    <td>{{ $key + $bonuses->firstItem() }}</td>
                                    <td>
                                        <span class="wbn-row-title">{{ Str::limit($bonus->title, 40, '...') }}</span>
                                        @if ($bonus->description)
                                            <span class="wbn-row-sub">{{ Str::limit($bonus->description, 60, '...') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $bonus->bonus_type === 'amount'
                                            ? \App\CentralLogics\Helpers::format_currency($bonus->bonus_amount)
                                            : $bonus->bonus_amount . '%' }}
                                    </td>
                                    <td class="col--numeric">{{ \App\CentralLogics\Helpers::format_currency($bonus->minimum_add_amount) }}</td>
                                    <td class="col--numeric">
                                        {{ $bonus->bonus_type === 'amount'
                                            ? '—'
                                            : \App\CentralLogics\Helpers::format_currency($bonus->maximum_bonus_amount) }}
                                    </td>
                                    <td>
                                        <span class="wbn-when">
                                            {{ $bonus->start_date?->format('d M Y') }} – {{ $bonus->end_date?->format('d M Y') }}
                                        </span>
                                        @if ($expired)
                                            <span class="tps-pill tps-pill--off">{{ translate('Expired') }}</span>
                                        @elseif ($scheduled)
                                            <span class="tps-pill tps-pill--warn">{{ translate('Scheduled') }}</span>
                                        @else
                                            <span class="tps-pill tps-pill--on">{{ translate('Running') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="status-toggle" data-status="{{ $bonus->status ? 1 : 0 }}">
                                            <label class="toggle-switch toggle-switch-sm" for="bonusCheckbox{{ $bonus->id }}">
                                                <input type="checkbox" id="bonusCheckbox{{ $bonus->id }}"
                                                    class="toggle-switch-input" data-status-toggle data-method="post"
                                                    data-url="{{ route('admin.users.customer.wallet.bonus.status', $bonus->id) }}"
                                                    data-confirm-on="{{ translate('You want to switch this bonus on') }}"
                                                    data-confirm-off="{{ translate('You want to switch this bonus off') }}"
                                                    aria-label="{{ translate('messages.Status') }}"
                                                    {{ $bonus->status ? 'checked' : '' }}>
                                                <span class="toggle-switch-label">
                                                    <span class="toggle-switch-indicator"></span>
                                                </span>
                                            </label>
                                            <span class="status-toggle__text" aria-live="polite">
                                                {{ $bonus->status ? translate('messages.Active') : translate('messages.Inactive') }}
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="btn--container justify-content-center">
                                            <a class="btn action-btn action-btn--edit"
                                                href="{{ route('admin.users.customer.wallet.bonus.edit', $bonus->id) }}"
                                                title="{{ translate('messages.Edit bonus') }}"><i class="tio-edit"></i></a>
                                            <a class="btn action-btn action-btn--delete form-alert" href="javascript:"
                                                data-id="bonus-{{ $bonus->id }}"
                                                data-message="{{ translate('Want to delete this bonus?') }}"
                                                title="{{ translate('messages.Delete bonus') }}"><i class="tio-delete-outlined"></i></a>
                                            <form action="{{ route('admin.users.customer.wallet.bonus.delete', $bonus->id) }}"
                                                method="post" id="bonus-{{ $bonus->id }}" class="d-none">
                                                @csrf @method('delete')
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="page-area">
                    {!! $bonuses->links() !!}
                </div>
            @else
                <div class="empty--data">
                    <img src="{{ asset('public/assets/admin/svg/illustrations/sorry.svg') }}" alt="">
                    <h5>{{ translate('No data found') }}</h5>
                </div>
            @endif
        </div>
    </div>
</div>

<div class="modal fade" id="how-it-works" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ translate('messages.Wallet bonus setup') }}</h5>
                <button type="button" class="close btn btn--reset btn-circle" data-dismiss="modal"
                    aria-label="{{ translate('messages.Close') }}">
                    <span aria-hidden="true" class="tio-clear fs-20 opacity-70"></span>
                </button>
            </div>
            <div class="modal-body">
                <ol class="tps-steps mb-3">
                    <li>{{ translate('Customers get a bonus on top of what they add, paid from the admin wallet.') }}</li>
                    <li>{{ translate('A percentage bonus is capped by the maximum you set. A fixed amount bonus pays the same on every qualifying top-up.') }}</li>
                    <li>{{ translate('If several bonuses are running, the customer is given the biggest one — they do not stack.') }}</li>
                    <li>{{ translate('Switch a bonus off to stop it paying out without deleting it.') }}</li>
                </ol>
                <div class="tps-note tps-note--warn">
                    <i class="tio-info-outined"></i>
                    <div>
                        {{ translate('Wallet bonus is only applicable when a customer add fund to wallet via outside payment gateway!') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('script_2')
<script>
    "use strict";

    window.walletBonusConfig = {
        startsToday: true,
        currency: {
            symbol: @json($currencySymbol),
            position: @json($currencyPosition),
            decimals: {{ (int) (config('round_up_to_digit') ?? 2) }}
        },
        lang: {
            capReached: @json(translate('Top-up that reaches the cap'))
        }
    };
</script>
<script src="{{ asset('public/assets/admin') }}/js/view-pages/wallet-bonus-form.js"></script>
@endpush
