<?php

namespace App\Console\Commands;

use App\Support\Seeding\AvayarShowcase;
use Illuminate\Console\Command;

class SeedAvayarShowcaseCommand extends Command
{
    protected $signature = 'avayar:seed-showcase';

    protected $description = 'Create the Avayar showcase organization and fill its workspace with realistic records';

    public function handle(AvayarShowcase $showcase): int
    {
        $result = $showcase->seed();

        $this->info($result['created'] ? 'شرکت آوایار ساخته شد.' : 'شرکت آوایار از قبل وجود داشت؛ چیزی دوباره ساخته نشد.');
        $this->table(
            ['مورد', 'مقدار'],
            [
                ['سازمان', $result['title']],
                ['ورود مدیر', $result['employer_email']],
                ['رمز عبور', $result['password']],
                ['کارشناس نمونه', $result['employee_email']],
                ['اشخاص', (string) $result['customers']],
                ['شرکت‌ها', (string) $result['companies']],
                ['تماس‌های تحلیل‌شده', (string) $result['calls']],
                ['معاملات', (string) $result['deals']],
                ['یادآوری‌ها', (string) $result['tasks']],
                ['فاکتورها', (string) $result['invoices']],
            ],
        );

        return self::SUCCESS;
    }
}
