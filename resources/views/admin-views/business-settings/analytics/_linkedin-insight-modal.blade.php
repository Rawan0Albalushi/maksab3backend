<div class="modal fade" id="modalForLinkedInInsight" tabindex="-1" aria-labelledby="modalForLinkedInInsight"
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
                                            src="{{ asset('public/assets/admin/img/svg/linkedin.svg') }}"
                                            loading="lazy" alt="">
                                    </div>
                                    <div class="text-dark mb-3">
                                        <h3 class="lh-base">
                                            {{ translate('How to get the linkedin partner id') }}
                                        </h3>
                                        <p class="opacity-75">
                                            {{ translate('to find your linkedin partner id, go to your ad account in campaign manager.') }}
                                            {{ translate('in the left menu, click data and then sources.') }}
                                            {{ translate('Next click on insight tag.') }}
                                            {{ translate('After that, select the i will use a Tag Manager dropdown and copy your partner id from the box provided.') }}
                                        </p>
                                    </div>

                                    <div class="text-dark mb-3">
                                        <h3 class="lh-base">
                                            {{ translate('Where to use the linkedin partner id') }}
                                        </h3>
                                        <p class="opacity-75">
                                            {{ translate('Open the marketing tools feature in your admin panel and follow the directions') }}:
                                        </p>
                                        <ol class="d-flex flex-column gap-2 opacity-75">
                                            <li>
                                                {{ translate('Go to the linkedin partner id section under marketing tools.') }}
                                            </li>
                                            <li>
                                                {{ translate('Turn on the toggle button.') }}
                                            </li>
                                            <li>
                                                {{ translate('Paste your linkedin partner id into the input box and click submit.') }}
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
