<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\HappyHour;
use App\Models\HappyHourStore;
use App\Traits\Promotion\HandlesPromotionEnrollment;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * QA case TC_693 / TC_697 — approving or rejecting a store's happy-hour request.
 *
 * Two defects, both only visible by driving the real vendor flow:
 *
 *  1. `updateEnrollmentStatus()` referenced an undefined `$happyHour` when notifying the store.
 *     The throw landed AFTER the status write, so the enrolment flipped, the admin got a 500
 *     instead of a success toast, and the store was never told.
 *  2. The rejection note served to the VENDOR used the admin's wording — a vendor whose request
 *     the admin denied read "You denied this request".
 */
class HappyHourEnrollmentDecisionTest extends TestCase
{
    use DatabaseTransactions;
    use HandlesPromotionEnrollment;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = Admin::find(1);
        $this->assertNotNull($this->admin, 'admin id 1 must exist');
    }

    private function panel()
    {
        return $this->actingAs($this->admin, 'admin')
            ->withSession(['login_remember_token' => $this->admin->login_remember_token]);
    }

    private function pendingEnrollment(): HappyHourStore
    {
        $storeId = \DB::table('stores')->value('id');

        if (! $storeId) {
            $this->markTestSkipped('needs a store to enrol');
        }

        // Created here rather than borrowed from the database: the suite must not depend on a
        // seeded offer existing, and DatabaseTransactions rolls this back with everything else.
        $offer = HappyHour::create([
            'module_id' => \DB::table('stores')->where('id', $storeId)->value('module_id'),
            'title' => 'QA Enrollment Offer',
            'discount' => 10,
            'duration_type' => HappyHour::DURATION_DAILY,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'start_time' => '16:00:00',
            'end_time' => '17:00:00',
            'status' => 1,
        ]);

        return HappyHourStore::create([
            'happy_hour_id' => $offer->id,
            'store_id' => $storeId,
            'status' => HappyHourStore::STATUS_PENDING,
            'requested_by' => 'store',
            'checked' => 0,
        ]);
    }

    /** The whole point: approving must not 500 after it has already written the status. */
    public function test_approving_a_request_succeeds_without_erroring(): void
    {
        $enrollment = $this->pendingEnrollment();

        $this->panel()
            ->post("/admin/happy-hour/confirmation/{$enrollment->happy_hour_id}/{$enrollment->id}/approved")
            ->assertRedirect();

        $this->assertSame(HappyHourStore::STATUS_APPROVED, $enrollment->fresh()->status);
    }

    /** A refusal owes the store a reason, and without one nothing may change. */
    public function test_rejecting_without_a_reason_changes_nothing(): void
    {
        $enrollment = $this->pendingEnrollment();

        $this->panel()->post("/admin/happy-hour/confirmation/{$enrollment->happy_hour_id}/{$enrollment->id}/rejected");

        $this->assertSame(HappyHourStore::STATUS_PENDING, $enrollment->fresh()->status);
    }

    public function test_rejecting_with_a_reason_records_it(): void
    {
        $enrollment = $this->pendingEnrollment();

        $this->panel()->post(
            "/admin/happy-hour/confirmation/{$enrollment->happy_hour_id}/{$enrollment->id}/rejected",
            ['rejection_reason' => 'Not eligible this month'],
        )->assertRedirect();

        $fresh = $enrollment->fresh();

        $this->assertSame(HappyHourStore::STATUS_REJECTED, $fresh->status);
        $this->assertSame('Not eligible this month', $fresh->rejection_reason);
        $this->assertSame('admin', $fresh->rejected_by);
    }

    /** The note is read by the STORE, so it must not tell them they denied their own request. */
    public function test_the_store_facing_rejection_note_names_the_admin(): void
    {
        $enrollment = $this->pendingEnrollment();
        $enrollment->forceFill([
            'status' => HappyHourStore::STATUS_REJECTED,
            'rejected_by' => 'admin',
            'rejection_reason' => 'Not eligible this month',
        ])->save();

        $note = $this->enrollmentRejectionNote($enrollment->fresh(), 'happy_hour');

        $this->assertStringNotContainsString('You denied', $note, 'the store must not be told they denied their own request');
        $this->assertStringContainsString(translate('messages.The admin denied this request'), $note);
        $this->assertStringContainsString('Not eligible this month', $note);
    }
}
