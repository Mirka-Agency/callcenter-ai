<?php

namespace App\Services;

use App\Enums\IntegrationMetaFieldType;
use App\Models\CrmProvider;
use App\Models\EmployeeIntegrationMeta;
use App\Models\IntegrationMetaDefinition;
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
    public static function connectionReference(Model $connection): string
    {
        return $connection::class.':'.$connection->getKey();
    }

    public static function resolveConnection(?string $reference): ?Model
    {
        if (! $reference || ! str_contains($reference, ':')) {
            return null;
        }

        [$type, $id] = explode(':', $reference, 2);

        if (! class_exists($type)) {
            return null;
        }

        return $type::query()->with('provider.metaDefinitions')->find($id);
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

        return $provider->metaDefinitions()->orderBy('sort_order')->get();
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
    public static function validateAssignments(array $assignments): void
    {
        $errors = [];

        foreach ($assignments as $index => $assignment) {
            $connection = self::resolveConnection($assignment['connection'] ?? null);

            if (! $connection) {
                continue;
            }

            $meta = $assignment['meta'] ?? [];

            foreach (self::definitionsForConnection($connection) as $definition) {
                if (! $definition->is_required) {
                    continue;
                }

                if (blank($meta[$definition->key] ?? null)) {
                    $errors["integration_assignments.{$index}.meta.{$definition->key}"] = "The {$definition->name} field is required.";
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @param array<int, array{connection: string, meta?: array<string, string|null>}> $assignments */
    public static function syncForEmployee(OrganizationUser $employee, array $assignments): void
    {
        self::validateAssignments($assignments);

        $employee->integrationMeta()->delete();

        foreach ($assignments as $assignment) {
            $connection = self::resolveConnection($assignment['connection'] ?? null);

            if (! $connection) {
                continue;
            }

            foreach ($assignment['meta'] ?? [] as $key => $value) {
                if (blank($value)) {
                    continue;
                }

                EmployeeIntegrationMeta::query()->create([
                    'organization_user_id' => $employee->id,
                    'integratable_type' => $connection::class,
                    'integratable_id' => $connection->getKey(),
                    'key' => $key,
                    'value' => $value,
                ]);
            }
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

        // Use plain arrays: Eloquent Collection::merge() expects models and calls getKey().
        return $crm + $voip;
    }
}
