<?php

namespace Database\Factories;

use App\Enums\BookCoverStatus;
use App\Models\Book;
use App\Models\BookCover;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BookCover> */
class BookCoverFactory extends Factory
{
    protected $model = BookCover::class;

    public function definition(): array
    {
        $fingerprint = fake()->unique()->sha1();

        return [
            'book_id' => Book::factory(),
            'status' => BookCoverStatus::Ready,
            'source_file_title' => "Datei:{$fingerprint}.jpg",
            'source_url' => 'https://wiki.example.test/images/'.$fingerprint.'.jpg',
            'source_sha1' => $fingerprint,
            'source_description_url' => 'https://wiki.example.test/wiki/Datei:Test.jpg',
            'source_artist' => fake()->name(),
            'source_credit' => null,
            'source_license' => 'Testlizenz',
            'source_license_url' => 'https://wiki.example.test/wiki/Lizenz',
            'small_path' => "cover-ratings/test/{$fingerprint}-360.webp",
            'large_path' => "cover-ratings/test/{$fingerprint}-720.webp",
            'width' => 720,
            'height' => 1024,
            'mime_type' => 'image/webp',
            'last_synced_at' => now(),
            'last_error' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => [
            'status' => BookCoverStatus::Pending,
            'small_path' => null,
            'large_path' => null,
            'last_synced_at' => null,
        ]);
    }

    public function missing(): static
    {
        return $this->pending()->state(fn (): array => [
            'status' => BookCoverStatus::Missing,
        ]);
    }
}
