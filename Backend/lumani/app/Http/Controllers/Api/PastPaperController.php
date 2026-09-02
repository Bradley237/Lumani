<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PastPaper;
use App\Models\User;
use App\Services\AccessControlService;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class PastPaperController extends Controller
{
    public function __construct(
        protected AccessControlService $accessControlService
    ) {}

    /**
     * Retrieve access ownership and document availability for a past paper.
     */
    public function accessStatus(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var PastPaper $pastPaper */
        $pastPaper = PastPaper::findOrFail($id);

        $paperUnlocked = $this->accessControlService->canAccessPastPaper($user, $pastPaper);
        $solutionUnlocked = $this->accessControlService->canAccessPastPaperSolution($user, $pastPaper);
        $hasPaperFile = $pastPaper->hasPaperFile() && Storage::disk('local')->exists((string) $pastPaper->file_path);
        $hasSolutionFile = $pastPaper->hasSolutionFile() && Storage::disk('local')->exists((string) $pastPaper->solution_file_path);

        return response()->json([
            'id' => $pastPaper->id,
            'title' => $pastPaper->title,
            'year' => $pastPaper->year,
            'paper_unlocked' => $paperUnlocked,
            'solution_unlocked' => $solutionUnlocked,
            'has_paper_file' => $hasPaperFile,
            'has_solution_file' => $hasSolutionFile,
            'paper_download_url' => ($paperUnlocked && $hasPaperFile) ? route('api.past-papers.download-paper', ['id' => $pastPaper->id]) : null,
            'paper_view_url' => ($paperUnlocked && $hasPaperFile) ? route('api.past-papers.view-paper', ['id' => $pastPaper->id]) : null,
            'solution_download_url' => ($solutionUnlocked && $hasSolutionFile) ? route('api.past-papers.download-solution', ['id' => $pastPaper->id]) : null,
            'solution_view_url' => ($solutionUnlocked && $hasSolutionFile) ? route('api.past-papers.view-solution', ['id' => $pastPaper->id]) : null,
        ]);
    }

    /**
     * Unlock a past paper questions document.
     */
    public function unlockPaper(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var PastPaper $pastPaper */
        $pastPaper = PastPaper::findOrFail($id);

        $result = $this->accessControlService->unlockPastPaper($user, $pastPaper);

        if ($result['success']) {
            $result['download_url'] = route('api.past-papers.download-paper', ['id' => $pastPaper->id]);
            $result['view_url'] = route('api.past-papers.view-paper', ['id' => $pastPaper->id]);
        }

        return response()->json($result);
    }

    /**
     * Unlock a past paper solution document.
     */
    public function unlockSolution(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var PastPaper $pastPaper */
        $pastPaper = PastPaper::findOrFail($id);

        $result = $this->accessControlService->unlockPastPaperSolution($user, $pastPaper);

        if ($result['success']) {
            $result['download_url'] = route('api.past-papers.download-solution', ['id' => $pastPaper->id]);
            $result['view_url'] = route('api.past-papers.view-solution', ['id' => $pastPaper->id]);
        }

        return response()->json($result);
    }

    /**
     * Download questions paper document (defaults to attachment disposition).
     */
    public function downloadPaper(Request $request, int $id): Response
    {
        $disposition = $request->query('disposition') === 'inline' || $request->boolean('inline')
            ? 'inline'
            : 'attachment';

        return $this->servePaperFile($request, $id, 'paper', $disposition);
    }

    /**
     * View questions paper document inline.
     */
    public function viewPaper(Request $request, int $id): Response
    {
        $disposition = $request->query('disposition') === 'attachment'
            ? 'attachment'
            : 'inline';

        return $this->servePaperFile($request, $id, 'paper', $disposition);
    }

    /**
     * Download solution document (defaults to attachment disposition).
     */
    public function downloadSolution(Request $request, int $id): Response
    {
        $disposition = $request->query('disposition') === 'inline' || $request->boolean('inline')
            ? 'inline'
            : 'attachment';

        return $this->servePaperFile($request, $id, 'solution', $disposition);
    }

    /**
     * View solution document inline.
     */
    public function viewSolution(Request $request, int $id): Response
    {
        $disposition = $request->query('disposition') === 'attachment'
            ? 'attachment'
            : 'inline';

        return $this->servePaperFile($request, $id, 'solution', $disposition);
    }

    /**
     * Securely authenticate, authorize ownership, and stream the document file from private disk.
     */
    protected function servePaperFile(Request $request, int $id, string $type, string $disposition): Response
    {
        /** @var User $user */
        $user = $request->user();

        /** @var PastPaper $pastPaper */
        $pastPaper = PastPaper::findOrFail($id);

        if ($type === 'paper') {
            if (! $this->accessControlService->canAccessPastPaper($user, $pastPaper)) {
                abort(403, 'You must unlock this past paper questions document before accessing it.');
            }
            $filePath = $pastPaper->file_path;
            $suffix = 'questions';
        } else {
            if (! $this->accessControlService->canAccessPastPaperSolution($user, $pastPaper)) {
                abort(403, 'You must unlock this past paper solution document before accessing it.');
            }
            $filePath = $pastPaper->solution_file_path;
            $suffix = 'solution';
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('local');

        if (blank($filePath) || ! $disk->exists($filePath)) {
            abort(404, 'The requested document is not currently available for download.');
        }

        $sanitizedTitle = Str::slug($pastPaper->title) ?: 'past-paper';
        $yearPart = $pastPaper->year ? "-{$pastPaper->year}" : '';
        $filename = "{$sanitizedTitle}{$yearPart}-{$suffix}.pdf";

        return $disk->response(
            $filePath,
            $filename,
            [
                'Content-Type' => 'application/pdf',
                'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ],
            $disposition
        );
    }
}
