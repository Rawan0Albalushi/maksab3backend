@php
    $zeroMoney = \App\CentralLogics\Helpers::format_currency(0);
@endphp

<div class="wbn-aside" id="bonus_preview">
    <div class="tps-card">
        <div class="tps-card__head">
            <span class="tps-card__brand"><i class="tio-calculator"></i></span>
            <div class="tps-card__titles">
                <h2 class="tps-card__title">{{ translate('What the customer gets') }}</h2>
                <p class="tps-card__subtitle">{{ translate('A top-up of exactly the minimum you set.') }}</p>
            </div>
        </div>
        <div class="tps-card__body">
            <div class="wbn-sum__hero">
                <span class="wbn-sum__hero-label">{{ translate('Lands in the wallet') }}</span>
                <span class="wbn-sum__hero-value" id="preview_total">{{ $zeroMoney }}</span>
            </div>
            <div class="wbn-sum__rows">
                <div class="wbn-sum__row">
                    <span class="wbn-sum__label">{{ translate('Customer adds') }}</span>
                    <span class="wbn-sum__value" id="preview_add">{{ $zeroMoney }}</span>
                </div>
                <div class="wbn-sum__row">
                    <span class="wbn-sum__label">{{ translate('Bonus paid by you') }}</span>
                    <span class="wbn-sum__value wbn-sum__value--add" id="preview_bonus">+ {{ $zeroMoney }}</span>
                </div>
            </div>
            <small class="tps-field__hint d-none" id="preview_cap"></small>
        </div>
    </div>

    <div class="tps-note tps-note--info mt-3">
        <i class="tio-info-outined"></i>
        <p>{{ translate('If several bonuses are running, the customer is given the biggest one — they do not stack.') }}</p>
    </div>
</div>
