<div class="modal fade" id="product_warning">
    <div class="modal-dialog modal-dialog-centered status-warning-modal">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true" class="tio-clear"></span>
                </button>
            </div>
            <div class="modal-body pb-5 pt-0">
                <div class="max-349 mx-auto mb-20">
                    <div>
                        <div class="text-center">
                            <img src="{{asset('/public/assets/admin/img/subscription-plan/package-status-disable.png')}}" class="mb-20">
                            <h5 class="modal-title" ></h5>
                        </div>
                        <div class="text-center">
                            <h3>{{ translate('Are you sure you want to switch to this plan?') }}</h3>
                            <p>{{ translate('You are about to downgrade your plan. After subscribing to this plan your oldest') }} <span id="disable_item_count"></span> {{ $isServiceModule ? translate('messages.Services will be inactivated.') : translate('Items will be inactivated.') }} </p>
                        </div>
                    </div>
                    <div class="btn--container justify-content-center">
                        <button  id="continue_btn" class="btn btn-outline-primary min-w-120" data-dismiss="modal" >
                            {{translate("Continue")}}
                        </button>
                        <button  class="btn btn--primary min-w-120  shift_package"  id="back_to_planes" data-dismiss="modal" >{{translate('Go back')}}</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
