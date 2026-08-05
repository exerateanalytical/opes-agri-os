<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiDocsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_docs_ui_is_publicly_reachable(): void
    {
        $this->get('/docs/api')->assertOk();
    }

    public function test_the_openapi_spec_documents_the_v1_endpoints(): void
    {
        $response = $this->get('/docs/api.json')->assertOk();

        $paths = array_keys($response->json('paths'));

        foreach (['/contacts', '/items', '/documents', '/documents/{document}/issue', '/payments'] as $expected) {
            $this->assertContains($expected, $paths, "Expected {$expected} to be documented.");
        }

        // The legacy session-authenticated sync protocol is a different
        // surface entirely and must never appear in the public API docs.
        foreach ($paths as $path) {
            $this->assertStringNotContainsString('sync', $path);
        }
    }

    public function test_the_spec_declares_bearer_auth_globally(): void
    {
        $response = $this->get('/docs/api.json')->assertOk();

        $this->assertSame('bearer', $response->json('components.securitySchemes.http.scheme'));
        $this->assertNotEmpty($response->json('security'));
    }
}
