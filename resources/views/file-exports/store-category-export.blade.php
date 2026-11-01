<div class="row">
    <div class="col-lg-12 text-center ">
        <h1> {{ translate('Category list') }}
        </h1>
    </div>
    <div class="col-lg-12">

        <table>
            <thead>
                <tr>
                    <th>{{ translate('Filter criteria') }}</th>
                    <th></th>
                    <th>
                        {{ translate('Search bar content') }}: {{ $data['search'] ?? translate('N/A') }}

                    </th>
                    <th> </th>
                </tr>


                <tr>
                    <th>{{ translate('SL') }}</th>
                    <th>{{ translate('Category name') }}</th>
                    <th>{{ translate('Category ID') }}</th>
                    @if (!empty($data['showStore']))
                        <th>{{ \App\CentralLogics\Helpers::moduleStoreLabel() }}</th>
                    @endif
                    <th>{{ translate('Priority') }}</th>
                    @if ($data['categoryWiseTax'])
                        <th class="border-0 w--1">{{ translate('VAT/tax') }}</th>
                    @endif
                    <th>{{ translate('Status') }}</th>

            </thead>
            <tbody>
                @foreach ($data['data'] as $key => $category)
                    <tr>
                        <td>{{ $loop->index + 1 }}</td>
                        <td>{{ $category->name }}</td>
                        <td>{{ $category->id }}</td>
                        @if (!empty($data['showStore']))
                            <td>{{ $category->store?->name ?? translate('messages.N/A') }}</td>
                        @endif
                        @php
                            $return_value = match ($category->priority) {
                                0 => translate('messages.Normal'),
                                1 => translate('messages.medium'),
                                2 => translate('messages.High'),
                            };
                        @endphp
                        <td>{{ $return_value }}</td>
                        @if ($data['categoryWiseTax'])
                            <td>
                                <span class="d-block font-size-sm text-body">

                                    @forelse ($category?->taxVats?->pluck('tax.name', 'tax.tax_rate')->toArray() as $key => $item)
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
                        <td>{{ $category->status == 1 ? translate('messages.Active') : translate('messages.Inactive') }}
                        </td>

                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
