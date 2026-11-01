@php
    $previewImage = $banner?->image_full_url;
    $previewTitle = $banner?->getRawOriginal('title');
    $previewZone = $banner?->zone?->name;
    $previewLink = match ($banner?->type) {
        'store_wise' => \App\Models\Store::find($banner->data)?->name,
        'item_wise' => null,
        default => $banner?->default_link,
    };
@endphp

<div class="bnr-aside">
    <div class="tps-card">
        <div class="tps-card__head">
            <span class="tps-card__brand"><i class="tio-devices-apple"></i></span>
            <div class="tps-card__titles">
                <h2 class="tps-card__title">{{ translate('Banner preview') }}</h2>
                <p class="tps-card__subtitle">{{ translate('Where the artwork sits on the customer app home screen.') }}</p>
            </div>
        </div>
        <div class="tps-card__body">
            <div class="bnr-stage">
                <div class="bnr-phone">
                    <div class="bnr-phone__bar">
                        <span class="bnr-phone__search"></span>
                        <span class="bnr-phone__avatar"></span>
                    </div>
                    <div class="bnr-slide {{ $previewImage ? 'has-image' : '' }}" id="banner-preview">
                        <img @if($previewImage) src="{{ $previewImage }}" @endif alt="">
                        <span class="bnr-slide__empty">
                            <i class="tio-image"></i>
                            {{ translate('Your artwork shows here') }}
                        </span>
                    </div>
                    <div class="bnr-dots">
                        <span class="is-active"></span>
                        <span></span>
                        <span></span>
                    </div>
                </div>
            </div>

            <dl class="bnr-facts">
                <div>
                    <dt>{{ translate('messages.Title') }}</dt>
                    <dd data-preview-fact="title">{{ $previewTitle ?: translate('messages.Not set') }}</dd>
                </div>
                <div>
                    <dt>{{ translate('messages.Zone') }}</dt>
                    <dd data-preview-fact="zone">{{ $previewZone ?: translate('messages.Not set') }}</dd>
                </div>
                <div>
                    <dt>{{ translate('Links to') }}</dt>
                    <dd data-preview-fact="link">{{ $previewLink ?: translate('messages.Not set') }}</dd>
                </div>
            </dl>
        </div>
    </div>

    <div class="tps-note tps-note--info mt-3">
        <i class="tio-info-outined"></i>
        <p>{{ translate('Featured banners run on the module home screen. Set that from the switch in the list once the banner is saved.') }}</p>
    </div>
</div>
