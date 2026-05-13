<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            // Plan 05-03 fix (Rule 1 - Bug): the Sanctum publish:install scaffold
            // shipped by plan 05-01 used $table->morphs('tokenable') which creates
            // tokenable_id as unsignedBigInteger. Trenchwars' users.id is uuid
            // (HasUuidPrimaryKey trait, Phase 1). UUIDs cannot insert into a bigint
            // column ("invalid input syntax for type bigint"). Swap to uuidMorphs
            // so the polymorphic tokenable_id column is uuid-typed.
            $table->id();
            $table->uuidMorphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
