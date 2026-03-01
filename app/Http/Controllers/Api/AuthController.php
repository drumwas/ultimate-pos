<?php

namespace App\Http\Controllers\Api;

use App\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * API Authentication Controller
 * 
 * Handles login, logout, and user profile for mobile app authentication.
 * 
 * @OA\Post(
 *     path="/auth/login",
 *     summary="Login to get access token",
 *     tags={"Authentication"},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"username","password"},
 *             @OA\Property(property="username", type="string", example="admin"),
 *             @OA\Property(property="password", type="string", format="password", example="password123")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Login successful",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Login successful"),
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="access_token", type="string"),
 *                 @OA\Property(property="token_type", type="string", example="Bearer"),
 *                 @OA\Property(property="user", type="object")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=401, description="Invalid credentials"),
 *     @OA\Response(response=422, description="Validation error")
 * )
 * 
 * @OA\Post(
 *     path="/auth/logout",
 *     summary="Logout and revoke token",
 *     tags={"Authentication"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(response=200, description="Logged out successfully"),
 *     @OA\Response(response=401, description="Unauthenticated")
 * )
 * 
 * @OA\Get(
 *     path="/auth/user",
 *     summary="Get authenticated user profile",
 *     tags={"Authentication"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="User profile retrieved",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="id", type="integer"),
 *                 @OA\Property(property="username", type="string"),
 *                 @OA\Property(property="email", type="string"),
 *                 @OA\Property(property="business_id", type="integer"),
 *                 @OA\Property(property="roles", type="array", @OA\Items(type="string"))
 *             )
 *         )
 *     ),
 *     @OA\Response(response=401, description="Unauthenticated")
 * )
 */
class AuthController extends BaseApiController
{
    /**
     * Login and return access token.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function login(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'username' => 'required|string',
                'password' => 'required|string',
            ]);

            // Find user by username or email
            $user = User::where('username', $request->username)
                ->orWhere('email', $request->username)
                ->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return $this->errorResponse('Invalid credentials', 401);
            }

            // Check if user is active
            if ($user->status !== 'active') {
                return $this->errorResponse('Your account is not active. Please contact administrator.', 403);
            }

            // Create personal access token
            $token = $user->createToken('mobile-app')->accessToken;

            return $this->successResponse([
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'business_id' => $user->business_id,
                    'business_name' => $user->business->name ?? null,
                ],
            ], 'Login successful');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Login failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Logout and revoke current token.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $request->user()->token()->revoke();
            return $this->successResponse(null, 'Logged out successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Logout failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get authenticated user profile.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function user(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $user->load('business');

            return $this->successResponse([
                'id' => $user->id,
                'username' => $user->username,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'status' => $user->status,
                'business_id' => $user->business_id,
                'business' => $user->business ? [
                    'id' => $user->business->id,
                    'name' => $user->business->name,
                    'currency' => $user->business->currency_id,
                ] : null,
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ], 'User profile retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve user profile: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Refresh access token.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function refresh(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Revoke current token
            $user->token()->revoke();

            // Create new token
            $token = $user->createToken('mobile-app')->accessToken;

            return $this->successResponse([
                'access_token' => $token,
                'token_type' => 'Bearer',
            ], 'Token refreshed successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Token refresh failed: ' . $e->getMessage(), 500);
        }
    }
}
