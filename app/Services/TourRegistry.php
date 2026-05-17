<?php

namespace App\Services;

use App\Support\Tours\TourDefinition;
use InvalidArgumentException;

class TourRegistry
{
    /**
     * @var array<string, TourDefinition>|null
     */
    private ?array $cachedDefinitions = null;

    /**
     * @return array<int, TourDefinition>
     */
    public function definitions(): array
    {
        return array_values($this->definitionsByKey());
    }

    public function definition(string $key): TourDefinition
    {
        $definition = $this->definitionsByKey()[$key] ?? null;

        if (! $definition instanceof TourDefinition) {
            throw new InvalidArgumentException("Unbekannte Tour [{$key}].");
        }

        return $definition;
    }

    /**
     * @return array<int, TourDefinition>
     */
    public function autoAssignableOnMemberApproval(): array
    {
        return array_values(array_filter(
            $this->definitions(),
            static fn (TourDefinition $definition): bool => $definition->autoAssignOnMemberApproval,
        ));
    }

    /**
     * @return array<int, TourDefinition>
     */
    public function selfServiceEnabled(): array
    {
        return array_values(array_filter(
            $this->definitions(),
            static fn (TourDefinition $definition): bool => $definition->selfServiceEnabled,
        ));
    }

    /**
     * @return array<string, TourDefinition>
     */
    private function definitionsByKey(): array
    {
        if (is_array($this->cachedDefinitions)) {
            return $this->cachedDefinitions;
        }

        $definitions = [];

        foreach (config('tours', []) as $key => $definition) {
            if (! is_string($key) || ! is_array($definition)) {
                continue;
            }

            $definitions[$key] = TourDefinition::fromArray($key, $definition);
        }

        return $this->cachedDefinitions = $definitions;
    }
}