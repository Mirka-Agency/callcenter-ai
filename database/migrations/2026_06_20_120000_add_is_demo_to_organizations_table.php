<?php

use App\Support\Seeding\DemoCatalog;
use Database\Support\IdempotentSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            if (! Schema::hasColumn('organizations', 'is_demo')) {
                $table->boolean('is_demo')->default(false)->after('disabled');
            }
            if (! Schema::hasIndex('organizations', ['is_demo'])) {
                $table->index('is_demo');
            }
        });

        DB::table('organizations')
            ->whereIn('user_id', function ($query): void {
                $query->select('id')
                    ->from('users')
                    ->where('email', 'like', 'demo-employer-%@'.DemoCatalog::EMAIL_DOMAIN);
            })
            ->update(['is_demo' => true]);
    }

    public function down(): void
    {
        if (Schema::hasIndex('organizations', ['is_demo'])) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->dropIndex(['is_demo']);
            });
        }
        IdempotentSchema::dropColumnsIfExist('organizations', 'is_demo');
    }
};
