@extends('layouts.admin.app')

@section('title',translate('Banner'))

@section('content')
<div class="content container-fluid">
    <div class="page-header">
        <h1 class="page-header-title">
            <span class="page-header-icon">
                <img src="{{asset('public/assets/admin/img/outline/3rd-party.svg')}}" class="w--26" alt="">
            </span>
            <span>
                {{translate('messages.Other Promotional Content Setup')}}
            </span>
        </h1>
        <p class="page-header-desc">{{ translate('Change this promotional block shown in the parcel module.') }}</p>
    </div>
    <div class="mb-20 mt-2">
        <div class="js-nav-scroller hs-nav-scroller-horizontal">
            @include('admin-views.other-banners.partial.parcel-links')
        </div>
    </div>
    <div class="tab-content">
        <div class="tab-pane fade show active">
            <div class="row g-3">
                <div class="col-lg-12 mb-3 mb-lg-2">
                    <div class="card h-100">
                        <form action="{{ route('admin.promotional-banner.update',[$banner['id']]) }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <input type="text" name="key" value="promotional_banner"  hidden>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-12 d-flex justify-content-between">
                                            <span class="d-flex g-1">
                                                <img src="{{asset('public/assets/admin/img/other-banner.png')}}" class="h-85" alt="">
                                                <h3 class="form-label d-block mb-2">
                                                    {{translate('messages.Promotional Banner Edit')}}
                                                </h3>
                                            </span>
                                        </div>
                                        <div class="col-12">
                                            <label class="__upload-img aspect-2-1 m-auto d-block">
                                                <div class="img">
                                                    <img class="onerror-image"

                                                        src="{{ $banner->value_full_url ?? asset('/public/assets/admin/img/upload-placeholder.png')}}" data-onerror-image="{{asset('/public/assets/admin/img/upload-placeholder.png')}}" alt="">
                                                </div>
                                                    <input type="file" name="image"  hidden>
                                            </label>
                                            <div class="text-center mt-5">
                                                <h3 class="form-label d-block mt-2">
                                                Banner Image Ratio 2:1
                                            </h3>
                                            <p>{{'JPG, PNG, JPEG' . ' image, max ' . 2 . ' MB'}}</p>

                                            </div>
                                        </div>
                                    </div>
                                    <div class="btn--container justify-content-end mt-20">
                                        <button type="submit" class="btn btn--primary mb-2"><i class="tio-save"></i> {{translate('Update')}}</button>
                                    </div>
                                </div>
                            </form>
                        </div>

                </div>
            </div>
        </div>
    </div>
</div>
@endsection
@push('script_2')
    <script src="{{asset('public/assets/admin')}}/js/view-pages/other-banners.js"></script>
        <script>
            $('#reset_btn').click(function(){
                $('#viewer').attr('src','{{asset('/public/assets/admin/img/upload-placeholder.png')}}');
            })
        </script>
@endpush

