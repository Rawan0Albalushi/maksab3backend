{{-- Connect Module — payment methods, the modules this zone serves, and each one's COD ceiling.
     No delivery pricing: a zone prices through Rule Setup and surges through its own screen.
     Styling lives in public/assets/admin/css/connect-module.css, built to the mock. --}}
<form action="{{ route('admin.business-settings.zone.connect-module.save', [$zone->id]) }}" method="post"
    class="d-flex flex-column h-100" id="connect-module-form">
    @csrf

    <div class="cm-header">
        <h3 class="cm-header__title">{{ translate('Connect module with zone') }}: {{ $zone->name }}</h3>
        <button type="button" class="cm-close offcanvas-close" aria-label="{{ translate('messages.Close') }}">&times;</button>
    </div>

    <div class="cm-body offcanvas-body--fill {{ $readiness['ready'] ? 'cm-body--padded' : '' }}">

        <div class="cm-card">
            <h4 class="cm-card__title">{{ translate('Select payment method') }}</h4>

            @if ($paymentMethods === [])
                <div class="admin-alert admin-alert--danger mt-3">
                    <span class="admin-alert__icon">i</span>
                    <span>{{ translate('Must enable at least one payment method from your third-party payment settings.') }}</span>
                </div>
            @else
                <div class="admin-alert mt-3">
                    <span class="admin-alert__icon">i</span>
                    <span>{{ translate('Must select at least one payment method.') }}</span>
                </div>

                <div class="cm-panel cm-payments">
                    @foreach ($paymentMethods as $method => $meta)
                        <label class="cm-check">
                            <input type="checkbox" value="1" name="{{ $method }}" id="connect-{{ $method }}"
                                {{ $meta['checked'] ? 'checked' : '' }}>
                            <span>{{ $meta['label'] }}</span>
                        </label>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="cm-card">
            <h4 class="cm-card__title">{{ translate('messages.Module to connect') }}</h4>
            <p class="cm-card__subtitle mb-3">
                {{ translate('messages.Here you connect your modules & setup the delivery charges for this zone.') }}
            </p>

            <label class="cm-label" for="connect_modules">{{ translate('Choose module to connect') }}</label>
            <div class="cm-picker">
                <select name="module_id[]" id="connect_modules" class="js-select2-custom" multiple="multiple" required>
                    @foreach ($modules as $module)
                        <option value="{{ $module->id }}" data-module-type="{{ $module->module_type }}"
                            {{ in_array($module->id, $selectedModuleIds, true) ? 'selected' : '' }}>
                            {{ $module->module_name }}
                        </option>
                    @endforeach
                </select>
                <svg class="cm-picker__caret" width="14" height="8" viewBox="0 0 14 8" fill="none"
                    xmlns="http://www.w3.org/2000/svg">
                    <path d="M1 1L7 7L13 1" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"
                        stroke-linejoin="round" />
                </svg>
            </div>
        </div>

        {{-- The ceiling per module. Folded away until switched on, because a platform with no
             limit should not have to look at an input per module to learn that. Folded away
             ENTIRELY, not just left unopened, when Cash On Delivery itself is off — a limit on
             an order type this zone does not accept has nothing to apply to (TC_123). --}}
        <div class="cm-card cm-card--split {{ $codLimitEnabled ? 'is-open' : '' }} {{ ($paymentMethods['cash_on_delivery']['checked'] ?? false) ? '' : 'd-none' }}"
            id="cod-limit-card">
            <div class="cm-cod-head">
                <div>
                    <h4 class="cm-card__title">{{ translate('Max COD order amount') }}</h4>
                    <p class="cm-card__subtitle">
                        {{ translate('messages.Customers cannot place COD orders above the configured limit.') }}
                    </p>
                </div>
                <label class="toggle-switch toggle-switch-sm cm-toggle" for="max_cod_status">
                    <input type="checkbox" class="toggle-switch-input" id="max_cod_status" name="max_cod_status"
                        value="1" {{ $codLimitEnabled ? 'checked' : '' }}>
                    <span class="toggle-switch-label">
                        <span class="toggle-switch-indicator"></span>
                    </span>
                </label>
            </div>

            <div id="cod-limit-body" class="cm-cod-body {{ $codLimitEnabled ? '' : 'd-none' }}">
                <div id="cod-limit-rows"></div>
                <p class="cm-card__subtitle m-0 d-none" id="cod-limit-empty">
                    {{ translate('messages.Choose a module first — each connected module sets its own limit.') }}
                </p>
            </div>
        </div>

        {{-- Three states, not two — the same S19 split the status toggle's confirm dialog and the
             row's "Important!" mark already use (ZoneService::readinessFor()'s `ready` vs
             `complete`). This used to be a plain if/else on `ready` alone, so a zone with just ONE
             complete module (enough to switch on, per S19) fell into the same unqualified success
             branch as a zone where every connected module was actually finished — this box said
             "fully configured and ready to serve orders" for a zone the row's own warning mark
             was, at the same moment, flagging as leaving other modules dark. Now the drawer cannot
             claim less trouble than the mark and the toggle already know about.

             A finished zone's notice sits in normal flow; an unfinished one gets the sticky
             version, because it names a step the admin still has to take before this zone can
             serve anything and should stay in reach while the module rows above scroll under it.
             The partial notice is informational, not a blocking step, so it stays in flow like the
             success case. --}}
        @if ($readiness['complete'])
            <div class="admin-alert admin-alert--success">
                <span class="admin-alert__icon">&check;</span>
                <span>{{ translate('messages.This business zone is fully configured and ready to serve orders.') }}</span>
            </div>
        @elseif ($readiness['ready'])
            <div class="admin-alert">
                <span class="admin-alert__icon">i</span>
                <span>
                    {{-- Same `partialNotice` the row mark's dialog shows for this exact state
                         (openZoneReadinessDialog() in _connect-module-scripts.blade.php), and the
                         same setup links, so a module still missing its rule or ETA here is named
                         and fixable the same way it would be from the row. --}}
                    {{ $readiness['partialNotice'] }}
                    @include('admin-views.zone.partials._setup-links', [
                        'zoneId' => $zone->id,
                        'hasRule' => $readiness['hasRule'],
                        'hasEta' => $readiness['hasEta'],
                    ])
                </span>
            </div>
        @else
            <div class="admin-alert admin-alert--sticky">
                <span class="admin-alert__icon">i</span>
                <span>
                    {{-- Names only what's actually missing (delivery rule, ETA, or both) — the
                         same resolved prompt the row's warning mark and status-toggle dialog use,
                         so this box cannot say something they don't. Previously a hardcoded
                         "create delivery charge rules" sentence always showed here regardless of
                         which setup was missing, duplicating the link below when it was the rule,
                         and never mentioning ETA when that was the actual gap. --}}
                    {{ $readiness['prompt'] }}
                    @include('admin-views.zone.partials._setup-links', [
                        'zoneId' => $zone->id,
                        'hasRule' => $readiness['hasRule'],
                        'hasEta' => $readiness['hasEta'],
                    ])
                </span>
            </div>
        @endif

        {{-- Rental, RideShare and Service price and time themselves — a delivery rule, an ETA
             configuration, and the COD ceiling above all assume a store dispatching a delivery,
             which none of the three do. After the readiness alert, not before: that alert is
             about the zone's OWN setup (rule/ETA), this is about a module that was never going
             to need one, and the reference design puts "here's what's missing" ahead of "here's
             what doesn't apply". Read only from the module list itself, not from Max COD being
             open — an admin who has not touched that toggle yet still needs to know a module
             they just picked has its pricing set up somewhere else entirely, not that this
             drawer forgot to ask. One box per such module, rebuilt by the same
             renderModuleNotes() the module picker's `change` already drives. --}}
        <div id="module-notes"></div>
    </div>

    <template id="cod-limit-row-template">
        <div class="cm-cod-row">
            <div class="cm-cod-row__label">
                <h5 class="cm-cod-row__name"></h5>
                <p class="cm-cod-row__hint"></p>
            </div>
            <div class="cm-cod-row__field">
                <label>
                    <span class="cm-cod-row__field-label"></span>
                    <span class="cm-req">*</span>
                    <svg width="14" height="14" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"
                        data-toggle="tooltip" data-placement="top"
                        title="{{ translate('messages.A COD order above this amount is refused at checkout.') }}">
                        <circle cx="8" cy="8" r="7" stroke="#a7b0be" stroke-width="1.3" />
                        <path d="M8 7v4M8 5h.01" stroke="#a7b0be" stroke-width="1.5" stroke-linecap="round" />
                    </svg>
                </label>
                <input type="number" min="0" step="0.01" class="cm-cod-row__input"
                    placeholder="{{ translate('Ex') }}: 5">
            </div>
        </div>
    </template>

    <div class="cm-footer">
        <button type="reset" class="btn btn--reset">{{ translate('messages.Reset') }}</button>
        <button type="submit" class="btn btn--primary">{{ translate('messages.Add') }}</button>
    </div>
</form>

{{-- Shown instead of an immediate save when the module picker drops a module that still has
     active stores in this zone. A plain warning, not a store-by-store list — Cancel returns to
     the form untouched, Continue submits exactly what was already built. --}}
<div class="modal fade" id="connect-module-disconnect-warning-modal">
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
                        <h5 class="modal-title mb-3">{{ translate('messages.This will disconnect a module that still has active stores') }}</h5>
                    </div>
                    <div class="text-center">
                        <p>{{ translate('messages.One or more of the modules you removed still has active stores serving from this zone. They will no longer be reachable through it. Continue?') }}</p>
                        {{-- Shown by the scripts only when a removed module actually carries live
                             storefronts. A site is served on its own domain and does not re-check
                             the zone, so it is the one thing that would keep answering after the
                             module went -- and it is switched off on confirm, not left stranded. --}}
                        <p id="connect-module-builder-warning" class="text-danger d-none">
                            {{ translate('Store websites built with the website builder in these modules will be turned off and will no longer be found.') }}
                        </p>
                    </div>
                    <div class="btn--container justify-content-center">
                        <button type="button" class="btn btn--reset min-w-120px"
                            data-dismiss="modal">{{ translate('messages.Cancel') }}</button>
                        <button type="button" id="connect-module-disconnect-warning-confirm"
                            class="btn btn--primary min-w-120px">{{ translate('messages.Continue') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@php
    // Same shape provider-serviceman.blade.php already uses for "Reels and cash in hand
    // controls are managed separately" — .info-notes-bg, the info-idea.svg icon, an .info-dark
    // link — so a module-setup aside reads the same wherever it turns up rather than this
    // drawer inventing its own second version of the same idea.
    //
    // Built here, not inline in the script below, so the Route::has() guard is asked once per
    // note instead of twice. This view ships with the base app rather than inside RideShare or
    // Service themselves, so it runs even on an install where either is switched off — the
    // guard keeps that from being a hard crash; the note just loses its link.
    $rideShareNote = ['text' => translate("RideShare module doesn't support delivery charges. You can set trip fare per zone from")];
    if (\Illuminate\Support\Facades\Route::has('admin.business-settings.ride-fare.rides')) {
        $rideShareNote['linkUrl'] = route('admin.business-settings.ride-fare.rides');
        $rideShareNote['linkLabel'] = translate('messages.RideShare Module > Fare Management > Trip Fare Setup');
    }

    $serviceNote = ['text' => translate("Service module doesn't support delivery charges. You can set up service bookings from")];
    if (\Illuminate\Support\Facades\Route::has('admin.business-settings.service.booking')) {
        $serviceNote['linkUrl'] = route('admin.business-settings.service.booking');
        $serviceNote['linkLabel'] = translate('messages.Service Module > Booking Setup');
    }

    $rentalNote = ['text' => translate("Rental module doesn't support delivery charges. Its setup is available within the Rental module.")];
@endphp
<script>
    "use strict";
    window.connectModuleState = {
        codLimits: @json($codLimits),
        fieldLabel: @json($codFieldLabel),
        codHint: @json(translate('Set the max COD amount allowed for orders of this module.')),
        moduleLabel: @json(translate('messages.Module')),
        initialModuleIds: @json($selectedModuleIds),
        activeStoreModuleIds: @json($activeStoreModuleIds),
        builderStoreModuleIds: @json($builderStoreModuleIds),
        infoIconUrl: @json(asset('public/assets/admin/img/info-idea.svg')),
        // Keyed by module_type, not module id: any module of this type reads the same note,
        // and the type is what the option carries (data-module-type) since a module can be
        // renamed but its type cannot. `linkUrl`/`linkLabel` are omitted for Rental — no
        // screen of its own to point at, so the note is plain text.
        moduleNotes: {
            'ride-share': @json($rideShareNote),
            'service': @json($serviceNote),
            'rental': @json($rentalNote),
        },
    };
    if (window.renderCodLimitRows) { window.renderCodLimitRows(); }
    if (window.renderModuleNotes) { window.renderModuleNotes(); }
</script>
