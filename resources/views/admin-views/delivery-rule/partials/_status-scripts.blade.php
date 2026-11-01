<script>
    "use strict";

    // Both directions are confirmed, so the checkbox never changes state on its
    // own — preventDefault restores it and only the server response reloads.
    (function () {
        let pendingRuleId = null;
        let pendingTurningOn = false;

        @php($ruleLockedText = translate('It is the only active rule for these modules in this zone, and a zone must always keep a way to price delivery. Activate a replacement rule for them first, and this one switches off on its own.'))
        $('input[data-rule-locked]').each(function () {
            $(this).on('click', function (event) {
                var modules = $(this).data('rule-locked');

                event.preventDefault();
                event.stopImmediatePropagation();
                $(this).prop('checked', true);

                $('#rule-status-locked-text').text(
                    @json($ruleLockedText) + ' ' + @json(translate('messages.Modules')) + ': ' + modules
                );
                $('#rule-status-locked-modal').modal('show');
            });
        });

        $(document).on('click', '.rule-status-toggle', function (e) {
            // preventDefault undoes the browser's flip on its own, so the switch
            // keeps showing the saved state. The direction has to come from that
            // saved state too: reading `checked` here reads the flip the browser
            // is about to undo, which is what opened the opposite modal.
            e.preventDefault();

            const $input = $(this);
            pendingRuleId = $input.data('id');
            pendingTurningOn = Number($input.data('status')) !== 1;

            if (pendingTurningOn) {
                $('#rule-turn-on-modal').modal('show');
                return;
            }

            // Offer the zone's other rules as the replacement. They come from the
            // server because the list is paginated and the detail page holds only
            // this rule, so the markup never carries the full set.
            const $select = $('#replacement_id').empty();
            $('#replacement-empty').addClass('d-none');
            $('#replacement_id').prop('disabled', true);
            $('#rule-turn-off-confirm').prop('disabled', true);
            $('#rule-turn-off-modal').modal('show');

            {{-- Built from route(), not a hand-written path: the ported script pointed at
                 `admin/delivery-rule/...`, which is not where these live, so both calls 404'd and
                 the toggle only ever reported an error. --}}
            $.get('{{ route('admin.business-settings.zone.delivery-rule.zone-rules', ['zoneId' => '__ZONE__']) }}'
                    .replace('__ZONE__', $input.data('zone')), {
                exclude: pendingRuleId
            }, function (data) {
                (data.rules || []).forEach(function (rule) {
                    $select.append($('<option>').val(rule.id).text(rule.name));
                });

                const hasOptions = $select.find('option').length > 0;
                $('#replacement-empty').toggleClass('d-none', hasOptions);
                $('#replacement_id').prop('disabled', !hasOptions);
                $('#rule-turn-off-confirm').prop('disabled', !hasOptions);
            });
        });

        function send(payload) {
            $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });
            $.post({
                url: '{{ route('admin.business-settings.zone.delivery-rule.status', ['id' => '__RULE__']) }}'
                        .replace('__RULE__', pendingRuleId),
                data: payload,
                success: function (data) {
                    if (data.errors) {
                        data.errors.forEach(function (err) { toastr.error(err.message); });
                        return;
                    }
                    toastr.success(data.success);
                    setTimeout(function () { location.reload(); }, 900);
                },
                error: function (xhr) {
                    const errs = xhr.responseJSON && xhr.responseJSON.errors;
                    if (errs) { errs.forEach(function (e) { toastr.error(e.message); }); }
                    else { toastr.error('{{ translate('messages.An unexpected error occurred') }}'); }
                }
            });
        }

        $(document).on('click', '#rule-turn-on-confirm', function () {
            $('#rule-turn-on-modal').modal('hide');
            send({ status: 1 });
        });

        $(document).on('click', '#rule-turn-off-confirm', function () {
            const replacement = $('#replacement_id').val();
            if (!replacement) { return; }
            $('#rule-turn-off-modal').modal('hide');
            send({ status: 0, replacement_id: replacement });
        });
    })();
</script>
