@push('script_2')
    <script>
        "use strict";

        // Changing the zone changes which modules are still free to claim, so the picker is
        // refetched rather than left showing modules another setup in the new zone already owns.
        (function () {
            const setupId = @json($setup?->id);
            const emptyMessage = $('.module-empty-message');

            // Display only — Parcel isn't blocked from this picker or its save, so this just
            // tells the admin up front that Express/Slightly Delay have no effect on it.
            function syncParcelWarning() {
                const hasParcel = $('#module_ids option:selected[data-module-type="parcel"]').length > 0;
                $('#parcel-adc-warning').toggleClass('d-none', !hasParcel);
            }

            $(document).on('change', '#module_ids', syncParcelWarning);
            syncParcelWarning();

            $('#zone_id').on('change', function () {
                const zoneId = $(this).val();

                if (!zoneId) { return; }

                {{-- Built from the route, not a hand-written path: this URL moved once already
                     when Delivery Management got its own prefix, and a literal would have 404'd
                     silently. The sentinel is stripped so the id can be appended. --}}
                $.get('{{ rtrim(route('admin.business-settings.zone.additional-delivery-charge.zone-modules', ['zoneId' => 0]), '0') }}' + zoneId,
                    { setup_id: setupId },
                    function (data) {
                        const $picker = $('#module_ids');
                        $picker.empty();

                        (data.modules || []).forEach(function (module) {
                            $picker.append($('<option>').val(module.id).attr('data-module-type', module.type).text(module.name));
                        });

                        $picker.trigger('change');
                        emptyMessage.text(data.modules_empty_message)
                            .toggleClass('d-none', (data.modules || []).length > 0);
                    });
            });
        })();
    </script>
@endpush
