<?php declare(strict_types=1);
/**
 * @package ReamurCMS
 * @author David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license https://opensource.org/licenses/GPL-3.0
 * @link https://reamurcms.com
 */

namespace Reamur\System\Library;

/**
 * Image manipulation class using GD library
 */
class Image {
    private const MAX_DIMENSION = 16384;
    private const MAX_PIXELS = 67108864; // 64 MP decompression bomb guard

    private string $file;
    private \GdImage $image;
    private int $width;
    private int $height;
    private string $bits;
    private string $mime;

    /** @var array<int, string> */
    private const SUPPORTED_TYPES = [
        'image/gif',
        'image/png',
        'image/jpeg',
        'image/webp'
    ];

    public function __construct(string $file) {
        if (!extension_loaded('gd')) {
            throw new \RuntimeException('PHP GD extension is not installed');
        }

        if ($file === '' || str_contains($file, "\0")) {
            throw new \InvalidArgumentException('Invalid image file path');
        }

        if (!is_file($file) || !is_readable($file)) {
            throw new \InvalidArgumentException('Could not load image: ' . $file);
        }

        $this->file = $file;
        $info = @getimagesize($file);

        if ($info === false) {
            throw new \RuntimeException('Invalid image file: ' . $file);
        }

        $this->width = (int)$info[0];
        $this->height = (int)$info[1];
        $this->bits = (string)($info['bits'] ?? '');
        $this->mime = (string)($info['mime'] ?? '');

        if ($this->width <= 0 || $this->height <= 0
            || $this->width > self::MAX_DIMENSION
            || $this->height > self::MAX_DIMENSION
            || ($this->width * $this->height) > self::MAX_PIXELS) {
            throw new \RuntimeException('Image dimensions exceed allowed safety bounds: ' . $file);
        }

        if (!in_array($this->mime, self::SUPPORTED_TYPES, true)) {
            throw new \RuntimeException('Unsupported image type: ' . $this->mime);
        }

        $this->image = $this->createImageResource($file);
    }

    private function createImageResource(string $file): \GdImage {
        $image = false;

        switch ($this->mime) {
            case 'image/gif':
                $image = @imagecreatefromgif($file);
                break;
            case 'image/png':
                $image = $this->createPngResource($file);
                break;
            case 'image/jpeg':
                $image = @imagecreatefromjpeg($file);
                break;
            case 'image/webp':
                $image = @imagecreatefromwebp($file);
                break;
        }

        if (!$image instanceof \GdImage) {
            throw new \RuntimeException('Failed to create image resource from: ' . $file);
        }

        return $image;
    }

    private function createPngResource(string $file): \GdImage|false {
        // Suprime warnings não-fatais de perfis ICC/cHRM via @ sem mutar error_handler global.
        $image = @imagecreatefrompng($file);

        if ($image instanceof \GdImage) {
            imageinterlace($image, false);
            if (function_exists('imagepalettetotruecolor')) {
                imagepalettetotruecolor($image);
            }
        }

        return $image;
    }

    public function __destruct() {
        if (isset($this->image) && $this->image instanceof \GdImage) {
            imagedestroy($this->image);
        }
    }

    public function getFile(): string {
        return $this->file;
    }

    public function getImage(): \GdImage {
        return $this->image;
    }

    public function getWidth(): int {
        return $this->width;
    }

    public function getHeight(): int {
        return $this->height;
    }

    public function getBits(): string {
        return $this->bits;
    }

    public function getMime(): string {
        return $this->mime;
    }

    public function save(string $file, int $quality = 90): void {
        if ($file === '' || str_contains($file, "\0")) {
            throw new \InvalidArgumentException('Invalid output image path');
        }

        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $dir = dirname($file);

        if (!is_dir($dir) || !is_writable($dir)) {
            throw new \RuntimeException('Directory not writable: ' . $dir);
        }

        $success = false;
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                $quality = max(0, min(100, $quality));
                $success = imagejpeg($this->image, $file, $quality);
                break;
            case 'png':
                $compression = max(0, min(9, (int)floor($quality / 10)));
                $success = imagepng($this->image, $file, $compression);
                break;
            case 'gif':
                $success = imagegif($this->image, $file);
                break;
            case 'webp':
                $quality = max(0, min(100, $quality));
                $success = imagewebp($this->image, $file, $quality);
                break;
            default:
                throw new \InvalidArgumentException('Unsupported file extension: ' . $extension);
        }

