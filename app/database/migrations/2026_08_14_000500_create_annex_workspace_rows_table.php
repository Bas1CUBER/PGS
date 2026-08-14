<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('annex_workspace_rows', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 30);
            $table->json('data');
            $table->integer('created_by')->nullable();
            $table->timestamps();
            $table->index('slug');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('annex_workspace_rows');
    }
};
