<?php

namespace App\Traits\Model;

use Illuminate\Database\Eloquent\Builder;

trait HasTranslationsTrait
{
    use HasTranslationRelationTrait;

    public static function bootHasTranslationsTrait(): void
    {
        static::addGlobalScope('translate', function (Builder $builder) {
            $builder->with(['translations' => function ($query) {
                $query->where('locale', app()->getLocale());
            }]);
        });
    }

    public function scopeTranslateOnly(Builder $query, array|string $keys): Builder
    {
        $keys = (array) $keys;

        return $query->withoutGlobalScope('translate')
            ->with(['translations' => function ($query) use ($keys) {
                $query->select('translationable_id', 'translationable_type', 'key', 'locale', 'value')
                    ->whereIn('key', $keys)
                    ->where('locale', app()->getLocale());
            }]);
    }

    public function scopeWithoutTranslation(Builder $query): Builder
    {
        return $query->withoutGlobalScope('translate');
    }

    /**
     * Edit forms need every locale, not just the current one, so they drop the 'translate'
     * scope — which also drops the eager load it carried, leaving ->translations to lazy-load
     * per row. This drops only the locale filter and keeps the relation loaded.
     */
    public function scopeWithAllTranslations(Builder $query): Builder
    {
        return $query->withoutGlobalScope('translate')->with('translations');
    }
}
