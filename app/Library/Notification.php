<?php

use App\Models\BusinessSetting;
use Illuminate\Support\Facades\Http;

function sendNotificationToHttp(array|null $data)
{
    $config = null;

    $paymentmethod = BusinessSetting::where('key', 'push_notification_service_file_content')->first();

    if ($paymentmethod) {
        $config = json_decode($paymentmethod->value, true);
    }
    $key = (array)$config;
    if($key['project_id']){
        $url = 'https://fcm.googleapis.com/v1/projects/'.$key['project_id'].'/messages:send';
        $headers = [
            'Authorization' => 'Bearer ' . getAccessToken($key),
            'Content-Type' => 'application/json',
        ];
        try {
            Http::withHeaders($headers)->connectTimeout(5)->timeout(10)->post($url, $data);
        }catch (\Exception $exception){
            return false;
        }
    }
    return false;
}

function getAccessToken($key)
{
    return \App\Support\Notification\Fcm\FcmCredentials::accessToken((array) $key);
}

if (!function_exists('sendDeviceNotification')) {
    function sendDeviceNotification($fcm_token, $title, $description, $status, $image = null, $ride_request_id = null, $type = null, $action = null, $user_id = null, $user_name = null, array $notificationData = []): bool|string
    {
        $imageUrl = $image ? asset("storage/app/public/push-notification/$image") : null;

        $postData = [
            'message' => [
                'token' => $fcm_token,
                'data' => [
                    'title' => (string)$title,
                    'body' => (string)$description,
                    'status' => (string)$status,
                    "ride_request_id" => (string)$ride_request_id,
                    "type" => (string)$type,
                    "user_name" => (string)$user_name,
                    "title_loc_key" => (string)$ride_request_id,
                    "body_loc_key" => (string)$type,
                    "image" => (string)$imageUrl,
                    "action" => (string)$action,
                    "reward_type" => (string)($notificationData['reward_type'] ?? null),
                    "reward_amount" => (string)($notificationData['reward_amount'] ?? 0),
                    "next_level" => (string)($notificationData['next_level'] ?? null),
                    "sound" => "notification.wav",
                    "android_channel_id" => "hexaride"
                ],
                'notification' => [
                    'title' => (string)$title,
                    'body' => (string)$description,
                    "image" => (string)$imageUrl,
                ],
                "android" => [
                    'priority' => 'high',
                    "notification" => [
                        "channel_id" => "hexaride",
                        "sound" => "notification.wav",
                        "icon" => "notification_icon",
                    ]
                ],
                "apns" => [
                    "payload" => [
                        "aps" => [
                            "sound" => "notification.wav"
                        ]
                    ],
                    'headers' => [
                        'apns-priority' => '10',
                    ],
                ],
            ]
        ];
        return sendNotificationToHttp($postData);
    }
}

if (!function_exists('sendTopicNotification')) {
    function sendTopicNotification($topic, $title, $description, $image = null, $ride_request_id = null, $type = null, $sentBy = null, $tripReferenceId = null, $route = null): bool|string
    {

        $image = asset('storage/app/public/push-notification') . '/' . $image;
        $postData = [
            'message' => [
                'topic' => $topic,
                'data' => [
                    'title' => (string)$title,
                    'body' => (string)$description,
                    "ride_request_id" => (string)$ride_request_id,
                    "type" => (string)$type,
                    "title_loc_key" => (string)$ride_request_id,
                    "body_loc_key" => (string)$type,
                    "image" => (string)$image,
                    "sound" => "notification.wav",
                    "android_channel_id" => "hexaride",
                    "sent_by" => (string)$sentBy,
                    "trip_reference_id" => (string)$tripReferenceId,
                    "route" => (string)$route,
                ],
                'notification' => [
                    'title' => (string)$title,
                    'body' => (string)$description,
                    "image" => (string)$image,
                ],
                "android" => [
                    'priority' => 'high',
                    "notification" => [
                        "channelId" => "hexaride"
                    ]
                ],
                "apns" => [
                    "payload" => [
                        "aps" => [
                            "sound" => "notification.wav"
                        ]
                    ],
                    'headers' => [
                        'apns-priority' => '10',
                    ],
                ],
            ]
        ];
        return sendNotificationToHttp($postData);
    }
}