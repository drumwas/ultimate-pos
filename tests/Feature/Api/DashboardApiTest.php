<?php

namespace Tests\Feature\Api;

use App\User;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * API Dashboard Tests
 * 
 * Tests for dashboard summary, stock alerts, and payment dues endpoints.
 */
class DashboardApiTest extends TestCase
{
    /**
     * Test dashboard summary requires authentication.
     *
     * @return void
     */
    public function test_dashboard_summary_requires_authentication()
    {
        $response = $this->getJson('/api/dashboard/summary');

        $response->assertStatus(401);
    }

    /**
     * Test dashboard summary returns expected structure.
     *
     * @return void
     */
    public function test_dashboard_summary_returns_expected_structure()
    {
        $user = User::first();

        if (!$user) {
            $this->markTestSkipped('No user found. Please create test data first.');
        }

        Passport::actingAs($user);

        $response = $this->getJson('/api/dashboard/summary');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'date_range' => ['start', 'end'],
                    'sales' => ['total', 'total_exc_tax', 'invoice_due'],
                    'purchases' => ['total', 'total_exc_tax', 'purchase_due'],
                    'returns' => ['sell_return', 'purchase_return'],
                    'expenses' => ['total'],
                    'net',
                ],
            ]);
    }

    /**
     * Test dashboard summary accepts date range parameters.
     *
     * @return void
     */
    public function test_dashboard_summary_accepts_date_range()
    {
        $user = User::first();

        if (!$user) {
            $this->markTestSkipped('No user found. Please create test data first.');
        }

        Passport::actingAs($user);

        $response = $this->getJson('/api/dashboard/summary?start_date=2024-01-01&end_date=2024-12-31');

        $response->assertStatus(200)
            ->assertJsonPath('data.date_range.start', '2024-01-01')
            ->assertJsonPath('data.date_range.end', '2024-12-31');
    }

    /**
     * Test today stats endpoint returns correct structure.
     *
     * @return void
     */
    public function test_today_stats_returns_expected_structure()
    {
        $user = User::first();

        if (!$user) {
            $this->markTestSkipped('No user found. Please create test data first.');
        }

        Passport::actingAs($user);

        $response = $this->getJson('/api/dashboard/today');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'date',
                    'sales' => ['count', 'total'],
                    'purchases' => ['count', 'total'],
                    'expenses' => ['count', 'total'],
                ],
            ]);
    }

    /**
     * Test stock alerts endpoint returns list.
     *
     * @return void
     */
    public function test_stock_alerts_returns_list()
    {
        $user = User::first();

        if (!$user) {
            $this->markTestSkipped('No user found. Please create test data first.');
        }

        Passport::actingAs($user);

        $response = $this->getJson('/api/dashboard/stock-alerts');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'count',
                    'products',
                ],
            ]);
    }

    /**
     * Test payment dues endpoint returns both purchase and sales dues.
     *
     * @return void
     */
    public function test_payment_dues_returns_both_types()
    {
        $user = User::first();

        if (!$user) {
            $this->markTestSkipped('No user found. Please create test data first.');
        }

        Passport::actingAs($user);

        $response = $this->getJson('/api/dashboard/payment-dues');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'purchase_dues' => ['count', 'items'],
                    'sales_dues' => ['count', 'items'],
                ],
            ]);
    }
}
