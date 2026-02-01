<?php

namespace Tests\Unit\Services\Romantausch;

use App\Services\Romantausch\BundleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BundleServiceTest extends TestCase
{
    use RefreshDatabase;

    private BundleService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BundleService::class);
    }

    // ========== parseBookNumbers Tests ==========

    public function test_parse_single_numbers(): void
    {
        $this->assertEquals([1, 5, 10], $this->service->parseBookNumbers('1, 5, 10'));
    }

    public function test_parse_range(): void
    {
        $this->assertEquals([1, 2, 3, 4, 5], $this->service->parseBookNumbers('1-5'));
    }

    public function test_parse_mixed_ranges_and_singles(): void
    {
        $result = $this->service->parseBookNumbers('1-3, 5, 7-9');
        $this->assertEquals([1, 2, 3, 5, 7, 8, 9], $result);
    }

    public function test_parse_removes_duplicates(): void
    {
        $result = $this->service->parseBookNumbers('1, 2, 1-3');
        $this->assertEquals([1, 2, 3], $result);
    }

    public function test_parse_handles_whitespace(): void
    {
        $result = $this->service->parseBookNumbers('  1 , 2 , 3  ');
        $this->assertEquals([1, 2, 3], $result);
    }

    public function test_parse_handles_leading_zeros(): void
    {
        $result = $this->service->parseBookNumbers('001, 010, 100');
        $this->assertEquals([1, 10, 100], $result);
    }

    public function test_parse_ignores_invalid_ranges(): void
    {
        // Umgekehrter Bereich wird ignoriert
        $result = $this->service->parseBookNumbers('5-1, 10');
        $this->assertEquals([10], $result);
    }

    public function test_parse_ignores_too_large_ranges(): void
    {
        // Bereich > MAX_RANGE_SPAN wird ignoriert
        $result = $this->service->parseBookNumbers('1-600, 10');
        $this->assertEquals([10], $result);
    }

    public function test_parse_ignores_non_numeric(): void
    {
        $result = $this->service->parseBookNumbers('1, abc, 3');
        $this->assertEquals([1, 3], $result);
    }

    public function test_parse_returns_empty_for_invalid_input(): void
    {
        $this->assertEquals([], $this->service->parseBookNumbers(''));
        $this->assertEquals([], $this->service->parseBookNumbers('abc'));
    }

    // ========== formatBookNumbersRange Tests ==========

    public function test_format_single_number(): void
    {
        $offers = collect([
            (object) ['book_number' => 5],
        ]);

        $this->assertEquals('5', $this->service->formatBookNumbersRange($offers));
    }

    public function test_format_consecutive_range(): void
    {
        $offers = collect([
            (object) ['book_number' => 1],
            (object) ['book_number' => 2],
            (object) ['book_number' => 3],
        ]);

        $this->assertEquals('1-3', $this->service->formatBookNumbersRange($offers));
    }

    public function test_format_mixed_ranges(): void
    {
        $offers = collect([
            (object) ['book_number' => 1],
            (object) ['book_number' => 2],
            (object) ['book_number' => 3],
            (object) ['book_number' => 5],
            (object) ['book_number' => 7],
            (object) ['book_number' => 8],
        ]);

        $this->assertEquals('1-3, 5, 7-8', $this->service->formatBookNumbersRange($offers));
    }

    public function test_format_empty_collection(): void
    {
        $this->assertEquals('', $this->service->formatBookNumbersRange(collect()));
    }

    // ========== validateConditionRange Tests ==========

    public function test_valid_condition_range(): void
    {
        // Von besser nach schlechter ist valide
        $this->assertNull($this->service->validateConditionRange('Z0', 'Z2'));
        $this->assertNull($this->service->validateConditionRange('Z1', 'Z1')); // Gleich ist ok
    }

    public function test_invalid_condition_range_reversed(): void
    {
        // Von schlechter nach besser ist nicht erlaubt
        $error = $this->service->validateConditionRange('Z2', 'Z0');
        $this->assertNotNull($error);
        $this->assertStringContainsString('Bis', $error);
    }

    public function test_condition_range_without_max(): void
    {
        // Kein Max-Wert ist immer gültig
        $this->assertNull($this->service->validateConditionRange('Z2', null));
        $this->assertNull($this->service->validateConditionRange('Z2', ''));
    }

    public function test_unknown_condition_value(): void
    {
        $error = $this->service->validateConditionRange('INVALID', 'Z2');
        $this->assertNotNull($error);
        $this->assertStringContainsString('INVALID', $error);
    }

    // ========== validateMissingBookNumbers Tests ==========

    public function test_no_missing_numbers(): void
    {
        $requested = [1, 2, 3];
        $existing = [1, 2, 3, 4, 5];

        $this->assertNull($this->service->validateMissingBookNumbers($requested, $existing));
    }

    public function test_some_missing_numbers(): void
    {
        $requested = [1, 2, 999, 1000];
        $existing = [1, 2, 3];

        $result = $this->service->validateMissingBookNumbers($requested, $existing);
        $this->assertNotNull($result);
        $this->assertStringContainsString('999', $result);
        $this->assertStringContainsString('1000', $result);
    }

    public function test_truncates_long_missing_list(): void
    {
        $requested = range(1, 20);
        $existing = [];

        $result = $this->service->validateMissingBookNumbers($requested, $existing);
        $this->assertNotNull($result);
        $this->assertStringContainsString('...', $result);
        $this->assertStringContainsString('20 insgesamt', $result);
    }

    // ========== Constants Tests ==========

    public function test_constants_have_expected_values(): void
    {
        $this->assertEquals(500, BundleService::MAX_RANGE_SPAN);
        $this->assertEquals(2, BundleService::MIN_BUNDLE_SIZE);
        $this->assertEquals(200, BundleService::MAX_BUNDLE_SIZE);
        $this->assertContains('Z0', BundleService::CONDITION_ORDER);
        $this->assertContains('Z4', BundleService::CONDITION_ORDER);
    }
}
