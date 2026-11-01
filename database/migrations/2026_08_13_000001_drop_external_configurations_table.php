<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('external_configurations');

        DB::table('migrations')
            ->where('migration', '2024_07_28_131816_create_external_configurations_table')
            ->delete();
    }

    public function down(): void
    {
        if (Schema::hasTable('external_configurations')) {
            return;
        }

        Schema::create('external_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }
};
