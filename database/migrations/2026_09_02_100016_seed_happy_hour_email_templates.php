<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Store-facing email templates for the happy hour enrolment outcomes.
 *
 * Identical in shape to the BOGO templates beside it, and different only in copy: joining a happy
 * hour commits the store to a discount rate for a window, not to a bundle, so the approval mail
 * has a window to name and no items to describe.
 *
 * Note what these emails do not say: they never state who absorbs the discount. A happy hour is
 * carried in full by the vendor, and that belongs on the offer screen and in the earning report
 * where the figure can be shown, not in a template an operator may rewrite.
 */
return new class extends Migration
{
    private const TEMPLATES = [
        [
            'email_type' => 'happy_hour_request',
            'title' => 'We have received your Happy Hour request',
            'body' => '<p>Dear User,</p><p>&nbsp;</p><p>Your request to join the Happy Hour offer has been received and is now waiting for review. We will let you know as soon as a decision is made.</p>',
            'button_name' => 'View Status',
        ],
        [
            'email_type' => 'happy_hour_approve',
            'title' => 'Congratulations! Your Happy Hour request is approved',
            'body' => '<p>Dear User,</p><p>&nbsp;</p><p>Your request to join the Happy Hour offer has been approved. The discount will apply automatically to your store whenever the offer window is running.</p>',
            'button_name' => 'View Offer',
        ],
        [
            'email_type' => 'happy_hour_deny',
            'title' => 'Update on your Happy Hour request',
            'body' => '<p>Dear User,</p><p>&nbsp;</p><p>Your request to join the Happy Hour offer was not approved. You can review the reason given and request to join again.</p>',
            'button_name' => 'View Details',
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('email_templates')) {
            return;
        }

        $now = now();

        foreach (self::TEMPLATES as $template) {
            $exists = DB::table('email_templates')
                ->where('type', 'store')
                ->where('email_type', $template['email_type'])
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('email_templates')->insert([
                'type' => 'store',
                'email_type' => $template['email_type'],
                'title' => $template['title'],
                'body' => $template['body'],
                'button_name' => $template['button_name'],
                'button_url' => '',
                'footer_text' => 'Please contact us for any queries; we are always happy to help.',
                'copyright_text' => '© '.$now->year.' 6amMart. All rights reserved.',
                'email_template' => 1,
                'privacy' => 1,
                'refund' => 1,
                'cancelation' => 1,
                'contact' => 1,
                'facebook' => 1,
                'instagram' => 1,
                'twitter' => 1,
                'linkedin' => 1,
                'pinterest' => 1,
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('email_templates')) {
            return;
        }

        DB::table('email_templates')
            ->where('type', 'store')
            ->whereIn('email_type', array_column(self::TEMPLATES, 'email_type'))
            ->delete();
    }
};
