<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\Response;
use PHPUnit\Framework\TestCase;
use ReflectionObject;

final class ResponseTest extends TestCase
{
    private function get(Response $response, string $property): mixed
    {
        $ref = new ReflectionObject($response);
        $prop = $ref->getProperty($property);
        $prop->setAccessible(true);

        return $prop->getValue($response);
    }

    // --- json() ---

    public function test_json_encodes_array_to_string(): void
    {
        $response = Response::json(['key' => 'value', 'count' => 2]);
        $this->assertSame('{"key":"value","count":2}', $this->get($response, 'content'));
    }

    public function test_json_default_status_is_200(): void
    {
        $response = Response::json([]);
        $this->assertSame(200, $this->get($response, 'statusCode'));
    }

    public function test_json_custom_status_code(): void
    {
        $response = Response::json([], 201);
        $this->assertSame(201, $this->get($response, 'statusCode'));
    }

    public function test_json_sets_application_json_content_type(): void
    {
        $response = Response::json([]);
        $headers  = $this->get($response, 'headers');
        $this->assertArrayHasKey('Content-Type', $headers);
        $this->assertStringContainsString('application/json', $headers['Content-Type']);
    }

    public function test_json_preserves_utf8_characters_unescaped(): void
    {
        $response = Response::json(['name' => 'Zupa pomidorowa', 'emoji' => '🍅']);
        $content  = $this->get($response, 'content');
        $this->assertStringContainsString('Zupa pomidorowa', $content);
        $this->assertStringContainsString('🍅', $content);
    }

    public function test_json_encodes_nested_structure(): void
    {
        $response = Response::json(['recipes' => [['id' => 1, 'title' => 'Makaron']]]);
        $decoded  = json_decode($this->get($response, 'content'), true);
        $this->assertSame(1, $decoded['recipes'][0]['id']);
    }

    public function test_json_throws_on_non_encodable_value(): void
    {
        $this->expectException(\JsonException::class);
        Response::json(['value' => NAN]);
    }

    // --- redirect() ---

    public function test_redirect_sets_location_header(): void
    {
        $response = Response::redirect('/login');
        $headers  = $this->get($response, 'headers');
        $this->assertSame('/login', $headers['Location']);
    }

    public function test_redirect_default_status_is_302(): void
    {
        $response = Response::redirect('/login');
        $this->assertSame(302, $this->get($response, 'statusCode'));
    }

    public function test_redirect_custom_status_code(): void
    {
        $response = Response::redirect('/dashboard', 301);
        $this->assertSame(301, $this->get($response, 'statusCode'));
    }

    public function test_redirect_body_is_empty(): void
    {
        $response = Response::redirect('/login');
        $this->assertSame('', $this->get($response, 'content'));
    }

    // --- html() ---

    public function test_html_sets_text_html_content_type(): void
    {
        $response = Response::html('<p>Hello</p>');
        $headers  = $this->get($response, 'headers');
        $this->assertStringContainsString('text/html', $headers['Content-Type']);
    }

    public function test_html_default_status_is_200(): void
    {
        $response = Response::html('');
        $this->assertSame(200, $this->get($response, 'statusCode'));
    }

    public function test_html_stores_content_verbatim(): void
    {
        $html     = '<h1>Tytuł</h1>';
        $response = Response::html($html);
        $this->assertSame($html, $this->get($response, 'content'));
    }
}
