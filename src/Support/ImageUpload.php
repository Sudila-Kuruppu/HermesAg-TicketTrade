<?php

/**
 * TicketTrade — Support\ImageUpload
 *
 * Per AD-14 + CONTEXT D-08..D-14: 4-layer validation pipeline for
 * listing image uploads, with three WebP thumbnails (200/600/1200 px,
 * WebP 80% quality) generated at upload time.
 *
 * Layer 1: finfo_file MIME check (image/jpeg | image/png | image/webp).
 * Layer 2: getimagesize() dimensions <= 4000px and filesize <= 5MB.
 * Layer 3: magic-byte check on first 12 bytes (JPEG 0xFFD8FF, PNG
 *          0x89504E47, WebP 0x52494646....57454250).
 * Layer 4: GD re-encode to WebP via imagecreatefromstring + imagewebp.
 *
 * Storage: <storage_root>/<sha256>_<size>.webp, three files per
 *          original image. The original is NOT kept; only WebP outputs are
 *          written. SHA256 is computed on the ORIGINAL bytes for the
 *          filename (so duplicates dedupe) and for the audit trail.
 *
 * Per D-09: max 8 files per listing; the 9th and beyond are dropped
 * with a per-file E_IMAGE_INVALID entry.
 *
 * Per CONTEXT: NEVER throws. Returns the AD-16 failure envelope with
 * a 'data' array that always has 'uploaded' (array of success rows)
 * and 'errors' (array of per-file failures with code+message).
 */

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

class ImageUpload
{
    /** Allowed sizes for output thumbnails. */
    public const SIZES = ['thumb', 'medium', 'full'];

    /** Magic-byte prefixes for the three accepted formats. */
    private const MAGIC_JPEG = "\xFF\xD8\xFF";
    private const MAGIC_PNG  = "\x89PNG\r\n\x1A\n";
    private const MAGIC_WEBP = "RIFF";

    /**
     * Process a $_FILES['images'] shaped array of upload descriptors.
     *
     * Normalises PHP's grouped $_FILES shape (name[], tmp_name[], size[],
     * error[], type[]) into a list of single-file descriptors and runs
     * the 4-layer pipeline. Inserts listing_images rows for each
     * successful upload via App\Listing\Service\listing_service.
     *
     * NOTE: this method only WRITES the WebP bytes to disk. The caller
     * (Service) is responsible for inserting the listing_images rows
     * because the Service is the sole writer (AD-1).
     *
     * @param int   $listingId The listing the images belong to.
     * @param array $files     Either a single descriptor
     *                         (['name'=>..., 'tmp_name'=>...]) or the
     *                         grouped $_FILES['images'] shape.
     * @return array{ok:bool,data:array{uploaded:array,errors:array},error:?array}
     */
    public static function process(int $listingId, array $files): array
    {
        $cfg = require APP_ROOT . '/config/uploads.php';
        $root = rtrim((string) $cfg['storage_root'], '/');
        if (!is_dir($root)) {
            @mkdir($root, 0775, true);
        }

        $normalized = self::normalizeFiles($files);
        $existingCount = self::existingImageCount($listingId);
        $capRemaining = max(0, (int) $cfg['max_files'] - $existingCount);

        $uploaded = [];
        $errors = [];

        $i = 0;
        foreach ($normalized as $idx => $file) {
            $i++;
            // Cap at 8: per-file rejection, do not stop the loop.
            if ($i > $capRemaining) {
                $errors[] = [
                    'index' => $idx,
                    'name' => (string) ($file['name'] ?? ''),
                    'code' => 'E_IMAGE_INVALID',
                    'message' => 'Too many images - max ' . (int) $cfg['max_files'] . ' per listing.',
                ];
                continue;
            }

            $result = self::processOne($file, $cfg, $root);
            if (!empty($result['silent'])) {
                // UPLOAD_ERR_NO_FILE - skip without recording.
                continue;
            }
            if ($result['ok']) {
                $uploaded[] = [
                    'index' => $idx,
                    'name' => (string) ($file['name'] ?? ''),
                    'sha256' => $result['sha256'],
                    'sizes' => $result['sizes'],
                    'primary' => ($existingCount + count($uploaded)) === 1,
                    'sort_order' => $existingCount + count($uploaded),
                ];
            } else {
                $errors[] = [
                    'index' => $idx,
                    'name' => (string) ($file['name'] ?? ''),
                    'code' => $result['code'],
                    'message' => $result['message'],
                ];
            }
        }

        return [
            'ok' => true,
            'data' => [
                'uploaded' => $uploaded,
                'errors' => $errors,
            ],
            'error' => null,
        ];
    }

