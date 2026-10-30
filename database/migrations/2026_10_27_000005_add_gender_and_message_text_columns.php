<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_users', function (Blueprint $table) {
            $table->string('gender', 20)->nullable()->index()->after('is_premium'); // male, female, other
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->text('message_text')->nullable()->after('message_type');
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropColumn('message_text');
        });

        Schema::table('telegram_users', function (Blueprint $table) {
            $table->dropColumn('gender');
        });
    }
};
