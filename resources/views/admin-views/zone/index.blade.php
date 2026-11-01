@extends('layouts.admin.app')

@section('title', translate('Zone setup'))

@push('css_or_js')
    <link rel="stylesheet"
        href="{{ asset('public/assets/admin/css/admin-shared.css') }}?v={{ @filemtime(public_path('public/assets/admin/css/admin-shared.css')) }}">
    <link rel="stylesheet"
        href="{{ asset('public/assets/admin/css/connect-module.css') }}?v={{ @filemtime(public_path('public/assets/admin/css/connect-module.css')) }}">
@endpush

@section('content')
<div class="content container-fluid">
    <div class="page-header">
        <h1 class="page-header-title">
            <span class="page-header-icon">
                <img src="{{ asset('public/assets/admin/img/outline/zone.svg') }}" class="w--26" alt="">
            </span>
            <span>
                {{translate('Zone setup')}}
            </span>
        </h1>
        <p class="page-header-desc">{{ translate('A zone is the area you deliver to. Draw it on the map and it starts taking orders.') }}</p>
    </div>

    <div class="admin-alert mb-20">
        <span class="admin-alert__icon">i</span>
        <span>{{ translate('A new zone stays invisible to customers until a module is connected to it. Use the connect module action on the zone\'s row to choose what it sells and deliver its first order.') }}</span>
    </div>

    <div class="row g-3">
        <div class="col-12">
            <form action="javascript:" method="post" id="zone_form" class="card p-20">
                <div class="card-header flex-wrap gap-1 pt-0 mb-20">
                    <h4 class="mb-0">{{translate('Add New Zone')}}</h4>
                    <a href="#0"
                        class="border-primary py-2 px-3 border d-flex align-items-center gap-2 fs-14 font-semibold theme-clr-dark bg-opacity-primary-10 rounded-pill offcanvas-trigger"
                        data-target="#instruction__customBtn2">
                        {{ translate('View Demo') }} <i class="tio-info-outined"></i>
                    </a>
                </div>
                @csrf
                <div class="row g-3 justify-content-between">
                    <div class="col-md-6">
                        <div class="bg-light rounded p-20">
                            @if($language)
                                <ul class="nav nav-tabs mb-4">
                                    <li class="nav-item">
                                        <a class="nav-link lang_link active" href="#"
                                            id="default-link">{{translate('Default')}}</a>
                                    </li>
                                    @foreach ($language as $lang)
                                        <li class="nav-item">
                                            <a class="nav-link lang_link" href="#"
                                                id="{{ $lang }}-link">{{ \App\CentralLogics\Helpers::get_language_name($lang) . '(' . strtoupper($lang) . ')' }}</a>
                                        </li>
                                    @endforeach
                                    <span class="form-label-secondary text-danger" data-toggle="tooltip"
                                        data-placement="right"
                                        data-original-title="{{ translate('Choose your preferred language & set your zone name.') }}"><img
                                            src="{{ asset('/public/assets/admin/img/info-circle.svg') }}"
                                            alt="{{ translate('Choose your preferred language & set your zone name.') }}"></span>
                                </ul>
                                <div class="tab-content">
                                    <div class="row g-3 lang_form" id="default-form">
                                        <div class="form-group col-12 mb-0">
                                            <label class="input-label"
                                                for="exampleFormControlInput1">{{ translate('Business zone name')}}
                                                ({{ translate('Default') }})
                                                <span class="text-danger">*</span></label>
                                            <input type="text" name="name[]" class="form-control"
                                                placeholder="{{translate('Write a new business zone name')}}"
                                                maxlength="191">
                                        </div>
                                        <div class="form-group col-12 mb-0">
                                            <label class="input-label"
                                                for="exampleFormControlInput1">{{ translate('messages.Display name')}}
                                                ({{ translate('Default') }})
                                                <span class="text-danger">*</span></label>
                                            <input type="text" name="display_name[]" class="form-control"
                                                placeholder="{{translate('Write a new display zone name')}}"
                                                maxlength="191">
                                        </div>
                                        <input type="hidden" name="lang[]" value="default">
                                    </div>
                                    @foreach($language as $lang)
                                        <div class="row g-3 lang_form d-none" id="{{$lang}}-form">
                                            <div class="form-group col-12 mb-0">
                                                <label class="input-label"
                                                    for="exampleFormControlInput1">{{ translate('Business zone name')}}
                                                    ({{strtoupper($lang)}})</label>
                                                <input type="text" name="name[]" class="form-control"
                                                    placeholder="{{translate('Write a new business zone name')}}"
                                                    maxlength="191">
                                            </div>
                                            <div class="form-group col-12 mb-0">
                                                <label class="input-label"
                                                    for="exampleFormControlInput1">{{ translate('messages.Display name')}}
                                                    ({{strtoupper($lang)}})</label>
                                                <input type="text" name="display_name[]" class="form-control"
                                                    placeholder="{{translate('Write a new display zone name')}}"
                                                    maxlength="191">
                                            </div>
                                            <input type="hidden" name="lang[]" value="{{$lang}}">
                                        </div>
                                    @endforeach
                            @endif
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-15">
                            <h5 class="mb-0">{{ translate('Select Area') }}</h5>
                            <p class="fs-12 m-0">
                                {{ translate('To select an area click on map and connect the dots together') }}
                            </p>
                        </div>
                        <div class="form-group mb-3 d-none">
                            <label class="input-label"
                                for="exampleFormControlInput1">{{ translate('Coordinates') }}<span
                                    class="form-label-secondary" data-toggle="tooltip" data-placement="right"
                                    data-original-title="{{translate('messages.Draw your zone on the map')}}">{{translate('messages.Draw your zone on the map')}}</span></label>
                            <textarea type="text" rows="8" name="coordinates" id="coordinates" class="form-control"
                                readonly></textarea>
                        </div>
                        <div class="map-warper map-controler rounded mt-0">
                            <input id="pac-input" class="controls rounded"
                                title="{{translate('Search your location')}}" type="text"
                                placeholder="{{translate('Search')}}" />
                            <div id="map-canvas" class="rounded"></div>
                        </div>
                    </div>
                </div>
                <div class="btn--container mt-3 justify-content-end">
                    <button id="reset_btn" type="reset"
                        class="btn min-w-120 btn--reset"><i class="tio-refresh"></i> {{translate('messages.Reset')}}</button>
                    <button type="submit" class="btn min-w-120 btn--primary"><i class="tio-checkmark-circle-outlined"></i> {{translate('messages.Submit')}}</button>
                </div>
            </form>
        </div>



        <div class="col-12">
            <div class="card" id="zone-list-section">
                <div class="card-header py-2 border-0">
                    <div class="search--button-wrapper">
                        @include('partials._table-head', [
                            'title'    => translate('Zone list'),
                            'subtitle' => translate('Every area you deliver to, the modules it serves and who is trading in it.'),
                            'count'    => $zones->total(),
                            'count_id' => 'itemCount',
                        ])
                        <form class="search-form">
                            <div class="input-group input--group">
                                <input id="datatableSearch_" type="search" name="search" class="form-control"
                                    placeholder="{{translate('Search business zone')}}"
                                    value="{{ request()?->search ?? null }}"
                                    aria-label="{{translate('messages.Search')}}">
                                <button type="submit" class="btn btn--primary"><i class="tio-search"></i></button>
                            </div>
                        </form>
                        <div class="hs-unfold mr-2">
                            <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle min-height-40"
                                href="javascript:;" data-hs-unfold-options='{
                                            "target": "#usersExportDropdown",
                                            "type": "css-animation"
                                        }'>
                                <i class="tio-download-to mr-1"></i> {{ translate('messages.Export') }}
                            </a>
                            <div id="usersExportDropdown"
                                class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">
                                <span class="dropdown-header">{{ translate('messages.Download options') }}</span>
                                <a id="export-excel" class="dropdown-item"
                                    href="{{route('admin.business-settings.zone.export', ['type' => 'excel', request()->getQueryString()])}}">
                                    <img class="avatar avatar-xss avatar-4by3 mr-2"
                                        src="{{ asset('public/assets/admin') }}/svg/components/excel.svg"
                                        alt="Image Description">
                                    Excel
                                </a>
                                <a id="export-csv" class="dropdown-item"
                                    href="{{route('admin.business-settings.zone.export', ['type' => 'csv', request()->getQueryString()])}}">
                                    <img class="avatar avatar-xss avatar-4by3 mr-2"
                                        src="{{ asset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg"
                                        alt="Image Description">
                                    CSV
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                @includeIf('admin-views.zone.partials._table', ['zones' => $zones])
            </div>
        </div>
    </div>
