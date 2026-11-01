<div class="modal fade" id="modalForSnapchatPixel" tabindex="-1" aria-labelledby="modalForSnapchatPixel" aria-hidden="true">
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
                                            src="{{ asset('public/assets/admin/img/svg/snapchat.svg') }}"
                                            loading="lazy" alt="">
                                    </div>
                                    <div class="text-dark mb-3">
                                        <h3 class="lh-base">
                                            {{ translate('How to get the Snapchat pixel id') }}
                                        </h3>
                                        <p class="opacity-75">
                                            {{ translate('to get your Snapchat pixel id, log in to your Snapchat Ads manager.') }}
                                            {{ translate('Click on business in the top bar and select business details from the dropdown menu.') }}
                                            {{ translate('from the left hand menu, go to the pixels section.') }}
                                            {{ translate('If you have already created a pixel, select it from the available list') }}
                                            {{ translate('Your pixel id will be displayed at the top of the page, copy it by clicking on it.') }}
                                        </p>
                                    </div>

                                    <div class="text-dark mb-3">
                                        <h3 class="lh-base">
                                            {{ translate('Where to use the Snapchat pixel id') }}
                                        </h3>
                                        <p class="opacity-75">
                                            {{ translate('Open the marketing tools feature in your admin panel and follow the steps') }}:
                                        </p>
                                        <ol class="d-flex flex-column gap-2 opacity-75">
                                            <li>
                                                {{ translate('Go to the Snapchat pixel id section under marketing tools.') }}
                                            </li>
                                            <li>
                                                {{ translate('Turn on the toggle button.') }}
                                            </li>
                                            <li>
                                                {{ translate('Paste your Snapchat pixel id into the input box and click submit.') }}
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
