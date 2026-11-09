<?php

declare(strict_types=1);

namespace Tests\Unit;

trait MemberLayoutMigrationTestFixtures
{
    /** @var list<string> */
    private const PROTECTED_BLADE_VIEWS = ['resources/views/dashboard.blade.php', 'resources/views/admin/auktionen/form.blade.php', 'resources/views/admin/auktionen/index.blade.php', 'resources/views/admin/datenbank/index.blade.php', 'resources/views/admin/fanfiction/index.blade.php', 'resources/views/admin/index.blade.php', 'resources/views/admin/messages/index.blade.php', 'resources/views/admin/touren/index.blade.php', 'resources/views/admin/veranstaltungen/form.blade.php', 'resources/views/admin/veranstaltungen/index.blade.php', 'resources/views/api/index.blade.php', 'resources/views/arbeitsgruppen/create.blade.php', 'resources/views/arbeitsgruppen/edit.blade.php', 'resources/views/arbeitsgruppen/index.blade.php', 'resources/views/auktionen/index.blade.php', 'resources/views/auktionen/show.blade.php', 'resources/views/fanfiction/index.blade.php', 'resources/views/fanfiction/show.blade.php', 'resources/views/kassenbuch/index.blade.php', 'resources/views/kassenbuch/kassenstand.blade.php', 'resources/views/maddraxiversum/index.blade.php', 'resources/views/mitglieder/index.blade.php', 'resources/views/mitglieder/karte.blade.php', 'resources/views/mitglieder/karte-locked.blade.php', 'resources/views/newsletter/archiv/admin/edit.blade.php', 'resources/views/newsletter/archiv/admin/index.blade.php', 'resources/views/newsletter/archiv/index.blade.php', 'resources/views/newsletter/archiv/show.blade.php', 'resources/views/newsletter/versenden.blade.php', 'resources/views/pages/downloads.blade.php', 'resources/views/pages/fotogalerie.blade.php', 'resources/views/pages/kompendium.blade.php', 'resources/views/pages/meetings.blade.php', 'resources/views/pages/protokolle.blade.php', 'resources/views/profile/show.blade.php', 'resources/views/profile/view.blade.php', 'resources/views/rpg/char-editor.blade.php', 'resources/views/rpg/characters/index.blade.php', 'resources/views/statistik/index.blade.php', 'resources/views/teams/create.blade.php', 'resources/views/teams/show.blade.php', 'resources/views/three-d-models/create.blade.php', 'resources/views/three-d-models/edit.blade.php', 'resources/views/three-d-models/index.blade.php', 'resources/views/three-d-models/show.blade.php', 'resources/views/todos/create.blade.php', 'resources/views/todos/edit.blade.php', 'resources/views/todos/index.blade.php', 'resources/views/todos/show.blade.php'];

    /** @var list<string> */
    private const PROTECTED_LIVEWIRE_COMPONENTS = ['app/Livewire/BelohnungenAdmin.php', 'app/Livewire/BelohnungenIndex.php', 'app/Livewire/CoverRatingIndex.php', 'app/Livewire/CoverRatingResults.php', 'app/Livewire/FanfictionCreate.php', 'app/Livewire/FanfictionEdit.php', 'app/Livewire/FantreffenAdminDashboard.php', 'app/Livewire/FantreffenVipAuthors.php', 'app/Livewire/HoerbuchForm.php', 'app/Livewire/KassenbuchIndex.php', 'app/Livewire/KompendiumAdminDashboard.php', 'app/Livewire/KompendiumSearchAnalyticsDashboard.php', 'app/Livewire/MaddraxikonAdmin.php', 'app/Livewire/MeetingAdmin.php', 'app/Livewire/MitgliederIndex.php', 'app/Livewire/MyCoverRatings.php', 'app/Livewire/RezensionForm.php', 'app/Livewire/RezensionIndex.php', 'app/Livewire/RezensionShow.php', 'app/Livewire/RomantauschBundleForm.php', 'app/Livewire/RomantauschIndex.php', 'app/Livewire/RomantauschOfferForm.php', 'app/Livewire/RomantauschRequestForm.php', 'app/Livewire/RomantauschShowOffer.php', 'app/Livewire/ThreeDModelForm.php', 'app/Livewire/ThreeDModelIndex.php', 'app/Livewire/ThreeDModelShow.php', 'app/Livewire/TodoForm.php', 'app/Livewire/TodoIndex.php', 'app/Livewire/TodoShow.php', 'app/Livewire/Umfragen/UmfrageVerwaltung.php'];

    private function source(string $path): string
    {
        $source = file_get_contents($this->projectPath($path));
        expect($source)->toBeString();

        return $source;
    }

    private function projectPath(string $path): string
    {
        return dirname(__DIR__, 2).'/'.$path;
    }
}
