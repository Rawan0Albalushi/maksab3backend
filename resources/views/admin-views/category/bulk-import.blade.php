@extends('layouts.admin.app')

@section('title', translate('messages.Category bulk import'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/third-party-setup.css') }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/bulk-tools.css') }}">
@endpush

@section('content')
    @include('partials.bulk._import', ['bulk' => [
        'title' => translate('messages.Category bulk import'),
        'subtitle' => translate('Upload a spreadsheet to add new categories or update the ones you already have.'),
        'icon' => asset('public/assets/admin/img/outline/category.svg'),
        'action' => route('admin.category.bulk-import'),
        'action_update' => route('admin.category.bulk-update'),
        'button_field' => false,
        'summary' => $summary,
        'count_label' => translate('Categories in this module'),
        'count_hint' => translate('Main categories') . ': ' . ($summary['parent_count'] ?? 0) . ' · ' . translate('Subcategories') . ': ' . ($summary['sub_count'] ?? 0),
        'mode_add_title' => translate('Add new categories'),
        'mode_add_desc' => translate('Every row becomes a new category.'),
        'mode_update_title' => translate('Update existing categories'),
        'mode_update_desc' => translate('Rows are matched on their ID and overwritten.'),
        'note_import' => translate('The Id column is ignored — new ids are assigned automatically.'),
        'note_update' => translate('Your file must keep its Id column. Matching rows are overwritten and cannot be restored, and a row with an unknown id is added as new.'),
        'templates' => [
            [
                'label' => translate('Spreadsheet template'),
                'hint' => translate('Carries the column headings and one sample row — replace that row with your own data.'),
                'url' => asset('public/assets/categories_bulk_format.xlsx'),
            ],
        ],
        'columns' => ['Name', 'Image', 'ParentId', 'Position', 'Priority', 'Status', translate('Id (update only)')],
        'tips' => [
            translate('Position tells a main category from a subcategory.') . ' ' . translate('Main category') . ': 0, ' . translate('Subcategory') . ': 1',
            translate('A subcategory needs a parentid belonging to a main category in this module.'),
            translate('Status is either active or inactive, and priority is a number.'),
            translate('Two categories under the same parent cannot share a name.'),
            translate('Image takes a file path from the category folder of the gallery, such as') . ' <code>2021-08-20-611fbe0e334c5.png</code>',
            translate('There is no module column — rows land in the module shown above.'),
        ],
        'export_url' => route('admin.category.bulk-export-index'),
        'export_title' => translate('messages.Export categories'),
        'export_desc' => translate('Download your current categories as a ready-made file to edit.'),
        'help_title' => translate('Importing categories'),
        'help_steps' => [
            translate('Download the template, or export your categories to start from the data you already have.'),
            translate('Fill one row per category and keep the column headings exactly as they come.'),
            translate('Pick whether the file adds new categories or updates existing ones.'),
            translate('Choose the file and press upload — the whole file is checked before anything is saved.'),
        ],
        'after' => 'admin-views.category.partials._bulk-import-parents',
    ]])
@endsection
