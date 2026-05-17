<?php

namespace App\Services;

use App\Support\Tours\TourDefinition;
use InvalidArgumentException;

class TourRegistry
{
    /**
     * @return array<int, TourDefinition>
     */
    public function definitions(): array
    {
        $definitions = [];

        foreach (config('tours', []) as $key => $definition) {
            if (! is_string($key) || ! is_array($definition)) {
                continue;
            }

            $definitions[] = TourDefinition::fromArray($key, $definition);
        }

        return $definitions;
    }

    public function definition(string $key): TourDefinition
    {
        $definition = config("tours.{$key}");

        if (! is_array($definition)) {
            throw new InvalidArgumentException("Unbekannte Tour [{$key}].");
        }

        return TourDefinition::fromArray($key, $definition);
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
}