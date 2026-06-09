<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\Router;
use App\Http\ViewRenderer;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $renderer    = new ViewRenderer('/nonexistent-path');
        $this->router = new Router([], $renderer);
    }

    private function matchPattern(string $pattern, string $path): ?array
    {
        $method = new ReflectionMethod(Router::class, 'matchPattern');
        $method->setAccessible(true);

        return $method->invoke($this->router, $pattern, $path);
    }

    public function test_exact_path_matches_and_returns_empty_params(): void
    {
        $result = $this->matchPattern('api/recipes', 'api/recipes');
        $this->assertSame([], $result);
    }

    public function test_non_matching_path_returns_null(): void
    {
        $result = $this->matchPattern('api/recipes', 'api/users');
        $this->assertNull($result);
    }

    public function test_single_route_param_is_extracted(): void
    {
        $result = $this->matchPattern('api/recipes/{recipeId}', 'api/recipes/42');
        $this->assertSame(['recipeId' => '42'], $result);
    }

    public function test_multiple_route_params_are_extracted(): void
    {
        $result = $this->matchPattern(
            'api/meal-plans/{planId}/slots/{slotId}/recipes/{recipeId}',
            'api/meal-plans/1/slots/5/recipes/99'
        );
        $this->assertSame(['planId' => '1', 'slotId' => '5', 'recipeId' => '99'], $result);
    }

    public function test_param_does_not_match_across_slashes(): void
    {
        $result = $this->matchPattern('api/recipes/{recipeId}', 'api/recipes/1/details');
        $this->assertNull($result);
    }

    public function test_partial_path_does_not_match(): void
    {
        $result = $this->matchPattern('api/recipes/{recipeId}', 'api/recipes');
        $this->assertNull($result);
    }

    public function test_slug_style_param_matches_hyphens(): void
    {
        $result = $this->matchPattern('api/recipes/{slug}', 'api/recipes/makaron-z-warzywami');
        $this->assertSame(['slug' => 'makaron-z-warzywami'], $result);
    }

    public function test_pattern_with_trailing_literal_after_param(): void
    {
        $result = $this->matchPattern(
            'api/recipes/{recipeId}/submit-for-review',
            'api/recipes/7/submit-for-review'
        );
        $this->assertSame(['recipeId' => '7'], $result);
    }

    public function test_literal_segment_must_match_exactly(): void
    {
        $result = $this->matchPattern(
            'api/recipes/{recipeId}/submit-for-review',
            'api/recipes/7/approve'
        );
        $this->assertNull($result);
    }

    public function test_two_params_with_literal_segment_between(): void
    {
        $result = $this->matchPattern(
            'api/recipe-reviews/{reviewId}/request-changes',
            'api/recipe-reviews/12/request-changes'
        );
        $this->assertSame(['reviewId' => '12'], $result);
    }
}