    /**
     * Process a single file descriptor through the 4-layer pipeline.
     * Returns ['ok'=>bool, 'sha256'=>?, 'sizes'=>[...], 'code'=>?, 'message'=>?].
     *
     * @param array $file Single descriptor
     * @param array $cfg  config/uploads.php
     * @param string $root Storage root absolute path
     */
    private static function processOne(array $file, array $cfg, string $root): array
    {
        $tmp = (string) ($file['tmp_name'] ?? '');
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        // PHP upload error codes (Per D-11; layer 0 = transport-level).
        if ($error === UPLOAD_ERR_NO_FILE) {
            // No file in this slot; silent skip per task 2 spec.
            // Returns ok=true with empty result so the caller does not add
            // a spurious error entry.
            return ['ok' => true, 'sha256' => '', 'sizes' => [], 'silent' => true];
        }
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            return ['ok' => false, 'code' => 'E_IMAGE_TOO_LARGE', 'message' => 'Image exceeds the size limit.'];
        }
        if ($error !== UPLOAD_ERR_OK || $tmp === '' || !is_file($tmp)) {
            return ['ok' => false, 'code' => 'E_IMAGE_INVALID', 'message' => 'Upload transport error.'];
        }

        $size = (int) filesize($tmp);
        $maxBytes = (int) $cfg['max_file_bytes'];
        if ($size <= 0 || $size > $maxBytes) {
            return ['ok' => false, 'code' => 'E_IMAGE_TOO_LARGE', 'message' => 'Image exceeds the 5MB size limit.'];
        }

