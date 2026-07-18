<?php

namespace App\Services\Maddraxikon;

use App\Exceptions\MaddraxikonApiException;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use JsonException;
use Throwable;

class MaddraxikonApiClient
{
    public function __construct(
        private readonly MaddraxikonApiRequestGuard $requestGuard,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function recentChanges(CarbonInterface $from, CarbonInterface $until): array
    {
        $changes = [];
        $continuation = [];
        $seenContinuations = [];

        do {
            $payload = $this->request([
                ...$continuation,
                'action' => 'query',
                'list' => 'recentchanges',
                'rcdir' => 'newer',
                'rcstart' => $from->clone()->utc()->format('Y-m-d\TH:i:s\Z'),
                'rcend' => $until->clone()->utc()->format('Y-m-d\TH:i:s\Z'),
                'rctype' => 'edit|new',
                'rcshow' => '!anon|!bot',
                'rcnamespace' => implode('|', $this->allowedNamespaces()),
                'rcprop' => 'title|ids|timestamp|user|userid|flags|sizes|tags',
                'rclimit' => 'max',
            ]);

            $page = data_get($payload, 'query.recentchanges');

            if (! is_array($page)) {
                throw new MaddraxikonApiException(
                    'Die Maddraxikon-API lieferte keine gültige RecentChanges-Liste.'
                );
            }

            foreach ($page as $change) {
                if (is_array($change)) {
                    $changes[] = $change;
                }
            }

            $continuation = $this->requestGuard->nextContinuation(
                $payload,
                ['continue', 'rccontinue'],
                $seenContinuations,
            );
        } while ($continuation !== []);

        return $changes;
    }

    /**
     * Fetch contributions for linked numeric user IDs. Unlike RecentChanges,
     * this revision-backed list remains available after $wgRCMaxAge.
     *
     * @param  array<int, mixed>  $wikiUserIds
     * @return list<array<string, mixed>>
     */
    public function userContributions(
        array $wikiUserIds,
        CarbonInterface $from,
        CarbonInterface $until
    ): array {
        $wikiUserIds = $this->positiveUniqueIds($wikiUserIds);

        if ($wikiUserIds === []) {
            return [];
        }

        $contributions = [];
        $batchSize = min(
            50,
            max(
                1,
                (int) config('maddraxikon.sync.usercontribs_batch_size', 50)
            )
        );

        foreach (array_chunk($wikiUserIds, $batchSize) as $userIdBatch) {
            $continuation = [];
            $seenContinuations = [];

            do {
                $payload = $this->request([
                    ...$continuation,
                    'action' => 'query',
                    'list' => 'usercontribs',
                    'ucuserids' => implode('|', $userIdBatch),
                    'ucdir' => 'newer',
                    'ucstart' => $from->clone()->utc()->format('Y-m-d\TH:i:s\Z'),
                    'ucend' => $until->clone()->utc()->format('Y-m-d\TH:i:s\Z'),
                    'ucnamespace' => implode('|', $this->allowedNamespaces()),
                    'ucprop' => 'ids|title|timestamp|flags|size|sizediff|tags',
                    'uclimit' => 'max',
                ]);

                $page = data_get($payload, 'query.usercontribs');

                if (! is_array($page)) {
                    throw new MaddraxikonApiException(
                        'Die Maddraxikon-API lieferte keine gültige UserContribs-Liste.'
                    );
                }

                foreach ($page as $contribution) {
                    if (is_array($contribution)) {
                        $contributions[] = $contribution;
                    }
                }

                $continuation = $this->requestGuard->nextContinuation(
                    $payload,
                    ['continue', 'uccontinue'],
                    $seenContinuations,
                );
            } while ($continuation !== []);
        }

        return $contributions;
    }

    /**
     * Fetch the current visibility and tags of revisions.
     *
     * @param  list<int>  $revisionIds
     * @return array<int, array{
     *     exists: bool,
     *     revision_id: int,
     *     page_id: ?int,
     *     namespace_id: ?int,
     *     user_id: ?int,
     *     user_hidden: bool,
     *     suppressed: bool,
     *     sha1: ?string,
     *     sha1_hidden: bool,
     *     text_hidden: bool,
     *     size: ?int,
     *     tags: list<string>
     * }>
     */
    public function revisionDetails(array $revisionIds): array
    {
        $revisionIds = $this->positiveUniqueIds($revisionIds);

        if ($revisionIds === []) {
            return [];
        }

        $details = [];

        foreach (array_chunk($revisionIds, 50) as $chunk) {
            $payload = $this->request([
                'action' => 'query',
                'prop' => 'revisions',
                'revids' => implode('|', $chunk),
                'rvprop' => 'ids|timestamp|user|userid|size|sha1|flags|tags',
            ]);

            $pages = data_get($payload, 'query.pages');

            if (! is_array($pages)) {
                throw new MaddraxikonApiException(
                    'Die Maddraxikon-API lieferte keine gültigen Revisionsdaten.'
                );
            }

            foreach ($pages as $page) {
                if (! is_array($page)) {
                    continue;
                }

                $pageId = isset($page['pageid']) ? (int) $page['pageid'] : null;
                $namespaceId = isset($page['ns']) ? (int) $page['ns'] : null;

                foreach (($page['revisions'] ?? []) as $revision) {
                    if (! is_array($revision) || ! isset($revision['revid'])) {
                        continue;
                    }

                    $revisionId = (int) $revision['revid'];
                    $details[$revisionId] = [
                        'exists' => true,
                        'revision_id' => $revisionId,
                        'page_id' => $pageId,
                        'namespace_id' => $namespaceId,
                        'user_id' => isset($revision['userid']) ? (int) $revision['userid'] : null,
                        'user_hidden' => array_key_exists('userhidden', $revision),
                        'suppressed' => array_key_exists('suppressed', $revision),
                        'sha1' => isset($revision['sha1']) && trim((string) $revision['sha1']) !== ''
                            ? (string) $revision['sha1']
                            : null,
                        'sha1_hidden' => array_key_exists('sha1hidden', $revision),
                        'text_hidden' => array_key_exists('texthidden', $revision),
                        'size' => isset($revision['size']) ? (int) $revision['size'] : null,
                        'tags' => $this->stringList($revision['tags'] ?? []),
                    ];
                }
            }
        }

        foreach ($revisionIds as $revisionId) {
            $details[$revisionId] ??= [
                'exists' => false,
                'revision_id' => $revisionId,
                'page_id' => null,
                'namespace_id' => null,
                'user_id' => null,
                'user_hidden' => false,
                'suppressed' => false,
                'sha1' => null,
                'sha1_hidden' => false,
                'text_hidden' => false,
                'size' => null,
                'tags' => [],
            ];
        }

        ksort($details);

        return $details;
    }

    /**
     * @param  list<int>  $pageIds
     * @return array<int, array{
     *     exists: bool,
     *     page_id: int,
     *     namespace_id: ?int,
     *     title: ?string,
     *     size: ?int,
     *     redirect: bool
     * }>
     */
    public function pageDetails(array $pageIds): array
    {
        $pageIds = $this->positiveUniqueIds($pageIds);

        if ($pageIds === []) {
            return [];
        }

        $details = [];

        foreach (array_chunk($pageIds, 50) as $chunk) {
            $payload = $this->request([
                'action' => 'query',
                'prop' => 'info|revisions',
                'pageids' => implode('|', $chunk),
                'rvlimit' => 1,
                'rvprop' => 'ids|size',
            ]);

            $pages = data_get($payload, 'query.pages');

            if (! is_array($pages)) {
                throw new MaddraxikonApiException(
                    'Die Maddraxikon-API lieferte keine gültigen Seitendaten.'
                );
            }

            foreach ($pages as $page) {
                if (! is_array($page) || ! isset($page['pageid'])) {
                    continue;
                }

                $pageId = (int) $page['pageid'];
                $latestRevision = is_array($page['revisions'] ?? null)
                    ? ($page['revisions'][0] ?? null)
                    : null;

                $details[$pageId] = [
                    'exists' => ! array_key_exists('missing', $page) && $pageId > 0,
                    'page_id' => $pageId,
                    'namespace_id' => isset($page['ns']) ? (int) $page['ns'] : null,
                    'title' => isset($page['title']) ? (string) $page['title'] : null,
                    'size' => is_array($latestRevision) && isset($latestRevision['size'])
                        ? (int) $latestRevision['size']
                        : (isset($page['length']) ? (int) $page['length'] : null),
                    'redirect' => array_key_exists('redirect', $page),
                ];
            }
        }

        foreach ($pageIds as $pageId) {
            $details[$pageId] ??= [
                'exists' => false,
                'page_id' => $pageId,
                'namespace_id' => null,
                'title' => null,
                'size' => null,
                'redirect' => false,
            ];
        }

        ksort($details);

        return $details;
    }

    /**
     * @return array<int, string>
     */
    public function namespaces(): array
    {
        $payload = $this->request([
            'action' => 'query',
            'meta' => 'siteinfo',
            'siprop' => 'namespaces|namespacealiases',
        ]);

        $namespaces = data_get($payload, 'query.namespaces');

        if (! is_array($namespaces)) {
            throw new MaddraxikonApiException(
                'Die Maddraxikon-API lieferte keine gültige Namensraumliste.'
            );
        }

        $resolved = [];

        foreach ($namespaces as $id => $namespace) {
            if (! is_array($namespace)) {
                continue;
            }

            $namespaceId = isset($namespace['id']) ? (int) $namespace['id'] : (int) $id;
            $resolved[$namespaceId] = (string) ($namespace['name'] ?? $namespace['canonical'] ?? '');
        }

        ksort($resolved);

        return $resolved;
    }

    /**
     * @param  array<string, int|string>  $parameters
     * @return array<string, mixed>
     */
    private function request(array $parameters): array
    {
        $attempts = max(1, (int) config('maddraxikon.http.attempts', 3));
        $delayMs = max(0, (int) config('maddraxikon.http.retry_delay_ms', 250));
        $lastException = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $response = Http::acceptJson()
                    ->withUserAgent((string) config(
                        'maddraxikon.http.user_agent',
                        'OMXFC-Maddraxikon-Baxx/1.0'
                    ))
                    ->connectTimeout((int) config('maddraxikon.http.connect_timeout', 5))
                    ->timeout((int) config('maddraxikon.http.timeout', 15))
                    ->withOptions(['allow_redirects' => false])
                    ->get($this->requestGuard->trustedApiUrl(), [
                        ...$parameters,
                        'format' => 'json',
                        'formatversion' => 2,
                        'maxlag' => (int) config('maddraxikon.http.maxlag', 5),
                    ]);

                if (! $response->successful()) {
                    $exception = new MaddraxikonApiException(
                        'Die Maddraxikon-API antwortete mit HTTP '.$response->status().'.',
                        null,
                        $response->status()
                    );

                    if ($this->isTransientStatus($response->status()) && $attempt < $attempts) {
                        $lastException = $exception;
                        $this->waitBeforeRetry($delayMs, $attempt, $response);

                        continue;
                    }

                    throw $exception;
                }

                $payload = $this->decodeResponse($response);
                $apiError = $payload['error'] ?? null;

                if (is_array($apiError)) {
                    $code = isset($apiError['code']) ? (string) $apiError['code'] : null;
                    $message = isset($apiError['info'])
                        ? (string) $apiError['info']
                        : 'Die Maddraxikon-API meldete einen Fehler.';

                    $exception = new MaddraxikonApiException(
                        $message,
                        $code,
                        $response->status()
                    );

                    if ($this->isTransientApiCode($code) && $attempt < $attempts) {
                        $lastException = $exception;
                        $this->waitBeforeRetry($delayMs, $attempt, $response);

                        continue;
                    }

                    throw $exception;
                }

                return $payload;
            } catch (ConnectionException $exception) {
                $lastException = $exception;

                if ($attempt < $attempts) {
                    $this->waitBeforeRetry($delayMs, $attempt);

                    continue;
                }
            } catch (MaddraxikonApiException $exception) {
                throw $exception;
            } catch (Throwable $exception) {
                throw new MaddraxikonApiException(
                    'Die Antwort der Maddraxikon-API konnte nicht verarbeitet werden.'
                );
            }
        }

