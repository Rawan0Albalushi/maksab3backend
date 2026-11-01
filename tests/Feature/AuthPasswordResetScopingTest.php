<?php

namespace Tests\Feature;

use App\Models\DeliveryMan;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthPasswordResetScopingTest extends TestCase
{
    use DatabaseTransactions;

    private const STRONG_PASSWORD = 'Str0ng!Passw0rd#2026';

    public function test_a_delivery_man_cannot_reset_another_account_with_their_own_token(): void
    {
        [$victim, $attacker] = $this->twoDeliveryMen();

        $this->issueToken(['email' => $victim->email, 'created_by' => 'deliveryman'], '987654');
        $victimHashBefore = DB::table('delivery_men')->where('id', $victim->id)->value('password');

        $response = $this->putJson('/api/v1/auth/delivery-man/reset-password', [
            'phone' => $attacker->phone,
            'reset_token' => '987654',
            'password' => self::STRONG_PASSWORD,
            'confirm_password' => self::STRONG_PASSWORD,
        ]);

        $response->assertStatus(400);
        $this->assertSame($victimHashBefore, DB::table('delivery_men')->where('id', $victim->id)->value('password'));
        $this->assertTrue(
            DB::table('password_resets')->where(['email' => $victim->email, 'created_by' => 'deliveryman'])->exists(),
            'the victim OTP row must survive a rejected reset'
        );
    }

    public function test_a_delivery_man_reset_ignores_a_token_issued_to_another_actor(): void
    {
        $deliveryMan = DeliveryMan::withoutGlobalScope('delivery_only')->whereNotNull('email')->first();

        if (! $deliveryMan) {
            $this->markTestSkipped('no delivery man with an email in this dataset');
        }

        $this->issueToken(['email' => $deliveryMan->email, 'created_by' => 'user'], '555555');
        $hashBefore = DB::table('delivery_men')->where('id', $deliveryMan->id)->value('password');

        $response = $this->putJson('/api/v1/auth/delivery-man/reset-password', [
            'phone' => $deliveryMan->phone,
            'reset_token' => '555555',
            'password' => self::STRONG_PASSWORD,
            'confirm_password' => self::STRONG_PASSWORD,
        ]);

        $response->assertStatus(400);
        $this->assertSame($hashBefore, DB::table('delivery_men')->where('id', $deliveryMan->id)->value('password'));
    }

    public function test_a_delivery_man_reset_succeeds_with_their_own_token(): void
    {
        $deliveryMan = DeliveryMan::withoutGlobalScope('delivery_only')->whereNotNull('email')->first();

        if (! $deliveryMan) {
            $this->markTestSkipped('no delivery man with an email in this dataset');
        }

        $this->issueToken(['email' => $deliveryMan->email, 'created_by' => 'deliveryman'], '987654');
        $updatedAtBefore = DB::table('delivery_men')->where('id', $deliveryMan->id)->value('updated_at');

        $response = $this->putJson('/api/v1/auth/delivery-man/reset-password', [
            'phone' => $deliveryMan->phone,
            'reset_token' => '987654',
            'password' => self::STRONG_PASSWORD,
            'confirm_password' => self::STRONG_PASSWORD,
        ]);

        $response->assertStatus(200);

        $row = DB::table('delivery_men')->where('id', $deliveryMan->id)->first();
        $this->assertTrue(Hash::check(self::STRONG_PASSWORD, $row->password));
        $this->assertSame($updatedAtBefore, $row->updated_at, 'a password reset must not bump updated_at');
        $this->assertFalse(
            DB::table('password_resets')->where(['email' => $deliveryMan->email, 'created_by' => 'deliveryman'])->exists(),
            'the OTP must be consumed'
        );
    }

    public function test_vendor_forgot_password_does_not_accumulate_rows(): void
    {
        $vendor = Vendor::whereNotNull('email')->first();

        if (! $vendor) {
            $this->markTestSkipped('no vendor with an email in this dataset');
        }

        DB::table('password_resets')->where(['email' => $vendor->email, 'created_by' => 'vendor'])->delete();

        for ($attempt = 0; $attempt < 3; $attempt++) {
            DB::table('password_resets')
                ->where(['email' => $vendor->email, 'created_by' => 'vendor'])
                ->update(['created_at' => now()->subMinutes(5)]);

            $this->postJson('/api/v1/auth/vendor/forgot-password', ['email' => $vendor->email]);
        }

        $this->assertSame(
            1,
            DB::table('password_resets')->where(['email' => $vendor->email, 'created_by' => 'vendor'])->count(),
            'each request must update the vendor OTP row rather than insert a new one'
        );
    }

    public function test_delivery_man_login_rejects_an_arbitrary_type_column(): void
    {
        $deliveryMan = DeliveryMan::withoutGlobalScope('delivery_only')->first();

        if (! $deliveryMan) {
            $this->markTestSkipped('no delivery man in this dataset');
        }

        foreach (['bogus_col', 'status'] as $type) {
            $this->postJson('/api/v1/auth/delivery-man/login', [
                'phone' => $deliveryMan->phone,
                'password' => self::STRONG_PASSWORD,
                'type' => $type,
            ])->assertStatus(422);
        }
    }

    public function test_vendor_login_does_not_crash_when_the_vendor_has_no_store(): void
    {
        $vendor = Vendor::doesntHave('stores')->first();

        if (! $vendor) {
            $this->markTestSkipped('every vendor in this dataset has a store');
        }

        DB::table('vendors')->where('id', $vendor->id)->update([
            'password' => bcrypt(self::STRONG_PASSWORD),
            'status' => 1,
        ]);

        $this->postJson('/api/v1/auth/vendor/login', [
            'email' => $vendor->email,
            'password' => self::STRONG_PASSWORD,
            'vendor_type' => 'owner',
        ])->assertStatus(403);
    }

    public function test_an_expired_otp_is_rejected(): void
    {
        $deliveryMan = DeliveryMan::withoutGlobalScope('delivery_only')->whereNotNull('email')->first();

        if (! $deliveryMan) {
            $this->markTestSkipped('no delivery man with an email in this dataset');
        }

        DB::table('password_resets')->where('email', $deliveryMan->email)->delete();
        DB::table('password_resets')->insert([
            'email' => $deliveryMan->email,
            'created_by' => 'deliveryman',
            'token' => '987654',
            'created_at' => now()->subMinutes(61),
        ]);

        $hashBefore = DB::table('delivery_men')->where('id', $deliveryMan->id)->value('password');

        $this->putJson('/api/v1/auth/delivery-man/reset-password', [
            'phone' => $deliveryMan->phone,
            'reset_token' => '987654',
            'password' => self::STRONG_PASSWORD,
            'confirm_password' => self::STRONG_PASSWORD,
        ])->assertStatus(400);

        $this->assertSame($hashBefore, DB::table('delivery_men')->where('id', $deliveryMan->id)->value('password'));
    }

    public function test_vendor_otp_is_six_digits(): void
    {
        $vendor = Vendor::whereNotNull('email')->first();

        if (! $vendor) {
            $this->markTestSkipped('no vendor with an email in this dataset');
        }

        DB::table('password_resets')->where(['email' => $vendor->email, 'created_by' => 'vendor'])->delete();

        $this->postJson('/api/v1/auth/vendor/forgot-password', ['email' => $vendor->email]);

        $token = DB::table('password_resets')->where(['email' => $vendor->email, 'created_by' => 'vendor'])->value('token');

        $this->assertSame(6, strlen((string) $token), 'vendor OTPs must match the six digits used by every other actor');
    }

    public function test_malformed_translations_do_not_reach_the_database(): void
    {
        $store = \App\Models\Store::first();

        if (! $store) {
            $this->markTestSkipped('no store in this dataset');
        }

        DB::table('business_settings')->updateOrInsert(['key' => 'toggle_store_registration'], ['value' => '1']);

        $vendorsBefore = DB::table('vendors')->count();

        $response = $this->postJson('/api/v1/auth/vendor/register', [
            'f_name' => 'Malformed',
            'email' => 'malformed.translations@example.test',
            'phone' => '+8801999888777',
            'password' => self::STRONG_PASSWORD,
            'latitude' => (string) $store->latitude,
            'longitude' => (string) $store->longitude,
            'zone_id' => $store->zone_id,
            'module_id' => $store->module_id,
            'minimum_delivery_time' => 10,
            'maximum_delivery_time' => 30,
            'delivery_time_type' => 'min',
            'translations' => json_encode(['just-a-string', 'another']),
        ]);

        $response->assertStatus(422);
        $this->assertSame($vendorsBefore, DB::table('vendors')->count(), 'a rejected registration must not write a vendor row');
    }

    private function twoDeliveryMen(): array
    {
        $men = DeliveryMan::withoutGlobalScope('delivery_only')
            ->whereNotNull('email')
            ->whereNotNull('phone')
            ->take(2)
            ->get();

        if ($men->count() < 2) {
            $this->markTestSkipped('need two delivery men with an email and a phone');
        }

        return [$men[0], $men[1]];
    }

    private function issueToken(array $owner, string $token): void
    {
        DB::table('password_resets')->where($owner)->delete();
        DB::table('password_resets')->insert($owner + ['token' => $token, 'created_at' => now()]);
    }
}
