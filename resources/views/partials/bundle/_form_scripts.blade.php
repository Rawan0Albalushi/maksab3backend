<script>
    "use strict";

    const itemsEndpoint = '{{ $itemsUrl }}';
    const formUrl = '{{ $action }}';
    const redirectUrl = '{{ $redirectUrl }}';
    const successMessage = '{{ $successMessage }}';
    const maxDiscount = {{ $maxDiscount }};
    const itemPickerWithPrice = true;
    const itemPickerNoResultsText = '{{ $isServiceModule
        ? translate('messages.No service found')
        : ($storeLocked ? translate('messages.No item found') : translate('messages.No item found in this store')) }}';
    const minItems = {{ $minItems }};
    const seedItems = @json($seedItems);
    const drawer = $(document.body);
    let foodCatalog = [];

    $.ajaxSetup({headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')}});
</script>

@include('partials.bundle._picker_scripts')

<script>
    "use strict";

    $(document).on('input', '[data-counter]', function () {
        $('#' + $(this).data('counter')).text($(this).val().length);
    });

    $('#bundle-form').on('reset', function () {
        const $form = $(this);

        setTimeout(function () {
            $form.find('[data-counter]').each(function () {
                $('#' + $(this).data('counter')).text($(this).val().length);
            });
            recalcBundlePrices();
        }, 0);
    });

    $('#start_date').on('change', function () {
        $('#end_date').attr('min', $(this).val());
    });
    $('#end_date').on('change', function () {
        $('#start_date').attr('max', $(this).val());
    });

    @if (! $storeLocked)
        $('#bundle_store_id').select2({
            width: '100%',
            placeholder: '{{ $isServiceModule ? translate('messages.Select provider') : translate('messages.Select store') }}',
            dropdownParent: drawer,
        });

        $('#bundle_store_id').on('change', function () {
            resetBundleItems();
            loadBundleCatalog($(this).val());
        });
    @endif

    if ($('#bundle_store_id').val()) {
        loadBundleCatalog($('#bundle_store_id').val(), function () {
            seedBundleItems(seedItems);
        });
    } else {
        renderBundleItems();
    }

    $('#bundle-form').on('submit', function (e) {
        e.preventDefault();

        if (bundleItems.length < minItems) {
            toastr.error('{{ translate('messages.A bundle needs at least') }} ' + minItems + ' {{ translate('messages.Items') }}');
            return;
        }

        const $button = $(this).find('button[type="submit"]');
        $button.prop('disabled', true);

        const formData = new FormData(this);
        bundleItemFields().forEach(([name, value]) => formData.append(name, value));

        $.post({
            url: formUrl,
            data: formData,
            cache: false,
            contentType: false,
            processData: false,
            success: function (data) {
                if (data.errors) {
                    $button.prop('disabled', false);
                    data.errors.forEach(err => toastr.error(err.message, {CloseButton: true, ProgressBar: true}));
                } else {
                    toastr.success(successMessage, {CloseButton: true, ProgressBar: true});
                    setTimeout(() => location.href = redirectUrl, 2000);
                }
            },
            error: function (xhr) {
                $button.prop('disabled', false);
                const errors = xhr.responseJSON?.errors || [];
                errors.length
                    ? errors.forEach(err => toastr.error(err.message))
                    : toastr.error('{{ translate('Something went wrong') }}');
            }
        });
    });
</script>
