@php($slots_by_day = $store->schedules->sortBy('opening_time')->groupBy('day'))
@php($week_start = now()->startOfWeek(\Carbon\CarbonInterface::SUNDAY))
@php($today = now()->dayOfWeek)
<ul class="sts-week">
    @foreach ([1, 2, 3, 4, 5, 6, 0] as $day)
        @php($day_name = $week_start->copy()->addDays($day)->translatedFormat('l'))
        @php($slots = $slots_by_day->get($day, collect()))
        <li class="sts-day {{ $slots->isEmpty() ? 'is-closed' : '' }}">
            <div class="sts-day__name">
                <span>{{ $day_name }}</span>
                @if ($today === $day)
                    <span class="sts-day__today">{{ translate('Today') }}</span>
                @endif
            </div>
            <div class="sts-day__slots">
                @forelse ($slots as $slot)
                    <span class="sts-slot">
                        <i class="tio-time"></i>
                        <span>{{ date(config('timeformat'), strtotime($slot->opening_time)) }} – {{ date(config('timeformat'), strtotime($slot->closing_time)) }}</span>
                        <button type="button" class="sts-slot__remove delete-schedule"
                            aria-label="{{ translate('Remove') }}" title="{{ translate('Remove') }}"
                            data-url="{{ route('vendor.business-settings.remove-schedule', ['store_schedule' => $slot->id]) }}">
                            <i class="tio-clear"></i>
                        </button>
                    </span>
                @empty
                    <span class="sts-day__closed">{{ translate('messages.Off day') }}</span>
                @endforelse
            </div>
            <button type="button" class="sts-day__add" data-toggle="modal" data-target="#add-schedule-modal"
                data-dayid="{{ $day }}" data-day="{{ $day_name }}">
                <i class="tio-add"></i> {{ translate('Add hours') }}
            </button>
        </li>
    @endforeach
</ul>
