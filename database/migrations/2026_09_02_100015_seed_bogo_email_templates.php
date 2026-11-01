<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Store-facing email templates for the BOGO enrolment outcomes.
 *
 * Modelled on the campaign_request / campaign_approve / campaign_deny rows already in this table,
 * which are the same conversation for a different feature: admin publishes something, stores join
 * it, the admin decides. Copying that row shape keeps these renderable by the existing template
 * editor with no new fields.
 *
 * The source's own seeder is not reusable here. Its email_templates has a narrower set of columns
 * and no type/email_type pair -- 6amMart addresses a template by (type, email_type), and the
 * branding and footer columns below have no counterpart on the other side at all.
 *
 * The five footer flags default on to match every neighbouring row; an operator turns them off per
 * template in the editor rather than in the schema. background_image, image and logo are left null
 * so the template inherits the panel's configured branding instead of pinning a 2023 asset.
 */
return new class extends Migration
{
    private const TEMPLATES = [
        [
            'email_type' => 'bogo_request',
            'title' => 'We have received your BOGO offer request',
            'body' => '<p>Dear User,</p><p>&nbsp;</p><p>Your request to join the BOGO offer has been received and is now waiting for review. We will let you know as soon as a decision is made.</p>',
            'button_name' => 'View Status',
        ],
        [
            'email_type' => 'bogo_approve',
            'title' => 'Congratulations! Your BOGO offer request is approved',
            'body' => '<p>Dear User,</p><p>&nbsp;</p><p>Your request to join the BOGO offer has been approved. The bundle is now available to customers whenever the offer is running and your items are in stock.</p>',
            'button_name' => 'View Offer',
        ],
        [
            'email_type' => 'bogo_deny',
            'title' => 'Update on your BOGO offer request',
            'body' => '<p>Dear User,</p><p>&nbsp;</p><p>Your request to join the BOGO offer was not approved. You can review the reason given and submit an updated bundle.</p>',
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
