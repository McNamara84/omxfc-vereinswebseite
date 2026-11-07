<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\FontMetrics;
use InvalidArgumentException;

final class RpgCharacterSheetLayoutService
{
    private const FONT_SIZES = [7.6, 7.2, 6.7, 6.5];

    private const TRAITS = ['advantages' => 'Vorteile', 'disadvantages' => 'Nachteile', 'trainings' => 'Ausbildung', 'professions' => 'Beruf'];

    private readonly FontMetrics $metrics;

    private readonly string $font;

    public function __construct()
    {
        // Measuring bundled DejaVu Sans needs neither rendering nor a writable font cache.
        $this->metrics = (new Dompdf)->getFontMetrics();
        $this->font = $this->metrics->getFont('DejaVu Sans');
    }

    public function prepare(array $characterData): array
    {
        // Includes the title and ten bordered rows; regression tests measure the
        // actual Dompdf boxes so template/font changes cannot silently misalign it.
        $skillsHeight = $this->pt(49.75);
        $weaponBlankRows = max(0, 7 - max(1, count($characterData['weapons'] ?? [])));
        // Four rows including blanks fit the compact default armor section.
        $armorBlankRows = max(0, 4 - max(1, count($characterData['armor'] ?? [])));
        $armorHeight = max(25, 12 + count($characterData['armor'] ?? []) * 3.5);
        $baseHeight = $this->pt(96 - ($armorHeight - 25));
        $traitsBudget = max($this->pt(16), $baseHeight + $this->pt(-1 - 24 + $weaponBlankRows * 5 + $armorBlankRows * 2) - $skillsHeight);
        $traits = $this->traits($characterData, $traitsBudget);
        $traitsHeight = array_sum(array_column($traits['rows'], 'height'));
        $attributesHeight = max($this->pt(24), $baseHeight - $this->pt(1) - $skillsHeight - $traitsHeight);
        $frameHeight = $traitsHeight + $this->pt(1) + $attributesHeight + $skillsHeight;
        // Borrow space only from empty writing rows, keeping all populated rows.
        $extraHeight = max(0, $frameHeight - $baseHeight);
        $removedWeaponRows = min($weaponBlankRows, (int) ceil($extraHeight / $this->pt(5)));
        $extraHeight = max(0, $extraHeight - $this->pt($removedWeaponRows * 5));
        $removedArmorRows = min($armorBlankRows, (int) ceil($extraHeight / $this->pt(2)));
        $frameContentHeight = $frameHeight - $this->pt(2.9);
        $withPortrait = ! empty($characterData['portrait']);
        $descriptionHeight = $frameContentHeight - $this->pt($withPortrait ? 55.25 : 2);
        // Reserve the label, 1mm vertical padding on each side and the portrait separator.
        $descriptionTextHeight = $descriptionHeight - 12 - $this->pt($withPortrait ? 2.25 : 2);

        $characterData['layout'] = [
            'traits' => $traits,
            'attributes_height' => $attributesHeight,
            'frame_content_height' => $frameContentHeight,
            'description_height' => $descriptionHeight,
            'description' => $this->fitText((string) ($characterData['description'] ?? ''), $this->pt(85.1), $descriptionTextHeight),
            'weapon_blank_rows' => $weaponBlankRows - $removedWeaponRows,
            'armor_blank_rows' => $armorBlankRows - $removedArmorRows,
            'weapon_height' => $this->pt(52 - $removedWeaponRows * 5),
            'armor_height' => $this->pt($armorHeight - $removedArmorRows * 2),
        ];

        return $characterData;
    }

    /** Fit plain text using the same font and explicit line heights as the PDF. */
    public function fitText(string $text, float $width, float $height): array
    {
        if ($width < 12 || $height < $this->lineHeight(6.5)) {
            throw new InvalidArgumentException('The text area must fit at least one line at the minimum font size.');
        }
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        foreach (self::FONT_SIZES as $size) {
            $lineHeight = $this->lineHeight($size);
            $maximumLines = (int) floor($height / $lineHeight);
            if ($maximumLines < 1) {
                continue;
            }
            $wrapped = $this->wrap($text, $width, $size, $maximumLines);
            if (! $wrapped['truncated']) {
                break;
            }
        }

        if ($wrapped['truncated']) {
            $last = array_key_last($wrapped['lines']);
            $wrapped['lines'][$last] = $this->ellipsis($wrapped['lines'][$last], $width, $size);
        }

        return $wrapped + ['font_size' => $size, 'line_height' => $lineHeight];
    }

