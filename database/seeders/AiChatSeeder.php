<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Enums\EnrollmentStatus;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Seeder;

final class AiChatSeeder extends Seeder
{
    public function run(): void
    {
        $student = User::query()
            ->where('email', 'student@certify-lms.test')
            ->first();

        if ($student === null) {
            $this->command?->warn(
                'AiChatSeeder: student@certify-lms.test が存在しません。先に UserSeeder を実行してください。'
            );

            return;
        }

        $enrollments = Enrollment::query()
            ->with('certification')
            ->where('user_id', $student->id)
            ->where('status', EnrollmentStatus::Learning->value)
            ->orderBy('created_at')
            ->get();

        if ($enrollments->isEmpty()) {
            $this->command?->warn(
                'AiChatSeeder: 固定受講生に learning の Enrollment がありません。先に EnrollmentSeeder を実行してください。'
            );

            return;
        }

        $primaryEnrollment = $enrollments->first();

        $section = Section::query()
            ->whereHas(
                'chapter.part',
                fn ($query) => $query->where(
                    'certification_id',
                    $primaryEnrollment->certification_id,
                ),
            )
            ->where('title', '1.1 2 進数の表現')
            ->first();

        $this->seedSectionConversation(
            $student,
            $primaryEnrollment,
            $section,
        );

        $this->seedGeneralConversation(
            $student,
            $primaryEnrollment,
        );

        $this->seedErrorConversation(
            $student,
            $primaryEnrollment,
        );
    }

    private function seedSectionConversation(
        User $student,
        Enrollment $enrollment,
        ?Section $section,
    ): void {
        $conversation = AiChatConversation::query()->firstOrCreate(
            [
                'user_id' => $student->id,
                'title' => '2進数の変換を理解したい',
            ],
            [
                'enrollment_id' => $enrollment->id,
                'section_id' => $section?->id,
                'auto_title_enabled' => true,
                'last_message_at' => now()->subHours(2),
            ],
        );

        if ($conversation->messages()->exists()) {
            return;
        }

        AiChatMessage::query()->create([
            'conversation_id' => $conversation->id,
            'role' => AiChatMessageRole::User,
            'status' => AiChatMessageStatus::Completed,
            'content' => '10進数を2進数に変換する考え方をもう少し簡単に説明してください。',
            'error_detail' => null,
            'model' => null,
            'input_tokens' => null,
            'output_tokens' => null,
            'response_time_ms' => null,
            'created_at' => now()->subHours(2),
            'updated_at' => now()->subHours(2),
        ]);

        AiChatMessage::query()->create([
            'conversation_id' => $conversation->id,
            'role' => AiChatMessageRole::Assistant,
            'status' => AiChatMessageStatus::Completed,
            'content' => "10進数を2進数にするときは、「2で割った余り」を順番に記録すると分かりやすいです。\n\n例えば10進数の25なら、2で割り続けた余りを下から読むと `11001` になります。",
            'error_detail' => null,
            'model' => 'gemini-2.5-flash',
            'input_tokens' => 180,
            'output_tokens' => 92,
            'response_time_ms' => 1240,
            'created_at' => now()->subHours(2)->addSeconds(2),
            'updated_at' => now()->subHours(2)->addSeconds(2),
        ]);
    }

    private function seedGeneralConversation(
        User $student,
        Enrollment $enrollment,
    ): void {
        $conversation = AiChatConversation::query()->firstOrCreate(
            [
                'user_id' => $student->id,
                'title' => '試験勉強の進め方',
            ],
            [
                'enrollment_id' => $enrollment->id,
                'section_id' => null,
                'auto_title_enabled' => false,
                'last_message_at' => now()->subDays(3),
            ],
        );

        if ($conversation->messages()->exists()) {
            return;
        }

        AiChatMessage::query()->create([
            'conversation_id' => $conversation->id,
            'role' => AiChatMessageRole::User,
            'status' => AiChatMessageStatus::Completed,
            'content' => '試験まで2か月あります。どんな順番で勉強するといいですか？',
            'error_detail' => null,
            'model' => null,
            'input_tokens' => null,
            'output_tokens' => null,
            'response_time_ms' => null,
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ]);

        AiChatMessage::query()->create([
            'conversation_id' => $conversation->id,
            'role' => AiChatMessageRole::Assistant,
            'status' => AiChatMessageStatus::Completed,
            'content' => "まず教材を一周して全体像をつかみ、その後に演習問題で苦手分野を確認する進め方がおすすめです。\n\n最後の数週間は模擬試験を使って、時間配分と弱点の復習に重点を置くとよいでしょう。",
            'error_detail' => null,
            'model' => 'gemini-2.5-flash',
            'input_tokens' => 145,
            'output_tokens' => 104,
            'response_time_ms' => 980,
            'created_at' => now()->subDays(3)->addSeconds(1),
            'updated_at' => now()->subDays(3)->addSeconds(1),
        ]);
    }

    private function seedErrorConversation(
        User $student,
        Enrollment $enrollment,
    ): void {
        $conversation = AiChatConversation::query()->firstOrCreate(
            [
                'user_id' => $student->id,
                'title' => '苦手分野について相談',
            ],
            [
                'enrollment_id' => $enrollment->id,
                'section_id' => null,
                'auto_title_enabled' => true,
                'last_message_at' => now()->subDays(10),
            ],
        );

        if ($conversation->messages()->exists()) {
            return;
        }

        AiChatMessage::query()->create([
            'conversation_id' => $conversation->id,
            'role' => AiChatMessageRole::User,
            'status' => AiChatMessageStatus::Completed,
            'content' => 'アルゴリズム問題が苦手です。勉強方法を教えてください。',
            'error_detail' => null,
            'model' => null,
            'input_tokens' => null,
            'output_tokens' => null,
            'response_time_ms' => null,
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);

        AiChatMessage::query()->create([
            'conversation_id' => $conversation->id,
            'role' => AiChatMessageRole::Assistant,
            'status' => AiChatMessageStatus::Error,
            'content' => '',
            'error_detail' => '503 Gemini API temporarily unavailable',
            'model' => 'gemini-2.5-flash',
            'input_tokens' => null,
            'output_tokens' => null,
            'response_time_ms' => 850,
            'created_at' => now()->subDays(10)->addSecond(),
            'updated_at' => now()->subDays(10)->addSecond(),
        ]);
    }
}
