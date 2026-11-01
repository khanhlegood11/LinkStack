<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

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
        'studio/page/delprofilepicture',
        'studio/rem-background',
        'theme-updater',
        'update',
        'update/theme',
    ];

    private const POST_ONLY_BLOCKED_PATHS = [
        'admin/advanced-config',
        'admin/theme',
    ];

    private const UPLOAD_PATHS = [
        'admin/edit-user/*',
        'admin/site',
        'studio/page',
        'studio/theme',
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
        $upload = $request->files->count() > 0 && collect(self::UPLOAD_PATHS)->contains(
            fn ($pattern) => $request->is($pattern)
        );
        $viewDelete = $request->is('admin/edit-user/*')
            && in_array($request->server('QUERY_STRING'), ['delete', 'deleteB'], true);

        if ($blocked || $postBlocked || $upload || $viewDelete) {
            return response(
                'This operation needs durable file storage and is disabled on the Vercel deployment. Database-backed configuration, page, profile, theme selection, and link editing remain available.',
                501
            )->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        return $next($request);
    }
}
