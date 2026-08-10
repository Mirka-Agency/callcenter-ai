<?php

namespace App\Services;

use App\Application\Call\Services\UnmatchedVoipExtensionService;
use App\Enums\IntegrationMetaFieldType;
use App\Models\CrmProvider;
use App\Models\EmployeeIntegrationMeta;
use App\Models\IntegrationMetaDefinition;
use App\Models\Organization;
use App\Models\OrganizationCrmConnection;
use App\Models\OrganizationUser;
use App\Models\OrganizationVoipConnection;
use App\Models\VoipProvider;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class EmployeeIntegrationMetaService
{
    /** @var list<class-string<Model>> */
    private const ALLOWED_CONNECTION_TYPES = [
        OrganizationCrmConnection::class,
        OrganizationVoipConnection::class,
    ];

    public static function connectionReference(Model $connection): string
    {
        return $connection::class.':'.$connection->getKey();
    }

    public static function resolveConnection(?string $reference, ?int $organizationId = null): ?Model
    {
        if (! $reference || ! str_contains($reference, ':')) {
            return null;
        }

        [$type, $id] = explode(':', $reference, 2);

        if (! in_array($type, self::ALLOWED_CONNECTION_TYPES, true) || ! class_exists($type)) {
            return null;
        }

        $query = $type::query()->with('provider.metaDefinitions');

        if ($organizationId !== null) {
            $query->where('organization_id', $organizationId);
        }

        return $query->find($id);
    }

    public static function providerForConnection(Model $connection): CrmProvider|VoipProvider|null
    {
        return $connection->provider ?? null;
    }

    public static function definitionsForConnection(Model $connection): Collection
    {
        $provider = self::providerForConnection($connection);

        if (! $provider) {
            return collect();
        }

        // Older Asterisk/Custom installs may lack the extension meta row until sync runs.
        if ($provider instanceof VoipProvider) {
            self::ensureVoipExtensionDefinition($provider);
        }

        return $provider->metaDefinitions()->orderBy('sort_order')->get();
    }

    private static function ensureVoipExtensionDefinition(VoipProvider $provider): void
    {
        $hasExtension = $provider->metaDefinitions()
            ->where('key', 'extension')
            ->exists();

        if ($hasExtension) {
            return;
        }

        app(IntegrationMetaDefinitionSynchronizer::class)->syncVoipProvider($provider);
        $provider->unsetRelation('metaDefinitions');
    }

    public static function formFieldsForConnection(?string $connectionReference, string $statePath = 'meta'): array
    {
        $connection = self::resolveConnection($connectionReference);

        if (! $connection) {
            return [];
        }

        return self::definitionsForConnection($connection)
            ->map(fn (IntegrationMetaDefinition $definition) => self::toFormField($definition, $statePath))
            ->all();
    }

    public static function toFormField(IntegrationMetaDefinition $definition, string $statePath = 'meta'): TextInput
    {
        $field = TextInput::make("{$statePath}.{$definition->key}")
            ->label($definition->name)
            ->required($definition->is_required);

        if ($definition->placeholder) {
            $field->placeholder($definition->placeholder);
        }

        if ($definition->help_text) {
            $field->helperText($definition->help_text);
        }

        return match ($definition->field_type) {
            IntegrationMetaFieldType::Email => $field->email(),
            IntegrationMetaFieldType::Tel => $field->tel(),
            IntegrationMetaFieldType::Number => $field->numeric(),
            IntegrationMetaFieldType::Password => $field->password()->revealable(),
            default => $field,
        };
    }

    /** @param array<int, array{connection: string, meta?: array<string, string|null>}> $assignments */
    public static function validateAssignments(array $assignments, int $organizationId): void
    {
        $errors = [];

        foreach ($assignments as $index => $assignment) {
            $connection = self::resolveConnection($assignment['connection'] ?? null, $organizationId);

            if (! filled($assignment['connection'] ?? null)) {
                continue;
            }

            if (! $connection) {
                $errors["integration_assignments.{$index}.connection"] = 'اتصال انتخاب‌شده معتبر نیست.';

                continue;
            }

            $meta = $assignment['meta'] ?? [];

            foreach (self::definitionsForConnection($connection) as $definition) {
                if (! $definition->is_required) {
                    continue;
                }

                if (blank($meta[$definition->key] ?? null)) {
                    $errors["integration_assignments.{$index}.meta.{$definition->key}"] = "فیلد {$definition->name} الزامی است.";
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    public static function assignVoipExtension(
        OrganizationUser $employee,
        OrganizationVoipConnection $connection,
        string $extension,
    ): void {
        $extension = trim($extension);

        if ($extension === '') {
            throw ValidationException::withMessages([
                'extension' => 'شماره داخلی الزامی است.',
            ]);
        }

        if ((int) $connection->organization_id !== (int) $employee->organization_id) {
            throw ValidationException::withMessages([
                'extension' => 'اتصال VoIP انتخاب‌شده معتبر نیست.',
            ]);
        }

        $existingEmployeeId = EmployeeIntegrationMeta::query()
            ->where('integratable_type', OrganizationVoipConnection::class)
            ->where('integratable_id', $connection->id)
            ->where('key', 'extension')
            ->where('value', $extension)
            ->where('organization_user_id', '!=', $employee->id)
            ->whereHas('employee', fn ($query) => $query->where('organization_id', $employee->organization_id))
            ->value('organization_user_id');

        if ($existingEmployeeId !== null) {
            throw ValidationException::withMessages([
                'extension' => __('ui.voip.unmatched_extension_conflict'),
            ]);
        }

        EmployeeIntegrationMeta::query()->updateOrCreate(
            [
                'organization_user_id' => $employee->id,
                'integratable_type' => OrganizationVoipConnection::class,
                'integratable_id' => $connection->id,
                'key' => 'extension',
            ],
            [
                'value' => $extension,
            ],
        );
    }

    /** @param array<int, array{connection: string, meta?: array<string, string|null>}> $assignments */
    public static function syncForEmployee(OrganizationUser $employee, array $assignments, ?int $organizationId = null): void
    {
        $organizationId ??= $employee->organization_id;

        self::validateAssignments($assignments, $organizationId);

        $employee->integrationMeta()->delete();

        /** @var list<array{connection: OrganizationVoipConnection, extension: string}> $voipExtensions */
        $voipExtensions = [];

        foreach ($assignments as $assignment) {
            $connection = self::resolveConnection($assignment['connection'] ?? null, $organizationId);

            if (! $connection) {
                continue;
            }

            foreach ($assignment['meta'] ?? [] as $key => $value) {
                if (blank($value)) {
                    continue;
                }

                $normalized = is_string($value) ? trim($value) : $value;

                if (blank($normalized)) {
                    continue;
                }

                EmployeeIntegrationMeta::query()->create([
                    'organization_user_id' => $employee->id,
                    'integratable_type' => $connection::class,
                    'integratable_id' => $connection->getKey(),
                    'key' => $key,
                    'value' => $normalized,
                ]);

                if (
                    $key === 'extension'
                    && $connection instanceof OrganizationVoipConnection
                    && is_string($normalized)
                ) {
                    $voipExtensions[] = [
                        'connection' => $connection,
                        'extension' => $normalized,
                    ];
                }
            }
        }

        self::backfillVoipExtensions($employee, $organizationId, $voipExtensions);
    }

    /**
     * @param list<array{connection: OrganizationVoipConnection, extension: string}> $voipExtensions
     */
    private static function backfillVoipExtensions(
        OrganizationUser $employee,
        int $organizationId,
        array $voipExtensions,
    ): void {
        if ($voipExtensions === []) {
            return;
        }

        $organization = Organization::query()->find($organizationId);

        if (! $organization) {
            return;
        }

        $backfill = app(UnmatchedVoipExtensionService::class);

        foreach ($voipExtensions as $item) {
            // All historical «تماس‌های بدون کارشناس» for this extension, not only recent days.
            $backfill->backfillCalls(
                organization: $organization,
                extension: $item['extension'],
                connectionId: (int) $item['connection']->id,
                days: null,
                organizationUserId: (int) $employee->id,
            );
        }
    }

    public static function assignmentsFromEmployee(OrganizationUser $employee): array
    {
        return $employee->integrationMeta()
            ->with('integratable.provider')
            ->get()
            ->groupBy(fn (EmployeeIntegrationMeta $meta) => $meta->integratable_type.':'.$meta->integratable_id)
            ->map(function (Collection $group, string $reference) {
                return [
                    'connection' => $reference,
                    'meta' => $group->pluck('value', 'key')->all(),
                ];
            })
            ->values()
            ->all();
    }

    public static function connectionOptionsForOrganization(int $organizationId): array
    {
        $crm = OrganizationCrmConnection::query()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->with('provider')
            ->get()
            ->mapWithKeys(fn (OrganizationCrmConnection $connection) => [
                self::connectionReference($connection) => 'CRM: '.$connection->provider->name.' · '.$connection->name,
            ])
            ->all();

        $voip = OrganizationVoipConnection::query()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->with('provider')
            ->get()
            ->mapWithKeys(fn (OrganizationVoipConnection $connection) => [
                self::connectionReference($connection) => 'VoIP: '.$connection->provider->name.' · '.$connection->name,
            ])
            ->all();

        return $crm + $voip;
    }

    /** @return list<array{key: string, name: string, required: bool, type: string, placeholder: ?string}> */
    public static function metaFieldDefinitionsForReference(?string $reference, ?int $organizationId = null): array
    {
        $connection = self::resolveConnection($reference, $organizationId);

        if (! $connection) {
            return [];
        }

        return self::definitionsForConnection($connection)
            ->map(fn (IntegrationMetaDefinition $definition) => [
                'key' => $definition->key,
                'name' => $definition->name,
                'required' => $definition->is_required,
                'type' => $definition->field_type->value,
                'placeholder' => $definition->placeholder,
            ])
            ->values()
            ->all();
    }
}
