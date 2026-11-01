<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

// Temporary release endpoint. It is removed after the initial deployment.
Route::post('/deploy/migrate', function (Request $request) {
    $expectedToken = env('DEPLOY_MIGRATION_TOKEN');
    $providedToken = (string) $request->bearerToken();

    abort_unless(
        is_string($expectedToken) && $expectedToken !== '' && hash_equals($expectedToken, $providedToken),
        404
    );

    try {
        Artisan::call('migrate', ['--force' => true]);
        $migrationOutput = Artisan::output();

        foreach (['ButtonSeeder', 'PageSeeder'] as $seeder) {
            $table = $seeder === 'ButtonSeeder' ? 'buttons' : 'pages';
            if (DB::table($table)->count() === 0) {
                Artisan::call('db:seed', [
                    '--class' => 'Database\\Seeders\\' . $seeder,
                    '--force' => true,
                ]);
            }
        }

        if (DB::table('users')->count() === 0) {
            $password = env('SEED_ADMIN_PASSWORD');
            throw_unless(is_string($password) && strlen($password) >= 16, new \RuntimeException('Missing admin seed password'));

            $user = User::create([
                'name' => 'admin',
                'email' => 'admin@linkstack.local',
                'email_verified_at' => now(),
                'password' => password_hash($password, PASSWORD_BCRYPT),
                'littlelink_name' => 'admin',
            ]);
            $user->forceFill([
                'littlelink_description' => 'admin page',
                'role' => 'admin',
                'block' => 'no',
            ])->save();
        }

        return response()->json([
            'status' => 'ok',
            'migrations' => $migrationOutput,
            'buttons' => DB::table('buttons')->count(),
            'pages' => DB::table('pages')->count(),
            'users' => DB::table('users')->count(),
        ]);
    } catch (\Throwable $e) {
        report($e);

        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
        ], 500);
    }
});
