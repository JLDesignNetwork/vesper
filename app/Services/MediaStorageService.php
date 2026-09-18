<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaStorageService
{
    /**
     * Store an uploaded media file and return its metadata.
     *
     * @return array{
     *     path: string,
     *     name: string,
     *     type: string,
     *     mime: string,
     *     size: int
     * }
     */
    public function storeAttachment(UploadedFile $file, string $roomId): array
    {
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $cleanName = Str::slug($originalName) ?: 'attachment';
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');
        $storedFilename = "{$cleanName}_".Str::random(16).".{$extension}";
        $mimeType = $file->getMimeType() ?: 'application/octet-stream';
        $type = $this->determineMediaType($mimeType, $extension);

        $directory = "attachments/{$roomId}";
        $relativePath = "{$directory}/{$storedFilename}";

        $cleanedContents = null;
        if ($type === 'image' && extension_loaded('gd')) {
            $cleanedContents = $this->stripExifData($file, $extension);
        }

        if ($cleanedContents !== null) {
            Storage::disk('local')->put($relativePath, $cleanedContents);
            $fileSize = strlen($cleanedContents);
        } else {
            $relativePath = $file->storeAs($directory, $storedFilename, 'local');
            $fileSize = $file->getSize() ?: 0;
        }

        return [
            'path' => $relativePath,
            'name' => $file->getClientOriginalName(),
            'type' => $type,
            'mime' => $mimeType,
            'size' => $fileSize,
        ];
    }

    /**
     * Strip EXIF and camera metadata from image files using GD.
     */
    protected function stripExifData(UploadedFile $file, string $extension): ?string
    {
        $realPath = $file->getRealPath();
        if (! $realPath || ! file_exists($realPath)) {
            return null;
        }

        $image = null;
        try {
            switch ($extension) {
                case 'jpg':
                case 'jpeg':
                    if (function_exists('imagecreatefromjpeg')) {
                        $image = @imagecreatefromjpeg($realPath);
                    }
                    break;
                case 'png':
                    if (function_exists('imagecreatefrompng')) {
                        $image = @imagecreatefrompng($realPath);
                        if ($image) {
                            imagealphablending($image, false);
                            imagesavealpha($image, true);
                        }
                    }
                    break;
                case 'webp':
                    if (function_exists('imagecreatefromwebp')) {
                        $image = @imagecreatefromwebp($realPath);
                        if ($image) {
                            imagealphablending($image, false);
                            imagesavealpha($image, true);
                        }
                    }
                    break;
            }

            if (! $image) {
                return null;
            }

            ob_start();
            switch ($extension) {
                case 'jpg':
                case 'jpeg':
                    imagejpeg($image, null, 90);
                    break;
                case 'png':
                    imagepng($image, null, 6);
                    break;
                case 'webp':
                    imagewebp($image, null, 90);
                    break;
            }
            $cleanData = ob_get_clean();
            imagedestroy($image);

            return $cleanData ?: null;
        } catch (\Throwable) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (is_resource($image) || (is_object($image) && $image instanceof \GdImage)) {
                imagedestroy($image);
            }

            return null;
        }
    }

    /**
     * Determine media category based on MIME type and extension.
     */
    public function determineMediaType(string $mime, string $extension): string
    {
        if (str_starts_with($mime, 'image/')) {
            return 'image';
        }

        if (str_starts_with($mime, 'video/')) {
            return 'video';
        }

        if (str_starts_with($mime, 'audio/')) {
            return 'audio';
        }

        $videoExtensions = ['mp4', 'mov', 'webm', 'ogg', 'mkv', 'avi'];
        if (in_array($extension, $videoExtensions, true)) {
            return 'video';
        }

        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
        if (in_array($extension, $imageExtensions, true)) {
            return 'image';
        }

        $audioExtensions = ['mp3', 'wav', 'ogg', 'm4a', 'aac'];
        if (in_array($extension, $audioExtensions, true)) {
            return 'audio';
        }

        return 'file';
    }

    /**
     * Delete a specific attachment file from storage.
     */
    public function deleteAttachment(?string $path): bool
    {
        if (! $path) {
            return false;
        }

        if (Storage::disk('local')->exists($path)) {
            return Storage::disk('local')->delete($path);
        }

        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->delete($path);
        }

        return false;
    }
}
