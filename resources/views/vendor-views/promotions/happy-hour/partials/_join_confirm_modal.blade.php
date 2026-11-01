{{--
    What joining a happy hour commits the store to, asked before it does.

    A generic "are you sure" was not enough here. Two of the three consequences are not visible
    anywhere on the screen the store is looking at, and both cost it money: the window REPLACES the
    store's own discount rather than adding to it, and the whole discount is borne by the store --
    there is no admin share on any business model, unlike an ordinary item discount.

    Raised from Join and from Approval alike: accepting the admin's invitation is the same
    commitment arrived at from the other direction.

    $proMemberEnabled - the Pro Member line only applies where the feature is switched on.
--}}
<div class="modal fade" id="happyHourJoinModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 rounded-8">
            <div class="modal-body px-4 pb-4 pt-3">
                {{-- A circular grey close, per the design, rather than the bare glyph: the same
                     control the offer drawers already use. --}}
                <button type="button"
                        class="btn-close w-25px h-25px border rounded-circle d-center bg--secondary ml-auto fz-15px p-0"
                        data-dismiss="modal" aria-label="{{ translate('Close') }}">&times;</button>

                {{-- bg-danger, not bg--danger: the double-dash form is not a class this theme
                     defines, so the circle drew transparent and its white glyph disappeared into
                     the modal -- the alert mark was simply missing from the dialog. --}}
                <div class="d-flex justify-content-center mt-2 mb-4">
                    <span class="d-center rounded-circle bg-danger text-white font-bold"
                          style="width:72px;height:72px;font-size:40px;line-height:1">!</span>
                </div>

                <h4 class="font-bold text-center mb-3">{{ translate('Join happy hour campaign') }}?</h4>

                <div class="alert-soft-warning alert--note rounded-8 p-3 mb-0 fs-14">
                    <ul class="mb-0 pl-3">
                        <li>{{ translate('The happy hour campaign will replace all other offers on eligible items') }}.</li>
                        @if($proMemberEnabled)
                            <li>{{ translate('Pro member discounts will stay for eligible customers') }}.</li>
                        @endif
                    </ul>

                    <div class="alert alert-soft-warning alert--note d-flex align-items-start gap-2 py-2 mb-0 mt-3">
                        <i class="tio-error"></i>
                        <span>{{ translate('You will bear the full happy hour discount cost') }}.</span>
                    </div>
                </div>
            </div>

            {{-- Centred pair, not stretched: the design gives each button its own width and the
                 dialog keeps its margins. --}}
            <div class="modal-footer border-0 pt-0 px-4 pb-4 d-flex justify-content-center gap-3">
                <button type="button" class="btn btn--reset h--45px m-0 px-5" data-dismiss="modal">
                    {{ translate('messages.Cancel') }}
                </button>
                <button type="button" class="btn btn--primary h--45px m-0 px-5" id="happy_hour_join_confirm">
                    {{ translate('messages.Join') }}
                </button>
            </div>
        </div>
    </div>
</div>
