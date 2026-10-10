<?php

namespace App\Console\Commands;

use App\Models\RomanExcerpt;
use Illuminate\Console\Command;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\TypesenseEngine;
use Throwable;

class CloneKompendiumIndex extends Command
{
    protected $signature = 'kompendium:clone-index {version : Neue, höhere Indexversion}';

    protected $description = 'Klont den aktiven Typesense-Index samt Dokumenten; aktiviert ihn erst nach separater Prüfung';

    public function handle(EngineManager $engines): int
    {
        $version = filter_var($this->argument('version'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($version === false || $version <= (int) config('kompendium.search.index_version', 1)) {
            $this->error('Die neue Indexversion muss eine höhere positive Ganzzahl sein.');

            return self::FAILURE;
        }
        if (config('scout.driver') !== 'typesense') {
            $this->error('Dieser Befehl benötigt den Typesense-Treiber.');

            return self::FAILURE;
        }

        $engine = $engines->engine();
        if (! $engine instanceof TypesenseEngine) {
            return self::FAILURE;
        }
        $source = (new RomanExcerpt)->searchableAs();
        $target = RomanExcerpt::indexNameFor((string) config('kompendium.search.index_variant', 'lexical'), $version);

        try {
            $collections = $engine->getCollections();
            $before = $collections[$source]->retrieve();
            // An existing target causes a conflict; never overwrite or delete it.
            $collections->create(['name' => $target], ['src_name' => $source, 'copy_documents' => 'true']);
            $copy = $collections[$target]->retrieve();
            $after = $collections[$source]->retrieve();
            if (! isset($before['num_documents'], $copy['num_documents'], $after['num_documents'])
                || $before['num_documents'] !== $copy['num_documents']
                || $before['num_documents'] !== $after['num_documents']
                || $before['fields'] !== $copy['fields']) {
                $this->error('Der Klon ist nicht konsistent. Aktiven Index beibehalten und den Klon prüfen.');

                return self::FAILURE;
            }

            // Typesense 30.2 can lose a newly cloned collection when replaying
            // Raft logs on restart. Persist the verified copy in an internal
            // snapshot before reporting success; no export path is required.
            // https://typesense.org/docs/30.2/api/cluster-operations.html#create-snapshot-for-backups
            if (($engine->getOperations()->perform('snapshot')['success'] ?? false) !== true) {
                $this->error('Der Klon konnte nicht dauerhaft gesichert werden. Aktiven Index beibehalten und den Klon prüfen.');

                return self::FAILURE;
            }
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Index konnte nicht geklont werden. Der aktive Index bleibt unverändert; Details im privaten Log.');

            return self::FAILURE;
        }

        $this->info("{$source} → {$target}: {$copy['num_documents']} Dokumente kopiert.");
        $this->info("Nach Referenzsuchtests KOMPENDIUM_SEARCH_INDEX_VERSION={$version} setzen und Konfiguration/Worker neu laden. Für den Rückweg die bisherige Version behalten.");

        return self::SUCCESS;
    }
}
