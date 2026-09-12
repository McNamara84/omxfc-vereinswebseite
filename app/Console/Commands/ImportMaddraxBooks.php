<?php

namespace App\Console\Commands;

use App\Enums\BookType;
use App\Exceptions\MaddraxikonCrawlException;
use App\Services\Maddraxikon\MaddraxikonBookImporter;
use App\Services\Maddraxikon\MaddraxikonDatasetValidator;
use App\Services\Maddraxikon\MaddraxikonSnapshotRepository;
use Illuminate\Console\Command;
use JsonException;

class ImportMaddraxBooks extends Command
{
    protected $signature = 'books:import
        {--path=private/maddrax.json : Path to novels JSON file relative to storage/app}
        {--hardcovers-path=private/hardcovers.json : Path to hardcovers JSON file relative to storage/app}
        {--missionmars-path=private/missionmars.json : Path to Mission Mars novels JSON file relative to storage/app}
        {--volkdertiefe-path=private/volkdertiefe.json : Path to Das Volk der Tiefe novels JSON file relative to storage/app}
        {--2012-path=private/2012.json : Path to 2012 novels JSON file relative to storage/app}
        {--abenteurer-path=private/abenteurer.json : Path to Die Abenteurer novels JSON file relative to storage/app}';

    protected $description = 'Validate and transactionally import all active Maddraxikon book datasets';

    public function handle(
        MaddraxikonDatasetValidator $validator,
        MaddraxikonBookImporter $importer,
        MaddraxikonSnapshotRepository $snapshots,
    ): int {
        $definitions = [
            'path' => BookType::MaddraxDieDunkleZukunftDerErde,
            'hardcovers-path' => BookType::MaddraxHardcover,
            'missionmars-path' => BookType::MissionMars,
            'volkdertiefe-path' => BookType::DasVolkDerTiefe,
            '2012-path' => BookType::ZweiTausendZwölfDasJahrDerApokalypse,
            'abenteurer-path' => BookType::DieAbenteurer,
        ];
        $datasets = [];
        $failed = false;

        foreach ($definitions as $option => $type) {
            try {
                $rows = $this->read($option, (string) $this->option($option), $type, $snapshots);
                $validator->validate($rows, $type);
                $datasets[$type->key()] = $rows;
            } catch (MaddraxikonCrawlException $exception) {
                $this->error('Import for '.$type->value.' failed: '.$exception->getMessage());
                $failed = true;
            }
        }

        if ($failed) {
            $this->warn('Keine Bücher wurden importiert; der Datenbankbestand blieb unverändert.');

            return self::FAILURE;
        }

        try {
            $importer->import($datasets);
        } catch (\Throwable $exception) {
            report($exception);
            $this->error('Der Bücherimport wurde vollständig zurückgerollt: '.$exception->getMessage());

            return self::FAILURE;
        }

        foreach ($definitions as $type) {
            $this->info('Import for '.$type->value.' completed successfully.');
        }

        return self::SUCCESS;
    }

    /** @return array<int, array<string, mixed>> */
    private function read(
        string $option,
        string $path,
        BookType $type,
        MaddraxikonSnapshotRepository $snapshots,
    ): array {
        $explicitPath = $this->input->hasParameterOption('--'.$option);
        $snapshot = $snapshots->activeDataset($type);

        if ($explicitPath && $snapshot !== null) {
            throw new MaddraxikonCrawlException(
                "Ein expliziter Dateipfad für {$type->label()} ist nicht zulässig, solange ein aktiver ".
                'Datenbank-Snapshot existiert. Verwende books:refresh für eine atomare Aktualisierung.'
            );
        }

        if ($snapshot !== null) {
            return $snapshot['rows'];
        }

        $fullPath = storage_path("app/{$path}");

        if (! is_file($fullPath) || ! is_readable($fullPath)) {
            throw new MaddraxikonCrawlException("JSON file not found at {$fullPath}");
        }

        $json = file_get_contents($fullPath);

        if (! is_string($json)) {
            throw new MaddraxikonCrawlException("JSON file could not be read at {$fullPath}");
        }

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new MaddraxikonCrawlException('Invalid JSON - '.$exception->getMessage());
        }

        if (! is_array($data)) {
            throw new MaddraxikonCrawlException("JSON for {$type->label()} is not a list");
        }

        return $data;
    }
}
