<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\IncomingCallService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IncomingCallController extends Controller
{
    public function __invoke(Request $request, IncomingCallService $incomingCalls): JsonResponse
    {
        $validated = $request->validate([
            'organization_id' => 'required|integer|exists:organizations,id',
            'caller_number' => 'required|string|max:50',
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:50',
            'external_call_id' => 'nullable|string|max:255',
            'organization_voip_connection_id' => 'nullable|integer',
            'voip_call_log_id' => 'nullable|integer',
            'direction' => 'nullable|string|max:20',
        ]);

        $organization = Organization::query()->findOrFail($validated['organization_id']);
        $token = $request->header('X-Voip-Webhook-Token')
            ?? $request->header('X-Voip-Webhook-Secret')
            ?? $request->header('X-Api-Secret');

        if ($token) {
            $valid = $organization->voipConnections()
                ->where('is_active', true)
                ->where('webhook_token', $token)
                ->exists();

            if (! $valid && $token !== config('app.key')) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }
        }

        $session = $incomingCalls->register($validated);

        return response()->json([
            'message' => 'Incoming call broadcast',
            'session_id' => $session->id,
            'status' => $session->status->value,
        ], 202);
    }
}
