<?php

namespace Tests\Feature;

use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_inquiry_view_writes_audit_row(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $inquiry = Inquiry::create([
            'name' => 'Maria Clara',
            'email' => 'maria@example.com',
            'phone' => '+639171234567',
            'message' => 'Garden wedding quote.',
        ]);

        $this->actingAs($admin)->get(route('admin.inquiries.show', $inquiry))->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'inquiry.viewed',
            'auditable_type' => Inquiry::class,
            'auditable_id' => $inquiry->id,
        ]);
    }

    public function test_dsar_export_writes_audit_row(): void
    {
        $user = User::factory()->create(['email' => 'me@example.com']);

        $this->actingAs($user, 'sanctum')->getJson('/api/user/export')->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'dsar.exported',
            'auditable_type' => $user::class,
            'auditable_id' => $user->id,
        ]);
    }

    public function test_dsar_erase_writes_audit_row(): void
    {
        $user = User::factory()->create(['email' => 'gone@example.com']);

        $this->actingAs($user, 'sanctum')->deleteJson('/api/user')->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'dsar.erased',
            'auditable_id' => $user->id,
        ]);
    }
}
