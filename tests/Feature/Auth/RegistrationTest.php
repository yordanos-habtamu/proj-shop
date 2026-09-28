<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register()
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertSame('buyer', User::first()->role->value);
    }

    public function test_new_users_can_register_as_seller()
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Seller User',
            'email' => 'seller@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'seller',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::where('email', 'seller@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('seller', $user->role->value);
        $this->assertTrue($user->isSeller());
    }
}
