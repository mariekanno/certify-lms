<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Certificate;

use App\Models\Certificate;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class DownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_download_own_certificate(): void
    {
        Storage::fake('private');

        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->passed()
            ->create();

        $certificate = Certificate::factory()
            ->forEnrollment($enrollment)
            ->create();

        Storage::disk('private')->put($certificate->pdf_path, 'dummy pdf');

        $response = $this
            ->actingAs($student)
            ->get(route('certificates.download', $certificate));

        $response->assertOk();
        $response->assertDownload('certificate.pdf');
    }

    public function test_other_student_cannot_download_certificate(): void
    {
        Storage::fake('private');

        $owner = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $enrollment = Enrollment::factory()
            ->for($owner)
            ->for($certification)
            ->passed()
            ->create();

        $certificate = Certificate::factory()
            ->forEnrollment($enrollment)
            ->create();

        Storage::disk('private')->put($certificate->pdf_path, 'dummy pdf');

        $this
            ->actingAs($otherStudent)
            ->get(route('certificates.download', $certificate))
            ->assertForbidden();
    }

    public function test_assigned_coach_can_download_certificate(): void
    {
        Storage::fake('private');

        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $admin = User::factory()->admin()->create();
        $certification = Certification::factory()->published()->create();

        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->passed()
            ->create();

        $certificate = Certificate::factory()
            ->forEnrollment($enrollment)
            ->create();

        Storage::disk('private')->put($certificate->pdf_path, 'dummy pdf');

        $this
            ->actingAs($coach)
            ->get(route('certificates.download', $certificate))
            ->assertOk()
            ->assertDownload('certificate.pdf');
    }

    public function test_unassigned_coach_cannot_download_certificate(): void
    {
        Storage::fake('private');

        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();

        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->passed()
            ->create();

        $certificate = Certificate::factory()
            ->forEnrollment($enrollment)
            ->create();

        Storage::disk('private')->put($certificate->pdf_path, 'dummy pdf');

        $this
            ->actingAs($coach)
            ->get(route('certificates.download', $certificate))
            ->assertForbidden();
    }

    public function test_admin_can_download_any_certificate(): void
    {
        Storage::fake('private');

        $student = User::factory()->student()->create();
        $admin = User::factory()->admin()->create();
        $certification = Certification::factory()->published()->create();

        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->passed()
            ->create();

        $certificate = Certificate::factory()
            ->forEnrollment($enrollment)
            ->create();

        Storage::disk('private')->put($certificate->pdf_path, 'dummy pdf');

        $this
            ->actingAs($admin)
            ->get(route('certificates.download', $certificate))
            ->assertOk()
            ->assertDownload('certificate.pdf');
    }

    public function test_returns_404_when_pdf_file_does_not_exist(): void
    {
        Storage::fake('private');

        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->passed()
            ->create();

        $certificate = Certificate::factory()
            ->forEnrollment($enrollment)
            ->create();

        $this
            ->actingAs($student)
            ->get(route('certificates.download', $certificate))
            ->assertNotFound();
    }
}
