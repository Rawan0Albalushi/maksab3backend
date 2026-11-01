{{-- The shared .form-alert helper renders a SweetAlert, which does not match the design, so
     deletion gets its own confirm modal — the same one Area and Dimension Setup use. --}}
<div class="modal fade" id="vehicle-delete-modal">
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
                        <img class="mb-20" src="{{ asset('public/assets/admin/img/modal/delete-icon.png') }}" alt="">
                        <h5 class="modal-title mb-3">{{ translate('Want to delete this vehicle category?') }}</h5>
                    </div>
                    <div class="text-center">
                        <p>{{ translate('messages.Are you sure you want to delete this vehicle category & remove it permanently?') }}</p>
                    </div>
                    <div class="btn--container justify-content-center">
                        <button type="button" class="btn btn--reset min-w-120px"
                            data-dismiss="modal">{{ translate('messages.No') }}</button>
                        <button type="button" id="vehicle-delete-confirm"
                            class="btn btn--danger min-w-120px">{{ translate('messages.Delete') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
