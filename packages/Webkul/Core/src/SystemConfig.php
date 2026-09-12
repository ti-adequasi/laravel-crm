<?php

namespace Webkul\Core;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Webkul\Core\Repositories\CoreConfigRepository;
use Webkul\Core\SystemConfig\Item;
use Webkul\Tenant\Support\CurrentTenant;

class SystemConfig
{
    /**
     * Items array.
     */
    public array $items = [];

    /**
     * Create a new class instance.
     *
     * @return void
     */
    public function __construct(protected CoreConfigRepository $coreConfigRepository) {}

    /**
     * Add Item.
     */
    public function addItem(Item $item): void
    {
        $this->items[] = $item;
    }

    /**
     * Get all configuration items.
     */
    public function getItems(): Collection
    {
        if (! $this->items) {
            $this->prepareConfigurationItems();
        }

        return collect($this->items)
            ->sortBy('sort');
    }

    /**
     * Retrieve Core Config
     */
    private function retrieveCoreConfig(): array
    {
        static $items;

        if ($items) {
            return $items;
        }

        return $items = config('core_config');
    }

    /**
     * Prepare configuration items.
     */
    public function prepareConfigurationItems()
    {
        $configWithDotNotation = [];

        foreach ($this->retrieveCoreConfig() as $item) {
            $configWithDotNotation[$item['key']] = $item;
        }

        $configs = Arr::undot(Arr::dot($configWithDotNotation));

        foreach ($configs as $configItem) {
            $subConfigItems = $this->processSubConfigItems($configItem);

            $this->addItem(new Item(
                children: $subConfigItems,
                fields: $configItem['fields'] ?? null,
                icon: $configItem['icon'] ?? null,
                key: $configItem['key'],
                name: trans($configItem['name']),
                route: $configItem['route'] ?? null,
                info: trans($configItem['info']) ?? null,
                sort: $configItem['sort'],
            ));
        }
    }

    /**
     * Process sub config items.
     */
    private function processSubConfigItems($configItem): Collection
    {
        return collect($configItem)
            ->sortBy('sort')
            ->filter(fn ($value) => is_array($value) && isset($value['name']))
            ->map(function ($subConfigItem) {
                $configItemChildren = $this->processSubConfigItems($subConfigItem);

                return new Item(
                    children: $configItemChildren,
                    fields: $subConfigItem['fields'] ?? null,
                    icon: $subConfigItem['icon'] ?? null,
                    key: $subConfigItem['key'],
                    name: trans($subConfigItem['name']),
                    info: trans($subConfigItem['info']) ?? null,
                    route: $subConfigItem['route'] ?? null,
                    sort: $subConfigItem['sort'] ?? null,
                );
            });
    }

    /**
     * Get active configuration item.
     */
    public function getActiveConfigurationItem(): ?Item
    {
        if (! $slug = request()->route('slug')) {
            return null;
        }

        $activeItem = $this->getItems()->where('key', $slug)->first() ?? null;

        if (! $activeItem) {
            return null;
        }

        if ($slug2 = request()->route('slug2')) {
            $activeItem = $activeItem->getChildren()[$slug2];
        }

        return $activeItem;
    }

    /**
     * Get config field.
     */
    public function getConfigField(string $fieldName): ?array
    {
        foreach ($this->retrieveCoreConfig() as $coreData) {
            if (! isset($coreData['fields'])) {
                continue;
            }

            foreach ($coreData['fields'] as $field) {
                $name = $coreData['key'].'.'.$field['name'];

                if ($name == $fieldName) {
                    return $field;
                }
            }
        }

        return null;
    }

    /**
     * Get default config.
     */
    private function getDefaultConfig(string $field): mixed
    {
        $configFieldInfo = $this->getConfigField($field);

        $fields = explode('.', $field);

        array_shift($fields);

        $field = implode('.', $fields);

        return Config::get($field, $configFieldInfo['default'] ?? null);
    }

    /**
     * Retrieve information for configuration
     *
     * Two-level fallback when a tenant is bound: that tenant's own row
     * first (a real per-tenant override — e.g. its own Magic AI API key),
     * else the global row (tenant_id NULL), else the field's static
     * default. A super-admin (no tenant bound) skips straight to the
     * global row, exactly as before per-tenant config existed.
     */
    public function getConfigData(string $field): mixed
    {
        $tenantId = CurrentTenant::id();

        if ($tenantId !== null) {
            $tenantConfigValue = $this->coreConfigRepository->findOneWhere([
                'code' => $field,
                'tenant_id' => $tenantId,
            ]);

            if ($tenantConfigValue) {
                return $tenantConfigValue->value;
            }
        }

        $coreConfigValue = $this->coreConfigRepository->findOneWhere([
            'code' => $field,
            'tenant_id' => null,
        ]);

        if (! $coreConfigValue) {
            return $this->getDefaultConfig($field);
        }

        return $coreConfigValue->value;
    }
}
