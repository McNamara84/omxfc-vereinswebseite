<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;

trait NextcloudGalleryServiceTestFixtures
{
    use RefreshDatabase;

    private function propfindResponse(string $token, array $files): string
    {
        $entries = array_map(fn (string $file): array => ['href' => '/public.php/dav/files/'.$token.'/'.$file, 'contentType' => str_ends_with(strtolower($file), '.txt') ? 'text/plain' : 'image/jpeg'], $files);

        return $this->propfindResponseWithEntries('/public.php/dav/files/'.$token.'/', $entries);
    }

    private function propfindResponseWithDoctype(string $token, array $files): string
    {
        $xml = $this->propfindResponse($token, $files);

        return str_replace('<?xml version="1.0"?>', "<?xml version=\"1.0\"?>\n<!DOCTYPE multistatus [<!ELEMENT multistatus ANY>]>", $xml);
    }

    /**
     * @param  array<int, array{href: string, contentType: string}>  $entries
     */
    private function propfindResponseWithEntries(string $collectionHref, array $entries): string
    {
        $responses = [<<<XML
        <d:response>
            <d:href>{$collectionHref}</d:href>
            <d:propstat>
                <d:prop>
                    <d:resourcetype><d:collection/></d:resourcetype>
                </d:prop>
                <d:status>HTTP/1.1 200 OK</d:status>
            </d:propstat>
        </d:response>
        XML];
        foreach ($entries as $entry) {
            $responses[] = <<<XML
            <d:response>
                <d:href>{$entry['href']}</d:href>
                <d:propstat>
                    <d:prop>
                        <d:getcontenttype>{$entry['contentType']}</d:getcontenttype>
                    </d:prop>
                    <d:status>HTTP/1.1 200 OK</d:status>
                </d:propstat>
            </d:response>
            XML;
        }
        $items = implode('', $responses);

        return <<<XML
        <?xml version="1.0"?>
        <d:multistatus xmlns:d="DAV:">
            {$items}
        </d:multistatus>
        XML;
    }
}
