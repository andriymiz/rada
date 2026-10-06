<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('roll_call_imports')
            ->where('status', 'completed')
            ->select(['id', 'parsed_result'])
            ->orderBy('id')
            ->chunkById(100, function (Collection $imports): void {
                foreach ($imports as $import) {
                    $parsedResult = json_decode((string) $import->parsed_result, true);
                    $motions = is_array($parsedResult) ? ($parsedResult['motions'] ?? []) : [];

                    $allMotionsApproved = is_array($motions)
                        && $motions !== []
                        && collect($motions)->every(
                            static fn (mixed $motion): bool => is_array($motion)
                                && ($motion['review_status'] ?? null) === 'approved',
                        );

                    if (! $allMotionsApproved) {
                        DB::table('roll_call_imports')
                            ->where('id', $import->id)
                            ->update(['status' => 'awaiting_review']);
                    }
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('roll_call_imports')
            ->where('status', 'awaiting_review')
            ->update(['status' => 'completed']);
    }
};
