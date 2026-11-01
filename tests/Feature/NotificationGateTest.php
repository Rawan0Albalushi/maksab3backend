<?php

namespace Tests\Feature;

use App\Models\NotificationSetting;
use App\Models\StoreNotificationSetting;
use App\Support\Notification\NotificationGate;
use App\Support\Notification\StoreNotificationSettings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use App\Support\Notification\SendNotification;

class NotificationGateTest extends TestCase
{
    use DatabaseTransactions;

    private string $key = 'zz_gate_test_key';

    protected function setUp(): void
    {
        parent::setUp();
        NotificationGate::flush();
    }

    protected function tearDown(): void
    {
        NotificationGate::flush();
        parent::tearDown();
    }

    private function setting(string $mail = 'active', string $push = 'active', ?string $module = 'all'): NotificationSetting
    {
        return NotificationSetting::create([
            'type' => 'customer',
            'key' => $this->key,
            'module_type' => $module,
            'title' => 'Gate test',
            'mail_status' => $mail,
            'push_notification_status' => $push,
            'sms_status' => 'inactive',
        ]);
    }

    public function test_active_and_inactive_map_to_one_and_zero(): void
    {
        $this->setting(mail: 'active', push: 'inactive');

        $this->assertSame(1, SendNotification::channelEnabled('customer', $this->key, 'mail_status'));
        $this->assertSame(0, SendNotification::channelEnabled('customer', $this->key, 'push_notification_status'));
        $this->assertSame(0, SendNotification::channelEnabled('customer', $this->key, 'sms_status'));
    }

    public function test_an_unknown_key_is_denied(): void
    {
        $this->assertSame(0, SendNotification::channelEnabled('customer', 'no_such_key_at_all', 'mail_status'));
    }

    public function test_repeated_checks_do_not_requery(): void
    {
        $this->setting();
        SendNotification::channelEnabled('customer', $this->key, 'mail_status');

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        for ($i = 0; $i < 25; $i++) {
            SendNotification::channelEnabled('customer', $this->key, 'mail_status');
            SendNotification::channelEnabled('customer', $this->key, 'push_notification_status');
        }

        $this->assertSame(0, $queries, '50 gate checks must not hit the database.');
    }

    public function test_saving_a_setting_invalidates_the_cache(): void
    {
        $setting = $this->setting(mail: 'active');
        $this->assertSame(1, SendNotification::channelEnabled('customer', $this->key, 'mail_status'));

        $setting->mail_status = 'inactive';
        $setting->save();

        $this->assertSame(0, SendNotification::channelEnabled('customer', $this->key, 'mail_status'), 'A toggled setting must take effect immediately.');
    }

    public function test_deleting_a_setting_invalidates_the_cache(): void
    {
        $setting = $this->setting();
        $this->assertSame(1, SendNotification::channelEnabled('customer', $this->key, 'mail_status'));

        $setting->delete();

        $this->assertSame(0, SendNotification::channelEnabled('customer', $this->key, 'mail_status'));
    }

    public function test_module_scoped_lookups_are_isolated(): void
    {
        $this->setting(mail: 'inactive', push: 'inactive', module: 'all');
        NotificationSetting::create([
            'type' => 'provider',
            'key' => $this->key,
            'module_type' => 'service',
            'title' => 'Gate test',
            'mail_status' => 'active',
            'push_notification_status' => 'active',
            'sms_status' => 'inactive',
        ]);

        $this->assertSame(1, SendNotification::serviceChannelEnabled('provider', $this->key, 'mail_status'));
        $this->assertSame(0, SendNotification::rentalChannelEnabled('provider', $this->key, 'mail_status'));
        $this->assertSame(0, SendNotification::channelEnabled('customer', $this->key, 'mail_status'));
    }

    public function test_a_store_override_can_switch_off_a_globally_enabled_channel(): void
    {
        NotificationSetting::create([
            'type' => 'store',
            'key' => $this->key,
            'module_type' => 'all',
            'title' => 'Gate test',
            'mail_status' => 'active',
            'push_notification_status' => 'active',
            'sms_status' => 'inactive',
        ]);

        $storeId = StoreNotificationSetting::query()->value('store_id');

        if ($storeId === null) {
            $this->markTestSkipped('No store notification settings seeded in this database.');
        }

        StoreNotificationSetting::create([
            'store_id' => $storeId,
            'key' => $this->key,
            'module_type' => 'all',
            'title' => 'Gate test',
            'mail_status' => 'inactive',
            'push_notification_status' => 'active',
            'sms_status' => 'inactive',
        ]);

        NotificationGate::forgetStore($storeId);

        $this->assertSame(1, SendNotification::channelEnabled('store', $this->key, 'mail_status'), 'Without a store id the global setting applies.');
        $this->assertSame(0, SendNotification::channelEnabled('store', $this->key, 'mail_status', $storeId), 'The store override must win.');
        $this->assertSame(1, SendNotification::channelEnabled('store', $this->key, 'push_notification_status', $storeId));
    }

    public function test_the_seeded_store_key_sets_stay_disjoint_across_module_types(): void
    {
        $keys = [
            'core' => array_column(StoreNotificationSettings::getStoreNotificationSetupData(1), 'key'),
            'rental' => array_column(StoreNotificationSettings::getRentalStoreNotificationSetupData(1), 'key'),
            'service' => array_column(StoreNotificationSettings::getServiceStoreNotificationSetupData(1), 'key'),
        ];

        foreach ([['core', 'rental'], ['core', 'service'], ['rental', 'service']] as [$a, $b]) {
            $this->assertSame(
                [],
                array_values(array_intersect($keys[$a], $keys[$b])),
                "Store notification keys overlap between {$a} and {$b}. NotificationGate::loadStore() keys rows "
                    ."by `key` alone, so one module_type's row would silently shadow the other's.",
            );
        }
    }

    public function test_statuses_for_returns_all_three_channels(): void
    {
        $this->setting(mail: 'active', push: 'inactive');

        $statuses = SendNotification::settingFor('customer', $this->key);

        $this->assertSame('active', $statuses->mail_status);
        $this->assertSame('inactive', $statuses->push_notification_status);
        $this->assertNull(SendNotification::settingFor('customer', 'no_such_key_at_all'));
    }
}
