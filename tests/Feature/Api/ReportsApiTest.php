<?php

namespace Tests\Feature\Api;

use App\User;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * API Reports Tests
 * 
 * Tests for reports endpoints including profit/loss, sales, purchases, expenses.
 */
class ReportsApiTest extends TestCase
{
    /**
     * Test reports require authentication.
     *
     * @return void
     */
    public function test_reports_require_authentication()
    {
        $endpoints = [
            '/api/reports/profit-loss',
            '/api/reports/sales',
            '/api/reports/purchases',
            '/api/reports/expenses',
            '/api/reports/stock-summary',
            '/api/reports/trending-products',
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->getJson($endpoint);
            $response->assertStatus(401);
        }
    }

    /**
     * Test profit loss report returns expected structure.
     *
     * @return void
     */
    public function test_profit_loss_report_returns_expected_structure()
    {
        $user = User::first();

        if (!$user) {
            $this->markTestSkipped('No user found. Please create test data first.');
        }

        Passport::actingAs($user);

        $response = $this->getJson('/api/reports/profit-loss');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'date_range',
                    'total_purchase',
                    'total_sell',
                    'total_expense',
                    'gross_profit',
                    'net_profit',
                ],
            ]);
    }

    /**
     * Test sales report returns expected structure.
     *
     * @return void
     */
    public function test_sales_report_returns_expected_structure()
    {
        $user = User::first();

        if (!$user) {
            $this->markTestSkipped('No user found. Please create test data first.');
        }

        Passport::actingAs($user);

        $response = $this->getJson('/api/reports/sales');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'date_range',
                    'totals' => ['total_sales', 'total_exc_tax', 'invoice_due'],
                    'by_status',
                    'by_payment_status',
                ],
            ]);
    }

    /**
     * Test purchases report returns expected structure.
     *
     * @return void
     */
    public function test_purchases_report_returns_expected_structure()
    {
        $user = User::first();

        if (!$user) {
            $this->markTestSkipped('No user found. Please create test data first.');
        }

        Passport::actingAs($user);

        $response = $this->getJson('/api/reports/purchases');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'date_range',
                    'totals' => ['total_purchases', 'total_exc_tax', 'purchase_due'],
                    'by_status',
                ],
            ]);
    }

    /**
     * Test expenses report returns categories breakdown.
     *
     * @return void
     */
    public function test_expenses_report_returns_categories_breakdown()
    {
        $user = User::first();

        if (!$user) {
            $this->markTestSkipped('No user found. Please create test data first.');
        }

        Passport::actingAs($user);

        $response = $this->getJson('/api/reports/expenses');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'date_range',
                    'total_expense',
                    'by_category',
                ],
            ]);
    }

    /**
     * Test stock summary returns inventory value.
     *
     * @return void
     */
    public function test_stock_summary_returns_inventory_value()
    {
        $user = User::first();

        if (!$user) {
            $this->markTestSkipped('No user found. Please create test data first.');
        }

        Passport::actingAs($user);

        $response = $this->getJson('/api/reports/stock-summary');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'total_quantity',
                    'stock_value_by_selling_price',
                    'stock_value_by_purchase_price',
                    'potential_profit',
                    'low_stock_products_count',
                ],
            ]);
    }

    /**
     * Test trending products returns top selling items.
     *
     * @return void
     */
    public function test_trending_products_returns_top_sellers()
    {
        $user = User::first();

        if (!$user) {
            $this->markTestSkipped('No user found. Please create test data first.');
        }

        Passport::actingAs($user);

        $response = $this->getJson('/api/reports/trending-products');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'date_range',
                    'products',
                ],
            ]);
    }
}
