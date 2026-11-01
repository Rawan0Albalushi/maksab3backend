<script>
    "use strict";

    (function () {
        const HINTS = {
            [@json(\App\Models\DeliveryRule::METHOD_AREA)]: @json(translate('messages.A separate charge applies to each area in the selected zone.')),
            [@json(\App\Models\DeliveryRule::METHOD_ZIP)]: @json(translate('messages.A separate charge applies to each ZIP code in the selected zone.')),
            [@json(\App\Models\DeliveryRule::METHOD_DISTANCE)]: @json(translate('The charge scales with distance, bounded by the minimum and maximum.')),
            [@json(\App\Models\DeliveryRule::METHOD_FIXED)]: @json(translate('messages.This fixed charge applies to every order in the selected zone.')),
        };

        function activeMethod() {
            return $('.rule-method-input:checked').val();
        }

        function syncPanes() {
            const method = activeMethod();
            $('.rule-pane').addClass('d-none');
            $('.rule-pane[data-pane="' + method + '"]').removeClass('d-none');
            $('.rule-method').removeClass('is-selected');
            $('.rule-method-input:checked').closest('.rule-method').addClass('is-selected');
            $('#method-hint').text(HINTS[method] || '');
        }

        $(document).on('change', '.rule-method-input', syncPanes);
        syncPanes();

        // Everything _charge-table.blade.php needs to redraw a pane client-side.
        // Both shapes below must stay identical to that partial.
        const PANES = {
            [@json(\App\Models\DeliveryRule::METHOD_AREA)]: {
                wrapper: '#area-charge-wrapper',
                labelKey: 'name',
                field: 'area_charges',
                heading: @json(translate('Area name')),
                title: @json(translate('Area wise charge setup')),
                description: @json(translate('messages.Configure fixed delivery charges for each service area within a zone.')),
                hintText: @json(translate('messages.All areas under this zone will be listed automatically. Set the delivery charge for each area.')),
                hintLinkText: @json(translate('Setup area')),
                link: @json(route('admin.business-settings.zone.area.list')),
                emptyText: @json(translate('No areas have been created for this zone. To set up area wise charges, first create an area for this zone from')),
                emptyLinkText: @json(translate('Area setup')),
            },
            [@json(\App\Models\DeliveryRule::METHOD_ZIP)]: {
                wrapper: '#zip-charge-wrapper',
                labelKey: 'zip_code',
                field: 'zip_charges',
                heading: @json(translate('Zip code')),
                title: @json(translate('Zip code wise charge setup')),
                description: @json(translate('messages.Configure fixed delivery charges for each ZIP code within a zone.')),
                hintText: @json(translate('messages.All ZIP codes under this zone will be listed automatically. Set the delivery charge for each code.')),
                hintLinkText: @json(translate('Setup zip code')),
                link: @json(route('admin.business-settings.zone.zip-code.list')),
                emptyText: @json(translate('No ZIP codes have been created for this zone. To set up ZIP code wise charges, first create a ZIP code for this zone from')),
                emptyLinkText: @json(translate('Zip code setup')),
            },
        };

        // Amounts already typed or saved, kept per zone.
        //
        // Switching zone refetches that zone's areas and redraws the table, which
        // used to discard whatever was in the inputs. Leaving a zone and coming
        // back therefore wiped the rule's own saved charges — the editor's real
        // data — so what is on screen is stashed before every redraw and restored
        // when that zone is shown again. Seeded from the saved rule so its own
        // zone is right even before it is left once.
        const chargeStore = @json($chargeSeed);

        let shownZoneId = String($('#zone_id').val() ?? '');

        /** Stash what is currently in the inputs against the zone it belongs to. */
        function stashCharges(zoneId) {
            if (!zoneId) { return; }
            chargeStore[zoneId] = chargeStore[zoneId] || {};

            Object.values(PANES).forEach(function (pane) {
                const values = {};
                $(pane.wrapper).find('input[name^="' + pane.field + '["]').each(function () {
                    const id = $(this).attr('name').replace(pane.field + '[', '').replace(']', '');
                    if ($(this).val() !== '') { values[id] = $(this).val(); }
                });
                chargeStore[zoneId][pane.field] = values;
            });
        }

        function storedCharge(zoneId, field, rowId) {
            const v = ((chargeStore[zoneId] || {})[field] || {})[rowId];
            return v === undefined || v === null ? '' : v;
        }

        // ------------------------------------------------------------------
        // The parcel wizard.
        //
        // Weight Rules and Dimension Rules are extra STEPS on this same form, and they exist only
        // while a parcel-capable module is connected. That is a live reaction to the module
        // multi-select, not a page-load decision, so the markup is always rendered and visibility
        // is decided here.
        //
        // Without parcel the screen stays exactly what it was: one page, one Save Information
        // button, no stepper.
        // ------------------------------------------------------------------
        const PARCEL_MODULE_IDS = @json($parcelModuleIds);
        const LAST_STEP = 3;
        const SUBMIT_LABEL = @json(translate('messages.Submit'));
        const SAVE_LABEL = @json(translate('Save information'));

        let currentStep = 1;
        let stepperMoveTimer = null;

        function parcelConnected() {
            const selected = ($('#module_ids').val() || []).map(Number);
            return selected.some(id => PARCEL_MODULE_IDS.includes(id));
        }

        /**
         * `animate` is false only when the wizard is being revealed or rebuilt — see syncWizard().
         *
         * The DIRECTION is worked out here rather than passed in, so the three call sites stay as
         * they were and the jump-to-the-failing-step on submit animates correctly too: it is a
         * move like any other, and which way it goes is simply which step it lands on.
         */
        function showStep(step, animate = true) {
            const $stepper = $('#rule-stepper');
            const from = currentStep;

            $stepper.removeClass('is-advancing is-reversing');
            clearTimeout(stepperMoveTimer);

            if (animate && step !== from) {
                $stepper.addClass(step > from ? 'is-advancing' : 'is-reversing');
                // Cleared once the move is over. Leaving the class on would apply the stagger's
                // transition-delay to the NEXT move as well, and the delay is only correct for
                // the direction it was set for.
                stepperMoveTimer = setTimeout(function () {
                    $stepper.removeClass('is-advancing is-reversing');
                }, 800);
            }

            currentStep = step;
            $('.rule-step').addClass('d-none');
            $('.rule-step[data-step="' + step + '"]').removeClass('d-none');

            $('[data-step-marker]').each(function () {
                const marker = Number($(this).data('step-marker'));
                $(this).toggleClass('is-done', marker < step).toggleClass('is-current', marker === step);
            });
            $('[data-line]').each(function () {
                $(this).toggleClass('is-done', Number($(this).data('line')) <= step);
            });

            // Step 1 keeps the original back-to-list link; later steps step backwards instead.
            $('#rule-back-link').toggleClass('d-none', step !== 1);
            $('#rule-step-prev').toggleClass('d-none', step === 1);

            // The design labels the last step's primary button "Next"; the annotation corrects it
            // to Submit, which is what actually happens there.
            $('#rule-step-next').toggleClass('d-none', step === LAST_STEP);
            $('#rule-submit').toggleClass('d-none', step !== LAST_STEP);
            $('#rule-submit-label').text(SUBMIT_LABEL);
        }

        function syncWizard() {
            if (parcelConnected()) {
                const $stepper = $('#rule-stepper');

                // Reappearing is not a step move. Transitions are suppressed for one frame so the
                // row arrives already in its resting state instead of animating into it — which is
                // what a stepper left on step 3, switched off and switched back on, would do.
                $stepper.addClass('rule-stepper--instant').removeClass('d-none');
                showStep(Math.min(currentStep, LAST_STEP), false);

                requestAnimationFrame(function () {
                    requestAnimationFrame(function () {
                        $stepper.removeClass('rule-stepper--instant');
                    });
                });

                return;
            }

            // Back to the plain single-page form.
            $('#rule-stepper').addClass('d-none');
            $('.rule-step').addClass('d-none');
            $('.rule-step[data-step="1"]').removeClass('d-none');
            currentStep = 1;
            $('#rule-back-link').removeClass('d-none');
            $('#rule-step-prev').addClass('d-none');
            $('#rule-step-next').addClass('d-none');
            $('#rule-submit').removeClass('d-none');
            $('#rule-submit-label').text(SAVE_LABEL);
        }

        const REQUIRED = {
            name: @json(translate('messages.Rule name is required')),
            modules: @json(translate('messages.Please select at least one module')),
            minimum: @json(translate('messages.Minimum delivery charge is required')),
            method: @json(translate('messages.Please select a delivery method')),
            perUnit: @json(translate('messages.Per unit delivery charge is required')),
            fixed: @json(translate('messages.Delivery charge is required')),
        };

        function isBlank($el) {
            return String($el.val() ?? '').trim() === '';
        }

        function firstEmptyCharge($scope) {
            let problem = null;

            $scope.find('input[type="number"]').each(function () {
                if (problem || !isBlank($(this))) { return; }
                const label = $(this).closest('tr').find('td').eq(1).text().trim();
                problem = { el: $(this), message: REQUIRED.fixed + ': ' + label };
            });

            return problem;
        }

        /** Returns the first problem on a step, or null. Order matches the fields on screen. */
        function validateStep(step) {
            if (step === 1) {
                if (isBlank($('#name'))) { return { el: $('#name'), message: REQUIRED.name }; }
                if (!($('#module_ids').val() || []).length) {
                    return { el: $('#module_ids'), message: REQUIRED.modules };
                }
                if (isBlank($('#minimum_delivery_charge'))) {
                    return { el: $('#minimum_delivery_charge'), message: REQUIRED.minimum };
                }

                const method = activeMethod();
                if (!method) { return { el: $('.rule-method-input').first(), message: REQUIRED.method }; }

                // Only the chosen method's own fields are required — a hidden pane must never
                // block a save with a value the admin cannot see.
                if (method === @json(\App\Models\DeliveryRule::METHOD_DISTANCE)) {
                    if (isBlank($('#per_km_charge'))) { return { el: $('#per_km_charge'), message: REQUIRED.perUnit }; }
                    // The maximum is OPTIONAL — blank means no upper limit, which is what the
                    // request rule, the nullable column and DeliveryRuleService::distanceCharge()
                    // all agree on. Requiring it here blocked the very save the server accepts.
                    return null;
                }
                if (method === @json(\App\Models\DeliveryRule::METHOD_FIXED)) {
                    return isBlank($('#fixed_charge')) ? { el: $('#fixed_charge'), message: REQUIRED.fixed } : null;
                }

                // Area or ZIP: every listed row carries a required charge.
                return firstEmptyCharge($('.rule-pane[data-pane="' + method + '"]'));
            }

            // Steps 2 and 3 exist only with parcel, and only bite when the tier is switched ON —
            // requiring a value for a disabled feature would block the save for nothing.
            const field = step === 2 ? 'weight' : 'dimension';
            if (!$('#' + field + '_charge_status').is(':checked')) { return null; }

            return firstEmptyCharge($('#' + field + '-table-wrapper'));
        }

        function reportProblem(problem) {
            toastr.error(problem.message, { CloseButton: true, ProgressBar: true });

            // select2 hides the real <select>, so focusing it does nothing visible.
            const $target = problem.el.attr('id') === 'module_ids'
                ? $('#module_ids').next('.select2').find('.select2-selection')
                : problem.el;

            if ($target.length) {
                $('html, body').animate({ scrollTop: Math.max(0, $target.offset().top - 160) }, 200);
                $target.trigger('focus');
            }
        }

        $(document).on('click', '#rule-step-next', function () {
            const problem = validateStep(currentStep);

            if (problem) {
                reportProblem(problem);
                return;
            }

            showStep(Math.min(currentStep + 1, LAST_STEP));
        });

        // Submitting checks every step, not just the one on screen — a field can be cleared after
        // its step was passed. The failing step is brought back into view before the message.
        //
        // This is the ONLY submit handler on this form (it used to be one of two independently
        // bound ones — see git history). The second handler ran the actual AJAX save with no
        // validation of its own, so calling preventDefault() here never stopped it: this handler
        // would show the error toast while the other silently saved the incomplete rule anyway
        // (TC_246). Validation and submission are now one function so a failed check genuinely
        // blocks the save.
        $(document).on('submit', '#delivery-rule-form', function (e) {
            e.preventDefault();

            const lastStep = parcelConnected() ? LAST_STEP : 1;

            for (let step = 1; step <= lastStep; step++) {
                const problem = validateStep(step);

                if (!problem) { continue; }

                if (parcelConnected() && step !== currentStep) { showStep(step); }
                reportProblem(problem);

                return;
            }

            const $form = $(this);
            const $submit = $form.find('button[type="submit"]');
            $submit.prop('disabled', true);

            $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

            $.post({
                url: $form.attr('action'),
                data: new FormData(this),
                cache: false,
                contentType: false,
                processData: false,
                success: function (data) {
                    if (data.errors) {
                        $submit.prop('disabled', false);
                        data.errors.forEach(function (err) { toastr.error(err.message); });
                        return;
                    }
                    $('#rule-success-view').attr('href', data.redirect);
                    $('#rule-success-eta').attr('href', data.eta_redirect);
                    $('#rule-success-modal').modal('show');

                    // The rule is saved and the form has nothing left to do, so dismissing
                    // the dialog returns the admin to the list. It waits to be dismissed
                    // rather than leaving on a timer: D6 puts two onward steps on this
                    // dialog, and neither is reachable if it takes itself away.
                    $('#rule-success-modal').one('hidden.bs.modal', function () {
                        window.location.href = @json(route('admin.business-settings.zone.delivery-rule.list'));
                    });
                },
                error: function (xhr) {
                    $submit.prop('disabled', false);
                    const errs = xhr.responseJSON && xhr.responseJSON.errors;
                    if (errs) { errs.forEach(function (e) { toastr.error(e.message); }); }
                    else { toastr.error(@json(translate('messages.An unexpected error occurred'))); }
                }
            });
        });

        $(document).on('click', '#rule-step-prev', function () {
            showStep(Math.max(currentStep - 1, 1));
        });

        // A step whose Status is off hides its table rather than disabling each input: the server
        // ignores the charges when the toggle is off, so leaving stale values visible would
        // suggest they still apply.
        $(document).on('change', '.parcel-step-toggle', function () {
            $($(this).data('target')).toggleClass('d-none', !$(this).is(':checked'));
        });

        $(document).on('change', '#module_ids', syncWizard);
        syncWizard();

        const CURRENCY = @json($currencySymbol);
        const SL = @json(translate('messages.SL'));
        const CHARGE = @json(translate('Delivery charge'));
        const EX = @json(translate('messages.Ex') . ': 2');

        function esc(value) {
            return $('<div>').text(value == null ? '' : value).html();
        }

        // The charge rows belong to the chosen zone, so they are refetched rather
        // than shipped for every zone up front.
        $(document).on('change', '#zone_id', function () {
            const zoneId = String($(this).val() ?? '');
            if (!zoneId) { return; }

            stashCharges(shownZoneId);
            shownZoneId = zoneId;

            {{-- From the route, not a literal path — see the note in the additional-charge form. --}}
            $.get('{{ rtrim(route('admin.business-settings.zone.delivery-rule.coverage', ['zoneId' => 0]), '0') }}' + zoneId, {
                rule_id: $('#delivery-rule-form').data('rule-id') || ''
            }, function (data) {
                rebuild(PANES[@json(\App\Models\DeliveryRule::METHOD_AREA)], data.areas, zoneId);
                rebuild(PANES[@json(\App\Models\DeliveryRule::METHOD_ZIP)], data.zip_codes, zoneId);
                rebuildModules(data.modules, data.modules_empty_message);
            });
        });

        // Modules already carrying a rule in the chosen zone are hidden, so the list is redrawn
        // whenever the zone changes. A selection that is no longer offered is dropped rather than
        // silently posted — it would fail the D1 guard anyway.
        function rebuildModules(modules, allConfiguredMessage) {
            const $select = $('#module_ids');
            const keep = ($select.val() || []).map(String);

            $select.empty();
            (modules || []).forEach(function (module) {
                $select.append(
                    $('<option>', { value: module.id, text: module.name })
                        .prop('selected', keep.indexOf(String(module.id)) !== -1)
                );
            });
            $select.trigger('change.select2');

            const none = !modules || modules.length === 0;
            $('#modules-all-configured').toggleClass('d-none', !none).find('span').text(allConfiguredMessage);
            $select.closest('.col-md-6').find('.select2-container').toggleClass('d-none', none);

            // The list above is rebuilt with `change.select2`, which notifies select2 and NOTHING
            // else — the plain `change` handler that runs syncWizard never fires. Without this
            // call, switching to a zone with no parcel module left the stepper on screen and Next
            // enabled, and clicking it walked the admin into Weight Rules for a grocery rule.
            syncWizard();
        }

        function rebuild(pane, rows, zoneId) {
            const $wrapper = $(pane.wrapper);

            if (!rows || rows.length === 0) {
                $wrapper.html(
                    '<div class="rule-coverage-empty">' +
                    '<h5 class="rule-coverage-empty__title">' + esc(pane.title) + '</h5>' +
                    '<p class="rule-coverage-empty__text">' + esc(pane.emptyText) + '</p>' +
                    '<a href="' + pane.link + '">' + esc(pane.emptyLinkText) + '</a>' +
                    '</div>'
                );
                return;
            }

            let body = '';
            rows.forEach(function (row, i) {
                body += '<tr>' +
                    '<td class="rule-charge-table__sl">' + (i + 1) + '</td>' +
                    '<td>' + esc(row[pane.labelKey]) + '</td>' +
                    '<td><input type="number" step="0.01" min="0" name="' + pane.field + '[' + row.id + ']" ' +
                    'class="form-control h-45" placeholder="' + esc(EX) + '" value="' +
                    esc(storedCharge(zoneId, pane.field, row.id)) + '"></td>' +
                    '</tr>';
            });

            $wrapper.html(
                '<div class="row g-3">' +
                '<div class="col-md-4">' +
                '<h5 class="surge-section__title">' + esc(pane.title) + '</h5>' +
                '<p class="surge-section__subtitle mb-0">' + esc(pane.description) + '</p>' +
                '<div class="rule-hint"><img src="{{ asset('public/assets/admin/img/svg/bulb.svg') }}" class="svg" alt=""><span>' + esc(pane.hintText) +
                ' <a href="' + pane.link + '">' + esc(pane.hintLinkText) + '</a>.</span></div>' +
                '</div>' +
                '<div class="col-md-8">' +
                '<div class="rule-charge-table"><table class="rule-charge-table__table"><thead><tr>' +
                '<th class="rule-charge-table__sl">' + esc(SL) + '</th>' +
                '<th>' + esc(pane.heading) + ' <span class="text-danger">*</span></th>' +
                '<th>' + esc(CHARGE) + ' (' + esc(CURRENCY) + ') <span class="text-danger">*</span></th>' +
                '</tr></thead><tbody>' + body + '</tbody></table></div>' +
                '</div></div>'
            );
        }
    })();

</script>
