{{-- One of the wizard's two parcel steps. Weight and Dimension differ only in their copy, their
     field names and what the middle column of the table shows, so they share this partial rather
     than being two near-identical files that drift apart.

     Everything arrives finished from DeliveryRuleController::formData(): $rows are the active
     bands or size classes, $labels their formatted descriptions, $charges the saved amounts. --}}
<div class="surge-section mb-20">
    <div class="row g-3 align-items-center">
        <div class="col-md-8">
            <h5 class="surge-section__title">{{ $setupTitle }}</h5>
            <p class="surge-section__subtitle mb-0">{{ $setupSubtitle }}</p>
        </div>
        <div class="col-md-4">
            <div class="border rounded p-3 d-flex justify-content-between align-items-center">
                <span class="input-label mb-0">{{ translate('messages.Status') }}</span>
                {{-- No confirm dialog here, unlike the list screens: nothing is saved until the
                     wizard is submitted, so flipping this is not yet a change to anything. --}}
                <label class="toggle-switch toggle-switch-sm mb-0" for="{{ $fieldName }}_charge_status">
                    <input type="checkbox" class="toggle-switch-input parcel-step-toggle"
                        name="{{ $fieldName }}_charge_status" id="{{ $fieldName }}_charge_status" value="1"
                        data-target="#{{ $fieldName }}-table-wrapper" {{ $enabled ? 'checked' : '' }}>
                    <span class="toggle-switch-label">
                        <span class="toggle-switch-indicator"></span>
                    </span>
                </label>
            </div>
        </div>
    </div>

    <div class="rule-hint mt-3">
        <img src="{{ asset('public/assets/admin/img/svg/bulb.svg') }}" class="svg" alt="">
        <span>{{ $setupHint }}</span>
    </div>
</div>

<div class="surge-section">
    <h5 class="surge-section__title">{{ $tableTitle }}</h5>
    <p class="surge-section__subtitle">{{ $tableSubtitle }}</p>

    <div class="rule-hint mb-20">
        <img src="{{ asset('public/assets/admin/img/svg/bulb.svg') }}" class="svg" alt="">
        <span>{{ $tableHint }}</span>
    </div>

    <div id="{{ $fieldName }}-table-wrapper" class="{{ $enabled ? '' : 'd-none' }}">
        @if (count($rows))
            <div class="row g-3">
                <div class="col-md-4">
                    <h5 class="surge-section__title">{{ $listTitle }}</h5>
                    <p class="surge-section__subtitle">{{ $listSubtitle }}</p>
                    {{-- The design renders this note in amber rather than the blue bulb used
                         above: it is an instruction about how to fill the table in, not an
                         explanation of what the feature does. --}}
                    <div class="rule-note">
                        <i class="tio-info-outined"></i>
                        <span>{!! $listNote !!}</span>
                    </div>
                </div>

                <div class="col-md-8">
                    <div class="rule-charge-table">
                        <table class="rule-charge-table__table">
                            <thead>
                                <tr>
                                    <th class="rule-charge-table__sl">{{ translate('messages.SL') }}</th>
                                    <th>{{ $heading }}</th>
                                    <th>
                                        {{ translate('Delivery charge') }} ({{ $currencySymbol }})
                                        <span class="text-danger">*</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rows as $index => $row)
                                    <tr>
                                        <td class="rule-charge-table__sl">{{ $index + 1 }}</td>
                                        <td>{{ $labels[$row->id] }}</td>
                                        <td>
                                            <input type="number" step="0.01" min="0"
                                                name="{{ $fieldName }}_charges[{{ $row->id }}]"
                                                class="form-control h-45"
                                                placeholder="{{ translate('Ex') }}: 2"
                                                value="{{ $charges[$row->id] ?? '' }}">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @else
            {{-- Nothing to price. The same shape the area and ZIP panes use for an empty zone:
                 say what is missing and link to the screen that fixes it, rather than showing an
                 empty table the admin cannot act on. --}}
            <div class="rule-coverage-empty">
                <h5 class="rule-coverage-empty__title">{{ $listTitle }}</h5>
                <p class="rule-coverage-empty__text">{{ $emptyText }}</p>
                <a href="{{ $emptyLink }}">{{ $emptyLinkText }}</a>
            </div>
        @endif
    </div>
</div>
