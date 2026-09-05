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

        $directory = "attachments/{$roomId}";
        $path = $file->storeAs($directory, $storedFilename, 'public');

        $mimeType = $file->getMimeType() ?: 'application/octet-stream';
        $type = $this->determineMediaType($mimeType, $extension);

        return [
            'path' => $path,
            'name' => $file->getClientOriginalName(),
            'type' => $type,
            'mime' => $mimeType,
            'size' => $file->getSize() ?: 0,
        ];
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
     * Delete a specific attachment file from public storage.
     */
    public function deleteAttachment(?string $path): bool
    {
        if (! $path) {
            return false;
        }

        return Storage::disk('public')->delete($path);
    }
}

