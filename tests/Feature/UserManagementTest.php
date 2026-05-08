<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_only_sees_own_cashiers_in_user_management(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $otherAdmin = User::factory()->create(['role' => 'admin']);
        $ownCashier = User::factory()->create([
            'role' => 'cashier',
            'created_by' => $admin->id,
        ]);
        $otherCashier = User::factory()->create([
            'role' => 'cashier',
            'created_by' => $otherAdmin->id,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/users');

        $response->assertOk()
            ->assertJsonPath('data.data.0.id', $ownCashier->id)
            ->assertJsonMissing(['id' => $admin->id])
            ->assertJsonMissing(['id' => $otherAdmin->id])
            ->assertJsonMissing(['id' => $otherCashier->id]);
    }

    public function test_admin_can_only_create_cashier_accounts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Sanctum::actingAs($admin);

        $this->postJson('/api/users', [
            'name' => 'Admin Baru',
            'email' => 'admin-baru@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin',
        ])->assertUnprocessable();

        $response = $this->postJson('/api/users', [
            'name' => 'Kasir Baru',
            'email' => 'kasir-baru@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.role', 'cashier')
            ->assertJsonPath('data.created_by', $admin->id);

        $this->assertDatabaseHas('users', [
            'email' => 'kasir-baru@example.com',
            'role' => 'cashier',
            'created_by' => $admin->id,
        ]);

        $this->assertDatabaseMissing('users', [
            'email' => 'admin-baru@example.com',
            'role' => 'admin',
        ]);
    }

    public function test_admin_cannot_manage_cashier_from_another_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $otherAdmin = User::factory()->create(['role' => 'admin']);
        $otherCashier = User::factory()->create([
            'role' => 'cashier',
            'created_by' => $otherAdmin->id,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson("/api/users/{$otherCashier->id}")
            ->assertNotFound();

        $this->patchJson("/api/users/{$otherCashier->id}", [
            'name' => 'Nama Diubah',
        ])->assertNotFound();

        $this->patchJson("/api/users/{$otherCashier->id}/reset-password", [
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertNotFound();

        $this->deleteJson("/api/users/{$otherCashier->id}")
            ->assertNotFound();
    }
}
