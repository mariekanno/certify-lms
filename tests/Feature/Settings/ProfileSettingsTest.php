<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_profile_settings(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('settings.profile.show'));

        $response->assertOk();
        $response->assertViewIs('settings.profile');
        $response->assertViewHas('user', $user);
    }

    public function test_user_can_update_name_and_bio(): void
    {
        $user = User::factory()->create([
            'name' => '変更前',
            'bio' => null,
        ]);

        $response = $this
            ->actingAs($user)
            ->patch(route('settings.profile.update'), [
                'name' => '変更後',
                'bio' => '自己紹介を更新しました。',
            ]);

        $response
            ->assertRedirect(route('settings.profile.show'))
            ->assertSessionHas('success', 'プロフィールを更新しました。');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => '変更後',
            'bio' => '自己紹介を更新しました。',
        ]);
    }

    public function test_email_cannot_be_updated_from_profile(): void
    {
        $user = User::factory()->create([
            'email' => 'before@example.com',
        ]);

        $this
            ->actingAs($user)
            ->patch(route('settings.profile.update'), [
                'name' => $user->name,
                'bio' => $user->bio,
                'email' => 'after@example.com',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'before@example.com',
        ]);
    }

    public function test_coach_can_update_meeting_url(): void
    {
        $coach = User::factory()->create([
            'role' => UserRole::Coach,
            'meeting_url' => null,
        ]);

        $response = $this
            ->actingAs($coach)
            ->patch(route('settings.profile.update'), [
                'name' => $coach->name,
                'bio' => $coach->bio,
                'meeting_url' => 'https://meet.google.com/abc-defg-hij',
            ]);

        $response->assertRedirect(route('settings.profile.show'));

        $this->assertDatabaseHas('users', [
            'id' => $coach->id,
            'meeting_url' => 'https://meet.google.com/abc-defg-hij',
        ]);
    }

    public function test_non_coach_cannot_update_meeting_url(): void
    {
        $student = User::factory()->create([
            'meeting_url' => null,
        ]);

        $response = $this
            ->actingAs($student)
            ->patch(route('settings.profile.update'), [
                'name' => $student->name,
                'bio' => $student->bio,
                'meeting_url' => 'https://example.com/meeting',
            ]);

        $response->assertSessionHasErrors('meeting_url');

        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'meeting_url' => null,
        ]);
    }

    public function test_user_can_upload_avatar(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'avatar_url' => null,
        ]);

        $file = UploadedFile::fake()->image('avatar.jpg');

        $response = $this
            ->actingAs($user)
            ->post(route('settings.avatar.store'), [
                'avatar' => $file,
            ]);

        $response
            ->assertRedirect(route('settings.profile.show'))
            ->assertSessionHas('success', 'アイコン画像を更新しました。');

        $user->refresh();

        $this->assertNotNull($user->avatar_url);

        $path = str_replace('/storage/', '', $user->avatar_url);

        Storage::disk('public')->assertExists($path);
    }

    public function test_avatar_must_be_allowed_image_type(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $file = UploadedFile::fake()->create(
            'avatar.gif',
            100,
            'image/gif',
        );

        $response = $this
            ->actingAs($user)
            ->post(route('settings.avatar.store'), [
                'avatar' => $file,
            ]);

        $response->assertSessionHasErrors('avatar');
    }

    public function test_user_can_delete_avatar(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put(
            'avatars/test.jpg',
            'dummy',
        );

        $user = User::factory()->create([
            'avatar_url' => '/storage/avatars/test.jpg',
        ]);

        $response = $this
            ->actingAs($user)
            ->delete(route('settings.avatar.destroy'));

        $response
            ->assertRedirect(route('settings.profile.show'))
            ->assertSessionHas('success', 'アイコン画像を削除しました。');

        $user->refresh();

        $this->assertNull($user->avatar_url);

        Storage::disk('public')->assertMissing(
            'avatars/test.jpg',
        );
    }

    public function test_user_can_update_password_with_correct_current_password(): void
    {
        $user = User::factory()->create([
            'password' => 'password123',
        ]);

        $response = $this
            ->actingAs($user)
            ->put(route('settings.password.update'), [
                'current_password' => 'password123',
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ]);

        $response
            ->assertRedirect(
                route('settings.profile.show', ['tab' => 'password']),
            )
            ->assertSessionHas('success', 'パスワードを変更しました。');

        $this->assertTrue(
            password_verify(
                'newpassword123',
                $user->fresh()->password,
            ),
        );
    }

    public function test_password_update_fails_when_current_password_is_wrong(): void
    {
        $user = User::factory()->create([
            'password' => 'password123',
        ]);

        $response = $this
            ->actingAs($user)
            ->put(route('settings.password.update'), [
                'current_password' => 'wrongpassword',
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ]);

        $response->assertSessionHasErrors(
            'current_password',
            errorBag: 'updatePassword',
        );
    }

    public function test_guest_cannot_access_profile_settings(): void
    {
        $response = $this->get(route('settings.profile.show'));

        $response->assertRedirect('/login');
    }
}