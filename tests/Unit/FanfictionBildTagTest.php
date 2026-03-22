<?php

namespace Tests\Unit;

use App\Models\Fanfiction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FanfictionBildTagTest extends TestCase
{
    // Tests use in-memory models only, but TestCase::setUp() calls $this->seed()
    // which requires migrated tables – RefreshDatabase provides them.
    use RefreshDatabase;
    private function createFanfiction(string $content, array $photos = []): Fanfiction
    {
        $fanfiction = new Fanfiction([
            'content' => $content,
            'title' => 'Testgeschichte',
        ]);

        // Set photos directly (model is not persisted, so no DB needed)
        $fanfiction->photos = $photos;

        return $fanfiction;
    }

    // ── Tag-Ersetzung ──────────────────────────────────────────

    public function test_bild_tag_is_replaced_with_figure_element(): void
    {
        $fanfiction = $this->createFanfiction(
            'Text davor [bild:1] Text danach',
            ['fanfiction/photo1.jpg']
        );

        $html = $fanfiction->renderFormattedContent($fanfiction->content, $fanfiction->photos);

        $this->assertStringContainsString('<figure', $html);
        $this->assertStringContainsString('fanfiction-bild', $html);
        $this->assertStringContainsString('photo1.jpg', $html);
    }

    public function test_bild_tag_with_position_links(): void
    {
        $fanfiction = $this->createFanfiction(
            'Text [bild:1:links] weiter',
            ['fanfiction/photo1.jpg']
        );

        $html = $fanfiction->renderFormattedContent($fanfiction->content, $fanfiction->photos);

        $this->assertStringContainsString('fanfiction-bild--links', $html);
    }

    public function test_bild_tag_with_position_rechts(): void
    {
        $fanfiction = $this->createFanfiction(
            'Text [bild:1:rechts] weiter',
            ['fanfiction/photo1.jpg']
        );

        $html = $fanfiction->renderFormattedContent($fanfiction->content, $fanfiction->photos);

        $this->assertStringContainsString('fanfiction-bild--rechts', $html);
    }

    public function test_bild_tag_with_position_zentriert(): void
    {
        $fanfiction = $this->createFanfiction(
            'Text [bild:1:zentriert] weiter',
            ['fanfiction/photo1.jpg']
        );

        $html = $fanfiction->renderFormattedContent($fanfiction->content, $fanfiction->photos);

        $this->assertStringContainsString('fanfiction-bild--zentriert', $html);
    }

    public function test_bild_tag_default_position_is_zentriert(): void
    {
        $fanfiction = $this->createFanfiction(
            'Text [bild:1] weiter',
            ['fanfiction/photo1.jpg']
        );

        $html = $fanfiction->renderFormattedContent($fanfiction->content, $fanfiction->photos);

        $this->assertStringContainsString('fanfiction-bild--zentriert', $html);
    }

    public function test_bild_tag_with_caption(): void
    {
        $fanfiction = $this->createFanfiction(
            'Text [bild:1:zentriert:Szene in Dorado] weiter',
            ['fanfiction/photo1.jpg']
        );

        $html = $fanfiction->renderFormattedContent($fanfiction->content, $fanfiction->photos);

        $this->assertStringContainsString('<figcaption>Szene in Dorado</figcaption>', $html);
        $this->assertStringContainsString('alt="Szene in Dorado"', $html);
    }

    public function test_bild_tag_with_links_and_caption(): void
    {
        $fanfiction = $this->createFanfiction(
            '[bild:2:links:Illustration zum Kapitel]',
            ['fanfiction/photo1.jpg', 'fanfiction/photo2.jpg']
        );

        $html = $fanfiction->renderFormattedContent($fanfiction->content, $fanfiction->photos);

        $this->assertStringContainsString('fanfiction-bild--links', $html);
        $this->assertStringContainsString('<figcaption>Illustration zum Kapitel</figcaption>', $html);
        $this->assertStringContainsString('photo2.jpg', $html);
    }

    public function test_bild_tag_without_caption_has_no_figcaption(): void
    {
        $fanfiction = $this->createFanfiction(
            'Text [bild:1] weiter',
            ['fanfiction/photo1.jpg']
        );

        $html = $fanfiction->renderFormattedContent($fanfiction->content, $fanfiction->photos);

        $this->assertStringNotContainsString('<figcaption>', $html);
    }

    // ── Ungültige Indizes ──────────────────────────────────────

    public function test_bild_tag_with_invalid_index_is_removed(): void
    {
        $fanfiction = $this->createFanfiction(
            'Text [bild:99] weiter',
            ['fanfiction/photo1.jpg']
        );

        $html = $fanfiction->renderFormattedContent($fanfiction->content, $fanfiction->photos);

        $this->assertStringNotContainsString('[bild:', $html);
        $this->assertStringNotContainsString('<figure', $html);
    }

    public function test_bild_tag_with_zero_index_is_removed(): void
    {
        $fanfiction = $this->createFanfiction(
            'Text [bild:0] weiter',
            ['fanfiction/photo1.jpg']
        );

        $html = $fanfiction->renderFormattedContent($fanfiction->content, $fanfiction->photos);

        $this->assertStringNotContainsString('[bild:', $html);
        $this->assertStringNotContainsString('<figure', $html);
    }

    // ── Kein Tag / Keine Bilder ────────────────────────────────

    public function test_no_bild_tags_leaves_html_unchanged(): void
    {
        $fanfiction = $this->createFanfiction(
            'Normaler **Markdown** Text.',
            ['fanfiction/photo1.jpg']
        );

        $html = $fanfiction->renderFormattedContent($fanfiction->content, $fanfiction->photos);

        $this->assertStringContainsString('<strong>Markdown</strong>', $html);
        $this->assertStringNotContainsString('<figure', $html);
    }

    public function test_bild_tags_removed_when_no_photos_exist(): void
    {
        $fanfiction = $this->createFanfiction(
            'Text [bild:1] weiter',
            []
        );

        $html = $fanfiction->renderFormattedContent($fanfiction->content, []);

        $this->assertStringNotContainsString('[bild:', $html);
        $this->assertStringNotContainsString('<figure', $html);
    }

    // ── Mehrere Tags ───────────────────────────────────────────

    public function test_multiple_bild_tags_all_replaced(): void
    {
        $fanfiction = $this->createFanfiction(
            'Anfang [bild:1:links:Erstes Bild] Mitte [bild:2:rechts:Zweites Bild] Ende',
            ['fanfiction/photo1.jpg', 'fanfiction/photo2.jpg']
        );

        $html = $fanfiction->renderFormattedContent($fanfiction->content, $fanfiction->photos);

        $this->assertStringContainsString('fanfiction-bild--links', $html);
        $this->assertStringContainsString('fanfiction-bild--rechts', $html);
        $this->assertStringContainsString('photo1.jpg', $html);
        $this->assertStringContainsString('photo2.jpg', $html);
        $this->assertStringContainsString('Erstes Bild', $html);
        $this->assertStringContainsString('Zweites Bild', $html);
    }

    // ── XSS-Schutz ────────────────────────────────────────────

    public function test_xss_in_caption_is_escaped(): void
    {
        $fanfiction = $this->createFanfiction(
            'Text [bild:1:zentriert:<script>alert("xss")</script>] weiter',
            ['fanfiction/photo1.jpg']
        );

        $html = $fanfiction->renderFormattedContent($fanfiction->content, $fanfiction->photos);

        // Captions are escaped via e() in buildFigureHtml(), so <script> cannot appear as raw HTML
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('</script>', $html);
        // The remaining text is still rendered (sanitized via htmlspecialchars)
        $this->assertStringContainsString('alert(', $html);
    }

    // ── Case-Insensitivität ────────────────────────────────────

    public function test_bild_tag_is_case_insensitive(): void
    {
        $fanfiction = $this->createFanfiction(
            'Text [Bild:1:Links:Test] weiter',
            ['fanfiction/photo1.jpg']
        );

        $html = $fanfiction->renderFormattedContent($fanfiction->content, $fanfiction->photos);

        $this->assertStringContainsString('fanfiction-bild--links', $html);
        $this->assertStringContainsString('<figure', $html);
    }

    // ── getReferencedPhotoIndices ──────────────────────────────

    public function test_get_referenced_photo_indices_returns_correct_indices(): void
    {
        $fanfiction = $this->createFanfiction(
            'Text [bild:1] und [bild:3]',
            ['fanfiction/a.jpg', 'fanfiction/b.jpg', 'fanfiction/c.jpg']
        );

        $indices = $fanfiction->getReferencedPhotoIndices();

        $this->assertContains(0, $indices);
        $this->assertContains(2, $indices);
        $this->assertNotContains(1, $indices);
    }

    public function test_get_referenced_photo_indices_ignores_invalid(): void
    {
        $fanfiction = $this->createFanfiction(
            'Text [bild:99]',
            ['fanfiction/a.jpg']
        );

        $indices = $fanfiction->getReferencedPhotoIndices();

        $this->assertEmpty($indices);
    }

    public function test_get_referenced_photo_indices_returns_empty_for_no_tags(): void
    {
        $fanfiction = $this->createFanfiction(
            'Normaler Text ohne Tags',
            ['fanfiction/a.jpg']
        );

        $indices = $fanfiction->getReferencedPhotoIndices();

        $this->assertEmpty($indices);
    }

    // ── getUnreferencedPhotos ──────────────────────────────────

    public function test_get_unreferenced_photos_excludes_referenced(): void
    {
        $fanfiction = $this->createFanfiction(
            'Text [bild:1] und [bild:3]',
            ['fanfiction/a.jpg', 'fanfiction/b.jpg', 'fanfiction/c.jpg']
        );

        $unreferenced = $fanfiction->getUnreferencedPhotos();

        $this->assertCount(1, $unreferenced);
        $this->assertContains('fanfiction/b.jpg', $unreferenced);
    }

    public function test_get_unreferenced_photos_returns_all_when_no_tags(): void
    {
        $fanfiction = $this->createFanfiction(
            'Kein Tag hier',
            ['fanfiction/a.jpg', 'fanfiction/b.jpg']
        );

        $unreferenced = $fanfiction->getUnreferencedPhotos();

        $this->assertCount(2, $unreferenced);
    }

    public function test_get_unreferenced_photos_returns_empty_when_all_referenced(): void
    {
        $fanfiction = $this->createFanfiction(
            '[bild:1] [bild:2]',
            ['fanfiction/a.jpg', 'fanfiction/b.jpg']
        );

        $unreferenced = $fanfiction->getUnreferencedPhotos();

        $this->assertEmpty($unreferenced);
    }

    // ── img-Attribute ──────────────────────────────────────────

    public function test_img_has_loading_lazy_attribute(): void
    {
        $fanfiction = $this->createFanfiction(
            'Text [bild:1] weiter',
            ['fanfiction/photo1.jpg']
        );

        $html = $fanfiction->renderFormattedContent($fanfiction->content, $fanfiction->photos);

        $this->assertStringContainsString('loading="lazy"', $html);
    }

    public function test_img_alt_text_uses_caption_when_provided(): void
    {
        $fanfiction = $this->createFanfiction(
            '[bild:1:zentriert:Mein Alt-Text]',
            ['fanfiction/photo1.jpg']
        );

        $html = $fanfiction->renderFormattedContent($fanfiction->content, $fanfiction->photos);

        $this->assertStringContainsString('alt="Mein Alt-Text"', $html);
    }

    public function test_img_alt_text_uses_title_when_no_caption(): void
    {
        $fanfiction = $this->createFanfiction(
            '[bild:1]',
            ['fanfiction/photo1.jpg']
        );

        $html = $fanfiction->renderFormattedContent($fanfiction->content, $fanfiction->photos);

        $this->assertStringContainsString('alt="Testgeschichte"', $html);
    }
}
