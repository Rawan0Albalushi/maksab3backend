{{-- Choose one item's variations and add-ons before it joins a list. Opened when an item is
     picked, and again from a selected card's Edit action; the body is rendered client side from
     the store-items payload.

     Shared by every promotion panel that picks items, so it carries nothing specific to one --
     no buy/get side, no offer id. The caller's own script fills it and reads the result. --}}
<div class="modal fade" id="foodConfigModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:420px" role="document">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0 p-3 align-items-start">
                <div class="d-flex align-items-start gap-2">
                    <div class="position-relative flex-shrink-0">
                        <img id="fc_image" class="rounded onerror-image" style="width:70px;height:70px;object-fit:cover"
                             data-onerror-image="{{asset('public/assets/admin/img/100x100/1.png')}}"
                             src="#" alt="food">
                        <span id="fc_veg_badge" class="badge position-absolute" style="top:6px;left:6px"></span>
                    </div>
                    <div>
                        <h4 id="fc_name" class="mb-1 fs-16 font-bold"></h4>
                        <div class="d-flex align-items-baseline gap-2 mb-1">
                            <del id="fc_old_price" class="fs-13 opacity-75"></del>
                            <span id="fc_price" class="fs-18 font-bold"></span>
                        </div>
                        <span id="fc_discount" class="d-block fs-12 opacity-75"></span>
                    </div>
                </div>
                <button type="button" class="btn-close w-24px h-24px border rounded-circle d-center bg--secondary p-0"
                        data-dismiss="modal" aria-label="Close">&times;</button>
            </div>

            <div class="modal-body p-3 pt-2">
                <div id="fc_description_wrap" class="mb-3">
                    <strong class="d-block mb-1 fs-14">{{ translate('messages.Description') }} :</strong>
                    <p class="mb-0 fs-13">
                        <span id="fc_description_short"></span>
                        <span id="fc_description_full" class="d-none"></span>
                        <a href="javascript:" id="fc_description_toggle" class="d-none"
                           style="text-decoration:underline">{{ translate('See more') }}</a>
                    </p>
                </div>

                {{-- One panel per variation group, honouring its own required/min/max rule. --}}
                <div id="fc_variations"></div>

                {{-- No food quantity here by design: the same food is added again rather than
                     given a count, so each add is one line. Add-ons do carry their own count,
                     which the stepper inside a selected card sets. --}}
                <div class="bg-global-gray rounded-8 p-2 mb-0" id="fc_addon_wrap">
                    <strong class="d-block mb-1 fs-14">{{ translate('messages.Addon') }}</strong>
                    <div id="fc_addons" class="d-flex flex-nowrap gap-2 pb-1" style="overflow-x:auto"></div>
                </div>
            </div>

            <div class="modal-footer border-top d-flex align-items-center justify-content-between p-3">
                <div>
                    <span class="fs-14 opacity-75">{{ translate('Total price') }} : </span>
                    <span id="fc_total" class="fs-18 font-bold"></span>
                </div>
                <button type="button" class="btn btn--primary min-w-120" id="fc_submit">
                    {{ translate('Add this item') }}
                </button>
            </div>
        </div>
    </div>
</div>
