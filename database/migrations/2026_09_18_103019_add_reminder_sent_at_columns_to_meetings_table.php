<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dateTime('eve_reminded_at')
                ->nullable()
                ->after('meeting_quota_transaction_id');

            $table->dateTime('one_hour_before_reminded_at')
                ->nullable()
                ->after('eve_reminded_at');
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn([
                'eve_reminded_at',
                'one_hour_before_reminded_at',
            ]);
        });
    }
};
