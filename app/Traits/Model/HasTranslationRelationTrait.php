<?php

namespace App\Traits\Model;

use App\Models\Translation;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasTranslationRelationTrait
{
    public function translations(): MorphMany
    {
        return $this->morphMany(Translation::class, 'translationable');
    }

    protected function translatedAttribute(?string $key, $value)
    {
        if ($key === null) {
            return $value;
        }

        $locale = app()->getLocale();
        $otherLocale = null;

        foreach ($this->translations as $translation) {
            if ($translation['key'] !== $key) {
                continue;
            }

            if ($translation['locale'] === $locale) {
                return $translation['value'];
            }

            $otherLocale ??= $translation['value'];
        }

        return $value === null || $value === '' ? $otherLocale ?? $value : $value;
    }
}
