<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminViewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_views_use_the_company_name_for_their_branding(): void
    {
        $owner = User::factory()->create();

        Company::create([
            'name' => 'Northstar Transport',
            'email' => 'hello@northstar.test',
            'user_id' => $owner->id,
        ]);

        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('Northstar Transport Admin');

        $this->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Northstar Transport');
    }
}
