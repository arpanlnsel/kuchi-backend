<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stone_groups', function (Blueprint $table) {
            $table->uuid('stonegroup_ID')->primary();
            $table->uuid('stonegroup_GUID')->unique();
            $table->string('stonegroup_name');
            $table->string('stonegroup_shortname')->nullable();
            $table->boolean('is_disabled')->default(false);
            $table->foreignUuid('user_id')->constrained('users', 'id')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stone_groups');
    }
};