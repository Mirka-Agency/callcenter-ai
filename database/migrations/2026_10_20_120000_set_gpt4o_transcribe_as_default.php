<?php

use App\Application\Llm\Actions\SetGpt4oTranscribeAsDefaultAction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('llm_models') || ! Schema::hasTable('llm_providers') || ! Schema::hasTable('platform_ai_settings')) {
            return;
        }

        if (! Schema::hasColumn('llm_models', 'is_transcription_default') || ! Schema::hasColumn('platform_ai_settings', 'default_transcription_model_id')) {
            return;
        }

        app(SetGpt4oTranscribeAsDefaultAction::class)->execute();
    }

    public function down(): void
    {
        // Transcription default is operational data; do not restore whisper-1 automatically.
    }
};
