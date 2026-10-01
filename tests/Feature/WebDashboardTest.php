<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WebDashboardTest extends TestCase
{
    use RefreshDatabase;

    private int $user;

    private int $event;

    private int $ticket;

    private int $foreignEvent;

    protected function setUp(): void
    {
        parent::setUp();
        $org = DB::table('organizations')->insertGetId(['name' => 'Demo', 'slug' => 'web-test']);
        $other = DB::table('organizations')->insertGetId(['name' => 'Other', 'slug' => 'other-test']);
        $this->user = DB::table('users')->insertGetId(['organization_id' => $org, 'name' => 'Web admin', 'email' => 'web@example.test', 'role' => 'SUPER_ADMIN', 'password_hash' => Hash::make('test-password-123')]);
        $this->event = DB::table('events')->insertGetId(['organization_id' => $org, 'name' => 'Concert de test', 'code' => 'WEB-TEST']);
        $this->foreignEvent = DB::table('events')->insertGetId(['organization_id' => $other, 'name' => 'Private event', 'code' => 'PRIVATE']);
        $this->ticket = DB::table('invitations')->insertGetId(['event_id' => $this->event, 'ticket_number' => 1, 'code' => 'CJA-001', 'qr_payload' => 'web-test-qr']);
    }

    private function login(): void
    {
        $this->post('/login', ['email' => 'web@example.test', 'password' => 'test-password-123'])->assertRedirect('/');
    }

    public function test_login_dashboard_and_logout(): void
    {
        $this->get('/login')->assertOk()->assertSee('Se connecter');
        $this->post('/login', ['email' => 'web@example.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->login();
        $this->get('/')->assertOk()->assertSee('Concert de test')->assertSee('CJA-001')->assertDontSee('Private event')->assertHeader('Cache-Control', 'no-store, private');
        $this->get('/agents')->assertOk();
        $this->get('/events/new')->assertOk();
        $this->post('/logout')->assertRedirect('/login');
        $this->get('/')->assertRedirect('/login');
        $this->assertDatabaseCount('auth_sessions', 0);
    }

    public function test_payment_requires_confirmation_and_is_atomic(): void
    {
        $this->login();
        $url = "/events/{$this->event}/payments";
        $this->post($url, ['ticketIds' => [$this->ticket]])->assertSessionHasErrors('confirm');
        $this->post($url, ['ticketIds' => [$this->ticket, 9999], 'confirm' => '1'])->assertStatus(422);
        $this->assertDatabaseHas('invitations', ['id' => $this->ticket, 'payment_status' => 'UNPAID']);
        $this->post($url, ['ticketIds' => [$this->ticket], 'confirm' => '1'])->assertRedirect();
        $this->post($url, ['ticketIds' => [$this->ticket], 'confirm' => '1'])->assertRedirect();
        $this->assertDatabaseCount('payment_audits', 1);
        $this->get('/?payment=UNPAID')->assertOk()->assertDontSee('CJA-001');
        $this->get('/?payment=PAID')->assertOk()->assertSee('CJA-001');
    }

    public function test_checker_is_read_only_and_scoped(): void
    {
        DB::table('users')->where('id', $this->user)->update(['role' => 'CHECKER']);
        $this->login();
        $this->get('/')->assertOk()->assertSee('Aucun événement accessible')->assertDontSee('Concert de test');
        $this->get('/?event='.$this->event)->assertForbidden();
        DB::table('event_users')->insert(['event_id' => $this->event, 'user_id' => $this->user]);
        $this->get('/')->assertOk()->assertSee('Concert de test')->assertDontSee('Marquer payé');
        $this->get('/agents')->assertForbidden();
        $this->get('/events/new')->assertForbidden();
        $this->post("/events/{$this->event}/payments", ['ticketIds' => [$this->ticket], 'confirm' => '1'])->assertForbidden();
        $this->post('/events', [])->assertForbidden();
        $this->get('/?event='.$this->foreignEvent)->assertForbidden();
        $this->post('/logout')->assertRedirect('/login');
    }

    public function test_event_create_update_and_import(): void
    {
        $this->login();
        $data = ['name' => 'New concert', 'code' => 'NEW', 'timezone' => 'Africa/Dakar', 'status' => 'DRAFT', 'expected_guests' => 1000];
        $this->post('/events', $data)->assertRedirect();
        $id = DB::table('events')->where('code', 'NEW')->value('id');
        $this->get("/events/$id/edit")->assertOk()->assertSee('New concert');
        $this->patch("/events/$id", [...$data, 'status' => 'LIVE'])->assertRedirect();
        $this->assertDatabaseHas('events', ['id' => $id, 'expected_guests' => 1000, 'status' => 'LIVE']);
        $this->patch("/events/{$this->foreignEvent}", $data)->assertForbidden();
        $file = UploadedFile::fake()->createWithContent('tickets.csv', "code;#code\nCJA-002;web-qr-two\n");
        $this->post("/events/$id/import", ['file' => $file])->assertRedirect()->assertSessionHas('import');
        $this->assertDatabaseHas('invitations', ['event_id' => $id, 'code' => 'CJA-002', 'payment_status' => 'UNPAID']);
    }

    public function test_agent_assignments_and_revoked_web_sessions(): void
    {
        $this->login();
        $this->post('/agents', ['fullName' => 'Agent web', 'email' => 'agent-web@example.test', 'password' => 'long-test-password', 'isActive' => '1', 'eventIds' => [$this->event]])->assertRedirect('/agents');
        $id = DB::table('users')->where('email', 'agent-web@example.test')->value('id');
        $this->get('/agents')->assertOk()->assertSee('Agent web');
        $this->patch('/agents', ['id' => $id, 'fullName' => 'Agent web', 'isActive' => '1', 'eventIds' => [$this->foreignEvent]])->assertForbidden();
        $this->assertDatabaseHas('event_users', ['event_id' => $this->event, 'user_id' => $id]);
        $this->patch('/agents', ['id' => $id, 'fullName' => 'Agent web', 'isActive' => '1'])->assertRedirect('/agents');
        $this->assertDatabaseMissing('event_users', ['user_id' => $id]);
        DB::table('auth_sessions')->delete();
        $this->get('/')->assertRedirect('/login');
    }

    public function test_forms_reject_requests_without_csrf(): void
    {
        $this->app['env'] = 'production';
        $this->post('/login', ['email' => 'web@example.test', 'password' => 'test-password-123'])->assertStatus(419);
    }

    public function test_event_dates_are_stored_as_utc_and_rendered_in_event_timezone(): void
    {
        $this->login();
        $this->post('/events', ['name' => 'Paris', 'code' => 'PARIS', 'timezone' => 'Europe/Paris', 'status' => 'DRAFT', 'expected_guests' => 10, 'starts_at' => '2026-10-01T18:00', 'ends_at' => '2026-10-01T20:00'])->assertRedirect();
        $this->assertDatabaseHas('events', ['code' => 'PARIS', 'starts_at' => '2026-10-01 16:00:00']);
        $id = DB::table('events')->where('code', 'PARIS')->value('id');
        $this->get("/events/$id/edit")->assertOk()->assertSee('2026-10-01T18:00');
        $this->get('/?event='.$id)->assertOk()->assertSee('01/10/2026 18:00');
    }

    public function test_manager_access_and_disabled_web_account(): void
    {
        DB::table('users')->where('id', $this->user)->update(['role' => 'MANAGER']);
        DB::table('event_users')->insert(['event_id' => $this->event, 'user_id' => $this->user]);
        $this->login();
        $this->get('/')->assertOk()->assertSee('Concert de test');
        $this->get('/agents')->assertForbidden();
        $this->post('/agents', [])->assertForbidden();
        $this->post("/events/{$this->foreignEvent}/payments", ['ticketIds' => [$this->ticket], 'confirm' => '1'])->assertForbidden();
        DB::table('users')->where('id', $this->user)->update(['is_active' => false]);
        $this->get('/')->assertRedirect('/login');
    }
}
