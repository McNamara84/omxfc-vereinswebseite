<?php

namespace App\Data\Maddraxikon;

final readonly class CrawledBook
{
    /**
     * @param  list<string>|null  $authors
     * @param  list<string>|null  $characters
     * @param  list<string>|null  $keywords
     * @param  list<string>|null  $locations
     */
    public function __construct(
        public int $number,
        public ?string $releasedAt,
        public ?string $cycle,
        public ?float $rating,
        public int $votes,
        public string $title,
        public ?array $authors,
        public ?array $characters,
        public ?array $keywords,
        public ?array $locations,
        public ?string $pageTitle,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'nummer' => $this->number,
            'evt' => $this->releasedAt,
            'zyklus' => $this->cycle,
            'titel' => $this->title,
            'text' => $this->authors,
            'bewertung' => $this->rating,
            'stimmen' => $this->votes,
            'personen' => $this->characters,
            'schlagworte' => $this->keywords,
            'orte' => $this->locations,
            'maddraxikon_seitentitel' => $this->pageTitle,
        ];
    }
}
