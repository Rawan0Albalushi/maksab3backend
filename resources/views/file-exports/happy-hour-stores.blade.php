<div class="row">
    <div class="col-lg-12 text-center">
        <h1>{{ translate('Happy hour') }} : {{ $data['happyHour']->title }}</h1>
    </div>
    <div class="col-lg-12">
        <table>
            <thead>
                <tr>
                    <th>{{ translate('Report analytics') }}</th>
                    <th></th>
                    <th></th>
                    <th>{{ translate('Total stores') }}: {{ $data['enrollments']->count() }}</th>
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
                </tr>
                <tr>
                    <th class="border-0">{{ translate('messages.SL') }}</th>
                    <th class="border-0 w--15">{{ translate('messages.Store') }}</th>
                    <th class="border-0">{{ translate('Contact information') }}</th>
                    <th class="border-0">{{ translate('Joining offer date') }}</th>
                    <th class="border-0">{{ translate('messages.Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['enrollments'] as $key => $enrollment)
                    <tr>
                        <td>{{ $key + 1 }}</td>
                        <td>{{ $enrollment->store ? Str::limit($enrollment->store->name, 30, '...') : translate('messages.store deleted') }}</td>
                        <td>{{ $enrollment->store->phone ?? translate('N/A') }}</td>
                        <td>{{ $enrollment->joined_at ? \App\CentralLogics\Helpers::date_format($enrollment->joined_at) : translate('N/A') }}</td>
                        @php
                            // The same distinction the screen draws: an admin-raised request is
                            // waiting on the store, not on the admin, and reading both as
                            // "Pending" made the file useless for chasing either one.
                            $state = $enrollment->status === 'pending'
                                ? ($enrollment->requested_by === 'admin' ? 'admin_requested' : 'pending')
                                : $enrollment->status;
                        @endphp
                        <td class="text-capitalize">
                            @if ($state === 'approved')
                                <span class="badge badge-soft-success border-0">{{ translate('messages.Approved') }}</span>
                            @elseif ($state === 'rejected')
                                <span class="badge badge-soft-danger border-0">{{ translate('messages.Denied') }}</span>
                            @elseif ($state === 'admin_requested')
                                <span class="badge badge-soft-info border-0">{{ translate('Waiting on store') }}</span>
                            @else
                                <span class="badge badge-soft-info border-0">{{ translate('messages.Pending') }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
