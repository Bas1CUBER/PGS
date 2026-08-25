<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Deliverable;
use App\Models\User;
use App\Modules\UploadModuleRegistry;
use App\Services\AuditLogService;
use App\Services\PageAccessService;
use App\Services\UploadModuleService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-side PDF export for upload module records and deliverables
 * (replaces the legacy browser-side PDF hacks; see docs/Reports.md).
 */
final class PdfExportController extends Controller
{
    public function __construct(
        private readonly AuditLogService $audit,
        private readonly PageAccessService $access,
    ) {}

    public function uploadRecord(Request $request, string $slug, int $id): Response
    {
        $module = UploadModuleRegistry::find($slug);

        if ($module === null) {
            abort(404);
        }

        $row = DB::table($module['table'])->where('id', $id)->first();

        if ($row === null) {
            abort(404);
        }

        $rowArr = (array) $row;
        $user = $this->userOrFail($request);
        $gate = UploadModuleService::accessGateFor($slug);
        abort_unless($gate === null || ! $this->access->hasMatrix($user) || $this->access->can($user, $gate), 403);

        $title = $module['label'].' — Record #'.$id;

        $html = view('exports.upload-record', [
            'moduleLabel' => $module['label'],
            'recordId' => $id,
            'title' => $this->str($rowArr['title'] ?? null),
            'description' => $this->str($rowArr['description'] ?? null),
            'originalName' => $this->str($rowArr['original_name'] ?? null),
            'fileSize' => $this->str($rowArr['file_size'] ?? null),
            'mimeType' => $this->str($rowArr['mime_type'] ?? null),
            'status' => $this->str($rowArr['status'] ?? null),
            'uploadedAt' => $this->str($rowArr['uploaded_at'] ?? null),
            'generatedBy' => $user->email ?? 'system',
            'extraRows' => [],
        ])->render();

        $this->audit->record(
            $user->id,
            "upload.{$slug}.exported",
            $module['table'],
            (string) $id,
            request: $request,
        );

        return $this->pdfResponse($html, 'pgs-'.$slug.'-'.$id.'.pdf');
    }

    public function deliverable(Request $request, int $id): Response
    {
        $deliverable = Deliverable::query()->findOrFail($id);
        $this->authorize('view', $deliverable);
        $user = $this->userOrFail($request);

        // Properly labeled rows instead of shoehorning values into the
        // generic upload-record fields (status was previously rendered as
        // the file "Type").
        $extraRows = [
            ['Division', $this->str($deliverable->division)],
            ['Focal Person', $this->str($deliverable->focal_person)],
            ['Form Type', $this->str($deliverable->form_type)],
            ['Target Date', $deliverable->target_date !== null ? $deliverable->target_date->format('Y-m-d') : ''],
            ['Actual Date', $deliverable->actual_date !== null ? $deliverable->actual_date->format('Y-m-d') : ''],
            ['Status', $deliverable->status !== null ? $deliverable->status->value : ''],
        ];

        $html = view('exports.upload-record', [
            'moduleLabel' => 'Deliverable',
            'recordId' => $id,
            'title' => $this->str($deliverable->title),
            'description' => '',
            'originalName' => $this->str($deliverable->mov_file !== null ? basename($deliverable->mov_file) : null),
            'fileSize' => '',
            'mimeType' => '',
            'status' => '',
            'uploadedAt' => $deliverable->created_at->format('Y-m-d H:i'),
            'generatedBy' => $user->email,
            'extraRows' => $extraRows,
        ])->render();

        $this->audit->record(
            $user->id,
            'deliverable.exported',
            'p_deliverables',
            (string) $deliverable->id,
            request: $request,
        );

        return $this->pdfResponse($html, 'pgs-deliverable-'.$id.'.pdf');
    }

    public function operationsReview(Request $request, int $id): Response
    {
        $user = $this->userOrFail($request);
        $record = DB::table('operations_review')->where('id', $id)->first();
        abort_if($record === null, 404);
        abort_unless($user->isAdmin() || $user->isFocal() || $this->idValue($record->employee_id) === $user->id, 403);
        $data = $this->decodeFormData($record->form_data);
        $html = view('exports.structured-form', [
            'title' => 'Operations Review', 'recordId' => $id, 'data' => $data, 'generatedBy' => $user->email,
        ])->render();
        $this->audit->record($user->id, 'operations_review.exported', 'operations_review', (string) $id, request: $request);

        return $this->pdfResponse($html, 'pgs-operations-review-'.$id.'.pdf');
    }

    public function strategyReview(Request $request, int $id): Response
    {
        $user = $this->userOrFail($request);
        $record = DB::table('strategy_review_forms')->where('id', $id)->first();
        abort_if($record === null, 404);
        abort_unless($user->isAdmin() || $user->isFocal() || $this->idValue($record->employee_id) === $user->id, 403);
        $data = $this->decodeFormData($record->form_data);
        $html = view('exports.structured-form', [
            'title' => 'Strategy Review', 'recordId' => $id, 'data' => $data, 'generatedBy' => $user->email,
        ])->render();
        $this->audit->record($user->id, 'strategy_review.exported', 'strategy_review_forms', (string) $id, request: $request);

        return $this->pdfResponse($html, 'pgs-strategy-review-'.$id.'.pdf');
    }

    private function pdfResponse(string $html, string $filename): Response
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function str(mixed $value): string
    {
        return $value === null ? '' : (string) $value;
    }

    private function idValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    /** @return array<string, string> */
    private function decodeFormData(mixed $raw): array
    {
        $decoded = is_string($raw) ? json_decode($raw, true) : null;

        if (! is_array($decoded)) {
            return [];
        }

        $data = [];
        foreach ($decoded as $key => $value) {
            $data[(string) $key] = is_scalar($value) ? (string) $value : '';
        }

        return $data;
    }

    /**
     * @throws AuthenticationException
     */
    private function userOrFail(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }
}
