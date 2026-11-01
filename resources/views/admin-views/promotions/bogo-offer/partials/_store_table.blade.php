@foreach ($enrollments as $key => $enrollment)
    @php
        // One word for the row. An admin assignment waits on the store, so it is not
        // the admin's to decide - only to cancel.
        $state = $enrollment->status === 'pending'
            ? ($enrollment->requested_by === 'admin' ? 'admin_requested' : 'pending')
            : $enrollment->status;

        // Who said no: the admin's own denial and a store declining an admin request
        // land in the same state and need different words.
        $rejectionNote = $state !== 'rejected' ? null : trim(
            ($enrollment->rejected_by === 'store'
                ? translate('messages.The store declined this offer')
                : translate('messages.You denied this request'))
            .($enrollment->rejection_reason ? ' : '.$enrollment->rejection_reason : '')
        );

        // Frozen items mapped back into the add drawer's working shape, so the row's edit
        // icon opens the same picker the enrolment drawer does.
        $editItems = fn ($items) => $items->map(fn ($i) => [
            'item_id' => $i->item_id,
            'quantity' => $i->quantity,
            'selected_variations' => $i->variations ?? [],
            'add_on_ids' => $i->add_on_ids ?? [],
            'add_on_qtys' => $i->add_on_qtys ?? [],
        ])->values();

        $editPayload = $state !== 'rejected' ? null : [
            'store_id' => $enrollment->store_id,
            // The add drawer's dropdown only lists stores yet to join, so the one being
            // edited is never among them - the name lets us inject its option.
            'store_name' => $enrollment->store->name ?? '',
            'enrollment_id' => $enrollment->id,
            'url' => route('admin.bogo-offer.update-enrollment', [$offer->id, $enrollment->id]),
            'is_resubmit' => true,
            'submit_label' => translate('Edit & resubmit'),
            'buy' => $editItems($enrollment->buyItems),
            'get' => $editItems($enrollment->getItems),
        ];
    @endphp

    <tr>
        <td>{{ $key + $enrollments->firstItem() }}</td>
        <td>
            @if($enrollment->store)
                <a href="{{ route('admin.store.view', $enrollment->store->id) }}" class="table-rest-info">
                    <img class="onerror-image" data-onerror-image="{{asset('public/assets/admin/img/100x100/1.png')}}"
                         src="{{ $enrollment->store->logo_full_url }}" alt="store">
                    <div class="info">
                        <span class="d-block text-body">{{ Str::limit($enrollment->store->name, 20, '...') }}</span>
                        <span class="d-block font-size-sm opacity-75">{{ $enrollment->store->phone }}</span>
                    </div>
                </a>
            @else
                <span class="text-muted">{{ translate('messages.store deleted') }}</span>
            @endif
        </td>
        <td>{{ $enrollment->joined_at ? $enrollment->joined_at->format('d M Y') : 'N/A' }}</td>

        {{-- A single item shows inline; several collapse to a link that opens the drawer. --}}
        @foreach (['buy' => $enrollment->buyItems, 'get' => $enrollment->getItems] as $items)
            <td>
                @if($items->count() === 0)
                    <span class="text-muted">N/A</span>
                @elseif($items->count() === 1)
                    @php($item = $items->first())
                    @include('admin-views.promotions.bogo-offer.partials._item_cell', ['item' => $item, 'addOnNames' => $addOnNames])
                @else
                    <a href="javascript:" class="text-primary enrollment-detail" style="text-decoration:underline"
                       data-url="{{ route('admin.bogo-offer.enrollment-detail', [$offer->id, $enrollment->id]) }}">
                        {{ $items->count() }} {{ translate('messages.Items') }}
                    </a>
                @endif
            </td>
        @endforeach

        <td class="text-capitalize">
            @if ($state === 'pending')
                <span class="badge badge-soft-info">{{ translate('messages.Pending') }}</span>
            @elseif($state === 'admin_requested')
                {{-- The admin raised this one, so it is not the admin's to decide - it waits
                     on the store's answer. --}}
                <span class="badge badge-soft-secondary">{{ translate('Waiting on store') }}</span>
            @elseif($state === 'approved')
                <span class="badge badge-soft-success">{{ translate('messages.Approved') }}</span>
            @else
                <span class="badge badge-soft-danger" data-toggle="tooltip" title="{{ $rejectionNote }}">
                    {{ translate('messages.Denied') }}
                </span>
            @endif
        </td>

        <td>
            <div class="btn--container justify-content-center">
                <a class="btn btn-sm action-btn action-btn--view enrollment-detail" href="javascript:"
                   data-url="{{ route('admin.bogo-offer.enrollment-detail', [$offer->id, $enrollment->id]) }}"
                   title="{{ translate('View') }}">
                    <i class="tio-visible-outlined"></i>
                </a>

                {{-- Approved and still not on the customer's screen: the reasons are named on
                     hover, so the admin can answer the store without opening the drawer.
                     Nothing is drawn while the bundle is running normally. --}}
                @include('partials.promotion._visibility_warning', [
                    'reasons' => $hiddenReasons[$enrollment->id] ?? [],
                    'class' => 'btn-sm action-btn',
                ])

                @if ($state === 'pending')
                    {{-- The store asked. Approve or deny it here; the drawer also offers
                         Edit & Approve. Denied enrolments are reworked, not re-approved. --}}
                    <a class="btn btn-sm btn-outline-success action-btn approve-enrollment" href="javascript:"
                       data-url="{{ route('admin.bogo-offer.store-confirmation', [$offer->id, $enrollment->id, 'approved']) }}"
                       title="{{ translate('Approve') }}">
                        <i class="tio-done font-weight-bold"></i>
                    </a>
                    <a class="btn btn-sm btn--danger btn-outline-danger action-btn reject-enrollment" href="javascript:"
                       data-url="{{ route('admin.bogo-offer.store-confirmation', [$offer->id, $enrollment->id, 'rejected']) }}"
                       title="{{ translate('Deny') }}">
                        <i class="tio-clear font-weight-bold"></i>
                    </a>
                @else
                    {{-- A denied enrolment can be reworked and sent back out as an admin
                         request, so the store still gets the last word. --}}
                    @if ($state === 'rejected')
                        <a class="btn btn-sm action-btn action-btn--edit edit-and-approve" href="javascript:"
                           data-payload="{{ json_encode($editPayload) }}"
                           title="{{ translate('Edit & resubmit') }}">
                            <i class="tio-edit"></i>
                        </a>
                    @endif

                    {{-- The same delete either way; only the wording changes. A store's
                         pending request is denied instead - it is owed the refusal. --}}
                    <a class="btn btn-sm action-btn action-btn--delete form-alert" href="javascript:"
                       data-id="enrollment-{{ $enrollment->id }}"
                       data-message="{{ $state === 'approved'
                            ? translate('Want to remove this store from the offer?')
                            : translate('Want to cancel this request?') }}"
                       title="{{ $state === 'approved' ? translate('Delete') : translate('Cancel request') }}">
                        <i class="tio-delete-outlined"></i>
                    </a>
                    <form action="{{ route('admin.bogo-offer.remove-store', [$offer->id, $enrollment->id]) }}"
                          method="post" id="enrollment-{{ $enrollment->id }}">
                        @csrf @method('delete')
                    </form>
                @endif
            </div>
        </td>
    </tr>
@endforeach
