<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Central\PermissionName;
use App\Enums\Central\UserRole;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class ProfileManagementTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function testUserCanUploadReplaceAndRemoveAvatarAndChangePassword(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'name' => 'Profissional Teste',
            'email' => 'pro.perfil@clinica.com',
            'password' => 'oldpass123',
        ]);
        $user->syncRoles([UserRole::PROFESSIONAL->value]);
        Sanctum::actingAs($user);

        // 1. Upload initial avatar
        $firstAvatar = UploadedFile::fake()->image('avatar1.jpg', 120, 120);
        $this->post('/api/auth/profile', [
            'avatar' => $firstAvatar,
        ], ['Accept' => 'application/json'])
            ->assertOk();

        $user->refresh();
        $firstPath = (string) $user->avatar_path;
        $this->assertNotEmpty($firstPath);
        Storage::disk('public')->assertExists($firstPath);

        // 2. Replace avatar -> deletes old file from storage
        $secondAvatar = UploadedFile::fake()->image('avatar2.png', 120, 120);
        $this->post('/api/auth/profile', [
            'avatar' => $secondAvatar,
        ], ['Accept' => 'application/json'])
            ->assertOk();

        $user->refresh();
        $secondPath = (string) $user->avatar_path;
        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($secondPath);

        // 3. Remove avatar -> deletes from storage
        $this->post('/api/auth/profile', [
            'remove_avatar' => true,
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.avatar_url', null);

        $user->refresh();
        $this->assertNull($user->avatar_path);
        Storage::disk('public')->assertMissing($secondPath);

        // 4. Cannot change name/email without profile.identity.update permission (403)
        $this->postJson('/api/auth/profile', [
            'name' => 'Nome Alterado Sem Permissão',
        ])->assertStatus(403);

        // 5. Grant profile.identity.update permission -> allowed (200)
        $user->givePermissionTo(PermissionName::PROFILE_IDENTITY_UPDATE->value);
        $this->postJson('/api/auth/profile', [
            'name' => 'Nome Autorizado',
            'email' => 'pro.novo@clinica.com',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Nome Autorizado')
            ->assertJsonPath('data.email', 'pro.novo@clinica.com');

        // 6. Change password rejects wrong current_password (422) and accepts correct one (200)
        $this->putJson('/api/auth/profile/password', [
            'current_password' => 'wrongpass',
            'password' => 'newpass123',
            'password_confirmation' => 'newpass123',
        ])->assertStatus(422);

        $this->putJson('/api/auth/profile/password', [
            'current_password' => 'oldpass123',
            'password' => 'newpass123',
            'password_confirmation' => 'newpass123',
        ])->assertOk();

        $user->refresh();
        $this->assertTrue(Hash::check('newpass123', $user->password));
    }
}
