<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_notification_api(): void
    {
        $response = $this->getJson('/api/v1/notifications');

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_can_only_fetch_own_notifications(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $ownNotification = $this->createNotification(
            $user,
            title: '自分宛の通知',
        );

        $this->createNotification(
            $otherUser,
            title: '他人宛の通知',
        );

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/notifications');

        $response
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownNotification->id)
            ->assertJsonPath('data.0.title', '自分宛の通知')
            ->assertJsonPath('data.0.message', 'テストメッセージ')
            ->assertJsonPath('data.0.is_unread', true);
    }

    public function test_notification_api_returns_latest_20_notifications(): void
    {
        $user = User::factory()->create();

        $oldest = $this->createNotification(
            $user,
            title: '最古の通知',
            createdAt: now()->subMinutes(21)->toDateTimeString(),
        );

        for ($i = 20; $i >= 1; $i--) {
            $this->createNotification(
                $user,
                title: "通知{$i}",
                createdAt: now()->subMinutes($i)->toDateTimeString(),
            );
        }

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/notifications');

        $response
            ->assertOk()
            ->assertJsonCount(20, 'data');

        $ids = collect($response->json('data'))->pluck('id');

        $this->assertFalse($ids->contains($oldest->id));
    }

    public function test_authenticated_user_can_mark_own_notification_as_read(): void
    {
        $user = User::factory()->create();

        $notification = $this->createNotification(
            $user,
            title: '既読にする通知',
        );

        $response = $this
            ->actingAs($user)
            ->postJson(
                "/api/v1/notifications/{$notification->id}/read",
            );

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $notification->id)
            ->assertJsonPath('data.is_unread', false)
            ->assertJsonPath('unread_count', 0);

        $this->assertNotNull(
            $notification->fresh()->read_at,
        );
    }

    public function test_authenticated_user_cannot_mark_another_users_notification_as_read(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $notification = $this->createNotification(
            $otherUser,
            title: '他人宛の通知',
        );

        $response = $this
            ->actingAs($user)
            ->postJson(
                "/api/v1/notifications/{$notification->id}/read",
            );

        $response->assertForbidden();

        $this->assertNull(
            $notification->fresh()->read_at,
        );
    }

    public function test_authenticated_user_can_mark_all_own_notifications_as_read(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $first = $this->createNotification(
            $user,
            title: '通知1',
        );

        $second = $this->createNotification(
            $user,
            title: '通知2',
        );

        $otherNotification = $this->createNotification(
            $otherUser,
            title: '他人の通知',
        );

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/notifications/read-all');

        $response
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->assertNotNull($first->fresh()->read_at);
        $this->assertNotNull($second->fresh()->read_at);

        $this->assertNull(
            $otherNotification->fresh()->read_at,
        );
    }

    private function createNotification(
        User $user,
        string $title,
        ?string $readAt = null,
        ?string $url = '/dashboard',
        ?string $createdAt = null,
    ): DatabaseNotification {
        $id = (string) Str::uuid();

        DB::table('notifications')->insert([
            'id' => $id,
            'type' => 'test_notification',
            'notifiable_type' => $user::class,
            'notifiable_id' => $user->id,
            'data' => json_encode([
                'title' => $title,
                'message' => 'テストメッセージ',
                'url' => $url,
                'notification_type' => 'test_notification',
            ], JSON_THROW_ON_ERROR),
            'read_at' => $readAt,
            'created_at' => $createdAt ?? now(),
            'updated_at' => $createdAt ?? now(),
        ]);

        return DatabaseNotification::query()
            ->findOrFail($id);
    }
}
