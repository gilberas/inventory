<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            // tin already missing from original create — add it
            $table->string('tin', 50)->nullable()->after('contact_person');
            // Replace boolean is_active with richer status enum
            $table->enum('status', ['active', 'inactive'])->default('active')->after('tin');

            // Hard Rule §5 — composite index on tenant_id + status
            $table->index(['tenant_id', 'status']);
            // Spec-required index for email lookups per tenant
            $table->index(['tenant_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'status']);
            $table->dropIndex(['tenant_id', 'email']);
            $table->dropColumn(['tin', 'status']);
        });
    }
};
