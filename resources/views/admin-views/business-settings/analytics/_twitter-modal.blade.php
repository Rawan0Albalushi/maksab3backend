<div class="modal fade" id="modalForTwitterPixel" tabindex="-1" aria-labelledby="modalForTwitterPixel" aria-hidden="true">
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
                                            src="{{ asset('public/assets/admin/img/svg/twitter.svg') }}"
                                            loading="lazy" alt="">
                                    </div>
                                    <div class="text-dark mb-3">
                                        <h3 class="lh-base">
                                            {{ translate('How to get the x (twitter) pixel id') }}
                                        </h3>
                                        <p class="opacity-75">
                                            {{ translate('to get your x (twitter) pixel id, log in to your twitter ads account.') }}
                                            {{ translate('from the top navigation, click on tools and select Events Manager.') }}
                                            {{ translate('Once in the Events Manager, create your pixel id by clicking on add event source.') }}
                                            {{ translate('Choose the install with pixel code option and press save.') }}
                                            {{ translate('Your pixel id will then be generated, and you can copy it from the interface.') }}
                                        </p>
                                    </div>

                                    <div class="text-dark mb-3">
                                        <h3 class="lh-base">
                                            {{ translate('Where to use the x (twitter) pixel id') }}
                                        </h3>
                                        <p class="opacity-75">
                                            {{ translate('Go to the marketing tools section in your admin panel and complete the steps') }}:
                                        </p>
                                        <ol class="d-flex flex-column gap-2 opacity-75">
                                            <li>
                                                {{ translate('Navigate to the x (twitter) pixel id section under marketing tools.') }}
                                            </li>
                                            <li>
                                                {{ translate('Turn on the toggle button.') }}
                                            </li>
                                            <li>
                                                {{ translate('Paste your x (twitter) pixel id into the input box and click submit.') }}
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
