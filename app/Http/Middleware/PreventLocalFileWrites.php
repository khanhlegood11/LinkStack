<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class PreventLocalFileWrites
{
    private const BLOCKED_PATHS = [
        'backup',
        'clearIcon/*',
        'env-editor',
        'env-editor/*',
        'import-data',
        'admin/site/delavatar',
        'admin/site/delfavicon',
        'studio/background',
        'studio/rem-background',
        'theme-updater',
        'update',
        'update/theme',
    ];

    private const POST_ONLY_BLOCKED_PATHS = [
        'admin/advanced-config',
        'admin/theme',
    ];

    // Site logos, favicons, custom backgrounds and theme archives still need a
    // writable disk, so uploads on these routes remain disabled.
    private const UPLOAD_PATHS = [
        'admin/site',
        'studio/theme',
    ];

    // Profile pictures are stored in Postgres on Vercel. Only the "image"
    // field is allowed here; any other uploaded file on these forms would
    // still try to write to disk and must be rejected.
    private const PROFILE_IMAGE_PATHS = [
        'admin/edit-user/*',
        'studio/page',
    ];

    public function handle(Request $request, Closure $next)
    {
        if (!env('VERCEL')) {
            return $next($request);
        }

        $blocked = collect(self::BLOCKED_PATHS)->contains(
            fn ($pattern) => $request->is($pattern)
        );
        $postBlocked = !$request->isMethodSafe() && collect(self::POST_ONLY_BLOCKED_PATHS)->contains(
            fn ($pattern) => $request->is($pattern)
        );

        $uploadedKeys = $this->uploadedKeys($request);
        $hasFiles = count($uploadedKeys) > 0;

        $upload = $hasFiles && collect(self::UPLOAD_PATHS)->contains(
            fn ($pattern) => $request->is($pattern)
        );

        if ($hasFiles && collect(self::PROFILE_IMAGE_PATHS)->contains(
            fn ($pattern) => $request->is($pattern)
        )) {
            foreach ($uploadedKeys as $key) {
                if ($key !== 'image') {
                    $upload = true;
                }
            }
        }

        $viewDelete = $request->is('admin/edit-user/*')
            && in_array($request->server('QUERY_STRING'), ['delete', 'deleteB'], true);

        if ($blocked || $postBlocked || $upload || $viewDelete) {
            return response(
                'This operation needs durable file storage and is disabled on the Vercel deployment. '
                . 'Profile pictures, database-backed configuration, page, theme selection, and link editing remain available.',
                501
            )->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        return $next($request);
    }

    /**
     * Names of valid uploaded file fields (empty file inputs are ignored).
     */
    private function uploadedKeys(Request $request): array
    {
        $keys = [];

        foreach ($request->allFiles() as $key => $file) {
            $candidates = is_array($file) ? $file : [$file];
            foreach ($candidates as $candidate) {
                if ($candidate instanceof UploadedFile && $candidate->isValid()) {
                    $keys[] = $key;
                    break;
                }
            }
        }

        return $keys;
    }
}
