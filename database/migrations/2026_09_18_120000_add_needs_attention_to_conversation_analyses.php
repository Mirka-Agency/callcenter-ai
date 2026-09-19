<?php

use App\Support\NeedsAttention;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_analyses', function (Blueprint $table) {
            if (! Schema::hasColumn('conversation_analyses', 'needs_attention')) {
                $table->boolean('needs_attention')->default(false)->after('is_evaluable')->index();
            }

            if (! Schema::hasColumn('conversation_analyses', 'attention_json')) {
                $table->json('attention_json')->nullable()->after('needs_attention');
            }
        });

        if (! Schema::hasColumn('conversation_analyses', 'needs_attention')) {
            return;
        }

        DB::table('conversation_analyses')
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $attention = NeedsAttention::inferFromSignals(
                        is_string($row->sentiment ?? null) ? $row->sentiment : null,
                        $this->decodeJson($row->customer_insights_json ?? null),
                        $this->decodeJson($row->operational_insights_json ?? null),
                        $this->decodeJsonList($row->concerns_json ?? null),
                        (string) ($row->summary ?? ''),
                    );

                    if (! $attention['needed']) {
                        continue;
                    }

                    DB::table('conversation_analyses')->where('id', $row->id)->update([
                        'needs_attention' => true,
                        'attention_json' => json_encode($attention, JSON_UNESCAPED_UNICODE),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('conversation_analyses', function (Blueprint $table) {
            if (Schema::hasColumn('conversation_analyses', 'attention_json')) {
                $table->dropColumn('attention_json');
            }

            if (Schema::hasColumn('conversation_analyses', 'needs_attention')) {
                $table->dropColumn('needs_attention');
            }
        });
    }

    /** @return array<string, mixed> */
    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @return list<mixed> */
    private function decodeJsonList(mixed $value): array
    {
        $decoded = $this->decodeJson($value);

        return array_is_list($decoded) ? $decoded : [];
    }
};
