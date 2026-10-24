<?php

namespace App\Filament\Support;

use App\Domain\Platform\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Organizations\OrganizationResource;
use App\Models\User;
use App\Services\Admin\AdminAuditLogger;
use App\Services\Sales\LeadConverter;
use App\Support\JalaliDate;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Shared sales-pipeline columns, filters and actions for demo requests, consultation requests and contact messages.
 */
class LeadPipelineActions
{
    /** @return list<TextColumn> */
    public static function columns(): array
    {
        return [
            TextColumn::make('lead_status')
                ->label(__('admin.leads.status'))
                ->badge()
                ->formatStateUsing(fn (?LeadStatus $state): string => $state?->label() ?? LeadStatus::New->label())
                ->color(fn (?LeadStatus $state): string => $state?->color() ?? 'gray')
                ->sortable(),
            TextColumn::make('assignee.name')
                ->label(__('admin.leads.assignee'))
                ->placeholder(__('admin.leads.unassigned'))
                ->toggleable(),
            TextColumn::make('follow_up_at')
                ->label(__('admin.leads.follow_up_at'))
                ->jalaliDateTime()
                ->color(fn (Model $record): ?string => $record->follow_up_at?->isPast() ? 'danger' : null)
                ->placeholder('—')
                ->sortable()
                ->toggleable(),
            TextColumn::make('source')
                ->label(__('admin.leads.source'))
                ->placeholder('—')
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('response_time')
                ->label(__('admin.leads.response_time'))
                ->state(function (Model $record): ?string {
                    if ($record->first_contacted_at) {
                        return __('admin.leads.hours', ['value' => (int) round($record->created_at->diffInMinutes($record->first_contacted_at) / 60)]);
                    }

                    return $record->created_at->lt(now()->subHours($record::SLA_HOURS)) ? __('admin.leads.sla_breached') : null;
                })
                ->color(fn (Model $record): ?string => ! $record->first_contacted_at && $record->created_at->lt(now()->subHours($record::SLA_HOURS)) ? 'danger' : null)
                ->placeholder(__('admin.leads.waiting'))
                ->toggleable(),
        ];
    }

    /** @return list<SelectFilter|Filter> */
    public static function filters(): array
    {
        return [
            SelectFilter::make('lead_status')
                ->label(__('admin.leads.status'))
                ->options(LeadStatus::options())
                ->multiple(),
            SelectFilter::make('assigned_to_user_id')
                ->label(__('admin.leads.assignee'))
                ->options(fn (): array => self::staffOptions()),
            Filter::make('mine')
                ->label(__('admin.leads.only_mine'))
                ->query(fn (Builder $query): Builder => $query->where('assigned_to_user_id', auth()->id())),
            Filter::make('sla_breached')
                ->label(__('admin.leads.sla_breached'))
                ->query(fn (Builder $query): Builder => $query->breachingSla()),
            Filter::make('follow_up_due')
                ->label(__('admin.leads.follow_up_due'))
                ->query(fn (Builder $query): Builder => $query->followUpDue()),
        ];
    }

    public static function group(bool $canConvert = true): ActionGroup
    {
        return ActionGroup::make(array_values(array_filter([
            self::changeStatus(),
            self::assignToMe(),
            self::assign(),
            self::followUp(),
            self::addNote(),
            $canConvert ? self::convert() : null,
        ])))->label(__('admin.leads.pipeline'))->icon(Heroicon::OutlinedFunnel)->button()->color('gray');
    }