</div>


<div class="modal fade" id="status-warning-modal">
    <div class="modal-dialog status-warning-modal">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true" class="tio-clear"></span>
                </button>
            </div>
            <div class="modal-body pt-0">
                <div class="text-center mb-20">
                    <img src="{{asset('/public/assets/admin/img/zone-status-on.png')}}" alt="" class="mb-20">
                    <h5 class="modal-title">
                        {{translate('By switching the status to "ON", this zone and under all the functionality of this zone will be turned on')}}
                    </h5>
                    <p class="txt">
                        {{translate("In the user app & website all stores & products already assigned under this zone will show to the customers")}}
                    </p>
                </div>
                <div class="btn--container justify-content-center">
                    <button type="submit" class="btn btn--primary min-w-120"
                        data-dismiss="modal"><i class="tio-checkmark-circle-outlined"></i> {{translate('OK')}}</button>
                    <button id="reset_btn" type="reset" class="btn btn--cancel min-w-120"
                        data-dismiss="modal"><i class="tio-clear-circle-outlined"></i> {{translate("Cancel")}}</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="offcanvasOverlay" class="offcanvas-overlay"></div>
<div id="instruction__customBtn2" class="custom-offcanvas d-flex flex-column justify-content-between">
    <div class="offcanvas-inner">
        <div class="custom-offcanvas-header bg--secondary d-flex justify-content-between align-items-center px-3 py-3">
            <h3 class="mb-0 theme-clr-dark">{{ translate('Instructions') }}</h2>
                <button type="button"
                    class="btn-close w-25px h-25px border rounded-circle d-center bg--secondary text-dark offcanvas-close fz-15px p-0"
                    aria-label="Close">&times;</button>
        </div>
        <div class="custom-offcanvas-body p-20">
            <div class="zone-setup-instructions">
                <div class="zone-setup-top">
                    <p>
                        {{ translate('Create & connect dots in a specific area on the map to add a new business zone.') }}
                    </p>
                </div>
                <div class="zone-setup-item">
                    <div class="zone-setup-icon">
                        <i class="tio-hand-draw"></i>
                    </div>
                    <div class="info">
                        {{ translate('Use this \'Hand Tool\' to find your target zone.') }}
                    </div>
                </div>
                <div class="zone-setup-item">
                    <div class="zone-setup-icon">
                        <i class="tio-free-transform"></i>
                    </div>
                    <div class="info">
                        {{ translate('Use this \'shape tool\' to point out the areas and connect the dots.') }} {{ translate('Minimum points') }}: 3
                    </div>
                </div>
                <div class="instructions-image mt-4">
                    <img src="{{asset('public/assets/admin/img/instructions.gif')}}" alt="instructions">
                </div>
            </div>
        </div>
    </div>
    <div class="offcanvas-footer p-3 d-flex align-items-center justify-content-center gap-3">

    </div>
