<?php

namespace App\Libraries;

/**
 * Re-encodes uploaded JPEG / PNG / WebP images with GD: removes all metadata (EXIF GPS location,
 * camera data) and anything hidden inside the file. Phone photos are turned upright first, since the
 * EXIF orientation tag is dropped. GIFs are kept as they are (animation).
 */
class ImageCleaner
{
    public function clean(string $path, string $mime): void
    {
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) || ! function_exists('imagecreatefromstring')) {
            return;
        }

        try {
            $data  = (string) file_get_contents($path);
            $image = @imagecreatefromstring($data);
            if ($image === false) {
                return;
            }

            if ($mime === 'image/jpeg') {
                $image = $this->upright($image, $this->orientation($data));
                imagejpeg($image, $path, 90);
            } elseif ($mime === 'image/png') {
                imagealphablending($image, false);
                imagesavealpha($image, true);
                imagepng($image, $path, 6);
            } elseif (function_exists('imagewebp')) {
                imagealphablending($image, false);
                imagesavealpha($image, true);
                imagewebp($image, $path, 90);
            }
            imagedestroy($image);
            clearstatcache(true, $path);
        } catch (\Throwable $e) {
            // Keep the original file (e.g. too large for memory_limit), but note it.
            log_message('warning', 'Image could not be cleaned ({path}): {message}', ['path' => $path, 'message' => $e->getMessage()]);
        }
    }

    /**
     * EXIF orientation (1–8) read straight from the JPEG, without the exif extension.
     */
    private function orientation(string $jpeg): int
    {
        $exif = strpos($jpeg, "Exif\0\0");
        if ($exif === false || $exif > 65536) {
            return 1;
        }

        $tiff   = $exif + 6;
        $little = substr($jpeg, $tiff, 2) === 'II';
        $u16    = fn (int $at) => unpack($little ? 'v' : 'n', substr($jpeg, $at, 2))[1] ?? 0;
        $u32    = fn (int $at) => unpack($little ? 'V' : 'N', substr($jpeg, $at, 4))[1] ?? 0;

        $ifd     = $tiff + $u32($tiff + 4);
        $entries = $u16($ifd);
        for ($i = 0; $i < $entries && $i < 512; $i++) {
            $entry = $ifd + 2 + $i * 12;
            if ($u16($entry) === 0x0112) {
                $value = $u16($entry + 8);

                return $value >= 1 && $value <= 8 ? $value : 1;
            }
        }

        return 1;
    }

    private function upright(\GdImage $image, int $orientation): \GdImage
    {
        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }

        $angle = match ($orientation) {
            3, 4    => 180,
            5, 6    => 270,
            7, 8    => 90,
            default => 0,
        };

        return $angle === 0 ? $image : (imagerotate($image, $angle, 0) ?: $image);
    }
}
