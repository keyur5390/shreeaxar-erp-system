<?php

namespace Tests\Feature;

use App\Http\Middleware\ValidateHealthToken;
use Illuminate\Http\Request;
use Tests\TestCase;

class HealthTokenMiddlewareTest extends TestCase
{
    public function test_health_endpoint_requires_valid_token(): void
    {
        config(['app.health_token' => 'secret-token']);

        $middleware = new ValidateHealthToken();
        $request = Request::create('/api/health', 'GET');

        $response = $middleware->handle($request, fn () => response('OK'));

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_health_endpoint_accepts_matching_token(): void
    {
        config(['app.health_token' => 'secret-token']);

        $middleware = new ValidateHealthToken();
        $request = Request::create('/api/health', 'GET');
        $request->headers->set('X-Health-Token', 'secret-token');

        $response = $middleware->handle($request, fn () => response('OK'));

        $this->assertSame(200, $response->getStatusCode());
    }
}