</div>

<div id="offcanvas__connect_module" class="custom-offcanvas d-flex flex-column justify-content-between">
    <div id="connect-module-view" class="h-100"></div>
</div>

{{-- Z3's one dialog, opened from two places: the status toggle (why the zone cannot be
     switched on) and the row's warning mark (why the zone is not working). Same shell, same
     list of shortcuts — only the heading and the lead line differ, and both are resolved
     server-side from the same gaps the toggle guard reads.

     StackFood's shape: the setups that are missing are listed as links, and the only button
     is the way out. That replaces the two action buttons this modal carried, which said the
     same thing twice once the list existed. --}}
<div class="modal fade" id="zone-readiness-modal">
    <div class="modal-dialog status-warning-modal modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true" class="tio-clear"></span>
                </button>
            </div>
            <div class="modal-body pt-0 pb-5">
                <div class="max-349 mx-auto">
                    <div class="text-center">
                        {{-- A warning, not an illustration: the dialog only ever opens because
                             something is missing, from either trigger. --}}
                        <img class="mb-20" src="{{ asset('public/assets/admin/img/modal-error.png') }}" alt=""
                            width="70" height="70">
                        <h5 class="modal-title mb-3" id="zone-readiness-title"></h5>
                    </div>
                    <div class="text-center">
                        {{-- Replaced on open with the line for whichever setups are actually
                             missing; this is the both-missing wording the design draws. --}}
                        <p id="zone-readiness-text">
                            {{ translate('After creating a new zone, you must configure the delivery charge rules and ETA for that zone. Until both are configured, the zone will not be available to customers, vendors, or deliverymen.') }}
                        </p>
                    </div>
                    <ul class="mb-20 pl-3" id="zone-readiness-list"></ul>
                    {{-- The rule behind the dialog, stated in full. The lines above name only
                         what THIS zone is short of; this says what the requirement actually is.
                         Rewritten for S19 — it used to demand both setups for EVERY connected
                         module, which is no longer what the toggle enforces. --}}
                    <div class="admin-alert mb-20 text-left">
                        <span class="admin-alert__icon">i</span>
                        <span>{{ translate('One module needs both a delivery charge rule and an ETA configuration before this zone can be switched on. The others stay unavailable in it until each gets its own.') }}</span>
                    </div>
                    <div class="btn--container justify-content-center">
                        <button type="button" class="btn btn--primary min-w-120px"
                            data-dismiss="modal">{{ translate('messages.Okay') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- S19's confirm, the counterpart to the blocking dialog above: this zone CAN be switched on,
     and doing so leaves some of its modules unavailable. Same shell as #zone-readiness-modal on
     purpose — one dialog design for one subject — but it is not a refusal, so it carries a
     working confirm rather than a single way out. --}}
