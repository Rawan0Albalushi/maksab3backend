<div class="modal fade" id="modalForFacebookMeta" tabindex="-1" aria-labelledby="modalForFacebookMeta" aria-hidden="true">
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
                                            src="{{ asset('public/assets/admin/img/svg/facebook.svg') }}"
                                            loading="lazy" alt="">
                                    </div>
                                    <div class="text-dark mb-3">
                                        <h3 class="lh-base">
                                            {{ translate('How to get the meta pixel id') }}
                                        </h3>
                                        <p class="opacity-75">
                                            {{ translate('to get your meta pixel id, log into your meta business manager account.') }}
                                            {{ translate('Go to the Events Manager, select your desired business account, and find data sources.') }}
                                            {{ translate('Your pixel id will be shown in the detailed section of the property you select.') }}
                                            {{ translate('Simply copy the pixel id from there.') }}
                                        </p>
                                    </div>

                                    <div class="text-dark mb-3">
                                        <h3 class="lh-base">
                                            {{ translate('Where to use the meta pixel id') }}
                                        </h3>
                                        <p class="opacity-75">
                                            {{ translate('Find the marketing tools feature from your admin panel and follow the instructions') }}:
                                        </p>
                                        <ol class="d-flex flex-column gap-2 opacity-75">
                                            <li>
                                                {{ translate('Navigate to the meta pixel id section under the marketing tools feature.') }}
                                            </li>
                                            <li>
                                                {{ translate('Turn on the toggle button.') }}
                                            </li>
                                            <li>
                                                {{ translate('Paste your meta pixel id into the input box and click submit.') }}
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
