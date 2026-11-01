<?php

namespace Tests\Feature;

use App\Models\Link;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class LinksPageTest extends TestCase
{
    use RefreshDatabase;

    protected function refreshInMemoryDatabase(): void
    {
        Artisan::call('migrate', ['--force' => true]);
    }

    private function createUser(): User
    {
        return User::forceCreate([
            'name' => 'Link Owner',
            'email' => 'owner@example.com',
            'littlelink_name' => 'link-owner',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'role' => 'user',
            'block' => 'no',
        ]);
    }

    public function test_links_page_renders_with_no_links(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->get('/studio/links');

        $response->assertOk();
        $response->assertSee('You haven’t added any links yet.');
        $response->assertSee('Page Icons');
        $response->assertSee('Mastodon');
        $response->assertDontSee('Server Error');
    }

    public function test_links_page_renders_with_links_and_page_icons(): void
    {
        $user = $this->createUser();
        DB::table('buttons')->insert(['id' => 14, 'name' => 'github', 'alt' => 'GitHub']);
        DB::table('buttons')->insert(['id' => 94, 'name' => 'icon', 'alt' => 'Icon']);
        $buttonId = 14;
        $iconButtonId = 94;

        Link::forceCreate([
            'link' => 'https://github.com/example',
            'title' => 'GitHub',
            'user_id' => $user->id,
            'button_id' => $buttonId,
            'order' => 0,
            'up_link' => 'no',
        ]);

        Link::forceCreate([
            'link' => 'https://mastodon.example/@user',
            'title' => 'mastodon',
            'user_id' => $user->id,
            'button_id' => $iconButtonId,
            'order' => 1,
            'up_link' => 'no',
            'click_number' => 7,
        ]);

        $response = $this->actingAs($user)->get('/studio/links');

        $response->assertOk();
        $response->assertSee('https://github.com/example');
        $response->assertSee('Page Icons');
        $response->assertSee('Clicks: 7');
        $response->assertDontSee('Server Error');
    }

    public function test_admin_links_page_renders_with_no_links(): void
    {
        $admin = User::forceCreate([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'littlelink_name' => 'admin',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'role' => 'admin',
            'block' => 'no',
        ]);
        $user = $this->createUser();

        $response = $this->actingAs($admin)->get('/admin/links/'.$user->id);

        $response->assertOk();
        $response->assertSee('Links');
        $response->assertDontSee('Server Error');
    }

    public function test_favicon_asset_exists_for_the_web_server(): void
    {
        $path = base_path('public/favicon.ico');

        $this->assertFileExists($path);
        $this->assertSame("\x00\x00\x01\x00", substr(file_get_contents($path), 0, 4));
    }
}
