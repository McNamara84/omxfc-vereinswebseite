<?php

namespace App\Services\Maddraxikon;

use App\Data\MaddraxikonPageMapping;
use App\Support\MaddraxikonPageTitle;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

class MaddraxikonBookMapper
{
    private const MAX_REDIRECTS = 5;

    public function resolve(string $pageTitle): ?MaddraxikonPageMapping
    {
        $databaseTitle = MaddraxikonPageTitle::databaseKey($pageTitle);

        if ($databaseTitle === null) {
            return null;
        }

        $connection = DB::connection('maddraxikon');
        $seenPageIds = [];

        for ($depth = 0; $depth <= self::MAX_REDIRECTS; $depth++) {
            $page = $this->page($connection, $databaseTitle);

            if ($page === null || isset($seenPageIds[$page->page_id])) {
                return null;
            }

            $seenPageIds[$page->page_id] = true;

            if (! $page->page_is_redirect) {
                return new MaddraxikonPageMapping(
                    pageId: $page->page_id,
                    pageTitle: MaddraxikonPageTitle::displayTitle($page->page_title),
                );
            }

            $redirect = $connection->table('redirect')
                ->where('rd_from', $page->page_id)
                ->first(['rd_namespace', 'rd_title']);

            if (
                $redirect === null
                || (int) $redirect->rd_namespace !== 0
                || ! is_string($redirect->rd_title)
            ) {
                return null;
            }

            $databaseTitle = $redirect->rd_title;
        }

        return null;
    }

    private function page(ConnectionInterface $connection, string $databaseTitle): ?object
    {
        $page = $connection->table('page')
            ->where('page_namespace', 0)
            ->where('page_title', $databaseTitle)
            ->first(['page_id', 'page_title', 'page_is_redirect']);

        if (
            $page === null
            || ! is_numeric($page->page_id)
            || (int) $page->page_id < 1
            || ! is_string($page->page_title)
        ) {
            return null;
        }

        return (object) [
            'page_id' => (int) $page->page_id,
            'page_title' => $page->page_title,
            'page_is_redirect' => (bool) $page->page_is_redirect,
        ];
    }
}
