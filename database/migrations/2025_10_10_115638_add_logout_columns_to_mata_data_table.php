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
        Schema::table('mata_data', function (Blueprint $table) {
            $table->timestamp('logout_time')->nullable()->after('last_login_time');
            $table->boolean('is_logout')->default(false)->after('logout_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mata_data', function (Blueprint $table) {
            $table->dropColumn(['logout_time', 'is_logout']);
        });
    }
};