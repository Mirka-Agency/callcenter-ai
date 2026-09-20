<?php

namespace App\Console\Commands;

use App\Application\Voip\Services\NormalizeVoipRecordingUrls;
use Illuminate\Console\Command;

class NormalizeVoipRecordingUrlsCommand extends Command
{
    protected $signature = 'voip:normalize-recording-urls
                            {--dry-run : Show how many URLs would change without writing}
                            {--no-requeue : Do not re-queue calls that failed because the recording could not be downloaded}';

    protected $description = 'Insert MixMonitor YYYY/MM/DD folders into recording URLs that only have the basename';

    public function handle(NormalizeVoipRecordingUrls $normalizer): int
    {
        $result = $normalizer->run(
            requeueFailedDownloads: ! $this->option('no-requeue'),
            dryRun: (bool) $this->option('dry-run'),
        );

        $this->components->info(sprintf(
            '%sVoIP recording URLs: %d call log(s), %d recording(s) rewritten, %d download-failed call(s) re-queued.',
            $this->option('dry-run') ? '[dry-run] ' : '',
            $result['logs'],
            $result['recordings'],
            $result['requeued'],
        ));

        return self::SUCCESS;
    }
}
