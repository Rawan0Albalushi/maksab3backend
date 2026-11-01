@extends('layouts.admin.app')
@section('title',translate('Add new role'))

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
                <span>{{ translate('Add new role') }}</span>
            </h1>
            <p class="page-header-desc">{{ translate('A role decides which parts of the panel an employee can open.') }}</p>
        </div>
        <a href="{{route('admin.users.custom-role.list')}}" class="btn btn--reset">
            <i class="tio-back-ui"></i> {{ translate('Role list') }}
        </a>
    </div>

    <form action="{{route('admin.users.custom-role.store')}}" method="post" class="role-form">
        @csrf
        <div class="rp-card">
            <div class="rp-card__head">
                <h2 class="rp-card__title">{{ translate('messages.Role form') }}</h2>
                <p class="rp-card__subtitle">{{ translate('messages.Create a role and assign its module and usage permissions.') }}</p>
            </div>
            <div class="rp-card__body">
                @if ($language)
                    <ul class="nav nav-tabs mb-4">
                        <li class="nav-item">
                            <a class="nav-link lang_link active" href="#" id="default-link">{{translate('Default')}}</a>
                        </li>
                        @foreach ($language as $lang)
                            <li class="nav-item">
                                <a class="nav-link lang_link" href="#"
                                    id="{{ $lang }}-link">{{ \App\CentralLogics\Helpers::get_language_name($lang) . '(' . strtoupper($lang) . ')' }}</a>
                            </li>
                        @endforeach
                    </ul>
                    <div class="form-group mb-0 lang_form" id="default-form">
                        <label class="input-label" for="default_title">{{translate('Role name')}} ({{ translate('Default') }})
                            <span class="form-label-secondary text-danger" data-toggle="tooltip" data-placement="right"
                                data-original-title="{{ translate('messages.Required.')}}"> *</span>
                        </label>
                        <input type="text" id="default_title" name="name[]" class="form-control"
                            placeholder="{{translate('Role name example')}}" maxlength="191">
                    </div>
                    <input type="hidden" name="lang[]" value="default">
                    @foreach($language as $lang)
                        <div class="form-group mb-0 mt-3 d-none lang_form" id="{{$lang}}-form">
                            <label class="input-label" for="{{$lang}}_title">{{translate('Role name')}} ({{strtoupper($lang)}})</label>
                            <input type="text" id="{{$lang}}_title" name="name[]" class="form-control"
                                placeholder="{{translate('Role name example')}}" maxlength="191">
                        </div>
                        <input type="hidden" name="lang[]" value="{{$lang}}">
                    @endforeach
                @else
                    <div class="form-group mb-0">
                        <label class="input-label" for="default_title">{{translate('Role name')}}
                            <span class="form-label-secondary text-danger" data-toggle="tooltip" data-placement="right"
                                data-original-title="{{ translate('messages.Required.')}}"> *</span>
                        </label>
                        <input type="text" id="default_title" name="name" class="form-control"
                            placeholder="{{translate('Role name example')}}" value="{{old('name')}}" maxlength="191">
                    </div>
                    <input type="hidden" name="lang[]" value="default">
                @endif
            </div>
        </div>

        @include('admin-views.custom-role.partials._permission_form', [
            'permissionGroups' => $permissionGroups,
            'selectedModules' => [],
        ])

        <div class="rp-actions">
            <p class="rp-actions__note">{{ translate('An employee with no permission can still sign in, but every section stays locked.') }}</p>
            <div class="rp-actions__buttons">
                <button type="reset" class="btn btn--reset"><i class="tio-refresh"></i> {{translate('messages.Reset')}}</button>
                <button type="submit" class="btn btn--primary"><i class="tio-checkmark-circle-outlined"></i> {{translate('messages.Submit')}}</button>
            </div>
        </div>
    </form>

</div>
@endsection

@push('script_2')
    <script src="{{asset('public/assets/admin/js/view-pages/custom-role-index.js')}}"></script>
@endpush
