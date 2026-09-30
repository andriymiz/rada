<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreImportRequest;
use App\Models\CouncilSession;
use App\Models\Import;
use App\Services\SourceDocumentUploadService;
use App\Services\SessionPdfImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ImportController extends Controller
{
    public function index(): View
    {
        return view('imports.index', [
            'imports' => Import::with(['sourceDocument', 'uploader', 'session'])
                ->latest()
                ->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('imports.create', [
            'maxKilobytes' => config('rada.pdf_max_kilobytes'),
        ]);
    }

    public function store(
        StoreImportRequest $request,
        SourceDocumentUploadService $uploadService,
        SessionPdfImportService $pdfImportService,
    ): RedirectResponse {
        $session = CouncilSession::firstOrCreate(
            ['session_number' => $request->string('session_number')->toString()],
            [
                'title' => 'Сесія №'.$request->string('session_number')->toString(),
                'status' => 'held',
            ],
        );
        $import = $uploadService->upload(
            $request->file('document'),
            $request->user(),
            $session->id,
        );
        $pdfImportService->import($import->load('sourceDocument'));

        return redirect()->route('imports.show', $import)
            ->with('status', 'PDF завантажено та імпортовано до staging для перевірки.');
    }

    public function show(Import $import): View
    {
        return view('imports.show', [
            'import' => $import->load(['sourceDocument', 'uploader', 'session', 'stagedRecords']),
        ]);
    }
}
