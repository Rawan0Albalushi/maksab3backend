<?php

namespace Tests\Feature;

use App\Models\Bundle;
use App\Models\Store;
use App\Models\Translation;
use App\Support\Promotion\BundleSettings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TranslatedAttributeTest extends TestCase
{
    use DatabaseTransactions;

    private ?Bundle $bundle = null;

    protected function setUp(): void
    {
        parent::setUp();

        $store = Store::withoutGlobalScopes()
            ->whereHas('module', fn ($q) => $q->whereIn('module_type', BundleSettings::moduleTypes()))
            ->first();

        if (! $store) {
            $this->markTestSkipped('no bundle-capable store');
        }

        $this->bundle = Bundle::create([
            'store_id' => $store->id,
            'module_id' => $store->module_id,
            'name' => 'English Name',
            'start_date' => now()->subHour(),
            'end_date' => now()->addWeek(),
        ]);
    }

    public function test_a_row_for_the_current_locale_wins(): void
    {
        $this->translate('en', 'name', 'Locale Name');

        app()->setLocale('en');

        $this->assertSame('Locale Name', $this->reloaded()->name);
    }

    public function test_another_locale_never_shadows_the_default_column(): void
    {
        $this->translate('bn', 'name', 'Bangla Name');

        app()->setLocale('en');

        $this->assertSame('English Name', $this->reloaded()->name,
            'a bn row must not be served to an en caller while the column holds the English text');
    }

    public function test_another_locale_still_fills_an_empty_column(): void
    {
        $this->bundle->forceFill(['name' => ''])->save();
        $this->translate('bn', 'name', 'Bangla Name');

        app()->setLocale('en');

        $this->assertSame('Bangla Name', $this->reloaded()->name,
            'an empty column is worse than another language');
    }

    public function test_the_column_is_served_when_nothing_is_translated(): void
    {
        app()->setLocale('en');

        $this->assertSame('English Name', $this->reloaded()->name);
    }

    public function test_the_current_locale_wins_over_an_earlier_row_for_another_locale(): void
    {
        $this->translate('bn', 'name', 'Bangla Name');
        $this->translate('en', 'name', 'English Translation');

        app()->setLocale('en');

        $this->assertSame('English Translation', $this->reloaded()->name);
    }

    private function reloaded(): Bundle
    {
        return Bundle::withAllTranslations()->find($this->bundle->id);
    }

    private function translate(string $locale, string $key, string $value): void
    {
        Translation::create([
            'translationable_type' => Bundle::class,
            'translationable_id' => $this->bundle->id,
            'locale' => $locale,
            'key' => $key,
            'value' => $value,
        ]);
    }
}
