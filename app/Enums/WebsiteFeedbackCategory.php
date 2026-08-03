<?php

namespace App\Enums;

enum WebsiteFeedbackCategory: string
{
    case Problem = 'problem';
    case Idea = 'idea';
    case Praise = 'praise';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Problem => 'Problem',
            self::Idea => 'Idee',
            self::Praise => 'Lob',
            self::Other => 'Sonstiges',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $category) {
            $options[$category->value] = $category->label();
        }

        return $options;
    }
}
