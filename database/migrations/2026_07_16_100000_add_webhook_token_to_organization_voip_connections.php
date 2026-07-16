<?php

use App\Models\OrganizationVoipConnection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_voip_connections', function (Blueprint $table) {
            $table->string('webhook_token', 64)->nullable()->unique()->after('name');
        });

        OrganizationVoipConnection::query()
            ->whereNull('webhook_token')
            ->eachById(function (OrganizationVoipConnection $connection): void {
                $connection->update([
                    'webhook_token' => OrganizationVoipConnection::generateWebhookToken(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('organization_voip_connections', function (Blueprint $table) {
            $table->dropUnique(['webhook_token']);
            $table->dropColumn('webhook_token');
        });
    }
};
