<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $hasOrphanedImages = DB::table('people')
            ->whereNotNull('image')
            ->whereNotExists(function (Builder $query): void {
                $query
                    ->selectRaw('1')
                    ->from('deputies')
                    ->whereColumn('deputies.person_id', 'people.id');
            })
            ->exists();

        if ($hasOrphanedImages) {
            throw new RuntimeException('Cannot move person images because some people do not have a deputy record.');
        }

        Schema::table('deputies', function (Blueprint $table) {
            $table->text('image')->nullable();
        });

        DB::table('deputies')
            ->join('people', 'people.id', '=', 'deputies.person_id')
            ->whereNotNull('people.image')
            ->select([
                'deputies.id as deputy_id',
                'people.image as person_image',
            ])
            ->chunkById(200, function ($deputies): void {
                foreach ($deputies as $deputy) {
                    DB::table('deputies')
                        ->where('id', $deputy->deputy_id)
                        ->update(['image' => $deputy->person_image]);
                }
            }, 'deputies.id', 'deputy_id');

        Schema::table('people', function (Blueprint $table) {
            $table->dropColumn('image');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $imagesByPerson = [];

        DB::table('deputies')
            ->select(['id', 'person_id', 'image'])
            ->orderBy('id')
            ->chunkById(200, function ($deputies) use (&$imagesByPerson): void {
                foreach ($deputies as $deputy) {
                    $personId = (int) $deputy->person_id;

                    if (array_key_exists($personId, $imagesByPerson) && $imagesByPerson[$personId] !== $deputy->image) {
                        throw new RuntimeException('Cannot restore person images because deputies linked to the same person have different images.');
                    }

                    $imagesByPerson[$personId] = $deputy->image;
                }
            });

        Schema::table('people', function (Blueprint $table) {
            $table->text('image')->nullable();
        });

        foreach ($imagesByPerson as $personId => $image) {
            DB::table('people')
                ->where('id', $personId)
                ->update(['image' => $image]);
        }

        Schema::table('deputies', function (Blueprint $table) {
            $table->dropColumn('image');
        });
    }
};
