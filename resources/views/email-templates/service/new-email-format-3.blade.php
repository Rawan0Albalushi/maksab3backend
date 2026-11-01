<!DOCTYPE html>
<?php
$lang = \App\CentralLogics\Helpers::system_default_language();
$site_direction = \App\CentralLogics\Helpers::system_default_direction();
?>
<html lang="{{ $lang }}" class="{{ $site_direction === 'rtl' ? 'active' : '' }}">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ translate('Email template') }}</title>

    @include('email-templates.partials._style-order')

</head>


<body style="background-color: #e9ecef;padding:15px">

    <table dir="{{ $site_direction }}" class="main-table">
        <tbody>
            <tr>
                <td class="main-table-td">
                    <h2 class="mb-3" id="mail-title">{{ $title ?? translate('Main title or subject of the mail') }}
                    </h2>
                    <div class="mb-1" id="mail-body">{!! $body ?? translate('Hi sabrina,') !!}</div>
                    <span class="d-block text-center mb-3">
                        @if ($data?->button_url)
                            <a type="button" href="{{ $data['button_url'] ?? '#' }}" class="cmn-btn"
                                id="mail-button">{{ $data['button_name'] ?? 'Submit' }}</a>
                        @endif
                    </span>
                    <hr>

                    @isset($url)
                        <div class="mb-2">
                            <a href="{{ $url }}" target="_blank">{{ translate('Download invoice') }}</a>
                        </div>
                    @endisset

                    <div class="mb-2" id="mail-footer">
                        {{ $footer_text ?? 'Please contact us for any queries, we’re always happy to help. ' }}
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
