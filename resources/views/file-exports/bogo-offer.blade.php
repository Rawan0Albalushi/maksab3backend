<div class="row">
    <div class="col-lg-12 text-center">
        <h1>{{ translate('BOGO offer') }}</h1>
    </div>
    <div class="col-lg-12">
        <table>
            <thead>
                <tr>
                    <th>{{ translate('Report analytics') }}</th>
                    <th></th>
                    <th></th>
                    <th>{{ translate('Total BOGO offers') }}: {{ $data['data']->count() }}</th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                </tr>
                <tr>
                    <th>{{ translate('Search criteria') }}</th>
                    <th></th>
                    <th></th>
                    <th>{{ translate('Search bar content') }}: {{ $data['search'] ?? translate('N/A') }}</th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                </tr>
                <tr>
                    <th class="border-0">{{ translate('messages.SL') }}</th>
                    <th class="border-0 w--25">{{ translate('Offer title') }}</th>
                    <th class="border-0">{{ translate('messages.Duration') }}</th>
                    <th class="border-0">{{ translate('Buy item quantity') }}</th>
                    <th class="border-0">{{ translate('Get item quantity') }}</th>
                    <th class="border-0">{{ translate('messages.Store') }}</th>
                    <th class="border-0">{{ translate('Usage limit') }}</th>
                    <th class="border-0">{{ translate('messages.Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['data'] as $key => $offer)
                    <tr>
                        <td>{{ $key + 1 }}</td>
                        <td>{{ Str::limit($offer->title, 40, '...') }}</td>
                        <td>
                            {{ $offer->start_date ? \App\CentralLogics\Helpers::date_format($offer->start_date) : translate('N/A') }}
                            -
                            {{ $offer->end_date ? \App\CentralLogics\Helpers::date_format($offer->end_date) : translate('N/A') }}
                        </td>
                        <td>{{ $offer->buy_qty }}</td>
                        <td>{{ $offer->get_qty }}</td>
                        <td>{{ $offer->enrollments_count ?? 0 }}</td>
                        <td>
                            {{-- Two independent caps, so both are named rather than folded into one number. --}}
                            {{ $offer->usage_limit_total
                                ? $offer->usage_limit_total.' '.translate('messages.Order')
                                : translate('N/A') }}
                            @if ($offer->usage_limit_per_customer)
                                ({{ translate('messages.Per person') }} {{ $offer->usage_limit_per_customer }})
                            @endif
                        </td>
                        <td class="text-capitalize">
                            @if ($offer->status)
                                <span class="badge badge-soft-success border-0">{{ translate('Active') }}</span>
                            @else
                                <span class="badge badge-soft-danger border-0">{{ translate('Inactive') }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
