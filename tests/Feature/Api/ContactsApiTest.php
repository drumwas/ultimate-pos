<?php

namespace Tests\Feature\Api;

use App\User;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * API Contacts Tests
 * 
 * Tests for customers and suppliers endpoints.
 */
class ContactsApiTest extends TestCase
{
    /**
     * Test contacts require authentication.
     *
     * @return void
     */
    public function test_contacts_require_authentication()
    {
        $endpoints = [
            '/api/contacts/customers',
            '/api/contacts/suppliers',
            '/api/contacts/with-balances',
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->getJson($endpoint);
            $response->assertStatus(401);
        }
    }

    /**
     * Test customers listing returns paginated results.
     *
     * @return void
     */
    public function test_customers_listing_returns_paginated_results()
    {
        $user = User::first();

        if (!$user) {
            $this->markTestSkipped('No user found. Please create test data first.');
        }

        Passport::actingAs($user);

        $response = $this->getJson('/api/contacts/customers');

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
     * Test customers can be searched.
     *
     * @return void
     */
    public function test_customers_can_be_searched()
    {
        $user = User::first();

        if (!$user) {
            $this->markTestSkipped('No user found. Please create test data first.');
        }

        Passport::actingAs($user);

        $response = $this->getJson('/api/contacts/customers?search=test');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => ['items', 'pagination'],
            ]);
    }

    /**
     * Test suppliers listing returns paginated results.
     *
     * @return void
     */
    public function test_suppliers_listing_returns_paginated_results()
    {
        $user = User::first();

        if (!$user) {
            $this->markTestSkipped('No user found. Please create test data first.');
        }

        Passport::actingAs($user);

        $response = $this->getJson('/api/contacts/suppliers');

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
     * Test contacts with balances returns list.
     *
     * @return void
     */
    public function test_contacts_with_balances_returns_list()
    {
        $user = User::first();

        if (!$user) {
            $this->markTestSkipped('No user found. Please create test data first.');
        }

        Passport::actingAs($user);

        $response = $this->getJson('/api/contacts/with-balances?type=customer');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'type',
                    'items',
                    'pagination',
                ],
            ]);
    }

    /**
     * Test contact details returns 404 for non-existent contact.
     *
     * @return void
     */
    public function test_contact_details_returns_404_for_nonexistent()
    {
        $user = User::first();

        if (!$user) {
            $this->markTestSkipped('No user found. Please create test data first.');
        }

        Passport::actingAs($user);

        $response = $this->getJson('/api/contacts/999999999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Contact not found',
            ]);
    }
}
