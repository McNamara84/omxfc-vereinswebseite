<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\TestCase;

pest()->extend(TestCase::class)->use(FanfictionBildTagTestFixtures::class);

test('bild tag is replaced with figure element', function (): void {
    $fanfiction = $this->createFanfiction('Text davor [bild:1] Text danach', ['fanfiction/photo1.jpg']);
    $html = $fanfiction->renderFormattedContent($fanfiction->content, $fanfiction->photos);
    expect($html)->toContain('<figure')
        ->toContain('fanfiction-bild')
        ->toContain('photo1.jpg');
});

test('bild tag with position links', function (): void {
    $fanfiction = $this->createFanfiction('Text [bild:1:links] weiter', ['fanfiction/photo1.jpg']);
    $html = $fanfiction->renderFormattedContent($fanfiction->content, $fanfiction->photos);
    expect($html)->toContain('fanfiction-bild--links');
});

test('bild tag with position rechts', function (): void {
    $fanfiction = $this->createFanfiction('Text [bild:1:rechts] weiter', ['fanfiction/photo1.jpg']);
    $html = $fanfiction->renderFormattedContent($fanfiction->content, $fanfiction->photos);
    expect($html)->toContain('fanfiction-bild--rechts');
});

test('bild tag with position zentriert', function (): void {
    $fanfiction = $this->createFanfiction('Text [bild:1:zentriert] weiter', ['fanfiction/photo1.jpg']);
    $html = $fanfiction->renderFormattedContent($fanfiction->content, $fanfiction->photos);
    expect($html)->toContain('fanfiction-bild--zentriert');
});

test('bild tag default position is zentriert', function (): void {
    $fanfiction = $this->createFanfiction('Text [bild:1] weiter', ['fanfiction/photo1.jpg']);
    $html = $fanfiction->renderFormattedContent($fanfiction->content, $fanfiction->photos);
    expect($html)->toContain('fanfiction-bild--zentriert');
});

test('links are sanitized with php 8 5 uri support rules', function (): void {
    $fanfiction = $this->createFanfiction('[Safe](https://example.com) [Broken](http:///example.com) [Protocol](//example.com/path) [Anchor](#anchor) [Relative](docs/page?section=1) [Bad](javascript:alert(1))');
    $html = $fanfiction->renderFormattedContent($fanfiction->content, $fanfiction->photos);
    expect($html)->toContain('<a href="https://example.com" rel="noopener noreferrer">Safe</a>')
        ->toContain('<a rel="noopener noreferrer">Broken</a>')
        ->toContain('<a rel="noopener noreferrer">Protocol</a>')
        ->toContain('<a href="#anchor" rel="noopener noreferrer">Anchor</a>')
        ->toContain('<a href="docs/page?section=1" rel="noopener noreferrer">Relative</a>')
        ->toContain('<a rel="noopener noreferrer">Bad</a>');
});

test('bild tag with caption', function (): void {
    $fanfiction = $this->createFanfiction('Text [bild:1:zentriert:Szene in Dorado] weiter', ['fanfiction/photo1.jpg']);
    $html = $fanfiction->renderFormattedContent($fanfiction->content, $fanfiction->photos);
    expect($html)->toContain('<figcaption>Szene in Dorado</figcaption>')
        ->toContain('alt="Szene in Dorado"');
});

test('bild tag with links and caption', function (): void {
    $fanfiction = $this->createFanfiction('[bild:2:links:Illustration zum Kapitel]', ['fanfiction/photo1.jpg', 'fanfiction/photo2.jpg']);
    $html = $fanfiction->renderFormattedContent($fanfiction->content, $fanfiction->photos);
    expect($html)->toContain('fanfiction-bild--links')
        ->toContain('<figcaption>Illustration zum Kapitel</figcaption>')
        ->toContain('photo2.jpg');
});

test('bild tag without caption has no figcaption', function (): void {
    $fanfiction = $this->createFanfiction('Text [bild:1] weiter', ['fanfiction/photo1.jpg']);
    $html = $fanfiction->renderFormattedContent($fanfiction->content, $fanfiction->photos);
    expect($html)->not->toContain('<figcaption>');
});

test('bild tag with invalid index is removed', function (): void {
    $fanfiction = $this->createFanfiction('Text [bild:99] weiter', ['fanfiction/photo1.jpg']);
    $html = $fanfiction->renderFormattedContent($fanfiction->content, $fanfiction->photos);
    expect($html)->not->toContain('[bild:')->not->toContain('<figure');
});

test('bild tag with zero index is removed', function (): void {
    $fanfiction = $this->createFanfiction('Text [bild:0] weiter', ['fanfiction/photo1.jpg']);
    $html = $fanfiction->renderFormattedContent($fanfiction->content, $fanfiction->photos);
    expect($html)->not->toContain('[bild:')->not->toContain('<figure');
});

test('no bild tags leaves html unchanged', function (): void {
    $fanfiction = $this->createFanfiction('Normaler **Markdown** Text.', ['fanfiction/photo1.jpg']);
    $html = $fanfiction->renderFormattedContent($fanfiction->content, $fanfiction->photos);
    expect($html)->toContain('<strong>Markdown</strong>')->not->toContain('<figure');
});

