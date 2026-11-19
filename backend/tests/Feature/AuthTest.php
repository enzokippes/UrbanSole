<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_registro_exitoso()
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Juan Perez',
            'email' => 'juan@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'role'],
                'token',
                'message',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'juan@test.com',
            'role' => 'customer',
        ]);

        // Verificacion en base de datos de hash bcrypt y nunca texto plano
        $user = User::where('email', 'juan@test.com')->first();
        $this->assertNotEquals('password123', $user->password);
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    public function test_login_exitoso()
    {
        User::create([
            'name' => 'Usuario Test',
            'email' => 'login@test.com',
            'password' => Hash::make('password123'),
            'role' => 'customer',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'login@test.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user',
                'token',
                'message',
            ]);
    }

    public function test_password_menor_a_8_caracteres_falla()
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Usuario Test',
            'email' => 'corto@test.com',
            'password' => 'pass1',
            'password_confirmation' => 'pass1',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_confirmacion_de_password_desigual_falla()
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Usuario Test',
            'email' => 'desigual@test.com',
            'password' => 'password123',
            'password_confirmation' => 'diferente123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_email_duplicado_falla()
    {
        User::create([
            'name' => 'Existente',
            'email' => 'duplicado@test.com',
            'password' => Hash::make('password123'),
            'role' => 'customer',
        ]);

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Otro Usuario',
            'email' => 'duplicado@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email'])
            ->assertJsonPath('errors.email.0', 'Este correo electrónico ya está registrado.');
    }

    public function test_formato_de_email_invalido_falla()
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Usuario Test',
            'email' => 'email-invalido',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email'])
            ->assertJsonPath('errors.email.0', 'Ingresá un correo electrónico válido.');
    }

    public function test_login_credenciales_incorrectas_en_espanol()
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'noexiste@test.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'El correo electrónico o la contraseña son incorrectos.');
    }

    public function test_actualizar_perfil_exitoso()
    {
        $user = User::create([
            'name' => 'Original',
            'email' => 'original@test.com',
            'password' => Hash::make('password123'),
            'role' => 'customer',
        ]);

        $response = $this->actingAs($user)->putJson('/api/auth/profile', [
            'name' => 'Nombre Actualizado',
            'email' => 'original@test.com',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('user.name', 'Nombre Actualizado');
    }

    public function test_cambiar_password_exitoso()
    {
        $user = User::create([
            'name' => 'User Password',
            'email' => 'password@test.com',
            'password' => Hash::make('password123'),
            'role' => 'customer',
        ]);

        $response = $this->actingAs($user)->putJson('/api/auth/password', [
            'current_password' => 'password123',
            'password' => 'nuevopassword123',
            'password_confirmation' => 'nuevopassword123',
        ]);

        $response->assertStatus(200);
        $this->assertTrue(Hash::check('nuevopassword123', $user->fresh()->password));
    }

    public function test_usuario_baneado_no_puede_iniciar_sesion_ni_usar_token()
    {
        $user = User::create([
            'name' => 'Usuario Suspendido',
            'email' => 'baneado@test.com',
            'password' => Hash::make('password123'),
            'role' => 'customer',
            'banned_at' => now(),
            'ban_reason' => 'Actividad sospechosa',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'baneado@test.com',
            'password' => 'password123',
        ])->assertStatus(403)
            ->assertJsonPath('banned', true);

        $token = $user->createToken('test-token')->plainTextToken;
        $this->withToken($token)->getJson('/api/auth/user')
            ->assertStatus(403)
            ->assertJsonPath('banned', true);
    }

    public function test_rate_limits_para_registro_y_login()
    {
        // Máximo 3 registros cada 5 minutos
        for ($i = 1; $i <= 3; $i++) {
            $this->postJson('/api/auth/register', [
                'name' => "Usuario {$i}",
                'email' => "ratelimit{$i}@test.com",
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])->assertCreated();
        }

        $this->postJson('/api/auth/register', [
            'name' => 'Usuario 4',
            'email' => 'ratelimit4@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(429);

        // Máximo 5 intentos de login por minuto
        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/api/auth/login', [
                'email' => 'ratelimit1@test.com',
                'password' => 'wrong-password',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/auth/login', [
            'email' => 'ratelimit1@test.com',
            'password' => 'password123',
        ])->assertStatus(429);
    }
}