<div class="modal fade" id="zone-partial-modal">
    <div class="modal-dialog status-warning-modal modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true" class="tio-clear"></span>
                </button>
            </div>
            <div class="modal-body pt-0 pb-5">
                <div class="max-349 mx-auto">
                    <div class="text-center">
                        <img class="mb-20" src="{{ asset('public/assets/admin/img/modal-error.png') }}" alt=""
                            width="70" height="70">
                        <h5 class="modal-title mb-3" id="zone-partial-title"></h5>
                    </div>
                    <div class="text-center">
                        <p id="zone-partial-text"></p>
                    </div>
                    {{-- The modules by name. This is the whole point of the dialog: "some modules"
                         is not something an admin can act on. --}}
                    <div class="admin-alert mb-20 text-left">
                        <span class="admin-alert__icon">i</span>
                        <span id="zone-partial-modules"></span>
                    </div>
                    <div class="btn--container justify-content-center">
                        <button type="button" class="btn btn--reset min-w-120px"
                            data-dismiss="modal">{{ translate('messages.Cancel') }}</button>
                        <button type="button" id="zone-partial-confirm"
                            class="btn btn--primary min-w-120px">{{ translate('Turn on anyway') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- The prompt after Connect Module is saved (TC_58) — a proactive next-step, not a refusal
     like the two dialogs above, so it gets its own shell: the illustration + two-button design
     the figma draws, shown once right after the drawer closes rather than reached by clicking a
     warning. Populated from the connect-module AJAX response's `setupGuide` (null once the zone
     is fully set up — nothing to prompt then). --}}
