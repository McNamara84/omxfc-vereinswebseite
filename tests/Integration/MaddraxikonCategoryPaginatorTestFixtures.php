<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Foundation\Testing\RefreshDatabase;

trait MaddraxikonCategoryPaginatorTestFixtures
{
    use RefreshDatabase;

    private const CATEGORY = 'https://de.maddraxikon.com/index.php?title=Kategorie:Maddrax-Heftromane';

    /** @param array<string, string> $links */
    private function categoryHtml(array $links): string
    {
        $anchors = '';
        foreach ($links as $href => $label) {
            $anchors .= '<a href="'.htmlspecialchars($href, ENT_QUOTES).'">'.$label.'</a>';
        }

        return '<html><head><meta charset="UTF-8"></head><body><div id="mw-pages">'.$anchors.'</div></body></html>';
    }
}
