<?php

namespace Tests\Feature\Api;

use App\User;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * API Transactions Tests
 * 
 * Tests for transaction listing and details endpoints.
 */
class TransactionsApiTest extends TestCase
{
    /**
     * Test transactions require authentication.
     *
     * @return void
     */
    public function test_transactions_require_authentication()
    {
        $endpoints = [
            '/api/transactions/sales',
            '/api/transactions/purchases',
            '/api/transactions/recent',
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->getJson($endpoint);
            $response->assertStatus(401);
        }
    }

    /**
     * Test sales listing returns paginated results.
     *
     * @return void
     */
    public function test_sales_listing_returns_paginated_results()
    {
        $user = User::first();

        if (!$user) {
            $this->markTestSkipped('No user found. Please create test data first.');
        }

        Passport::actingAs($user);

        $response = $this->getJson('/api/transactions/sales');

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
     * Test sales listing accepts filters.
     *
     * @return void
     */
    public function test_sales_listing_accepts_filters()
    {
        $user = User::first();

        if (!$user) {
            $this->markTestSkipped('No user found. Please create test data first.');
        }

        Passport::actingAs($user);

        $response = $this->getJson('/api/transactions/sales?status=final&payment_status=paid&per_page=10');

        $response->assertStatus(200)
            ->assertJsonPath('data.pagination.per_page', 10);
    }

    /**
     * Test purchases listing returns paginated results.
     *
     * @return void
     */
    public function test_purchases_listing_returns_paginated_results()
    {
        $user = User::first();

        if (!$user) {
            $this->markTestSkipped('No user found. Please create test data first.');
        }

        Passport::actingAs($user);

        $response = $this->getJson('/api/transactions/purchases');

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
     * Test recent transactions endpoint works.
     *
     * @return void
     */
    public function test_recent_transactions_returns_list()
    {
        $user = User::first();

        if (!$user) {
            $this->markTestSkipped('No user found. Please create test data first.');
        }

        Passport::actingAs($user);

        $response = $this->getJson('/api/transactions/recent');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
            ]);
    }

    /**
     * Test sale details returns 404 for non-existent sale.
     *
     * @return void
     */
    public function test_sale_details_returns_404_for_nonexistent()
    {
        $user = User::first();

        if (!$user) {
            $this->markTestSkipped('No user found. Please create test data first.');
        }

        Passport::actingAs($user);

        $response = $this->getJson('/api/transactions/sales/999999999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Sale not found',
            ]);
    }

    /**
     * Test purchase details returns 404 for non-existent purchase.
     *
     * @return void
     */
    public function test_purchase_details_returns_404_for_nonexistent()
    {
        $user = User::first();

        if (!$user) {
            $this->markTestSkipped('No user found. Please create test data first.');
        }

        Passport::actingAs($user);

        $response = $this->getJson('/api/transactions/purchases/999999999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Purchase not found',
            ]);
    }
}
