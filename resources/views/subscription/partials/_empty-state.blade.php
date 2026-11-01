<div class="tps-card">
    <div class="sbd-empty__inner">
        <img class="sbd-empty__art" src="{{ asset('/public/assets/admin/img/empty-subscription.svg') }}" alt="">
        <h2 class="sbd-empty__title">{{ translate('Choose subscription plan') }}</h2>
        <p class="sbd-empty__text">
            {{ $isServiceModule ? translate('Choose a subscription package from the list. So that Providers get more options to join the business for the growth and success.') : translate('Choose a subscription package from the list, so stores get more options to join.') }}
        </p>
        <button type="button" data-toggle="modal" data-target="#plan-modal" class="btn btn--primary"><i class="tio-arrow-forward"></i> {{ translate('Choose subscription plan') }}</button>
    </div>
</div>
