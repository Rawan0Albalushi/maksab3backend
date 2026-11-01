<div class="modal fade" id="modalForTikTokPixel" tabindex="-1" aria-labelledby="modalForTikTokPixel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered max-w-655px">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0 d-flex justify-content-end">
                <button type="button" class="close border-0 btn-circle bg-section2 shadow-none" data-dismiss="modal"
                    aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body px-20 py-0 mb-30">
                <div class="swiper instruction-carousel pb-3">
                    <div class="swiper-wrapper">
                        <div class="swiper-slide">
                            <div class="swiper-slide">
                                <div class="">
                                    <div class="d-flex justify-content-center mb-5">
                                        <img height="60"
                                            src="{{ asset('public/assets/admin/img/svg/tiktok.svg') }}"
                                            loading="lazy" alt="">
                                    </div>
                                    <div class="text-dark mb-3">
                                        <h3 class="lh-base">
                                            {{ translate('How to get the tiktok pixel id') }}
                                        </h3>
                                        <p class="opacity-75">
                                            {{ translate('from the tiktok business account click on tools in the menu and select events.') }}
                                            {{ translate('Access the Events Manager by clicking on connect data sources in the top right corner.') }}
                                            {{ translate('from the popup, choose the web option and click next.') }}
                                            {{ translate('Now, create your pixel by selecting manual setup.') }}
                                            {{ translate('Find your pixel ID under Data sources in the left-hand menu.') }}
                                        </p>
                                    </div>

                                    <div class="text-dark mb-3">
                                        <h3 class="lh-base">
                                            {{ translate('Where to use the tiktok pixel id') }}
                                        </h3>
                                        <p class="opacity-75">
                                            {{ translate('Go to the marketing tools section in your admin panel and complete the steps') }}:
                                        </p>
                                        <ol class="d-flex flex-column gap-2 opacity-75">
                                            <li>
                                                {{ translate('Navigate to the tiktok pixel id section under marketing tools.') }}
                                            </li>
                                            <li>
                                                {{ translate('Turn on the toggle button.') }}
                                            </li>
                                            <li>
                                                {{ translate('Paste your tiktok pixel id into the input box and click submit.') }}
                                            </li>
                                        </ol>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