        if (!$success) {
            throw new \RuntimeException('Failed to save image to: ' . $file);
        }
    }

    public function resize(int $width = 0, int $height = 0, string $default = ''): void {
        if (!$this->width || !$this->height || ($width <= 0 && $height <= 0)) {
            return;
        }

        if ($width <= 0) {
            $width = max(1, (int)round($this->width * ($height / $this->height)));
        }
        if ($height <= 0) {
            $height = max(1, (int)round($this->height * ($width / $this->width)));
        }

        if ($width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION || ($width * $height) > self::MAX_PIXELS) {
            throw new \InvalidArgumentException('Target image dimensions exceed allowed bounds');
        }

        $scale_w = $width / $this->width;
        $scale_h = $height / $this->height;

        switch ($default) {
            case 'w':
                $scale = $scale_w;
                break;
            case 'h':
                $scale = $scale_h;
                break;
            default:
                $scale = min($scale_w, $scale_h);
                break;
        }

        if ($scale == 1.0 && $scale_h == $scale_w && !in_array($this->mime, ['image/png', 'image/webp'], true)) {
            return;
        }

        $new_width = max(1, (int)round($this->width * $scale));
        $new_height = max(1, (int)round($this->height * $scale));
        $xpos = (int)(($width - $new_width) / 2);
        $ypos = (int)(($height - $new_height) / 2);

        $image_old = $this->image;
        $canvas = imagecreatetruecolor($width, $height);
        if (!$canvas instanceof \GdImage) {
            throw new \RuntimeException('Failed to allocate target image canvas');
        }

        if (in_array($this->mime, ['image/png', 'image/webp'], true)) {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $background = imagecolorallocatealpha($canvas, 255, 255, 255, 127);
            if ($background !== false) {
                imagecolortransparent($canvas, $background);
                imagefilledrectangle($canvas, 0, 0, $width, $height, $background);
            }
        } else {
            $background = imagecolorallocate($canvas, 255, 255, 255);
            if ($background !== false) {
                imagefilledrectangle($canvas, 0, 0, $width, $height, $background);
            }
        }

        imagecopyresampled(
            $canvas,
            $image_old,
            $xpos,
            $ypos,
            0,
            0,
            $new_width,
            $new_height,
            $this->width,
            $this->height
        );

        imagedestroy($image_old);
        $this->image = $canvas;
        $this->width = $width;
        $this->height = $height;
    }

    public function watermark(Image $watermark, string $position = 'bottomright'): void {
        $positions = [
            'topleft'      => [0, 0],
            'topcenter'    => [(int)(($this->width - $watermark->getWidth()) / 2), 0],
            'topright'     => [$this->width - $watermark->getWidth(), 0],
            'middleleft'   => [0, (int)(($this->height - $watermark->getHeight()) / 2)],
            'middlecenter' => [(int)(($this->width - $watermark->getWidth()) / 2), (int)(($this->height - $watermark->getHeight()) / 2)],
            'middleright'  => [$this->width - $watermark->getWidth(), (int)(($this->height - $watermark->getHeight()) / 2)],
            'bottomleft'   => [0, $this->height - $watermark->getHeight()],
            'bottomcenter' => [(int)(($this->width - $watermark->getWidth()) / 2), $this->height - $watermark->getHeight()],
            'bottomright'  => [$this->width - $watermark->getWidth(), $this->height - $watermark->getHeight()]
        ];

        if (!isset($positions[$position])) {
            throw new \InvalidArgumentException('Invalid watermark position: ' . $position);
        }

        [$watermark_pos_x, $watermark_pos_y] = $positions[$position];
        $watermark_pos_x = max(0, min($watermark_pos_x, max(0, $this->width - $watermark->getWidth())));
        $watermark_pos_y = max(0, min($watermark_pos_y, max(0, $this->height - $watermark->getHeight())));

        imagealphablending($this->image, true);
        imagesavealpha($this->image, true);
        imagecopy(
            $this->image,
            $watermark->getImage(),
            $watermark_pos_x,
            $watermark_pos_y,
            0,
            0,
            $watermark->getWidth(),
            $watermark->getHeight()
        );
    }

    public function crop(int $top_x, int $top_y, int $bottom_x, int $bottom_y): void {
        if ($top_x < 0 || $top_y < 0 || $bottom_x <= $top_x || $bottom_y <= $top_y
            || $bottom_x > $this->width || $bottom_y > $this->height) {
            throw new \InvalidArgumentException('Invalid crop coordinates');
        }

        $new_width = $bottom_x - $top_x;
        $new_height = $bottom_y - $top_y;

        $image_old = $this->image;
        $cropped = imagecreatetruecolor($new_width, $new_height);
        if (!$cropped instanceof \GdImage) {
            throw new \RuntimeException('Failed to allocate cropped image canvas');
        }

        if (in_array($this->mime, ['image/png', 'image/webp'], true)) {
            imagealphablending($cropped, false);
            imagesavealpha($cropped, true);
            $transparent = imagecolorallocatealpha($cropped, 0, 0, 0, 127);
            if ($transparent !== false) {
                imagefill($cropped, 0, 0, $transparent);
            }
        }

        imagecopy($cropped, $image_old, 0, 0, $top_x, $top_y, $new_width, $new_height);
        imagedestroy($image_old);

        $this->image = $cropped;
        $this->width = $new_width;
        $this->height = $new_height;
    }

    public function rotate(int $degree, string $color = 'FFFFFF'): void {
        $rgb = $this->html2rgb($color);

        if ($rgb === []) {
            throw new \InvalidArgumentException('Invalid color format: ' . $color);
        }

        $background_color = imagecolorallocate($this->image, $rgb[0], $rgb[1], $rgb[2]);
        if ($background_color === false) {
            throw new \RuntimeException('Failed to allocate background color');
        }

        $rotated = imagerotate($this->image, (float)$degree, $background_color);
        if (!$rotated instanceof \GdImage) {
            throw new \RuntimeException('Failed to rotate image');
        }

        imagedestroy($this->image);
        $this->image = $rotated;
        $this->width = imagesx($this->image);
        $this->height = imagesy($this->image);
    }

    public function filter(int $filter, mixed ...$args): void {
        $result = imagefilter($this->image, $filter, ...$args);

        if (!$result) {
            throw new \RuntimeException('Failed to apply image filter');
        }
    }

    public function text(string $text, int $x = 0, int $y = 0, int $size = 5, string $color = '000000'): void {
        $size = max(1, min(5, $size));
        $rgb = $this->html2rgb($color);

        if ($rgb === []) {
            throw new \InvalidArgumentException('Invalid color format: ' . $color);
        }

        $text_color = imagecolorallocate($this->image, $rgb[0], $rgb[1], $rgb[2]);
        if ($text_color !== false) {
            imagestring($this->image, $size, $x, $y, $text, $text_color);
        }
    }

    public function merge(Image $merge, int $x = 0, int $y = 0, int $opacity = 100): void {
        $opacity = max(0, min(100, $opacity));

        $result = imagecopymerge(
            $this->image,
            $merge->getImage(),
            $x,
            $y,
            0,
            0,
            $merge->getWidth(),
            $merge->getHeight(),
            $opacity
        );

        if (!$result) {
            throw new \RuntimeException('Failed to merge images');
        }
    }

    /**
     * Convert HTML color to RGB array
     * @param string $color HTML color code
     * @return array<int, int> RGB values [r, g, b]
     */
    private function html2rgb(string $color): array {
        $color = ltrim($color, '#');

        if (strlen($color) === 6 && ctype_xdigit($color)) {
            $r = (int)hexdec(substr($color, 0, 2));
            $g = (int)hexdec(substr($color, 2, 2));
            $b = (int)hexdec(substr($color, 4, 2));
        } elseif (strlen($color) === 3 && ctype_xdigit($color)) {
            $r = (int)hexdec(str_repeat($color[0], 2));
            $g = (int)hexdec(str_repeat($color[1], 2));
            $b = (int)hexdec(str_repeat($color[2], 2));
        } else {
            return [];
        }

        return [$r, $g, $b];
    }
}
