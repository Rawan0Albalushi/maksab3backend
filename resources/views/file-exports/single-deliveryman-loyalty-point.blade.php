<div class="row">
    <div class="col-lg-12 text-center ">
        <h1>{{ translate('Delivery man loyalty point transaction history') }}</h1>
    </div>
    <div class="col-lg-12">



        <table>
            <thead>
                <tr>
                    <th>{{ translate('Deliveryman information') }}</th>
                    <th></th>
                    <th></th>
                    <th>
                        {{ translate('Name')  }}- {{ $data['dm']->f_name . ' ' . $data['dm']->l_name}}
                        <br>
                        {{ translate('Phone')  }}- {{ $data['dm']->phone}}
                        <br>
                        {{ translate('email')  }}- {{ $data['dm']->email}}
                        <br>
                        {{ translate('Total rating')  }}- {{ count($data['dm']->rating)}}
                        <br>
                        {{ translate('Average review')  }}-
                        {{count($data['dm']->rating) > 0 ? number_format($data['dm']->rating[0]->average, 1, '.', ' ') : 0}}

                    </th>
                    <th> </th>
                    <th></th>
                    <th></th>
                    <th></th>
                </tr>
                <tr>
                    <th>{{ translate('SL') }}</th>
                    <th>{{translate('messages.Transaction ID')}}</th>
                    <th>{{translate('messages.Date')}}</th>
                    <th>{{translate('Transaction type')}}</th>
                    <th>{{translate('messages.Point')}}</th>
                    <th>{{translate('messages.reference')}}</th>

            </thead>
            <tbody>
                @foreach($data['histories'] as $key => $loyalty_point)
                    <tr>
                        <td class="text-center">{{  $key + 1 }}</td>
                        <td>
                            <div class="text-wrap line--limit-1  max-w--220px min-w-160 text-title">
                                {{ $loyalty_point->transaction_id }}
                            </div>
                        </td>
                        <td>
                            <div class="text-wrap line--limit-1  max-w--220px min-w-160 text-title">
                                {{ \App\CentralLogics\Helpers::date_format($loyalty_point->created_at) }}
                            </div>
                        </td>
                        <td>
                            <div class="text-wrap line--limit-1  max-w--220px min-w-160 text-title">
                                {{ translate($loyalty_point->transaction_type) }}
                                {{ $loyalty_point->transaction_type == 'converted_to_wallet' ? '(' . \App\CentralLogics\Helpers::currency_symbol() . ')' : ''}}
                            </div>
                        </td>
                        <td>
                            <div class="text-dark text-right pr-6">
                                {{ $loyalty_point->point_conversion_type == 'credit' ? '+' : '-' }}
                                {{ $loyalty_point->point }} <br>
                                @if ($loyalty_point->point_conversion_type == 'credit')
                                    <span type="button"
                                        class="btn px-3 fs-12 py-1 badge-soft-success">{{ translate('credit') }}</span>
                                @else
                                    <span type="button"
                                        class="btn px-3 fs-12 py-1 badge-soft-danger">{{ translate('debit') }}</span>
                                @endif
                            </div>
                        </td>
                        <td>
                            {{ $loyalty_point->reference ?? translate('N/A') }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>