<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUserWithRole;

trait PhotoGalleryControllerTestFixtures
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    private function propfindResponse(string $token, array $files, bool $includeImages = true): string
    {
        $responses = [<<<XML
        <d:response>
            <d:href>/public.php/dav/files/{$token}/</d:href>
            <d:propstat>
                <d:prop>
                    <d:resourcetype><d:collection/></d:resourcetype>
                </d:prop>
                <d:status>HTTP/1.1 200 OK</d:status>
            </d:propstat>
        </d:response>
        XML];
        foreach ($files as $file) {
            $contentType = $includeImages ? 'image/jpeg' : 'text/plain';
            $responses[] = <<<XML
            <d:response>
                <d:href>/public.php/dav/files/{$token}/{$file}</d:href>
                <d:propstat>
                    <d:prop>
                        <d:getcontenttype>{$contentType}</d:getcontenttype>
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