test('bild tags removed when no photos exist', function (): void {
    $fanfiction = $this->createFanfiction('Text [bild:1] weiter', []);
    $html = $fanfiction->renderFormattedContent($fanfiction->content, []);
    expect($html)->not->toContain('[bild:')->not->toContain('<figure');
});

test('multiple bild tags all replaced', function (): void {
    $fanfiction = $this->createFanfiction('Anfang [bild:1:links:Erstes Bild] Mitte [bild:2:rechts:Zweites Bild] Ende', ['fanfiction/photo1.jpg', 'fanfiction/photo2.jpg']);
    $html = $fanfiction->renderFormattedContent($fanfiction->content, $fanfiction->photos);
    expect($html)->toContain('fanfiction-bild--links')
        ->toContain('fanfiction-bild--rechts')
        ->toContain('photo1.jpg')
        ->toContain('photo2.jpg')
        ->toContain('Erstes Bild')
        ->toContain('Zweites Bild');
});

test('xss in caption is escaped', function (): void {
    $fanfiction = $this->createFanfiction('Text [bild:1:zentriert:<script>alert("xss")</script>] weiter', ['fanfiction/photo1.jpg']);
    $html = $fanfiction->renderFormattedContent($fanfiction->content, $fanfiction->photos);
    // Captions are escaped via e() in buildFigureHtml(), so <script> cannot appear as raw HTML
    expect($html)->not->toContain('<script>');
    expect($html)->not->toContain('</script>');
    // The remaining text is still rendered (sanitized via htmlspecialchars)
    expect($html)->toContain('alert(');
});

test('bild tag is case insensitive', function (): void {
    $fanfiction = $this->createFanfiction('Text [Bild:1:Links:Test] weiter', ['fanfiction/photo1.jpg']);
    $html = $fanfiction->renderFormattedContent($fanfiction->content, $fanfiction->photos);
    expect($html)->toContain('fanfiction-bild--links')
        ->toContain('<figure');
});

test('get referenced photo indices returns correct indices', function (): void {
    $fanfiction = $this->createFanfiction('Text [bild:1] und [bild:3]', ['fanfiction/a.jpg', 'fanfiction/b.jpg', 'fanfiction/c.jpg']);
    $indices = $fanfiction->getReferencedPhotoIndices();
    expect($indices)->toContain(0)
        ->toContain(2)->not->toContain(1);
});

test('get referenced photo indices ignores invalid', function (): void {
    $fanfiction = $this->createFanfiction('Text [bild:99]', ['fanfiction/a.jpg']);
    $indices = $fanfiction->getReferencedPhotoIndices();
    expect($indices)->toBeEmpty();
});

test('get referenced photo indices returns empty for no tags', function (): void {
    $fanfiction = $this->createFanfiction('Normaler Text ohne Tags', ['fanfiction/a.jpg']);
    $indices = $fanfiction->getReferencedPhotoIndices();
    expect($indices)->toBeEmpty();
});

test('get unreferenced photos excludes referenced', function (): void {
    $fanfiction = $this->createFanfiction('Text [bild:1] und [bild:3]', ['fanfiction/a.jpg', 'fanfiction/b.jpg', 'fanfiction/c.jpg']);
    $unreferenced = $fanfiction->getUnreferencedPhotos();
    expect($unreferenced)->toHaveCount(1)
        ->toContain('fanfiction/b.jpg');
});

test('get unreferenced photos returns all when no tags', function (): void {
    $fanfiction = $this->createFanfiction('Kein Tag hier', ['fanfiction/a.jpg', 'fanfiction/b.jpg']);
    $unreferenced = $fanfiction->getUnreferencedPhotos();
    expect($unreferenced)->toHaveCount(2);
});

test('get unreferenced photos returns empty when all referenced', function (): void {
    $fanfiction = $this->createFanfiction('[bild:1] [bild:2]', ['fanfiction/a.jpg', 'fanfiction/b.jpg']);
    $unreferenced = $fanfiction->getUnreferencedPhotos();
    expect($unreferenced)->toBeEmpty();
});

test('img has loading lazy attribute', function (): void {
    $fanfiction = $this->createFanfiction('Text [bild:1] weiter', ['fanfiction/photo1.jpg']);
    $html = $fanfiction->renderFormattedContent($fanfiction->content, $fanfiction->photos);
    expect($html)->toContain('loading="lazy"');
});

test('img alt text uses caption when provided', function (): void {
    $fanfiction = $this->createFanfiction('[bild:1:zentriert:Mein Alt-Text]', ['fanfiction/photo1.jpg']);
    $html = $fanfiction->renderFormattedContent($fanfiction->content, $fanfiction->photos);
    expect($html)->toContain('alt="Mein Alt-Text"');
});

test('img alt text uses title when no caption', function (): void {
    $fanfiction = $this->createFanfiction('[bild:1]', ['fanfiction/photo1.jpg']);
    $html = $fanfiction->renderFormattedContent($fanfiction->content, $fanfiction->photos);
    expect($html)->toContain('alt="Testgeschichte"');
});
