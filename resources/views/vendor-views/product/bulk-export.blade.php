@extends('layouts.vendor.app')

@section('title',translate('Item bulk export'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/third-party-setup.css') }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/bulk-tools.css') }}">
@endpush

@section('content')
    @include('partials.bulk._export', ['bulk' => [
        'title' => translate('messages.Export items'),
        'subtitle' => translate('Download your items as a spreadsheet you can edit and import back.'),
        'icon' => asset('public/assets/admin/img/items.png'),
        'action' => route('vendor.item.bulk-export'),
        'summary' => $summary,
        'count_label' => translate('Items in your store'),
        'show_module' => false,
        'file_name' => 'Items.xlsx',
        'preview_all' => translate('Exporting every item in your store.'),
        'columns' => ['Id', 'Name', 'Description', 'Image', 'Images', 'CategoryId', 'SubCategoryId', 'UnitId', 'Stock',
                      'Price', 'Discount', 'DiscountType', 'AvailableTimeStarts', 'AvailableTimeEnds', 'Variations',
                      'ChoiceOptions', 'AddOns', 'Attributes', 'StoreId', 'ModuleId', 'Status', 'Veg', 'Recommended'],
        'tips' => [
            translate('Variations, ChoiceOptions, AddOns and Attributes hold JSON. Keep them exactly as they come unless you know the format.'),
            translate('Image and images hold stored file paths, not the pictures themselves.'),
            translate('Keep the Id column untouched if you plan to import the file back as an update.'),
        ],
        'import_url' => route('vendor.item.bulk-import'),
        'import_title' => translate('messages.Item bulk import'),
        'import_desc' => translate('Upload a filled spreadsheet to add or update items.'),
        'help_title' => translate('Exporting items'),
        'help_steps' => [
            translate('Pick the scope — everything, a date range, or an ID range.'),
            translate('Fill in the range if you picked one. Use full range fills in the bounds shown above.'),
            translate('Press export and the spreadsheet downloads in .xlsx format.'),
            translate('Edit the rows you need and bring the file back through bulk import to update them.'),
        ],
    ]])
@endsection
