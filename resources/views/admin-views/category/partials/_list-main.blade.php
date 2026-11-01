<div class="card mt-3">
    <ul class="nav nav-tabs flex-wrap tabs-inner border-0 nav--tabs px-3 pt-3">
        <li class="nav-item">
            <a class="nav-link {{ $status === 'all' ? 'active' : '' }}"
                href="{{ route('admin.category.add', ['position' => 0, 'status' => 'all']) }}">{{ translate('All') }}</a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $status === 'active' ? 'active' : '' }}"
                href="{{ route('admin.category.add', ['position' => 0, 'status' => 'active']) }}">{{ translate('messages.Active') }}</a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $status === 'inactive' ? 'active' : '' }}"
                href="{{ route('admin.category.add', ['position' => 0, 'status' => 'inactive']) }}">{{ translate('messages.Inactive') }}</a>
        </li>
    </ul>
    <div class="card-header py-2 border-0">
        <div class="search--button-wrapper">

            @include('partials._table-head', [
                'title'    => translate('Main category list'),
                'subtitle' => translate('messages.Top-level categories customers browse in the app.'),
                'count'    => $categories->total(),
                'count_id' => 'itemCount',
            ])

            <form class="search-form w-340-lg">
                <div class="input-group input--group">
                    <input type="search" name="search" value="{{ request()?->search ?? null }}"
                        class="form-control h-40" placeholder="{{ translate('messages.Search main categories') }}"
                        aria-label="{{ translate('Ex') }}: Categories">
                    <input type="hidden" name="position" value="0">
                    <input type="hidden" name="status" value="{{ $status }}">
                    <button type="submit" class="btn btn--primary h-40"><i class="tio-search"></i></button>
                </div>
            </form>
            @if (request()->input('search'))
                <a class="btn btn--primary ml-2 list-reset-search"
                    href="{{ request()->fullUrlWithoutQuery(['search', 'page']) }}"><i class="tio-refresh"></i>
                    {{ translate('messages.Reset') }}</a>
            @endif
            <div class="hs-unfold mr-2">
                <a class="js-hs-unfold-invoker btn btn-sm btn-white text-title dropdown-toggle font-medium min-height-40"
                    href="javascript:;"
                    data-hs-unfold-options='{
                            "target": "#usersExportDropdown",
                            "type": "css-animation"
                        }'>
                    <i class="tio-download-to mr-1 text-title"></i> {{ translate('messages.Export') }}
                </a>

                <div id="usersExportDropdown"
                    class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">

                    <span class="dropdown-header">{{ translate('messages.Download options') }}</span>
                    <a id="export-excel" class="dropdown-item"
                        href="{{ route('admin.category.export-categories', ['type' => 'excel', request()->getQueryString()]) }}">
                        <img class="avatar avatar-xss avatar-4by3 mr-2"
                            src="{{ asset('public/assets/admin') }}/svg/components/excel.svg"
                            alt="Image Description">
                        Excel
                    </a>
                    <a id="export-csv" class="dropdown-item"
                        href="{{ route('admin.category.export-categories', ['type' => 'csv', request()->getQueryString()]) }}">
                        <img class="avatar avatar-xss avatar-4by3 mr-2"
                            src="{{ asset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg"
                            alt="Image Description">
                        CSV
                    </a>

                </div>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive datatable-custom">
            <table id="columnSearchDatatable"
                class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table"
                data-hs-datatables-options='{
                    "isResponsive": false,
                    "isShowPaging": false,
                    "paging":false
                }'>
                <thead class="thead-light">
                    <tr>
                        <th class="border-0 w--1">{{ translate('messages.Category') }}</th>
                        <th class="border-0">{{ translate('messages.Translations') }}</th>
                        <th class="border-0 col--numeric">{{ translate('Subcategories') }}</th>
                        <th class="border-0 col--numeric">{{ translate('messages.Items') }}</th>
                        @if ($categoryWiseTax)
                            <th class="border-0">{{ translate('VAT/tax') }}</th>
                        @endif
                        <th class="border-0 text-center">{{ translate('messages.Priority') }}
                            <span class="input-label-secondary" data-toggle="tooltip" data-placement="right"
                                data-original-title="{{ translate('Categories will be displayed based on priority order: High first, then Medium, and finally Low') }} "><img
                                    src="{{ asset('public/assets/admin/img/info-circle.svg') }}" alt="public/img"></span>
                        </th>
                        <th class="border-0">{{ translate('messages.Created') }}</th>
                        @if (Config::get('module.current_module_type') == 'ecommerce')
                            <th class="border-0 text-center">{{ translate('messages.featured') }}</th>
                        @endif
                        <th class="border-0 text-center">{{ translate('messages.Status') }}</th>
                        <th class="border-0 text-center">{{ translate('messages.Action') }}</th>
                    </tr>
                </thead>

                <tbody id="table-div">
                    @foreach ($categories as $category)
                        @php($subCount = $subCategoryCounts[$category->id] ?? 0)
                        @php($itemCount = $itemCounts[$category->id] ?? 0)
                        @php($locales = $translatedLocales[$category->id] ?? [])
                        <tr>
                            <td>
                                <div class="media-area d-flex gap-2 align-items-center">
                                    <div class="w-40px min-w-40px h-40px rounded overflow-hidden border">
                                        <img src="{{ $category['image_full_url'] }}" alt=""
                                            class="w-100 rounded object-cover">
                                    </div>
                                    <div>
                                        <a class="fs-14 line--limit-2 text-title max-w-250 min-w-160 font-weight-medium offcanvas-trigger data-info-show"
                                            href="javascript:void(0)" data-id="{{ $category['id'] }}"
                                            data-url="{{ route('admin.category.edit', [$category['id']]) }}"
                                            data-target="#offcanvas__categoryBtn"
                                            title="{{ $category['name'] }}">
                                            {{ Str::limit($category['name'], 20, '...') }}
                                        </a>
                                        <p class="m-0 text-muted font-size-sm">#{{ $category->id }}</p>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if (count($locales))
                                    <span class="cell-chips">
                                        @foreach ($locales as $locale)
                                            <span class="cell-chip"
                                                title="{{ \App\CentralLogics\Helpers::get_language_name($locale) }}">{{ strtoupper($locale) }}</span>
                                        @endforeach
                                    </span>
                                @else
                                    <span class="text-muted font-size-sm">{{ translate('messages.Default only') }}</span>
                                @endif
                            </td>
                            <td class="col--numeric">
                                <span class="badge badge-soft-{{ $subCount ? 'info' : 'secondary' }}"
                                    title="{{ $subCount ? translate('Subcategories') . ': ' . $subCount : translate('No subcategory under this one yet') }}">
                                    {{ $subCount }}
                                </span>
                            </td>
                            <td class="col--numeric">
                                @if ($itemCount)
                                    <a class="badge badge-soft-success"
                                        href="{{ route('admin.item.list', ['category_id' => $category->id]) }}"
                                        title="{{ translate('messages.See the items in this category') }}">
                                        {{ $itemCount }}
                                    </a>
                                @else
                                    <span class="badge badge-soft-secondary"
                                        title="{{ translate('messages.No item in this category yet') }}">0</span>
                                @endif
                            </td>

                            @if ($categoryWiseTax)
                                <td>
                                    <span class="d-block fs-14 text-title text-body ">
                                        @forelse ($category?->taxVats?->pluck('tax.name', 'tax.tax_rate')->toArray() as $key => $tax)
                                            <span class="bg-light rounded py-2 px-3">
                                                {{ $tax }} :
                                                <span class="font-light">
                                                    ({{ $key }}%)
                                                </span>
                                            </span>
                                            <br>
                                        @empty
                                            <span> {{ translate('messages.N/A') }} </span>
                                        @endforelse
                                    </span>
                                </td>
                            @endif
                            <td>
                                <form action="{{ route('admin.category.priority', $category->id) }}"
                                    class="priority-form">
                                    <select name="priority"
                                        class="form-control form--control-select  priority-select  mx-auto {{ $category->priority == 0 ? 'text-title' : '' }} {{ $category->priority == 1 ? 'text-info' : '' }} {{ $category->priority == 2 ? 'text-success' : '' }}">
                                        <option value="0" class="text--title"
                                            {{ $category->priority == 0 ? 'selected' : '' }}>
                                            {{ translate('messages.Normal') }}</option>
                                        <option value="1" class="text--title"
                                            {{ $category->priority == 1 ? 'selected' : '' }}>
                                            {{ translate('messages.medium') }}</option>
                                        <option value="2" class="text--title"
                                            {{ $category->priority == 2 ? 'selected' : '' }}>
                                            {{ translate('messages.High') }}</option>
                                    </select>
                                </form>

                            </td>
                            <td>
                                <span class="font-size-sm"
                                    title="{{ \App\CentralLogics\Helpers::time_date_format($category['created_at']) }}">
                                    {{ \App\CentralLogics\Helpers::date_format($category['created_at']) }}
                                </span>
                            </td>
                            @if (Config::get('module.current_module_type') == 'ecommerce')
                                <td>
                                    <label class="toggle-switch toggle-switch-sm"
                                        for="featuredCheckbox{{ $category->id }}">
                                        <input type="checkbox" data-id="featuredCheckbox{{ $category->id }}"
                                            data-type="status"
                                            data-image-on="{{ asset('/public/assets/admin/img/status-ons.png') }}"
                                            data-image-off="{{ asset('/public/assets/admin/img/off-danger.png') }}"
                                            data-title-on="{{ translate('Do you want to featured this main category?') }}"
                                            data-title-off="{{ translate('Do you want to remove this main category from featured?') }}"
                                            data-text-on="<p>{{ translate('If you turn on this main category as a featured category it will show in customer app landing page.') }}"
                                            data-text-off="<p>{{ translate('If you turn off this main category from featured category it will not show in customer app landing page.') }}</p>"
                                            class="toggle-switch-input dynamic-checkbox"
                                            id="featuredCheckbox{{ $category->id }}"
                                            {{ $category->featured ? 'checked' : '' }}>
                                        <span class="toggle-switch-label mx-auto">
                                            <span class="toggle-switch-indicator"></span>
                                        </span>
                                    </label>

                                    <form
                                        action="{{ route('admin.category.featured', [$category['id'], $category->featured ? 0 : 1]) }}"
                                        method="get" id="featuredCheckbox{{ $category->id }}_form">
                                    </form>
                                </td>
                            @endif
                            <td>
                                <label class="toggle-switch toggle-switch-sm"
                                    for="stocksCheckbox{{ $category->id }}">
                                    <input type="checkbox"
                                        data-url="{{ route('admin.category.status', [$category['id'], $category->status ? 0 : 1]) }}"
                                        class="toggle-switch-input redirect-url"
                                        id="stocksCheckbox{{ $category->id }}"
                                        @if ($status !== 'all') data-ajax-refresh="[data-ajax-region]" @endif
                                        {{ $category->status ? 'checked' : '' }}>
                                    <span class="toggle-switch-label mx-auto">
                                        <span class="toggle-switch-indicator"></span>
                                    </span>
                                </label>
                            </td>
                            <td>
                                <div class="btn--container justify-content-center">

                                    <a class="btn action-btn action-btn--edit offcanvas-trigger data-info-show"
                                        href="javascript:void(0)" data-id="{{ $category['id'] }}"
                                        data-url="{{ route('admin.category.edit', [$category['id']]) }}"
                                        data-target="#offcanvas__categoryBtn"
                                        title="{{ translate('messages.Edit main category') }}">
                                        <i class="tio-edit"></i>
                                    </a>
                                    <a class="btn action-btn action-btn--delete form-alert"
                                        href="javascript:" data-id="category-{{ $category['id'] }}"
                                        data-message="{{ $subCount
                                            ? translate('This category has subcategories. delete those first — the category cannot be removed while they exist.') . ' ' . translate('Subcategories') . ': ' . $subCount
                                            : ($itemCount
                                                ? translate('messages.Items in this category will be left without one. Delete it anyway?') . ' ' . translate('messages.Items') . ': ' . $itemCount
                                                : translate('Want to delete this main category?')) }}"
                                        title="{{ translate('messages.Delete main category') }}"><i
                                            class="tio-delete-outlined"></i>
                                    </a>
                                    <form action="{{ route('admin.category.delete', [$category['id']]) }}"
                                        method="post" id="category-{{ $category['id'] }}"
                                        data-ajax-form data-ajax-remove="closest:tr"
                                        data-ajax-refresh="[data-ajax-region]">
                                        @csrf @method('delete')
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @if (count($categories) !== 0)
        <hr>
    @endif

    @if (count($categories) === 0)
        <div class="empty--data">
            <img src="{{ asset('/public/assets/admin/svg/illustrations/sorry.svg') }}" alt="public">
            @if (request()->input('search'))
                <h5>{{ translate('messages.No main category matches your search.') }}</h5>
                <p class="text-muted font-size-sm">
                    {{ translate('Try a different spelling, or clear the search to see every main category.') }}</p>
            @else
                <h5>{{ translate('messages.No main category added yet.') }}</h5>
                <p class="text-muted font-size-sm">
                    {{ translate('Add your first one with the form above — it is what customers see at the top of the app.') }}
                </p>
            @endif
        </div>
    @endif
    <div class="page-area px-4 pb-3">
        <div class="d-flex align-items-center justify-content-end">
            <div>
                {!! $categories->withQueryString()->links() !!}
            </div>
        </div>
    </div>
</div>
