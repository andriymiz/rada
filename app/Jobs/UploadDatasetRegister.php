<?php

namespace App\Jobs;

use App\Enums\DatasetResourceType;
use App\Models\DatasetResource;
use App\Services\OpenDataPortal;
use Filament\Actions\Exports\ContentGenerators\CsvExportContentGenerator;
use Filament\Actions\Exports\Models\Export;
use Filament\Notifications\Notification;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class UploadDatasetRegister implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public Export $export) {}

    public function handle(OpenDataPortal $portal): void
    {
        $stream = Utils::streamFor((function (): \Generator {
            foreach (app(CsvExportContentGenerator::class)($this->export) as $segment) {
                yield $segment;
            }
        })());

        try {
            $resource = DatasetResource::query()
                ->where('name', DatasetResourceType::Register)
                ->first();

            $portal->uploadResource($stream, $resource);
        } catch (Throwable $exception) {
            Notification::make()
                ->title('Помилка завантаження на портал')
                ->body('Не вдалося завантажити реєстр наборів даних на портал відкритих даних.')
                ->danger()
                ->sendToDatabase($this->export->user);

            Log::error('Dataset register upload failed', [
                'export' => $this->export,
                'exception' => $exception,
            ]);

            throw $exception;
        } finally {
            $stream->close();
            unset($stream);
        }

        Notification::make()
            ->title('Реєстр завантажено на портал')
            ->body('Реєстр наборів даних завантажено на портал відкритих даних.')
            ->success()
            ->sendToDatabase($this->export->user);

        Log::info('Dataset register uploaded to open data portal', [
            'export' => $this->export,
        ]);
    }
}
