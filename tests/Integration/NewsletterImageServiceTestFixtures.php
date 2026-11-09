<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Foundation\Testing\RefreshDatabase;

trait NewsletterImageServiceTestFixtures
{
    use RefreshDatabase;

    private function pngWithDimensions(int $width, int $height): string
    {
        $header = pack('NNCCCCC', $width, $height, 8, 6, 0, 0, 0);

        return "\x89PNG\r\n\x1a\n".$this->pngChunk('IHDR', $header).$this->pngChunk('IEND', '');
    }

    private function pngChunk(string $type, string $data): string
    {
        return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
    }

    private function transparentPng(): string
    {
        $image = imagecreatetruecolor(2, 2);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $transparent);
        ob_start();
        imagepng($image);
        $contents = (string) ob_get_clean();

        return $contents;
    }

    private function orientedJpeg(): string
    {
        $image = imagecreatetruecolor(40, 20);
        $color = imagecolorallocate($image, 30, 120, 210);
        imagefill($image, 0, 0, $color);
        ob_start();
        imagejpeg($image, null, 90);
        $jpeg = (string) ob_get_clean();
        $tiff = 'II'.pack('v', 42).pack('V', 8).pack('v', 1).pack('v', 0x112).pack('v', 3).pack('V', 1).pack('v', 6)."\x00\x00".pack('V', 0);
        $exif = "Exif\x00\x00".$tiff;
        $app1 = "\xff\xe1".pack('n', strlen($exif) + 2).$exif;

        return substr($jpeg, 0, 2).$app1.substr($jpeg, 2);
    }
}
