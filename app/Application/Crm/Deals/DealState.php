<?php

namespace App\Application\Crm\Deals;

use App\Models\CrmDeal;
use Illuminate\Validation\ValidationException;

class DealState
{
    public static function assertOpen(CrmDeal $deal): void
    {
        if (! $deal->status->isOpen()) {
            throw ValidationException::withMessages([
                'status' => 'معامله بسته‌شده را نمی‌توان در این وضعیت تغییر داد. ابتدا آن را بازگشایی کنید.',
            ]);
        }
    }
}
