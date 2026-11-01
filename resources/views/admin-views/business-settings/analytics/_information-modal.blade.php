<div class="modal fade" id="getInformationModal" tabindex="-1" aria-labelledby="getInformationModal" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered max-w-655px">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0 pt-2 px-2 d-flex justify-content-end">
                <button type="button" class="close border-0 btn-circle bg-section2 shadow-none" data-dismiss="modal"
                    aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body px-4 px-sm-5 pt-0">
                <div class="swiper instruction-carousel pb-3">
                    <div class="swiper-wrapper">
                        <div class="swiper-slide">
                            <div class="d-flex flex-column align-items-center gap-2">
                                <img width="80" class="mb-3"
                                    src="{{ asset('public/assets/back-end/img/modal/instruction.png') }}"
                                    loading="lazy" alt="">
                                <div>
                                    <h3 class="lh-md mb-3 text-capitalize text-start">
                                        {{ translate('Step by step guide') }}
                                    </h3>
                                    <ol class="d-flex flex-column px-4 gap-2 mb-4">
                                        <li> {{ translate('Open the advertising manager or platform you want to integrate (e.g., Meta Ads, Snapchat Ads, Google Analytics).') }}
                                        </li>
                                        <li> {{ translate('Locate and copy the necessary tracking ids from their respective settings.') }}
                                        </li>
                                        <li> {{ translate('Turn on the toggle for the platform you want to activate.') }}
                                        </li>
                                        <li> {{ translate('Paste the code into the input box and click submit.') }}
                                        </li>
                                        <li> {{ translate('If you no longer want to track a platforms analytics turn the toggle off anytime.') }}
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
