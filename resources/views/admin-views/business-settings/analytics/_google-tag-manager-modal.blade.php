<div class="modal fade" id="modalForGoogleTagManager" tabindex="-1" aria-labelledby="modalForGoogleTagManager"
    aria-hidden="true">
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
                                            src="{{ asset('public/assets/admin/img/svg/google.svg') }}"
                                            loading="lazy" alt="">
                                    </div>
                                    <div class="text-dark mb-3">
                                        <h3 class="lh-base">
                                            {{ translate('How to get the Google Tag Manager container id') }}
                                        </h3>
                                        <p class="opacity-75">
                                            {{ translate('Log into Google Tag Manager') }}
                                            {{ translate('Open the container you wish to use.') }}
                                            {{ translate('the container id will be displayed in the top section of the container page after you open the admin tab.') }}
                                            {{ translate('Typical format') }}: GTM-XXXXXXX
                                            {{ translate('Copy it.') }}
                                        </p>
                                    </div>

                                    <div class="text-dark mb-3">
                                        <h3 class="lh-base">
                                            {{ translate('Where to use the Google Tag Manager container id') }}
                                        </h3>
                                        <p class="opacity-75">
                                            {{ translate('Go to the marketing tools section in your admin panel and complete the steps') }}:
                                        </p>
                                        <ol class="d-flex flex-column gap-2 opacity-75">
                                            <li>
                                                {{ translate('Navigate to the Google Tag Manager container id section under marketing tools.') }}
                                            </li>
                                            <li>
                                                {{ translate('Turn on the toggle button.') }}
                                            </li>
                                            <li>
                                                {{ translate('Paste your Google Tag Manager container id into the input box and click submit.') }}
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
