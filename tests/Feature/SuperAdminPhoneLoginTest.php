<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class SuperAdminPhoneLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_authenticate_using_the_phone_number_and_password(): void
    {
        User::withoutGlobalScopes()->create([
            'role' => 'super_admin',
            'name' => 'Carbay+ Super Admin',
            'phone' => '0241786330',
            'email' => 'superadmin@carbayplus.test',
            'password' => '1234',
            'status' => 'active',
        ]);

        $this->assertTrue(Auth::attempt([
            'phone' => '0241786330',
            'password' => '1234',
        ]));
        $this->assertSame('super_admin', Auth::user()->role);
    }
}
