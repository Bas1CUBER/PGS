<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('upload_module_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 100);
            $table->string('label', 255);
            $table->string('filename', 255);
            $table->string('original_name', 255);
            $table->integer('uploaded_by')->nullable();
            $table->timestamps();
            $table->unique(['slug', 'label']);
            $table->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('upload_module_templates');
    }
};
