<?php

namespace App\Application\Transcription\Services;

use App\Application\Llm\Prompts\SpeakerRolePrompt;
use App\Domain\Llm\Contracts\CompletesJsonPrompt;
use App\Domain\Llm\Contracts\LlmProviderInterface;
use App\Domain\Llm\DTOs\PromptContextData;
use App\Domain\Llm\Exceptions\LlmTransientException;
use App\Domain\Llm\ValueObjects\LlmOperationResult;
use App\Models\CallTranscript;
use Illuminate\Support\Facades\Log;

class SpeakerRoleMapper
{
    public function __construct(private SonioxTranscriptNormalizer $normalizer) {}

    public function map(CallTranscript $transcript, LlmProviderInterface $provider, string $model, PromptContextData $context): ?LlmOperationResult
    {
        if ($transcript->speakers_json !== null) {
            return null;
        }
        if (! $provider instanceof CompletesJsonPrompt) {
            throw new \RuntimeException('The analysis provider must support JSON speaker-role mapping.');
        }

        $result = $provider->completeJson(
            model: $model,
            system: SpeakerRolePrompt::system(),
            user: SpeakerRolePrompt::user($context, $transcript->content),
            maxOutputTokens: 1024,
        );
        if (! $result->success) {
            $message = $result->error ?? 'Speaker role mapping failed.';
            if (LlmTransientException::isTransientMessage($message)) {
                throw new LlmTransientException($message);
            }
            throw new \RuntimeException($message);
        }
        $roles = $result->data['roles'] ?? null;
        if (! is_array($roles)) {
            throw new \RuntimeException('Invalid speaker role mapping response.');
        }

        $mapped = [];
        foreach ($transcript->segments_json as $segment) {
            $speaker = $segment['speaker'];
            if ($speaker !== null) {
                $role = $roles[$speaker] ?? 'unknown';
                $mapped[$speaker] = in_array($role, ['agent', 'customer'], true) ? $role : 'unknown';
            }
        }
        if (in_array('unknown', $mapped, true)) {
            Log::notice('Some transcript speaker roles remain unknown.', ['transcript_id' => $transcript->id]);
        }
        $transcript->update([
            'speakers_json' => $mapped,
            'content' => $this->normalizer->render($transcript->segments_json, $mapped),
        ]);

        return $result;
    }
}
