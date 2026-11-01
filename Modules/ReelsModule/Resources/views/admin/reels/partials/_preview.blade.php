@php
    $runsFact = $alwaysVisible ? translate('Always visible to customers') : ($existingDateRange ?: translate('messages.Not set'));
    $actionFact = $callToActionOn ? ($selectedProduct?->name ?: translate('messages.Not set')) : translate('No action button');
@endphp

<div class="rlf-aside">
    <div class="tps-card">
        <div class="tps-card__head">
            <span class="tps-card__brand"><i class="tio-play-circle-outlined"></i></span>
            <div class="tps-card__titles">
                <h2 class="tps-card__title">{{ translate('Reel preview') }}</h2>
                <p class="tps-card__subtitle">{{ translate('How the reel reads in the customer app feed.') }}</p>
            </div>
        </div>
        <div class="tps-card__body">
            <div class="rlf-stage">
                <div class="rlf-phone">
                    <div class="rlf-phone__notch"></div>
                    <div class="reel-preview-box {{ $reel->video_full_url ? 'active' : '' }} {{ $reel->thumbnail_full_url ? 'has-thumbnail' : '' }}" style="{{ $reel->thumbnail_full_url ? "background-image: url('{$reel->thumbnail_full_url}');" : '' }}">
                        <video @if($reel->video_full_url) src="{{ $reel->video_full_url }}" @endif controls class="reels-video" style="display:none;"></video>

                        <div class="reel-overlay">
                            <div class="rlf-empty">
                                <i class="tio-video-gallery-outlined"></i>
                                {{ translate('Your video and cover show here') }}
                            </div>
                            <button type="button" class="btn reels-play-btn">
                                <div class="d-flex justify-content-center align-items-center w-100 h-100">
                                    <i class="tio-play"></i>
                                </div>
                            </button>
                            <div class="rlf-rail" aria-hidden="true">
                                <span><i class="tio-heart"></i></span>
                                <span><i class="tio-comment"></i></span>
                                <span><i class="tio-share"></i></span>
                            </div>
                        </div>

                        <div class="reel-des-wrapper">
                            <div class="d-flex gap-2 align-items-center justify-content-between mb-2">
                                <div class="rlf-meta d-flex gap-2 align-items-center">
                                    <div class="reel-preview-thumbnail" data-reel-thumbnail="{{ $previewStoreLogo }}" style="{{ $previewStoreLogo ? "background-image: url('{$previewStoreLogo}');" : '' }}"></div>
                                    <div class="thumbnail-placeholder" style="{{ $previewStoreLogo ? 'display:none;' : '' }}"></div>
                                    <div class="reel-preview-title" data-reel-title="{{ $previewStoreName }}">{{ $previewStoreName }}</div>
                                    <div class="title-placeholder" style="{{ $previewStoreName ? 'display:none;' : '' }}"></div>
                                </div>
                                <button type="button" id="order-now-btn" class="btn px-2 py-1 fs-12 btn--warning text-white" style="{{ $callToActionOn ? '' : 'display:none;' }}">{{ $actionLabel ?? translate('messages.Order now') }}</button>
                            </div>
                            <div class="reel-preview-des">{{ $defaultDescription }}</div>
                            <div class="des-placeholder" style="{{ $defaultDescription ? 'display:none;' : '' }}">
                                <div class="mb-1"></div>
                                <div></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <dl class="rlf-facts">
                <div>
                    <dt>{{ translate('messages.Runs') }}</dt>
                    <dd data-preview-fact="runs">{{ $runsFact }}</dd>
                </div>
                <div>
                    <dt>{{ translate('Links to') }}</dt>
                    <dd data-preview-fact="action">{{ $actionFact }}</dd>
                </div>
            </dl>
        </div>
    </div>

    <div class="tps-note tps-note--info mt-3">
        <i class="tio-info-outined"></i>
        <p>{{ translate('Views, likes and orders earned by the reel are tracked on the reels list once it is live.') }}</p>
    </div>
</div>
