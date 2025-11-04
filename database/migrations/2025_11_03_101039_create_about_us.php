<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('create_about_us', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('content');
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('user_id')->constrained()->onDelete('cascade'); 
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('create_about_us');
    }
};