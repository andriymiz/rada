<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreImportRequest;
use App\Models\AuditLog;
use App\Models\Import;
use App\Services\ImportConfirmationService;
use App\Services\SessionPdfImportService;
use App\Services\SourceDocumentUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
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
        $records = $pdfImportService->import($import);

        if ($records === 0) {
            return redirect()->route('imports.show', $import)
                ->with('error', 'PDF збережено, але не вдалося розпізнати сторінки сесії. Чернетку можна видалити.');
        }

        return redirect()->route('imports.show', $import)
            ->with('status', 'PDF завантажено та імпортовано до staging для перевірки.');
    }

    public function show(Import $import): View
    {
        Gate::authorize('view', $import);

        return view('imports.show', [
            'import' => $import->load(['sourceDocument', 'uploader', 'session', 'stagedRecords']),
        ]);
    }

    public function confirm(
        Request $request,
        Import $import,
        ImportConfirmationService $confirmationService,
    ): RedirectResponse {
        Gate::authorize('update', $import);

        try {
            $confirmationService->confirm($import, $request->user());
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['import' => $exception->getMessage()]);
        }

        return redirect()->route('imports.show', $import->fresh())
            ->with('status', 'Імпорт підтверджено та підготовлено до експорту.');
    }

    public function destroy(Request $request, Import $import): RedirectResponse
    {
        Gate::authorize('delete', $import);

        $import->loadMissing('sourceDocument');
        $document = $import->sourceDocument;
        $disk = $document->disk;
        $path = $document->path;

        AuditLog::create([
            'user_id' => $request->user()->id,
            'event' => 'import.cancelled',
            'auditable_type' => Import::class,
            'auditable_id' => $import->id,
            'created_at' => now(),
        ]);

        $import->delete();
        $document->delete();
        Storage::disk($disk)->delete($path);

        return redirect()->route('imports.index')->with('status', 'Чернетку імпорту видалено.');
    }
}
