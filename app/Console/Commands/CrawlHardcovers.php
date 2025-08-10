<?php
namespace App\Console\Commands;
use Carbon\Carbon;
use DOMDocument;
use DOMXPath;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CrawlHardcovers extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'crawlhardcovers';

    /**
     * The console command description.
     */
    protected $description = 'Crawl maddraxikon.com for hardcover information';

    private const BASE_URL = 'https://de.maddraxikon.com/';
    private const CATEGORY_URL = self::BASE_URL.'index.php?title=Kategorie:Maddrax-Hardcover';
    private const NBSP = "\u{A0}";

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
            $info = $this->getHardcoverInfo($url);
            if ($info !== null) {
                $data[] = $info;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        if ($this->writeHardcovers($data)) {
            $this->info('hardcovers.json updated.');
            return self::SUCCESS;
        }

        $this->error('Failed to write hardcovers.json');
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
            $urls[] = self::BASE_URL.$article->getAttribute('href');
        }

        $nextPage = $xpath->query("//a[text()='nächste Seite']");
        if ($nextPage->length > 0) {
            $urls = array_merge(
                $urls,
                $this->getArticleUrls(self::BASE_URL.$nextPage->item(0)->getAttribute('href'))
            );
        }

        return $urls;
    }

    private function getHardcoverInfo(string $url): ?array
    {
        $html = $this->getUrlContent($url);
        if ($html === false) {
            return null;
        }

        $dom = new DOMDocument;
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);

        // Nummer (spezifischer XPath für Hardcover)
        $numberNode = $xpath->query("//div[contains(@class, 'heftartikel-navigationsleiste-anfang')]//td[contains(@align, 'center')]/i");
        $number = $numberNode->length > 0 ? (int) ltrim($numberNode->item(0)->textContent, '0') : null;

        // Titel (spezifischer XPath für Hardcover)
        $titleNode = $xpath->query("//td[contains(text(), 'Titel:')]/following-sibling::th/b");
        $title = $titleNode->length > 0 ? trim($titleNode->item(0)->textContent) : null;

        // EVT
        $evtNode = $xpath->query("//td[contains(text(), 'Erstmals".self::NBSP."erschienen:')]/following-sibling::td[1]");
        $evt = $evtNode->length > 0 ? trim($evtNode->item(0)->textContent) : null;

        // Serie (nicht Zyklus wie bei Heftromanen)
        $serieNode = $xpath->query("//td[contains(text(), 'Serie:')]/following-sibling::td[1]/a");
        $serie = $serieNode->length > 0 ? trim($serieNode->item(0)->textContent) : null;

        // Bewertung
        $ratingNode = $xpath->query("//div[@class='voteboxrate']");
        $rating = $ratingNode->length > 0 ? trim($ratingNode->item(0)->textContent) : null;

        // Stimmen
        $votesNode = $xpath->query("//span[@class='rating-total']");
        if ($votesNode->length > 0) {
            $votesText = $votesNode->item(0)->textContent;
            preg_match('/\((\d+)\s+Stimmen?\)/', $votesText, $matches);
            $votes = isset($matches[1]) ? $matches[1] : null;
        } else {
            $votes = null;
        }

        // Text (Autor)
        $textNode = $xpath->query("//td[contains(text(), 'Text:')]/following-sibling::td[1]");
        $text = $textNode->length > 0 ? explode(', ', trim($textNode->item(0)->textContent)) : null;

        // Personen
        $personenNode = $xpath->query("//td[contains(text(), 'Personen:')]/following-sibling::td[1]");
        $personen = $personenNode->length > 0 ? explode(', ', trim($personenNode->item(0)->textContent)) : null;

        // Schlagworte
        $schlagworteNode = $xpath->query("//td[contains(text(), 'Schlagworte:')]/following-sibling::td[1]");
        $schlagworte = $schlagworteNode->length > 0 ? explode(', ', trim($schlagworteNode->item(0)->textContent)) : null;

        // Handlungsort
        $handlungsortNode = $xpath->query("//td[contains(text(), 'Handlungsort:')]/following-sibling::td[1]");
        $handlungsort = $handlungsortNode->length > 0 ? explode(', ', trim($handlungsortNode->item(0)->textContent)) : null;

        // Bedingung wie im Legacy-Code
        if ($title !== null || $rating !== null) {
            return [$number, $title, $evt, $serie, $rating, $votes, $text, $personen, $schlagworte, $handlungsort];
        }

        return null;
    }

    private function writeHardcovers(array $data): bool
{
    $jsonData = [];
    foreach ($data as $row) {
        // Datum-Parsing mit try-catch für deutsche Datumsformate
        $releaseDate = null;
        if ($row[2] !== null) {
            try {
                // Versuche zuerst, deutsche Monatsnamen zu konvertieren
                $germanMonths = [
                    'Januar' => 'January', 'Februar' => 'February', 'März' => 'March',
                    'April' => 'April', 'Mai' => 'May', 'Juni' => 'June',
                    'Juli' => 'July', 'August' => 'August', 'September' => 'September',
                    'Oktober' => 'October', 'November' => 'November', 'Dezember' => 'December'
                ];
                
                $dateString = $row[2];
                foreach ($germanMonths as $german => $english) {
                    $dateString = str_replace($german, $english, $dateString);
                }
                
                $releaseDate = Carbon::parse($dateString);
            } catch (\Exception $e) {
                // Wenn das Parsing fehlschlägt, einfach null setzen
                $releaseDate = null;
            }
        }

        // Nur zukünftige Daten ausschließen, wenn das Datum erfolgreich geparst wurde
        if ($releaseDate && $releaseDate->isAfter(Carbon::today())) {
            continue;
        }

        $obj = new \stdClass;
        $obj->nummer = $row[0];              // Position 0
        $obj->titel = $row[1];               // Position 1  
        $obj->evt = $row[2];                 // Position 2
        $obj->zyklus = $row[3];              // Position 3 (Serie)
        $obj->bewertung = $row[4] !== null ? (float) $row[4] : null; // Position 4
        $obj->stimmen = $row[5] !== null ? (int) $row[5] : null;     // Position 5
        $obj->text = $row[6];                // Position 6
        $obj->personen = $row[7];            // Position 7
        $obj->schlagworte = $row[8];         // Position 8
        $obj->orte = $row[9];               // Position 9

        $jsonData[] = $obj;
    }

    usort($jsonData, fn ($a, $b) => $a->nummer <=> $b->nummer);
    $json = json_encode($jsonData, JSON_PRETTY_PRINT);

    try {
        return Storage::disk('private')->put('hardcovers.json', $json);
    } catch (\Throwable $e) {
        $this->error('Failed to write hardcovers.json: '.$e->getMessage());
        return false;
    }
}
}