
@extends('layouts.admin.app')

@section('title',translate('Wallet transactions'))

@section('subscriberList')
active
@endsection
@push('css_or_js')


@endpush

@section('content')

    <div class="content container-fluid">
        @include('admin-views.subscription.subscriber.partials._subscriber-nav', [
            'sn_active' => 'refunds',
            'sn_subtitle' => translate('Money refunded to this store when a subscription ended early, and what each entry covered.'),
        ])

        <div class="card">
            <div class="card-header flex-wrap py-2 border-0">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <h4 class="mb-0">{{ translate('Refund History') }}</h4>
                    <span class="badge badge-soft-dark rounded-circle">{{ $transactions->total() }}</span>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-borderless middle-align __txt-14px">
                        <thead class="thead-light white--space-false">
                            <th class="border-top px-4 border-bottom text-center">{{ translate('SL') }}</th>
                            <th class="border-top px-4 border-bottom"><div class="text-title">{{ translate('Transaction date') }}</div></th>
                            <th class="border-top px-4 border-bottom">{{ translate('Package name') }}</th>
                            <th class="border-top px-4 border-bottom">{{ translate('Refund amount') }}</th>
                            <th class="border-top px-4 border-bottom">{{ translate('Refunded for') }}</th>
                            <th class="border-top px-4 border-bottom">{{ translate('Status') }}</th>
                        </thead>
                        <tbody>
                            @foreach ($transactions as $k=> $transaction)

                            <tr>
                                <td class="px-4 text-center">{{ $k + $transactions->firstItem() }}</td>
                                <td class="px-4">
                                    <div class="pl-4">{{ \App\CentralLogics\Helpers::date_format($transaction->created_at) }}</div>
                                </td>

                                <td class="px-4">
                                    <div class="text-title">{{ $transaction?->package?->package_name }}</div>
                                </td>

                                <td class="px-4">
                                    <div class="w--120px text-title text-right pr-5">{{ \App\CentralLogics\Helpers::format_currency($transaction->amount) }}</div>
                                </td>
                                <td class="px-4">
                                    <div class="w--120px text-title text-right pr-5">{{ str_replace(['validity_left_'], '', $transaction->reference)  }} {{ translate('messages.days') }}</div>
                                </td>


                                <td class="px-4">
                                    <span class="text-success">
                                        {{ translate('success')  }}
                                    </span>

                                </td>

                            </tr>
                            @endforeach

                        </tbody>
                    </table>
                </div>
                @if(count($transactions) !== 0)
                <hr>
                @endif
                <div class="page-area">
                    {!! $transactions->withQueryString()->links() !!}
                </div>
                @if(count($transactions) === 0)
                <div class="empty--data">
                    <img src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="public">
                    <h5>
                        {{translate('No data found')}}
                    </h5>
                </div>
                @endif
            </div>
        </div>
    </div>




@endsection

@push('script_2')

@endpush
