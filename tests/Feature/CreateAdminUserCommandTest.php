<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminUserCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_admin_from_command_arguments(): void
    {
        $this->artisan('user:create-admin', [
            'name' => 'Console Admin',
            'email' => 'console.admin@example.com',
            '--password' => 'password123',
        ])->assertSuccessful();

        $admin = User::where('email', 'console.admin@example.com')->firstOrFail();

        $this->assertSame('Console Admin', $admin->name);
        $this->assertSame('admin', $admin->user_type);
        $this->assertTrue(Hash::check('password123', $admin->password));
    }

    public function test_it_rejects_a_duplicate_admin_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->artisan('user:create-admin', [
            'name' => 'Duplicate Admin',
            'email' => 'taken@example.com',
            '--password' => 'password123',
        ])->assertFailed();

        $this->assertSame(1, User::where('email', 'taken@example.com')->count());
    }
}
