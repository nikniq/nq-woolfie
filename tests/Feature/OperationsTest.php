<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OperationsTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_PAGES = ['/admin/health', '/admin/monitoring', '/admin/logs', '/admin/reports', '/admin/marketing'];

    public function test_operations_pages_are_admin_only(): void
    {
        $player = User::factory()->create();
        foreach (self::ADMIN_PAGES as $page) {
            $this->actingAs($player)->get($page)->assertForbidden();
        }
    }

    public function test_operations_pages_render_for_admins(): void
    {
        $admin = User::factory()->admin()->create();
        foreach (self::ADMIN_PAGES as $page) {
            $this->actingAs($admin)->get($page)->assertOk();
        }
        $this->actingAs($admin)->get('/admin/health')->assertSee('Database');
    }

    public function test_csv_export_neutralises_formula_injection(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['name' => '=HYPERLINK("evil")']);

        $csv = $this->actingAs($admin)->get('/admin/reports/export/users')->assertOk()->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->actingAs($admin)->get('/admin/reports/export/passwords')->assertNotFound();
    }

    public function test_json_health_endpoint_requires_the_token(): void
    {
        $this->get('/health')->assertNotFound();

        config(['game.health_token' => 'tok']);
        $this->get('/health?token=wrong')->assertNotFound();
        $this->get('/health?token=tok')->assertJsonPath('checks.0.name', 'Database');
    }

    public function test_health_command_reports_checks(): void
    {
        $this->artisan('app:health')->expectsOutputToContain('Database')->run();
    }

    public function test_signup_is_attributed_to_the_campaign_that_brought_the_visitor(): void
    {
        $this->get('/?utm_source=Reddit&utm_medium=social&utm_campaign=launch')->assertOk();
        $this->post('/register', ['name' => 'Sneaky', 'email' => 's@example.com', 'password' => 'password123', 'password_confirmation' => 'password123']);

        $this->assertDatabaseHas('visits', ['source' => 'reddit', 'medium' => 'social', 'campaign' => 'launch', 'landing' => '/']);
        $this->assertDatabaseHas('users', ['email' => 's@example.com', 'signup_source' => 'reddit', 'signup_campaign' => 'launch']);
    }

    public function test_one_visit_per_session_and_bots_are_ignored(): void
    {
        $this->get('/');
        $this->get('/leaderboard');
        $this->assertSame(1, Visit::count());

        $this->flushSession();
        $this->withHeader('User-Agent', 'Googlebot/2.1')->get('/');
        $this->assertSame(1, Visit::count());
    }

    public function test_sitemap_and_robots(): void
    {
        $this->get('/sitemap.xml')->assertOk()->assertSee(route('game'), false);
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /admin')->assertSee('Sitemap:');
    }

    public function test_weekly_report_is_emailed_to_admins(): void
    {
        Mail::fake();
        User::factory()->admin()->create();

        $this->artisan('app:weekly-report')->expectsOutputToContain('Report sent to 1 admin')->assertSuccessful();
    }
}
