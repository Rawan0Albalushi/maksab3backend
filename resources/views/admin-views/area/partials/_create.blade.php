<form action="{{ route('admin.business-settings.zone.area.store') }}" method="post" class="d-flex flex-column h-100"
    id="area-offcanvas-form">
    @csrf
    <div class="custom-offcanvas-header bg--secondary d-flex justify-content-between align-items-center px-3 py-3">
        <h3 class="mb-0">{{ translate('Add new area') }}</h3>
        <button type="button"
            class="btn-close w-25px h-25px border rounded-circle d-center bg--secondary offcanvas-close fz-15px p-0"
            aria-label="Close">&times;</button>
    </div>

    @include('admin-views.area.partials._form-body', [
        'area' => null,
        'submitLabel' => translate('messages.Add'),
    ])
</form>
