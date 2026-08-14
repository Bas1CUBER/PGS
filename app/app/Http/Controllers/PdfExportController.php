<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\UploadModuleRegistry;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-side PDF export for upload module records and deliverables
 * (replaces the legacy browser-side PDF hacks; see docs/Reports.md).
 */
final class PdfExportController extends Controller
{
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
        $user = $request->user();

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
        ])->render();

        return $this->pdfResponse($html, 'pgs-'.$slug.'-'.$id.'.pdf');
    }

    public function deliverable(int $id): Response
    {
        $deliverable = DB::table('p_deliverables')->where('id', $id)->first();

        if ($deliverable === null) {
            abort(404);
        }

        $row = (array) $deliverable;

        $html = view('exports.upload-record', [
            'moduleLabel' => 'Deliverable',
            'recordId' => $id,
            'title' => $this->str($row['title'] ?? null),
            'description' => $this->str($row['division'] ?? null),
            'originalName' => $this->str($row['mov_file'] ?? null),
            'fileSize' => '',
            'mimeType' => $this->str($row['status'] ?? null),
            'status' => $this->str($row['status'] ?? null),
            'uploadedAt' => $this->str($row['actual_date'] ?? null),
            'generatedBy' => 'PGS',
        ])->render();

        return $this->pdfResponse($html, 'pgs-deliverable-'.$id.'.pdf');
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
}
