<?php

namespace App\Support\Navigation;

use App\Models\User;
use Illuminate\Support\Str;

class NavigationSearch
{
    public function __construct(private NavigationBuilder $builder, private NavigationContext $context) {}

    /** @return list<array{name: string, description: string, link: string}> */
    public function search(?User $user, string $query, string $scope = 'public'): array
    {
        $query = $this->normalize($query);
        if (mb_strlen($query) < 2) {
            return [];
        }

        $polls = $this->context->polls();
        $entries = $this->flatten($this->builder->build($user, $polls, 'public'), 'public');
        if ($this->context->canAccessMembers($user)) {
            $entries = array_merge($entries, $this->flatten($this->builder->build($user, $polls, 'member'), 'member'));
        }

        $entries[] = $this->entry('home', 'Öffentliche Startseite', 'Öffentliche Website', $this->context->publicHome($user), 'public');
        foreach (['impressum' => 'Impressum', 'datenschutz' => 'Datenschutz'] as $route => $title) {
            $entries[] = $this->entry($route, $title, 'Öffentliche Website', route($route), 'public');
        }
        // Account routes use the existing auth/verified/applicant middleware.
        if ($this->context->canAccessMembers($user)) {
            $entries[] = $this->entry('profile.show', 'Profil', 'Mein Konto', route('profile.show'), 'member');
        } elseif (! $user) {
            $entries[] = $this->entry('login', 'Login', 'Mein Konto', route('login'), 'public');
        }

        $words = explode(' ', $query);
        $results = [];
        foreach ($entries as $entry) {
            $title = $this->normalize($entry['name']);
            $haystack = $this->normalize($entry['name'].' '.$entry['description'].' '.implode(' ', $entry['aliases'] ?? []));
            if (! collect($words)->every(fn (string $word): bool => str_contains($haystack, $word))) {
                continue;
            }
            $rank = $title === $query ? 0 : (str_starts_with($title, $query) ? 1 : (str_contains($title, $query) ? 2 : 3));
            $entry['rank'] = [$rank, $entry['scope'] === $scope ? 0 : 1, $title];
            if (! isset($results[$entry['key']]) || $entry['rank'] < $results[$entry['key']]['rank']) {
                $results[$entry['key']] = $entry;
            }
        }
        usort($results, fn (array $a, array $b): int => $a['rank'] <=> $b['rank']);

        return array_map(fn (array $item): array => [
            // maryUI renders these fields with x-html, including dynamic poll labels.
            'name' => e($item['name']),
            'description' => e($item['description']),
            'link' => $item['link'],
        ], array_slice($results, 0, 10));
    }

    /** @return list<array<string, mixed>> */
    private function flatten(array $navigation, string $scope): array
    {
        $entries = [];
        foreach (array_merge($navigation['featured'], $navigation['direct'] ?? []) as $item) {
            $entries[] = $this->entry($item['key'], $item['title'], $scope === 'member' ? 'Mitgliederbereich · Aktuell' : 'Öffentliche Website', $item['href'], $scope, $item['search_aliases']);
        }
        foreach ($navigation['sections'] as $section) {
            $description = in_array($section['title'], ['Vorstand', 'Admin'], true) ? 'Verwaltung · '.$section['title'] : $section['title'];
            foreach ($section['items'] as $item) {
                $entries[] = $this->entry($item['key'], $item['title'], $description, $item['href'], $scope, $item['search_aliases']);
            }
        }

        return $entries;
    }

    private function entry(string $key, string $name, string $description, string $link, string $scope, array $aliases = []): array
    {
        return compact('key', 'name', 'description', 'link', 'scope', 'aliases');
    }

    private function normalize(string $text): string
    {
        return preg_replace('/\s+/u', ' ', Str::lower(Str::ascii(trim($text), 'de')));
    }
}
