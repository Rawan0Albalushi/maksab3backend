<?php

namespace App\Support\Notification\Fcm;

class FcmMessage
{
    private ?string $token = null;

    private ?string $topic = null;

    private array $data = [];

    private bool $withNotification = true;

    private bool $withAndroid = true;

    private bool $withApns = true;

    private ?string $androidChannel = null;

    private ?string $title = null;

    private ?string $body = null;

    private ?string $image = null;

    public static function toDevice(string $token): self
    {
        $message = new self;
        $message->token = $token;

        return $message;
    }

    public static function toTopic(string $topic): self
    {
        $message = new self;
        $message->topic = $topic;

        return $message;
    }

    public function alert(?string $title, ?string $body, ?string $image = null): self
    {
        $this->title = (string) $title;
        $this->body = (string) $body;
        $this->image = $image === null ? null : (string) $image;

        return $this;
    }

    public function data(array $data): self
    {
        foreach ($data as $key => $value) {
            $this->data[$key] = (string) $value;
        }

        return $this;
    }


    public function dataOnly(): self
    {
        $this->withNotification = false;
        $this->withAndroid = false;
        $this->withApns = false;

        return $this;
    }

    public function toArray(): array
    {
        $message = [];

        if ($this->token !== null) {
            $message['token'] = $this->token;
        }

        if ($this->topic !== null) {
            $message['topic'] = $this->topic;
        }

        $message['data'] = $this->data;

        if ($this->withNotification) {
            $notification = [
                'title' => (string) $this->title,
                'body' => (string) $this->body,
            ];

            if ($this->image !== null) {
                $notification['image'] = $this->image;
            }

            $message['notification'] = $notification;
        }

        if ($this->withAndroid) {
            $message['android'] = [
                'notification' => [
                    'channelId' => $this->androidChannel ?? (string) config('notification.fcm.android_channel', '6ammart'),
                ],
            ];
        }

        if ($this->withApns) {
            $message['apns'] = [
                'payload' => [
                    'aps' => [
                        'sound' => (string) config('notification.fcm.sound', 'notification.wav'),
                    ],
                ],
            ];
        }

        return ['message' => $message];
    }

    public function isBroadcast(): bool
    {
        return $this->topic !== null;
    }

    public function target(): ?string
    {
        return $this->token ?? $this->topic;
    }

}
