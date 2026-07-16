<?php

namespace App\Http\Controllers;

use App\Application\Voip\Jobs\ProcessVoipWebhookJob;
use App\Models\OrganizationVoipConnection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoipWebhookController extends Controller
{
    public function __invoke(Request $request, string $token): JsonResponse
    {
        $connection = OrganizationVoipConnection::query()
            ->where('webhook_token', $token)
            ->first();

        if (! $connection) {
            abort(404);
        }

        ProcessVoipWebhookJob::dispatch($connection->id, $request->all());

        return response()->json(['message' => 'Webhook accepted'], 202);
    }
}
