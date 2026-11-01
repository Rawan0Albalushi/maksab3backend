{{-- Turning a rule ON: the zone's current rule is displaced automatically, so
     this only needs confirming. --}}
<div class="modal fade" id="rule-turn-on-modal">
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
                        <img class="mb-20" src="{{ asset('public/assets/admin/img/price-emty.png') }}" alt="">
                        <h5 class="modal-title mb-3">{{ translate('Do you want to turn on the rule?') }}</h5>
                    </div>
                    <div class="text-center">
                        <p>{{ translate('messages.If you turn on this rule current charge rule will be turned off automatically & all charge will calculate based on new rule.') }}
                        </p>
                    </div>
                    <div class="btn--container justify-content-center">
                        <button type="button" class="btn btn--reset min-w-120px"
                            data-dismiss="modal">{{ translate('messages.Cancel') }}</button>
                        <button type="button" id="rule-turn-on-confirm"
                            class="btn btn--primary min-w-120px">{{ translate('Turn on') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Turning a rule OFF: a zone must keep exactly one active rule, so the admin
     has to name the replacement before this can proceed. --}}
<div class="modal fade" id="rule-turn-off-modal">
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
                        <img class="mb-20" src="{{ asset('public/assets/admin/img/price-emty.png') }}" alt="">
                        <h5 class="modal-title mb-3">{{ translate('Do you want to turn off the rule?') }}</h5>
                    </div>
                    <div class="text-center">
                        <p>{{ translate('messages.If you turn off this rule you must choose another rule for this zone. Please select another rule from bellow.') }}
                        </p>
                    </div>

                    <div class="form-group text-left">
                        <label class="input-label" for="replacement_id">{{ translate('Select rule') }}</label>
                        <select id="replacement_id" class="form-control h--45px"></select>
                        <span class="d-none text-danger fs-12 mt-1" id="replacement-empty">
                            {{ translate('messages.This zone has no other rule to switch to. Create one first.') }}
                        </span>
                    </div>

                    <div class="btn--container justify-content-center">
                        <button type="button" class="btn btn--reset min-w-120px"
                            data-dismiss="modal">{{ translate('messages.Cancel') }}</button>
                        <button type="button" id="rule-turn-off-confirm"
                            class="btn btn--primary min-w-120px">{{ translate('messages.Update') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Shown instead of the turn-off modal when the toggled row is the only active rule for one of
     its modules. A warning with a single way out: there is nothing to pick from, because the
     hand-over is refused. Same shell as the ETA configuration's locked-status modal. --}}
<div class="modal fade" id="rule-status-locked-modal">
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
                        <h5 class="modal-title mb-3">{{ translate('messages.This delivery rule cannot be turned off') }}</h5>
                    </div>
                    <div class="text-center">
                        <p id="rule-status-locked-text"></p>
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
