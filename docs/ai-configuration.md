# AI configuration

Prompt definitions and message builders are centralized in [`app/Application/Llm/Prompts`](../app/Application/Llm/Prompts/README.md). That directory documents analysis, speaker-role mapping, triage, legacy transcription, and how to edit and validate them. Prompt wording and post-processing rules are unchanged by this reorganization.

Soniox now receives lightweight context from `SonioxContext.php`: general transcription guidance plus the current organization's title and business context when available. Recognizable English words and technical terms are requested in their original English spelling when spoken. No fixed vocabulary list, translation configuration, guessed customer names, or fixed speaker count is supplied. Persian remains a language hint, not a strict language restriction. Organization title and domain context are limited to 200 and 1000 characters respectively to keep this background small. This affects new transcription submissions; completed transcripts are reused. Analysis summaries and coaching still follow their separate language policy.

Recorded calls use Soniox for transcription and GPT-6 Luna through AvalAI for text analysis. Provider/model management pages have been removed. Existing provider/model rows remain solely for historical records and migrations; they never override environment configuration.

## Environment

```dotenv
LLM_PROVIDER=openai
LLM_MODEL=gpt-6-luna
LLM_BASE_URL=https://api.avalai.ir/v1
LLM_API_KEY=<AvalAI API key>
SONIOX_API_KEY=<Soniox project API key>
SONIOX_BASE_URL=https://api.soniox.com/v1
SONIOX_MODEL=stt-async-v5
SONIOX_DEADLINE_SECONDS=3600
QUEUE_CONNECTION=database
```

`openai` selects the OpenAI-compatible adapter; the base URL routes requests through AvalAI. Keep the existing AvalAI key in `LLM_API_KEY`. Luna uses low reasoning effort and an 8192-token analysis output limit, matching the benchmark configuration. Speaker-role mapping keeps its separate output limit.

After changing environment values, run `php artisan config:clear` and restart the queue workers (`php artisan queue:restart`, or `php artisan horizon:terminate` when using Horizon). If deployment caches configuration, rebuild it with `php artisan config:cache` before restarting workers. Apply the same environment values in the production deployment settings; a local `.env` edit does not update the deployed application.

Use a real asynchronous database or Redis queue worker subscribed to the configured analysis queue. The synchronous queue cannot poll Soniox. Keep the worker timeout below the queue connection's `retry_after`; the existing analysis-job timeout is 1140 seconds and database/Redis retry-after defaults to 1260 seconds. Each analysis job has a fixed overall deadline, and up to three unhandled transient exceptions; normal five-second polling releases do not count as exceptions.

Keep `LLM_REMOTE_ENABLED=false` for local work. Existing AvalAI blocking settings also remain effective. Production requires remote processing enabled and AvalAI blocking disabled. Credentials must be set in the deployment environment, never committed. Missing configuration fails before audio is submitted.

Custom Soniox-compatible proxies are supported through `SONIOX_BASE_URL`, including path prefixes:

```dotenv
SONIOX_BASE_URL=https://third.mirka.agency/soniox/v1
```

Requests append `/files` and `/transcriptions` to this URL, preserving `/soniox/v1`. The proxy must accept the Soniox API schema and bearer authentication. Rebuild cached configuration and restart workers after changing the URL.

## Processing

1. Download the recording into existing private storage.
2. Upload it through Soniox's Files API and submit its file ID with Persian hints and diarization enabled. Full recordings retain audio context; the legacy Whisper segmentation path is not used.
3. Persist remote IDs and resume polling across queue retries and worker restarts.
4. Save speech segments, speaker IDs, timestamps, and token confidence metadata before calling the analysis model.
5. Ask Gemini to map speaker IDs to roles without rewriting transcript words or changing segment boundaries. Uncertain and additional speakers remain explicitly labeled.
6. Run existing triage and analysis. New analyses store the provider/model names and no legacy model foreign key. Completed transcripts and mappings are reused after analysis failures.
7. Delete remote files/transcriptions through an independent retryable cleanup job. Cleanup failures do not invalidate saved results. Monitor failed cleanup jobs and retry them through the existing queue tools.

Soniox is the only transcription provider in this pipeline. There is no automatic fallback or bulk retranscription. Completed legacy transcripts remain reusable.

## Deployment

Pause ingestion and drain preexisting analysis jobs before this queue-behavior change. Set both API keys, apply `php artisan migrate --force`, run `php artisan config:cache`, and restart workers with `php artisan queue:restart`. Verify a controlled Persian recording before enabling normal ingestion. Check transcript text, speaker attribution, employee/customer roles, analysis fields, and cleanup completion. Automated tests use HTTP fakes and spend no provider credits; this change does not establish comparative Persian accuracy.

Run `php artisan transcriptions:cleanup` to enqueue cleanup for terminal Soniox records with outstanding remote resources. The scheduler also runs it hourly when remote analysis is enabled.

## Failure recovery

- Rate limits and retrieval server/network errors use bounded queue retries. Authentication, billing, validation, and terminal transcription errors surface as failures rather than empty/demo analyses.
- Lost responses, missing remote IDs, or server errors during upload/submission leave acceptance uncertain. These records stay in `uploading` or `submitting`; retries stop before another submission. Inspect Soniox's console/usage using the `call-<id>` reference before changing their state. If a matching transcription exists, recover its ID and set the record to `processing`. If acceptance is confirmed absent, reset the record to `pending`; retain a known file ID. Do not reset blindly.
- After correcting a terminal provider error, explicitly create a new transcription attempt and requeue the call. Do not reuse IDs for resources already deleted by cleanup.
- Logs contain stage identifiers and error types rather than audio or credentials. `remote_cleaned_at` records successful cleanup. Inspect terminal records with no cleanup timestamp and the failed-job queue if storage quotas rise.

## References

- [Soniox async API](https://soniox.com/docs/stt/async/async-transcription)
- [Speaker diarization](https://soniox.com/docs/stt/concepts/speaker-diarization)
- [Persian language support](https://soniox.com/docs/stt/concepts/supported-languages)
- [Soniox errors](https://soniox.com/docs/api-reference/errors)
- [Gemini 3.8 Flash](https://ai.google.dev/gemini-api/docs/models/gemini-3.8-flash)
- [AvalAI native Gemini API](https://docs.avalai.org/en/api-reference/v1beta)
