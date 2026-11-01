{{-- Shown instead of the confirm dialog when the toggled row is the only active estimate for one
     of its modules. A warning with a single way out: there is nothing to confirm, because the
     action is refused. Same shell as the delete modal so the two read as one family. --}}
<div class="modal fade" id="eta-status-locked-modal">
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
                        <img class="mb-20" src="{{ asset('public/assets/admin/img/modal/warning.png') }}"
                            onerror="this.src='{{ asset('public/assets/admin/img/modal/delete-icon.png') }}'" alt="">
                        <h5 class="modal-title mb-3">{{ translate('messages.This ETA configuration cannot be turned off') }}</h5>
                    </div>
                    <div class="text-center">
                        <p id="eta-status-locked-text"></p>
                    </div>
                    <div class="btn--container justify-content-center">
                        <button type="button" class="btn btn--primary min-w-120px"
                            data-dismiss="modal">{{ translate('messages.Got it') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
