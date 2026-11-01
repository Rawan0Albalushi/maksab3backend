<div class="card mb-20">
    <div class="card-body">
        <h4 class="surge-section__title">{{ translate('Basic information') }}</h4>
        <p class="surge-section__subtitle">
            {{ translate('messages.Configure the basic details of the surge pricing rule.') }}</p>

        <div class="surge-section">
            {{-- The tabs head the section rather than sitting inside one column: a column
                 carrying them stands one tab-row taller than its neighbour, and the note field
                 beside it would then float above the name input. --}}
            @if ($languages)
                <div class="js-nav-scroller hs-nav-scroller-horizontal">
                    <ul class="nav nav-tabs mb-3 border-0">
                        <li class="nav-item">
                            <a class="nav-link lang_link active" href="#"
                                id="default-link">{{ translate('messages.Default') }}</a>
                        </li>
                        @foreach ($languages as $language)
                            <li class="nav-item">
                                <a class="nav-link lang_link" href="#"
                                    id="{{ $language['code'] }}-link">{{ $language['label'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @foreach ($languages ? array_merge([['code' => 'default', 'suffix' => translate('messages.Default')]], array_map(fn ($l) => ['code' => $l['code'], 'suffix' => strtoupper($l['code'])], $languages)) : [['code' => 'default', 'suffix' => translate('messages.Default')]] as $index => $tab)
                <div class="{{ $index === 0 ? '' : 'd-none' }} lang_form" id="{{ $tab['code'] }}-form">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-group mb-0">
                                <label class="input-label" for="surge_price_name_{{ $tab['code'] }}">
                                    {{ translate('Surge price name') }} ({{ $tab['suffix'] }})
                                    @if ($index === 0)
                                        <span class="text-danger">*</span>
                                    @endif
                                    <span class="input-label-secondary" data-toggle="tooltip" data-placement="right"
                                        data-original-title="{{ translate('messages.A label for your own reference. It identifies this surge in the list and is never shown to customers.') }}"><i
                                            class="tio-info"></i></span>
                                </label>
                                {{-- The mock's placeholder reads "Type your business zone name" —
                                     a copy-paste slip from the zone screen. It names this field. --}}
                                <input type="text" name="surge_price_name[]" id="surge_price_name_{{ $tab['code'] }}"
                                    class="form-control h-45" maxlength="191"
                                    placeholder="{{ translate('messages.Type your surge price name') }}"
                                    value="{{ $tab['code'] === 'default' ? old('surge_price_name.0', $surge?->getRawOriginal('surge_price_name')) : ($translated[$tab['code']]['surge_price_name'] ?? '') }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <label class="input-label mb-0" for="customer_note_{{ $tab['code'] }}">
                                        {{ translate('Note for customer') }} ({{ $tab['suffix'] }})
                                        <span class="input-label-secondary" data-toggle="tooltip" data-placement="right"
                                            data-original-title="{{ translate('messages.Shown to the customer at checkout to explain why delivery costs more right now.') }}"><i
                                                class="tio-info"></i></span>
                                    </label>
                                    {{-- G4 — the note has its own switch. Only the default tab
                                         carries it: one note is either shown or it is not,
                                         whichever language the customer reads it in. --}}
                                    @if ($index === 0)
                                        <label class="toggle-switch toggle-switch-sm mb-0" for="customer_note_status">
                                            <input type="checkbox" class="toggle-switch-input" name="customer_note_status"
                                                id="customer_note_status" value="1" {{ $noteEnabled ? 'checked' : '' }}>
                                            <span class="toggle-switch-label">
                                                <span class="toggle-switch-indicator"></span>
                                            </span>
                                        </label>
                                    @endif
                                </div>
                                <textarea name="customer_note[]" id="customer_note_{{ $tab['code'] }}" rows="2"
                                    class="form-control" maxlength="50"
                                    {{ $index === 0 && $noteEnabled ? 'required' : '' }}
                                    placeholder="{{ translate('messages.Type about the description') }}">{{ $tab['code'] === 'default' ? old('customer_note.0', $surge?->getRawOriginal('customer_note')) : ($translated[$tab['code']]['customer_note'] ?? '') }}</textarea>
                                <div class="text-right"><span class="surge-char-count">0/50</span></div>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="lang[]" value="{{ $tab['code'] }}">
                </div>
            @endforeach
        </div>
    </div>
</div>

<div class="card mb-20">
    <div class="card-body">
        <h4 class="surge-section__title">{{ translate('Zone, module & surge price setup') }}</h4>
        <p class="surge-section__subtitle">
            {{ translate('messages.Choose where this surge pricing rule will be applied.') }}</p>

        <div class="surge-section">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="input-label" for="zone_id">{{ translate('Choose zone') }}
                        <span class="text-danger">*</span>
                    </label>
                    <select name="zone_id" id="zone_id" class="form-control js-select2-custom">
                        @foreach ($zones as $zone)
                            <option value="{{ $zone->id }}" {{ $selectedZoneId == $zone->id ? 'selected' : '' }}>
                                {{ $zone->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="input-label" for="module_ids">{{ translate('Choose module') }}
                        <span class="text-danger">*</span>
                        <span class="input-label-secondary" data-toggle="tooltip" data-placement="right"
                            data-original-title="{{ translate('messages.The modules this surge applies to in the chosen zone.') }}"><i
                                class="tio-info"></i></span>
                    </label>
                    {{-- G2. The theme's HSSelect2.init parses its options out of
                         data-hs-select2-options and ignores data-placeholder.

                         tags is off explicitly rather than by omission: a module has to exist to
                         be surged, so the list is the only source of valid ids, and Enter must
                         pick the highlighted module rather than turn the search term itself into
                         a value the request cannot map. --}}
                    <select name="module_ids[]" id="module_ids" class="form-control js-select2-custom" multiple
                        data-hs-select2-options='@json(["placeholder" => translate('Choose module'), "closeOnSelect" => false, "tags" => false], JSON_HEX_APOS)'>
                        @foreach ($modules as $module)
                            <option value="{{ $module->id }}" {{ in_array($module->id, $selectedModuleIds) ? 'selected' : '' }}>
                                {{ $module->module_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="input-label" for="price">{{ translate('Price increase rate') }}
                        <span class="text-danger">*</span>
                        <span class="input-label-secondary" data-toggle="tooltip" data-placement="right"
                            data-original-title="{{ translate('messages.How much the delivery charge is raised by while the surge is running.') }}"><i
                                class="tio-info"></i></span>
                    </label>
                    {{-- G3 — the rate carries its own unit. Percent is capped at 100 by the
                         request; a flat amount is in the store currency. --}}
                    {{-- One control, not two: the unit lives inside the input's right edge.
                         As an .input-group-append it was a second rounded box butted against
                         the field, since Bootstrap squares off the input's corners but leaves
                         the appended select fully rounded. --}}
                    <div class="surge-rate">
                        <input type="number" step="0.01" min="0" name="price" id="price"
                            class="form-control surge-rate__value"
                            placeholder="{{ translate('Ex') }}: 50" value="{{ $price }}">
                        <select name="price_type" id="price_type" class="surge-rate__unit">
                            <option value="percent" {{ $priceType === 'percent' ? 'selected' : '' }}>%</option>
                            <option value="amount" {{ $priceType === 'amount' ? 'selected' : '' }}>
                                {{ $currencySymbol }}</option>
                        </select>
                    </div>
                </div>
            </div>

            {{-- DESIGN RULE G1. Not the D1/F1/E1 rule: a zone may be surged more than once, just
                 not twice over the same hours. --}}
            <div class="rule-hint mt-3">
                <img src="{{ asset('public/assets/admin/img/svg/bulb.svg') }}" class="svg" alt="">
                <span>{{ translate('messages.A surge price can share a zone and module with another one, but not an overlapping duration.') }}</span>
            </div>
        </div>
    </div>
</div>

@include('admin-views.surge-price.partials._duration')

@include('admin-views.partials._floating-submit-button', ['submitButtonText' => translate('Save information')])
