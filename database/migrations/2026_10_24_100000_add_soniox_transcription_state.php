<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('call_transcripts', function (Blueprint $table) {
            $table->string('remote_file_id')->nullable();
            $table->string('remote_transcription_id')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->json('segments_json')->nullable();
            $table->timestamp('remote_cleaned_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('call_transcripts', function (Blueprint $table) {
            $table->dropColumn(['remote_file_id', 'remote_transcription_id', 'submitted_at', 'segments_json', 'remote_cleaned_at']);
        });
    }
};
