<?php

namespace Tests\Feature;

use Tests\TestCase;

class SitemapTest extends TestCase
{
    public function test_sitemap_is_valid_xml_with_home_url(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $this->assertStringContainsString('application/xml', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('<loc>'.url('/').'</loc>', $response->getContent());
        $this->assertNotFalse(simplexml_load_string($response->getContent()));
    }
}