        throw new MaddraxikonApiException(
            'Die Maddraxikon-API ist nach mehreren Versuchen nicht erreichbar: '.
            ($lastException?->getMessage() ?? 'unbekannter Fehler')
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse(Response $response): array
    {
        try {
            $payload = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new MaddraxikonApiException(
                'Die Maddraxikon-API lieferte ungültiges JSON.',
                null,
                $response->status()
            );
        }

        if (! is_array($payload)) {
            throw new MaddraxikonApiException(
                'Die Maddraxikon-API lieferte keine JSON-Objektantwort.',
                null,
                $response->status()
            );
        }

        return $payload;
    }

    /**
     * @return list<int>
     */
    private function allowedNamespaces(): array
    {
        return collect(config(
            'maddraxikon.allowed_namespaces',
            [0, 10, 14, 102, 106, 108, 112, 420]
        ))
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function isTransientApiCode(?string $code): bool
    {
        return in_array($code, ['maxlag', 'ratelimited', 'readonly'], true);
    }

    private function isTransientStatus(int $status): bool
    {
        return $status === 429 || $status >= 500;
    }

    private function waitBeforeRetry(
        int $baseDelayMs,
        int $attempt,
        ?Response $response = null
    ): void {
        $maximumDelayMs = max(
            0,
            (int) config('maddraxikon.http.retry_max_delay_ms', 5000)
        );

        if ($maximumDelayMs === 0) {
            return;
        }

        $backoffMs = $baseDelayMs > 0
            ? $baseDelayMs * (2 ** ($attempt - 1))
            : 0;
        $retryAfterMs = $response instanceof Response
            ? $this->retryAfterDelayMs($response)
            : 0;
        $delayMs = min($maximumDelayMs, max($backoffMs, $retryAfterMs));

        if ($delayMs <= 0) {
            return;
        }

        $jitterMs = random_int(0, min(250, max(1, intdiv($delayMs, 5))));
        usleep(min($maximumDelayMs, $delayMs + $jitterMs) * 1000);
    }

    private function retryAfterDelayMs(Response $response): int
    {
        $retryAfter = trim((string) $response->header('Retry-After'));

        if ($retryAfter === '') {
            return 0;
        }

        if (ctype_digit($retryAfter)) {
            return (int) $retryAfter * 1000;
        }

        $retryAt = strtotime($retryAfter);

        return $retryAt === false
            ? 0
            : max(0, ($retryAt - time()) * 1000);
    }

    /**
     * @param  array<int, mixed>  $values
     * @return list<int>
     */
    private function positiveUniqueIds(array $values): array
    {
        return collect($values)
            ->map(static fn (mixed $value): int => (int) $value)
            ->filter(static fn (int $value): bool => $value > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return collect($values)
            ->filter(static fn (mixed $value): bool => is_string($value))
            ->values()
            ->all();
    }
}
