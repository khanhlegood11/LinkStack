<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AdminController;
use App\Http\Controllers\DbAssetController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Auth\SocialLoginController;
use App\Http\Controllers\LinkTypeViewController;
use App\Http\Controllers\PagesController;
use App\Http\Controllers\InstallerController;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// Vercel supplies installation state and configuration through environment variables.
$appInstalled = env('APP_INSTALLED', false) || file_exists(base_path('storage/app/ISINSTALLED'));

// Prevents section below from being run by 'composer update'
if(!env('VERCEL') && file_exists(base_path('storage/app/ISINSTALLED'))){
  // generates new APP KEY if no one is set
  if(EnvEditor::getKey('APP_KEY')==''){try{Artisan::call('key:generate');} catch (exception $e) {}}

  // copies template meta config if none is present
  if(!file_exists(base_path("config/advanced-config.php"))){copy(base_path('storage/templates/advanced-config.php'), base_path('config/advanced-config.php'));}
 }

Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

// Serve profile pictures that are persisted in the database on Vercel.
Route::get('/avatar/{id}', [UserController::class, 'serveAvatar'])
    ->where('id', '[0-9]+')
    ->name('serveAvatar');

// Serve site logo / favicon / custom backgrounds and uploaded theme files
// that are persisted in Postgres on read-only deployments.
Route::get('/db-asset/{key}', [DbAssetController::class, 'siteAsset'])
    ->where('key', '[A-Za-z0-9_-]+')
    ->name('dbAsset');

Route::get('/theme-file/{slug}/{path}', [DbAssetController::class, 'themeFile'])
    ->where('slug', '[A-Za-z0-9_-]+')
    ->where('path', '.*')
    ->name('themeFile');


