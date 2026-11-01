@extends('layouts.admin.app')

@section('title',translate('Create BOGO offer'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <i class="tio-gift"></i>
                <span>{{translate('Create BOGO offer')}}</span>
            </h1>
            <p class="page-header-desc">{{ translate('Give customers a second item free when they buy the first.') }}</p>
        </div>

        <form action="{{route('admin.bogo-offer.store')}}" method="post" enctype="multipart/form-data" id="bogo-offer-form">
            @csrf
            @include('admin-views.promotions.bogo-offer.partials._form_body', ['offer' => null])

            <div class="btn--container justify-content-end my-4">
                <button type="reset" class="btn min-w-120 btn--reset"><i class="tio-refresh"></i> {{translate('Reset')}}</button>
                <button type="submit" class="btn min-w-120 btn--primary"><i class="tio-save"></i> {{translate('Save')}}</button>
            </div>
        </form>
    </div>
@endsection

@push('script_2')
    <script>
        const formUrl = '{{ route('admin.bogo-offer.store') }}';
        const redirectUrl = '{{ route('admin.bogo-offer.list') }}';
        const successMessage = '{{ translate('Added successfully') }}';
    </script>
    @include('admin-views.promotions.bogo-offer.partials._form_scripts')
@endpush
