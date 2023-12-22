<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Verifier\Tests\Fixtures;

/**
 * @OA\Info(
 *     title="API",
 *     version="1.0"
 * )
 *
 * @OA\SecurityScheme(
 *     type="apiKey",
 *     in="header",
 *     securityScheme="JWT",
 *     name="Authorization",
 *     description="Bearer JWT Token"
 * )
 *
 * @OA\Schema(
 *     schema="paginate",
 *
 *     @OA\Property(property="links", type="object",
 *         schema="links",
 *         @OA\Property(property="first", type="string"),
 *         @OA\Property(property="last", type="string", nullable=true),
 *         @OA\Property(property="prev", type="string", nullable=true),
 *         @OA\Property(property="next", type="string", nullable=true)
 *     ),
 *     @OA\Property(property="meta", type="object",
 *         schema="meta",
 *         @OA\Property(property="current_page", type="integer"),
 *         @OA\Property(property="from", type="integer", nullable=true),
 *         @OA\Property(property="to", type="integer", nullable=true),
 *         @OA\Property(property="per_page", type="integer"),
 *         @OA\Property(property="path", type="string")
 *     )
 * )
 *
 * @OA\Response(
 *     response="200",
 *     description="Success",
 * )
 * @OA\Response(
 *     response="401",
 *     description="Unauthorized",
 * )
 */
class openapi
{
}
