{{-- The whole body of an area-wise or zip-code-wise pane: a descriptor beside
     the charge rows, or a centred prompt when the chosen zone has nothing to
     price yet. The controller re-renders this on a zone change, and
     _form-scripts.blade.php mirrors both shapes, so keep the three in step. --}}
@if (count($rows) === 0)
    <div class="rule-coverage-empty">
        <h5 class="rule-coverage-empty__title">{{ $title }}</h5>
        <p class="rule-coverage-empty__text">{{ $emptyText }}</p>
        <a href="{{ $emptyLink }}">{{ $emptyLinkText }}</a>
    </div>
@else
    <div class="row g-3">
        <div class="col-md-4">
            <h5 class="surge-section__title">{{ $title }}</h5>
            <p class="surge-section__subtitle mb-0">{{ $description }}</p>
            <div class="rule-hint">
                <img src="{{ asset('public/assets/admin/img/svg/bulb.svg') }}" class="svg" alt="">
                <span>{{ $hintText }} <a href="{{ $emptyLink }}">{{ $hintLinkText }}</a>.</span>
            </div>
        </div>

        <div class="col-md-8">
            <div class="rule-charge-table">
                <table class="rule-charge-table__table">
                    <thead>
                        <tr>
                            <th class="rule-charge-table__sl">{{ translate('messages.SL') }}</th>
                            <th>{{ $heading }} <span class="text-danger">*</span></th>
                            <th>
                                {{ translate('Delivery charge') }}
                                ({{ $currencySymbol }})
                                <span class="text-danger">*</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $index => $row)
                            <tr>
                                <td class="rule-charge-table__sl">{{ $index + 1 }}</td>
                                <td>{{ $row->{$labelKey} }}</td>
                                <td>
                                    {{-- Each method posts under its own field: the inactive pane is
                                         only hidden, so its inputs are submitted too, and area and zip
                                         ids share a keyspace — one array would let a blank row of the
                                         unused method overwrite a priced row of the chosen one. --}}
                                    <input type="number" step="0.01" min="0" name="{{ $fieldName }}[{{ $row->id }}]"
                                        class="form-control h-45" placeholder="{{ translate('Ex') }}: 2"
                                        value="{{ $charges[$row->id] ?? '' }}">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif
