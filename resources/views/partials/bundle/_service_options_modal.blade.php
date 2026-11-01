<div class="modal fade" id="serviceConfigModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0 align-items-start">
                <div class="d-flex align-items-start gap-3">
                    <div class="position-relative flex-shrink-0">
                        <img id="sc_image" class="rounded onerror-image" style="width:90px;height:90px;object-fit:cover"
                            data-onerror-image="{{ asset('public/assets/admin/img/100x100/1.png') }}"
                            src="#" alt="service">
                    </div>
                    <div>
                        <h4 id="sc_name" class="mb-2 font-bold"></h4>
                        <div class="d-flex align-items-baseline gap-2 mb-2">
                            <span id="sc_price" class="fs-24 font-bold"></span>
                        </div>
                        <span id="sc_category" class="d-block fs-14 opacity-75"></span>
                    </div>
                </div>
                <button type="button" class="btn-close w-30px h-30px border rounded-circle d-center bg--secondary p-0"
                    data-dismiss="modal" aria-label="{{ translate('Close') }}">&times;</button>
            </div>

            <div class="modal-body">
                <div id="sc_description_wrap" class="mb-4">
                    <strong class="d-block mb-2">{{ translate('messages.Description') }} :</strong>
                    <p class="mb-0" id="sc_description"></p>
                </div>

                <div id="sc_variant_wrap">
                    <strong class="d-block mb-2">{{ translate('Select variant') }}</strong>
                    <div id="sc_variants" class="d-flex flex-column gap-2"></div>
                </div>
            </div>

            <div class="modal-footer border-top d-flex align-items-center justify-content-between">
                <div>
                    <span class="fs-16 opacity-75">{{ translate('Total price') }} : </span>
                    <span id="sc_total" class="fs-24 font-bold"></span>
                </div>
                <button type="button" class="btn btn--primary min-w-120" id="sc_submit">
                    {{ translate('Add this service') }}
                </button>
            </div>
        </div>
    </div>
</div>
