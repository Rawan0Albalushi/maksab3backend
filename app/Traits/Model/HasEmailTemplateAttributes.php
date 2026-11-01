<?php

namespace App\Traits\Model;


trait HasEmailTemplateAttributes
{
    private const STORAGE_KEYS = ['image', 'logo', 'icon'];

    public static function bootHasEmailTemplateAttributes(): void
    {
        static::saved(function ($model) {
            foreach (self::STORAGE_KEYS as $key) {
                self::recordStorageDisk($model, $key, $key);
            }
        });
    }

    public function getImageFullUrlAttribute()
    {
        return $this->storageFullUrl('email_template', 'image', $this->image);
    }

    public function getLogoFullUrlAttribute()
    {
        return $this->storageFullUrl('email_template', 'logo', $this->logo);
    }

    public function getIconFullUrlAttribute()
    {
        return $this->storageFullUrl('email_template', 'icon', $this->icon);
    }

    public function getTitleAttribute($value)
    {
        return $this->translatedAttribute('title', $value);
    }

    public function getBodyAttribute($value)
    {
        return $this->translatedAttribute('body', $value);
    }

    public function getButtonNameAttribute($value)
    {
        return $this->translatedAttribute('button_name', $value);
    }

    public function getFooterTextAttribute($value)
    {
        return $this->translatedAttribute('footer_text', $value);
    }

    public function getCopyrightTextAttribute($value)
    {
        return $this->translatedAttribute('copyright_text', $value);
    }
}
