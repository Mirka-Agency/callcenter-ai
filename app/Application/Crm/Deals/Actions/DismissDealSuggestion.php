<?php

namespace App\Application\Crm\Deals\Actions;

use App\Application\Crm\Deals\DealAuthorization;
use App\Domain\Crm\Deals\DealSuggestionStatus;
use App\Models\CrmDealAiSuggestion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DismissDealSuggestion
{
    public function __construct(private DealAuthorization $auth) {}

    public function handle(CrmDealAiSuggestion $suggestion, User $actor): CrmDealAiSuggestion
    {
        $this->auth->authorize($this->auth->canCreate($actor, (int) $suggestion->organization_id));

        return DB::transaction(function () use ($suggestion, $actor): CrmDealAiSuggestion {
            $locked = CrmDealAiSuggestion::query()->whereKey($suggestion->id)->lockForUpdate()->first();

            if (! $locked instanceof CrmDealAiSuggestion || $locked->status !== DealSuggestionStatus::Pending) {
                throw ValidationException::withMessages([
                    'suggestion' => 'این پیشنهاد دیگر در انتظار بررسی نیست.',
                ]);
            }

            $locked->update([
                'status' => DealSuggestionStatus::Dismissed,
                'resolved_by_user_id' => $actor->id,
                'resolved_at' => now(),
            ]);

            return $locked;
        });
    }
}
