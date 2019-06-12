<?php


namespace Radebatz\OpenApi\Verifier\Tests\Api;

use OpenApi\Annotations as OA;

class UserController
{
    /**
     * @OA\Get(
     *     path="/users",
     *     operationId="users.index",
     *     summary="Get all users",
     *     security={{"JWT":{}}},
     *
     *     @OA\Parameter(
     *         name="perPage",
     *         in="query",
     *         description="Number of results",
     *         required=false,
     *         @OA\Schema(
     *             type="integer",
     *             default=6,
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(
     *             type="integer",
     *             default=1,
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response="200",
     *         description="Users",
     *         @OA\JsonContent(allOf={
     *             @OA\Schema(ref="#components/schemas/paginate"),
     *             @OA\Schema(
     *                 required={"data"},
     *                 @OA\Property(property="data", type="array", @OA\Items(ref="#components/schemas/user"))
     *             )
     *         })
     *     ),
     *     @OA\Response(response="401", ref="#components/responses/401")
     * )
     */
    public function index()
    {
    }
}
