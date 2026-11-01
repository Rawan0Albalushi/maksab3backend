@php
    // The schedule is three shapes behind one column, so it is written out rather than dumped:
    // a custom run names how many days it covers, a permanent weekly rule says it never ends, and
    // a dated run gives its range. Without this the cell was blank for every custom schedule,
    // which have no start_date at all.
    $schedule = function ($happyHour) {
        if ($happyHour->duration_type === \App\Models\HappyHour::DURATION_CUSTOM) {
            return translate('messages.Custom').' - '.count($happyHour->custom_days ?? []).' '.translate('messages.Days');
        }

        $days = $happyHour->duration_type === \App\Models\HappyHour::DURATION_WEEKLY
            ? implode(', ', $happyHour->weekly_days ?? [])
            : translate('messages.Daily');

        $window = $happyHour->is_permanent
            ? translate('messages.Permanent')
            : (($happyHour->start_date ? \App\CentralLogics\Helpers::date_format($happyHour->start_date) : translate('N/A'))
                .' - '
                .($happyHour->end_date ? \App\CentralLogics\Helpers::date_format($happyHour->end_date) : translate('N/A')));

        return $days.' | '.$window;
    };
@endphp

<div class="row">
    <div class="col-lg-12 text-center">
        <h1>{{ translate('Happy hour') }}</h1>
    </div>
    <div class="col-lg-12">
        <table>
            <thead>
                <tr>
                    <th>{{ translate('Report analytics') }}</th>
                    <th></th>
                    <th></th>
                    <th>{{ translate('Total happy hours') }}: {{ $data['data']->count() }}</th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                </tr>
                <tr>
                    <th>{{ translate('Search criteria') }}</th>
                    <th></th>
                    <th></th>
                    <th>{{ translate('Search bar content') }}: {{ $data['search'] ?? translate('N/A') }}</th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                </tr>
                <tr>
                    <th class="border-0">{{ translate('messages.SL') }}</th>
                    <th class="border-0 w--25">{{ translate('messages.Title') }}</th>
                    <th class="border-0">{{ translate('messages.Module') }}</th>
                    <th class="border-0 w--25">{{ translate('messages.Schedule') }}</th>
                    <th class="border-0">{{ translate('messages.Discount') }}</th>
                    <th class="border-0">{{ translate('messages.Store') }}</th>
                    <th class="border-0">{{ translate('messages.Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['data'] as $key => $happyHour)
                    <tr>
                        <td>{{ $key + 1 }}</td>
                        <td>{{ Str::limit($happyHour->title, 40, '...') }}</td>
                        <td>{{ $happyHour->module->module_name ?? translate('N/A') }}</td>
                        <td>
                            {{ $schedule($happyHour) }}
                            @if ($happyHour->start_time)
                                <br>
                                {{ \App\CentralLogics\Helpers::time_format($happyHour->start_time) }}
                                -
                                {{ \App\CentralLogics\Helpers::time_format($happyHour->end_time) }}
                            @endif
                        </td>
                        <td>
                            {{ $happyHour->discount }}%
                            @if ($happyHour->min_order_amount)
                                <br>
                                {{ translate('messages.Min') }}
                                {{ \App\CentralLogics\Helpers::format_currency($happyHour->min_order_amount) }}
                            @endif
                        </td>
                        <td>{{ $happyHour->enrollments_count ?? 0 }}</td>
                        <td class="text-capitalize">
                            @if ($happyHour->status)
                                <span class="badge badge-soft-success border-0">{{ translate('Active') }}</span>
                            @else
                                <span class="badge badge-soft-danger border-0">{{ translate('Inactive') }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