        // Layer 1: finfo MIME.
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp) ?: '';
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mime, $allowedMimes, true)) {
            return ['ok' => false, 'code' => 'E_IMAGE_INVALID', 'message' => 'Unsupported image type (' . $mime . ').'];
        }

        // Layer 2: getimagesize dimensions and integrity.
        $info = @getimagesize($tmp);
        if ($info === false) {
            return ['ok' => false, 'code' => 'E_IMAGE_INVALID', 'message' => 'Image is corrupt or unreadable.'];
        }
        [$w, $h] = $info;
        $maxDim = (int) $cfg['max_dim_px'];
        if ($w <= 0 || $h <= 0 || $w > $maxDim || $h > $maxDim) {
            return ['ok' => false, 'code' => 'E_IMAGE_INVALID', 'message' => 'Image dimensions exceed the limit.'];
        }

        // Layer 3: magic-byte check on first 12 bytes.
        $fp = fopen($tmp, 'rb');
        if ($fp === false) {
            return ['ok' => false, 'code' => 'E_IMAGE_INVALID', 'message' => 'Cannot read image bytes.'];
        }
        $head = fread($fp, 12);
        fclose($fp);
        if ($head === false || strlen($head) < 4) {
            return ['ok' => false, 'code' => 'E_IMAGE_INVALID', 'message' => 'Image header is unreadable.'];
        }
        $magicOk = false;
        if ($mime === 'image/jpeg' && str_starts_with($head, self::MAGIC_JPEG)) {
            $magicOk = true;
        } elseif ($mime === 'image/png' && str_starts_with($head, self::MAGIC_PNG)) {
            $magicOk = true;
        } elseif ($mime === 'image/webp' && str_starts_with($head, self::MAGIC_WEBP) && str_contains(substr($head, 8, 4), 'WEBP')) {
            // WebP: RIFF????WEBP — bytes 0-3 RIFF, 8-11 WEBP.
            $magicOk = true;
        }
        if (!$magicOk) {
            return ['ok' => false, 'code' => 'E_IMAGE_INVALID', 'message' => 'Image magic bytes do not match declared type.'];
        }

        // Compute SHA256 of the original bytes BEFORE GD decoding. This is
        // the file's identity hash; the WebP outputs are derived from it.
        $sha256 = hash_file('sha256', $tmp);
        if ($sha256 === false) {
            return ['ok' => false, 'code' => 'E_IMAGE_INVALID', 'message' => 'Cannot hash image.'];
        }

        // Layer 4: GD re-encode.
        $bytes = file_get_contents($tmp);
        if ($bytes === false) {
            return ['ok' => false, 'code' => 'E_IMAGE_INVALID', 'message' => 'Cannot read image for re-encoding.'];
        }
        $src = @imagecreatefromstring($bytes);
        if ($src === false) {
            return ['ok' => false, 'code' => 'E_IMAGE_INVALID', 'message' => 'GD cannot decode image.'];
        }

        $quality = (int) $cfg['webp_quality'];
        $thumbPx = (int) $cfg['thumb_px'];
        $mediumPx = (int) $cfg['medium_px'];
        $fullPx = (int) $cfg['full_px'];

        $writtenSizes = [];
        foreach (['thumb' => $thumbPx, 'medium' => $mediumPx, 'full' => $fullPx] as $sizeName => $targetPx) {
            $gd = self::resample($src, $w, $h, $targetPx);
            $path = sprintf('%s/%s_%s.webp', $root, $sha256, $sizeName);
            $ok = @imagewebp($gd, $path, $quality);
            imagedestroy($gd);
            if (!$ok) {
                // Roll back already-written sizes for this sha.
                self::rollbackSizes($root, $sha256, array_keys($writtenSizes));
                imagedestroy($src);
                return ['ok' => false, 'code' => 'E_IMAGE_INVALID', 'message' => 'Failed to write WebP for ' . $sizeName . '.'];
            }
            $writtenSizes[] = $sizeName;
        }
        imagedestroy($src);

        return ['ok' => true, 'sha256' => $sha256, 'sizes' => $writtenSizes];
    }

    /**
     * Resample source GD image to fit within targetPx on its longest side.
     */
    private static function resample(\GdImage $src, int $w, int $h, int $targetPx): \GdImage
    {
        $ratio = $w >= $h ? $w : $h;
        if ($ratio <= $targetPx) {
            // Source is smaller than target; emit a copy.
            $newW = $w;
            $newH = $h;
        } else {
            $scale = $targetPx / $ratio;
            $newW = max(1, (int) round($w * $scale));
            $newH = max(1, (int) round($h * $scale));
        }
        $dst = imagecreatetruecolor($newW, $newH);
        // Preserve alpha for PNG/WebP sources.
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $newW, $newH, $transparent);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);
        return $dst;
    }

    /**
     * Delete any files already written for this sha at the listed sizes.
     */
    private static function rollbackSizes(string $root, string $sha256, array $sizeNames): void
    {
        foreach ($sizeNames as $sn) {
            @unlink(sprintf('%s/%s_%s.webp', $root, $sha256, $sn));
        }
    }

    /**
     * Normalise the PHP $_FILES['images'] shape into a list of single-file
     * descriptors so the loop body is uniform.
     *
     * Accepts both:
     *   - grouped: ['name'=>[...], 'tmp_name'=>[...], 'size'=>[...], 'error'=>[...], 'type'=>[...]]
     *   - single:  ['name'=>string, 'tmp_name'=>string, ...]
     *
     * @return array<int, array<string, mixed>>
     */
    private static function normalizeFiles(array $files): array
    {
        // Detect the single-descriptor shape: top-level keys are name/tmp_name/etc.
        if (isset($files['name']) && !is_array($files['name'])) {
            return [$files];
        }
        // Detect the single-descriptor-wrapped shape: a list of one descriptor.
        if (
            count($files) === 1
            && isset($files[0])
            && is_array($files[0])
            && array_key_exists('name', $files[0])
        ) {
            return [$files[0]];
        }
        // Grouped shape: name[], tmp_name[], size[], error[], type[].
        if (isset($files['name']) && is_array($files['name'])) {
            $out = [];
            $count = count($files['name']);
            for ($i = 0; $i < $count; $i++) {
                $out[] = [
                    'name' => $files['name'][$i] ?? '',
                    'tmp_name' => $files['tmp_name'][$i] ?? '',
                    'size' => $files['size'][$i] ?? 0,
                    'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                    'type' => $files['type'][$i] ?? '',
                ];
            }
            return $out;
        }
        // Fallback: treat as already-normalized list.
        return $files;
    }

    /**
     * Count the listing_images rows already attached to this listing.
     * Returns 0 if the table does not exist yet (e.g. before migrations).
     */
    private static function existingImageCount(int $listingId): int
    {
        try {
            $stmt = Db::pdo()->prepare('SELECT COUNT(*) FROM listing_images WHERE listing_id = ?');
            $stmt->execute([$listingId]);
            return (int) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
