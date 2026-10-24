<?php

namespace Tests\Performance;

use App\Enums\BookType;
use App\Enums\Role;
use App\Models\Book;
use App\Models\BookOffer;
use App\Models\BookRequest;
use App\Models\BookSwap;
use App\Services\Romantausch\SwapMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

/** Run explicitly; the same HTTP operations measure both implementations. */
class RomantauschBenchmark extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    public function test_measure_http_mutations(): void
    {
        $actor = $this->actingMember(Role::Vorstand);
        $other = $this->createUserWithRole(Role::Mitglied);
        Mail::fake();
        foreach (range(1, 200) as $number) {
            Book::create(['roman_number' => $number, 'title' => "Roman {$number}", 'author' => 'Autor', 'type' => BookType::MaddraxDieDunkleZukunftDerErde]);
        }
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });
        $samples = [];
        foreach (range(0, 5) as $iteration) {
            foreach (['offer', 'request', 'bundle'] as $operation) {
                $data = ['series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_numbers' => '1-200', 'condition' => 'Z0'];
                $route = match ($operation) {
                    'offer' => 'store-offer', 'request' => 'store-request', 'bundle' => 'store-bundle-offer'
                };
                $queries = 0;
                memory_reset_peak_usage();
                $start = hrtime(true);
                $this->post(route('romantausch.'.$route), $data)->assertRedirect(route('romantausch.index'));
                $elapsed = (hrtime(true) - $start) / 1e6;
                if ($iteration > 0) {
                    $samples[$operation][] = ['ms' => round($elapsed, 2), 'queries' => $queries, 'peak_bytes' => memory_get_peak_usage(true)];
                }
                if ($operation !== 'bundle') {
                    $source = $operation === 'offer' ? BookOffer::latest('id')->firstOrFail() : BookRequest::latest('id')->firstOrFail();
                    $queries = 0;
                    memory_reset_peak_usage();
                    $start = hrtime(true);
                    $this->put(route('romantausch.update-'.$operation, $source), array_replace($data, ['condition' => 'Z1']))->assertRedirect(route('romantausch.index'));
                    if ($iteration > 0) {
                        $samples[$operation.'_update'][] = ['ms' => round((hrtime(true) - $start) / 1e6, 2), 'queries' => $queries, 'peak_bytes' => memory_get_peak_usage(true)];
                    }
                }
                if ($operation === 'bundle') {
                    $bundle = BookOffer::whereNotNull('bundle_id')->latest('id')->firstOrFail();
                    $queries = 0;
                    memory_reset_peak_usage();
                    $start = hrtime(true);
                    $this->put(route('romantausch.update-bundle', $bundle->bundle_id), $data + ['condition_max' => 'Z2'])->assertRedirect(route('romantausch.index'));
                    if ($iteration > 0) {
                        $samples['bundle_update'][] = ['ms' => round((hrtime(true) - $start) / 1e6, 2), 'queries' => $queries, 'peak_bytes' => memory_get_peak_usage(true)];
                    }
                }
            }
            $fixture = ['series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'Roman 1', 'condition' => 'Z0'];
            foreach (['confirm', 'complete'] as $operation) {
                $offer = BookOffer::create($fixture + ['user_id' => $actor->id]);
                $request = BookRequest::create($fixture + ['user_id' => $other->id]);
                if ($operation === 'confirm') {
                    $swap = BookSwap::create(['offer_id' => $offer->id, 'request_id' => $request->id, 'offer_confirmed' => true]);
                    $this->actingAs($other);
                    $url = route('romantausch.confirm-swap', $swap);
                } else {
                    $this->actingAs($actor);
                    $url = route('romantausch.complete-swap', [$offer, $request]);
                }
                $queries = 0;
                memory_reset_peak_usage();
                $start = hrtime(true);
                $this->post($url)->assertRedirect(route('romantausch.index'));
                if ($iteration > 0) {
                    $samples[$operation][] = ['ms' => round((hrtime(true) - $start) / 1e6, 2), 'queries' => $queries, 'peak_bytes' => memory_get_peak_usage(true)];
                }
            }
            $this->actingAs($actor);
            foreach ([0, 20] as $candidateCount) {
                $fixture['book_number'] = $candidateCount ? 3 : 199;
                $offer = BookOffer::create($fixture + ['user_id' => $actor->id]);
                $candidates = [];
                for ($index = 0; $index < $candidateCount; $index++) {
                    $candidates[] = BookRequest::create($fixture + ['user_id' => $other->id]);
                }
                $queries = 0;
                memory_reset_peak_usage();
                $start = hrtime(true);
                app(SwapMatchingService::class)->matchSwap($offer, 'offer');
                if ($iteration > 0) {
                    $samples['matching_'.$candidateCount][] = ['ms' => round((hrtime(true) - $start) / 1e6, 2), 'queries' => $queries, 'peak_bytes' => memory_get_peak_usage(true)];
                }
                foreach ($candidates as $candidate) {
                    $candidate->delete();
                }
            }
        }
        $output = getenv('ROMANTAUSCH_BENCHMARK_OUTPUT');
        $this->assertNotFalse($output, 'ROMANTAUSCH_BENCHMARK_OUTPUT must name the report file.');
        file_put_contents($output, json_encode($samples, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n");
    }
}
