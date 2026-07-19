<?php

namespace App\Services\QueueMonitoring;

class QueueJobInspector
{
    public function inspect(?string $payloadJson, ?string $exception = null): QueueJobInspection
    {
        $payload = is_string($payloadJson) ? json_decode($payloadJson, true) : null;

        if (! is_array($payload)) {
            return new QueueJobInspection(
                exceptionMessage: $this->exceptionMessage($exception),
                exceptionFull: $exception,
            );
        }

        $displayName = $payload['displayName'] ?? null;
        $properties = [];
        $chainedJobs = [];

        $command = $payload['data']['command'] ?? null;

        if (is_string($command)) {
            [$properties, $chainedJobs] = $this->extractFromCommand($command);
        }

        return new QueueJobInspection(
            displayName: is_string($displayName) ? $displayName : null,
            jobClass: is_string($displayName) ? class_basename($displayName) : null,
            jobUuid: $payload['uuid'] ?? null,
            maxTries: isset($payload['maxTries']) ? (int) $payload['maxTries'] : null,
            timeout: isset($payload['timeout']) ? (int) $payload['timeout'] : null,
            properties: $properties,
            chainedJobs: $chainedJobs,
            exceptionMessage: $this->exceptionMessage($exception),
            exceptionFull: $exception,
        );
    }

    /** @return array{0: array<string, mixed>, 1: list<string>} */
    private function extractFromCommand(string $command): array
    {
        try {
            $job = unserialize($command, ['allowed_classes' => true]);
        } catch (\Throwable) {
            return [[], []];
        }

        if (! is_object($job)) {
            return [[], []];
        }

        $properties = [];
        $reflection = new \ReflectionObject($job);

        foreach ($reflection->getProperties() as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $name = $property->getName();

            if (in_array($name, ['chained', 'chainConnection', 'chainQueue', 'chainCatchCallbacks'], true)) {
                continue;
            }

            $property->setAccessible(true);

            if (! $property->isInitialized($job)) {
                continue;
            }

            $value = $property->getValue($job);

            if ($value instanceof \BackedEnum) {
                $value = $value->value;
            }

            $properties[$name] = $value;
        }

        $chainedJobs = [];

        if (property_exists($job, 'chained') && is_array($job->chained)) {
            foreach ($job->chained as $chainedCommand) {
                if (! is_string($chainedCommand)) {
                    continue;
                }

                try {
                    $chainedJob = unserialize($chainedCommand, ['allowed_classes' => true]);
                    $chainedJobs[] = is_object($chainedJob) ? $chainedJob::class : $chainedCommand;
                } catch (\Throwable) {
                    $chainedJobs[] = $chainedCommand;
                }
            }
        }

        return [$properties, $chainedJobs];
    }

    private function exceptionMessage(?string $exception): ?string
    {
        if (! is_string($exception) || $exception === '') {
            return null;
        }

        return trim(strtok($exception, "\n") ?: $exception);
    }
}
