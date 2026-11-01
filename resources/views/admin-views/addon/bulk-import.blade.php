@extends('layouts.admin.app')

@section('title',translate('messages.Addon bulk import'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/third-party-setup.css') }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/bulk-tools.css') }}">
@endpush

@section('content')
    @include('partials.bulk._import', ['bulk' => [
        'title' => translate('messages.Addon bulk import'),
        'subtitle' => translate('Upload a spreadsheet to add new add-ons or update the ones you already have.'),
        'icon' => asset('public/assets/admin/img/addon.png'),
        'action' => route('admin.addon.bulk-import'),
        'action_update' => route('admin.addon.bulk-update'),
        'summary' => $summary,
        'count_label' => translate('Add-ons in this module'),
        'mode_add_title' => translate('Add new add-ons'),
        'mode_add_desc' => translate('Every row becomes a new add-on.'),
        'mode_update_title' => translate('Update existing add-ons'),
        'mode_update_desc' => translate('Rows are matched on their ID and overwritten.'),
        'note_import' => translate('The Id column is ignored — new ids are assigned automatically.'),
        'note_update' => translate('Your file must keep its Id column. Matching add-ons are overwritten and cannot be restored.'),
        'templates' => [
            [
                'label' => translate('Template with current data'),
                'hint' => translate('The heading row plus example rows, so you can see how each column is filled.'),
                'url' => asset('public/assets/addons_bulk_format.xlsx'),
            ],
            [
                'label' => translate('Empty template'),
                'hint' => translate('Only the heading row — start from a clean sheet.'),
                'url' => asset('public/assets/addons_bulk_format_nodata.xlsx'),
            ],
        ],
        'columns' => ['Name', 'Price', 'StoreId', 'Status', translate('Id (update only)')],
        'tips' => [
            translate('Name and StoreId must carry a value, and StoreId has to be a number.'),
            translate('Price cannot be negative.'),
            translate('Status is either active or inactive.'),
        ],
        'export_url' => route('admin.addon.bulk-export-index'),
        'export_title' => translate('messages.Export addons'),
        'export_desc' => translate('Download your current add-ons as a ready-made file to edit.'),
        'help_title' => translate('Importing add-ons'),
        'help_steps' => [
            translate('Download a template, or export your add-ons to start from the data you already have.'),
            translate('Fill one row per add-on and keep the column headings exactly as they come.'),
            translate('Pick whether the file adds new add-ons or updates existing ones.'),
            translate('Choose the file and press upload — the whole file is checked before anything is saved.'),
        ],
    ]])
@endsection
