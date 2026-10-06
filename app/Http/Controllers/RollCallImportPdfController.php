<?php

namespace App\Http\Controllers;

use App\Models\RollCallImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RollCallImportPdfController extends Controller
{
    public function __invoke(Request $request, RollCallImport $rollCallImport): StreamedResponse|RedirectResponse
    {
        if (! $request->user()) {
            return redirect()->guest(route('filament.rada.auth.login'));
        }

        $disk = Storage::disk('local');

        abort_unless($disk->exists($rollCallImport->file_path), 404);

        return $disk->response(
            $rollCallImport->file_path,
            basename($rollCallImport->original_filename),
            [
                'Content-Type' => 'application/pdf',
                'X-Content-Type-Options' => 'nosniff',
            ],
            'inline',
        );
    }
}
