<?php

namespace App\Contracts;

interface ProvidesEmployeeIntegrationMeta
{
    /**
     * @return list<array{
     *     key: string,
     *     name: string,
     *     field_type: string,
     *     is_required: bool,
     *     placeholder?: string|null,
     *     help_text?: string|null,
     *     sort_order?: int
     * }>
     */
    public static function employeeIntegrationMetaDefinitions(): array;
}
