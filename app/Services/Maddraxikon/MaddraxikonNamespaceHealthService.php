<?php

namespace App\Services\Maddraxikon;

class MaddraxikonNamespaceHealthService
{
    public function __construct(private readonly MaddraxikonApiClient $apiClient) {}

    /**
     * Compare the configured allowlist with the wiki's localized namespace names.
     *
     * Additional namespaces in the wiki are deliberately ignored. Only configured
     * namespace IDs can be imported and therefore need to match.
     *
     * @return array{
     *     healthy: bool,
     *     expected: array<int, string>,
     *     actual: array<int, string>,
     *     missing: array<int, string>,
     *     mismatched: array<int, array{expected: string, actual: string}>
     * }
     */
    public function check(): array
    {
        $expected = $this->expectedNamespaces();
        $actual = $this->apiClient->namespaces();
        $missing = [];
        $mismatched = [];

        foreach ($expected as $id => $expectedName) {
            if (! array_key_exists($id, $actual)) {
                $missing[$id] = $expectedName;

                continue;
            }

            if ($actual[$id] !== $expectedName) {
                $mismatched[$id] = [
                    'expected' => $expectedName,
                    'actual' => $actual[$id],
                ];
            }
        }

        return [
            'healthy' => $missing === [] && $mismatched === [],
            'expected' => $expected,
            'actual' => $actual,
            'missing' => $missing,
            'mismatched' => $mismatched,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function expectedNamespaces(): array
    {
        $allowed = collect(config('maddraxikon.allowed_namespaces', []))
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->all();
        $names = config('maddraxikon.expected_namespace_names', []);

        if (! is_array($names)) {
            return [];
        }

        $expected = [];

        foreach ($allowed as $id) {
            if (array_key_exists($id, $names)) {
                $expected[$id] = (string) $names[$id];
            }
        }

        ksort($expected);

        return $expected;
    }
}
