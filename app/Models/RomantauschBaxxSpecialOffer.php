<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RomantauschBaxxSpecialOffer extends Model
{
    use HasFactory;

    public const ACTIONS = [
        'romantausch_offer' => [
            'label' => 'Angebot erstellen',
            'rule_label' => 'Romantausch-Angebot',
            'description' => 'Baxx für neue Angebote in der Romantauschbörse.',
        ],
        'romantausch_request' => [
            'label' => 'Gesuch erstellen',
            'rule_label' => 'Romantausch-Gesuch',
            'description' => 'Baxx für neue Gesuche in der Romantauschbörse.',
        ],
        'romantausch_swap_complete' => [
            'label' => 'Tausch abschließen',
            'rule_label' => 'Romantausch abschließen',
            'description' => 'Baxx für vollständig abgeschlossene Tauschaktionen.',
        ],
    ];

    protected $fillable = [
        'action_key',
        'points',
        'every_count',
        'ends_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'every_count' => 'integer',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function scopeCurrentlyActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('ends_at', '>', now());
    }

    public function scopeForActionKey(Builder $query, string $actionKey): Builder
    {
        return $query->where('action_key', $actionKey);
    }

    /**
     * @return array<int, string>
     */
    public static function allowedActionKeys(): array
    {
        return array_keys(self::ACTIONS);
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    public static function actionOptions(): array
    {
        return collect(self::ACTIONS)
            ->map(fn (array $config, string $actionKey): array => [
                'id' => $actionKey,
                'name' => $config['label'],
            ])
            ->values()
            ->all();
    }

    public static function actionLabel(string $actionKey): string
    {
        return self::ACTIONS[$actionKey]['label'] ?? $actionKey;
    }

    public static function defaultRuleDefinition(string $actionKey): array
    {
        return match ($actionKey) {
            'romantausch_offer' => [
                'label' => self::ACTIONS[$actionKey]['rule_label'],
                'description' => '1 Baxx pro 10 neue Angebote in der Romantauschbörse.',
                'points' => 1,
                'every_count' => 10,
                'is_active' => true,
            ],
            'romantausch_request' => [
                'label' => self::ACTIONS[$actionKey]['rule_label'],
                'description' => 'Aktuell keine Baxx für neue Gesuche; kann im Adminbereich aktiviert werden.',
                'points' => 0,
                'every_count' => 1,
                'is_active' => false,
            ],
            'romantausch_swap_complete' => [
                'label' => self::ACTIONS[$actionKey]['rule_label'],
                'description' => '2 Baxx pro vollständig abgeschlossenem Tausch für jede beteiligte Seite.',
                'points' => 2,
                'every_count' => 1,
                'is_active' => true,
            ],
            default => throw new \InvalidArgumentException("Unbekannter Romantausch-Action-Key [{$actionKey}]."),
        };
    }
}