    public static function changeStatus(): Action
    {
        return Action::make('changeLeadStatus')
            ->label(__('admin.leads.change_status'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->fillForm(fn (Model $record): array => ['lead_status' => $record->lead_status?->value, 'lost_reason' => $record->lost_reason])
            ->schema([
                Select::make('lead_status')
                    ->label(__('admin.leads.status'))
                    ->options(collect(LeadStatus::options())->except(LeadStatus::Converted->value)->all())
                    ->required()
                    ->live()
                    ->native(false),
                TextInput::make('lost_reason')
                    ->label(__('admin.leads.lost_reason'))
                    ->visible(fn (Get $get): bool => $get('lead_status') === LeadStatus::Lost->value)
                    ->required(fn (Get $get): bool => $get('lead_status') === LeadStatus::Lost->value)
                    ->maxLength(255),
            ])
            ->action(function (Model $record, array $data): void {
                $record->lost_reason = $data['lost_reason'] ?? null;
                $record->moveTo(LeadStatus::from($data['lead_status']));
                self::saved();
            });
    }

    public static function assignToMe(): Action
    {
        return Action::make('assignLeadToMe')
            ->label(__('admin.leads.assign_to_me'))
            ->icon(Heroicon::OutlinedHandRaised)
            ->visible(fn (Model $record): bool => $record->assigned_to_user_id !== auth()->id())
            ->action(function (Model $record): void {
                $record->update(['assigned_to_user_id' => auth()->id()]);
                self::saved();
            });
    }

    public static function assign(): Action
    {
        return Action::make('assignLead')
            ->label(__('admin.leads.assign'))
            ->icon(Heroicon::OutlinedUserPlus)
            ->fillForm(fn (Model $record): array => ['assigned_to_user_id' => $record->assigned_to_user_id])
            ->schema([
                Select::make('assigned_to_user_id')
                    ->label(__('admin.leads.assignee'))
                    ->options(fn (): array => self::staffOptions())
                    ->native(false),
            ])
            ->action(function (Model $record, array $data): void {
                $record->update(['assigned_to_user_id' => $data['assigned_to_user_id'] ?? null]);
                self::saved();
            });
    }

    public static function followUp(): Action
    {
        return Action::make('scheduleFollowUp')
            ->label(__('admin.leads.schedule_follow_up'))
            ->icon(Heroicon::OutlinedCalendarDays)
            ->fillForm(fn (Model $record): array => ['follow_up_at' => $record->follow_up_at])
            ->schema([
                DateTimePicker::make('follow_up_at')->label(__('admin.leads.follow_up_at'))->jalali(),
            ])
            ->action(function (Model $record, array $data): void {
                $record->update(['follow_up_at' => $data['follow_up_at'] ?? null]);
                self::saved();
            });
    }

    public static function addNote(): Action
    {
        return Action::make('addLeadNote')
            ->label(__('admin.notes.add'))
            ->icon(Heroicon::OutlinedPencilSquare)
            ->schema([
                Textarea::make('body')->label(__('admin.notes.body'))->required()->rows(4)->maxLength(5000),
            ])
            ->action(function (Model $record, array $data): void {
                $record->adminNotes()->create(['user_id' => auth()->id(), 'body' => $data['body']]);

                if ($record->first_contacted_at === null) {
                    $record->forceFill(['first_contacted_at' => now()])->save();
                }

                app(AdminAuditLogger::class)->log('note_added', $record, ['after' => ['body' => $data['body']]]);
                self::saved();
            });
    }

    public static function convert(): Action
    {
        return Action::make('convertLead')
            ->label(__('admin.leads.convert'))
            ->icon(Heroicon::OutlinedBuildingOffice2)
            ->color('success')
            ->visible(fn (Model $record): bool => $record->converted_organization_id === null)
            ->fillForm(fn (Model $record): array => [
                'title' => $record->company_name ?? $record->full_name,
                'employer_name' => $record->full_name,
                'employer_phone' => $record->phone ?? null,
                'employer_email' => $record->email ?? null,
            ])
            ->schema([
                TextInput::make('title')->label(__('filament.fields.title'))->required()->maxLength(255),
                TextInput::make('employer_name')->label(__('admin.leads.employer_name'))->required()->maxLength(255),
                TextInput::make('employer_email')->label(__('filament.fields.email_address'))->email()->required()
                    ->rule(Rule::unique('users', 'email')),
                TextInput::make('employer_phone')->label(__('filament.fields.phone'))->tel()
                    ->rule(Rule::unique('users', 'phone')),
            ])
            ->modalDescription(__('admin.leads.convert_description'))
            ->action(function (Model $record, array $data): void {
                $result = app(LeadConverter::class)->convert($record, $data);

                Notification::make()
                    ->title(__('admin.leads.converted'))
                    ->body(__('admin.leads.converted_body', ['email' => $data['employer_email'], 'password' => $result['password']]))
                    ->success()
                    ->persistent()
                    ->actions([
                        Action::make('openOrganization')
                            ->label(__('admin.common.open'))
                            ->url(OrganizationResource::getUrl('overview', ['record' => $result['organization']])),
                    ])
                    ->send();
            });
    }

    public static function infolistSection(): Section
    {
        return Section::make(__('admin.leads.pipeline'))
            ->columns(3)
            ->schema([
                TextEntry::make('lead_status')->label(__('admin.leads.status'))->badge()
                    ->formatStateUsing(fn (?LeadStatus $state): string => $state?->label() ?? LeadStatus::New->label())
                    ->color(fn (?LeadStatus $state): string => $state?->color() ?? 'gray'),
                TextEntry::make('assignee.name')->label(__('admin.leads.assignee'))->placeholder(__('admin.leads.unassigned')),
                TextEntry::make('follow_up_at')->label(__('admin.leads.follow_up_at'))->jalaliDateTime()->placeholder('—'),
                TextEntry::make('first_contacted_at')->label(__('admin.leads.first_contacted_at'))->jalaliDateTime()->placeholder(__('admin.leads.waiting')),
                TextEntry::make('source')->label(__('admin.leads.source'))->placeholder('—'),
                TextEntry::make('utm')->label('UTM')
                    ->state(fn (Model $record): ?string => collect([$record->utm_source, $record->utm_medium, $record->utm_campaign])->filter()->implode(' / ') ?: null)
                    ->placeholder('—'),
                TextEntry::make('lost_reason')->label(__('admin.leads.lost_reason'))->visible(fn (Model $record): bool => $record->lead_status === LeadStatus::Lost),
                TextEntry::make('convertedOrganization.title')->label(__('admin.leads.converted_organization'))
                    ->url(fn (Model $record): ?string => $record->converted_organization_id ? OrganizationResource::getUrl('overview', ['record' => $record->converted_organization_id]) : null)
                    ->visible(fn (Model $record): bool => $record->converted_organization_id !== null),
                RepeatableEntry::make('adminNotes')
                    ->label(__('admin.notes.heading'))
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('author.name')->hiddenLabel()->size('xs')->color('gray')
                            ->suffix(fn ($record): string => ' · '.JalaliDate::datetime($record->created_at)),
                        TextEntry::make('body')->hiddenLabel(),
                    ])
                    ->placeholder(__('admin.notes.empty')),
            ]);
    }

    /** @return array<int, string> */
    public static function staffOptions(): array
    {
        return User::query()
            ->whereIn('role', [UserRole::SuperAdmin->value, UserRole::Admin->value])
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    private static function saved(): void
    {
        Notification::make()->title(__('admin.common.saved'))->success()->send();
    }
}
