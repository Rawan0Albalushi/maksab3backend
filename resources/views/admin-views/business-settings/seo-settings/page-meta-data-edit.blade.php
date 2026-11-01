@extends('layouts.admin.app')

@section('title',translate('SEO setup'))

@section('content')
<div class="content container-fluid">
    <div class="page-header">
        <h1 class="page-header-title text-break">
            <span class="page-header-icon">
                <img src="{{asset('public/assets/admin/img/outline/seo-setting.svg')}}" class="w--26" alt="">
            </span>
            <span>{{ translate('Manage page SEO') }}</span>
        </h1>
        <p class="page-header-desc">{{ translate('The title, description and preview image search engines show for this page.') }}</p> 
    </div>

    <form action="{{ route('admin.business-settings.seo-settings.pageMetaDataUpdate') }}" method="POST"
                                      enctype="multipart/form-data">
                                    @csrf
                                    <input type="hidden" name="page_name" value="{{ request()->page_name }}">
                                    @include('admin-views.business-settings.landing-page-settings.partial._meta_data',['submit'=>true])
    </form>
    
    

</div>
@endsection



