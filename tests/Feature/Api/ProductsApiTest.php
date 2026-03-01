<?php

namespace Tests\Feature\Api;

use App\User;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * API Products Tests
 * 
 * Tests for products, categories, and brands endpoints.
 */
class ProductsApiTest extends TestCase
{
    /**
     * Test products require authentication.
     *
     * @return void
     */
    public function test_products_require_authentication()
    {
        $endpoints = [
            '/api/products',
            '/api/products/categories',
            '/api/products/brands',
            '/api/products/low-stock',
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->getJson($endpoint);
            $response->assertStatus(401);
        }
    }

    /**
     * Test products listing returns paginated results.
     *
     * @return void
     */
    public function test_products_listing_returns_paginated_results()
    {
        $user = User::first();

        if (!$user) {
            $this->markTestSkipped('No user found. Please create test data first.');
        }

        Passport::actingAs($user);

        $response = $this->getJson('/api/products');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'items',
                    'pagination' => [
                        'current_page',
                        'last_page',
                        'per_page',
                        'total',
                    ],
                ],
            ]);
    }

    /**
     * Test products can be searched.
     *
     * @return void
     */
    public function test_products_can_be_searched()
    {
        $user = User::first();

        if (!$user) {
            $this->markTestSkipped('No user found. Please create test data first.');
        }

        Passport::actingAs($user);

        $response = $this->getJson('/api/products?search=test');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => ['items', 'pagination'],
            ]);
    }

    /**
     * Test categories returns list with subcategories.
     *
     * @return void
     */
    public function test_categories_returns_list_with_subcategories()
    {
        $user = User::first();

        if (!$user) {
            $this->markTestSkipped('No user found. Please create test data first.');
        }

        Passport::actingAs($user);

        $response = $this->getJson('/api/products/categories');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
            ]);
    }

    /**
     * Test brands returns list.
     *
     * @return void
     */
    public function test_brands_returns_list()
    {
        $user = User::first();

        if (!$user) {
            $this->markTestSkipped('No user found. Please create test data first.');
        }

        Passport::actingAs($user);

        $response = $this->getJson('/api/products/brands');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
            ]);
    }

    /**
     * Test low stock products returns list.
     *
     * @return void
     */
    public function test_low_stock_returns_paginated_list()
    {
        $user = User::first();

        if (!$user) {
            $this->markTestSkipped('No user found. Please create test data first.');
        }

        Passport::actingAs($user);

        $response = $this->getJson('/api/products/low-stock');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'items',
                    'pagination',
                ],
            ]);
    }

    /**
     * Test product details returns 404 for non-existent product.
     *
     * @return void
     */
    public function test_product_details_returns_404_for_nonexistent()
    {
        $user = User::first();

        if (!$user) {
            $this->markTestSkipped('No user found. Please create test data first.');
        }

        Passport::actingAs($user);

        $response = $this->getJson('/api/products/999999999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Product not found',
            ]);
    }
}