<div class="modal fade" id="zone-setup-guide-modal">
    <div class="modal-dialog status-warning-modal modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true" class="tio-clear"></span>
                </button>
            </div>
            <div class="modal-body pt-0 pb-5">
                <div class="max-349 mx-auto">
                    <div class="text-center">
                        {{-- Placeholder: the figma's illustration (a hand offering a parcel with
                             a coin badge) is not among the assets in public/assets/admin/img —
                             swap the src for the exported figma asset once it's dropped in. --}}
                        <svg class="mb-20" width="70" height="70" viewBox="0 0 70 70" fill="none"
                            xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <rect x="14" y="24" width="42" height="32" rx="4" fill="#E9F5F1" />
                            <rect x="14" y="24" width="42" height="12" fill="#0B8457" fill-opacity="0.15" />
                            <path d="M14 30H56" stroke="#0B8457" stroke-width="2" />
                            <path d="M35 24V56" stroke="#0B8457" stroke-width="2" />
                            <circle cx="49" cy="49" r="12" fill="#FFB020" />
                            <path d="M49 43V55M45 46.5H51.5C52.6 46.5 53.5 47.4 53.5 48.5C53.5 49.6 52.6 50.5 51.5 50.5H46.5C45.4 50.5 44.5 51.4 44.5 52.5C44.5 53.6 45.4 54.5 46.5 54.5H53"
                                stroke="#fff" stroke-width="1.6" stroke-linecap="round" />
                        </svg>
                        <h5 class="modal-title mb-3" id="zone-setup-guide-title"></h5>
                    </div>
                    <div class="text-center">
                        <p id="zone-setup-guide-text"></p>
                    </div>
                    <div class="btn--container justify-content-center" id="zone-setup-guide-actions"></div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('script_2')
<script async
    src="https://maps.googleapis.com/maps/api/js?key={{\App\CentralLogics\Helpers::get_business_settings('map_api_key', false)}}&libraries=places,marker&v=quarterly&loading=async&callback=initialize"></script>
@include('admin-views.zone.partials._connect-module-scripts')
<script>
    "use strict";

    @if (session('zoneSetupGuide'))
        // "Mark As Default" (ZoneController::defaultStatus()) is a plain GET form submit, not
        // AJAX, so the guide can't be shown inline the way the Connect Module drawer's success
        // handler does it — it's flashed instead and picked up here on the reloaded index page.
        // showZoneSetupGuide() itself is defined in _connect-module-scripts.blade.php, included
        // just above.
        showZoneSetupGuide(@json(session('zoneSetupGuide')));
    @endif

    @if (request()->filled('search'))
        // The search form has no AJAX handler (platform-wide pattern, resources/views/admin-views/*
        // list screens) — submitting it is a plain GET to this same page, which the browser lands
        // at the very top of by default. That buries the results the admin just searched for below
        // the map/instructions panel. Landing on the list section instead, only when a search
        // actually ran.
        document.addEventListener('DOMContentLoaded', function () {
            document.getElementById('zone-list-section')?.scrollIntoView({ block: 'start' });
        });
    @endif

    // Delegated, not bound to the rows present at load: adding a zone replaces
    // #set-rows wholesale with the partial the add endpoint renders, and a direct
    // binding would leave every Delete in the repainted table doing nothing.
    $(document).on('click', '.status_form_alert', function (event) {
        let id = $(this).data('id');
        let title = $(this).data('title');
        let message = $(this).data('message');
        status_form_alert(id, title, message, event)
    })

    function status_form_alert(id, title, message, e) {
        e.preventDefault();
        Swal.fire({
            title: title,
            text: message,
            type: 'warning',
            showCancelButton: true,
            cancelButtonColor: 'default',
            confirmButtonColor: '#FC6A57',
            cancelButtonText: '{{ translate('messages.No') }}',
            confirmButtonText: '{{ translate('messages.Yes') }}',
            reverseButtons: true
        }).then((result) => {
            if (result.value) {
                $('#' + id).submit()
            }
        })
    }
    auto_grow();
    function auto_grow() {
        let element = document.getElementById("coordinates");
        element.style.height = "5px";
        element.style.height = (element.scrollHeight) + "px";
    }


    $(document).on('ready', function () {
        $.HSCore.components.HSDatatables.init($('#columnSearchDatatable'));

        $('.js-select2-custom').each(function () {
            $.HSCore.components.HSSelect2.init($(this));
        });

        $("#zone_form").on('keydown', function (e) {
            if (e.keyCode === 13) {
                e.preventDefault();
            }
        })
    });

    let map;
    let drawingPolyline = null;
    let drawingPolygon = null;
    let polygonClosed = false;
    let lastpolygon = null;
    let polygons = [];
    let drawingMode = true;
    let vertexMarkers = [];
    const MIN_VERTICES = 3;

    // translateY(50%) compensates for AdvancedMarkerElement's bottom-center
    // anchor so the circle's center sits on the LatLng.
    function vertexElement(highlighted) {
        const size = highlighted ? 20 : 12;
        const div = document.createElement('div');
        div.style.cssText =
            'width:' + size + 'px;' +
            'height:' + size + 'px;' +
            'border-radius:50%;' +
            'background:' + (highlighted ? '#00b35c' : '#FF0000') + ';' +
            'border:2px solid #fff;' +
            'box-shadow:0 1px 3px rgba(0,0,0,0.3);' +
            'cursor:' + (highlighted ? 'pointer' : 'default') + ';' +
            'transform:translateY(50%);';
        return div;
    }

    function currentPath() {
        if (polygonClosed && drawingPolygon) return drawingPolygon.getPath().getArray();
        if (drawingPolyline) return drawingPolyline.getPath().getArray();
        return [];
    }

    function syncVertexMarkers() {
        // AdvancedMarkerElement unmounts via `.map = null`, not setMap().
        vertexMarkers.forEach(function (m) { m.map = null; });
        vertexMarkers = [];
        // After close, the editable Polygon draws its own vertex handles.
        if (polygonClosed) return;
        const { AdvancedMarkerElement } = google.maps.marker;
        const path = currentPath();
        path.forEach(function (latLng, idx) {
            const isFirst = idx === 0;
            const canClose = isFirst && path.length >= MIN_VERTICES;
            const marker = new AdvancedMarkerElement({
                position: latLng,
                map: map,
                content: vertexElement(canClose),
                gmpClickable: canClose,
                title: canClose ? "{{ translate('Click to close polygon') }}" : "",
                zIndex: 9999,
            });
            // AdvancedMarkerElement fires 'gmp-click', not 'click'.
            if (canClose) marker.addListener("gmp-click", closePolygon);
            vertexMarkers.push(marker);
        });
    }

    function clearDrawing() {
        if (drawingPolygon) {
            drawingPolygon.setMap(null);
            drawingPolygon = null;
        }
        if (drawingPolyline) {
            drawingPolyline.getPath().clear();
            drawingPolyline.setMap(map);       // re-show in case close hid it
            lastpolygon = drawingPolyline;
        }
        polygonClosed = false;
        vertexMarkers.forEach(function (m) { m.map = null; });
        vertexMarkers = [];
        $('#coordinates').val('');
        auto_grow();
    }

    function updateCoordinates() {
        const path = currentPath();
        $('#coordinates').val(path.length ? path.toString() : '');
        auto_grow();
        syncVertexMarkers();
    }

    function closePolygon() {
        if (!drawingPolyline) return;
        const path = drawingPolyline.getPath().getArray();
        if (path.length < MIN_VERTICES) return;

        drawingPolyline.setMap(null);
        drawingPolygon = new google.maps.Polygon({
            map: map,
            paths: path,
            editable: true,
            clickable: false,
            strokeColor: "#FF0000",
            strokeOpacity: 0.8,
            strokeWeight: 2,
            fillColor: "#FF0000",
            fillOpacity: 0.1,
        });
        polygonClosed = true;
        lastpolygon = drawingPolygon;

        const polyPath = drawingPolygon.getPath();
        google.maps.event.addListener(polyPath, "set_at", updateCoordinates);
        google.maps.event.addListener(polyPath, "insert_at", updateCoordinates);
        google.maps.event.addListener(polyPath, "remove_at", updateCoordinates);

        // Polygon's built-in editable handles replace our custom dots.
        vertexMarkers.forEach(function (m) { m.map = null; });
        vertexMarkers = [];
        updateCoordinates();
    }

    let handToolEl = null;
    let shapeToolEl = null;

    function setDrawingMode(drawing) {
        drawingMode = drawing;
        if (map) {
            map.setOptions({ draggableCursor: drawing ? "crosshair" : null });
        }
        if (shapeToolEl) {
            shapeToolEl.style.backgroundColor = drawing ? "#e7f0ff" : "#fff";
            shapeToolEl.style.color = drawing ? "#050df2" : "#444";
        }
        if (handToolEl) {
            handToolEl.style.backgroundColor = drawing ? "#fff" : "#e7f0ff";
            handToolEl.style.color = drawing ? "#444" : "#050df2";
        }
    }

    function buildDrawingControl() {
        const wrapper = document.createElement("div");
        wrapper.style.cssText = "margin:10px;display:flex;border-radius:4px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.3);background:#fff;font-family:Roboto,Arial,sans-serif;";

        handToolEl = document.createElement("div");
        handToolEl.title = "Hand Tool — pan the map";
        handToolEl.style.cssText = "cursor:pointer;display:flex;align-items:center;justify-content:center;width:36px;height:36px;font-size:18px;color:#444;";
        handToolEl.innerHTML = `<i class="tio-hand-draw"></i>`;

        shapeToolEl = document.createElement("div");
        shapeToolEl.title = "Shape Tool — click the map to connect the dots";
        shapeToolEl.style.cssText = "cursor:pointer;display:flex;align-items:center;justify-content:center;width:36px;height:36px;font-size:18px;color:#444;border-left:1px solid #e6e6e6;";
        shapeToolEl.innerHTML = `<i class="tio-free-transform"></i>`;

        handToolEl.addEventListener("click", function () { setDrawingMode(false); });
        shapeToolEl.addEventListener("click", function () { setDrawingMode(true); });

        wrapper.appendChild(handToolEl);
        wrapper.appendChild(shapeToolEl);
        return wrapper;
    }

    function resetMap(controlDiv) {
        const controlUI = document.createElement("div");
        controlUI.style.backgroundColor = "#fff";
        controlUI.style.border = "2px solid #fff";
        controlUI.style.borderRadius = "3px";
        controlUI.style.boxShadow = "0 2px 6px rgba(0,0,0,.3)";
        controlUI.style.cursor = "pointer";
        controlUI.style.marginTop = "8px";
        controlUI.style.marginBottom = "22px";
        controlUI.style.textAlign = "center";
        controlUI.title = "Reset map";
        controlDiv.appendChild(controlUI);
        const controlText = document.createElement("div");
        controlText.style.color = "rgb(25,25,25)";
        controlText.style.fontFamily = "Roboto,Arial,sans-serif";
        controlText.style.fontSize = "10px";
        controlText.style.lineHeight = "16px";
        controlText.style.paddingLeft = "2px";
        controlText.style.paddingRight = "2px";
        controlText.innerHTML = "X";
        controlUI.appendChild(controlText);
        controlUI.addEventListener("click", () => {
            clearDrawing();
        });
    }

    function initialize() {
        @php($default_location = \App\Models\BusinessSetting::where('key', 'default_location')->first())
        @php($default_location = $default_location->value ? json_decode($default_location->value, true) : 0)
        let myLatlng = { lat: {{$default_location ? $default_location['lat'] : '23.757989'}}, lng: {{$default_location ? $default_location['lng'] : '90.360587'}} };
        const mapId = "{{ \App\CentralLogics\Helpers::get_business_settings('map_api_key', false) }}"

        let myOptions = {
            zoom: 13,
            center: myLatlng,
            mapTypeId: google.maps.MapTypeId.ROADMAP,
            mapId: mapId

        }
        map = new google.maps.Map(document.getElementById("map-canvas"), myOptions);
        // Polyline (not Polygon) during drawing — Polygon would auto-render
        // the closing edge + fill once it has 3 vertices, pre-empting the
        // user's "click first dot to close" gesture.
        drawingPolyline = new google.maps.Polyline({
            map: map,
            editable: false,
            clickable: false,
            strokeColor: "#FF0000",
            strokeOpacity: 0.8,
            strokeWeight: 2,
        });
        drawingPolyline.setPath([]);
        const polylinePath = drawingPolyline.getPath();
        lastpolygon = drawingPolyline;

        google.maps.event.addListener(polylinePath, "set_at", updateCoordinates);
        google.maps.event.addListener(polylinePath, "insert_at", updateCoordinates);
        google.maps.event.addListener(polylinePath, "remove_at", updateCoordinates);

        google.maps.event.addListener(map, "click", function (event) {
            if (!drawingMode) return;
            if (polygonClosed) return;
            polylinePath.push(event.latLng);
            updateCoordinates();
        });

        map.controls[google.maps.ControlPosition.LEFT_TOP].push(buildDrawingControl());
        setDrawingMode(true);

        const resetDiv = document.createElement("div");
        resetMap(resetDiv);
        map.controls[google.maps.ControlPosition.RIGHT_TOP].push(resetDiv);

        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const pos = {
                        lat: position.coords.latitude,
                        lng: position.coords.longitude,
                    };
                    map.setCenter(pos);
                });
        }


        const input = document.getElementById("pac-input");
        const searchBox = new google.maps.places.SearchBox(input);
        map.controls[google.maps.ControlPosition.TOP_CENTER].push(input);
        map.addListener("bounds_changed", () => {
            searchBox.setBounds(map.getBounds());
        });
        let markers = [];
        searchBox.addListener("places_changed", () => {
            const places = searchBox.getPlaces();

            if (places.length == 0) {
                return;
            }
            markers.forEach((marker) => {
                marker.map = null;
            });
            markers = [];
            const bounds = new google.maps.LatLngBounds();
            places.forEach((place) => {
                if (!place.geometry || !place.geometry.location) {
                    return;
                }

                const { AdvancedMarkerElement } = google.maps.marker;

                markers.push(
                    new AdvancedMarkerElement({
                        map,
                        title: place.name,
                        position: place.geometry.location,
                    })
                );

                if (place.geometry.viewport) {
                    bounds.union(place.geometry.viewport);
                } else {
                    bounds.extend(place.geometry.location);
                }
            });
            map.fitBounds(bounds);
        });

        set_all_zones();
    }

    function set_all_zones() {
        $.get({
            url: '{{route('admin.zone.zoneCoordinates')}}',
            dataType: 'json',
            success: function (data) {
                for (let i = 0; i < data.length; i++) {
                    polygons.push(new google.maps.Polygon({
                        paths: data[i],
                        strokeColor: "#FF0000",
                        strokeOpacity: 0.8,
                        strokeWeight: 2,
                        fillColor: "#FF0000",
                        fillOpacity: 0.1,
                        clickable: false,
                    }));
                    polygons[i].setMap(map);
                }

            },
        });
    }

    $('#zone_form').on('submit', function (e) {
        if (!polygonClosed) {
            e.preventDefault();
            toastr.warning("{{ translate('Connect the last dot to the first dot to close the polygon before saving') }}");
            return false;
        }
        let formData = new FormData(this);
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });
        $.post({
            url: '{{route('admin.business-settings.zone.store')}}',
            data: formData,
            cache: false,
            contentType: false,
            processData: false,
            beforeSend: function () {
                $('#loading').show();
            },
            success: function (data) {
                if (data.errors) {
                    $.each(data.errors, function (index, value) {
                        toastr.error(value.message);
                    });
                }
                else {
                    $('.tab-content').find('input:text').val('');
                    $('input[name="name"]').val(null);
                    clearDrawing();
                    toastr.success("{{ translate('Added successfully') }}", {
                        CloseButton: true,
                        ProgressBar: true
                    });
                    $('#set-rows').html(data.view);
                    $('#itemCount').html(data.total);
                    // A zone shows nothing to customers until modules are connected, so the
                    // panel that connects them opens straight away on the zone just created.
                    // This replaced an interstitial that only pointed at the old full page.
                    openConnectModuleDrawer(connectModuleUrl(data.id));
                }
            },
            complete: function () {
                $('#loading').hide();
            },
        });
    });

    $('#reset_btn').click(function () {
        $('.tab-content').find('input:text').val('');
        clearDrawing();
    })
</script>
@endpush
