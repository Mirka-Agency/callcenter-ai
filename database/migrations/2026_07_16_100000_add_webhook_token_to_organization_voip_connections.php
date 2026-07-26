<?php

use App\Models\OrganizationVoipConnection;
use Database\Support\IdempotentSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_voip_connections', function (Blueprint $table) {
            if (! Schema::hasColumn('organization_voip_connections', 'webhook_token')) {
                $table->string('webhook_token', 64)->nullable()->unique()->after('name');
            }
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
        IdempotentSchema::dropColumnsIfExist('organization_voip_connections', 'webhook_token');
    }
};
