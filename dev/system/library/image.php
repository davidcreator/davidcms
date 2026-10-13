<?php
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
    private string $file;
    private \GdImage $image;
    private int $width;
    private int $height;
    private string $bits;
    private string $mime;
    
    // Supported MIME types
    private const SUPPORTED_TYPES = [
        'image/gif',
        'image/png', 
        'image/jpeg',
        'image/webp'
    ];

    /**
     * Constructor
     * @param string $file Path to image file
     * @throws \Exception If GD extension not loaded or file cannot be loaded
     */
    public function __construct(string $file) {
        if (!extension_loaded('gd')) {
            throw new \RuntimeException('PHP GD extension is not installed');
        }

        if (!is_file($file) || !is_readable($file)) {
            throw new \InvalidArgumentException('Could not load image: ' . $file);
        }

        $this->file = $file;
        $info = getimagesize($file);

        if ($info === false) {
            throw new \RuntimeException('Invalid image file: ' . $file);
        }

        $this->width = $info[0];
        $this->height = $info[1];
        $this->bits = $info['bits'] ?? '';
        $this->mime = $info['mime'] ?? '';

        if (!in_array($this->mime, self::SUPPORTED_TYPES)) {
            throw new \RuntimeException('Unsupported image type: ' . $this->mime);
        }

        $this->image = $this->createImageResource($file);
    }

    /**
     * Create image resource based on MIME type
     * @param string $file
     * @return \GdImage
     * @throws \RuntimeException
     */
    private function createImageResource(string $file): \GdImage {
        $image = false;
        
        switch ($this->mime) {
            case 'image/gif':
                $image = imagecreatefromgif($file);
                break;
            case 'image/png':
                $image = $this->createPngResource($file);
                break;
            case 'image/jpeg':
                $image = imagecreatefromjpeg($file);
                break;
            case 'image/webp':
                $image = imagecreatefromwebp($file);
                break;
        }

        if ($image === false) {
            throw new \RuntimeException('Failed to create image resource from: ' . $file);
        }

        return $image;
    }

    /**
     * Load PNG without noisy libpng profile warnings
     */
    private function createPngResource(string $file): \GdImage|false {
        $previousHandler = set_error_handler(
            function (int $severity, string $message): bool {
                if (str_contains($message, 'libpng warning: iCCP') || str_contains($message, 'libpng warning: cHRM')) {
                    // swallow only the known color profile warnings
                    return true;
                }
                // let any other warnings bubble up
                return false;
            },
            E_WARNING
        );

        $previousReporting = error_reporting();
        error_reporting($previousReporting & ~E_WARNING);

        $image = imagecreatefrompng($file);

        error_reporting($previousReporting);
        restore_error_handler();

        if ($image !== false) {
            imageinterlace($image, false);
            if (function_exists('imagepalettetotruecolor')) {
                imagepalettetotruecolor($image);
            }
        }

        return $image;
    }

    /**
     * Destructor - cleanup image resource
     */
    public function __destruct() {
        if (isset($this->image) && $this->image instanceof \GdImage) {
            imagedestroy($this->image);
        }
    }

    /**
     * getFile
     * @return string
     */
    public function getFile(): string {
        return $this->file;
    }

    /**
     * getImage
     * @return \GdImage
     */
    public function getImage(): \GdImage {
        return $this->image;
    }

    /**
     * getWidth
     * @return int
     */
    public function getWidth(): int {
        return $this->width;
    }

    /**
     * getHeight
     * @return int
     */
    public function getHeight(): int {
        return $this->height;
    }

    /**
     * getBits
     * @return string
     */
    public function getBits(): string {
        return $this->bits;
    }

    /**
     * getMime
     * @return string
     */
    public function getMime(): string {
        return $this->mime;
    }

    /**
     * Save image to file
     * @param string $file Output file path
     * @param int $quality JPEG quality (0-100) or PNG compression (0-9)
     * @throws \RuntimeException If image save fails
     */
    public function save(string $file, int $quality = 90): void {
        if (!($this->image instanceof \GdImage)) {
            throw new \RuntimeException('No valid image resource to save');
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
                // For PNG, quality represents compression level (0-9)
                $compression = max(0, min(9, intval($quality / 10)));
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

    /**
     * Resize image
     * @param int $width Target width
     * @param int $height Target height  
     * @param string $default Resize mode: 'w' (width priority), 'h' (height priority), '' (fit)
     * @return void
     */
    public function resize(int $width = 0, int $height = 0, string $default = ''): void {
        if (!$this->width || !$this->height || ($width <= 0 && $height <= 0)) {
            return;
        }

        // If only one dimension provided, maintain aspect ratio
        if ($width <= 0) {
            $width = intval($this->width * ($height / $this->height));
        }
        if ($height <= 0) {
            $height = intval($this->height * ($width / $this->width));
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

        // Skip if no scaling needed and not PNG/WebP (which might need format conversion)
        if ($scale == 1 && $scale_h == $scale_w && 
            !in_array($this->mime, ['image/png', 'image/webp'])) {
            return;
        }

        $new_width = intval($this->width * $scale);
        $new_height = intval($this->height * $scale);
        $xpos = intval(($width - $new_width) / 2);
        $ypos = intval(($height - $new_height) / 2);

        $image_old = $this->image;
        $this->image = imagecreatetruecolor($width, $height);

        // Preserve transparency for PNG and WebP
        if (in_array($this->mime, ['image/png', 'image/webp'])) {
            imagealphablending($this->image, false);
            imagesavealpha($this->image, true);
            $background = imagecolorallocatealpha($this->image, 255, 255, 255, 127);
            imagecolortransparent($this->image, $background);
        } else {
            $background = imagecolorallocate($this->image, 255, 255, 255);
        }

        imagefilledrectangle($this->image, 0, 0, $width, $height, $background);
        imagecopyresampled($this->image, $image_old, $xpos, $ypos, 0, 0, 
                          $new_width, $new_height, $this->width, $this->height);
        
        imagedestroy($image_old);

        $this->width = $width;
        $this->height = $height;
    }

    /**
     * Add watermark to image
     * @param Image $watermark Watermark image instance
     * @param string $position Position: topleft, topcenter, topright, middleleft, middlecenter, middleright, bottomleft, bottomcenter, bottomright
     * @return void
     * @throws \InvalidArgumentException
     */
    public function watermark(Image $watermark, string $position = 'bottomright'): void {
        $positions = [
            'topleft' => [0, 0],
            'topcenter' => [intval(($this->width - $watermark->getWidth()) / 2), 0],
            'topright' => [$this->width - $watermark->getWidth(), 0],
            'middleleft' => [0, intval(($this->height - $watermark->getHeight()) / 2)],
            'middlecenter' => [intval(($this->width - $watermark->getWidth()) / 2), 
                             intval(($this->height - $watermark->getHeight()) / 2)],
            'middleright' => [$this->width - $watermark->getWidth(), 
                            intval(($this->height - $watermark->getHeight()) / 2)],
            'bottomleft' => [0, $this->height - $watermark->getHeight()],
            'bottomcenter' => [intval(($this->width - $watermark->getWidth()) / 2), 
                             $this->height - $watermark->getHeight()],
            'bottomright' => [$this->width - $watermark->getWidth(), 
                            $this->height - $watermark->getHeight()]
        ];

        if (!isset($positions[$position])) {
            throw new \InvalidArgumentException('Invalid watermark position: ' . $position);
        }

        [$watermark_pos_x, $watermark_pos_y] = $positions[$position];

        // Ensure watermark fits within image bounds
        $watermark_pos_x = max(0, min($watermark_pos_x, $this->width - $watermark->getWidth()));
        $watermark_pos_y = max(0, min($watermark_pos_y, $this->height - $watermark->getHeight()));

        imagealphablending($this->image, true);
        imagesavealpha($this->image, true);
        imagecopy($this->image, $watermark->getImage(), $watermark_pos_x, $watermark_pos_y, 
                 0, 0, $watermark->getWidth(), $watermark->getHeight());
    }

    /**
     * Crop image
     * @param int $top_x Top-left X coordinate
     * @param int $top_y Top-left Y coordinate  
     * @param int $bottom_x Bottom-right X coordinate
     * @param int $bottom_y Bottom-right Y coordinate
     * @return void
     * @throws \InvalidArgumentException
     */
    public function crop(int $top_x, int $top_y, int $bottom_x, int $bottom_y): void {
        // Validate crop coordinates
        if ($top_x < 0 || $top_y < 0 || $bottom_x <= $top_x || $bottom_y <= $top_y ||
            $bottom_x > $this->width || $bottom_y > $this->height) {
            throw new \InvalidArgumentException('Invalid crop coordinates');
        }

        $new_width = $bottom_x - $top_x;
        $new_height = $bottom_y - $top_y;

        $image_old = $this->image;
        $this->image = imagecreatetruecolor($new_width, $new_height);

        // Preserve transparency for PNG and WebP
        if (in_array($this->mime, ['image/png', 'image/webp'])) {
            imagealphablending($this->image, false);
            imagesavealpha($this->image, true);
            $transparent = imagecolorallocatealpha($this->image, 0, 0, 0, 127);
            imagefill($this->image, 0, 0, $transparent);
        }

        imagecopy($this->image, $image_old, 0, 0, $top_x, $top_y, $new_width, $new_height);
        imagedestroy($image_old);

        $this->width = $new_width;
        $this->height = $new_height;
    }

    /**
     * Rotate image
     * @param int $degree Rotation angle in degrees
     * @param string $color Background color in hex format
     * @return void
     */
    public function rotate(int $degree, string $color = 'FFFFFF'): void {
        $rgb = $this->html2rgb($color);
        
        if (empty($rgb)) {
            throw new \InvalidArgumentException('Invalid color format: ' . $color);
        }

        $background_color = imagecolorallocate($this->image, $rgb[0], $rgb[1], $rgb[2]);
        $this->image = imagerotate($this->image, $degree, $background_color);

        if ($this->image === false) {
            throw new \RuntimeException('Failed to rotate image');
        }

        $this->width = imagesx($this->image);
        $this->height = imagesy($this->image);
    }

    /**
     * Apply image filter
     * @param int $filter Filter constant
     * @param mixed ...$args Additional filter arguments
     * @return void
     */
    public function filter(int $filter, ...$args): void {
        $result = imagefilter($this->image, $filter, ...$args);
        
        if (!$result) {
            throw new \RuntimeException('Failed to apply image filter');
        }
    }

    /**
     * Add text to image
     * @param string $text Text to add
     * @param int $x X coordinate
     * @param int $y Y coordinate  
     * @param int $size Font size (1-5)
     * @param string $color Text color in hex format
     * @return void
     */
    public function text(string $text, int $x = 0, int $y = 0, int $size = 5, string $color = '000000'): void {
        $size = max(1, min(5, $size));
        $rgb = $this->html2rgb($color);
        
        if (empty($rgb)) {
            throw new \InvalidArgumentException('Invalid color format: ' . $color);
        }

        $text_color = imagecolorallocate($this->image, $rgb[0], $rgb[1], $rgb[2]);
        imagestring($this->image, $size, $x, $y, $text, $text_color);
    }

    /**
     * Merge another image
     * @param Image $merge Image to merge
     * @param int $x X coordinate
     * @param int $y Y coordinate
     * @param int $opacity Opacity (0-100)
     * @return void
     */
    public function merge(Image $merge, int $x = 0, int $y = 0, int $opacity = 100): void {
        $opacity = max(0, min(100, $opacity));
        
        $result = imagecopymerge($this->image, $merge->getImage(), $x, $y, 0, 0, 
                               $merge->getWidth(), $merge->getHeight(), $opacity);
        
        if (!$result) {
            throw new \RuntimeException('Failed to merge images');
        }
    }

    /**
     * Convert HTML color to RGB array
     * @param string $color HTML color code
     * @return array RGB values [r, g, b]
     */
    private function html2rgb(string $color): array {
        $color = ltrim($color, '#');
        
        if (strlen($color) === 6) {
            $r = hexdec(substr($color, 0, 2));
            $g = hexdec(substr($color, 2, 2));
            $b = hexdec(substr($color, 4, 2));
        } elseif (strlen($color) === 3) {
            $r = hexdec(str_repeat($color[0], 2));
            $g = hexdec(str_repeat($color[1], 2));
            $b = hexdec(str_repeat($color[2], 2));
        } else {
            return [];
        }

        return [$r, $g, $b];
    }
}
