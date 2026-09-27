<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Orders are financial records, so they must not disappear when a project
     * or a user is deleted. Both foreign keys become restricting, which makes
     * the existing "cannot delete a published project" rule enforceable at the
     * database level too.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropForeign(['project_id']);
            $table->dropForeign(['buyer_id']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreign('project_id')->references('id')->on('projects')->restrictOnDelete();
            $table->foreign('buyer_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropForeign(['project_id']);
            $table->dropForeign(['buyer_id']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('buyer_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
