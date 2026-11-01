{{--
    The enrolment's state, as one badge, for both promotion lists.

    The wording comes from HandlesPromotionEnrollment so the panel and the vendor app never
    describe one enrolment two ways. "N/A" rather than a badge for a promotion the store has not
    answered at all: there is no state yet, and a grey pill reads as one.

    $enrollment - the store's own row, or null
    $label      - the phrase, already resolved by the controller
    $note       - who denied it and why, shown on hover of a denied badge
    $ended      - the promotion's window is behind us, which replaces the state entirely
--}}
@php($state = ! $enrollment
    ? 'not_joined'
    : ($enrollment->status === 'pending'
        ? ($enrollment->requested_by === 'admin' ? 'admin_requested' : 'pending')
        : $enrollment->status))

@if($ended)
    <span class="badge badge-soft-secondary">{{ translate('messages.Expired') }}</span>
@elseif($state === 'not_joined')
    <span class="opacity-75">{{ translate('N/A') }}</span>
@elseif($state === 'approved')
    <span class="badge badge-soft-success">{{ $label }}</span>
@elseif($state === 'pending')
    <span class="badge badge-soft-info">{{ $label }}</span>
@elseif($state === 'admin_requested')
    {{-- Not a coloured pill, per the design: this one is waiting on the vendor, and the two
         action buttons beside it already carry the urgency. --}}
    <span class="opacity-75">{{ $label }}</span>
@else
    <span class="badge badge-soft-danger" @if($note) data-toggle="tooltip" title="{{ $note }}" @endif>
        {{ $label }}
    </span>
@endif
