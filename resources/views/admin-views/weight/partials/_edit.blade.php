<form action="{{ route('admin.business-settings.zone.weight.update', $weight->id) }}" method="post"
    class="d-flex flex-column h-100" id="weight-offcanvas-form">
    @csrf
    <div class="custom-offcanvas-header bg--secondary d-flex justify-content-between align-items-center px-3 py-3">
        <h3 class="mb-0">{{ translate('Edit weight') }}</h3>
        <button type="button"
            class="btn-close w-25px h-25px border rounded-circle d-center bg--secondary offcanvas-close fz-15px p-0"
            aria-label="Close">&times;</button>
    </div>

    @include('admin-views.weight.partials._form-body', [
        'weight' => $weight,
        'submitLabel' => translate('messages.Update'),
    ])
</form>
