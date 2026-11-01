@extends('layouts.admin.app')

@section('title',translate('messages.Store bulk import'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/third-party-setup.css') }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/bulk-tools.css') }}">
@endpush

@section('content')
    @include('partials.bulk._import', ['bulk' => [
        'title' => translate('messages.Store bulk import'),
        'subtitle' => translate('Upload a spreadsheet to add new stores or update the ones you already have.'),
        'icon' => asset('public/assets/admin/img/shop.png'),
        'action' => route('admin.store.bulk-import'),
        'summary' => $summary,
        'count_label' => translate('Stores in this module'),
        'mode_add_title' => translate('Add new stores'),
        'mode_add_desc' => translate('Every row becomes a new store with its owner account.'),
        'mode_update_title' => translate('Update existing stores'),
        'mode_update_desc' => translate('Rows are matched on their ID and overwritten.'),
        'note_import' => translate('A store is rejected if its email or phone already belongs to another store.'),
        'note_update' => translate('Your file must keep its Id column. Matching stores are overwritten and cannot be restored.'),
        'templates' => [
            [
                'label' => translate('Template with current data'),
                'hint' => translate('The heading row plus example rows, so you can see how each column is filled.'),
                'url' => asset('public/assets/stores_bulk_format.xlsx'),
            ],
            [
                'label' => translate('Empty template'),
                'hint' => translate('Only the heading row — start from a clean sheet.'),
                'url' => asset('public/assets/stores_bulk_format_nodata.xlsx'),
            ],
        ],
        'aside_subtitle' => translate('These columns must carry a value in every row.'),
        'columns' => ['storeName', 'ownerFirstName', 'ownerLastName', 'email', 'phone', 'logo', 'CoverPhoto', 'Address',
                      'latitude', 'longitude', 'zone_id', 'module_id', 'MinimumDeliveryFee', 'MaximumDeliveryFee',
                      'PerKmDeliveryFee', 'MinimumOrderAmount', 'Comission', 'DeliveryTime'],
        'tips' => [
            translate('Two rows cannot share an email or a phone number, and neither can clash with a store you already have.'),
            translate('Zone ID and module ID come from their own lists — the file is not checked against names.'),
            translate('latitude and longitude place the store on the map, and must sit inside the zone you name.'),
            translate('Logo and cover photo paths come from the store folder in the gallery.'),
        ],
        'export_url' => route('admin.store.bulk-export-index'),
        'export_title' => translate('messages.Export stores'),
        'export_desc' => translate('Download your current stores as a ready-made file to edit.'),
        'help_title' => translate('Importing stores'),
        'help_steps' => [
            translate('Download a template, or export your stores to start from the data you already have.'),
            translate('Fill one row per store and keep the column headings exactly as they come.'),
            translate('Pick whether the file adds new stores or updates existing ones.'),
            translate('Choose the file and press upload — the whole file is checked before anything is saved.'),
        ],
    ]])
@endsection
