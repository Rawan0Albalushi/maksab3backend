@extends('layouts.admin.app')

@section('title',translate('Update BOGO offer'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <i class="tio-gift"></i>
                <span>{{translate('Update BOGO offer')}}</span>
            </h1>
            <p class="page-header-desc">{{ translate('Change which items this buy-one-get-one offer covers, or how long it runs.') }}</p>
        </div>

        <form action="{{route('admin.bogo-offer.update',$offer->id)}}" method="post" enctype="multipart/form-data" id="bogo-offer-form">
            @csrf
            @include('admin-views.promotions.bogo-offer.partials._form_body', [
                'offer' => $offer,
                'quantity_locked' => $quantity_locked,
            ])

            <div class="btn--container justify-content-end my-4">
                <button type="reset" class="btn min-w-120 btn--reset"><i class="tio-refresh"></i> {{translate('Reset')}}</button>
                <button type="submit" class="btn min-w-120 btn--primary"><i class="tio-save"></i> {{translate('Update')}}</button>
            </div>
        </form>
    </div>
@endsection

@push('script_2')
    <script>
        const formUrl = '{{ route('admin.bogo-offer.update', $offer->id) }}';
        const redirectUrl = '{{ route('admin.bogo-offer.list') }}';
        const successMessage = '{{ translate('Updated successfully') }}';
    </script>
    @include('admin-views.promotions.bogo-offer.partials._form_scripts')
@endpush
