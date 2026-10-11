<?php

namespace App\Actions;

use App\Jobs\ProcessRollCallImport;
use App\Models\PlenaryMeeting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class QueueRollCallImportsForMeeting
{
    /**
     * @param  list<string>  $filePaths
     * @param  array<string, string>  $originalFilenames
     */
    public function handle(
        PlenaryMeeting $meeting,
        array $filePaths,
        array $originalFilenames,
        User $user,
    ): void {
        DB::transaction(function () use ($meeting, $filePaths, $originalFilenames, $user): void {
            foreach ($filePaths as $filePath) {
                $import = $meeting->rollCallImports()->create([
                    'user_id' => $user->getKey(),
                    'file_path' => $filePath,
                    'original_filename' => $originalFilenames[$filePath] ?? basename($filePath),
                ]);

                ProcessRollCallImport::dispatch($import)->afterCommit();
            }
        });
    }
}
