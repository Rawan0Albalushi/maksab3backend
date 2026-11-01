@extends('layouts.admin.app')

@section('title', translate('Category bulk export'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/third-party-setup.css') }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/bulk-tools.css') }}">
@endpush

@section('content')
    @include('partials.bulk._export', ['bulk' => [
        'title' => translate('messages.Export categories'),
        'subtitle' => translate('Download your categories as a spreadsheet you can edit and import back.'),
        'icon' => asset('public/assets/admin/img/outline/category.svg'),
        'action' => route('admin.category.bulk-export'),
        'summary' => $summary,
        'count_label' => translate('Categories in this module'),
        'count_hint' => translate('Main categories') . ': ' . ($summary['parent_count'] ?? 0) . ' · ' . translate('Subcategories') . ': ' . ($summary['sub_count'] ?? 0),
        'file_name' => \App\Enums\ExportFileNames\Admin\Category::EXPORT_XLSX,
        'preview_all' => translate('Exporting every category in this module.'),
        'columns' => ['Id', 'Name', 'Image', 'ParentId', 'Position', 'Priority', 'Status'],
        'tips' => [
            translate('Position tells a main category from a subcategory.') . ' ' . translate('Main category') . ': 0, ' . translate('Subcategory') . ': 1',
            translate('ParentId is empty on a main category and holds the parent category id on a subcategory.'),
            translate('Image holds the stored file path, not the picture itself.'),
            translate('Keep the Id column untouched if you plan to import the file back as an update.'),
        ],
        'import_url' => route('admin.category.bulk-import'),
        'import_title' => translate('messages.Category bulk import'),
        'import_desc' => translate('Upload a filled spreadsheet to add or update categories.'),
        'help_title' => translate('Exporting categories'),
        'help_steps' => [
            translate('Pick the scope — everything, a date range, or an ID range.'),
            translate('Fill in the range if you picked one. Use full range fills in the bounds shown above.'),
            translate('Press export and the spreadsheet downloads in .xlsx format.'),
            translate('Edit the rows you need and bring the file back through bulk import to update them.'),
        ],
    ]])
@endsection
