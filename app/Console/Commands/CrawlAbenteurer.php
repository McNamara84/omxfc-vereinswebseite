<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use DOMDocument;
use DOMXPath;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CrawlAbenteurer extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'crawlabenteurer';

    /**
     * The console command description.
     */
    protected $description = 'Crawl maddraxikon.com for Die Abenteurer novel information';

    private const BASE_URL = 'https://de.maddraxikon.com/';

    private const CATEGORY_URL = self::BASE_URL.'index.php?title=Kategorie:Die_Abenteurer-Heftromane';

    public function handle(): int
    {
        set_time_limit(1800);

        $this->info('Fetching article list…');
        $articleUrls = $this->getArticleUrls(self::CATEGORY_URL);
        if (empty($articleUrls)) {
            $this->error('No articles found.');

            return self::FAILURE;
        }

        $data = [];
        $bar = $this->output->createProgressBar(count($articleUrls));
        $bar->start();
        foreach ($articleUrls as $url) {
            $info = $this->getHeftromanInfo($url);
            if ($info !== null) {
                $data[] = $info;
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();

        $path = Storage::disk('private')->path('abenteurer.json');
        if ($this->writeHeftromane($data, $path)) {
            $this->info('abenteurer.json updated.');

            return self::SUCCESS;
        }

        $this->error('Failed to write abenteurer.json');

        return self::FAILURE;
    }

    private function getUrlContent(string $url): string|false
    {
        return @file_get_contents($url);
    }

    private function getArticleUrls(string $categoryUrl): array
    {
        $html = $this->getUrlContent($categoryUrl);
        if ($html === false) {
            return [];
        }
        $dom = new DOMDocument;
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);
        $articles = $xpath->query("//div[@id='mw-pages']//a");
        $urls = [];
        foreach ($articles as $article) {
            $resolved = $this->resolveUrl($article->getAttribute('href'));
            if ($resolved !== null) {
                $urls[] = $resolved;
            }
        }
        $nextPage = $xpath->query("//a[text()='nächste Seite']");
        if ($nextPage->length > 0) {
            $nextUrl = $this->resolveUrl($nextPage->item(0)->getAttribute('href'));
            if ($nextUrl !== null) {
                $urls = array_merge(
                    $urls,
                    $this->getArticleUrls($nextUrl)
                );
            }
        }

        return $urls;
    }

    private function getHeftromanInfo(string $url): ?array
    {
        $html = $this->getUrlContent($url);
        if ($html === false) {
            return null;
        }
        $dom = new DOMDocument;
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);

        // Die Abenteurer-Seiten haben die Nummer in der Navigationsleiste als <i>026</i>
        // Suche nach der aktuellen Nummer (nicht verlinkt, nur kursiv) in der Navigationsleiste
        $numberNode = $xpath->query("//div[@class='heftartikel-navigationsleiste-anfang']//td[@align='center']//i[not(a)]");
        $number = $numberNode->length > 0 ? trim($numberNode->item(0)->nodeValue) : null;

        // Fallback: Suche nach fetter Nummer (wie bei anderen Serien)
        if ($number === null) {
            $numberNodeFallback = $xpath->query('//b[number(text()) >= 1 and number(text()) <= 999]');
            $number = $numberNodeFallback->length > 0 ? $numberNodeFallback->item(0)->nodeValue : null;
        }

        $evtNode = $xpath->query("//td[contains(text(), 'Erstmals\xC2\xA0erschienen:')]/following-sibling::td[1]");
        $evt = $evtNode->length > 0 ? trim($evtNode->item(0)->textContent) : null;
        $evt = $evtNode->length > 0 ? trim($evtNode->item(0)->textContent) : null;

        $zyklusNode = $xpath->query("//td[contains(text(), 'Zyklus:')]/following-sibling::td[1]");
        if ($zyklusNode->length > 0) {
            $zyklusText = $zyklusNode->item(0)->textContent;
            $pos = strrpos($zyklusText, ' (');
            if ($pos !== false) {
                $zyklusText = substr($zyklusText, 0, $pos);
            }
            $zyklus = trim($zyklusText);
        } else {
            $zyklus = null;
        }

        $ratingNode = $xpath->query("//div[@class='voteboxrate']");
        $rating = $ratingNode->length > 0 ? $ratingNode->item(0)->textContent : null;

        $votesNode = $xpath->query("//span[@class='rating-total']");
        if ($votesNode->length > 0) {
            $votesText = $votesNode->item(0)->textContent;
            if ($votesText === '(eine Stimme)') {
                $votes = '1';
            } else {
                $votes = preg_replace('/\D/', '', $votesText);
            }
        } else {
            $votes = null;
        }

        $titleNode = $xpath->query("//td[contains(text(), 'Titel:')]/following-sibling::th[1]");
        $title = $titleNode->length > 0 ? trim($titleNode->item(0)->textContent) : null;

        $textNode = $xpath->query("//td[contains(text(), 'Text:')]/following-sibling::td[1]");
        $text = $textNode->length > 0 ? explode(', ', trim($textNode->item(0)->textContent)) : null;

        $personenNode = $xpath->query("//td[contains(text(), 'Personen:')]/following-sibling::td[1]");
        $personen = $personenNode->length > 0 ? explode(', ', trim($personenNode->item(0)->textContent)) : null;

        $schlagworteNode = $xpath->query("//td[contains(text(), 'Schlagworte:')]/following-sibling::td[1]");
        $schlagworte = $schlagworteNode->length > 0 ? explode(', ', trim($schlagworteNode->item(0)->textContent)) : null;

        $handlungsortNode = $xpath->query("//td[contains(text(), 'Handlungsort:')]/following-sibling::td[1]");
        $handlungsort = $handlungsortNode->length > 0 ? explode(', ', trim($handlungsortNode->item(0)->textContent)) : null;

        if ($number !== null && $rating !== null) {
            return [$number, $evt, $zyklus, $rating, $votes, $title, $text, $personen, $schlagworte, $handlungsort];
        }

        return null;
    }

    private function writeHeftromane(array $data, string $filename): bool
    {
        $jsonData = [];
        foreach ($data as $row) {
            $releaseDate = $row[1] !== null ? Carbon::parse($row[1]) : null;
            if ($releaseDate && $releaseDate->isAfter(Carbon::today())) {
                continue;
            }
            $obj = new \stdClass;
            $obj->nummer = (int) $row[0];
            $obj->evt = $row[1];
            $obj->zyklus = $row[2];
            $obj->titel = $row[5];
            $obj->text = $row[6];
            $obj->bewertung = empty($row[3]) ? 0.0 : (float) $row[3];
            $obj->stimmen = (int) $row[4];
            $obj->personen = $row[7];
            $obj->schlagworte = $row[8];
            $obj->orte = $row[9];
            $jsonData[] = $obj;
        }
        usort($jsonData, fn ($a, $b) => $a->nummer <=> $b->nummer);
        $json = json_encode($jsonData, JSON_PRETTY_PRINT);

        return file_put_contents($filename, $json) !== false;
    }

    private function resolveUrl(string $href): ?string
    {
        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return $this->isAllowedUrl($href) ? $href : null;
        }

        $absolute = self::BASE_URL.ltrim($href, '/');

        return $this->isAllowedUrl($absolute) ? $absolute : null;
    }

    private function isAllowedUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (($parts['scheme'] ?? '') !== 'https') {
            return false;
        }

        return ($parts['host'] ?? '') === 'de.maddraxikon.com';
    }
}
