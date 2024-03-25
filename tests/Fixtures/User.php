<?php declare(strict_types=1);

namespace Radebatz\OpenApi\Verifier\Tests\Fixtures;

use OpenApi\Attributes as OAT;

#[OAT\Schema(
    schema: 'user',
    required: ['id', 'name', 'email'],
    properties: [
        new OAT\Property(property: 'id', type: 'integer'),
        new OAT\Property(property: 'name', type: 'string'),
        new OAT\Property(property: 'email', type: 'string'),
        new OAT\Property(property: 'dob', type: 'string', nullable: true),
    ]
)]
class User
{
}
