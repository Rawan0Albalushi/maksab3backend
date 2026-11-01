@php
    $isSubCategory = $data['isSubCategory'] ?? false;
@endphp
<div class="row">
    <div class="col-lg-12 text-center ">
        <h1> {{ $isSubCategory ? translate('Subcategory list') : translate('Category list') }}
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
                    <th>{{ $isSubCategory ? translate('Subcategory name') : translate('Category name') }}</th>
                    <th>{{ $isSubCategory ? translate('Subcategory ID') : translate('Category ID') }}</th>
                    <th>{{ translate('Module') }}</th>
                    <th>{{ translate('Priority') }}</th>
                    @if (isset($data['module'])  && $data['module'] == 'ecommerce')
                    <th>{{ translate('featured') }}</th>

                    @endif
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
                        <td>{{ $category?->module?->module_name }}</td>
                        @php
                            $return_value = match ($category->priority) {
                                0 => translate('messages.Normal'),
                                1 => translate('messages.medium'),
                                2 => translate('messages.High'),
                            };
                        @endphp
                        <td>{{ $return_value }}</td>
                         @if (isset($data['module'])  && $data['module'] == 'ecommerce')
                         <td>{{ $category->featured == 1 ? translate('messages.Yes') : '--' }} </td>
                        @endif
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
                        <td>{{ $category->status == 1 ? translate('messages.Active') : translate('messages.Inactive') }}  </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
