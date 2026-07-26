<?php

namespace Tests\Feature;

use App\Mail\DispatcherCreatedPasswordMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DispatcherCredentialsEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatcher_credentials_are_emailed_but_not_returned_by_the_api(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['user_type' => 'admin']);
        Sanctum::actingAs($admin, ['user']);

        $response = $this->postJson('/api/user/create-dispatcher', [
            'name' => 'Test Dispatcher',
            'email' => 'dispatcher@example.com',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.email', 'dispatcher@example.com')
            ->assertJsonMissingPath('generated_password');

        $dispatcher = User::where('email', 'dispatcher@example.com')->firstOrFail();
        $this->assertSame('dispatcher', $dispatcher->user_type);

        Mail::assertSent(DispatcherCreatedPasswordMail::class, function (DispatcherCreatedPasswordMail $mail) use ($dispatcher): bool {
            return $mail->hasTo('dispatcher@example.com')
                && $mail->dispatcher->is($dispatcher)
                && Hash::check($mail->generatedPassword, $dispatcher->password);
        });
    }
}
