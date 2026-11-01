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
                ? translate('messages.The store declined this happy hour')
                : translate('messages.You denied this request'))
            .($enrollment->rejection_reason ? ' : '.$enrollment->rejection_reason : '')
        );
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
                        <span class="d-block font-size-sm">{{ $enrollment->store->phone }}</span>
                    </div>
                </a>
            @else
                <span class="text-muted">{{ translate('messages.store deleted') }}</span>
            @endif
        </td>
        <td>{{ $enrollment->joined_at ? $enrollment->joined_at->format('d M Y') : 'N/A' }}</td>
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
            {{-- The button set follows who the row is waiting on:
                 pending          -> view, approve, deny
                 waiting on rest. -> view, cancel
                 approved         -> view, delete
                 denied           -> view, cancel
                 There is nothing to rework in a happy hour, so a denied enrolment is
                 cancelled and the store joins again from a clean row. --}}
            <div class="btn--container justify-content-center">
                @if ($enrollment->store)
                    <a class="btn btn-sm action-btn action-btn--view"
                       href="{{ route('admin.store.view', $enrollment->store->id) }}"
                       title="{{ translate('View') }}">
                        <i class="tio-visible-outlined"></i>
                    </a>
                @endif

                @if ($state === 'pending')
                    {{-- btn--success is not defined in this theme; the outline class alone is. --}}
                    <a class="btn btn-sm btn-outline-success action-btn approve-enrollment" href="javascript:"
                       data-url="{{ route('admin.happy-hour.store-confirmation', [$happyHour->id, $enrollment->id, 'approved']) }}"
                       title="{{ translate('Approve') }}">
                        <i class="tio-done font-weight-bold"></i>
                    </a>
                    <a class="btn btn-sm btn--danger btn-outline-danger action-btn reject-enrollment" href="javascript:"
                       data-url="{{ route('admin.happy-hour.store-confirmation', [$happyHour->id, $enrollment->id, 'rejected']) }}"
                       title="{{ translate('Deny') }}">
                        <i class="tio-clear font-weight-bold"></i>
                    </a>
                @else
                    {{-- The same delete either way; only the wording changes. A store's
                         pending request is denied instead - it is owed the refusal. --}}
                    <a class="btn btn-sm action-btn action-btn--delete form-alert" href="javascript:"
                       data-id="hh-enrollment-{{ $enrollment->id }}"
                       data-message="{{ $state === 'approved'
                            ? translate('Want to remove this store from the happy hour?')
                            : translate('Want to cancel this request?') }}"
                       title="{{ $state === 'approved' ? translate('Delete') : translate('Cancel request') }}">
                        <i class="tio-delete-outlined"></i>
                    </a>
                    <form action="{{ route('admin.happy-hour.remove-store', [$happyHour->id, $enrollment->id]) }}"
                          method="post" id="hh-enrollment-{{ $enrollment->id }}">
                        @csrf @method('delete')
                    </form>
                @endif
            </div>
        </td>
    </tr>
@endforeach
