<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->dropIndexIfExists('users', 'users_phone_unique');
        $this->dropIndexIfExists('users', 'users_ref_code_unique');

        Schema::table('users', function (Blueprint $t) {
            $t->unsignedBigInteger('tenant_id')->default(0);
            $t->unsignedBigInteger('sub_tenant_id')->default(0);

            $t->unique(['email', 'tenant_id', 'sub_tenant_id'], 'users_email_scope_unique');
            $t->unique(['phone', 'tenant_id', 'sub_tenant_id'], 'users_phone_scope_unique');
            $t->unique(['ref_code', 'tenant_id', 'sub_tenant_id'], 'users_ref_code_scope_unique');
            $t->index(['tenant_id', 'sub_tenant_id'], 'users_scope_index');
        });

        $this->dropIndexIfExists('phone_verifications', 'phone_verifications_phone_unique');

        Schema::table('phone_verifications', function (Blueprint $t) {
            $t->unsignedBigInteger('tenant_id')->default(0);
            $t->unsignedBigInteger('sub_tenant_id')->default(0);
            $t->unique(['phone', 'tenant_id', 'sub_tenant_id'], 'phone_verifications_phone_scope_unique');
            $t->index(['tenant_id', 'sub_tenant_id'], 'phone_verifications_scope_index');
        });

        Schema::table('password_resets', function (Blueprint $t) {
            $t->unsignedBigInteger('tenant_id')->default(0);
            $t->unsignedBigInteger('sub_tenant_id')->default(0);
            $t->index(['tenant_id', 'sub_tenant_id'], 'password_resets_scope_index');
        });

        Schema::table('email_verifications', function (Blueprint $t) {
            $t->unsignedBigInteger('tenant_id')->default(0);
            $t->unsignedBigInteger('sub_tenant_id')->default(0);
            $t->index(['tenant_id', 'sub_tenant_id'], 'email_verifications_scope_index');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropUnique('users_email_scope_unique');
            $t->dropUnique('users_phone_scope_unique');
            $t->dropUnique('users_ref_code_scope_unique');
            $t->dropIndex('users_scope_index');
            $t->dropColumn(['tenant_id', 'sub_tenant_id']);
            $t->unique('phone', 'users_phone_unique');
            $t->unique('ref_code', 'users_ref_code_unique');
        });

        Schema::table('phone_verifications', function (Blueprint $t) {
            $t->dropUnique('phone_verifications_phone_scope_unique');
            $t->dropIndex('phone_verifications_scope_index');
            $t->dropColumn(['tenant_id', 'sub_tenant_id']);
            $t->unique('phone', 'phone_verifications_phone_unique');
        });

        Schema::table('password_resets', function (Blueprint $t) {
            $t->dropIndex('password_resets_scope_index');
            $t->dropColumn(['tenant_id', 'sub_tenant_id']);
        });

        Schema::table('email_verifications', function (Blueprint $t) {
            $t->dropIndex('email_verifications_scope_index');
            $t->dropColumn(['tenant_id', 'sub_tenant_id']);
        });
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        $rows = DB::select(
            'SELECT 1 FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?
             LIMIT 1',
            [$table, $index],
        );
        if (! empty($rows)) {
            DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$index}`");
        }
    }
};
