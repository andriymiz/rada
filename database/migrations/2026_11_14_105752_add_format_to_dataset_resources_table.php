<?php

use Illuminate\Database\Migrations\Migration;
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
        Schema::table('dataset_resources', function (Blueprint $table): void {
            $table->dropUnique('dataset_resources_type_unique');
            $table->renameColumn('type', 'name');
            $table->string('format')->nullable();
            $table->unique(['dataset_id', 'external_id']);
        });

        foreach ([
            'organizations' => 'organizations.csv',
            'posts' => 'posts.csv',
            'deputies' => 'deputies.csv',
            'register' => 'register.csv',
        ] as $oldType => $resourceName) {
            DB::table('dataset_resources')
                ->where('name', $oldType)
                ->update(['name' => $resourceName]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $typeMap = [
            'organizations.csv' => 'organizations',
            'posts.csv' => 'posts',
            'deputies.csv' => 'deputies',
            'register.csv' => 'register',
        ];
        $restoredTypes = [];

        foreach (DB::table('dataset_resources')->pluck('name') as $currentName) {
            if (! is_string($currentName)) {
                throw new RuntimeException('Cannot roll back dataset resource names with invalid values.');
            }

            $restoredTypes[] = $typeMap[$currentName] ?? $currentName;
        }

        if (count($restoredTypes) !== count(array_unique($restoredTypes))) {
            throw new RuntimeException('Cannot roll back while dataset resource names are duplicated.');
        }

        foreach ($typeMap as $resourceName => $oldType) {
            DB::table('dataset_resources')
                ->where('name', $resourceName)
                ->update(['name' => $oldType]);
        }

        Schema::table('dataset_resources', function (Blueprint $table): void {
            $table->dropUnique(['dataset_id', 'external_id']);
            $table->dropColumn('format');
            $table->renameColumn('name', 'type');
            $table->unique('type');
        });
    }
};
