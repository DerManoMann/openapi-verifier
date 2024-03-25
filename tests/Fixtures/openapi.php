<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Verifier\Tests\Fixtures;

use OpenApi\Attributes as OAT;

#[OAT\Info(
    title: 'API',
    version: '1.0'
)]
#[OAT\SecurityScheme(
    type: 'apiKey',
    in: 'header',
    securityScheme: 'JWT',
    name: 'Authorization',
    description: 'Bearer JWT Token'
)]
#[OAT\Schema(
    schema: 'paginate',
    properties: [
        new OAT\Property(
            property: 'links',
            type: 'object',
            schema: 'links',
            properties: [
                new OAT\Property(property: 'first', type: 'string'),
                new OAT\Property(property: 'last', type: 'string', nullable: true),
                new OAT\Property(property: 'prev', type: 'string', nullable: true),
                new OAT\Property(property: 'next', type: 'string', nullable: true),
            ]
        ),
        new OAT\Property(
            property: 'meta',
            type: 'object',
            schema: 'meta',
            properties: [
                new OAT\Property(property: 'current_page', type: 'integer'),
                new OAT\Property(property: 'from', type: 'integer', nullable: true),
                new OAT\Property(property: 'to', type: 'integer', nullable: true),
                new OAT\Property(property: 'per_page', type: 'integer'),
                new OAT\Property(property: 'path', type: 'string'),
            ]
        ),
    ],
)]
#[OAT\Response(
    response: 200,
    description: 'Success'
)]
#[OAT\Response(
    response: 401,
    description: 'Unauthorized'
)]
class openapi
{
}
