<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\MaddraxDataService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(MaddraxDataServiceTestFixtures::class);

beforeEach(function (): void {
    $this->testStoragePath = base_path('storage/testing');
    $this->app->useStoragePath($this->testStoragePath);
    File::ensureDirectoryExists($this->testStoragePath.'/app/private');
    config(['filesystems.disks.local.root' => $this->testStoragePath.'/app/private']);
    $ref = new \ReflectionClass(MaddraxDataService::class);
    $property = $ref->getProperty('data');
    $property->setValue(null, null);
    $data = [['nummer' => 1, 'zyklus' => 'Z1', 'titel' => 'Roman1', 'text' => ['Author1', 'Author2'], 'personen' => ['Figur1'], 'orte' => ['Ort1'], 'schlagworte' => ['Thema1']], ['nummer' => 2, 'zyklus' => 'Z2', 'titel' => 'Roman2', 'text' => ['Author1'], 'personen' => ['Figur2'], 'orte' => ['Ort2'], 'schlagworte' => ['Thema2']]];
    File::put($this->testStoragePath.'/app/private/maddrax.json', json_encode($data));
});

afterEach(function (): void {
    File::deleteDirectory($this->testStoragePath);
});

test('service extracts information', function (): void {
    $this->actingAs($this->actingMember());
    $this->assertArraysAreIdentical(['Author1', 'Author2'], MaddraxDataService::getAutoren());
    $this->assertArraysAreIdentical(['Z1', 'Z2'], MaddraxDataService::getZyklen());
    $this->assertArraysAreIdentical(['1 - Roman1', '2 - Roman2'], MaddraxDataService::getRomane());
    $this->assertArraysAreIdentical(['Figur1', 'Figur2'], MaddraxDataService::getFiguren());
    $this->assertArraysAreIdentical(['Ort1', 'Ort2'], MaddraxDataService::getSchauplaetze());
    $this->assertArraysAreIdentical(['Thema1', 'Thema2'], MaddraxDataService::getSchlagworte());
});

test('load data returns empty when file is missing', function (): void {
    File::delete($this->testStoragePath.'/app/private/maddrax.json');
    $this->actingAs($this->actingMember());
    $this->assertArraysAreIdentical([], MaddraxDataService::loadData());
});

test('load data returns empty when json is invalid', function (): void {
    File::put($this->testStoragePath.'/app/private/maddrax.json', '{invalid');
    $this->actingAs($this->actingMember());
    $this->assertArraysAreIdentical([], MaddraxDataService::getAutoren());
});
