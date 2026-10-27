<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_users', function (Blueprint $table) {
            $table->string('username', 64)->nullable()->index()->after('telegram_id');
            $table->string('first_name', 120)->nullable()->after('username');
            $table->string('last_name', 120)->nullable()->after('first_name');
            $table->boolean('is_premium')->default(false)->after('last_name');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_users', function (Blueprint $table) {
            $table->dropColumn(['username', 'first_name', 'last_name', 'is_premium']);
        });
    }
};
