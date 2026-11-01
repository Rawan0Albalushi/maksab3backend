@extends('layouts.admin.app')

@section('title', translate('Automated message'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/owl.min.css') }}">
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title mr-3">
                <span class="page-header-icon">
                    <img src="{{ asset('public/assets/admin/img/outline/business.svg') }}" class="w--26" alt="">
                </span>
                <span>
                    {{ translate('Business setup') }}
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('Canned replies your team can send in chat instead of typing the same answer again.') }}</p>
            @include('admin-views.business-settings.partials.nav-menu')
        </div>

        <div class="card mb-20">
            <div class="card-body">
                <div class="mb-0">
                    <h3 class="mb-1">
                        {{ translate('Automated Message/Reason') }}
                    </h3>
                    <p class="mb-0 fs-12">
                        {{ translate('Predefined reasons customers pick from when reporting a problem with an order.') }}
                    </p>
                </div>
                <div class="fs-12 text-dark px-3 py-2 bg-opacity-10 rounded bg-info mt-20">
                    <div class="d-flex align-items-center gap-2 mb-0">
                        <span class="text-info fs-16">
                            <i class="tio-light-on"></i>
                        </span>
                        <span>
                            {{ translate('All orders you can see and manage from All order list page') }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="mb-0">
                    <h3 class="mb-1">
                        {{ translate('Automated Message Setup') }}
                    </h3>
                    <p class="mb-0 fs-12">
                        {{ translate('You must setup the automated message or reason to allow the customer choose from those for reporting') }}
                    </p>
                </div>
            </div>
            <div class="card-body">
                <div class="report-card-inner bg-light2 rounded p-xxl-20 p-3 mb-4 mw-100">
                    <form action="{{ route('admin.business-settings.automated_message.store') }}" method="post">
                        @csrf
                        @if ($language)
                            <ul class="nav nav-tabs nav--tabs d-block nav-slider owl-theme owl-carousel mb-4">
                                <li class="nav-item">
                                    <a class="nav-link lang_link active px-0" href="#"
                                        id="default-link" data-text="{{ translate('Default') }}">{{ translate('Default') }}</a>
                                </li>
                                @foreach ($language as $lang)
                                    <li class="nav-item">
                                        <a class="nav-link lang_link px-0" href="#"
                                            id="{{ $lang }}-link" data-text="{{ \App\CentralLogics\Helpers::get_language_name($lang) . '(' . strtoupper($lang) . ')' }}">{{ \App\CentralLogics\Helpers::get_language_name($lang) . '(' . strtoupper($lang) . ')' }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        <div class="row align-items-end">
                            <div class="col-md-12 lang_form default-form">
                                <label for="reason" class="form-label">{{ translate('Automated Message/Reason') }}
                                    ({{ translate('Default') }})

                                    <span class="input-label-secondary text--title" data-toggle="tooltip"
                                        data-placement="right"
                                        data-original-title="{{ translate('Predefined reasons guide customers when they report a problem with an order.') }}">
                                        <i class="tio-info text-muted"></i>
                                    </span>
                                    <span class="text-danger">*</span>
                                </label>
                                <textarea id="reason" type="text" class="form-control h--45px" name="message[]" maxlength="150"
                                    placeholder="{{ translate('Ex') . ':' . translate('Enter the message') }}"></textarea>
                                <span class="text-right text-counting color-A7A7A7 d-block mt-1">0/150</span>
                                <input type="hidden" name="lang[]" value="default">
                            </div>
                            @if ($language)
                                @foreach ($language as $lang)
                                    <div class="col-md-12 d-none lang_form" id="{{ $lang }}-form">
                                        <label for="reason{{ $lang }}"
                                            class="form-label">{{ translate('Automated Message/Reason') }}
                                            ({{ strtoupper($lang) }})
                                        </label>
                                        <textarea id="reason{{ $lang }}" type="text" class="form-control h--45px" name="message[]" maxlength="150"
                                            placeholder="{{ translate('Ex') . ':' . translate('Enter the message') }}"></textarea>
                                        <span class="text-right text-counting color-A7A7A7 d-block mt-1">0/150</span>
                                        <input type="hidden" name="lang[]" value="{{ $lang }}">
                                    </div>
                                @endforeach
                            @endif
                        </div>
                        <div class="mt-20 btn--container justify-content-end">
                            <button type="reset" id="reset_btn"
                                class="btn btn--reset"><i class="tio-refresh"></i> {{ translate('messages.Reset') }}</button>
                            <button type="submit" class="btn btn--primary"><i class="tio-save"></i> {{ translate('messages.Save') }}</button>
                        </div>

                    </form>
                </div>
                <div class="card">
                    <div class="card-header align-items-center gap-3 flex-wrap border-0">
                        <div class="mx-1">
                        </div>
                        <div class="search--button-wrapper justify-content-end">
                            @include('partials._table-head', [
                                'title'    => translate('messages.Automated Message List'),
                                'subtitle' => translate('messages.Preset messages sent automatically at each stage of an order.'),
                                'count'    => $messages->total(),
                                'count_id' => 'itemCount',
                            ])

                            <form class="search-form">
                                <div class="input-group input--group">
                                    <input id="datatableSearch" name="search" value="{{ request()?->search ?? null }}"
                                        type="search" class="form-control h--40px"
                                        placeholder="{{ translate('Ex') }}: Search here"
                                        aria-label="{{ translate('Search') }}">
                                    <button type="submit" class="btn btn--secondary h--40px"><i
                                            class="tio-search"></i></button>
                                </div>
                            </form>
                            @if (request()->input('search'))
                                <button type="reset" class="btn btn--primary ml-2 location-reload-to-base"
                                    data-url="{{ url()->full() }}"><i class="tio-refresh"></i> {{ translate('messages.Reset') }}</button>
                            @endif
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive datatable-custom">
                            <table id="columnSearchDatatable"
                                class="table table-borderless table-thead-bordered table-align-middle"
                                data-hs-datatables-options='{
                                "isResponsive": false,
                                "isShowPaging": false,
                                "paging":false
                            }'>
                                <thead class="thead-light">
                                    <tr>
                                        <th class="border-0">{{ translate('messages.SL') }}</th>
                                        <th class="border-0">{{ translate('messages.message') }}</th>
                                        <th class="border-0">{{ translate('messages.Status') }}</th>
                                        <th class="border-0 text-center">{{ translate('messages.Action') }}</th>
                                    </tr>
                                </thead>

                                <tbody id="table-div">
                                    @foreach ($messages as $key => $message)
                                        <tr>
                                            <td class="fs-14 text-dark">{{ $key + $messages->firstItem() }}</td>

                                            <td>
                                                <span
                                                    class="d-block text-body fs-14 text-dark line--limit-2 min-w-176px max-w-490"
                                                    title="{{ $message->message }}">
                                                    {{ Str::limit($message->message, 50, '...') }}
                                                </span>
                                            </td>
                                            <td>
                                                <label class="toggle-switch toggle-switch-sm"
                                                    for="stocksCheckbox{{ $message->id }}">
                                                    <input type="checkbox"
                                                        data-url="{{ route('admin.business-settings.automated_message.status', [$message['id'], $message->status ? 0 : 1]) }}"
                                                        class="toggle-switch-input redirect-url"
                                                        id="stocksCheckbox{{ $message->id }}"
                                                        {{ $message->status ? 'checked' : '' }}>
                                                    <span class="toggle-switch-label">
                                                        <span class="toggle-switch-indicator"></span>
                                                    </span>
                                                </label>
                                            </td>

                                            <td>
                                                <div class="btn--container justify-content-center">
                                                    <a class="btn btn-sm text-end action-btn action-btn--edit offcanvas-trigger get_data data-info-show"
                                                        data-target="#offcanvas__customBtn3"
                                                        data-id="{{ $message->id }}"
                                                        data-url="{{ route('admin.business-settings.automated_message.edit', [$message->id]) }}"
                                                        href="javascript:"
                                                        title="{{ translate('messages.edit Message') }}"><i
                                                            class="tio-edit"></i></a>



                                                    <a class="btn btn-sm action-btn action-btn--delete form-alert"
                                                        href="javascript:" data-id="refund_reason-{{ $message['id'] }}"
                                                        data-message="{{ translate('Want to delete this message?') }}"
                                                        title="{{ translate('messages.Delete') }}">
                                                        <i class="tio-delete-outlined"></i>
                                                    </a>
                                                    <form
                                                        action="{{ route('admin.business-settings.automated_message.destroy', [$message['id']]) }}"
                                                        method="post" id="refund_reason-{{ $message['id'] }}">
                                                        @csrf @method('delete')
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            @if (count($messages) === 0)
                                <div class="empty--data">
                                    <img src="{{ asset('/public/assets/admin/svg/illustrations/sorry.svg') }}"
                                        alt="public">
                                    <h5>
                                        {{ translate('No data found') }}
                                    </h5>
                                </div>
                            @endif
                        </div>
                        <div class="card-footer pt-0 border-0">
                            <div class="page-area px-4 pb-3">
                                <div class="d-flex align-items-center justify-content-end">
                                    <div>
                                        {!! $messages->withQueryString()->links() !!}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>



    </div>
    </div>

    <div id="global_guideline_offcanvas"
    class="custom-offcanvas d-flex flex-column justify-content-between global_guideline_offcanvas">
    <div>
        <div class="custom-offcanvas-header bg--secondary d-flex justify-content-between align-items-center px-3 py-3">
            <h3 class="mb-0">{{ translate('Automated Message Guideline') }}</h3>
            <button type="button"
                class="btn-close w-25px h-25px border rounded-circle d-center bg--secondary offcanvas-close fz-15px p-0"
                aria-label="Close">&times;</button>
        </div>

        <div class="py-3 px-3 bg-light rounded mb-3 mb-sm-20">
            <div class="d-flex gap-2 align-items-center justify-content-between overflow-hidden">
                <button class="btn-collapse d-flex gap-2 align-items-center bg-transparent border-0 p-0 collapsed"
                    type="button" data-toggle="collapse" data-target="#automated_message_guide" aria-expanded="true">
                    <div
                        class="btn-collapse-icon w-35px h-35px bg-white d-flex align-items-center justify-content-center border icon-btn rounded-circle fs-12 lh-1">
                        <i class="tio-down-ui"></i>
                    </div>
                    <span class="font-semibold text-left fs-14 text-title">{{ translate('Automated message') }}</span>
                </button>
                <a href="#automated_message_section"
                    class="text-info text-underline fs-12 text-nowrap offcanvas-close offcanvas-close-btn">{{ translate('Let\'s setup') }}</a>
            </div>
            <div class="collapse mt-3 show" id="automated_message_guide">
                <div class="card card-body">
                    <div class="">
                        <h5 class="mb-3">{{ translate('Automated message') }}</h5>
                        <p class="fs-12 mb-0">
                            {{ translate('Manage the predefined messages customers pick when reporting an issue, and activate each as needed.') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>



    <div id="offcanvasOverlay" class="offcanvas-overlay"></div>
    <div id="offcanvas__customBtn3" class="custom-offcanvas d-flex flex-column justify-content-between">
        <div id="data-view" class="h-100">
        </div>
    </div>


@endsection
@push('script_2')
<script src="{{asset('public/assets/admin/js/view-pages/offcanvas-edit.js')}}"></script>
    <script src="{{ asset('public/assets/admin/js/owl.min.js') }}"></script>
    <script>
        $('.nav-slider').owlCarousel({
            margin: 30,
            loop: false,
            autoWidth: true,
            items: 4
        })

    </script>
@endpush
