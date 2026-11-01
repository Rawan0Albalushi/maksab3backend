@extends('layouts.admin.app')
@section('title',translate('Role list'))

@push('css_or_js')
    <link rel="stylesheet"
        href="{{ asset('public/assets/admin/css/view-pages/custom-role.css') }}?v={{ @filemtime(public_path('public/assets/admin/css/view-pages/custom-role.css')) }}">
@endpush

@section('content')
<div class="content container-fluid">

    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/role.png')}}" class="w--26" alt="">
                </span>
                <span>
                    {{ translate('messages.Employee role') }}
                    <span class="badge badge-soft-dark ml-2" id="itemCount">{{ $roles->total() }}</span>
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('Every role your employees can hold, what each one reaches and how many people use it.') }}</p>
        </div>
        <a href="{{route('admin.users.custom-role.create')}}" class="btn btn--primary">
            <i class="tio-add-circle"></i> {{ translate('Add new role') }}
        </a>
    </div>

    <div class="card">
        <div class="card-header border-0">
            <div class="search--button-wrapper">
                @include('partials._table-head', [
                    'subtitle' => translate('messages.Employee roles and the sections each one can reach.'),
                    'count'    => null,
                ])
                <form class="search-form min--200">
                    <div class="input-group input--group">
                        <input value="{{request()?->search ?? ''}}" type="search" name="search" class="form-control"
                            placeholder="{{translate('messages.Search role')}}" aria-label="{{translate('messages.Search')}}">
                        <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                    </div>
                </form>
                @if(request()->input('search'))
                    <a href="{{route('admin.users.custom-role.list')}}" class="btn btn--primary ml-2"><i class="tio-refresh"></i> {{translate('messages.Reset')}}</a>
                @endif
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive datatable-custom">
                <table class="table table-hover table-borderless table-thead-bordered table-align-middle card-table">
                    <thead class="thead-light">
                        <tr>
                            <th class="border-0 w-50px">{{translate('messages.SL')}}</th>
                            <th class="border-0">{{translate('Role name')}}</th>
                            <th class="border-0">{{translate('messages.Permission')}}</th>
                            <th class="border-0 col--numeric">{{translate('Employee')}}</th>
                            <th class="border-0">{{translate('messages.Created at')}}</th>
                            <th class="border-0 text-center">{{translate('messages.Action')}}</th>
                        </tr>
                    </thead>
                    <tbody id="set-rows">
                        @include('admin-views.custom-role.partials._table')
                    </tbody>
                </table>
            </div>
            @if(count($roles) !== 0)
            <hr>
            @endif
            <div class="page-area">
                {!! $roles->withQueryString()->links() !!}
            </div>
            @if(count($roles) === 0)
            <div class="empty--data">
                <img src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="public">
                <h5>{{translate('No data found')}}</h5>
            </div>
            @endif
        </div>
    </div>

</div>

<div id="offcanvas__role_table" class="custom-offcanvas d-flex flex-column justify-content-between">
    <div>
        <div id="data-view" class="h-100">  </div>
    </div>
</div>
<div id="offcanvasOverlay" class="offcanvas-overlay"></div>
@endsection

@push('script_2')
    <script>
        $(document).on('click', '.data-info-show', function() {
            let id = $(this).data('id');
            let url = $(this).data('url');
            $('#content-disable').addClass('disabled');
            fetch_data(id, url)
        })

        function fetch_data(id, url) {
            $.ajax({
                url: url,
                type: "get",
                beforeSend: function() {
                    $('#data-view').empty();
                    $('#loading').show();
                },
                success: function(data) {
                    $("#data-view").append(data.view);
                    bindOffcanvasClose();
                },
                complete: function() {
                    $('#loading').hide()
                }
            })
        }

        function bindOffcanvasClose() {
            $('.offcanvas-close, #offcanvasOverlay').on('click', function () {
                $('.custom-offcanvas').removeClass('open');
                $('#offcanvasOverlay').removeClass('show');
                $('#content-disable').removeClass('disabled');
            });
        }
    </script>
@endpush
