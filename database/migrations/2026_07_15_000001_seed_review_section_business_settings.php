<?php

use App\Models\BusinessSetting;
use App\Models\DataSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $reviewSection = BusinessSetting::firstOrNew(['key' => 'review_section']);
        if (! $reviewSection->exists) {
            $reviewSection->value = '1';
            $reviewSection->save();
        }

        $serviceReviewSection = DataSetting::firstOrNew([
            'key' => 'service_review_section',
            'type' => SERVICE_BUSINESS_SETTINGS,
        ]);
        if (! $serviceReviewSection->exists) {
            $serviceReviewSection->value = '1';
            $serviceReviewSection->save();
        }
    }

    public function down(): void
    {
        BusinessSetting::where('key', 'review_section')->delete();
        DataSetting::where('type', SERVICE_BUSINESS_SETTINGS)
            ->where('key', 'service_review_section')
            ->delete();
    }
};
