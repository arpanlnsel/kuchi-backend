<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->uuid('event_id')->primary();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('venue');
            $table->enum('status', ['Active', 'Inactive', 'Cancelled', 'Completed'])->default('Active');
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->boolean('show_video_details')->default(false);
            $table->string('event_location')->nullable();
            $table->string('main_image')->nullable();
            $table->json('event_images')->nullable();
            $table->foreignUuid('user_id')->constrained('users', 'id')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};