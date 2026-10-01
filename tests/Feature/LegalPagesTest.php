<?php

namespace Tests\Feature;

use App\Http\Controllers\LegalController;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\DataProvider('legalRoutes')]
    public function test_legal_page_renders_versioned_content(string $uri, string $component): void
    {
        $this->get($uri)
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component($component)
                ->where('version', LegalController::VERSION)
                ->where('effectiveDate', LegalController::EFFECTIVE_DATE)
                ->where('contactEmail', config('app.privacy_contact_email'))
                ->has('appName'));
    }

    public static function legalRoutes(): array
    {
        return [
            'privacy' => ['/privacy', 'Legal/Privacy'],
            'terms' => ['/terms', 'Legal/Terms'],
        ];
    }

    public function test_requested_version_is_echoed(): void
    {
        $this->get('/privacy?v=2026-10-01')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->where('requestedVersion', '2026-10-01'));

        $this->get('/terms?v=2026-10-01')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->where('requestedVersion', '2026-10-01'));
    }
}
