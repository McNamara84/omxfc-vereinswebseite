<?php

namespace Tests\Unit\Services\Romantausch;

use App\Services\Romantausch\SwapMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SwapMatchingServiceTest extends TestCase
{
    use RefreshDatabase;

    private SwapMatchingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SwapMatchingService();
    }

    public function test_build_book_key_format(): void
    {
        $key = $this->service->buildBookKey('Maddrax', 42);
        $this->assertEquals('Maddrax::42', $key);
    }

    public function test_build_book_key_with_special_series(): void
    {
        $key = $this->service->buildBookKey('Das Volk der Tiefe', 1);
        $this->assertEquals('Das Volk der Tiefe::1', $key);
    }

    public function test_build_book_key_consistency(): void
    {
        // Gleiche Eingaben sollten immer gleiche Keys erzeugen
        $key1 = $this->service->buildBookKey('Series', 10);
        $key2 = $this->service->buildBookKey('Series', 10);

        $this->assertEquals($key1, $key2);
    }

    public function test_build_book_key_different_for_different_series(): void
    {
        $key1 = $this->service->buildBookKey('SeriesA', 1);
        $key2 = $this->service->buildBookKey('SeriesB', 1);

        $this->assertNotEquals($key1, $key2);
    }

    public function test_build_book_key_different_for_different_numbers(): void
    {
        $key1 = $this->service->buildBookKey('Series', 1);
        $key2 = $this->service->buildBookKey('Series', 2);

        $this->assertNotEquals($key1, $key2);
    }
}