// TEMPORARY verification route - removed before merge.
Route::get('/__verify-assets', function () {
    if (request()->query('token') !== 'ls-verify-assets-8f3b21c9') {
        abort(404);
    }

    $mode = (string) request()->query('mode', 'state');
    $out = [];
    $png = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg==',
        true
    );

    $makeFile = function ($name, $mime = 'image/png') use ($png) {
        $tmp = tempnam(sys_get_temp_dir(), 'lsprobe');
        file_put_contents($tmp, $png);
        return new \Illuminate\Http\UploadedFile($tmp, $name, $mime, null, true);
    };

    $middlewareStatus = function ($path, $method = 'POST', $fileKey = null, $fileName = 'x.png', $mime = 'image/png') use ($png) {
        $files = [];
        if ($fileKey !== null) {
            $tmp = tempnam(sys_get_temp_dir(), 'lsmw');
            file_put_contents($tmp, $png);
            $files[$fileKey] = new \Illuminate\Http\UploadedFile($tmp, $fileName, $mime, null, true);
        }
        $request = \Illuminate\Http\Request::create('/' . $path, $method, [], [], $files);
        $response = (new \App\Http\Middleware\PreventLocalFileWrites())->handle(
            $request,
            function ($r) { return response('OK', 200); }
        );
        return $response->getStatusCode();
    };

    if ($mode === 'middleware') {
        $out[] = 'admin_site_image=' . $middlewareStatus('admin/site', 'POST', 'image');
        $out[] = 'admin_site_icon=' . $middlewareStatus('admin/site', 'POST', 'icon');
        $out[] = 'admin_site_other=' . $middlewareStatus('admin/site', 'POST', 'other');
        $out[] = 'studio_background=' . $middlewareStatus('studio/background', 'POST', 'image');
        $out[] = 'studio_theme_zip=' . $middlewareStatus('studio/theme', 'POST', 'zip', 'theme.zip', 'application/zip');
        $out[] = 'studio_page_image=' . $middlewareStatus('studio/page', 'POST', 'image');
        $out[] = 'studio_page_zip=' . $middlewareStatus('studio/page', 'POST', 'zip', 'theme.zip', 'application/zip');
        $out[] = 'admin_edit_image=' . $middlewareStatus('admin/edit-user/1', 'POST', 'image');
        $out[] = 'admin_edit_background=' . $middlewareStatus('admin/edit-user/1', 'POST', 'background');
        $out[] = 'import_data=' . $middlewareStatus('import-data', 'POST', 'import', 'data.json', 'application/json');
        $out[] = 'admin_theme_post=' . $middlewareStatus('admin/theme', 'POST');
        $out[] = 'rem_background=' . $middlewareStatus('studio/rem-background', 'GET');
        $out[] = 'delavatar=' . $middlewareStatus('admin/site/delavatar', 'GET');
        $out[] = 'delfavicon=' . $middlewareStatus('admin/site/delfavicon', 'GET');
        $out[] = 'clearIcon=' . $middlewareStatus('clearIcon/1', 'GET');
        $out[] = 'update_theme=' . $middlewareStatus('update/theme', 'GET');
        $out[] = 'advanced_config=' . $middlewareStatus('admin/advanced-config', 'POST');
        return response(implode(' ', $out), 200)->header('Content-Type', 'text/plain');
    }

    if ($mode === 'upload') {
        \Illuminate\Support\Facades\Auth::loginUsingId(1);

        // 1. Site logo + favicon through the real admin controller.
        $request = \Illuminate\Http\Request::create('/admin/site', 'POST', ['message' => 'default'], [], [
            'image' => $makeFile('logo.png'),
            'icon' => $makeFile('favicon.png'),
        ]);
        app(\App\Http\Controllers\AdminController::class)->editSite($request);
        $out[] = 'site_logo=' . (dbAssetExists('site_logo') ? 'yes' : 'no');
        $out[] = 'site_favicon=' . (dbAssetExists('site_favicon') ? 'yes' : 'no');
        $out[] = 'logo_exists=' . (siteImageExists('avatar') ? 'yes' : 'no');
        $out[] = 'logo_url=' . siteImageUrl('avatar');

        // 2. Custom background through the real studio controller.
        $request = \Illuminate\Http\Request::create('/studio/background', 'POST', [], [], [
            'image' => $makeFile('bg.png'),
        ]);
        app(\App\Http\Controllers\UserController::class)->themeBackground($request);
        $out[] = 'background_1=' . (hasBackground(1) ? 'yes' : 'no');
        $out[] = 'background_url=' . backgroundUrl(1);

        // 3. Custom background through the real admin edit-user controller.
        $user = \App\Models\User::find(1);
        $params = [
            'id' => 1,
            'name' => $user->name,
            'email' => $user->email,
            'littlelink_name' => $user->littlelink_name,
            'littlelink_description' => $user->littlelink_description,
            'role' => $user->role,
            'theme' => $user->theme,
            'password' => '',
        ];
        $request = \Illuminate\Http\Request::create('/admin/edit-user/1', 'POST', $params, [], [
            'background' => $makeFile('bg2.png'),
        ]);
        app(\App\Http\Controllers\AdminController::class)->editUser($request);
        $out[] = 'admin_background=' . (dbAssetExists('background_1') ? 'yes' : 'no');

        // 4. Theme ZIP through the real studio/theme controller.
        $zipPath = tempnam(sys_get_temp_dir(), 'lszip');
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('ArenaTest-v1.0/readme.md', "Theme Name: Arena DB Theme\nTheme Version: 1.0\n");
        $zip->addFromString('ArenaTest-v1.0/config.php', "<?php return ['allow_custom_background' => 'true', 'enable_custom_code' => 'false', 'use_custom_icons' => 'false', 'custom_icon_extension' => '.svg'];");
        $zip->addFromString('ArenaTest-v1.0/share.button.css', '/* arena db theme */ body{}');
        $zip->addFromString('ArenaTest-v1.0/brands.css', '/* brands */');
        $zip->addFromString('ArenaTest-v1.0/skeleton-auto.css', '/* skeleton */');
        $zip->addFromString('ArenaTest-v1.0/animations.css', '/* animations */');
        $zip->addFromString('ArenaTest-v1.0/preview.png', $png);
        $zip->addFromString('ArenaTest-v1.0/extra/custom-head.blade.php', '<meta name="arena-db-theme" content="yes">');
        $zip->close();

        $zipUpload = new \Illuminate\Http\UploadedFile($zipPath, 'arena-theme.zip', 'application/zip', null, true);
        $request = \Illuminate\Http\Request::create('/studio/theme', 'POST', ['theme' => 'default'], [], ['zip' => $zipUpload]);
        app(\App\Http\Controllers\UserController::class)->editTheme($request);

        $slugs = dbThemeSlugs();
        $slug = $slugs[0] ?? '';
        $out[] = 'theme_slug=' . ($slug === '' ? 'none' : $slug);
        $out[] = 'theme_files=' . ($slug === '' ? 0 : \Illuminate\Support\Facades\DB::table('theme_files')->where('theme', $slug)->count());
        $out[] = 'theme_config=' . ($slug === '' ? '-' : (themeConfigValue($slug, 'allow_custom_background') ?? '-'));
        $out[] = 'theme_css_url=' . ($slug === '' ? '-' : themeFileUrl($slug, 'share.button.css'));
        $out[] = 'theme_exists=' . ($slug !== '' && dbThemeExists($slug) ? 'yes' : 'no');
        $out[] = 'available=' . implode(',', array_column(availableThemes(), 'slug'));

        // 5. Select the DB theme for the public profile test.
        if ($slug !== '') {
            \Illuminate\Support\Facades\DB::table('users')->where('id', 1)->update(['theme' => $slug]);
            $out[] = 'theme_selected=' . $slug;
        }

        @unlink($zipPath);
        return response(implode(' ', $out), 200)->header('Content-Type', 'text/plain');
    }

    if ($mode === 'render') {
        \Illuminate\Support\Facades\Auth::loginUsingId(1);
        $views = [
            'login' => ['auth.login', []],
            'site_panel' => ['panel.site', ['home_message' => 'default']],
            'studio_theme' => ['studio.theme', ['pages' => \App\Models\User::where('id', 1)->select('littlelink_name', 'theme')->get()]],
            'edit_user' => ['panel.edit-user', ['user' => \App\Models\User::find(1)]],
            'delete_theme' => ['panel.theme', []],
        ];
        foreach ($views as $label => [$view, $data]) {
            try {
                $html = view($view, $data)->render();
                $out[] = $label . '=ok bytes=' . strlen($html)
                    . ' logo=' . (strpos($html, '/db-asset/site_logo') !== false ? 'yes' : 'no')
                    . ' bg=' . (strpos($html, '/db-asset/background_1') !== false ? 'yes' : 'no')
                    . ' themefile=' . (strpos($html, '/theme-file/') !== false ? 'yes' : 'no')
                    . ' delbg=' . (strpos($html, 'delbackground') !== false ? 'yes' : 'no');
            } catch (\Throwable $e) {
                $out[] = $label . '=ERR ' . substr(str_replace(["\n", "\r"], ' ', $e->getMessage()), 0, 120);
            }
        }

        // Render the public profile the same way UserController::littlelink() does.
        try {
            $request = \Illuminate\Http\Request::create('/@admin', 'GET', ['littlelink' => 'admin']);
            $html = app(\App\Http\Controllers\UserController::class)->littlelink($request)->render();
            $out[] = 'public=ok bytes=' . strlen($html)
                . ' themefile=' . (strpos($html, '/theme-file/') !== false ? 'yes' : 'no')
                . ' bg=' . (strpos($html, '/db-asset/background_1') !== false ? 'yes' : 'no')
                . ' logo=' . (strpos($html, '/db-asset/site_logo') !== false ? 'yes' : 'no');
        } catch (\Throwable $e) {
            $out[] = 'public=ERR ' . substr(str_replace(["\n", "\r"], ' ', $e->getMessage()), 0, 160);
        }

        return response(implode(' ', $out), 200)->header('Content-Type', 'text/plain');
    }

    if ($mode === 'delete') {
        \Illuminate\Support\Facades\Auth::loginUsingId(1);
        deleteDbAsset('site_logo');
        deleteDbAsset('site_favicon');
        deleteDbAsset('background_1');
        foreach (dbThemeSlugs() as $slug) {
            deleteDbTheme($slug);
        }
        \Illuminate\Support\Facades\DB::table('users')->whereIn('id', [1, 786090])->update(['theme' => null]);
        $out[] = 'assets=' . \Illuminate\Support\Facades\DB::table('site_assets')->count();
        $out[] = 'theme_files=' . \Illuminate\Support\Facades\DB::table('theme_files')->count();
        $out[] = 'logo_exists=' . (siteImageExists('avatar') ? 'yes' : 'no');
        return response(implode(' ', $out), 200)->header('Content-Type', 'text/plain');
    }

    // Default: report current state.
    $out[] = 'vercel=' . (env('VERCEL') ? 'yes' : 'no');
    $out[] = 'assets=' . \Illuminate\Support\Facades\DB::table('site_assets')->count();
    $out[] = 'theme_files=' . \Illuminate\Support\Facades\DB::table('theme_files')->count();
    $out[] = 'logo_exists=' . (siteImageExists('avatar') ? 'yes' : 'no');
    $out[] = 'favicon_exists=' . (siteImageExists('favicon') ? 'yes' : 'no');
    $out[] = 'bg_exists=' . (hasBackground(1) ? 'yes' : 'no');
    $out[] = 'db_themes=' . implode(',', dbThemeSlugs());
    return response(implode(' ', $out), 200)->header('Content-Type', 'text/plain');
});

 // Installer
