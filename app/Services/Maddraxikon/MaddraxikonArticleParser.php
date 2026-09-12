<?php

namespace App\Services\Maddraxikon;

use App\Data\Maddraxikon\CrawledBook;
use App\Enums\BookType;
use App\Exceptions\MaddraxikonCrawlException;
use App\Support\MaddraxikonPageTitle;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

class MaddraxikonArticleParser
{
    public function parse(string $html, string $url, BookType $type): CrawledBook
    {
        $xpath = $this->xpath($html, $url);
        $number = $this->number($xpath, $type);
        $title = $type === BookType::MaddraxHardcover
            ? $this->textAt($xpath, "//td[contains(., 'Titel:')]/following-sibling::th[1]//b[1]")
            : $this->siblingValue($xpath, 'Titel:');

        if ($number === null || $number <= 0 || $title === null || $title === '') {
            throw new MaddraxikonCrawlException(
                "Romanseite {$url} enthält keine gültige Nummer und keinen gültigen Titel.",
                $url,
            );
        }

        $cycleLabel = $type === BookType::MaddraxHardcover ? 'Serie:' : 'Zyklus:';
        $cycle = $this->siblingValue($xpath, $cycleLabel);

        if ($cycle !== null) {
            $cycle = trim((string) preg_replace('/\s+\([^)]*\)\s*$/u', '', $cycle));
            $cycle = $cycle !== '' ? $cycle : null;
        }

        $ratingText = $this->textAt(
            $xpath,
            "//div[contains(concat(' ', normalize-space(@class), ' '), ' voteboxrate ')][1]"
        );
        $rating = $ratingText !== null && is_numeric(str_replace(',', '.', $ratingText))
            ? (float) str_replace(',', '.', $ratingText)
            : null;
        $votes = $this->votes($this->textAt(
            $xpath,
            "//span[contains(concat(' ', normalize-space(@class), ' '), ' rating-total ')][1]"
        ));

        return new CrawledBook(
            number: $number,
            releasedAt: $this->siblingValue($xpath, 'Erstmals erschienen:'),
            cycle: $cycle,
            rating: $rating,
            votes: $votes,
            title: $title,
            authors: $this->listValue($xpath, 'Text:'),
            characters: $this->listValue($xpath, 'Personen:'),
            keywords: $this->listValue($xpath, 'Schlagworte:'),
            locations: $this->listValue($xpath, 'Handlungsort:'),
            pageTitle: MaddraxikonPageTitle::fromUrl($url),
        );
    }

    private function xpath(string $html, string $url): DOMXPath
    {
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        try {
            $loaded = $dom->loadHTML($html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (! $loaded) {
            throw new MaddraxikonCrawlException("Ungültiges HTML von {$url}.", $url);
        }

        return new DOMXPath($dom);
    }

    private function number(DOMXPath $xpath, BookType $type): ?int
    {
        if (in_array($type, [BookType::MaddraxHardcover, BookType::DieAbenteurer], true)) {
            $navigationNumber = $this->textAt(
                $xpath,
                "//div[contains(concat(' ', normalize-space(@class), ' '), ' heftartikel-navigationsleiste-anfang ')]//td[@align='center']//i[not(a)][1]"
            );

            if ($navigationNumber !== null && preg_match('/\d+/', $navigationNumber, $matches) === 1) {
                return (int) $matches[0];
            }
        }

        if ($type === BookType::MaddraxHardcover) {
            $coverSource = $this->attributeAt($xpath, "//img[contains(@src, 'hc.jpg')][1]", 'src');

            if ($coverSource !== null && preg_match('/(\d+)hc\.jpg/i', $coverSource, $matches) === 1) {
                return (int) $matches[1];
            }
        }

        $nodes = $xpath->query('//b');

        if ($nodes !== false) {
            foreach ($nodes as $node) {
                $value = trim($node->textContent);

                if (preg_match('/^\d{1,5}$/', $value) === 1) {
                    return (int) $value;
                }
            }
        }

        return null;
    }

    private function siblingValue(DOMXPath $xpath, string $label): ?string
    {
        $cells = $xpath->query('//td');

        if ($cells === false) {
            return null;
        }

        foreach ($cells as $cell) {
            if (! $cell instanceof DOMElement || $this->normalizeLabel($cell->textContent) !== $label) {
                continue;
            }

            $sibling = $cell->nextSibling;

            while ($sibling instanceof DOMNode && ! $sibling instanceof DOMElement) {
                $sibling = $sibling->nextSibling;
            }

            if ($sibling instanceof DOMElement) {
                $value = trim((string) preg_replace('/\s+/u', ' ', $sibling->textContent));

                return $value !== '' ? $value : null;
            }
        }

        return null;
    }

    /** @return list<string>|null */
    private function listValue(DOMXPath $xpath, string $label): ?array
    {
        $value = $this->siblingValue($xpath, $label);

        if ($value === null) {
            return null;
        }

        $items = array_values(array_filter(
            array_map('trim', explode(',', $value)),
            static fn (string $item): bool => $item !== '',
        ));

        return $items !== [] ? $items : null;
    }

    private function normalizeLabel(string $value): string
    {
        $value = str_replace("\u{00A0}", ' ', $value);

        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    private function votes(?string $value): int
    {
        if ($value === null) {
            return 0;
        }

        if (str_contains(mb_strtolower($value), 'eine stimme')) {
            return 1;
        }

        return preg_match('/\d+/', $value, $matches) === 1 ? (int) $matches[0] : 0;
    }

    private function textAt(DOMXPath $xpath, string $query): ?string
    {
        $nodes = $xpath->query($query);

        if ($nodes === false || $nodes->length === 0) {
            return null;
        }

        $value = trim((string) preg_replace('/\s+/u', ' ', $nodes->item(0)?->textContent ?? ''));

        return $value !== '' ? $value : null;
    }

    private function attributeAt(DOMXPath $xpath, string $query, string $attribute): ?string
    {
        $nodes = $xpath->query($query);
        $node = $nodes !== false && $nodes->length > 0 ? $nodes->item(0) : null;

        if (! $node instanceof DOMElement) {
            return null;
        }

        $value = trim($node->getAttribute($attribute));

        return $value !== '' ? $value : null;
    }
}
