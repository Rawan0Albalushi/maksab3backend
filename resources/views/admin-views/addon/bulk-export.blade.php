@extends('layouts.admin.app')

@section('title',translate('Addon bulk export'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/third-party-setup.css') }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/bulk-tools.css') }}">
@endpush

@section('content')
    @include('partials.bulk._export', ['bulk' => [
        'title' => translate('messages.Export addons'),
        'subtitle' => translate('Download your add-ons as a spreadsheet you can edit and import back.'),
        'icon' => asset('public/assets/admin/img/addon.png'),
        'action' => route('admin.addon.bulk-export'),
        'summary' => $summary,
        'count_label' => translate('Add-ons in this module'),
        'count_hint' => translate('Across every store in the module.'),
        'file_name' => 'Addons.xlsx',
        'preview_all' => translate('Exporting every add-on in this module.'),
        'columns' => ['Id', 'Name', 'Price', 'StoreId', 'Status'],
        'tips' => [
            translate('An add-on belongs to one store, so StoreId is what ties it to the module.'),
            translate('Status is either active or inactive.'),
            translate('Keep the Id column untouched if you plan to import the file back as an update.'),
        ],
        'import_url' => route('admin.addon.bulk-import'),
        'import_title' => translate('messages.Addon bulk import'),
        'import_desc' => translate('Upload a filled spreadsheet to add or update add-ons.'),
        'help_title' => translate('Exporting add-ons'),
        'help_steps' => [
            translate('Pick the scope — everything, a date range, or an ID range.'),
            translate('Fill in the range if you picked one. Use full range fills in the bounds shown above.'),
            translate('Press export and the spreadsheet downloads in .xlsx format.'),
            translate('Edit the rows you need and bring the file back through bulk import to update them.'),
        ],
    ]])
@endsection
