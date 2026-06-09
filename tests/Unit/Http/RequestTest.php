<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\Request;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    private function make(string $method, string $uri, array $post = [], array $query = []): Request
    {
        return new Request($method, $uri, $query, $post, []);
    }

    public function test_path_strips_leading_and_trailing_slashes(): void
    {
        $request = $this->make('GET', '/api/recipes/');
        $this->assertSame('api/recipes', $request->path());
    }

    public function test_path_strips_query_string(): void
    {
        $request = $this->make('GET', '/api/recipes?q=pasta&difficulty=easy');
        $this->assertSame('api/recipes', $request->path());
    }

    public function test_path_returns_empty_string_for_root(): void
    {
        $request = $this->make('GET', '/');
        $this->assertSame('', $request->path());
    }

    public function test_method_is_normalized_to_uppercase(): void
    {
        $request = $this->make('get', '/');
        $this->assertSame('GET', $request->method());
    }

    public function test_isGet_returns_true_for_get_method(): void
    {
        $request = $this->make('GET', '/');
        $this->assertTrue($request->isGet());
        $this->assertFalse($request->isPost());
        $this->assertFalse($request->isPatch());
        $this->assertFalse($request->isDelete());
    }

    public function test_isPost_returns_true_for_post_method(): void
    {
        $request = $this->make('POST', '/');
        $this->assertTrue($request->isPost());
        $this->assertFalse($request->isGet());
    }

    public function test_isPatch_returns_true_for_patch_method(): void
    {
        $request = $this->make('PATCH', '/');
        $this->assertTrue($request->isPatch());
    }

    public function test_isDelete_returns_true_for_delete_method(): void
    {
        $request = $this->make('DELETE', '/');
        $this->assertTrue($request->isDelete());
    }

    public function test_input_returns_null_when_key_missing(): void
    {
        $request = $this->make('POST', '/', []);
        $this->assertNull($request->input('nonexistent'));
    }

    public function test_input_returns_provided_default(): void
    {
        $request = $this->make('POST', '/', []);
        $this->assertSame('fallback', $request->input('missing', 'fallback'));
    }

    public function test_input_returns_value_from_post_body(): void
    {
        $request = $this->make('POST', '/', ['title' => 'Makaron z pesto', 'servings' => 4]);
        $this->assertSame('Makaron z pesto', $request->input('title'));
        $this->assertSame(4, $request->input('servings'));
    }

    public function test_query_returns_value_from_query_string(): void
    {
        $request = $this->make('GET', '/api/recipes?q=zupa', [], ['q' => 'zupa']);
        $this->assertSame('zupa', $request->query('q'));
    }

    public function test_query_returns_default_when_key_missing(): void
    {
        $request = $this->make('GET', '/');
        $this->assertSame('', $request->query('q', ''));
    }

    public function test_routeParam_returns_null_before_params_are_set(): void
    {
        $request = $this->make('GET', '/api/recipes/42');
        $this->assertNull($request->routeParam('recipeId'));
    }

    public function test_withRouteParams_returns_new_instance_with_params(): void
    {
        $request = $this->make('GET', '/api/recipes/42');
        $cloned  = $request->withRouteParams(['recipeId' => '42']);

        $this->assertSame('42', $cloned->routeParam('recipeId'));
    }

    public function test_withRouteParams_does_not_mutate_original(): void
    {
        $request = $this->make('GET', '/api/recipes/42');
        $request->withRouteParams(['recipeId' => '42']);

        $this->assertNull($request->routeParam('recipeId'));
    }

    public function test_routeParam_returns_custom_default(): void
    {
        $request = $this->make('GET', '/api/recipes/42');
        $this->assertSame(0, $request->routeParam('recipeId', 0));
    }
}
