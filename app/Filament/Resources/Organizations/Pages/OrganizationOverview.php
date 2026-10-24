<?php

namespace App\Filament\Resources\Organizations\Pages;

use App\Application\Impersonation\Actions\StartImpersonationAction;
use App\Domain\Call\Enums\CallProcessingStatus;
use App\Domain\Crm\Enums\CrmLogStatus;
use App\Domain\Voip\Enums\VoipLogStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Organizations\OrganizationResource;
use App\Models\Call;
use App\Models\CrmSyncLog;
use App\Models\Organization;
use App\Models\VoipWebhookLog;
use App\Services\Admin\AdminAuditLogger;
use App\Services\Billing\BillingMetrics;
use App\Services\ImpersonationService;
use App\Services\PlatformAnalytics\EngagementMetrics;
use App\Services\PlatformAnalytics\OrganizationHealthScorer;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;

/**
 * @property Organization $record
 */
class OrganizationOverview extends Page
{
    use InteractsWithRecord;

    protected static string $resource = OrganizationResource::class;

    protected string $view = 'filament.resources.organizations.overview';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string|Htmlable
    {
        return $this->record->title;
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.organization_overview.tab');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addNote')
                ->label(__('admin.notes.add'))
                ->icon(Heroicon::OutlinedPencilSquare)
                ->schema([
                    Textarea::make('body')->label(__('admin.notes.body'))->required()->rows(4)->maxLength(5000),
                ])
                ->action(function (array $data): void {
                    $note = $this->record->adminNotes()->create([
                        'user_id' => auth()->id(),
                        'body' => $data['body'],
                    ]);
                    app(AdminAuditLogger::class)->log('note_added', $this->record, ['after' => ['body' => $note->body]]);
                    Notification::make()->title(__('admin.common.saved'))->success()->send();
                }),
            Action::make('impersonateEmployer')
                ->label(__('admin.organization_overview.impersonate_employer'))
                ->icon(Heroicon::OutlinedArrowRightEndOnRectangle)
                ->color('warning')
                ->visible(fn (): bool => auth()->user()?->role === UserRole::SuperAdmin && $this->record->employer !== null)
                ->disabled(fn (): bool => ! app(ImpersonationService::class)->canImpersonate(auth()->user(), $this->record->employer))
                ->requiresConfirmation()
                ->action(fn () => redirect(app(StartImpersonationAction::class)->execute(
                    admin: auth()->user(),
                    target: $this->record->employer,
                    request: request(),
                ))),
            Action::make('toggleDisabled')
                ->label(fn (): string => $this->record->disabled ? __('admin.organization_overview.enable') : __('admin.organization_overview.disable'))
                ->color(fn (): string => $this->record->disabled ? 'success' : 'danger')
                ->icon(Heroicon::OutlinedPower)
                ->visible(fn (): bool => auth()->user()?->role === UserRole::SuperAdmin)
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->record->update(['disabled' => ! $this->record->disabled]);
                    Notification::make()->title(__('admin.common.saved'))->success()->send();
                }),
            Action::make('edit')
                ->label(__('filament-actions::edit.single.label'))
                ->icon(Heroicon::OutlinedPencil)
                ->color('gray')
                ->url(fn (): string => OrganizationResource::getUrl('edit', ['record' => $this->record])),
        ];
    }

    public function deleteNote(int $noteId): void
    {
        $note = $this->record->adminNotes()->whereKey($noteId)->first();

        if ($note && ($note->user_id === auth()->id() || auth()->user()?->role === UserRole::SuperAdmin)) {
            $note->delete();
        }
    }

    protected function getViewData(): array
    {
        $organization = $this->record->loadMissing('employer');
        $since = now()->subDays(7);

        return [
            'organization' => $organization,
            'health' => app(OrganizationHealthScorer::class)->for($organization),
            'onboarding' => app(EngagementMetrics::class)->onboarding([$organization->id])[$organization->id] ?? [],
            'billing' => app(BillingMetrics::class)->organizationSummary($organization),
            'failedCalls' => Call::query()
                ->where('organization_id', $organization->id)
                ->where('processing_status', CallProcessingStatus::Failed)
                ->where('updated_at', '>=', $since)
                ->count(),
            'integrationErrors' => $this->integrationErrors($organization, $since),
            'timeline' => $this->timeline($organization),
            'notes' => $organization->adminNotes()->with('author:id,name')->limit(20)->get(),
            'employees' => $organization->memberships()->count(),
            'activeEmployees' => $organization->memberships()->where('is_active', true)->count(),
        ];
    }

    /** @return Collection<int, array{at: mixed, source: string, message: string|null}> */
    private function integrationErrors(Organization $organization, $since): Collection
    {
        $voip = VoipWebhookLog::query()
            ->whereIn('organization_voip_connection_id', $organization->voipConnections()->select('id'))
            ->where('status', VoipLogStatus::Failed)
            ->where('created_at', '>=', $since)
            ->latest()
            ->limit(10)
            ->get(['created_at', 'event_type', 'message'])
            ->map(fn (VoipWebhookLog $log): array => ['at' => $log->created_at, 'source' => 'VoIP · '.$log->event_type, 'message' => $log->message]);

        $crm = CrmSyncLog::query()
            ->whereIn('organization_crm_connection_id', $organization->crmConnections()->select('id'))
            ->where('status', CrmLogStatus::Failed)
            ->where('created_at', '>=', $since)
            ->latest()
            ->limit(10)
            ->get(['created_at', 'operation', 'message'])
            ->map(fn (CrmSyncLog $log): array => ['at' => $log->created_at, 'source' => 'CRM · '.($log->operation?->value ?? $log->operation), 'message' => $log->message]);

        return $voip->concat($crm)->sortByDesc('at')->take(10)->values();
    }

    /** @return Collection<int, array{at: mixed, title: string, description: string|null}> */
    private function timeline(Organization $organization): Collection
    {
        return $organization->activities()
            ->latest()
            ->limit(15)
            ->get(['title', 'description', 'created_at'])
            ->map(fn ($activity): array => [
                'at' => $activity->created_at,
                'title' => (string) $activity->title,
                'description' => $activity->description,
            ]);
    }
}
