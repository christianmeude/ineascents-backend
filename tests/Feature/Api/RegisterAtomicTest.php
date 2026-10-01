<?php

namespace Tests\Feature\Api;

use App\Notifications\VerificationCode as VerificationCodeMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegisterAtomicTest extends TestCase
{
    use RefreshDatabase;

    public function test_mailer_failure_rolls_back_user_and_code()
    {
        // Notification::route() is a Facade static (not on the ChannelManager
        // mock), so fail at the Dispatcher the on-demand notify() resolves.
        $this->mock(\Illuminate\Contracts\Notifications\Dispatcher::class, function ($mock) {
            $mock->shouldReceive('send')->once()->andThrow(new \Exception('mailer down'));
        });

        $response = $this->postJson('/api/register', [
            'name' => 'Atomic User',
            'email' => 'atomic-fail@example.com',
            'password' => 'C0ncierge-Str0ng-77',
        ]);

        $response->assertStatus(500);

        $this->assertDatabaseMissing('users', ['email' => 'atomic-fail@example.com']);
        $this->assertDatabaseMissing('verification_codes', [
            'purpose' => 'register',
            'identifier' => 'atomic-fail@example.com',
        ]);
    }

    public function test_register_success_persists_user_code_and_mail()
    {
        Notification::fake();

        $response = $this->postJson('/api/register', [
            'name' => 'Atomic User',
            'email' => 'atomic-ok@example.com',
            'password' => 'C0ncierge-Str0ng-77',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('users', ['email' => 'atomic-ok@example.com']);
        $this->assertDatabaseHas('verification_codes', [
            'purpose' => 'register',
            'identifier' => 'atomic-ok@example.com',
        ]);

        Notification::assertSentOnDemand(VerificationCodeMail::class);
    }
}
