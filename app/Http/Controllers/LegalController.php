<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A17: versioned public legal pages (privacy notice + terms).
 * Version is date-based (YYYY-MM-DD); links carry ?v= so consent
 * records (A18) can pin the exact text a user agreed to.
 */
class LegalController extends Controller
{
    public const VERSION = '2026-10-01';

    public const EFFECTIVE_DATE = 'October 1, 2026';

    private function props(Request $request): array
    {
        $requested = $request->query('v');

        return [
            'version' => self::VERSION,
            'effectiveDate' => self::EFFECTIVE_DATE,
            'requestedVersion' => is_string($requested) ? $requested : null,
            'contactEmail' => config('app.privacy_contact_email'),
            'appName' => config('app.name'),
        ];
    }

    public function privacy(Request $request): Response
    {
        return Inertia::render('Legal/Privacy', $this->props($request));
    }

    public function terms(Request $request): Response
    {
        return Inertia::render('Legal/Terms', $this->props($request));
    }
}
