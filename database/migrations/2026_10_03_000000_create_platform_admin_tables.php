<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // A platform operator with no company of their own; manages every company from /platform.
            $table->boolean('is_super_admin')->default(false)->after('company_id');
        });

        Schema::table('companies', function (Blueprint $table) {
            // A suspended company's users can no longer sign in; the company and its data stay intact.
            $table->dateTime('suspended_at')->nullable()->after('brand_color');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('suspended_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_super_admin');
        });
    }
};
