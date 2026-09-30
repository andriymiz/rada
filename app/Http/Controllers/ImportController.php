<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreImportRequest;
use App\Models\Import;
use App\Services\ImportConfirmationService;
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
        $import = $uploadService->upload(
            $request->file('document'),
            $request->user(),
            $request->string('session_number')->toString(),
        );
        $pdfImportService->import($import->load('sourceDocument'));

        return redirect()->route('imports.show', $import)
            ->with('status', 'PDF завантажено та імпортовано до staging для перевірки.');
    }

    public function show(Import $import): View
    {
        abort_unless($import->uploaded_by === request()->user()->id, 403);

        return view('imports.show', [
            'import' => $import->load(['sourceDocument', 'uploader', 'session', 'stagedRecords']),
        ]);
    }

    public function confirm(Import $import, ImportConfirmationService $confirmationService): RedirectResponse
    {
        abort_unless($import->uploaded_by === request()->user()->id, 403);

        try {
            $confirmationService->confirm($import, request()->user());
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['import' => $exception->getMessage()]);
        }

        return redirect()->route('imports.show', $import->fresh())
            ->with('status', 'Імпорт підтверджено та підготовлено до експорту.');
    }

    public function destroy(Import $import): RedirectResponse
    {
        abort_unless($import->uploaded_by === request()->user()->id, 403);

        $document = $import->sourceDocument;
        $disk = $document->disk;
        $path = $document->path;

        \App\Models\AuditLog::create([
            'user_id' => request()->user()->id,
            'event' => 'import.cancelled',
            'auditable_type' => Import::class,
            'auditable_id' => $import->id,
            'created_at' => now(),
        ]);

        $import->delete();
        $document->delete();
        \Illuminate\Support\Facades\Storage::disk($disk)->delete($path);

        return redirect()->route('imports.index')->with('status', 'Чернетку імпорту видалено.');
    }
}
