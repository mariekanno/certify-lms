<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Learning;

use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\Part;
use App\Models\Section;
use App\Models\SectionProgress;
use App\Models\User;
use App\Services\Learning\LearningProgressService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearningProgressServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_summarize_returns_section_chapter_and_part_progress(): void
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

        $section1 = Section::factory()->published()->forChapter($chapter1)->create();
        $section2 = Section::factory()->published()->forChapter($chapter1)->create();
        $section3 = Section::factory()->published()->forChapter($chapter2)->create();

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

        $summary = app(LearningProgressService::class)->summarize($enrollment);

        $this->assertSame(4, $summary->sectionsTotal);
        $this->assertSame(3, $summary->sectionsCompleted);
        $this->assertSame(0.75, $summary->sectionCompletionRatio);

        $this->assertSame(2, $summary->chaptersTotal);
        $this->assertSame(1, $summary->chaptersCompleted);
        $this->assertSame(0.5, $summary->chapterCompletionRatio);

        $this->assertSame(1, $summary->partsTotal);
        $this->assertSame(0, $summary->partsCompleted);
        $this->assertSame(0.0, $summary->partCompletionRatio);

        $this->assertSame(0.75, $summary->overallCompletionRatio);
    }

    public function test_batch_section_completion_ratios_returns_ratios_for_each_enrollment(): void
    {
        $student = User::factory()->student()->create();

        $certification1 = Certification::factory()->published()->create();
        $certification2 = Certification::factory()->published()->create();

        $enrollment1 = Enrollment::factory()
            ->for($student, 'user')
            ->for($certification1)
            ->learning()
            ->create();

        $enrollment2 = Enrollment::factory()
            ->for($student, 'user')
            ->for($certification2)
            ->learning()
            ->create();

        $part1 = Part::factory()->published()->forCertification($certification1)->create();
        $chapter1 = Chapter::factory()->published()->forPart($part1)->create();

        $section1 = Section::factory()->published()->forChapter($chapter1)->create();
        Section::factory()->published()->forChapter($chapter1)->create();

        SectionProgress::factory()
            ->forEnrollment($enrollment1)
            ->forSection($section1)
            ->completedNow()
            ->create();

        $part2 = Part::factory()->published()->forCertification($certification2)->create();
        $chapter2 = Chapter::factory()->published()->forPart($part2)->create();

        Section::factory()->published()->forChapter($chapter2)->create();

        $enrollments = new Collection([$enrollment1, $enrollment2]);

        $ratios = app(LearningProgressService::class)
            ->batchSectionCompletionRatios($enrollments);

        $this->assertSame(0.5, $ratios[$enrollment1->id]);
        $this->assertSame(0.0, $ratios[$enrollment2->id]);
    }

    public function test_batch_section_completion_ratios_returns_empty_array_for_empty_collection(): void
    {
        $ratios = app(LearningProgressService::class)
            ->batchSectionCompletionRatios(new Collection);

        $this->assertSame([], $ratios);
    }
}
