<?php

namespace Tests\Feature;

use Tests\TestCase;

class SpaFallbackTest extends TestCase
{
    private string $index;

    private ?string $original = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->index = public_path('index.html');
        $this->original = is_file($this->index) ? file_get_contents($this->index) : null;

        file_put_contents($this->index, '<pms-root></pms-root>');
    }

    protected function tearDown(): void
    {
        $this->original === null ? @unlink($this->index) : file_put_contents($this->index, $this->original);

        parent::tearDown();
    }

    public function test_deep_links_are_served_the_angular_shell_without_caching(): void
    {
        foreach (['/', '/dashboard', '/password/reset/abc'] as $path) {
            $response = $this->get($path)->assertOk();

            $this->assertStringContainsString('<pms-root>', $response->streamedContent() ?: (string) file_get_contents($response->baseResponse->getFile()->getPathname()));
            $this->assertStringContainsString('no-cache', (string) $response->headers->get('Cache-Control'));
        }
    }

    public function test_server_prefixes_never_fall_back_to_the_spa(): void
    {
        $this->get('/bff/unknown')->assertNotFound();
        $this->get('/integrations/unknown')->assertNotFound();
    }
}
