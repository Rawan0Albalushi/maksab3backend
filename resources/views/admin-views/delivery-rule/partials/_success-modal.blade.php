<div class="modal fade" id="rule-success-modal" data-backdrop="static">
    <div class="modal-dialog status-warning-modal">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true" class="tio-clear"></span>
                </button>
            </div>
            <div class="modal-body pb-5 pt-0">
                <div class="max-349 mx-auto mb-20 text-center">
                    <span class="rule-success-mark"><i class="tio-done"></i></span>
                    <h5 class="modal-title mb-3" id="rule-success-title">
                        @if ($isUpdate ?? false)
                            {{ translate('Updated successfully') }}
                        @else
                            {{ translate('Added successfully') }}
                        @endif
                    </h5>
                    <p>
                        @if ($isUpdate ?? false)
                            {{ translate('messages.Your delivery rule has been updated successfully and is now available for delivery charge calculations.') }}
                        @else
                            {{ translate('messages.Your delivery rule has been created successfully and is now available for delivery charge calculations.') }}
                        @endif
                    </p>
                    {{-- DESIGN RULE D6 — the dialog offers both onward steps: open the rule, or
                         go straight on to give the zone an ETA configuration, which is what makes
                         the rule's estimate answerable. --}}
                    <div class="btn--container justify-content-center">
                        <a href="javascript:" id="rule-success-view"
                            class="btn btn--reset min-w-120px">{{ translate('View details') }}</a>
                        <a href="javascript:" id="rule-success-eta"
                            class="btn btn--primary min-w-120px">{{ translate('messages.Setup ETA') }}</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
