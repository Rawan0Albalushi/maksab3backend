{{-- Confirm before a save drops a module from a zone setup (S19 · W3, W4).

     Removing a module from the picker and saving used to be silent, and what it costs depends on
     the setup: a delivery rule or an ETA configuration takes the module DARK in that zone, while
     free delivery, surge and additional charge only stop that one add-on applying to it. The
     sentence is the caller's, resolved server-side; everything else here is shared.

     Only modules this row is the ONLY active cover for are named. A module another active rule or
     configuration still covers is not losing anything, and warning about it would train the admin
     to click through the dialog without reading it.

     Required:
       $formId       the form to intercept, without the "#"
       $soloModules  array<int, string> — module id => name, the ones that would lose their cover
       $warningTitle heading
       $warningBody  the sentence, with :modules already substituted or carrying it

     The precedent is the Connect Module drawer's #connect-module-disconnect-warning-modal, and
     this follows it — the same shell, the same delegated confirm. It needs none of that dialog's
     relocation to <body>: these are full pages, not a `position: fixed` drawer, so the modal is
     not trapped in a stacking context below Bootstrap's backdrop. --}}

@php($soloModules = $soloModules ?? [])

<div class="modal fade" id="module-removal-warning-modal">
    <div class="modal-dialog status-warning-modal modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true" class="tio-clear"></span>
                </button>
            </div>
            <div class="modal-body pt-0 pb-5">
                <div class="max-349 mx-auto">
                    <div class="text-center">
                        <img class="mb-20" src="{{ asset('public/assets/admin/img/modal-error.png') }}" alt=""
                            width="70" height="70">
                        <h5 class="modal-title mb-3">{{ $warningTitle }}</h5>
                    </div>
                    <div class="text-center">
                        <p id="module-removal-warning-text"></p>
                    </div>
                    <div class="btn--container justify-content-center">
                        <button type="button" class="btn btn--reset min-w-120px"
                            data-dismiss="modal">{{ translate('messages.Cancel') }}</button>
                        <button type="button" id="module-removal-warning-confirm"
                            class="btn btn--primary min-w-120px">{{ translate('Save anyway') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    "use strict";
    (function () {
        // Module id => name, for the modules that would be left with no cover. Ids are strings
        // here because a select's values are; the picker is compared against them as strings.
        const SOLO = @json(array_map('strval', array_keys($soloModules)));
        const NAMES = @json($soloModules);
        const BODY = @json($warningBody);
        const FORM = '#{{ $formId }}';

        // What the form opened with. Read once, at load, so the comparison is against what is
        // SAVED rather than against whatever the picker held a moment ago.
        const OPENED_WITH = ($('#module_ids').val() || []).map(String);

        let confirmed = false;

        function losing() {
            const selected = ($('#module_ids').val() || []).map(String);

            return OPENED_WITH
                .filter(id => selected.indexOf(id) === -1)
                .filter(id => SOLO.indexOf(id) !== -1)
                .map(id => NAMES[id]);
        }

        /* Bound on the form ELEMENT, and bound before the page's own scripts are included, so it
           runs first: these forms carry validating and ajax submit handlers of their own, and a
           delegated handler on document would fire after the request had already gone out.
           stopImmediatePropagation() is what holds them back until the admin has answered. */
        $(FORM).on('submit', function (e) {
            if (confirmed) { return; }

            const modules = losing();

            if (modules.length === 0) { return; }

            e.preventDefault();
            e.stopImmediatePropagation();

            $('#module-removal-warning-text').text(BODY.replace(':modules', modules.join(', ')));
            $('#module-removal-warning-modal').modal('show');
        });

        $(document).on('click', '#module-removal-warning-confirm', function () {
            $('#module-removal-warning-modal').modal('hide');

            // The flag, not a second code path: the form goes through its own handlers exactly as
            // it would have without this dialog, validation and all.
            confirmed = true;
            $(FORM).submit();
            confirmed = false;
        });
    })();
</script>