if(!$appInstalled && (file_exists(base_path('INSTALLING')) or file_exists(base_path('INSTALLERLOCK')))){

  Route::get('/', [InstallerController::class, 'showInstaller'])->name('showInstaller');
  Route::post('/create-admin', [InstallerController::class, 'createAdmin'])->name('createAdmin');
  Route::post('/db', [InstallerController::class, 'db'])->name('db');
  Route::post('/mysql', [InstallerController::class, 'mysql'])->name('mysql');
  Route::post('/options', [InstallerController::class, 'options'])->name('options');
  Route::get('/mysql-test', [InstallerController::class, 'mysqlTest'])->name('mysqlTest');
  Route::get('/skip', function () {Artisan::call('db:seed', ['--class' => 'AdminSeeder',]); Auth::login(User::where('name', 'admin')->first()); return redirect(url('dashboard'));});
  Route::post('/editConfigInstaller', [InstallerController::class, 'editConfigInstaller'])->name('editConfigInstaller');

  Route::get('{any}', function() {
    if(!DB::table('users')->get()->isEmpty()){
    if(file_exists(base_path("INSTALLING")) and !file_exists(base_path('INSTALLERLOCK'))){unlink(base_path("INSTALLING"));header("Refresh:0");}
    } else {
      return redirect(url(''));
    }
  })->where('any', '.*');

}else{

// Disables routes if in Maintenance Mode
if(env('MAINTENANCE_MODE') != 'true'){

require __DIR__.'/home.php';

//Redirect if no page URL is set
Route::get('/@', function () {
    return redirect('/studio/no_page_name');
});

//Show diagnose page
Route::get('/panel/diagnose', function () {
        return view('panel.diagnose', []);
});

//Public route
$custom_prefix = config('advanced-config.custom_url_prefix');
Route::get('/going/{id?}', [UserController::class, 'clickNumber'])->where('link', '.*')->name('clickNumber')->middleware('disableCookies');
Route::get('/info/{id?}', [AdminController::class, 'redirectInfo'])->name('redirectInfo');
if($custom_prefix != ""){Route::get('/' . $custom_prefix . '{littlelink}', [UserController::class, 'littlelink'])->name('littlelink');}
Route::get('/@{littlelink}', [UserController::class, 'littlelink'])->name('littlelink')->middleware('disableCookies');
Route::get('/pages/'.strtolower(footer('Terms')), [AdminController::class, 'pagesTerms'])->name('pagesTerms')->middleware('disableCookies');
Route::get('/pages/'.strtolower(footer('Privacy')), [AdminController::class, 'pagesPrivacy'])->name('pagesPrivacy')->middleware('disableCookies');
Route::get('/pages/'.strtolower(footer('Contact')), [AdminController::class, 'pagesContact'])->name('pagesContact')->middleware('disableCookies');
Route::get('/theme/@{littlelink}', [UserController::class, 'theme'])->name('theme');
Route::get('/vcard/{id?}', [UserController::class, 'vcard'])->name('vcard');
Route::get('/u/{id?}', [UserController::class, 'userRedirect'])->name('userRedirect');

Route::get('/report', function () {return view('report');});
Route::post('/report', [UserController::class, 'report'])->name('report');

Route::get('/demo-page', [App\Http\Controllers\HomeController::class, 'demo'])->name('demo')->middleware('disableCookies');

Route::get('/block-asset/{type}', [LinkTypeViewController::class, 'blockAsset'])
  ->name('block.asset')->where(['type' => '[a-zA-Z0-9_-]+']);

}

Route::middleware(['auth', 'blocked', 'impersonate'])->group(function () {
//User route
Route::group([
    'middleware' => env('REGISTER_AUTH'),
], function () {
if(env('FORCE_ROUTE_HTTPS') == 'true'){URL::forceScheme('https');}
Route::get('/dashboard', [AdminController::class, 'index'])->name('panelIndex');
Route::get('/studio/index', function(){return redirect(url('dashboard'));});
Route::get('/studio/add-link', [UserController::class, 'AddUpdateLink'])->name('showButtons');
Route::post('/studio/edit-link', [UserController::class, 'saveLink'])->name('addLink');
Route::get('/studio/edit-link/{id}', [UserController::class, 'AddUpdateLink'])->name('showLink')->middleware('link-id');
Route::post('/studio/sort-link', [UserController::class, 'sortLinks'])->name('sortLinks');
Route::get('/studio/links', [UserController::class, 'showLinks'])->name('showLinks');
Route::get('/studio/theme', [UserController::class, 'showTheme'])->name('showTheme');
Route::post('/studio/theme', [UserController::class, 'editTheme'])->name('editTheme');
Route::get('/deleteLink/{id}', [UserController::class, 'deleteLink'])->name('deleteLink')->middleware('link-id');
Route::get('/upLink/{up}/{id}', [UserController::class, 'upLink'])->name('upLink')->middleware('link-id');
Route::post('/studio/edit-link/{id}', [UserController::class, 'editLink'])->name('editLink')->middleware('link-id');
Route::get('/studio/button-editor/{id}', [UserController::class, 'showCSS'])->name('showCSS')->middleware('link-id');
Route::post('/studio/button-editor/{id}', [UserController::class, 'editCSS'])->name('editCSS')->middleware('link-id');
Route::get('/studio/page', [UserController::class, 'showPage'])->name('showPage');
Route::get('/studio/no_page_name', [UserController::class, 'showPage'])->name('showPage');
Route::post('/studio/page', [UserController::class, 'editPage'])->name('editPage');
Route::post('/studio/background', [UserController::class, 'themeBackground'])->name('themeBackground');
Route::get('/studio/rem-background', [UserController::class, 'removeBackground'])->name('removeBackground');
Route::get('/studio/profile', [UserController::class, 'showProfile'])->name('showProfile');
Route::post('/studio/profile', [UserController::class, 'editProfile'])->name('editProfile');
Route::post('/edit-icons', [UserController::class, 'editIcons'])->name('editIcons');
Route::get('/clearIcon/{id}', [UserController::class, 'clearIcon'])->name('clearIcon')->middleware('link-id');
Route::get('/studio/page/delprofilepicture', [UserController::class, 'delProfilePicture'])->name('delProfilePicture');
Route::get('/studio/delete-user/{id}', [UserController::class, 'deleteUser'])->name('deleteUser')->middleware('verified');
Route::post('/auth-as', [AdminController::class, 'authAs'])->name('authAs');

// Catch all redirects
Route::get('/admin/users/all', fn() => redirect(route('showUsers')));
Route::get('/studio', fn() => redirect(url('dashboard')));
Route::get('/studio/edit-link', fn() => redirect(url('dashboard')));

if(env('ALLOW_USER_EXPORT') != false){
  Route::get('/export-links', [UserController::class, 'exportLinks'])->name('exportLinks');
  Route::get('/export-all', [UserController::class, 'exportAll'])->name('exportAll');
}
if(env('ALLOW_USER_IMPORT') != false){
  Route::post('/import-data', [UserController::class, 'importData'])->name('importData');
}
Route::get('/studio/linkparamform_part/{typeid}/{linkid}', [LinkTypeViewController::class, 'getParamForm'])->name('linkparamform.part');
});
});
}

