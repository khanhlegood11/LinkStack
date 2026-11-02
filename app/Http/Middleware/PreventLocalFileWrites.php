<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class PreventLocalFileWrites
{
    /**
     * Routes whose whole purpose is to write to the server filesystem.
     * They cannot work in a read-only container and stay disabled.
     */
    private const BLOCKED_PATHS = [
        'backup',
        'clearIcon/*',
        'env-editor',
        'env-editor/*',
        'theme-updater',
        'update',
        'update/theme',
    ];

    private const POST_ONLY_BLOCKED_PATHS = [
        'admin/advanced-config',
    ];

    /**
     * Upload fields that are handled by the database-backed stores on Vercel.
     * Any other file upload still ends up in move()/unlink() against the
     * read-only container filesystem and must be rejected.
     *
     *  - admin/site, studio/background: site logo, favicon and custom background
     *  - studio/theme:                 theme ZIP unpacked into theme_files
     *  - studio/page, admin/edit-user: profile picture
     *  - import-data:                  JSON import (avatar stored in Postgres)
     */
    private const ALLOWED_UPLOADS = [
        'admin/site' => ['image', 'icon'],
        'studio/background' => ['image'],
        'studio/theme' => ['zip'],
        'studio/page' => ['image'],
        'admin/edit-user/*' => ['image', 'background'],
        'import-data' => ['import'],
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
        $upload = false;

        if ($uploadedKeys !== []) {
            $allowedKeys = null;
            foreach (self::ALLOWED_UPLOADS as $pattern => $keys) {
                if ($request->is($pattern)) {
                    $allowedKeys = $keys;
                    break;
                }
            }

            if ($allowedKeys === null) {
                $upload = true;
            } else {
                foreach ($uploadedKeys as $key) {
                    if (!in_array($key, $allowedKeys, true)) {
                        $upload = true;
                        break;
                    }
                }
            }
        }

        if ($blocked || $postBlocked || $upload) {
            return response(
                'This operation needs a writable server filesystem and is disabled on the Vercel deployment. '
                . 'Profile pictures, site logo and favicon, custom backgrounds, theme ZIP uploads, '
                . 'database-backed configuration, page, theme selection, and link editing remain available.',
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
