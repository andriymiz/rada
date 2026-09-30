<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreImportRequest;
use App\Models\CouncilSession;
use App\Models\Import;
use App\Services\SourceDocumentUploadService;
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
            'sessions' => CouncilSession::orderByDesc('held_at')->get(),
            'maxKilobytes' => config('rada.pdf_max_kilobytes'),
        ]);
    }

    public function store(
        StoreImportRequest $request,
        SourceDocumentUploadService $uploadService,
    ): RedirectResponse {
        $import = $uploadService->upload(
            $request->file('document'),
            $request->user(),
            $request->integer('session_id') ?: null,
        );

        return redirect()->route('imports.show', $import)
            ->with('status', 'PDF завантажено. Розпізнавання ще не виконується.');
    }

    public function show(Import $import): View
    {
        return view('imports.show', [
            'import' => $import->load(['sourceDocument', 'uploader', 'session', 'stagedRecords']),
        ]);
    }
}
