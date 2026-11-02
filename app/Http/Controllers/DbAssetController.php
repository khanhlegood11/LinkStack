<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Serves uploads that live in Postgres instead of on the container filesystem.
 *
 * These routes are public: site logo, favicon, custom backgrounds and theme
 * assets are all meant to be visible on public pages.
 */
class DbAssetController extends Controller
{
    /**
     * GET /db-asset/{key}
     */
    public function siteAsset(Request $request, $key)
    {
        if (!preg_match('/^[A-Za-z0-9_-]+$/', (string) $key)) {
            abort(404);
        }

        $row = dbAssetData($key);
        $binary = $row !== null ? base64_decode((string) $row->data, true) : false;

        // Temporary text probe (removed after verification); fetch_page cannot
        // parse binary responses.
        if ($request->query('__probe') === '1') {
            return response(
                'OK key=' . $key
                . ' row=' . ($row !== null ? 'yes' : 'no')
                . ' mime=' . ($row->mime ?? '-')
                . ' decoded=' . ($binary !== false ? 'yes' : 'no')
                . ' bytes=' . ($binary !== false ? strlen($binary) : 0),
                200
            )->header('Content-Type', 'text/plain');
        }

        if ($binary === false || $binary === '') {
            abort(404);
        }

        return response($binary, 200)
            ->header('Content-Type', (string) $row->mime)
            ->header('Content-Length', (string) strlen($binary))
            ->header('Cache-Control', 'public, max-age=60');
    }

    /**
     * GET /theme-file/{slug}/{path}
     */
    public function themeFile(Request $request, $slug, $path)
    {
        $slug = safeThemeSlug($slug);
        $path = safeThemePath($path);

        if ($slug === '' || $path === '' || preg_match('/\.php$/i', $path)) {
            abort(404);
        }

        $row = dbThemeFileData($slug, $path);
        $binary = $row !== null ? base64_decode((string) $row->data, true) : false;

        // Temporary text probe (removed after verification).
        if ($request->query('__probe') === '1') {
            return response(
                'OK theme=' . $slug . ' path=' . $path
                . ' row=' . ($row !== null ? 'yes' : 'no')
                . ' mime=' . ($row->mime ?? '-')
                . ' decoded=' . ($binary !== false ? 'yes' : 'no')
                . ' bytes=' . ($binary !== false ? strlen($binary) : 0),
                200
            )->header('Content-Type', 'text/plain');
        }

        if ($binary === false || $binary === '') {
            abort(404);
        }

        return response($binary, 200)
            ->header('Content-Type', (string) $row->mime)
            ->header('Content-Length', (string) strlen($binary))
            ->header('Cache-Control', 'public, max-age=300');
    }
}
