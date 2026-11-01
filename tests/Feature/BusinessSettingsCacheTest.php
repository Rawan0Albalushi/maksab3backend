<?php

namespace Tests\Feature;

use App\CentralLogics\Helpers;
use App\Models\BusinessSetting;
use Tests\TestCase;

class BusinessSettingsCacheTest extends TestCase
{
    private string $key = 'zz_cache_test_key';

    protected function setUp(): void
    {
        parent::setUp();
        BusinessSetting::where('key', $this->key)->delete();
        Helpers::clearBusinessSettingsCache();
    }

    protected function tearDown(): void
    {
        BusinessSetting::where('key', $this->key)->delete();
        Helpers::clearBusinessSettingsCache();
        parent::tearDown();
    }

    public function test_read_after_model_save_returns_fresh_value_in_same_request(): void
    {
        BusinessSetting::create(['key' => $this->key, 'value' => 'OLD']);

        $this->assertSame('OLD', Helpers::get_business_settings($this->key, false));

        Helpers::businessUpdateOrInsert(['key' => $this->key], ['value' => 'NEW']);

        $this->assertSame('NEW', Helpers::get_business_settings($this->key, false));
    }

    public function test_get_web_config_reflects_a_save_made_in_the_same_request(): void
    {
        BusinessSetting::create(['key' => $this->key, 'value' => 'OLD']);

        $this->assertSame('OLD', getWebConfig($this->key));

        Helpers::businessUpdateOrInsert(['key' => $this->key], ['value' => 'NEW']);

        $this->assertSame('NEW', getWebConfig($this->key));
    }

    public function test_query_builder_update_is_visible_after_an_explicit_flush(): void
    {
        BusinessSetting::create(['key' => $this->key, 'value' => 'OLD']);
        $this->assertSame('OLD', Helpers::get_business_settings($this->key, false));

        BusinessSetting::where('key', $this->key)->update(['value' => 'NEW']);
        Helpers::clearBusinessSettingsCache();

        $this->assertSame('NEW', Helpers::get_business_settings($this->key, false));
    }

    public function test_delete_is_reflected_in_the_same_request(): void
    {
        BusinessSetting::create(['key' => $this->key, 'value' => 'OLD']);
        $this->assertSame('OLD', Helpers::get_business_settings($this->key, false));

        BusinessSetting::where('key', $this->key)->first()->delete();

        $this->assertNull(Helpers::get_business_settings($this->key, false));
    }

    public function test_raw_value_is_returned_unchanged_when_json_decode_is_disabled(): void
    {
        BusinessSetting::create(['key' => $this->key, 'value' => '1']);

        $this->assertSame('1', Helpers::get_business_settings($this->key, false));
    }

    public function test_json_value_is_decoded_when_requested(): void
    {
        BusinessSetting::create(['key' => $this->key, 'value' => '{"a":1}']);

        $this->assertSame(['a' => 1], Helpers::get_business_settings($this->key));
    }

    public function test_missing_key_returns_null_instead_of_throwing(): void
    {
        $this->assertNull(Helpers::get_business_settings('zz_definitely_absent_key', false));
        $this->assertNull(getWebConfig('zz_definitely_absent_key'));
        $this->assertSame(0, getWebConfigStatus('zz_definitely_absent_key'));
    }

    public function test_many_matches_the_batched_pluck_it_replaced(): void
    {
        $keys = ['additional_charge_status', 'additional_charge', 'extra_packaging_data'];

        $viaQuery = BusinessSetting::whereIn('key', $keys)->pluck('value', 'key');
        $viaHelper = Helpers::get_business_settings_many($keys);

        foreach ($keys as $key) {
            $this->assertSame($viaQuery[$key] ?? null, $viaHelper[$key], "mismatch on {$key}");
        }
    }

    public function test_many_returns_null_for_absent_keys_so_isset_and_data_get_still_work(): void
    {
        $result = Helpers::get_business_settings_many(['zz_absent_a', 'zz_absent_b']);

        $this->assertArrayHasKey('zz_absent_a', $result);
        $this->assertNull($result['zz_absent_a']);
        $this->assertFalse(isset($result['zz_absent_a']));
        $this->assertNull(data_get($result, 'zz_absent_a'));
    }

    public function test_many_reflects_a_save_made_in_the_same_request(): void
    {
        BusinessSetting::create(['key' => $this->key, 'value' => 'OLD']);
        $this->assertSame('OLD', Helpers::get_business_settings_many([$this->key])[$this->key]);

        Helpers::businessUpdateOrInsert(['key' => $this->key], ['value' => 'NEW']);

        $this->assertSame('NEW', Helpers::get_business_settings_many([$this->key])[$this->key]);
    }

    public function test_model_helper_keeps_its_id_and_relations_after_a_value_read(): void
    {
        $logo = Helpers::getSettingsDataFromConfig('logo', ['storage']);

        if ($logo === null) {
            $this->markTestSkipped('no logo row in business_settings');
        }

        Helpers::clearBusinessSettingsCache();

        Helpers::get_business_settings('logo', false);
        $after = Helpers::getSettingsDataFromConfig('logo', ['storage']);

        $this->assertNotNull($after->id, 'model memo must not be poisoned by the value memo');
        $this->assertTrue($after->relationLoaded('storage'));
    }
}
