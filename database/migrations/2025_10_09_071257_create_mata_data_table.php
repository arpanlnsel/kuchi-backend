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
        Schema::create('mata_data', function (Blueprint $table) {
            $table->uuid('mata_id')->primary();
            $table->string('device_name')->nullable();
            $table->string('device_type')->nullable();
            $table->timestamp('last_login_time')->nullable();
            $table->uuid('user_id');
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mata_data');
    }
};