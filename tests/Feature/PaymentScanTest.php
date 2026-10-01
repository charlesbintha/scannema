<?php
namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PaymentScanTest extends TestCase
{
    use RefreshDatabase;
    private int $event;
    private int $ticket;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $org = DB::table('organizations')->insertGetId(['name' => 'Test', 'slug' => 'test']);
        $user = DB::table('users')->insertGetId(['organization_id' => $org, 'name' => 'Test admin', 'email' => 'test@example.test', 'role' => 'SUPER_ADMIN', 'password_hash' => Hash::make('test-secret')]);
        $this->event = DB::table('events')->insertGetId(['organization_id' => $org, 'name' => 'Test concert', 'code' => 'TEST', 'status' => 'LIVE']);
        $this->ticket = DB::table('invitations')->insertGetId(['event_id' => $this->event, 'ticket_number' => 1, 'code' => 'CJA-001', 'qr_payload' => 'test-opaque-qr']);
        $login = $this->postJson('/api/mobile/auth/login', ['email' => 'test@example.test', 'password' => 'test-secret'])->assertOk();
        $this->token = $login->json('session.accessToken');
        $this->withToken($this->token);
    }

    public function test_unpaid_payment_scan_and_duplicate_cycle(): void
    {
        $scan = "/api/events/{$this->event}/scan/verify";
        $this->postJson($scan, ['qrPayload' => 'test-opaque-qr'])->assertForbidden()->assertJsonPath('result', 'UNPAID');
        $this->assertDatabaseHas('invitations', ['id' => $this->ticket, 'status' => 'NOT_SCANNED', 'scanned_at' => null]);
        $this->patchJson("/api/events/{$this->event}/tickets", ['ticketIds' => [$this->ticket]])->assertOk()->assertJsonPath('updated', 1);
        $this->patchJson("/api/events/{$this->event}/tickets", ['ticketIds' => [$this->ticket]])->assertOk()->assertJsonPath('updated', 0);
        $this->assertDatabaseCount('payment_audits', 1);
        $this->postJson($scan, ['qrPayload' => 'test-opaque-qr'])->assertOk()->assertJsonPath('result', 'VALID')->assertJsonPath('ticket.paymentStatus', 'PAID');
        $this->postJson($scan, ['qrPayload' => 'test-opaque-qr'])->assertOk()->assertJsonPath('result', 'ALREADY_SCANNED');
        $this->assertDatabaseCount('scan_logs', 3);
    }

    public function test_agent_management_scopes_and_revokes_sessions(): void
    {
        $created = $this->postJson('/api/agents', ['fullName' => 'Test checker', 'email' => 'agent@example.test', 'password' => 'long-test-password', 'isActive' => true, 'eventIds' => [$this->event], 'role' => 'SUPER_ADMIN'])->assertCreated();
        $id = $created->json('agentId');
        $this->assertDatabaseHas('users', ['id' => $id, 'role' => 'CHECKER']);
        $login = $this->postJson('/api/mobile/auth/login', ['email' => 'agent@example.test', 'password' => 'long-test-password'])->assertOk()->assertJsonCount(1, 'session.linkedEvents');
        $agentToken = $login->json('session.accessToken');
        $this->withToken($agentToken)->getJson('/api/agents')->assertForbidden();
        $this->withToken($this->token)->patchJson('/api/agents', ['id' => $id, 'fullName' => 'Test checker', 'isActive' => true, 'eventIds' => [999999]])->assertForbidden();
        $this->assertDatabaseHas('event_users', ['user_id' => $id, 'event_id' => $this->event]);
        $this->patchJson('/api/agents', ['id' => $id, 'fullName' => 'Test checker', 'isActive' => true, 'eventIds' => []])->assertOk();
        $this->withToken($agentToken)->getJson('/api/events')->assertUnauthorized();
        $this->postJson('/api/mobile/auth/login', ['email' => 'agent@example.test', 'password' => 'long-test-password'])->assertOk()->assertJsonCount(0, 'session.linkedEvents');
    }

    public function test_checker_can_revoke_own_session(): void
    {
        DB::table('users')->update(['role' => 'CHECKER']);
        $this->postJson('/api/mobile/auth/logout')->assertOk();
        $this->getJson('/api/events')->assertUnauthorized();
    }

    public function test_unknown_ticket_is_logged_without_consumption(): void
    {
        $this->postJson("/api/events/{$this->event}/scan/verify", ['qrPayload' => 'does-not-exist'])->assertNotFound()->assertJsonPath('result', 'INVALID');
        $this->assertDatabaseHas('scan_logs', ['result' => 'INVALID', 'invitation_id' => null]);
        $this->assertDatabaseHas('invitations', ['id' => $this->ticket, 'status' => 'NOT_SCANNED']);
    }

    public function test_forged_session_and_disabled_account_are_rejected(): void
    {
        $this->withToken('scn-123-1')->postJson("/api/events/{$this->event}/scan/verify", ['qrPayload' => 'test-opaque-qr'])->assertUnauthorized();
        DB::table('users')->update(['is_active' => false]);
        $this->withToken($this->token)->getJson('/api/events')->assertUnauthorized();
    }

    public function test_checker_requires_assignment_and_cannot_mark_paid(): void
    {
        DB::table('users')->update(['role' => 'CHECKER']);
        $this->getJson('/api/events')->assertOk()->assertJsonCount(0, 'events');
        $this->postJson("/api/events/{$this->event}/scan/verify", ['qrPayload' => 'test-opaque-qr'])->assertForbidden();
        DB::table('event_users')->insert(['event_id' => $this->event, 'user_id' => DB::table('users')->value('id')]);
        $this->getJson('/api/events')->assertOk()->assertJsonCount(1, 'events');
        $this->patchJson("/api/events/{$this->event}/tickets", ['ticketIds' => [$this->ticket]])->assertForbidden();
        $this->postJson("/api/events/{$this->event}/scan/verify", ['qrPayload' => 'test-opaque-qr'])->assertJsonPath('result', 'UNPAID');
    }

    public function test_batch_is_atomic_and_scanned_or_blocked_stays_refused(): void
    {
        $this->patchJson("/api/events/{$this->event}/tickets", ['ticketIds' => [$this->ticket, 999999]])->assertUnprocessable();
        $this->assertDatabaseHas('invitations', ['id' => $this->ticket, 'payment_status' => 'UNPAID']);
        DB::table('invitations')->where('id', $this->ticket)->update(['status' => 'BLOCKED', 'payment_status' => 'PAID']);
        $this->postJson("/api/events/{$this->event}/scan/verify", ['qrPayload' => 'test-opaque-qr'])->assertForbidden()->assertJsonPath('result', 'BLOCKED');
    }

    public function test_csv_aliases_and_reimport_preserve_payment(): void
    {
        $file = fn () => UploadedFile::fake()->createWithContent('tickets.csv', "code;#code\nCJA-002;opaque-token-two\n");
        $this->post("/api/events/{$this->event}/invitations/import", ['file' => $file()], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('summary.created', 1);
        $this->assertDatabaseHas('invitations', ['code' => 'CJA-002', 'ticket_number' => 2, 'qr_payload' => 'opaque-token-two', 'payment_status' => 'UNPAID']);
        DB::table('invitations')->where('ticket_number', 2)->update(['payment_status' => 'PAID']);
        $this->post("/api/events/{$this->event}/invitations/import", ['file' => $file()], ['Accept' => 'application/json'])->assertOk();
        $this->assertDatabaseHas('invitations', ['code' => 'CJA-002', 'payment_status' => 'PAID']);
    }
}