//Social login route
Route::get('/social-auth/{provider}/callback', [SocialLoginController::class, 'providerCallback']);
Route::get('/social-auth/{provider}', [SocialLoginController::class, 'redirectToProvider'])->name('social.redirect');

Route::middleware(['auth', 'blocked', 'impersonate'])->group(function () {
//Admin route
Route::group([
    'middleware' => 'admin',
], function () {
    if(env('FORCE_ROUTE_HTTPS') == 'true'){URL::forceScheme('https');}
    Route::get('/panel/index', function(){return redirect(url('dashboard'));});
    Route::get('/admin/users', [AdminController::class, 'users'])->name('showUsers');
    Route::get('/admin/links/{id}', [AdminController::class, 'showLinksUser'])->name('showLinksUser');
    Route::get('/admin/deleteLink/{id}', [AdminController::class, 'deleteLinkUser'])->name('deleteLinkUser');
    Route::get('/admin/users/block/{block}/{id}', [AdminController::class, 'blockUser'])->name('blockUser');
    Route::get('/admin/users/verify/{verify}/{id}', [AdminController::class, 'verifyCheckUser'])->name('verifyCheckUser');
    Route::get('/admin/users/verify-mail/{verify}/{id}', [AdminController::class, 'verifyUser'])->name('verifyUser');
    Route::get('/admin/edit-user/{id}', [AdminController::class, 'showUser'])->name('showUser');
    Route::post('/admin/edit-user/{id}', [AdminController::class, 'editUser'])->name('editUser');
    Route::get('/admin/edit-user/{id}/delavatar', [AdminController::class, 'delUserAvatar'])->name('delUserAvatar');
    Route::get('/admin/edit-user/{id}/delbackground', [AdminController::class, 'delUserBackground'])->name('delUserBackground');
    Route::get('/admin/new-user', [AdminController::class, 'createNewUser'])->name('createNewUser')->middleware('max.users');
    Route::get('/admin/delete-user/{id}', [AdminController::class, 'deleteUser'])->name('deleteUser');
    Route::post('/admin/delete-table-user/{id}', [AdminController::class, 'deleteTableUser'])->name('deleteTableUser');
    Route::get('/admin/pages', [AdminController::class, 'showSitePage'])->name('showSitePage');
    Route::post('/admin/pages', [AdminController::class, 'editSitePage'])->name('editSitePage');
    Route::get('/admin/advanced-config', [AdminController::class, 'showFileEditor'])->name('showFileEditor');
    Route::post('/admin/advanced-config', [AdminController::class, 'editAC'])->name('editAC');
    Route::get('/admin/env', [AdminController::class, 'showFileEditor'])->name('showFileEditor');
    Route::post('/admin/env', [AdminController::class, 'editENV'])->name('editENV');
    Route::get('/admin/site', [AdminController::class, 'showSite'])->name('showSite');
    Route::post('/admin/site', [AdminController::class, 'editSite'])->name('editSite');
    Route::get('/admin/site/delavatar', [AdminController::class, 'delAvatar'])->name('delAvatar');
    Route::get('/admin/site/delfavicon', [AdminController::class, 'delFavicon'])->name('delFavicon');
    Route::get('/admin/phpinfo', [AdminController::class, 'phpinfo'])->name('phpinfo');
    Route::get('/admin/backups', [AdminController::class, 'showBackups'])->name('showBackups');
    Route::post('/admin/theme', [AdminController::class, 'deleteTheme'])->name('deleteTheme');
    Route::get('/admin/theme', [AdminController::class, 'showThemes'])->name('showThemes');
    Route::get('/update/theme', [AdminController::class, 'updateThemes'])->name('updateThemes');
    Route::get('/admin/config', [AdminController::class, 'showConfig'])->name('showConfig');
    Route::post('/admin/config', [AdminController::class, 'editConfig'])->name('editConfig');
    Route::get('/send-test-email', [AdminController::class, 'SendTestMail'])->name('SendTestMail');
    Route::get('/auth-as/{id}', [AdminController::class, 'authAsID'])->name('authAsID');
    Route::get('/theme-updater', function () {return view('studio/theme-updater', []);});
    Route::get('/update', function () {return view('update', []);});
    Route::get('/backup', function () {return view('backup', []);});

    Route::group(['namespace'=>'App\Http\Controllers\Admin', 'prefix'=>'admin', 'as'=>'admin'],function() {
        //Route::resource('/admin/linktype', LinkTypeController::class);
        Route::resources([
            'linktype'=>LinkTypeController::class
        ]);
    });

}); // End Admin authenticated routes
});

// Displays Maintenance Mode page
if(env('MAINTENANCE_MODE') == 'true'){
Route::get('/{any}', function () {
  return view('maintenance');
  })->where('any', '.*');
}

require __DIR__.'/auth.php';

if(config('advanced-config.custom_url_prefix') == ""){
  Route::get('/{littlelink}', [UserController::class, 'littlelink'])->name('littlelink');
}