<?php

use App\Filament\Resources\PastPapers\Schemas\PastPaperForm;
use App\Models\AppSetting;
use App\Models\PastPaper;
use App\Models\User;
use App\Models\UserPastPaperUnlock;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

test('filament past paper form configures file_path and solution_file_path as FileUpload on private local disk with PDF restriction', function () {
    $livewire = new class extends Component implements HasSchemas
    {
        use InteractsWithSchemas;

        public function render()
        {
            return '<div></div>';
        }
    };

    $schema = PastPaperForm::configure(Schema::make($livewire));
    $section = collect($schema->getComponents())->first();
    $components = collect($section->getChildComponents());

    $paperUpload = $components->first(fn ($c) => method_exists($c, 'getName') && $c->getName() === 'file_path');
    $solutionUpload = $components->first(fn ($c) => method_exists($c, 'getName') && $c->getName() === 'solution_file_path');

    expect($paperUpload)->not->toBeNull()
        ->toBeInstanceOf(FileUpload::class);
    expect($solutionUpload)->not->toBeNull()
        ->toBeInstanceOf(FileUpload::class);

    // Confirm local private disk
    expect($paperUpload->getDiskName())->toBe('local');
    expect($solutionUpload->getDiskName())->toBe('local');

    // Confirm private visibility
    expect($paperUpload->getVisibility())->toBe('private');
    expect($solutionUpload->getVisibility())->toBe('private');

    // Confirm accepted file types restrict to PDF
    expect($paperUpload->getAcceptedFileTypes())->toContain('application/pdf');
    expect($solutionUpload->getAcceptedFileTypes())->toContain('application/pdf');

    // Confirm directories
    expect($paperUpload->getDirectory())->toBe('past-papers/questions');
    expect($solutionUpload->getDirectory())->toBe('past-papers/solutions');
});

test('past paper model serialization hides file_path and solution_file_path from json output', function () {
    $pastPaper = PastPaper::factory()->create([
        'file_path' => 'past-papers/questions/confidential_exam.pdf',
        'solution_file_path' => 'past-papers/solutions/confidential_solution.pdf',
    ]);

    $array = $pastPaper->toArray();
    $json = $pastPaper->toJson();

    expect($array)->not->toHaveKey('file_path');
    expect($array)->not->toHaveKey('solution_file_path');
    expect($json)->not->toContain('confidential_exam.pdf');
    expect($json)->not->toContain('confidential_solution.pdf');
});

test('download and view endpoints reject unauthenticated requests with 401', function () {
    $pastPaper = PastPaper::factory()->create();

    $this->getJson("/api/past-papers/{$pastPaper->id}/download-paper")->assertUnauthorized();
    $this->getJson("/api/past-papers/{$pastPaper->id}/download-solution")->assertUnauthorized();
    $this->getJson("/api/past-papers/{$pastPaper->id}/view-paper")->assertUnauthorized();
    $this->getJson("/api/past-papers/{$pastPaper->id}/view-solution")->assertUnauthorized();
    $this->getJson("/api/past-papers/{$pastPaper->id}/access")->assertUnauthorized();
});

test('download and view endpoints reject locked past paper requests with 403', function () {
    $setting = AppSetting::current();
    $setting->free_mode_enabled = false;
    $setting->save();

    Storage::fake('local');
    $dummyPdf = UploadedFile::fake()->create('test.pdf', 100, 'application/pdf');
    $path = Storage::disk('local')->putFile('past-papers/questions', $dummyPdf);

    $student = User::factory()->student()->create(['coin_balance' => 50]);
    $pastPaper = PastPaper::factory()->create([
        'file_path' => $path,
        'coin_price' => 15,
    ]);

    // Student has not unlocked questions paper
    $respDownload = $this->actingAs($student, 'sanctum')->get("/api/past-papers/{$pastPaper->id}/download-paper");
    $respDownload->assertForbidden();

    $respView = $this->actingAs($student, 'sanctum')->get("/api/past-papers/{$pastPaper->id}/view-paper");
    $respView->assertForbidden();
});

test('download and view endpoints reject locked solution requests with 403', function () {
    $setting = AppSetting::current();
    $setting->free_mode_enabled = false;
    $setting->save();

    Storage::fake('local');
    $dummyPdf = UploadedFile::fake()->create('solution.pdf', 100, 'application/pdf');
    $path = Storage::disk('local')->putFile('past-papers/solutions', $dummyPdf);

    $student = User::factory()->student()->create(['coin_balance' => 50]);
    $pastPaper = PastPaper::factory()->create([
        'solution_file_path' => $path,
        'solution_coin_price' => 20,
    ]);

    // Student has not unlocked solution
    $respDownload = $this->actingAs($student, 'sanctum')->get("/api/past-papers/{$pastPaper->id}/download-solution");
    $respDownload->assertForbidden();

    $respView = $this->actingAs($student, 'sanctum')->get("/api/past-papers/{$pastPaper->id}/view-solution");
    $respView->assertForbidden();
});

