<?php

use App\Enums\RollCallImportStatus;
use App\Filament\Resources\RollCallImports\Pages\CreateRollCallImport;
use App\Filament\Resources\RollCallImports\Pages\ListRollCallImports;
use App\Filament\Resources\RollCallImports\Pages\ViewRollCallImport;
use App\Filament\Resources\RollCallImports\RollCallImportResource;
use App\Jobs\ProcessRollCallImport;
use App\Models\ParliamentaryConvocation;
use App\Models\ParliamentarySession;
use App\Models\RollCallImport;
use App\Models\User;
use App\Notifications\RollCallImportProcessed;
use Carbon\CarbonInterval;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel('rada');
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('uploads a PDF, records its session, queues processing, and returns to the import history', function () {
    $convocation = ParliamentaryConvocation::factory()->create();
    $session = ParliamentarySession::factory()->for($convocation, 'convocation')->create();

    Storage::fake('local');
    Queue::fake([ProcessRollCallImport::class]);

    livewire(CreateRollCallImport::class)
        ->fillForm([
            'file_path' => UploadedFile::fake()->create('roll-calls.pdf', 100, 'application/pdf'),
            'convocation_id' => $convocation->id,
            'session_id' => $session->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect(RollCallImportResource::getUrl('index'));

    $import = RollCallImport::query()->firstOrFail();

    expect($import->session_id)->toBe($session->id)
        ->and($import->user_id)->toBe($this->user->id)
        ->and($import->original_filename)->toBe('roll-calls.pdf')
        ->and($import->status)->toBe(RollCallImportStatus::Queued);

    Storage::disk('local')->assertExists($import->file_path);
    Queue::assertPushed(ProcessRollCallImport::class, fn (ProcessRollCallImport $job): bool => $job->rollCallImport->is($import));
});

it('rejects a session that belongs to a different convocation', function () {
    $selectedConvocation = ParliamentaryConvocation::factory()->create();
    $otherConvocation = ParliamentaryConvocation::factory()->create();
    $session = ParliamentarySession::factory()->for($otherConvocation, 'convocation')->create();

    Storage::fake('local');
    Queue::fake([ProcessRollCallImport::class]);

    livewire(CreateRollCallImport::class)
        ->fillForm([
            'file_path' => UploadedFile::fake()->create('roll-calls.pdf', 100, 'application/pdf'),
            'convocation_id' => $selectedConvocation->id,
            'session_id' => $session->id,
        ])
        ->call('create')
        ->assertHasFormErrors(['session_id']);

    expect(RollCallImport::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('rejects non-PDF files', function () {
    $convocation = ParliamentaryConvocation::factory()->create();
    $session = ParliamentarySession::factory()->for($convocation, 'convocation')->create();

    Storage::fake('local');
    Queue::fake([ProcessRollCallImport::class]);

    livewire(CreateRollCallImport::class)
        ->fillForm([
            'file_path' => UploadedFile::fake()->create('roll-calls.txt', 10, 'text/plain'),
            'convocation_id' => $convocation->id,
            'session_id' => $session->id,
        ])
        ->call('create')
        ->assertHasFormErrors(['file_path']);

    expect(RollCallImport::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('creates and edits convocation and session options from the import form', function () {
    $form = livewire(CreateRollCallImport::class);

    $form->callFormComponentAction('convocation_id', 'createOption', [
        'name' => 'VIII скликання',
    ])->assertHasNoFormErrors();

    $convocation = ParliamentaryConvocation::query()->sole();

    $form->callFormComponentAction('convocation_id', 'editOption', [
        'name' => 'IX скликання',
    ])->assertHasNoFormErrors();

    $form->callFormComponentAction('session_id', 'createOption', [
        'name' => 'Перша сесія',
    ])->assertHasNoFormErrors();

    $session = ParliamentarySession::query()->sole();

    $form->callFormComponentAction('session_id', 'editOption', [
        'name' => 'Друга сесія',
    ])->assertHasNoFormErrors();

    expect($convocation->fresh()->name)->toBe('IX скликання')
        ->and($session->fresh()->name)->toBe('Друга сесія')
        ->and($session->fresh()->convocation_id)->toBe($convocation->id);
});

it('shows the newest imports first and only permits viewing completed imports', function () {
    $user = User::factory()->create();
    $session = ParliamentarySession::factory()->create();
    $olderImport = RollCallImport::factory()
        ->for($user)
        ->for($session, 'session')
        ->create();
    $newestImport = RollCallImport::factory()
        ->for($user)
        ->for($session, 'session')
        ->create(['status' => RollCallImportStatus::Completed]);

    $list = livewire(ListRollCallImports::class)
        ->assertCanSeeTableRecords([$newestImport, $olderImport], inOrder: true);

    expect($list->instance()->getTable()->getPollingInterval())->toBe('3s');

    livewire(ViewRollCallImport::class, ['record' => $olderImport->id])
        ->assertForbidden();

    livewire(ViewRollCallImport::class, ['record' => $newestImport->id])
        ->assertOk()
        ->assertSee('example.pdf');
});

it('marks the import complete and notifies its uploader after the simulated work', function () {
    $import = RollCallImport::factory()->create();

    Sleep::fake();

    (new ProcessRollCallImport($import))->handle();

    $import->refresh();
    $notification = $import->user->notifications()->firstOrFail();

    expect($import->status)->toBe(RollCallImportStatus::Completed)
        ->and($import->processed_at)->not->toBeNull()
        ->and($notification->data['title'])->toBe('Імпорт завершено')
        ->and($notification->data['format'])->toBe('filament');

    Sleep::assertSlept(fn (CarbonInterval $duration): bool => $duration->totalSeconds === 3.0);
});

it('marks a failed import and notifies its uploader', function () {
    $import = RollCallImport::factory()->create();

    Notification::fake();

    (new ProcessRollCallImport($import))->failed(new RuntimeException('Processing failed'));

    expect($import->fresh()->status)->toBe(RollCallImportStatus::Failed);

    Notification::assertSentTo($import->user, RollCallImportProcessed::class);
});
