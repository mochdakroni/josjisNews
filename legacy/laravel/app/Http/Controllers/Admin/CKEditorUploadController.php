<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;

/**
 * Endpoint Upload & Direktori Media Redaksi (CKEditor 5 & Media Picker)
 * Mendukung:
 * 1. GET  /admin/media/library          -> Daftar file (Image, Video, PDF) yang sudah pernah diunggah di direktori S3/R2/Storage
 * 2. POST /admin/media/ckeditor-upload  -> Upload file baru (Gambar, Video MP4/WebM, atau Dokumen PDF)
 */
class CKEditorUploadController extends Controller
{
    private const ALLOWED_MIME_MAP = [
        // Gambar
        'image/jpeg' => ['ext' => 'jpg', 'type' => 'image', 'dir' => 'articles/images'],
        'image/png' => ['ext' => 'png', 'type' => 'image', 'dir' => 'articles/images'],
        'image/webp' => ['ext' => 'webp', 'type' => 'image', 'dir' => 'articles/images'],
        'image/gif' => ['ext' => 'gif', 'type' => 'image', 'dir' => 'articles/images'],
        // Video
        'video/mp4' => ['ext' => 'mp4', 'type' => 'video', 'dir' => 'articles/videos'],
        'video/webm' => ['ext' => 'webm', 'type' => 'video', 'dir' => 'articles/videos'],
        // Dokumen PDF
        'application/pdf' => ['ext' => 'pdf', 'type' => 'pdf', 'dir' => 'articles/documents'],
    ];

    /**
     * GET /admin/media/library
     * Menampilkan daftar file yang sudah pernah diunggah di dalam direktori media.
     */
    public function index(Request $request): JsonResponse
    {
        $filterType = $request->query('type'); // image | video | pdf | null (all)
        $diskName = config('filesystems.media_disk', 's3');
        $disk = Storage::disk($diskName);

        $directories = [
            'image' => 'articles/images',
            'video' => 'articles/videos',
            'pdf' => 'articles/documents',
            'featured' => 'articles/featured',
        ];

        $items = [];

        foreach ($directories as $type => $dir) {
            if ($filterType && $filterType !== $type && ! ($filterType === 'image' && $type === 'featured')) {
                continue;
            }

            try {
                $files = $disk->allFiles($dir);
                foreach ($files as $filePath) {
                    $normalizedType = $type === 'featured' ? 'image' : $type;
                    $items[] = [
                        'name' => basename($filePath),
                        'path' => $filePath,
                        'url' => $disk->url($filePath),
                        'type' => $normalizedType,
                    ];
                }
            } catch (\Throwable) {
                // Abaikan jika direktori belum terbentuk di bucket
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => $items,
        ]);
    }

    /**
     * POST /admin/media/ckeditor-upload
     * Mengunggah Gambar, Video (MP4/WebM), atau Dokumen PDF baru ke direktori S3/R2/Storage.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'upload' => [
                'required',
                File::types(['jpg', 'jpeg', 'png', 'webp', 'gif', 'mp4', 'webm', 'pdf'])
                    ->max(50 * 1024), // Maksimal 50 MB untuk mendukung video & PDF
            ],
        ]);

        $file = $request->file('upload');
        $realMime = $file->getMimeType();

        if (! $realMime || ! array_key_exists($realMime, self::ALLOWED_MIME_MAP)) {
            return response()->json([
                'error' => ['message' => 'Tipe file tidak diizinkan. Gunakan JPG, PNG, WebP, GIF, MP4, WebM, atau PDF.'],
            ], 422);
        }

        $meta = self::ALLOWED_MIME_MAP[$realMime];

        // Jika tipe gambar, verifikasi struktur header gambar via getimagesize()
        if ($meta['type'] === 'image') {
            $imageSize = @getimagesize($file->getRealPath());
            if ($imageSize === false) {
                return response()->json([
                    'error' => ['message' => 'File gambar rusak atau tidak valid.'],
                ], 422);
            }
        }

        $disk = config('filesystems.media_disk', 's3');
        $safeOriginalName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $filename = now()->format('Y/m/d/') . ($safeOriginalName ?: 'media') . '-' . Str::lower(Str::random(6)) . '.' . $meta['ext'];

        $path = Storage::disk($disk)->putFileAs(
            $meta['dir'],
            $file,
            $filename,
            'public'
        );

        $publicUrl = Storage::disk($disk)->url($path);

        return response()->json([
            'url' => $publicUrl,
            'type' => $meta['type'],
            'name' => basename($path),
            'path' => $path,
        ]);
    }
}