test('unlocked student can download real binary questions paper with correct headers and non-cacheable control', function () {
    $setting = AppSetting::current();
    $setting->free_mode_enabled = false;
    $setting->save();

    Storage::fake('local');
    $pdfContent = '%PDF-1.4 Fake PDF Content Questions';
    $filePath = 'past-papers/questions/cameroon_gce_math_2024.pdf';
    Storage::disk('local')->put($filePath, $pdfContent);

    $student = User::factory()->student()->create();
    $pastPaper = PastPaper::factory()->create([
        'title' => 'Cameroon GCE Math 2024 Paper 1',
        'file_path' => $filePath,
    ]);

    // Grant unlock
    UserPastPaperUnlock::create([
        'user_id' => $student->id,
        'past_paper_id' => $pastPaper->id,
        'paper_unlocked_at' => now(),
    ]);

    $response = $this->actingAs($student, 'sanctum')->get("/api/past-papers/{$pastPaper->id}/download-paper");

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
    expect($response->headers->get('Content-Disposition'))->toContain('attachment');
    expect($response->headers->get('Content-Disposition'))->toContain('cameroon-gce-math-2024-paper-1');
    expect($response->headers->get('Cache-Control'))->toContain('no-cache');
    expect($response->headers->get('Cache-Control'))->toContain('no-store');

    // Confirm body contains the actual file content
    $streamedOutput = $response->streamedContent();
    expect($streamedOutput)->toBe($pdfContent);
});

test('unlocked student can view questions paper inline', function () {
    $setting = AppSetting::current();
    $setting->free_mode_enabled = false;
    $setting->save();

    Storage::fake('local');
    $pdfContent = '%PDF-1.4 Inline Viewing Content';
    $filePath = 'past-papers/questions/view_inline.pdf';
    Storage::disk('local')->put($filePath, $pdfContent);

    $student = User::factory()->student()->create();
    $pastPaper = PastPaper::factory()->create([
        'title' => 'Biology Paper 2',
        'file_path' => $filePath,
    ]);

    UserPastPaperUnlock::create([
        'user_id' => $student->id,
        'past_paper_id' => $pastPaper->id,
        'paper_unlocked_at' => now(),
    ]);

    // Via /view-paper
    $response = $this->actingAs($student, 'sanctum')->get("/api/past-papers/{$pastPaper->id}/view-paper");
    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))->toContain('inline');
    expect($response->streamedContent())->toBe($pdfContent);

    // Via /download-paper?disposition=inline
    $responseParam = $this->actingAs($student, 'sanctum')->get("/api/past-papers/{$pastPaper->id}/download-paper?disposition=inline");
    $responseParam->assertOk();
    expect($responseParam->headers->get('Content-Disposition'))->toContain('inline');
});

test('unlocked student can download and view real solution file', function () {
    $setting = AppSetting::current();
    $setting->free_mode_enabled = false;
    $setting->save();

    Storage::fake('local');
    $solutionContent = '%PDF-1.4 Worked Solutions Content';
    $filePath = 'past-papers/solutions/chemistry_sol_2023.pdf';
    Storage::disk('local')->put($filePath, $solutionContent);

    $student = User::factory()->student()->create();
    $pastPaper = PastPaper::factory()->create([
        'title' => 'Chemistry Paper 2 Worked Solutions',
        'solution_file_path' => $filePath,
    ]);

    UserPastPaperUnlock::create([
        'user_id' => $student->id,
        'past_paper_id' => $pastPaper->id,
        'solution_unlocked_at' => now(),
    ]);

    $response = $this->actingAs($student, 'sanctum')->get("/api/past-papers/{$pastPaper->id}/download-solution");
    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))->toContain('attachment');
    expect($response->streamedContent())->toBe($solutionContent);

    $viewResponse = $this->actingAs($student, 'sanctum')->get("/api/past-papers/{$pastPaper->id}/view-solution");
    $viewResponse->assertOk();
    expect($viewResponse->headers->get('Content-Disposition'))->toContain('inline');
    expect($viewResponse->streamedContent())->toBe($solutionContent);
});

