<?php

namespace Radebatz\OpenApi\Verifier\Tests\Api;

use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="user",
 *     required={"id", "name", "email"},
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="email", type="string")
 * )
 */
class User
{
}
