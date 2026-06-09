<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable()->index()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            try { $table->dropIndex(['tenant_id']); } catch (\Exception) {}
            $table->dropColumn('tenant_id');
        });
    }
};
