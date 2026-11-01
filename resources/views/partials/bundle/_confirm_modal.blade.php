<div class="modal fade" id="bundleConfirmModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered status-warning-modal" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true" class="tio-clear"></span>
                </button>
            </div>
            <div class="modal-body pb-5 pt-0">
                <div class="max-349 mx-auto mb-20">
                    <div class="text-center">
                        <img class="mb-20" src="{{ asset('public/assets/admin/img/warning.png') }}" alt=""
                            style="max-width:64px;height:auto">
                        <h5 class="modal-title mb-3" id="bundle-confirm-title"></h5>
                    </div>
                    <div class="text-center">
                        <p id="bundle-confirm-body"></p>
                    </div>
                    <div class="btn--container justify-content-center">
                        <button type="button" class="btn btn--reset min-w-120px" data-dismiss="modal">
                            {{ translate('messages.Cancel') }}
                        </button>
                        <button type="button" id="bundle-confirm-ok" class="btn btn--primary min-w-120px">
                            {{ translate('OK') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
