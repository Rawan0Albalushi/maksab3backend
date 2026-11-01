<?php

namespace App\Support\Notification\Fcm;

use App\Services\System\BusinessSettingService;
use App\Support\Notification\NotificationConfig;
use Illuminate\Support\Facades\Log;

class WebPushServiceWorker
{
    public static function generate(): void
    {
        $config = (array) app(BusinessSettingService::class)->value('fcm_credentials');

        $apiKey            = self::js($config['apiKey'] ?? '');
        $authDomain        = self::js($config['authDomain'] ?? '');
        $projectId         = self::js($config['projectId'] ?? '');
        $storageBucket     = self::js($config['storageBucket'] ?? '');
        $messagingSenderId = self::js($config['messagingSenderId'] ?? '');
        $appId             = self::js($config['appId'] ?? '');
        $measurementId     = self::js($config['measurementId'] ?? '');

        $filePath = base_path('firebase-messaging-sw.js');

        try {
            if (file_exists($filePath) && !is_writable($filePath)) {
                if (!chmod($filePath, 0644)) {
                    throw new \Exception('File is not writable and permission change failed: ' . $filePath);
                }
            }

            $fileContent = <<<JS
                importScripts("https://www.gstatic.com/firebasejs/10.12.0/firebase-app-compat.js");
                importScripts("https://www.gstatic.com/firebasejs/10.12.0/firebase-messaging-compat.js");

                firebase.initializeApp({
                    apiKey: $apiKey,
                    authDomain: $authDomain,
                    projectId: $projectId,
                    storageBucket: $storageBucket,
                    messagingSenderId: $messagingSenderId,
                    appId: $appId,
                    measurementId: $measurementId
                });

                const messaging = firebase.messaging();

                messaging.onBackgroundMessage((payload) => {
                    const data = payload.data || {};
                    const title = data.title || (payload.notification && payload.notification.title) || "Notification";
                    const body  = data.body  || (payload.notification && payload.notification.body)  || "";
                    const image = data.image || (payload.notification && payload.notification.image) || undefined;

                    self.registration.showNotification(title, {
                        body,
                        icon: image,
                        data,
                    });
                });

                self.addEventListener("notificationclick", (event) => {
                    event.notification.close();
                    const data = event.notification.data || {};
                    const url = resolveTargetUrl(data);

                    event.waitUntil(
                        self.clients
                            .matchAll({ type: "window", includeUncontrolled: true })
                            .then((windowClients) => {
                                const existing = windowClients.find((c) => c.url.startsWith(self.location.origin));
                                if (existing) {
                                    existing.focus();
                                    return existing.navigate(url);
                                }
                                return self.clients.openWindow(url);
                            }),
                    );
                });

                function resolveTargetUrl(data) {
                    const base = self.location.origin;
                    if (data && data.type === "order_status" && data.order_id) {
                        return base + "/profile?page=orders&orderId=" + encodeURIComponent(data.order_id);
                    }
                    if (data && data.type === "message") {
                        return base + "/profile?page=inbox";
                    }
                    return base + "/";
                }
                JS;


            if (file_put_contents($filePath, $fileContent) === false) {
                throw new \Exception('Failed to write to file: ' . $filePath);
            }

        } catch (\Throwable $exception) {
            Log::channel(NotificationConfig::logChannel())->error('web_push.service_worker_write_failed', [
                'path' => $filePath,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private static function js(mixed $value): string
    {
        return json_encode((string) $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
