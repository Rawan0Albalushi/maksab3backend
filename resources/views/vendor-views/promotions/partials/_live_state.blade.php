{{--
    What a CUSTOMER would see of this enrolment right now.

    Deliberately a second badge rather than folded into the status one. They answer different
    questions and came apart often enough to matter: the status badge says where the request stands
    with the admin, and "Approved" is not "running". A bundle whose only item just sold out is
    still approved, and without this the vendor's only clue was that nothing was selling.

    The positive states earn their place as much as the negative one. A vendor who sees nothing
    cannot tell "running fine" from "we never built this", and a scheduled promotion looks
    identical to a broken one.

    $visibility - {status, label, reasons} from bogoCustomerVisibility() / happyHourCustomerVisibility()
    $inline     - true in a drawer header, where the reasons are already listed below it
--}}
@php($visibility = $visibility ?? ['status' => 'not_applicable', 'label' => null, 'reasons' => []])

@if($visibility['status'] !== 'not_applicable' && $visibility['label'])
    @php($tone = match ($visibility['status']) {
        'running' => 'badge-soft-success',
        'scheduled' => 'badge-soft-info',
        'ended' => 'badge-soft-secondary',
        default => 'badge-soft-warning',
    })

    <span class="badge {{ $tone }} fs-13 fw-400 px-2 py-1"
          @if(! ($inline ?? false) && $visibility['reasons'])
              data-toggle="tooltip" data-placement="top"
              title="{{ implode(' | ', $visibility['reasons']) }}"
          @endif>
        {{ $visibility['label'] }}
    </span>
@endif
