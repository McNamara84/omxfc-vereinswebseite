<?php

namespace App\Enums;

enum BookType: string
{
    case MaddraxDieDunkleZukunftDerErde = 'Maddrax - Die dunkle Zukunft der Erde';
    case MaddraxHardcover = 'Maddrax-Hardcover';
    case MissionMars = 'Mission Mars-Heftromane';
    case DasVolkDerTiefe = 'Das Volk der Tiefe';
    case ZweiTausendZwölfDasJahrDerApokalypse = '2012 - Das Jahr der Apokalypse';
    case DieAbenteurer = 'Die Abenteurer';

    public function key(): string
    {
        return match ($this) {
            self::MaddraxDieDunkleZukunftDerErde => 'maddrax',
            self::MaddraxHardcover => 'hardcovers',
            self::MissionMars => 'missionmars',
            self::DasVolkDerTiefe => 'volkdertiefe',
            self::ZweiTausendZwölfDasJahrDerApokalypse => '2012',
            self::DieAbenteurer => 'abenteurer',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::MaddraxDieDunkleZukunftDerErde => 'Maddrax',
            self::MaddraxHardcover => 'Maddrax-Hardcover',
            self::MissionMars => 'Mission Mars',
            self::DasVolkDerTiefe => 'Das Volk der Tiefe',
            self::ZweiTausendZwölfDasJahrDerApokalypse => '2012 – Das Jahr der Apokalypse',
            self::DieAbenteurer => 'Die Abenteurer',
        };
    }

    public static function fromKey(string $key): ?self
    {
        return collect(self::cases())
            ->first(fn (self $type): bool => $type->key() === $key);
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    public static function options(bool $includeAll = false): array
    {
        $options = collect(self::cases())
            ->map(fn (self $type): array => [
                'id' => $type->key(),
                'name' => $type->label(),
            ]);

        if ($includeAll) {
            $options->prepend(['id' => 'all', 'name' => 'Alle Serien']);
        }

        return $options->values()->all();
    }
}
