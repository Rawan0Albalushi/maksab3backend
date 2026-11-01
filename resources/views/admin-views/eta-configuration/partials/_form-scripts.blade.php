<script>
    "use strict";

    (function () {
        const DISTANCE = @json(\App\Models\EtaConfiguration::METHOD_DISTANCE);
        const MINUTE = @json(translate('ETA minute unit'));

        function minutes(selector) {
            const value = parseInt($(selector).val(), 10);
            return isNaN(value) || value < 0 ? 0 : value;
        }

        // The same arithmetic EtaConfiguration::minimumEtaMinutes() does, so the admin previews
        // what the customer will be shown: the minimum is a FLOOR the buffers are measured
        // against, not a term added to them.
        function syncPreview() {
            const floor = Math.max(
                minutes('#preparation_buffer') + minutes('#transit_buffer'),
                minutes('#minimum_delivery_time'),
            );
            const ceiling = floor + minutes('#time_gap');

            $('#eta-preview-range').text(
                ceiling === floor ? floor + ' ' + MINUTE : floor + ' ' + MINUTE + ' - ' + ceiling + ' ' + MINUTE,
            );
        }

        // Parcel's own preview — no preparation buffer to fold in, mirrors
        // EtaConfiguration::parcelMinimumEtaMinutes()/parcelMaximumEtaMinutes().
        function syncParcelPreview() {
            const floor = Math.max(minutes('#parcel_transit_buffer'), minutes('#parcel_minimum_delivery_time'));
            const ceiling = floor + minutes('#parcel_time_gap');

            $('#parcel-eta-preview-range').text(
                ceiling === floor ? floor + ' ' + MINUTE : floor + ' ' + MINUTE + ' - ' + ceiling + ' ' + MINUTE,
            );
        }

        // Parcel's section is a different setup entirely (no method choice, no preparation
        // buffer), so it is shown or hidden as a whole rather than field by field, the moment
        // Parcel enters or leaves the picked modules — including right after the zone-change
        // AJAX below has rebuilt the whole #module_ids option list, since every rebuilt option
        // carries the same data-module-type this reads.
        function syncParcelSection() {
            const hasParcel = $('#module_ids option:selected[data-module-type="parcel"]').length > 0;

            $('#parcel-eta-section').toggleClass('d-none', !hasParcel);

            if (hasParcel) { syncParcelPreview(); }
        }

        // Mirrors syncParcelSection(): the other card is a different setup entirely too (method
        // choice, preparation buffer), and has nothing to configure once Parcel is the only
        // module picked, so it is hidden as a whole rather than field by field.
        function syncOtherSection() {
            const hasOther = $('#module_ids option:selected').not('[data-module-type="parcel"]').length > 0;

            $('#other-eta-section').toggleClass('d-none', !hasOther);

            if (hasOther) { syncPreview(); }
        }

        // The gap and the map note belong to the distance method alone, and dropping the gap
        // lets the three remaining timings spread three across — the design's fixed layout.
        function syncMethod() {
            const method = $('.eta-method-input:checked').val();
            const isDistance = method === DISTANCE;

            $('.rule-method').removeClass('is-selected');
            $('.eta-method-input:checked').closest('.rule-method').addClass('is-selected');

            $('#time-gap-wrapper').toggleClass('d-none', !isDistance);
            $('#eta-map-note').toggleClass('d-none', !isDistance);

            $('.eta-time-col').not('#time-gap-wrapper')
                .toggleClass('col-md-6', isDistance)
                .toggleClass('col-md-4', !isDistance);

            syncPreview();
        }

        $(document).on('change', '.eta-method-input', syncMethod);
        $(document).on('input', '#minimum_delivery_time, #preparation_buffer, #transit_buffer, #time_gap', syncPreview);
        $(document).on('input', '#parcel_minimum_delivery_time, #parcel_transit_buffer, #parcel_time_gap', syncParcelPreview);
        // select2 fires 'change' on the underlying <select> for every pick/unpick, and again
        // (triggered below) after the zone-change AJAX rebuilds the option list, so this one
        // binding covers every way the picked modules can change.
        $(document).on('change', '#module_ids', syncParcelSection);
        $(document).on('change', '#module_ids', syncOtherSection);
        syncMethod();
        syncParcelSection();
        syncOtherSection();

        // Changing zone re-narrows the module picker: only modules connected to that zone and not
        // already configured. Built from route(), never a hand-written path.
        $(document).on('change', '#zone_id', function () {
            const zoneId = $(this).val();
            if (!zoneId) { return; }

            $.get('{{ route('admin.business-settings.zone.eta-configuration.zone-modules', ['zoneId' => '__ZONE__']) }}'
                    .replace('__ZONE__', zoneId),
                { configuration_id: {{ $configuration?->id ?? 'null' }} },
                function (data) {
                    const $select = $('#module_ids');
                    $select.empty();
                    (data.modules || []).forEach(function (m) {
                        $select.append($('<option>').val(m.id).attr('data-module-type', m.type).text(m.name));
                    });
                    $select.trigger('change');

                    const none = (data.modules || []).length === 0;
                    $('#modules-all-configured').toggleClass('d-none', !none)
                        .find('span').text(data.modules_empty_message);
                });
        });

        // One error at a time, in field order — the same rule the delivery-rule form follows.
        const REQUIRED = {
            name: @json(translate('messages.Default name is required')),
            zone: @json(translate('messages.Please select a zone')),
            modules: @json(translate('messages.Please select at least one module')),
            method: @json(translate('messages.Please select an ETA method')),
            minimum: @json(translate('messages.Minimum delivery time is required')),
            preparation: @json(translate('messages.Preparation buffer is required')),
            transit: @json(translate('messages.Transit buffer is required')),
        };

        $(document).on('submit', '#eta-configuration-form', function (e) {
            let problem = null;
            const parcelVisible = !$('#parcel-eta-section').hasClass('d-none');
            const otherVisible = !$('#other-eta-section').hasClass('d-none');

            if (!$.trim($('#name_default').val())) {
                problem = { el: $('#name_default'), message: REQUIRED.name };
            } else if (!$('#zone_id').val()) {
                problem = { el: $('#zone_id'), message: REQUIRED.zone };
            } else if (!($('#module_ids').val() || []).length) {
                problem = { el: $('#module_ids'), message: REQUIRED.modules };
            } else if (otherVisible && !$('.eta-method-input:checked').val()) {
                problem = { el: $('.eta-method-input').first(), message: REQUIRED.method };
            } else if (otherVisible && $.trim($('#minimum_delivery_time').val()) === '') {
                problem = { el: $('#minimum_delivery_time'), message: REQUIRED.minimum };
            } else if (otherVisible && $.trim($('#preparation_buffer').val()) === '') {
                problem = { el: $('#preparation_buffer'), message: REQUIRED.preparation };
            } else if (otherVisible && $.trim($('#transit_buffer').val()) === '') {
                problem = { el: $('#transit_buffer'), message: REQUIRED.transit };
            } else if (parcelVisible && $.trim($('#parcel_minimum_delivery_time').val()) === '') {
                problem = { el: $('#parcel_minimum_delivery_time'), message: REQUIRED.minimum };
            } else if (parcelVisible && $.trim($('#parcel_transit_buffer').val()) === '') {
                problem = { el: $('#parcel_transit_buffer'), message: REQUIRED.transit };
            }

            if (!problem) { return; }

            e.preventDefault();
            toastr.error(problem.message, { CloseButton: true, ProgressBar: true });

            // The name lives behind a language tab, which must be opened before it can be focused.
            const $langForm = problem.el.closest('.lang_form');
            if ($langForm.length && $langForm.hasClass('d-none')) {
                $('#' + $langForm.attr('id').replace('-form', '-link')).trigger('click');
            }

            // select2 hides the real <select>, so focus its rendered control instead.
            const id = problem.el.attr('id');
            const $target = id === 'module_ids' || id === 'zone_id'
                ? problem.el.next('.select2').find('.select2-selection')
                : problem.el;

            if ($target.length) {
                $('html, body').animate({ scrollTop: Math.max(0, $target.offset().top - 160) }, 200);
                $target.trigger('focus');
            }

            return false;
        });
    })();
</script>
