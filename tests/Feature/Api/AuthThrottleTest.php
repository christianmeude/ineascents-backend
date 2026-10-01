<?php

namespace Tests\Feature\Api;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthThrottleTest extends TestCase
{
    #[DataProvider('throttledRoutes')]
    public function test_bare_auth_and_write_routes_are_throttled(string $method, string $uri, string $throttle): void
    {
        $route = collect(\Illuminate\Support\Facades\Route::getRoutes()->getRoutes())
            ->first(fn ($r) => in_array(strtoupper($method), $r->methods()) && $r->uri() === $uri);

        $this->assertNotNull($route, "Route {$method} {$uri} not found");
        $middleware = array_merge($route->gatherMiddleware(), (array) ($route->getAction()['middleware'] ?? []));
        $want = 'ThrottleRequests:' . explode(':', $throttle)[1];
        $hit = array_filter($middleware, fn ($m) => is_string($m) && (str_contains($m, $want) || $m === $throttle));
        $this->assertNotEmpty($hit, "Route {$method} {$uri} missing {$throttle}; has: " . implode(',', array_map('strval', $middleware)));
    }

    public static function throttledRoutes(): array
    {
        return [
            'login' => ['POST', 'api/login', 'throttle:5,1'],
            'register' => ['POST', 'api/register', 'throttle:5,1'],
            'bookings store' => ['POST', 'api/bookings', 'throttle:10,1'],
            'availability' => ['GET', 'api/availability', 'throttle:30,1'],
        ];
    }
}
