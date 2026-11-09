<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Foundation\Testing\RefreshDatabase;

trait MaddraxikonArticleParserTestFixtures
{
    use RefreshDatabase;

    private function articleHtml(string $navigation, string $titleCell): string
    {
        return '<html><body>'.$navigation.'<table>
            <tr><td>Erstmals&nbsp;erschienen:</td><td>2026-09-01</td></tr>
            <tr><td>Zyklus:</td><td>Weltrat (50 Romane)</td></tr>
            <tr><td>Serie:</td><td>Hardcover</td></tr>
            <tr><td>Titel:</td>'.$titleCell.'</tr>
            <tr><td>Text:</td><td>Autor A, Autor B</td></tr>
        </table></body></html>';
    }
}
