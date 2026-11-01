<?php

namespace App\Traits\System;

use Exception;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use Illuminate\Http\RedirectResponse;
use Illuminate\Foundation\Application;
use App\Services\Payment\PaymentRequestService;
use App\Services\System\BusinessSettingService;
use App\Support\Storage\FileStorage;

trait  ProcessorTrait
{
    public function responseFormatter($constant, $content = null, $errors = []): array
    {
        $constant = (array)$constant;
        $constant['content'] = $content;
        $constant['errors'] = $errors;
        return $constant;
    }

    public function errorProcessor($validator): array
    {
        $errors = [];
        foreach ($validator->errors()->getMessages() as $index => $error) {
            $errors[] = ['error_code' => $index, 'message' => self::translate($error[0])];
        }
        return $errors;
    }

    public function translate($key)
    {
        try {
            App::setLocale('en');
            $path = base_path('resources/lang/' . 'en' . '/lang.php');
            // include() on a missing file returns false, which would make
            // array_key_exists() below raise a TypeError that this catch
            // block (Exception only) would not absorb.
            $lang_array = file_exists($path) ? include($path) : [];
            if (! is_array($lang_array)) {
                $lang_array = [];
            }
            $processed_key = ucfirst(str_replace('_', ' ', str_ireplace(['\'', '"', ',', ';', '<', '>', '?'], ' ', $key)));
            if (!array_key_exists($key, $lang_array)) {
                // Same guard as translate() in app/helpers.php: validation
                // messages arrive here verbatim, so never persist junk.
                if (function_exists('isPersistableTranslationKey') && ! isPersistableTranslationKey($key)) {
                    return $processed_key;
                }

                $lang_array[$key] = $processed_key;
                $str = "<?php return " . var_export($lang_array, true) . ";";
                file_put_contents($path, $str);
                $result = $processed_key;
            } else {
                $result = __('lang.' . $key);
            }
            return $result;
        } catch (\Throwable $exception) {
            return $key;
        }
    }

    public function paymentConfig($key, $settings_type): object|null
    {
        try {
            $config = DB::table('addon_settings')->where('key_name', $key)
                ->where('settings_type', $settings_type)->first();
        } catch (Exception $exception) {
            return new Setting();
        }

        return (isset($config)) ? $config : null;
    }
    public static function getDisk()
    {
        $config=app(BusinessSettingService::class)->value('local_storage');

        return isset($config)?($config==0?'s3':'public'):'public';
    }
    public function uploadFile(string $dir, string $format, $image = null, $old_image = null)
    {
        return FileStorage::update($dir, $old_image, $image);
    }

    public function paymentResponse($payment_info, $payment_flag): Application|JsonResponse|Redirector|RedirectResponse|\Illuminate\Contracts\Foundation\Application
    {
        $payment_info = app(PaymentRequestService::class)->find($payment_info->id);
        $token_string = 'payment_method=' . $payment_info->payment_method . '&&attribute_id=' . $payment_info->attribute_id . '&&transaction_reference=' . $payment_info->transaction_id;
        if (in_array($payment_info->payment_platform, ['web', 'app']) && $payment_info['external_redirect_link'] != null) {
            return redirect($payment_info['external_redirect_link'] . '?flag=' . $payment_flag . '&&token=' . base64_encode($token_string));
        }
        return redirect()->route('payment-' . $payment_flag, ['token' => base64_encode($token_string)]);
    }
}
