{{--
    "The admin has invited you" prompt, raised once per unanswered invitation.

    An admin-raised enrolment lands pending with checked = 0 and waits on the store, so without a
    prompt it sits unseen until the vendor happens to open the promotion list. This is the same
    mechanism the new-order popup uses: the row carries its own unread flag, and opening the list
    clears it, so an invitation is announced once and a later one is announced again.

    Included only from the dashboard (see layouts/vendor/app.blade.php) rather than every screen,
    so answering it is not a race against the next page load putting it back up.
--}}
@php
    $promotionInvites = \App\Navigation\VendorPromotionInvitations::pending();
@endphp

@if($promotionInvites)
    <div class="modal fade" id="promotionInviteModal" tabindex="-1" role="dialog" data-backdrop="static">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content border-0 rounded-8">
                <div class="modal-body text-center px-4 pb-4 pt-3">
                    <button type="button" class="close ml-auto" data-dismiss="modal"
                            aria-label="{{ translate('Close') }}">&times;</button>

                    {{-- The panel's own confirmation artwork. It replaced a "!" drawn on a
                         `bg--danger` disc -- the double-dash form is not a class this theme
                         defines, so the disc rendered transparent and its white glyph vanished
                         into the modal, leaving the prompt with no mark at all. One image serves
                         both invitations: this modal is filled from a queue, so the BOGO and the
                         happy hour prompt are the same dialog shown twice. --}}
                    <div class="d-flex justify-content-center my-3">
                        <img src="{{ asset('public/assets/admin/img/delete-confirmation.png') }}" alt=""
                             style="max-width:64px;height:auto">
                    </div>

                    <h4 class="font-bold mb-2" id="promotion-invite-title"></h4>
                    <p class="opacity-75 mb-4" id="promotion-invite-body"></p>

                    <a href="#" class="btn btn--primary min-w-120" id="promotion-invite-link">
                        {{ translate('View list') }}
                    </a>
                </div>
            </div>
        </div>
    </div>

    @push('script_2')
        <script>
            "use strict";

            // One queue, shown one at a time: a store invited to both would otherwise get two
            // modals stacked on each other.
            const promotionInvites = @json($promotionInvites);

            function showNextPromotionInvite() {
                const invite = promotionInvites.shift();

                if (!invite) return;

                $('#promotion-invite-title').text(invite.title);
                $('#promotion-invite-body').text(invite.body);
                $('#promotion-invite-link').attr('href', invite.url);
                $('#promotionInviteModal').modal('show');
            }

            $('#promotionInviteModal').on('hidden.bs.modal', showNextPromotionInvite);

            $(function () {
                showNextPromotionInvite();
            });
        </script>
    @endpush
@endif
