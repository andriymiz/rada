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
        Schema::create('people', function (Blueprint $table): void {
            $table->id();
            $table->string('last_name')->nullable();
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->unsignedTinyInteger('gender')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->text('image')->nullable();
            $table->text('homepage')->nullable();
            $table->timestamps();
        });

        Schema::table('deputies', function (Blueprint $table): void {
            $table->foreignId('person_id')->nullable()->constrained('people')->restrictOnDelete();
            $table->unique('person_id');
        });

        DB::table('deputies')
            ->select([
                'id',
                'last_name',
                'first_name',
                'middle_name',
                'gender',
                'date_of_birth',
                'image',
                'homepage',
                'created_at',
                'updated_at',
            ])
            ->orderBy('id')
            ->chunkById(200, function ($deputies): void {
                foreach ($deputies as $deputy) {
                    $personId = DB::table('people')->insertGetId([
                        'last_name' => $deputy->last_name,
                        'first_name' => $deputy->first_name,
                        'middle_name' => $deputy->middle_name,
                        'gender' => $deputy->gender,
                        'date_of_birth' => $deputy->date_of_birth,
                        'image' => $deputy->image,
                        'homepage' => $deputy->homepage,
                        'created_at' => $deputy->created_at,
                        'updated_at' => $deputy->updated_at,
                    ]);

                    DB::table('deputies')
                        ->where('id', $deputy->id)
                        ->update(['person_id' => $personId]);
                }
            });

        Schema::table('deputies', function (Blueprint $table): void {
            $table->foreignId('person_id')->nullable(false)->change();
            $table->dropColumn([
                'last_name',
                'first_name',
                'middle_name',
                'gender',
                'date_of_birth',
                'image',
                'homepage',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deputies', function (Blueprint $table): void {
            $table->string('last_name')->nullable();
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->unsignedTinyInteger('gender')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->text('image')->nullable();
            $table->text('homepage')->nullable();
        });

        DB::table('deputies')
            ->orderBy('id')
            ->chunkById(200, function ($deputies): void {
                foreach ($deputies as $deputy) {
                    $person = DB::table('people')->where('id', $deputy->person_id)->first();

                    if ($person === null) {
                        throw new RuntimeException("Deputy {$deputy->id} has no linked person to restore.");
                    }

                    DB::table('deputies')
                        ->where('id', $deputy->id)
                        ->update([
                            'last_name' => $person->last_name,
                            'first_name' => $person->first_name,
                            'middle_name' => $person->middle_name,
                            'gender' => $person->gender,
                            'date_of_birth' => $person->date_of_birth,
                            'image' => $person->image,
                            'homepage' => $person->homepage,
                        ]);
                }
            });

        Schema::table('deputies', function (Blueprint $table): void {
            $table->dropUnique('deputies_person_id_unique');
            $table->dropConstrainedForeignId('person_id');
        });

        Schema::dropIfExists('people');
    }
};
