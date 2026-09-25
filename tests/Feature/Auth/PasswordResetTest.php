<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\VerificationCode as VerificationCodeMail;
use App\Services\VerificationCodes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(VerificationCodes::class, fn () => new class('482916') extends VerificationCodes
        {
            public function __construct(private string $fixed) {}

            protected function generateCode(): string
            {
                return $this->fixed;
            }
        });
    }

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/admin/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_code_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create(['is_admin' => true]);

        $this->post('/admin/forgot-password', ['email' => $user->email])
            ->assertSessionHas('status');

        Notification::assertSentOnDemand(VerificationCodeMail::class);
    }

    public function test_unknown_email_returns_same_success(): void
    {
        Notification::fake();

        $this->post('/admin/forgot-password', ['email' => 'nobody@example.com'])
            ->assertSessionHas('status');

        Notification::assertNothingSent();
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        $response = $this->get('/admin/reset-password?email=test@example.com');

        $response->assertStatus(200);
    }

    public function test_password_can_be_reset_with_valid_code(): void
    {
        Notification::fake();

        $user = User::factory()->create(['is_admin' => true]);

        $this->post('/admin/forgot-password', ['email' => $user->email]);

        $response = $this->post('/admin/reset-password', [
            'code' => '482916',
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_password_cannot_be_reset_with_wrong_code(): void
    {
        Notification::fake();

        $user = User::factory()->create(['is_admin' => true]);

        $this->post('/admin/forgot-password', ['email' => $user->email]);

        $this->post('/admin/reset-password', [
            'code' => '000000',
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors(['code']);
    }
}
