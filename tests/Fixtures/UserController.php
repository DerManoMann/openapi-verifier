<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Verifier\Tests\Fixtures;

use OpenApi\Attributes as OAT;

class UserController
{
    #[OAT\Get(
        path: '/users',
        operationId: 'users.index',
        summary: 'Get all users',
        security: [['JWT' => []]],
        parameters: [
            new OAT\QueryParameter(
                name: 'perPage',
                description: 'Number of results',
                required: false,
                schema: new OAT\Schema(type: 'integer', default: 6)
            ),
            new OAT\QueryParameter(
                name: 'page',
                description: 'Page number',
                required: false,
                schema: new OAT\Schema(type: 'integer', default: 1)
            ),
        ],
        responses: [
            new OAT\Response(
                response: 200,
                description: 'Users',
                content: new OAT\JsonContent(
                    allOf: [
                        new OAT\Schema(ref: '#components/schemas/paginate'),
                        new OAT\Schema(
                            required: ['data'],
                            properties: [
                                new OAT\Property(
                                    property: 'data',
                                    type: 'array',
                                    items: new OAT\Items(ref: '#components/schemas/user')
                                ),
                            ],
                        ),
                    ],
                )
            ),
            new OAT\Response(
                response: 401,
                ref: '#components/responses/401'
            ),
        ],
    )]
    public function index()
    {
        return '{"data":[{"id":1,"name":"joe","email":"joe@cool.com"}]';
    }

    #[OAT\Get(
        path: '/user/{id}',
        operationId: 'users.user',
        summary: 'Get user for id',
        security: [['JWT' => []]],
        parameters: [
            new OAT\PathParameter(
                name: 'id',
                description: 'User id',
                schema: new OAT\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OAT\Response(
                response: 200,
                description: 'User',
                content: new OAT\JsonContent(
                    required: ['data'],
                    properties: [
                        new OAT\Property(
                            property: 'data',
                            ref: User::class
                        ),
                    ],
                ),
            ),
            new OAT\Response(
                response: 401,
                ref: '#components/responses/401'
            ),
        ],
    )]
    public function user(int $id)
    {
        return '{"data":{"id":1,"name":"joe","email":"joe@cool.com"}';
    }
}
