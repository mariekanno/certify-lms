<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\QaThreadStatus;
use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Database\Seeder;

class QaBoardSeeder extends Seeder
{
    public function run(): void
    {
        $student = User::query()
            ->where('email', 'student@certify-lms.test')
            ->firstOrFail();

        $coach = User::query()
            ->where('email', 'coach@certify-lms.test')
            ->firstOrFail();

        $certifications = Certification::query()
            ->published()
            ->orderBy('name')
            ->take(2)
            ->get();

        if ($certifications->isEmpty()) {
            return;
        }

        $certification1 = $certifications->first();
        $certification2 = $certifications->get(1) ?? $certification1;

        // 未解決・回答なし
        QaThread::factory()->create([
            'certification_id' => $certification1->id,
            'user_id' => $student->id,
            'title' => '学習方法について質問があります',
            'body' => 'この資格を学習するときのおすすめの進め方を教えてください。',
            'status' => QaThreadStatus::Unresolved->value,
            'resolved_at' => null,
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ]);

        // 未解決・回答あり
        $threadWithReplies = QaThread::factory()->create([
            'certification_id' => $certification1->id,
            'user_id' => $student->id,
            'title' => '教材の復習方法について',
            'body' => '一度学習した教材はどのように復習するのがよいでしょうか。',
            'status' => QaThreadStatus::Unresolved->value,
            'resolved_at' => null,
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDay(),
        ]);

        QaReply::factory()->create([
            'qa_thread_id' => $threadWithReplies->id,
            'user_id' => $coach->id,
            'body' => 'まず間違えた箇所を中心に復習するのがおすすめです。',
            'created_at' => now()->subDays(2)->addHours(2),
            'updated_at' => now()->subDays(2)->addHours(2),
        ]);

        QaReply::factory()->create([
            'qa_thread_id' => $threadWithReplies->id,
            'user_id' => $student->id,
            'body' => 'ありがとうございます。間違えた箇所から復習してみます。',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        // 解決済み・回答あり
        $resolvedThread = QaThread::factory()->create([
            'certification_id' => $certification2->id,
            'user_id' => $student->id,
            'title' => '模試を受けるタイミングについて',
            'body' => '模試はどのタイミングで受験するのがおすすめですか。',
            'status' => QaThreadStatus::Resolved->value,
            'resolved_at' => now()->subHours(12),
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subHours(12),
        ]);

        QaReply::factory()->create([
            'qa_thread_id' => $resolvedThread->id,
            'user_id' => $coach->id,
            'body' => '教材を一通り終えたあとに受験するのがおすすめです。',
            'created_at' => now()->subDays(4),
            'updated_at' => now()->subDays(4),
        ]);
    }
}
