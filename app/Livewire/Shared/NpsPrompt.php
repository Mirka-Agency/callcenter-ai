<?php

namespace App\Livewire\Shared;

use App\Enums\UserRole;
use App\Models\NpsResponse;
use App\Models\User;
use App\Services\ImpersonationService;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Asks employers "how likely are you to recommend us" once every SURVEY_INTERVAL_DAYS,
 * after their account is MIN_ACCOUNT_AGE_DAYS old. Skipping records a response without a score.
 */
class NpsPrompt extends Component
{
    public const SURVEY_INTERVAL_DAYS = 90;

    public const MIN_ACCOUNT_AGE_DAYS = 14;

    public bool $visible = false;

    #[Validate('nullable|integer|min:0|max:10')]
    public ?int $score = null;

    #[Validate('nullable|string|max:2000')]
    public string $comment = '';

    public bool $submitted = false;

    public function mount(): void
    {
        $this->visible = self::shouldAsk(auth()->user());
    }

    public static function shouldAsk(?User $user): bool
    {
        if ($user?->role !== UserRole::Employer || $user->primaryOrganization() === null) {
            return false;
        }

        if (app(ImpersonationService::class)->isImpersonating()) {
            return false;
        }

        if ($user->created_at === null || $user->created_at->gt(now()->subDays(self::MIN_ACCOUNT_AGE_DAYS))) {
            return false;
        }

        return ! NpsResponse::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDays(self::SURVEY_INTERVAL_DAYS))
            ->exists();
    }

    public function choose(int $score): void
    {
        $this->score = max(0, min(10, $score));
    }

    public function submit(): void
    {
        $this->validate(['score' => 'required|integer|min:0|max:10', 'comment' => 'nullable|string|max:2000']);
        $this->store($this->score, $this->comment);
        $this->submitted = true;
    }

    public function skip(): void
    {
        $this->store(null, null);
        $this->visible = false;
    }

    private function store(?int $score, ?string $comment): void
    {
        $user = auth()->user();

        if (! self::shouldAsk($user)) {
            return;
        }

        NpsResponse::query()->create([
            'organization_id' => $user->primaryOrganization()->id,
            'user_id' => $user->id,
            'score' => $score,
            'comment' => filled($comment) ? $comment : null,
        ]);
    }

    public function render()
    {
        return view('livewire.shared.nps-prompt');
    }
}
