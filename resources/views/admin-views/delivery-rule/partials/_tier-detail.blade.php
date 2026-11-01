{{-- One of the two ADDITIVE parcel tiers on the rule detail, laid out exactly like the base charge
     block beside it — a heading column with a blue hint, and a table column — so the three tabs
     read as one screen.

     No status badge here, unlike my first pass: the design does not carry one, and it would say
     nothing anyway. A tier only earns its tab by having priced rows, and switching a tier off
     clears them, so a visible tab is always an active tier. --}}
<div class="row g-3">
    <div class="col-md-4">
        <h5 class="surge-section__title">{{ $title }}</h5>
        <p class="surge-section__subtitle">{{ $subtitle }}</p>

        <div class="rule-hint">
            <img src="{{ asset('public/assets/admin/img/svg/bulb.svg') }}" class="svg" alt="">
            <span>{{ $hint }}</span>
        </div>
    </div>

    <div class="col-md-8">
        <div class="rule-charge-table">
            <table class="rule-charge-table__table">
                <thead>
                    <tr>
                        <th class="rule-charge-table__sl">{{ translate('messages.SL') }}</th>
                        <th>{{ $heading }}</th>
                        <th>{{ translate('Delivery charge') }} ({{ $currencySymbol }})</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $index => $row)
                        <tr>
                            <td class="rule-charge-table__sl">{{ $index + 1 }}</td>
                            <td>{{ $row['label'] }}</td>
                            <td>{{ $row['charge'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center">{{ translate('No data found') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
