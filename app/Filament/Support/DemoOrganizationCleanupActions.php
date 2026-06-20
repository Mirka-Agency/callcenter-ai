<?php

namespace App\Filament\Support;

use App\Enums\UserRole;
use App\Exceptions\DemoCleanupException;
use App\Filament\Resources\Organizations\OrganizationResource;
use App\Models\Organization;
use App\Services\Demo\DemoOrganizationCleanupService;
use App\Services\Demo\DemoPersonProvisioner;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;

final class DemoOrganizationCleanupActions
{
    public static function deleteAll(): Action
    {
        return Action::make('deleteAllDemoOrganizations')
            ->label(__('filament.actions.delete_all_demo_organizations'))
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->button()
            ->visible(fn (): bool => auth()->user()?->role === UserRole::SuperAdmin)
            ->requiresConfirmation()
            ->modalIcon(Heroicon::OutlinedExclamationTriangle)
            ->modalIconColor('danger')
            ->modalHeading(__('filament.demo_cleanup.delete_all_heading'))
            ->modalDescription(fn (): string => app(DemoOrganizationCleanupService::class)
                ->summarizeAll()
                ->toDeleteAllModalDescription())
            ->modalSubmitActionLabel(__('filament.actions.delete_all_demo_organizations'))
            ->modalCancelActionLabel(__('filament.actions.cancel'))
            ->disabled(fn (): bool => app(DemoOrganizationCleanupService::class)->demoOrganizationCount() === 0)
            ->tooltip(fn (): ?string => app(DemoOrganizationCleanupService::class)->demoOrganizationCount() === 0
                ? __('filament.demo_cleanup.no_demo_data')
                : null)
            ->action(function (): void {
                try {
                    $summary = app(DemoOrganizationCleanupService::class)->deleteAll();
                } catch (DemoCleanupException $exception) {
                    Notification::make()
                        ->title(__('filament.demo_cleanup.failed'))
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title(__('filament.demo_cleanup.deleted_all_success'))
                    ->body(__('filament.demo_cleanup.deleted_summary', [
                        'organizations' => number_format($summary->organizations),
                        'total' => number_format($summary->totalRecords()),
                    ]))
                    ->success()
                    ->send();
            });
    }

    public static function addSinglePerson(): Action
    {
        return Action::make('addSingleDemoPerson')
            ->label(__('filament.actions.add_single_demo_person'))
            ->icon(Heroicon::OutlinedUserPlus)
            ->color('primary')
            ->visible(fn (): bool => auth()->user()?->role === UserRole::SuperAdmin)
            ->form([
                TextInput::make('phone')
                    ->label(__('filament.fields.phone'))
                    ->required()
                    ->maxLength(20),
                TextInput::make('name')
                    ->label(__('filament.fields.name'))
                    ->required()
                    ->maxLength(100),
                TextInput::make('email')
                    ->label(__('filament.fields.email'))
                    ->email()
                    ->required()
                    ->maxLength(255),
                TextInput::make('password')
                    ->label(__('filament.fields.password'))
                    ->required()
                    ->default('123456789')
                    ->maxLength(100),
            ])
            ->action(function (array $data): void {
                try {
                    $organization = app(DemoPersonProvisioner::class)->provision(
                        phone: $data['phone'],
                        name: $data['name'],
                        email: $data['email'],
                        password: $data['password'],
                    );
                } catch (\Throwable $exception) {
                    Notification::make()
                        ->title(__('filament.demo_import.failed'))
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title(__('filament.demo_import.single_success'))
                    ->body(__('filament.demo_import.single_body', [
                        'name' => $data['name'],
                        'email' => $data['email'],
                        'phone' => $data['phone'],
                        'password' => $data['password'],
                    ]))
                    ->success()
                    ->send();
            });
    }

    public static function importCsv(): Action
    {
        return Action::make('importDemoCsv')
            ->label(__('filament.actions.import_demo_csv'))
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->color('primary')
            ->visible(fn (): bool => auth()->user()?->role === UserRole::SuperAdmin)
            ->form([
                FileUpload::make('csv_file')
                    ->label(__('filament.demo_import.csv_file_label'))
                    ->helperText(__('filament.demo_import.csv_format_hint'))
                    ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                    ->disk('local')
                    ->directory('demo-csv-imports')
                    ->required(),
            ])
            ->action(function (array $data): void {
                $path = Storage::disk('local')->path($data['csv_file']);

                if (! file_exists($path)) {
                    Notification::make()
                        ->title(__('filament.demo_import.failed'))
                        ->body(__('filament.demo_import.file_not_found'))
                        ->danger()
                        ->send();

                    return;
                }

                $provisioner = app(DemoPersonProvisioner::class);
                $succeeded = 0;
                $failed = 0;
                $errors = [];

                $handle = fopen($path, 'r');

                if ($handle === false) {
                    Notification::make()
                        ->title(__('filament.demo_import.failed'))
                        ->body(__('filament.demo_import.file_not_found'))
                        ->danger()
                        ->send();

                    return;
                }

                $rowNumber = 0;

                while (($row = fgetcsv($handle)) !== false) {
                    $rowNumber++;

                    if (count($row) < 4) {
                        $failed++;
                        $errors[] = "ردیف {$rowNumber}: ستون‌های ناقص";

                        continue;
                    }

                    [$phone, $name, $email, $password] = $row;

                    $phone = trim($phone);
                    $name = trim($name);
                    $email = trim($email);
                    $password = trim($password);

                    if (empty($phone) || empty($name) || empty($email) || empty($password)) {
                        $failed++;
                        $errors[] = "ردیف {$rowNumber}: مقدار خالی";

                        continue;
                    }

                    try {
                        $provisioner->provision($phone, $name, $email, $password);
                        $succeeded++;
                    } catch (\Throwable $exception) {
                        $failed++;
                        $errors[] = "ردیف {$rowNumber} ({$phone}): ".$exception->getMessage();
                    }
                }

                fclose($handle);

                Storage::disk('local')->delete($data['csv_file']);

                if ($succeeded > 0) {
                    Notification::make()
                        ->title(__('filament.demo_import.csv_success'))
                        ->body(__('filament.demo_import.csv_summary', [
                            'succeeded' => $succeeded,
                            'failed' => $failed,
                        ]))
                        ->success()
                        ->send();
                }

                if ($failed > 0) {
                    Notification::make()
                        ->title(__('filament.demo_import.csv_partial_failure'))
                        ->body(implode("\n", array_slice($errors, 0, 5)))
                        ->warning()
                        ->send();
                }
            });
    }

    public static function managementGroup(): ActionGroup
    {
        return ActionGroup::make([
            self::addSinglePerson(),
            self::importCsv(),
            self::deleteAll(),
        ])
            ->label(__('filament.demo_cleanup.management_group'))
            ->icon(Heroicon::OutlinedBeaker)
            ->color('danger')
            ->button()
            ->visible(fn (): bool => auth()->user()?->role === UserRole::SuperAdmin);
    }

    public static function deleteRecord(): Action
    {
        return Action::make('deleteDemoData')
            ->label(__('filament.actions.delete_demo_data'))
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (Organization $record): bool => auth()->user()?->role === UserRole::SuperAdmin && $record->is_demo)
            ->requiresConfirmation()
            ->modalIcon(Heroicon::OutlinedExclamationTriangle)
            ->modalIconColor('danger')
            ->modalHeading(__('filament.demo_cleanup.delete_one_heading'))
            ->modalDescription(fn (Organization $record): string => app(DemoOrganizationCleanupService::class)
                ->summarizeOrganization($record)
                ->toFilamentDescription())
            ->modalSubmitActionLabel(__('filament.actions.delete_demo_data'))
            ->modalCancelActionLabel(__('filament.actions.cancel'))
            ->action(function (Organization $record, \Livewire\Component $livewire): void {
                try {
                    $summary = app(DemoOrganizationCleanupService::class)->deleteOrganization($record);
                } catch (DemoCleanupException $exception) {
                    Notification::make()
                        ->title(__('filament.demo_cleanup.failed'))
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title(__('filament.demo_cleanup.deleted_one_success', ['title' => $record->title]))
                    ->body(__('filament.demo_cleanup.deleted_summary', [
                        'organizations' => number_format($summary->organizations),
                        'total' => number_format($summary->totalRecords()),
                    ]))
                    ->success()
                    ->send();

                $livewire->redirect(OrganizationResource::getUrl('index'));
            });
    }
}
