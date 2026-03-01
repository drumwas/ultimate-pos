<?php

namespace App\Http\Controllers\Api;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="Ultimate POS Mobile Admin API",
 *     description="API endpoints for mobile admin oversight of POS operations. Provides read-only access to dashboard, reports, transactions, products, and contacts.",
 *     @OA\Contact(
 *         email="support@ultimatepos.com",
 *         name="API Support"
 *     ),
 *     @OA\License(
 *         name="Proprietary",
 *         url="https://ultimatepos.com/license"
 *     )
 * )
 * 
 * @OA\Server(
 *     url="/api",
 *     description="API Server"
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Enter your Bearer token in the format: Bearer {token}"
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="apiKey",
 *     type="apiKey",
 *     in="header",
 *     name="X-Api-Key",
 *     description="API client key for rate limiting and analytics"
 * )
 * 
 * @OA\Tag(
 *     name="Authentication",
 *     description="Login, logout, and user profile endpoints"
 * )
 * 
 * @OA\Tag(
 *     name="Dashboard",
 *     description="Dashboard summary and quick stats"
 * )
 * 
 * @OA\Tag(
 *     name="Reports",
 *     description="Business reports and analytics"
 * )
 * 
 * @OA\Tag(
 *     name="Transactions",
 *     description="Sales and purchase transactions"
 * )
 * 
 * @OA\Tag(
 *     name="Products",
 *     description="Product catalog and inventory"
 * )
 * 
 * @OA\Tag(
 *     name="Contacts",
 *     description="Customers and suppliers"
 * )
 * 
 * @OA\Tag(
 *     name="API Clients",
 *     description="API client management for multi-client support"
 * )
 * 
 * @OA\Schema(
 *     schema="SuccessResponse",
 *     type="object",
 *     @OA\Property(property="success", type="boolean", example=true),
 *     @OA\Property(property="message", type="string", example="Operation successful"),
 *     @OA\Property(property="data", type="object")
 * )
 * 
 * @OA\Schema(
 *     schema="ErrorResponse",
 *     type="object",
 *     @OA\Property(property="success", type="boolean", example=false),
 *     @OA\Property(property="message", type="string", example="Error message"),
 *     @OA\Property(property="errors", type="object", nullable=true)
 * )
 * 
 * @OA\Schema(
 *     schema="PaginationMeta",
 *     type="object",
 *     @OA\Property(property="current_page", type="integer", example=1),
 *     @OA\Property(property="last_page", type="integer", example=10),
 *     @OA\Property(property="per_page", type="integer", example=20),
 *     @OA\Property(property="total", type="integer", example=200)
 * )
 */
class SwaggerAnnotations
{
    // This file contains only OpenAPI annotations for Swagger documentation.
    // It is not a functional controller.
}
