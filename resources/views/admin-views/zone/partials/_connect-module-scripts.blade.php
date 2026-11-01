<script>
    "use strict";

    /* ── Connect Module drawer ──────────────────────────────────────────────────
       offcanvas.js slides the panel; the form itself is fetched here so one panel
       serves every row. Same shape as Weight and Dimension Setup. */
    /* Two callers: the row's Connect Module button, and the zone form once it has
       created a zone — a new zone shows nothing to customers until its modules are
       connected, so the panel opens on the spot rather than being pointed at. */
    window.openConnectModuleDrawer = function (url) {
        // The spinner the zone-save handler in index.blade.php already uses. Without it this
        // was a bare $.get: on a slow line the button looked dead, and a failed request left
        // the admin with no drawer and no explanation at all.
        $('#loading').show();

        $.get(url, function (data) {
            // A copy left on <body> by a previous open — see relocateDisconnectWarningModal().
            // Dropped before the new markup lands so two elements never share the id.
            $('body > #connect-module-disconnect-warning-modal').remove();
            $('#connect-module-view').html(data.view);
            relocateDisconnectWarningModal();
            // The picker and its COD rows only exist once the markup has landed.
            $('#connect_modules').select2({ dropdownParent: $('#offcanvas__connect_module') });
            renderCodLimitRows();
            if (window.renderModuleNotes) { window.renderModuleNotes(); }
            if (window.syncCodLimitCardVisibility) { window.syncCodLimitCardVisibility(); }
            $('#offcanvas__connect_module').addClass('open');
            $('#offcanvasOverlay').addClass('show');
            $('body').addClass('modal-open');
        }).fail(function () {
            toastr.error(@json(translate('Could not open connect module. Please try again.')));
        }).always(function () {
            $('#loading').hide();
        });
    };

    /** The drawer's URL for one zone, from the named route rather than a hand-built path. */
    window.connectModuleUrl = function (zoneId) {
        return @json(route('admin.business-settings.zone.connect-module', ['id' => '__ZONE__']))
            .replace('__ZONE__', encodeURIComponent(zoneId));
    };

    // Delegated, and it opens the panel itself: the rows are re-rendered by the zone
    // filter, and offcanvas.js binds directly at ready, so a row drawn after page
    // load would otherwise have a dead button.
    $(document).on('click', '.offcanvas-trigger[data-url]', function (e) {
        e.preventDefault();
        // Bootstrap leaves the row's tooltip on screen once the pointer stops moving over
        // the button, and the drawer then opens on top of a floating "Connect module".
        // Wrapped because a row redrawn by the zone filter may have no tooltip instance,
        // and a throw here would stop the drawer opening at all.
        try { $(this).tooltip('hide'); } catch (err) { /* no instance on this row */ }
        $('.tooltip').remove();
        openConnectModuleDrawer($(this).data('url'));
    });

    /* Rental, RideShare and Service price and time themselves, so a COD ceiling here has
       nothing under it to cap — none of the three reach PlaceNewOrderTrait, the one place
       maximum_cod_order_amount is ever read. A module of one of these types is skipped
       entirely rather than given a row; renderModuleNotes() below is what tells the admin
       why, and where its own setup actually lives. */
    function isCodExemptModuleType(moduleType) {
        return window.connectModuleState && !!window.connectModuleState.moduleNotes[moduleType];
    }

    /* One row per connected COD-eligible module, rebuilt whenever the picker changes so the
       rows and the selection cannot disagree. Values already typed survive a
       re-render — losing them because another module was added would be its own
       small bug. */
    window.renderCodLimitRows = function (preferStored) {
        let state = window.connectModuleState;
        let container = $('#cod-limit-rows');
        if (!state || !container.length) { return; }

        // What the admin has typed wins over what is stored, so re-rendering after a module
        // change does not wipe half-finished input. `preferStored` turns that off for Reset,
        // whose whole job is to discard the typing and go back to the saved values.
        let typed = {};
        if (! preferStored) {
            container.find('.cm-cod-row__input').each(function () {
                typed[$(this).data('module')] = $(this).val();
            });
        }

        let template = document.getElementById('cod-limit-row-template');
        container.empty();

        let selected = $('#connect_modules').find('option:selected');
        let eligible = selected.filter(function () {
            return !isCodExemptModuleType($(this).data('module-type'));
        });
        // "Choose a module first" reads correctly for the exempt-only case too: from here it
        // looks exactly like nothing was picked, and renderModuleNotes() is what actually
        // explains a Rental/RideShare/Service selection instead.
        $('#cod-limit-empty').toggleClass('d-none', eligible.length > 0);

        eligible.each(function () {
            let moduleId = $(this).val();
            let moduleName = $(this).text().trim();
            let row = $(template.content.cloneNode(true));

            row.find('.cm-cod-row__name').text(state.moduleLabel + ': ' + moduleName);
            row.find('.cm-cod-row__hint').text(state.codHint);
            row.find('.cm-cod-row__field-label').text(state.fieldLabel);

            let stored = state.codLimits[moduleId];
            row.find('.cm-cod-row__input')
                .attr('name', 'max_cod_order_amount[' + moduleId + ']')
                .attr('data-module', moduleId)
                .val(typed[moduleId] !== undefined ? typed[moduleId] : (stored > 0 ? stored : ''));

            container.append(row);
        });
    };

    /* One note per selected Rental/RideShare/Service module, telling the admin where that
       module's own pricing actually lives — shown as soon as it's picked, independent of
       whether Max COD Order Amount is even open, since the point is not about COD at all.
       Rebuilt on the same triggers as renderCodLimitRows() (see below), so the two can never
       disagree about which modules are exempt.

       Markup matches provider-serviceman.blade.php's "Reels and cash in hand controls are
       managed separately" note exactly — .info-notes-bg, the info-idea.svg icon, an
       .info-dark link — so a module-setup aside built by hand here can't drift from the one
       already established elsewhere in the panel. */
    window.renderModuleNotes = function () {
        let state = window.connectModuleState;
        let container = $('#module-notes');
        if (!state || !container.length) { return; }

        container.empty();

        let seen = {};
        $('#connect_modules').find('option:selected').each(function () {
            let moduleType = $(this).data('module-type');
            let note = state.moduleNotes[moduleType];

            // One note per TYPE, not per module: two RideShare modules would otherwise
            // repeat the exact same sentence twice.
            if (!note || seen[moduleType]) { return; }
            seen[moduleType] = true;

            let text = note.text;
            let body = $('<span></span>');

            if (note.linkUrl) {
                body.text(text + ': ').append(
                    $('<a></a>').attr('href', note.linkUrl)
                        .addClass('fz-12px font-semibold info-dark text-decoration-underline')
                        .text(note.linkLabel)
                ).append(document.createTextNode('.'));
            } else {
                body.text(text);
            }

            container.append(
                $('<div class="info-notes-bg px-3 py-2 rounded fz-11 gap-2 d-flex mb-20"></div>')
                    .append($('<img alt="">').attr('src', state.infoIconUrl))
                    .append(body)
            );
        });
    };

    // Reset means "back to what is saved", for every field.
    //
    // The button is a plain type="reset", so the browser restores each control to the value in
    // its HTML — that puts the right options back on <select id="connect_modules"> itself, but
    // Select2 keeps its own detached chip list and only re-reads the <select> on a `change`
    // event, which a native reset never fires. Left alone, the old chips stayed on screen
    // exactly as they were the instant before Reset was clicked. Re-selecting from
    // state.initialModuleIds and triggering `change` forces Select2 to rebuild its chips from
    // the restored selection, which also re-renders the COD rows for free below.
    //
    // The COD rows are built by renderCodLimitRows() and carry their value as a property, not
    // an attribute, so a native reset left them BLANK while everything else went back to the
    // saved configuration. renderCodLimitRows(true) settles that: the rows come back holding
    // state.codLimits, the same values the drawer opened with.
    //
    // Deferred a tick because the native reset runs after this handler returns.
    $(document).on('reset', '#connect-module-form', function () {
        setTimeout(function () {
            let state = window.connectModuleState;
            if (state) {
                $('#connect_modules').val(state.initialModuleIds).trigger('change');
            }
            renderCodLimitRows(true);
        }, 0);
    });

    $(document).on('change', '#connect_modules', function () {
        renderCodLimitRows();
        renderModuleNotes();
    });

    // A limit on COD orders has nothing to apply to once Cash On Delivery itself is off, so the
    // whole card folds away with it — not just left closed, which still let an admin open and
    // save a per-module limit for an order type the zone no longer accepts (TC_123). Named and
    // called on drawer open as well as on change: a zone saved before this fix can carry a
    // Max COD toggle left ON from when COD was still checked, and that stale ON has to be
    // corrected the moment the drawer opens, not only on the admin's next click.
    window.syncCodLimitCardVisibility = function () {
        let codEnabled = $('#connect-cash_on_delivery').is(':checked');
        $('#cod-limit-card').toggleClass('d-none', !codEnabled);
        if (!codEnabled) {
            $('#max_cod_status').prop('checked', false);
            $('#cod-limit-card').removeClass('is-open');
            $('#cod-limit-body').addClass('d-none');
        }
    };

    $(document).on('change', '#connect-cash_on_delivery', syncCodLimitCardVisibility);

    $(document).on('change', '#max_cod_status', function () {
        // The card drops its bottom padding only while the rows are showing — folded
        // away, that padding is what keeps the subtitle off the card's edge.
        $('#cod-limit-card').toggleClass('is-open', this.checked);
        // The inputs are required only while the toggle is on, which is exactly
        // when they are visible — so hiding them also stops them being validated.
        $('#cod-limit-body').toggleClass('d-none', !this.checked);
        if (this.checked) { renderCodLimitRows(); }
    });

    /* The drawer is `position: fixed; z-index: 1050`, so it opens a stacking context and every
       descendant is painted at 1050 whatever its own z-index says. Bootstrap appends the modal's
       backdrop to <body> instead, where `body.v2-chrome .modal-backdrop` puts it at 1054 — above
       the whole drawer, the warning dialog inside it included. The dialog was drawn but the
       backdrop swallowed every click on it, and BS4 binds its dismiss handler to `.modal` rather
       than to the backdrop, so the page looked frozen with no way out but a reload.

       Lifting the drawer above 1054 would only trade the bug for an undimmed backdrop, so the
       dialog moves out to <body> instead: at the root it is a sibling of the backdrop and its
       own 1055 wins. Nothing else in the drawer needs this — the module picker already renders
       its dropdown into the drawer via select2's dropdownParent. */
    function relocateDisconnectWarningModal() {
        let modal = $('#connect-module-view').find('#connect-module-disconnect-warning-modal');
        if (! modal.length) { return; }

        modal.appendTo('body');

        // The drawer, not the dialog, owns `modal-open` here: Bootstrap drops the class when the
        // dialog closes, which would hand the page behind the still-open drawer its scrollbar back.
        modal.on('hidden.bs.modal', function () {
            if ($('#offcanvas__connect_module').hasClass('open')) {
                $('body').addClass('modal-open');
            }
        });
    }

    /** Modules the drawer opened with that were connected AND had an active store, but are not
     *  in the current selection — a real disconnect a store depends on, not just an unrelated
     *  module never having one. */
    function disconnectedModulesWithActiveStores() {
        let state = window.connectModuleState;
        if (!state || !state.initialModuleIds || !state.activeStoreModuleIds) { return []; }

        let selected = $('#connect_modules').find('option:selected').map(function () {
            return Number($(this).val());
        }).get();

        return state.initialModuleIds.filter(function (id) {
            return state.activeStoreModuleIds.indexOf(id) !== -1 && selected.indexOf(id) === -1;
        });
    }

    /** The same question asked of live storefronts instead of active stores. Kept separate
     *  because a module can carry a site without carrying an ACTIVE store -- a temporarily
     *  closed store still serves its website -- and that case has to raise the warning too. */
    function disconnectedModulesWithStorefronts() {
        let state = window.connectModuleState;
        if (!state || !state.initialModuleIds || !state.builderStoreModuleIds) { return []; }

        let selected = $('#connect_modules').find('option:selected').map(function () {
            return Number($(this).val());
        }).get();

        return state.initialModuleIds.filter(function (id) {
            return state.builderStoreModuleIds.indexOf(id) !== -1 && selected.indexOf(id) === -1;
        });
    }

    function submitConnectModuleForm(form) {
        let submitBtn = form.find('button[type="submit"]');
        $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });
        $.post({
            url: form.attr('action'),
            data: new FormData(form.get(0)),
            cache: false,
            contentType: false,
            processData: false,
            beforeSend: function () { submitBtn.prop('disabled', true); },
            success: function (data) {
                if (data.errors) {
                    submitBtn.prop('disabled', false);
                    for (let i = 0; i < data.errors.length; i++) {
                        toastr.error(data.errors[i].message, { CloseButton: true, ProgressBar: true });
                    }
                    return;
                }
                toastr.success(data.success, { CloseButton: true, ProgressBar: true });

                // The zone still isn't ready — prompt the next step (TC_58) instead of just
                // reloading. The drawer closes right away rather than sitting open underneath
                // the prompt: the guide is stashed across the reload and picked back up once
                // the index page — not the drawer — is what's behind it.
                if (data.setupGuide) {
                    closeConnectModuleDrawer();
                    sessionStorage.setItem('zoneSetupGuidePending', JSON.stringify(data.setupGuide));
                }
                setTimeout(function () { location.reload(); }, 1000);
            },
            error: function (xhr) {
                submitBtn.prop('disabled', false);
                // 422 carries Laravel's validation bag rather than our own list.
                let bag = xhr.responseJSON && xhr.responseJSON.errors;
                if (bag) {
                    $.each(bag, function (field, messages) {
                        toastr.error(messages[0], { CloseButton: true, ProgressBar: true });
                    });
                }
            }
        });
    }

    let pendingConnectModuleForm = null;

    $(document).on('submit', '#connect-module-form', function (e) {
        e.preventDefault();
        let form = $(this);

        let losesStorefronts = disconnectedModulesWithStorefronts().length > 0;

        if (disconnectedModulesWithActiveStores().length > 0 || losesStorefronts) {
            // The storefront line is only true when a removed module actually carries one, so it
            // is shown per open rather than left standing in the markup.
            $('#connect-module-builder-warning').toggleClass('d-none', !losesStorefronts);
            pendingConnectModuleForm = form;
            $('#connect-module-disconnect-warning-modal').modal('show');
            return;
        }

        submitConnectModuleForm(form);
    });

    $(document).on('click', '#connect-module-disconnect-warning-confirm', function () {
        $('#connect-module-disconnect-warning-modal').modal('hide');
        if (pendingConnectModuleForm) {
            submitConnectModuleForm(pendingConnectModuleForm);
            pendingConnectModuleForm = null;
        }
    });

    /** Same teardown the manual close button/overlay click below performs — pulled out so the
     *  setup-guide prompt can close the drawer itself before it reloads, instead of leaving the
     *  drawer open underneath the modal until that reload happens. */
    function closeConnectModuleDrawer() {
        $('#offcanvas__connect_module').removeClass('open');
        $('#offcanvasOverlay').removeClass('show');
        $('body').removeClass('modal-open');
        $('#connect-module-view').empty();
        // Emptying the drawer no longer reaches the warning dialog — it lives on <body> now.
        // Removing one that is still showing would strand its backdrop over the page, so a
        // visible dialog is closed first and dropped once Bootstrap has cleaned up after it.
        let warning = $('body > #connect-module-disconnect-warning-modal');
        if (warning.hasClass('show')) {
            warning.one('hidden.bs.modal', function () { $(this).remove(); }).modal('hide');
        } else {
            warning.remove();
        }
        pendingConnectModuleForm = null;
    }

    // Also delegated, for the same reason: this close button arrives with the markup.
    $(document).on('click', '#offcanvas__connect_module .offcanvas-close, #offcanvasOverlay', closeConnectModuleDrawer);

    /* ── TC_58: the post-save next-step prompt ─────────────────────────────────
       Not a refusal like openZoneReadinessDialog() below — the save already succeeded — so it
       gets its own buttons rather than a link list: "Setup ETA" / "Create Charge Rules", shown
       for whichever is actually still missing. `guide` is the connect-module response's
       `setupGuide`, already resolved server-side from the same readiness the rest of the zone
       screen reads (ZoneController::setupGuideData()). */
    function showZoneSetupGuide(guide) {
        let zoneQuery = '?zone_id=' + encodeURIComponent(guide.zoneId);
        let actions = [];

        if (!guide.hasEta) {
            actions.push('<a class="btn btn--primary min-w-120px" href="{{ route('admin.business-settings.zone.eta-configuration.create') }}'
                + zoneQuery + '">' + @json(translate('messages.Setup ETA')) + '</a>');
        }
        if (!guide.hasRule) {
            actions.push('<a class="btn btn--primary min-w-120px" href="{{ route('admin.business-settings.zone.delivery-rule.create') }}'
                + zoneQuery + '">' + @json(translate('Create charge rules')) + '</a>');
        }

        $('#zone-setup-guide-title').text(guide.title);
        $('#zone-setup-guide-text').text(guide.text);
        $('#zone-setup-guide-actions').html(actions.join(''));
        $('#zone-setup-guide-modal').modal('show');
    }

    // The other half of the stash in submitConnectModuleForm()'s success handler: the guide
    // that couldn't be shown before the reload (it would have sat on top of the still-open
    // drawer) is picked back up once this fresh load of the index page is what's behind it.
    (function () {
        let pending = sessionStorage.getItem('zoneSetupGuidePending');
        if (!pending) { return; }

        sessionStorage.removeItem('zoneSetupGuidePending');

        try {
            showZoneSetupGuide(JSON.parse(pending));
        } catch (err) { /* stale/corrupt value — nothing to show */ }
    })();

    // The list underneath (readiness/status/warning mark) is stale once this closes — the
    // Connect Module save that led here already changed it.
    $(document).on('hidden.bs.modal', '#zone-setup-guide-modal', function () {
        location.reload();
    });

    /* ── Z3: the one dialog, two ways in ───────────────────────────────────────
       The status toggle and the row's warning mark open the same dialog and list
       the same missing setups, but not with the same lead line: the toggle
       answers "why did my switch bounce back", the mark answers "why is there a
       warning on a zone that is already on". Both sentences come from the server,
       resolved from the same gaps the toggle guard reads, so nothing here can
       invent a wording — this only picks which of the two the caller carries.
       `data` is a plain object, not a jQuery element: the warning mark passes its
       own data-* (`$(this).data()`), the toggle passes the fresh JSON a readiness
       check just answered with — same shape either way (readiness() below is
       written to match the field names data-* already uses). */
    function openZoneReadinessDialog(data, zoneId) {
        let title = data.title;
        let text = data.notice || data.prompt;
        let zoneQuery = '?zone_id=' + encodeURIComponent(zoneId);
        let missing = [];

        // Only what is left to do. An admin who has already added one of the two is
        // not handed a link back to it.
        if (data.hasRule != 1) {
            missing.push('<li><a class="text-info text-underline" href="{{ route('admin.business-settings.zone.delivery-rule.create') }}'
                + zoneQuery + '">' + @json(translate('Add delivery charge setup')) + '</a></li>');
        }
        if (data.hasEta != 1) {
            missing.push('<li><a class="text-info text-underline" href="{{ route('admin.business-settings.zone.eta-configuration.create') }}'
                + zoneQuery + '">' + @json(translate('Add ETA configuration')) + '</a></li>');
        }

        $('#zone-readiness-title').text(title);
        $('#zone-readiness-text').text(text);
        $('#zone-readiness-list').html(missing.join(''));
        $('#zone-readiness-modal').modal('show');
    }

    // The row's warning mark: read-only and informational, so the snapshot this row was
    // last rendered with is fine here — nothing gets switched on from this click.
    $(document).on('click', '.zone-setup-warning__mark', function (e) {
        e.preventDefault();
        openZoneReadinessDialog($(this).data(), $(this).data('zone-id'));
    });

    /* ── Z3 / S19: the status toggle, verified fresh on every click ────────────
       Which of the three dialogs applies — hard block, "turn on anyway", or the
       plain confirm — used to be decided from this row's own data-*, baked in
       whenever it was last rendered. That snapshot goes stale the moment a zone
       changes status without a full reload, which status-toggle.js's flip-in-place
       AJAX now does routinely for the plain case (see _table_rows.blade.php's note
       on why the toggle never carries `dynamic-checkbox`). So instead of trusting
       the row, every click here asks admin.business-settings.zone.readiness for
       this zone's CURRENT state first and decides from that — the same computation
       updateStatus() itself is about to re-check, so the dialog can never promise
       something the server then refuses.

       Deactivating skips the check: turning a zone off never needs it (Z3 only
       guards switching ON), and skipping means the common case pays no extra
       request. */
    const zoneStatusUrlTemplate = @json(route('admin.business-settings.zone.status', ['id' => '__ZONE__', 'status' => '__STATUS__']));
    const zoneReadinessUrlTemplate = @json(route('admin.business-settings.zone.readiness', ['id' => '__ZONE__']));

    function zoneStatusUrl(zoneId, status) {
        return zoneStatusUrlTemplate.replace('__ZONE__', encodeURIComponent(zoneId)).replace('__STATUS__', status);
    }

    function zoneReadinessUrl(zoneId) {
        return zoneReadinessUrlTemplate.replace('__ZONE__', encodeURIComponent(zoneId));
    }

    function confirmZoneStatus($input, titleKey, textKey, onConfirm) {
        Swal.fire({
            title: $input.data(titleKey),
            // `html`, not `text`: data-text-on/-off carry markup (the storefront-count
            // warning is a whole extra <p>), and `text` would show the tags literally
            // instead of rendering them.
            html: $input.data(textKey),
            type: 'warning',
            showCancelButton: true,
            cancelButtonColor: 'default',
            confirmButtonColor: '#FC6A57',
            cancelButtonText: @json(translate('messages.No')),
            confirmButtonText: @json(translate('messages.Yes')),
            reverseButtons: true
        }).then((result) => {
            if (result.value) {
                onConfirm();
            }
        });
    }

    let pendingZoneStatusUrl = null;

    $(document).on('click', '.zone-status-toggle', function (e) {
        // The browser has already applied the click's default action (this box's own
        // toggle) by the time a handler sees it, so `checked` here is the state the
        // admin just asked for — preventDefault below only stops it becoming real.
        e.preventDefault();

        const $input = $(this);
        const turningOn = $input.prop('checked');
        $input.prop('checked', !turningOn);

        const zoneId = $input.data('zone-id');

        if (!turningOn) {
            confirmZoneStatus($input, 'title-off', 'text-off', function () {
                window.location.href = zoneStatusUrl(zoneId, 0);
            });
            return;
        }

        $.get(zoneReadinessUrl(zoneId))
            .done(function (fresh) {
                if (fresh.status === 1) {
                    // Already on — a stale double-click, or another tab got there first.
                    // Nothing left for this click to do.
                    return;
                }

                if (!fresh.toggleEnabled) {
                    openZoneReadinessDialog(fresh, zoneId);
                    return;
                }

                if (fresh.requiresConfirmation) {
                    pendingZoneStatusUrl = zoneStatusUrl(zoneId, 1);
                    $('#zone-partial-title').text(fresh.partialTitle);
                    $('#zone-partial-text').text(fresh.partialPrompt);
                    $('#zone-partial-modules').text(fresh.unavailableModules);
                    $('#zone-partial-modal').modal('show');
                    return;
                }

                confirmZoneStatus($input, 'title-on', 'text-on', function () {
                    window.location.href = zoneStatusUrl(zoneId, 1);
                });
            })
            .fail(function () {
                // The check itself failed, not the readiness question — fall through to a
                // real navigation, which asks updateStatus() the same question server-side
                // regardless of what any dialog here would have said.
                window.location.href = zoneStatusUrl(zoneId, 1);
            });
    });

    $(document).on('click', '#zone-partial-confirm', function () {
        $('#zone-partial-modal').modal('hide');

        if (pendingZoneStatusUrl) {
            window.location.href = pendingZoneStatusUrl;
            pendingZoneStatusUrl = null;
        }
    });

    // Cancelling leaves the zone exactly as it was, including the switch this handler
    // already put back.
    $('#zone-partial-modal').on('hidden.bs.modal', function () {
        pendingZoneStatusUrl = null;
    });
</script>
