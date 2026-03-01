<?php

namespace Tests\Feature\Api;

use App\Business;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * API Authentication Tests
 * 
 * Tests for login, logout, and user profile endpoints.
 */
class AuthApiTest extends TestCase
{
    // Note: Uncomment RefreshDatabase if you have a test database configured
    // use RefreshDatabase;

    /**
     * Test login with valid credentials returns token.
     *
     * @return void
     */
    public function test_login_with_valid_credentials_returns_token()
    {
        // Create test user if not exists
        $user = User::where('username', 'admin')->first();

        if (!$user) {
            $this->markTestSkipped('No admin user found. Please create test data first.');
        }

        $response = $this->postJson('/api/auth/login', [
            'username' => 'admin',
            'password' => 'admin123', // Default password - adjust as needed
        ]);

        // Should either succeed or fail gracefully
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'access_token',
                    'token_type',
                    'user' => [
                        'id',
                        'username',
                        'email',
                    ],
                ],
            ]);
    }

    /**
     * Test login with invalid credentials returns error.
     *
     * @return void
     */
    public function test_login_with_invalid_credentials_returns_error()
    {
        $response = $this->postJson('/api/auth/login', [
            'username' => 'invalid_user',
            'password' => 'wrong_password',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid credentials',
            ]);
    }

    /**
     * Test login with missing fields returns validation error.
     *
     * @return void
     */
    public function test_login_with_missing_fields_returns_validation_error()
    {
        $response = $this->postJson('/api/auth/login', [
            'username' => 'test',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test authenticated user can get profile.
     *
     * @return void
     */
    public function test_authenticated_user_can_get_profile()
    {
        $user = User::first();

        if (!$user) {
            $this->markTestSkipped('No user found. Please create test data first.');
        }

        Passport::actingAs($user);

        $response = $this->getJson('/api/auth/user');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'username',
                    'email',
                    'business_id',
                ],
            ]);
    }

    /**
     * Test unauthenticated user cannot access profile.
     *
     * @return void
     */
    public function test_unauthenticated_user_cannot_access_profile()
    {
        $response = $this->getJson('/api/auth/user');

        $response->assertStatus(401);
    }

    /**
     * Test logout revokes token.
     *
     * @return void
     */
    public function test_logout_revokes_token()
    {
        $user = User::first();

        if (!$user) {
            $this->markTestSkipped('No user found. Please create test data first.');
        }

        Passport::actingAs($user);

        $response = $this->postJson('/api/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logged out successfully',
            ]);
    }
}
