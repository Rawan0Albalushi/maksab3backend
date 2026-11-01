{{--
    Why an approved bundle is not reaching customers, spelled out on demand.

    The reasons started life in a hover tooltip on the row's warning icon, which was the wrong
    container for them: several sentences, some naming a food and a time window, none of them
    readable while the pointer had to stay still. They are shown the way every other blocking
    condition in the panel is shown - the surge price overlap modal - so the admin and the
    vendor read the same layout they already know.

    One modal per page, filled from whichever warning icon was clicked; the reasons ride on
    the icon itself, so opening this costs no request.

    The copy names the promotion rather than calling it "the bundle", so the same strings read
    correctly whichever list the vendor opened them from.
--}}
{{-- Named by whichever list included it, so the copy reads correctly on both. --}}
@php($promotion = ['promotion' => $promotionLabel ?? translate('messages.BOGO offer')])
<div class="modal fade" id="promo-visibility-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog status-warning-modal">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true" class="tio-clear"></span>
                </button>
            </div>
            <div class="modal-body pb-5 pt-0">
                <div class="max-349 mx-auto mb-20">
                    <div class="text-center">
                        <img class="mb-20" src="{{ asset('public/assets/admin/img/modal-error.png') }}" alt="">
                        <h5 class="modal-title mb-3">{{ translate('Offer not visible to customers') }}</h5>
                        <p>{{ translate('messages.This promotion is approved but customers can not see or order it right now') }}</p>
                    </div>

                    <div class="alert-soft-warning alert--note rounded-8 p-3 mb-20 fs-14 text-left">
                        <strong class="d-block mb-1">{{ translate('messages.Reasons the promotion is on hold') }}</strong>
                        <ul class="mb-0 pl-3" id="promo_visibility_reasons"></ul>
                    </div>

                    <p class="text-center">{{ translate('messages.The promotion returns to the customer app automatically once these are resolved') }}</p>

                    <div class="btn--container justify-content-center">
                        <button type="button" class="btn btn--primary min-w-120px"
                                data-dismiss="modal">{{ translate('messages.Okay') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('script_2')
    <script>
        "use strict";

        // Delegated: the admin table is rebuilt by pagination and the vendor list by search,
        // so binding to the icons themselves would go stale on the first page change.
        $(document).on('click', '.promo-visibility-warning', function () {
            const reasons = $(this).data('reasons') || [];
            const $list = $('#promo_visibility_reasons').empty();

            // .text() rather than .html(): a reason names foods and variations the store
            // typed itself, and nothing here is meant to render as markup.
            reasons.forEach(function (reason) {
                $list.append($('<li>').text(reason));
            });

            $('#promo-visibility-modal').modal('show');
        });
    </script>
@endpush
