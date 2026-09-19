<?php

namespace App\Infrastructure\Crm;

use App\Domain\Crm\Contracts\CrmAdapterInterface;
use App\Domain\Crm\Enums\CrmProviderCode;
use App\Domain\Crm\Exceptions\CrmAdapterNotFoundException;
use App\Infrastructure\Crm\Adapters\DidarCrmAdapter;
use App\Infrastructure\Crm\Adapters\DynamicsCrmAdapter;

class CrmAdapterRegistry
{
    /** @var array<string, class-string<CrmAdapterInterface>> */
    private array $adapters = [];

    public function __construct()
    {
        $this->register(CrmProviderCode::Didar, DidarCrmAdapter::class);
        $this->register(CrmProviderCode::Dynamics, DynamicsCrmAdapter::class);
    }

    public function register(CrmProviderCode|string $provider, string $adapterClass): void
    {
        $code = $provider instanceof CrmProviderCode ? $provider->value : $provider;
        $this->adapters[$code] = $adapterClass;
    }

    public function resolve(CrmProviderCode|string $provider): CrmAdapterInterface
    {
        $code = $provider instanceof CrmProviderCode ? $provider->value : $provider;

        if (! isset($this->adapters[$code])) {
            throw CrmAdapterNotFoundException::forProvider($code);
        }

        return app($this->adapters[$code]);
    }

    /** @return array<string, class-string<CrmAdapterInterface>> */
    public function all(): array
    {
        return $this->adapters;
    }
}
