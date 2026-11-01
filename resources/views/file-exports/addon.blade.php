<div class="row">
    <div class="col-lg-12 text-center ">
        <h1> {{ translate('Addon list') }}
        </h1>
    </div>
    <div class="col-lg-12">

        <table>
            <thead>
                <tr>
                    <th>{{ translate('Filter criteria') }}</th>
                    <th></th>
                    <th>
                        {{ translate('Store') }}: {{ $data['store'] ?? translate('N/A') }}
                        <br>
                        {{ translate('Search bar content') }}: {{ $data['search'] ?? translate('N/A') }}

                    </th>
                    <th> </th>
                </tr>


                <tr>
                    <th>{{ translate('SL') }}</th>
                    <th>{{ translate('Addon name') }}</th>
                    <th>{{ translate('price') }}</th>
                    <th>{{ translate('Store name') }}</th>


                    @if ($data['productWiseTax'])
                        <th class="border-0 w--1">{{ translate('VAT/tax') }}</th>
                    @endif

            </thead>
            <tbody>
                @foreach ($data['data'] as $key => $addon)
                    <tr>
                        <td>{{ $loop->index + 1 }}</td>
                        <td>{{ $addon->name }}</td>
                        <td>
                            {{ \App\CentralLogics\Helpers::format_currency($addon->price) }}
                        </td>
                        <td>{{ $addon?->store?->name ?? translate('N/A') }}</td>


                        @if ($data['productWiseTax'])
                            <td>
                                <span class="d-block font-size-sm text-body">

                                    @forelse ($addon?->taxVats?->pluck('tax.name', 'tax.tax_rate')->toArray() as $key => $item)
                                        <br>
                                        <span> {{ $item }} : <span class="font-bold">
                                                ({{ $key }}%)
                                            </span> </span>
                                        <br>
                                    @empty
                                        <span> {{ translate('messages.No tax') }} </span>
                                    @endforelse
                                </span>
                            </td>
                        @endif

                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
