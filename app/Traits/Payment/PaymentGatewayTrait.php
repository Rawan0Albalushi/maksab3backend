<?php

namespace App\Traits\Payment;


trait PaymentGatewayTrait
{
    public static function getPaymentGatewaySupportedCurrencies($key = null): array
    {
        $paymentGateway = [
            "amazon_pay" => [
                "USD" => "United States Dollar",
                "GBP" => "Pound Sterling",
                "EUR" => "Euro",
                "JPY" => "Japanese Yen",
                "AUD" => "Australian Dollar",
                "NZD" => "New Zealand Dollar",
                "CAD" => "Canadian Dollar",
                "DKK" => "Danish Krone",
                "HKD" => "Hong Kong Dollar",
                "SEK" => "Swedish Krona",
                "CHF" => "Swiss Franc",
                "NOK" => "Norwegian Krone",
                "ZAR" => "South African Rand"
            ],
            "bkash" => [
                "BDT" => "Bangladeshi Taka"
            ],
            "cashfree" => [
                "INR" => "Indian Rupee"
            ],
            "ccavenue" => [
                "INR" => "Indian Rupee",
                "USD" => "United States Dollar",
                "EUR" => "Euro",
                "GBP" => "Pound Sterling",
                "AED" => "United Arab Emirates Dirham",
                "AUD" => "Australian Dollar",
                "BDT" => "Bangladeshi Taka",
                "BHD" => "Bahraini Dinar",
                "CAD" => "Canadian Dollar",
                "CHF" => "Swiss Franc",
                "CNY" => "Chinese Yuan",
                "HKD" => "Hong Kong Dollar",
                "JPY" => "Japanese Yen",
                "KES" => "Kenyan Shilling",
                "KWD" => "Kuwaiti Dinar",
                "LKR" => "Sri Lankan Rupee",
                "MUR" => "Mauritian Rupee",
                "MYR" => "Malaysian Ringgit",
                "NPR" => "Nepalese Rupee",
                "NZD" => "New Zealand Dollar",
                "OMR" => "Omani Rial",
                "PHP" => "Philippine Peso",
                "QAR" => "Qatari Riyal",
                "SAR" => "Saudi Riyal",
                "SGD" => "Singapore Dollar",
                "THB" => "Thai Baht",
                "ZAR" => "South African Rand"
            ],
            "esewa" => [
                "NPR" => "Nepalese Rupee"
            ],
            "fatoorah" => [
                "KWD" => "Kuwaiti Dinar",
                "SAR" => "Saudi Riyal",
                "AED" => "United Arab Emirates Dirham",
                "BHD" => "Bahraini Dinar",
                "QAR" => "Qatari Riyal",
                "OMR" => "Omani Rial",
                "EGP" => "Egyptian Pound",
                "USD" => "United States Dollar"
            ],
            "flutterwave" => [
                "NGN" => "Nigerian Naira",
                "GHS" => "Ghanaian Cedi",
                "KES" => "Kenyan Shilling",
                "ZAR" => "South African Rand",
                "USD" => "United States Dollar",
                "EUR" => "Euro",
                "GBP" => "Pound Sterling",
                "CAD" => "Canadian Dollar",
                "RWF" => "Rwandan Franc",
                "UGX" => "Ugandan Shilling",
                "TZS" => "Tanzanian Shilling",
                "ZMW" => "Zambian Kwacha",
                "XAF" => "Central African CFA Franc",
                "XOF" => "West African CFA Franc",
                "EGP" => "Egyptian Pound",
                "COP" => "Colombian Peso",
                "SLL" => "Sierra Leonean Leone"
            ],
            "foloosi" => [
                "AED" => "United Arab Emirates Dirham",
                "SAR" => "Saudi Riyal",
                "USD" => "United States Dollar",
                "EUR" => "Euro",
                "INR" => "Indian Rupee"
            ],
            "hubtel" => [
                "GHS" => "Ghanaian Cedi"
            ],
            "hyper_pay" => [
                "AED" => "United Arab Emirates Dirham",
                "SAR" => "Saudi Riyal",
                "EGP" => "Egyptian Pound",
                "BHD" => "Bahraini Dinar",
                "KWD" => "Kuwaiti Dinar",
                "OMR" => "Omani Rial",
                "QAR" => "Qatari Riyal",
                "USD" => "United States Dollar",
                "EUR" => "Euro",
                "GBP" => "Pound Sterling",
                "JOD" => "Jordanian Dinar",
                "LBP" => "Lebanese Pound"
            ],
            "instamojo" => [
                "INR" => "Indian Rupee"
            ],
            "iyzi_pay" => [
                "TRY" => "Turkish Lira",
                "EUR" => "Euro",
                "USD" => "United States Dollar",
                "GBP" => "Pound Sterling",
                "CHF" => "Swiss Franc",
                "NOK" => "Norwegian Krone",
                "RUB" => "Russian Ruble"
            ],
            "liqpay" => [
                "UAH" => "Ukrainian Hryvnia",
                "USD" => "United States Dollar",
                "EUR" => "Euro",
                "RUB" => "Russian Ruble",
                "BYN" => "Belarusian Ruble",
                "KZT" => "Kazakhstani Tenge"
            ],
            "maxicash" => [
                "PHP" => "Philippine Peso",
                "USD" => "United States Dollar",
                "ZAR" => "South African Rand"
            ],
            "mercadopago" => [
                "ARS" => "Argentine Peso",
                "BRL" => "Brazilian Real",
                "CLP" => "Chilean Peso",
                "COP" => "Colombian Peso",
                "MXN" => "Mexican Peso",
                "PEN" => "Peruvian Sol",
                "UYU" => "Uruguayan Peso",
                "USD" => "United States Dollar",
                "PYG" => "Paraguayan Guarani",
                "BOB" => "Bolivian Boliviano"
            ],
            "momo" => [
                "VND" => "Vietnamese Dong"
            ],
            "moncash" => [
                "HTG" => "Haitian Gourde",
                "USD" => "United States Dollar"
            ],
            "payfast" => [
                "ZAR" => "South African Rand",
                "USD" => "United States Dollar",
                "EUR" => "Euro",
                "GBP" => "Pound Sterling"
            ],
            "paymob_accept" => [
                "EGP" => "Egyptian Pound",
                "AED" => "United Arab Emirates Dirham",
                "SAR" => "Saudi Riyal",
                "USD" => "United States Dollar",
                "EUR" => "Euro",
                "OMR" => "Omani Rial",
                "PKR" => "Pakistani Rupee"
            ],
            "paypal" => [
                "AUD" => "Australian Dollar",
                "BRL" => "Brazilian Real",
                "CAD" => "Canadian Dollar",
                "CZK" => "Czech Koruna",
                "DKK" => "Danish Krone",
                "EUR" => "Euro",
                "HKD" => "Hong Kong Dollar",
                "HUF" => "Hungarian Forint",
                "INR" => "Indian Rupee",
                "ILS" => "Israeli New Shekel",
                "JPY" => "Japanese Yen",
                "MYR" => "Malaysian Ringgit",
                "MXN" => "Mexican Peso",
                "TWD" => "New Taiwan Dollar",
                "NZD" => "New Zealand Dollar",
                "NOK" => "Norwegian Krone",
                "PHP" => "Philippine Peso",
                "PLN" => "Polish Zloty",
                "GBP" => "Pound Sterling",
                "RUB" => "Russian Ruble",
                "SGD" => "Singapore Dollar",
                "SEK" => "Swedish Krona",
                "CHF" => "Swiss Franc",
                "THB" => "Thai Baht",
                "TRY" => "Turkish Lira",
                "USD" => "United States Dollar",
                "AED" => "United Arab Emirates Dirham",
                "SAR" => "Saudi Riyal",
                "ZAR" => "South African Rand",
                "CNY" => "Chinese Yuan"
            ],
            "paystack" => [
                "NGN" => "Nigerian Naira",
                "KES" => "Kenyan Shilling",
                "GHS" => "Ghanaian Cedi",
                "ZAR" => "South African Rand",
                "XOF" => "West African CFA Franc",
                "USD" => "United States Dollar",
                "EUR" => "Euro",
                "GBP" => "Pound Sterling",
                "RWF" => "Rwandan Franc",
                "EGP" => "Egyptian Pound"
            ],
            "paytabs" => [
                "AED" => "United Arab Emirates Dirham",
                "SAR" => "Saudi Riyal",
                "BHD" => "Bahraini Dinar",
                "KWD" => "Kuwaiti Dinar",
                "OMR" => "Omani Rial",
                "QAR" => "Qatari Riyal",
                "EGP" => "Egyptian Pound",
                "USD" => "United States Dollar",
                "EUR" => "Euro",
                "GBP" => "Pound Sterling",
                "JOD" => "Jordanian Dinar",
                "LBP" => "Lebanese Pound"
            ],
            "paytm" => [
                "INR" => "Indian Rupee"
            ],
            "phonepe" => [
                "INR" => "Indian Rupee"
            ],
            "pvit" => [
                "NGN" => "Nigerian Naira",
                "XAF" => "Central African CFA Franc"
            ],
            "razor_pay" => [
                "INR" => "Indian Rupee",
                "USD" => "United States Dollar",
                "EUR" => "Euro",
                "GBP" => "Pound Sterling",
                "SGD" => "Singapore Dollar",
                "AED" => "United Arab Emirates Dirham",
                "AUD" => "Australian Dollar",
                "CAD" => "Canadian Dollar",
                "JPY" => "Japanese Yen"
            ],
            "senang_pay" => [
                "MYR" => "Malaysian Ringgit"
            ],
            "sixcash" => [
                "BDT" => "Bangladeshi Taka"
            ],
            "ssl_commerz" => [
                "BDT" => "Bangladeshi Taka",
                "USD" => "United States Dollar",
                "EUR" => "Euro",
                "GBP" => "Pound Sterling",
                "CAD" => "Canadian Dollar",
                "AUD" => "Australian Dollar",
                "SGD" => "Singapore Dollar",
                "INR" => "Indian Rupee",
                "MYR" => "Malaysian Ringgit"
            ],
            "stripe" => [
                "USD" => "United States Dollar",
                "EUR" => "Euro",
                "GBP" => "Pound Sterling",
                "JPY" => "Japanese Yen",
                "CNY" => "Chinese Yuan",
                "AUD" => "Australian Dollar",
                "CAD" => "Canadian Dollar",
                "CHF" => "Swiss Franc",
                "SEK" => "Swedish Krona",
                "NOK" => "Norwegian Krone",
                "DKK" => "Danish Krone",
                "PLN" => "Polish Zloty",
                "CZK" => "Czech Koruna",
                "HUF" => "Hungarian Forint",
                "RON" => "Romanian Leu",
                "BGN" => "Bulgarian Lev",
                "ISK" => "Icelandic Krona",
                "HKD" => "Hong Kong Dollar",
                "SGD" => "Singapore Dollar",
                "NZD" => "New Zealand Dollar",
                "KRW" => "South Korean Won",
                "TWD" => "New Taiwan Dollar",
                "THB" => "Thai Baht",
                "MYR" => "Malaysian Ringgit",
                "PHP" => "Philippine Peso",
                "IDR" => "Indonesian Rupiah",
                "VND" => "Vietnamese Dong",
                "INR" => "Indian Rupee",
                "PKR" => "Pakistani Rupee",
                "BDT" => "Bangladeshi Taka",
                "LKR" => "Sri Lankan Rupee",
                "NPR" => "Nepalese Rupee",
                "MMK" => "Myanmar Kyat",
                "KHR" => "Cambodian Riel",
                "LAK" => "Lao Kip",
                "MNT" => "Mongolian Tugrik",
                "MXN" => "Mexican Peso",
                "BRL" => "Brazilian Real",
                "ARS" => "Argentine Peso",
                "CLP" => "Chilean Peso",
                "COP" => "Colombian Peso",
                "PEN" => "Peruvian Sol",
                "UYU" => "Uruguayan Peso",
                "DOP" => "Dominican Peso",
                "GTQ" => "Guatemalan Quetzal",
                "CRC" => "Costa Rican Colon",
                "PAB" => "Panamanian Balboa",
                "JMD" => "Jamaican Dollar",
                "TTD" => "Trinidad and Tobago Dollar",
                "AED" => "United Arab Emirates Dirham",
                "SAR" => "Saudi Riyal",
                "QAR" => "Qatari Riyal",
                "KWD" => "Kuwaiti Dinar",
                "BHD" => "Bahraini Dinar",
                "OMR" => "Omani Rial",
                "JOD" => "Jordanian Dinar",
                "LBP" => "Lebanese Pound",
                "ILS" => "Israeli New Shekel",
                "EGP" => "Egyptian Pound",
                "ZAR" => "South African Rand",
                "NGN" => "Nigerian Naira",
                "KES" => "Kenyan Shilling",
                "GHS" => "Ghanaian Cedi",
                "TZS" => "Tanzanian Shilling",
                "UGX" => "Ugandan Shilling",
                "MAD" => "Moroccan Dirham",
                "TND" => "Tunisian Dinar",
                "TRY" => "Turkish Lira",
                "RUB" => "Russian Ruble",
                "UAH" => "Ukrainian Hryvnia",
                "GEL" => "Georgian Lari",
                "AMD" => "Armenian Dram",
                "AZN" => "Azerbaijani Manat",
                "KZT" => "Kazakhstani Tenge",
                "UZS" => "Uzbekistani Som",
                "BWP" => "Botswana Pula",
                "MUR" => "Mauritian Rupee",
                "SCR" => "Seychellois Rupee",
                "XOF" => "West African CFA Franc",
                "XAF" => "Central African CFA Franc"
            ],
            "swish" => [
                "SEK" => "Swedish Krona"
            ],
            "tap" => [
                "AED" => "United Arab Emirates Dirham",
                "SAR" => "Saudi Riyal",
                "BHD" => "Bahraini Dinar",
                "KWD" => "Kuwaiti Dinar",
                "OMR" => "Omani Rial",
                "QAR" => "Qatari Riyal",
                "USD" => "United States Dollar",
                "EUR" => "Euro",
                "GBP" => "Pound Sterling",
                "JOD" => "Jordanian Dinar",
                "EGP" => "Egyptian Pound"
            ],
            "thawani" => [
                "OMR" => "Omani Rial",
                "AED" => "United Arab Emirates Dirham",
                "USD" => "United States Dollar",
                "EUR" => "Euro",
                "SAR" => "Saudi Riyal",
                "QAR" => "Qatari Riyal",
                "BHD" => "Bahraini Dinar",
                "KWD" => "Kuwaiti Dinar"
            ],
            "viva_wallet" => [
                "EUR" => "Euro",
                "GBP" => "Pound Sterling",
                "BGN" => "Bulgarian Lev",
                "RON" => "Romanian Leu",
                "PLN" => "Polish Zloty",
                "CZK" => "Czech Koruna",
                "HUF" => "Hungarian Forint",
                "DKK" => "Danish Krone",
                "SEK" => "Swedish Krona"
            ],
            "worldpay" => [
                "GBP" => "Pound Sterling",
                "USD" => "United States Dollar",
                "EUR" => "Euro",
                "JPY" => "Japanese Yen",
                "CAD" => "Canadian Dollar",
                "AUD" => "Australian Dollar",
                "NZD" => "New Zealand Dollar",
                "HKD" => "Hong Kong Dollar",
                "SGD" => "Singapore Dollar"
            ],
            "xendit" => [
                "IDR" => "Indonesian Rupiah",
                "PHP" => "Philippine Peso",
                "VND" => "Vietnamese Dong",
                "THB" => "Thai Baht",
                "MYR" => "Malaysian Ringgit",
                "SGD" => "Singapore Dollar",
                "USD" => "United States Dollar",
                "EUR" => "Euro",
                "GBP" => "Pound Sterling",
                "HKD" => "Hong Kong Dollar",
                "AUD" => "Australian Dollar"
            ],
            "dlocal" => [
                "ARS" => "Argentine Peso",
                "BRL" => "Brazilian Real",
                "CLP" => "Chilean Peso",
                "COP" => "Colombian Peso",
                "MXN" => "Mexican Peso",
                "PEN" => "Peruvian Sol",
                "UYU" => "Uruguayan Peso",
                "EGP" => "Egyptian Pound",
                "INR" => "Indian Rupee",
                "NGN" => "Nigerian Naira",
                "ZAR" => "South African Rand"
            ],
            "mollie" => [
                "EUR" => "Euro",
                "GBP" => "Pound Sterling",
                "USD" => "United States Dollar",
                "CAD" => "Canadian Dollar",
                "AUD" => "Australian Dollar",
                "NZD" => "New Zealand Dollar",
                "CHF" => "Swiss Franc",
                "DKK" => "Danish Krone",
                "NOK" => "Norwegian Krone",
                "SEK" => "Swedish Krona",
                "PLN" => "Polish Zloty",
                "CZK" => "Czech Koruna",
                "HUF" => "Hungarian Forint"
            ],
            "cinetpay" => [
                "XOF" => "West African CFA franc",
                "XAF" => "Central African CFA franc",
                "GNF" => "Guinean franc",
                "CDF" => "Congolese franc",
                "USD" => "United States Dollar"
            ],
            "mercadopago_pix" => [
                "BRL" => "Brazilian Real"
            ],
            "adyen" => [
                "USD" => "United States Dollar",
                "EUR" => "Euro",
                "GBP" => "Pound Sterling",
                "AUD" => "Australian Dollar",
                "CAD" => "Canadian Dollar",
                "JPY" => "Japanese Yen",
                "CHF" => "Swiss Franc",
                "CNY" => "Chinese Yuan",
                "HKD" => "Hong Kong Dollar",
                "SGD" => "Singapore Dollar",
                "SEK" => "Swedish Krona",
                "NOK" => "Norwegian Krone",
                "DKK" => "Danish Krone",
                "PLN" => "Polish Zloty",
                "NZD" => "New Zealand Dollar",
                "INR" => "Indian Rupee",
                "BRL" => "Brazilian Real",
                "MXN" => "Mexican Peso",
                "ZAR" => "South African Rand",
                "AED" => "United Arab Emirates Dirham"
            ],
            "checkout" => [
                "USD" => "United States Dollar",
                "EUR" => "Euro",
                "GBP" => "Pound Sterling",
                "AED" => "United Arab Emirates Dirham",
                "SAR" => "Saudi Riyal",
                "AUD" => "Australian Dollar",
                "CAD" => "Canadian Dollar",
                "CHF" => "Swiss Franc",
                "HKD" => "Hong Kong Dollar",
                "JPY" => "Japanese Yen",
                "SGD" => "Singapore Dollar",
                "SEK" => "Swedish Krona",
                "NOK" => "Norwegian Krone",
                "DKK" => "Danish Krone",
                "NZD" => "New Zealand Dollar",
                "PLN" => "Polish Zloty",
                "QAR" => "Qatari Riyal",
                "KWD" => "Kuwaiti Dinar",
                "BHD" => "Bahraini Dinar",
                "OMR" => "Omani Rial"
            ],
            "braintree" => [
                "USD" => "United States Dollar",
                "EUR" => "Euro",
                "GBP" => "Pound Sterling",
                "AUD" => "Australian Dollar",
                "CAD" => "Canadian Dollar",
                "JPY" => "Japanese Yen",
                "CHF" => "Swiss Franc",
                "HKD" => "Hong Kong Dollar",
                "NZD" => "New Zealand Dollar",
                "SGD" => "Singapore Dollar",
                "SEK" => "Swedish Krona",
                "NOK" => "Norwegian Krone",
                "DKK" => "Danish Krone",
                "PLN" => "Polish Zloty",
                "CZK" => "Czech Koruna",
                "HUF" => "Hungarian Forint",
                "ILS" => "Israeli New Shekel",
                "MXN" => "Mexican Peso",
                "MYR" => "Malaysian Ringgit",
                "PHP" => "Philippine Peso",
                "TWD" => "New Taiwan Dollar",
                "THB" => "Thai Baht",
                "TRY" => "Turkish Lira"
            ],
            "square" => [
                "USD" => "United States Dollar",
                "CAD" => "Canadian Dollar",
                "GBP" => "Pound Sterling",
                "AUD" => "Australian Dollar",
                "JPY" => "Japanese Yen",
                "EUR" => "Euro"
            ],
            "authorize_net" => [
                "USD" => "United States Dollar",
                "CAD" => "Canadian Dollar",
                "GBP" => "Pound Sterling",
                "EUR" => "Euro",
                "AUD" => "Australian Dollar",
                "NZD" => "New Zealand Dollar"
            ],
            "2c2p" => [
                "SGD" => "Singapore Dollar",
                "THB" => "Thai Baht",
                "MYR" => "Malaysian Ringgit",
                "IDR" => "Indonesian Rupiah",
                "PHP" => "Philippine Peso",
                "VND" => "Vietnamese Dong",
                "HKD" => "Hong Kong Dollar",
                "USD" => "United States Dollar"
            ],
            "gocardless" => [
                "GBP" => "Pound Sterling",
                "EUR" => "Euro",
                "USD" => "United States Dollar",
                "AUD" => "Australian Dollar",
                "CAD" => "Canadian Dollar",
                "NZD" => "New Zealand Dollar",
                "SEK" => "Swedish Krona",
                "DKK" => "Danish Krone"
            ],
            "payu" => [
                "INR" => "Indian Rupee",
                "PLN" => "Polish Zloty",
                "USD" => "United States Dollar",
                "EUR" => "Euro",
                "TRY" => "Turkish Lira",
                "ZAR" => "South African Rand",
                "RON" => "Romanian Leu",
                "CZK" => "Czech Koruna",
                "HUF" => "Hungarian Forint"
            ],
            "vnpay" => [
                "VND" => "Vietnamese Dong"
            ],
            "omise" => [
                "THB" => "Thai Baht",
                "JPY" => "Japanese Yen",
                "SGD" => "Singapore Dollar",
                "MYR" => "Malaysian Ringgit",
                "USD" => "United States Dollar"
            ],
            "paypay" => [
                "JPY" => "Japanese Yen"
            ],
            "moyasar" => [
                "SAR" => "Saudi Riyal",
                "USD" => "United States Dollar",
                "EUR" => "Euro",
                "GBP" => "Pound Sterling",
                "AED" => "United Arab Emirates Dirham",
                "KWD" => "Kuwaiti Dinar",
                "BHD" => "Bahraini Dinar",
                "OMR" => "Omani Rial",
                "QAR" => "Qatari Riyal"
            ],
            "kashier" => [
                "EGP" => "Egyptian Pound",
                "USD" => "United States Dollar"
            ],
            "opay" => [
                "NGN" => "Nigerian Naira"
            ],
            "pawapay" => [
                "ZMW" => "Zambian Kwacha",
                "TZS" => "Tanzanian Shilling",
                "UGX" => "Ugandan Shilling",
                "RWF" => "Rwandan Franc",
                "GHS" => "Ghanaian Cedi",
                "KES" => "Kenyan Shilling",
                "XOF" => "West African CFA Franc",
                "XAF" => "Central African CFA Franc",
                "CDF" => "Congolese Franc",
                "MWK" => "Malawian Kwacha",
                "NGN" => "Nigerian Naira",
                "MZN" => "Mozambican Metical",
                "SLE" => "Sierra Leonean Leone"
            ],
            "conekta" => [
                "MXN" => "Mexican Peso"
            ],
            "openpay" => [
                "MXN" => "Mexican Peso",
                "COP" => "Colombian Peso",
                "PEN" => "Peruvian Sol"
            ],
            "line_pay" => [
                "JPY" => "Japanese Yen",
                "TWD" => "New Taiwan Dollar",
                "THB" => "Thai Baht"
            ]
        ];

        if ($key) {
            return $paymentGateway[$key] ?? [];
        }
        return $paymentGateway;
    }

}
