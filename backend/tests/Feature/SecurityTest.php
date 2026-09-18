<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_customer_bloqueado_en_rutas_admin_con_403()
    {
        $customer = User::create([
            'name' => 'Cliente Normal',
            'email' => 'cliente@test.com',
            'password' => bcrypt('password123'),
            'role' => 'customer',
        ]);

        $token = $customer->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/stats');

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Unauthorized. Admin access required.',
            ]);
    }

    public function test_usuario_admin_autorizado_en_rutas_admin()
    {
        $admin = User::create([
            'name' => 'Administrador',
            'email' => 'admin@test.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $token = $admin->createToken('admin-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/stats');

        $response->assertStatus(200);
    }

    public function test_logout_revoca_token_efectivamente_en_base_de_datos()
    {
        $user = User::create([
            'name' => 'Usuario Logout',
            'email' => 'logout@test.com',
            'password' => bcrypt('password123'),
            'role' => 'customer',
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/logout');

        $response->assertStatus(200)
            ->assertJson(['message' => 'Logged out successfully']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_peticion_sin_token_a_ruta_protegida_responde_401()
    {
        $response = $this->getJson('/api/auth/user');

        $response->assertStatus(401);
    }
}
