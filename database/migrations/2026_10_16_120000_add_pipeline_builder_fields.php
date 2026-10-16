<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_pipelines', function (Blueprint $table): void {
            $table->boolean('show_win_probability')->default(true)->after('is_active');
        });

        Schema::table('crm_pipeline_stages', function (Blueprint $table): void {
            $table->string('description', 2000)->nullable()->after('name');
            $table->unsignedTinyInteger('win_probability')->nullable()->after('color');
            $table->boolean('is_won_stage')->default(false)->after('is_active');
            $table->boolean('is_lost_stage')->default(false)->after('is_won_stage');
        });
    }

    public function down(): void
    {
        Schema::table('crm_pipeline_stages', function (Blueprint $table): void {
            $table->dropColumn(['description', 'win_probability', 'is_won_stage', 'is_lost_stage']);
        });

        Schema::table('crm_pipelines', function (Blueprint $table): void {
            $table->dropColumn('show_win_probability');
        });
    }
};
