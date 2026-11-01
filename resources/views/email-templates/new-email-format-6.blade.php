<!DOCTYPE html>
<?php
    $lang = \App\CentralLogics\Helpers::system_default_language();
    $site_direction = \App\CentralLogics\Helpers::system_default_direction();
?>
<html lang="{{ $lang }}" class="{{ $site_direction === 'rtl'?'active':'' }}">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ translate('Email template') }}</title>

    @include('email-templates.partials._style-transaction')

</head>


<body style="background-color: #e9ecef;padding:15px">

    <table dir="{{ $site_direction }}" class="main-table">
        <tbody>
            <tr>
                <td class="main-table-td">
                    <div class="text-center">
                    <img class="mail-img-2"
                    src="{{ $data['icon_full_url'] ?? asset('/public/assets/admin/img/blank3.png') }}"


                    id="iconViewer" alt="">
                        <h2 id="mail-title" class="mt-2">{{ $title?? translate('Main title or subject of the mail') }}</h2>
                        <div class="mb-2" id="mail-body">{!! $body?? translate('Hi sabrina,') !!}</div>
                    </div>
                    @isset($transaction_id)
                    <table class="bg-section p-10 w-100 text-center">
                        <thead>
                            <tr>
                                <th>{{ translate('messages.SL') }}</th>
                                <th>{{ translate('messages.Transaction ID') }}</th>
                                <th>{{ translate('messages.Time') }}</th>
                                <th>{{ translate('Amount') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>1</td>
                                <td>{{$transaction_id}}</td>
                                <td>{{$time}}</td>
                                <td>{{\App\CentralLogics\Helpers::format_currency($amount)}}</td>
                            </tr>
                        </tbody>
                    </table>
                    @endisset
                    @if ($data?->button_url)
                    <span class="d-block text-center" style="margin-top: 16px">
                                        <a type="button" href="{{ $data['button_url']??'#' }}" class="cmn-btn" id="mail-button">{{ $data['button_name']??'Submit' }}</a>

                    </span>
                    @endif
                    <hr>
                    <div class="mb-2" id="mail-footer">
                        {{ $footer_text ?? translate('Please contact us for any queries; we\'re always happy to help.') }}
                    </div>
                    <div>
                        {{ translate('Thanks & regards') }},
                    </div>
                    <div class="mb-4">
                        {{ $company_name }}
                    </div>
                </td>
            </tr>
            <tr>
                <td>
                    @include('email-templates.partials._footer')
                </td>
            </tr>
        </tbody>
    </table>


</body>
</html>
