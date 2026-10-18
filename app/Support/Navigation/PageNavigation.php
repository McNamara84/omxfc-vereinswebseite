<?php

namespace App\Support\Navigation;

use App\Models\User;
use Illuminate\Support\Str;

class PageNavigation
{
    public function __construct(private NavigationBuilder $builder, private NavigationContext $context) {}

    /** @return array{title: string, breadcrumbs: list<array{label: string, link?: string}>} */
    public function resolve(?User $user, string $scope): array
    {
        $route = request()->route()?->getName() ?? '';
        $root = $scope === 'member'
            ? ['label' => 'Dashboard', 'link' => route('dashboard')]
            : ['label' => 'Startseite', 'link' => $this->context->publicHome($user)];
        if (in_array($route, ['home', 'dashboard'], true)) {
            return ['title' => $root['label'], 'breadcrumbs' => []];
        }

        $navigation = $this->builder->build($user, $this->context->polls(), $scope);
        $breadcrumbs = [$root];
        // Route names contain dots; use literal keys instead of config's dot lookup.
        $label = config('navigation.pages', [])[$route] ?? null;
        $match = null;
        $group = null;
        foreach (array_merge([['items' => array_merge($navigation['featured'], $navigation['direct'] ?? [])]], $navigation['sections']) as $section) {
            foreach ($section['items'] as $item) {
                $base = preg_replace('/\.(index|show)$/', '', $item['key']);
                if ($item['active'] || $route === $item['key'] || Str::startsWith($route, $base.'.')) {
                    if (! $match || strlen($base) > strlen(preg_replace('/\.(index|show)$/', '', $match['key']))) {
                        $match = $item;
                        $group = $section['title'] ?? null;
                    }
                }
            }
        }

        if ($match) {
            if ($group) {
                $breadcrumbs[] = ['label' => $group];
            }
            if ($route !== $match['key']) {
                $breadcrumbs[] = ['label' => $match['title'], 'link' => $match['href']];
            }
            $label ??= $route === $match['key'] ? $match['title'] : $this->actionLabel($route);
        }

        $label ??= $this->actionLabel($route);
        $breadcrumbs[] = ['label' => $label];

        return ['title' => $label, 'breadcrumbs' => $breadcrumbs];
    }

    private function actionLabel(string $route): string
    {
        return match (Str::afterLast($route, '.')) {
            'create' => 'Erstellen', 'edit' => 'Bearbeiten', 'show' => 'Details',
            'index' => 'Übersicht', 'history' => 'Verlauf', 'improve' => 'Charakter verbessern',
            'award' => 'Erfahrungspunkte vergeben', 'download' => 'Herunterladen',
            default => 'Details',
        };
    }
}
