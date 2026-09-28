<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\VerificationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class VerificationCodeMailTest extends TestCase
{
    use RefreshDatabase;

    private const CODE = '482916';

    public function test_verification_code_is_sent_via_mail(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $user->notify(new VerificationCode(self::CODE, 'password reset'));

        Notification::assertSentTo($user, VerificationCode::class, function ($notification) use ($user) {
            return $notification->code === self::CODE
                && $notification->via($user) === ['mail'];
        });
    }

    public function test_verification_code_mail_renders_code_subject_and_expiry(): void
    {
        $user = User::factory()->create();

        $mail = (new VerificationCode(self::CODE, 'password reset'))->toMail($user);

        $this->assertSame('Your Inea Scents verification code', $mail->subject);

        $html = $mail->render();

        $this->assertStringContainsString(self::CODE, $html);
        $this->assertStringContainsString('INEA SCENTS', $html);
        $this->assertStringContainsString('Your password reset code is:', $html);
        $this->assertStringContainsString(
            'This code expires in 15 minutes. If you did not request it, ignore this email.',
            $html
        );
        $this->assertStringContainsString('#6a4053', $html);
        $this->assertStringContainsString('#fdf4f5', $html);
        $this->assertStringNotContainsString('#3d2f23', $html);
    }

    public function test_verification_code_mail_subject_carries_local_prefix_on_local(): void
    {
        $this->app->detectEnvironment(fn () => 'local');

        $user = User::factory()->create();

        $mail = (new VerificationCode(self::CODE, 'password reset'))->toMail($user);

        $this->assertSame('[LOCAL] Your Inea Scents verification code', $mail->subject);
    }

    public function test_verification_code_mail_sender_is_configured(): void
    {
        $this->assertNotEmpty(config('mail.from.address'));
        $this->assertNotEmpty(config('mail.from.name'));
    }
}
