@extends('layouts.admin.app')

@section('title',translate('Create happy hour'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('admin-views.promotions.happy-hour.partials._schedule_styles')
@endpush

@section('content')
    <div class="content container-fluid hh-form">
        <div class="page-header">
            <h1 class="page-header-title">
                <i class="tio-time"></i>
                <span>{{translate('Create happy hour')}}</span>
            </h1>
            <p class="page-header-desc">{{ translate('Discount a slow part of the day to bring orders forward into it.') }}</p>
        </div>

        <form action="{{route('admin.happy-hour.store')}}" method="post" enctype="multipart/form-data" id="happy-hour-form">
            @csrf
            @include('admin-views.promotions.happy-hour.partials._form_body', ['happyHour' => null])

            <div class="btn--container justify-content-end my-4">
                <button type="reset" class="btn min-w-120 btn--reset">
                    <i class="tio-refresh"></i> {{translate('Reset')}}
                </button>
                <button type="submit" class="btn min-w-120 btn--primary">
                    <i class="tio-checkmark-circle-outlined"></i> {{translate('Submit')}}
                </button>
            </div>
        </form>
    </div>

    @include('admin-views.promotions.happy-hour.partials._schedule_modals')
@endsection

@push('script_2')
    <script>const initialState = {};</script>
    @include('admin-views.promotions.happy-hour.partials._schedule_scripts')
    @include('admin-views.promotions.happy-hour.partials._overlap_alert')
    <script>
        "use strict";
        $('#happy-hour-form').on('submit', function (e) {
            e.preventDefault();
            syncDateRange();

            // Held down until the answer comes back: without it a double click sent two
            // identical creates, and both landed.
            const $button = $(this).find('button[type="submit"]');
            $button.prop('disabled', true);
            const release = () => $button.prop('disabled', false);

            let formData = new FormData(this);
            $.ajaxSetup({headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')}});
            $.post({
                url: '{{route('admin.happy-hour.store')}}',
                data: formData,
                cache: false,
                contentType: false,
                processData: false,
                success: function (data) {
                    if (data.errors) {
                        release();
                        if (handleScheduleConflict(data.errors)) return;
                        data.errors.forEach(e => toastr.error(e.message, {CloseButton: true, ProgressBar: true}));
                    } else {
                        toastr.success('{{ translate('Added successfully') }}', {CloseButton: true, ProgressBar: true});
                        setTimeout(() => location.href = '{{route('admin.happy-hour.list')}}', 2000);
                    }
                },
                error: function (xhr) {
                    release();
                    const errors = xhr.responseJSON?.errors || [];

                    // An overlap comes back 409 and gets the modal; everything else toasts.
                    if (handleScheduleConflict(errors)) return;

                    errors.length
                        ? errors.forEach(e => toastr.error(e.message))
                        : toastr.error('{{ translate('messages.Something went wrong') }}');
                }
            });
        });
    </script>
@endpush
