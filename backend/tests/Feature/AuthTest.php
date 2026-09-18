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
            ->assertJsonValidationErrors(['email']);
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
            ->assertJsonValidationErrors(['email']);
    }
}
