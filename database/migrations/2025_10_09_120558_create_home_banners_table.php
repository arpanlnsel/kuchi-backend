<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_banner', function (Blueprint $table) {
            $table->id();
            $table->string('banner_title');
            $table->integer('priority')->default(0);
            $table->enum('device_type', ['mobile', 'desktop', 'tablet', 'all'])->default('all');
            $table->string('image')->nullable();
            $table->foreignUuid('create_user_id')->constrained('users', 'id')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_banner');
    }
};