test('downloading missing file on disk returns 404', function () {
    Storage::fake('local');

    $student = User::factory()->student()->create();
    $pastPaper = PastPaper::factory()->create([
        'file_path' => 'past-papers/questions/non_existent.pdf',
    ]);

    UserPastPaperUnlock::create([
        'user_id' => $student->id,
        'past_paper_id' => $pastPaper->id,
        'paper_unlocked_at' => now(),
    ]);

    $response = $this->actingAs($student, 'sanctum')->get("/api/past-papers/{$pastPaper->id}/download-paper");
    $response->assertNotFound();
});

test('unlocking paper or solution returns download_url and view_url pointing to authenticated endpoints', function () {
    $setting = AppSetting::current();
    $setting->free_mode_enabled = false;
    $setting->save();

    $student = User::factory()->student()->create(['coin_balance' => 100]);
    $pastPaper = PastPaper::factory()->create([
        'coin_price' => 15,
        'solution_coin_price' => 20,
    ]);

    $respPaper = $this->actingAs($student, 'sanctum')->postJson("/api/past-papers/{$pastPaper->id}/unlock-paper");
    $respPaper->assertOk()
        ->assertJson([
            'success' => true,
            'download_url' => url("/api/past-papers/{$pastPaper->id}/download-paper"),
            'view_url' => url("/api/past-papers/{$pastPaper->id}/view-paper"),
        ]);
    // Ensure raw storage path is not exposed in unlock response
    expect($respPaper->json())->not->toHaveKey('file_path');

    $respSolution = $this->actingAs($student, 'sanctum')->postJson("/api/past-papers/{$pastPaper->id}/unlock-solution");
    $respSolution->assertOk()
        ->assertJson([
            'success' => true,
            'download_url' => url("/api/past-papers/{$pastPaper->id}/download-solution"),
            'view_url' => url("/api/past-papers/{$pastPaper->id}/view-solution"),
        ]);
    expect($respSolution->json())->not->toHaveKey('solution_file_path');
});

test('access status endpoint returns accurate authorization and endpoints without leaking raw paths', function () {
    $setting = AppSetting::current();
    $setting->free_mode_enabled = false;
    $setting->save();

    Storage::fake('local');
    $paperFile = 'past-papers/questions/paper_status_test.pdf';
    $solutionFile = 'past-papers/solutions/solution_status_test.pdf';
    Storage::disk('local')->put($paperFile, '%PDF-1.4');
    Storage::disk('local')->put($solutionFile, '%PDF-1.4');

    $student = User::factory()->student()->create();
    $pastPaper = PastPaper::factory()->create([
        'file_path' => $paperFile,
        'solution_file_path' => $solutionFile,
    ]);

    // Initial status: neither unlocked
    $initialResp = $this->actingAs($student, 'sanctum')->getJson("/api/past-papers/{$pastPaper->id}/access");
    $initialResp->assertOk()->assertJson([
        'id' => $pastPaper->id,
        'paper_unlocked' => false,
        'solution_unlocked' => false,
        'has_paper_file' => true,
        'has_solution_file' => true,
        'paper_download_url' => null,
        'solution_download_url' => null,
    ]);
    expect($initialResp->json())->not->toHaveKey('file_path');
    expect($initialResp->json())->not->toHaveKey('solution_file_path');

    // Unlock paper only
    UserPastPaperUnlock::create([
        'user_id' => $student->id,
        'past_paper_id' => $pastPaper->id,
        'paper_unlocked_at' => now(),
    ]);

    $afterPaperUnlock = $this->actingAs($student, 'sanctum')->getJson("/api/past-papers/{$pastPaper->id}/access");
    $afterPaperUnlock->assertOk()->assertJson([
        'paper_unlocked' => true,
        'solution_unlocked' => false,
        'paper_download_url' => url("/api/past-papers/{$pastPaper->id}/download-paper"),
        'solution_download_url' => null,
    ]);
});

test('global free mode allows downloading without prior unlock', function () {
    $setting = AppSetting::current();
    $setting->free_mode_enabled = true;
    $setting->save();

    Storage::fake('local');
    $content = '%PDF-1.4 Free Mode Test Content';
    $filePath = 'past-papers/questions/free_mode.pdf';
    Storage::disk('local')->put($filePath, $content);

    $student = User::factory()->student()->create(['coin_balance' => 0]);
    $pastPaper = PastPaper::factory()->create([
        'file_path' => $filePath,
    ]);

    $response = $this->actingAs($student, 'sanctum')->get("/api/past-papers/{$pastPaper->id}/download-paper");
    $response->assertOk();
    expect($response->streamedContent())->toBe($content);
});
