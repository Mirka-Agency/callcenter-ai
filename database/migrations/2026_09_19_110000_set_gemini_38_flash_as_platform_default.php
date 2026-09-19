<?php

use App\Application\Llm\Actions\SetGemini38FlashAsDefaultAction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('llm_models') || ! Schema::hasTable('llm_providers')) {
            return;
        }

        app(SetGemini38FlashAsDefaultAction::class)->execute();
    }

    public function down(): void
    {
        // Platform default is operational data; do not restore the previous model automatically.
    }
};
