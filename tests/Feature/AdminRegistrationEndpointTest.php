<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminRegistrationEndpointTest extends TestCase
{
    public function test_the_public_admin_registration_endpoint_is_not_available(): void
    {
        $this->postJson('/api/user/register', [
            'name' => 'Unauthorized Admin',
            'email' => 'unauthorized@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertNotFound();
    }
}
