@extends('layouts.admin.app')

@section('title',translate('Withdraw request'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center py-2">
                <div class="col-sm mb-2 mb-sm-0">
                    <div class="d-flex align-items-center">
                        <img src="{{asset('/public/assets/admin/img/new-img/users.svg')}}" alt="img">
                        <div class="w-0 flex-grow pl-3">
                            <h1 class="page-header-title mb-0">{{ translate('Transaction overview') }}</h1>
                            <p class="page-header-desc">{{ translate('Money waiting to go out, money already paid and what is holding the rest up.') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
