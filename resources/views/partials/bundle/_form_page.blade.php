<div class="content container-fluid">
    <div class="page-header">
        <h1 class="page-header-title m-0">
            <span class="page-header-icon">
                <img src="{{ asset('public/assets/admin/img/items-store.png') }}" alt="">
            </span>
            <span>{{ $heading }}</span>
        </h1>
    </div>

    <form action="{{ $action }}" method="post" enctype="multipart/form-data" id="bundle-form">
        @csrf
        @include('partials.bundle._form')

        <div class="btn--container justify-content-end my-4">
            <button type="reset" class="btn min-w-120 btn--reset">{{ translate('messages.Reset') }}</button>
            <button type="submit" class="btn min-w-120 btn--primary">{{ $submitLabel }}</button>
        </div>
    </form>
</div>

@include('partials.promotion._item_options_modal')
@include('partials.bundle._service_options_modal')
