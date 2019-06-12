<?php

namespace App;

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
 *     @OA\Property(property="links", type="object",
 *         @OA\Schema(
 *         schema="links",
 *         required={"first","last"},
 *         @OA\Property(property="first", type="string"),
 *         @OA\Property(property="last", type="string"),
 *         @OA\Property(property="prev", type="string"),
 *         @OA\Property(property="next", type="string")
 *         )
 *     ),
 *     @OA\Property(property="meta", type="object",
 *         schema="meta",
 *         @OA\Property(property="current_page", type="integer"),
 *         @OA\Property(property="from", type="integer"),
 *         @OA\Property(property="to", type="integer"),
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
