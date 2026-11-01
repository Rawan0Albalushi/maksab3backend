<div class="content container-fluid">
    <div class="card" style="max-width: 465px">
        @include('partials.bundle._detail_drawer', ['closeUrl' => route($routePrefix.'.list')])
    </div>
</div>

@include('partials.bundle._confirm_modal')
