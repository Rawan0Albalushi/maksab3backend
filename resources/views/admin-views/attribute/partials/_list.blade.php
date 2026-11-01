<div class="card mt-3">
    <div class="card-header py-2 border-0">
        <div class="search--button-wrapper">
            @include('partials._table-head', [
                'title'    => translate('messages.Attribute list'),
                'subtitle' => translate('messages.Reusable item options such as size or colour that vendors choose from.'),
                'count'    => $attributes->total(),
                'count_id' => 'itemCount',
            ])

            <form class="search-form w-340-lg">
                <div class="input-group input--group">
                    <input type="search" name="search" value="{{ request()?->search ?? null }}"
                        class="form-control h-40" placeholder="{{ translate('messages.Search attributes') }}"
                        aria-label="{{ translate('messages.Search attributes') }}">
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
                        href="{{ route('admin.attribute.export-attributes', ['type' => 'excel', request()->getQueryString()]) }}">
                        <img class="avatar avatar-xss avatar-4by3 mr-2"
                            src="{{ asset('public/assets/admin') }}/svg/components/excel.svg" alt="Image Description">
                        Excel
                    </a>
                    <a id="export-csv" class="dropdown-item"
                        href="{{ route('admin.attribute.export-attributes', ['type' => 'csv', request()->getQueryString()]) }}">
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
                class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table"
                data-hs-datatables-options='{
                    "order": [],
                    "orderCellsTop": true,
                    "paging":false
                  }'>
                <thead class="thead-light">
                    <tr>
                        <th class="border-0">{{ translate('messages.Attribute') }}</th>
                        <th class="border-0">{{ translate('messages.Translations') }}</th>
                        <th class="border-0 col--numeric">{{ translate('messages.Used in items') }}</th>
                        <th class="border-0 col--numeric">{{ translate('messages.Stores') }}</th>
                        <th class="border-0">{{ translate('messages.Created at') }}</th>
                        <th class="border-0">{{ translate('messages.Last updated') }}</th>
                        <th class="border-0 text-center">{{ translate('messages.Action') }}</th>
                    </tr>
                </thead>

                <tbody id="set-rows">
                    @foreach ($attributes as $attribute)
                        @php($itemCount = $usageStats[$attribute['id']]['items'] ?? 0)
                        @php($storeCount = $usageStats[$attribute['id']]['stores'] ?? 0)
                        @php($locales = $translatedLocales[$attribute['id']] ?? [])
                        <tr>
                            <td>
                                <div class="media align-items-center max-w-250">
                                    <div class="avatar avatar-sm avatar-circle avatar-soft-primary mr-2">
                                        <span class="avatar-initials">{{ strtoupper(mb_substr($attribute['name'] ?? '-', 0, 1)) }}</span>
                                    </div>
                                    <div class="media-body cell--truncate">
                                        <a class="font-weight-medium d-block"
                                            href="{{ route('admin.attribute.edit', [$attribute['id']]) }}"
                                            title="{{ $attribute['name'] }}">{{ $attribute['name'] }}</a>
                                        <small class="d-block">#{{ $attribute['id'] }}</small>
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
                            <td class="col--numeric" data-order="{{ $itemCount }}">
                                <span class="badge badge-soft-{{ $itemCount ? 'success' : 'secondary' }}"
                                    title="{{ $itemCount ? translate('messages.Items using this attribute') : translate('messages.Not used by any item yet') }}">
                                    {{ $itemCount }}
                                </span>
                            </td>
                            <td class="col--numeric" data-order="{{ $storeCount }}">
                                <span class="badge badge-soft-{{ $storeCount ? 'info' : 'secondary' }}"
                                    title="{{ $storeCount ? translate('messages.Stores offering this attribute') : translate('messages.No store offers this attribute yet') }}">
                                    {{ $storeCount }}
                                </span>
                            </td>
                            <td data-order="{{ $attribute['created_at'] }}">
                                <span title="{{ \App\CentralLogics\Helpers::time_date_format($attribute['created_at']) }}">
                                    {{ \App\CentralLogics\Helpers::date_format($attribute['created_at']) }}
                                </span>
                            </td>
                            <td data-order="{{ $attribute['updated_at'] }}">
                                <span title="{{ \App\CentralLogics\Helpers::time_date_format($attribute['updated_at']) }}">
                                    {{ \App\CentralLogics\Helpers::date_format($attribute['updated_at']) }}
                                </span>
                            </td>
                            <td>
                                <div class="btn--container justify-content-center">
                                    <a class="btn action-btn action-btn--edit"
                                        href="{{ route('admin.attribute.edit', [$attribute['id']]) }}"
                                        title="{{ translate('Edit') }}"><i class="tio-edit"></i>
                                    </a>
                                    <a class="btn action-btn action-btn--delete form-alert" href="javascript:"
                                        data-id="attribute-{{ $attribute['id'] }}"
                                        data-message="{{ $itemCount
                                            ? translate('messages.Items using this attribute') . ': ' . $itemCount . '. ' . translate('messages.Deleting it will remove it from them. Continue?')
                                            : translate('Want to delete this attribute?') }}"
                                        title="{{ translate('messages.Delete') }}"><i class="tio-delete-outlined"></i>
                                    </a>
                                    <form action="{{ route('admin.attribute.delete', [$attribute['id']]) }}" method="post"
                                        id="attribute-{{ $attribute['id'] }}"
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

    @if (count($attributes) !== 0)
        <hr>
    @endif

    @if (count($attributes) === 0)
        <div class="empty--data">
            <img src="{{ asset('/public/assets/admin/svg/illustrations/sorry.svg') }}" alt="public">
            @if (request()->input('search'))
                <h5>{{ translate('messages.No attribute matches your search.') }}</h5>
                <p class="text-muted font-size-sm">
                    {{ translate('Try a different spelling, or clear the search to see every attribute.') }}</p>
            @else
                <h5>{{ translate('messages.No attribute added yet.') }}</h5>
                <p class="text-muted font-size-sm">
                    {{ translate('Add your first one with the form above — size, colour and weight are good starting points.') }}
                </p>
            @endif
        </div>
    @endif

    <div class="page-area px-4 pb-3">
        {!! $attributes->withQueryString()->links() !!}
    </div>
</div>
