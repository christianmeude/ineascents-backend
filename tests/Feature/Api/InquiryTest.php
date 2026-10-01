<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InquiryTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Maria Clara',
            'email' => 'maria@example.com',
            'phone' => '+639171234567',
            'event_date' => '2026-12-15',
            'message' => 'Garden wedding for 100 pax, need a quote.',
            'consent_privacy_version' => \App\Http\Controllers\LegalController::VERSION,
        ], $overrides);
    }

    public function test_valid_submit_persists_one_row(): void
    {
        $response = $this->postJson('/api/inquiries', $this->payload());

        $response->assertCreated();
        $this->assertDatabaseCount('inquiries', 1);
        $this->assertDatabaseHas('inquiries', [
            'email' => 'maria@example.com',
            'status' => 'new',
        ]);
    }

    public function test_submit_without_event_date_persists(): void
    {
        $response = $this->postJson('/api/inquiries', $this->payload(['event_date' => null]));

        $response->assertCreated();
        $this->assertDatabaseHas('inquiries', ['email' => 'maria@example.com']);
    }

    public function test_submit_with_omitted_event_date_key_persists(): void
    {
        $data = $this->payload();
        unset($data['event_date']);

        $this->postJson('/api/inquiries', $data)->assertCreated();
        $this->assertDatabaseHas('inquiries', ['email' => 'maria@example.com']);
    }

    public function test_submit_without_message_persists_as_null(): void
    {
        $data = $this->payload();
        unset($data['message']);

        $this->postJson('/api/inquiries', $data)->assertCreated();
        $this->assertDatabaseHas('inquiries', ['email' => 'maria@example.com', 'message' => null]);
    }

    public function test_submit_with_blank_message_persists_as_null(): void
    {
        $this->postJson('/api/inquiries', $this->payload(['message' => '']))->assertCreated();
        $this->assertDatabaseHas('inquiries', ['email' => 'maria@example.com', 'message' => null]);
    }

    public function test_email_is_not_unique(): void
    {
        $this->postJson('/api/inquiries', $this->payload())->assertCreated();
        $this->postJson('/api/inquiries', $this->payload(['name' => 'Jose Rizal']))->assertCreated();

        $this->assertDatabaseCount('inquiries', 2);
    }

    public function test_past_event_date_rejected(): void
    {
        $response = $this->postJson('/api/inquiries', $this->payload(['event_date' => '2020-01-01']));

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('event_date');
        $this->assertDatabaseCount('inquiries', 0);
    }

    public function test_honeypot_filled_is_silently_dropped(): void
    {
        $response = $this->postJson('/api/inquiries', $this->payload(['website' => 'https://spam.test']));

        $response->assertOk();
        $this->assertDatabaseCount('inquiries', 0);
    }

    public function test_missing_name_is_rejected(): void
    {
        $response = $this->postJson('/api/inquiries', $this->payload(['name' => null]));

        $response->assertUnprocessable();
        $this->assertDatabaseCount('inquiries', 0);
    }

    public function test_missing_consent_is_rejected(): void
    {
        $payload = $this->payload();
        unset($payload['consent_privacy_version']);

        $this->postJson('/api/inquiries', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['consent_privacy_version']);
        $this->assertDatabaseCount('inquiries', 0);
    }

    public function test_stale_consent_version_is_rejected(): void
    {
        $this->postJson('/api/inquiries', $this->payload(['consent_privacy_version' => '2000-01-01']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['consent_privacy_version']);
        $this->assertDatabaseCount('inquiries', 0);
    }

    public function test_consent_version_and_timestamp_persist(): void
    {
        $this->postJson('/api/inquiries', $this->payload())->assertCreated();

        $this->assertDatabaseHas('inquiries', [
            'email' => 'maria@example.com',
            'consent_privacy_version' => \App\Http\Controllers\LegalController::VERSION,
        ]);
        $this->assertNotNull(\App\Models\Inquiry::first()->consented_at);
    }

    public function test_throttle_blocks_eleventh_request(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/inquiries', $this->payload(['email' => "user{$i}@example.com"]))->assertCreated();
        }

        $this->postJson('/api/inquiries', $this->payload(['email' => 'blocked@example.com']))->assertStatus(429);
    }
}
