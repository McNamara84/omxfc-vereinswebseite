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
                        'user_hidden' => $this->mediaWikiFlag($revision, 'userhidden'),
                        'suppressed' => $this->mediaWikiFlag($revision, 'suppressed'),
                        'sha1' => isset($revision['sha1']) && trim((string) $revision['sha1']) !== ''
                            ? (string) $revision['sha1']
                            : null,
                        'sha1_hidden' => $this->mediaWikiFlag($revision, 'sha1hidden'),
                        'text_hidden' => $this->mediaWikiFlag($revision, 'texthidden'),
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
                'prop' => 'info',
                'pageids' => implode('|', $chunk),
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

                $details[$pageId] = [
                    'exists' => ! $this->mediaWikiFlag($page, 'missing') && $pageId > 0,
                    'page_id' => $pageId,
                    'namespace_id' => isset($page['ns']) ? (int) $page['ns'] : null,
                    'title' => isset($page['title']) ? (string) $page['title'] : null,
                    'size' => isset($page['length']) ? (int) $page['length'] : null,
                    'redirect' => $this->mediaWikiFlag($page, 'redirect'),
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
     * Resolve the representative image and its file metadata for article page IDs.
     *
     * @param  array<int, mixed>  $pageIds
     * @return array<int, array{
     *     exists: bool,
     *     page_id: int,
     *     file_title: ?string,
     *     url: ?string,
     *     description_url: ?string,
     *     sha1: ?string,
     *     mime_type: ?string,
     *     width: ?int,
     *     height: ?int,
     *     artist: ?string,
     *     credit: ?string,
     *     license: ?string,
     *     license_url: ?string
     * }>
     */
    public function coverImages(array $pageIds): array
    {
        $pageIds = $this->positiveUniqueIds($pageIds);

        if ($pageIds === []) {
            return [];
        }

        $pageImages = [];
        $fileTitles = [];

        foreach (array_chunk($pageIds, 50) as $chunk) {
            $payload = $this->request([
                'action' => 'query',
                'prop' => 'pageimages',
                'pageids' => implode('|', $chunk),
                'piprop' => 'name|original',
                'pilicense' => 'any',
            ]);
            $pages = data_get($payload, 'query.pages');

            if (! is_array($pages)) {
                throw new MaddraxikonApiException(
                    'Die Maddraxikon-API lieferte keine gültigen Coverdaten.'
                );
            }

            foreach ($pages as $page) {
                if (! is_array($page) || ! isset($page['pageid'])) {
                    continue;
                }

                $pageId = (int) $page['pageid'];
                $fileName = isset($page['pageimage']) ? trim((string) $page['pageimage']) : '';
                $original = is_array($page['original'] ?? null) ? $page['original'] : [];
                $pageImages[$pageId] = [
                    'file_name' => $fileName !== '' ? $fileName : null,
                    'fallback_url' => isset($original['source']) ? (string) $original['source'] : null,
                    'fallback_width' => isset($original['width']) ? (int) $original['width'] : null,
                    'fallback_height' => isset($original['height']) ? (int) $original['height'] : null,
                ];

                if ($fileName !== '') {
                    $fileTitles[] = 'File:'.$fileName;
                }
            }
        }

        $fileDetails = $this->imageDetails($fileTitles);
        $resolved = [];

        foreach ($pageIds as $pageId) {
            $pageImage = $pageImages[$pageId] ?? null;
            $fileName = is_array($pageImage) ? $pageImage['file_name'] : null;
            $detail = is_string($fileName)
                ? ($fileDetails[$this->normalizeFileKey($fileName)] ?? null)
                : null;
            $fallbackUrl = is_array($pageImage) ? $pageImage['fallback_url'] : null;
            $url = is_array($detail) ? ($detail['url'] ?? null) : $fallbackUrl;

            $resolved[$pageId] = [
                'exists' => is_string($fileName) && is_string($url) && $url !== '',
                'page_id' => $pageId,
                'file_title' => is_array($detail) ? $detail['file_title'] : $fileName,
                'url' => is_string($url) && $url !== '' ? $url : null,
                'description_url' => is_array($detail) ? $detail['description_url'] : null,
                'sha1' => is_array($detail) ? $detail['sha1'] : null,
                'mime_type' => is_array($detail) ? $detail['mime_type'] : null,
                'width' => is_array($detail) ? $detail['width'] : ($pageImage['fallback_width'] ?? null),
                'height' => is_array($detail) ? $detail['height'] : ($pageImage['fallback_height'] ?? null),
                'artist' => is_array($detail) ? $detail['artist'] : null,
                'credit' => is_array($detail) ? $detail['credit'] : null,
                'license' => is_array($detail) ? $detail['license'] : null,
                'license_url' => is_array($detail) ? $detail['license_url'] : null,
            ];
        }

        return $resolved;
    }

    /**
     * @param  list<string>  $fileTitles
     * @return array<string, array<string, mixed>>
     */
    private function imageDetails(array $fileTitles): array
    {
        $details = [];
        $targetWidth = max(720, (int) config('cover-ratings.images.large_width', 720));

        foreach (array_chunk(array_values(array_unique($fileTitles)), 50) as $chunk) {
            $payload = $this->request([
                'action' => 'query',
                'prop' => 'imageinfo',
                'titles' => implode('|', $chunk),
                'iiprop' => 'url|size|sha1|mime|extmetadata',
                'iiurlwidth' => $targetWidth,
            ]);
            $pages = data_get($payload, 'query.pages');

            if (! is_array($pages)) {
                throw new MaddraxikonApiException(
                    'Die Maddraxikon-API lieferte keine gültigen Cover-Dateidaten.'
                );
            }

            foreach ($pages as $page) {
                if (! is_array($page)) {
                    continue;
                }

                $imageInfo = is_array($page['imageinfo'][0] ?? null)
                    ? $page['imageinfo'][0]
                    : null;

                if (! is_array($imageInfo)) {
                    continue;
                }

                $title = trim((string) ($page['title'] ?? ''));
                $fileName = str_contains($title, ':')
                    ? explode(':', $title, 2)[1]
                    : $title;
                $metadata = is_array($imageInfo['extmetadata'] ?? null)
                    ? $imageInfo['extmetadata']
                    : [];

                $details[$this->normalizeFileKey($fileName)] = [
                    'file_title' => $title !== '' ? $title : $fileName,
                    'url' => (string) ($imageInfo['thumburl'] ?? $imageInfo['url'] ?? ''),
                    'description_url' => $this->safePublicUrl($imageInfo['descriptionurl'] ?? null),
                    'sha1' => $this->nullableString($imageInfo['sha1'] ?? null),
                    'mime_type' => $this->nullableString($imageInfo['mime'] ?? null),
                    'width' => isset($imageInfo['thumbwidth'])
                        ? (int) $imageInfo['thumbwidth']
                        : (isset($imageInfo['width']) ? (int) $imageInfo['width'] : null),
                    'height' => isset($imageInfo['thumbheight'])
                        ? (int) $imageInfo['thumbheight']
                        : (isset($imageInfo['height']) ? (int) $imageInfo['height'] : null),
                    'artist' => $this->metadataText($metadata, 'Artist'),
                    'credit' => $this->metadataText($metadata, 'Credit'),
                    'license' => $this->metadataText($metadata, 'LicenseShortName')
                        ?? $this->metadataText($metadata, 'UsageTerms'),
                    'license_url' => $this->metadataUrl($metadata, 'LicenseUrl'),
                ];
            }
        }

        return $details;
    }

    private function metadataText(array $metadata, string $key): ?string
    {
        $value = data_get($metadata, $key.'.value');

        if (! is_string($value)) {
            return null;
        }

        $text = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(
            strip_tags($value),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8',
        )));

        return $text === '' ? null : mb_substr($text, 0, 4000);
    }

    private function metadataUrl(array $metadata, string $key): ?string
    {
        $value = data_get($metadata, $key.'.value');

        return $this->safePublicUrl($value);
    }

    private function normalizeFileKey(string $fileName): string
    {
        return mb_strtolower(str_replace('_', ' ', trim($fileName)));
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function safePublicUrl(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);
        $parts = parse_url($value);

        if (
            $value === ''
            || ! filter_var($value, FILTER_VALIDATE_URL)
            || ! is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || ! isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['fragment'])
        ) {
            return null;
        }

        return $value;
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

    /** @param array<string, mixed> $payload */
    private function mediaWikiFlag(array $payload, string $key): bool
    {
        return array_key_exists($key, $payload)
            && ! in_array($payload[$key], [false, null, 0, '0'], true);
    }
}