    private function traits(array $characterData, float $budget): array
    {
        $width = $this->pt(84);
        foreach (self::FONT_SIZES as $size) {
            $lineHeight = $this->lineHeight($size);
            $rows = [];
            foreach (self::TRAITS as $key => $label) {
                $wrapped = $this->wrap((string) ($characterData[$key] ?? ''), $width, $size, 32);
                $rows[$key] = $wrapped + ['label' => $label, 'height' => max($this->pt(4), count($wrapped['lines']) * $lineHeight + 2)];
            }
            if (array_sum(array_column($rows, 'height')) <= $budget) {
                break;
            }
        }

        // Extremely wide inputs must also stay on the main page. Distribute the
        // remaining lines between fields and mark any necessary shortening visibly.
        if (array_sum(array_column($rows, 'height')) > $budget) {
            $limits = array_fill_keys(array_keys(self::TRAITS), 1);
            $remaining = (int) floor(($budget - 8) / $lineHeight) - count($limits);
            while ($remaining > 0) {
                $added = false;
                foreach ($limits as $key => $limit) {
                    if ($remaining > 0 && $limit < count($rows[$key]['lines'])) {
                        $limits[$key]++;
                        $remaining--;
                        $added = true;
                    }
                }
                if (! $added) {
                    break;
                }
            }
            foreach ($rows as $key => &$row) {
                $wrapped = $this->wrap((string) ($characterData[$key] ?? ''), $width, $size, $limits[$key]);
                if ($wrapped['truncated']) {
                    $last = array_key_last($wrapped['lines']);
                    $wrapped['lines'][$last] = $this->ellipsis($wrapped['lines'][$last], $width, $size);
                }
                $row = $wrapped + ['label' => self::TRAITS[$key], 'height' => max($this->pt(4), count($wrapped['lines']) * $lineHeight + 2)];
            }
            unset($row);
        }

        return ['rows' => $rows, 'font_size' => $size, 'line_height' => $lineHeight];
    }

    private function wrap(string $text, float $width, float $size, int $maximumLines): array
    {
        $lines = [];
        $offset = 0;
        $length = mb_strlen($text);
        while ($offset < $length && count($lines) < $maximumLines) {
            // A bounded window also handles huge unbroken inputs without repeatedly
            // measuring the entire remainder. Even narrow glyphs fit well below 512.
            $window = mb_substr($text, $offset, 512);
            $fit = $this->prefixLength($window, $width, $size);
            if ($fit < mb_strlen($window) || $offset + $fit < $length) {
                $space = mb_strrpos(mb_substr($window, 0, $fit + 1), ' ');
                if ($space !== false && $space > 0) {
                    $fit = $space;
                }
            }
            $fit = max(1, $fit);
            $lines[] = rtrim(mb_substr($window, 0, $fit));
            $offset += $fit;
            while ($offset < $length && mb_substr($text, $offset, 1) === ' ') {
                $offset++;
            }
        }

        return ['lines' => $lines, 'truncated' => $offset < $length];
    }

    private function prefixLength(string $text, float $width, float $size): int
    {
        $low = 0;
        $high = mb_strlen($text);
        while ($low < $high) {
            $middle = (int) ceil(($low + $high) / 2);
            if ($this->metrics->getTextWidth(mb_substr($text, 0, $middle), $this->font, $size) <= $width) {
                $low = $middle;
            } else {
                $high = $middle - 1;
            }
        }

        return $low;
    }

    private function ellipsis(string $line, float $width, float $size): string
    {
        $available = $width - $this->metrics->getTextWidth('…', $this->font, $size);
        $prefix = mb_substr($line, 0, $this->prefixLength($line, $available, $size));
        $space = mb_strrpos($prefix, ' ');
        if ($space !== false && mb_strlen($prefix) < mb_strlen($line)) {
            $prefix = mb_substr($prefix, 0, $space);
        }

        return rtrim($prefix).'…';
    }

    private function lineHeight(float $size): float
    {
        return max($size * 1.35, $this->metrics->getFontHeight($this->font, $size));
    }

    private function pt(float $millimeters): float
    {
        return $millimeters * 72 / 25.4;
    }
}
