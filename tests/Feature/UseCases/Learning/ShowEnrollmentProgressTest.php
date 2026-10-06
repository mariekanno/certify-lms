<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Learning;

use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\Part;
use App\Models\Section;
use App\Models\SectionProgress;
use App\Models\User;
use App\Services\Learning\ProgressSummary;
use App\UseCases\Learning\ShowEnrollmentAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowEnrollmentProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_progress_summary_keeps_current_calculation_rules(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->for($certification)
            ->learning()
            ->create();

        $part = Part::factory()
            ->published()
            ->forCertification($certification)
            ->create();

        $chapter1 = Chapter::factory()
            ->published()
            ->forPart($part)
            ->create();

        $chapter2 = Chapter::factory()
            ->published()
            ->forPart($part)
            ->create();

        $section1 = Section::factory()
            ->published()
            ->forChapter($chapter1)
            ->create();

        $section2 = Section::factory()
            ->published()
            ->forChapter($chapter1)
            ->create();

        $section3 = Section::factory()
            ->published()
            ->forChapter($chapter2)
            ->create();

        Section::factory()
            ->published()
            ->forChapter($chapter2)
            ->create();

        foreach ([$section1, $section2, $section3] as $section) {
            SectionProgress::factory()
                ->forEnrollment($enrollment)
                ->forSection($section)
                ->completedNow()
                ->create();
        }

        $result = app(ShowEnrollmentAction::class)($enrollment);

        /** @var ProgressSummary $progress */
        $progress = $result['progress'];

        $this->assertSame(4, $progress->sectionsTotal);
        $this->assertSame(3, $progress->sectionsCompleted);
        $this->assertSame(0.75, $progress->sectionCompletionRatio);

        $this->assertSame(2, $progress->chaptersTotal);
        $this->assertSame(1, $progress->chaptersCompleted);
        $this->assertSame(0.5, $progress->chapterCompletionRatio);

        $this->assertSame(1, $progress->partsTotal);
        $this->assertSame(0, $progress->partsCompleted);
        $this->assertSame(0.0, $progress->partCompletionRatio);

        $this->assertSame(0.75, $progress->overallCompletionRatio);
    }
}
