<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AuthEndpointSweepTest extends TestCase
{
    use DatabaseTransactions;

    private const ENDPOINTS = [
        ['POST', '/api/v1/auth/forgot-password'],
        ['POST', '/api/v1/auth/verify-token'],
        ['PUT', '/api/v1/auth/reset-password'],
        ['PUT', '/api/v1/auth/firebase-reset-password'],
        ['POST', '/api/v1/auth/delivery-man/forgot-password'],
        ['POST', '/api/v1/auth/delivery-man/verify-token'],
        ['POST', '/api/v1/auth/delivery-man/firebase-verify-token'],
        ['PUT', '/api/v1/auth/delivery-man/reset-password'],
        ['POST', '/api/v1/auth/delivery-man/login'],
        ['POST', '/api/v1/auth/delivery-man/store'],
        ['POST', '/api/v1/auth/vendor/forgot-password'],
        ['POST', '/api/v1/auth/vendor/verify-token'],
        ['PUT', '/api/v1/auth/vendor/reset-password'],
        ['POST', '/api/v1/auth/vendor/login'],
        ['POST', '/api/v1/auth/vendor/register'],
    ];

    public function test_every_auth_endpoint_answers_an_empty_body_in_the_response_envelope(): void
    {
        $failures = [];

        foreach (self::ENDPOINTS as [$verb, $uri]) {
            $response = $this->json($verb, $uri, []);
            $body = $response->json();

            if ($response->getStatusCode() >= 500) {
                $failures[] = "$verb $uri returned {$response->getStatusCode()}";

                continue;
            }

            if (! is_array($body) || ! array_key_exists('identical_code', $body) || ! array_key_exists('errors', $body)) {
                $failures[] = "$verb $uri is not using the response envelope";
            }
        }

        echo "\nprobed ".count(self::ENDPOINTS)." auth endpoints\n";

        $this->assertSame([], $failures, implode("\n", $failures));
    }
}
