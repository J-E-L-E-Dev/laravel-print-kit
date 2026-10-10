<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Mantiene las medidas del catálogo en centímetros, sin aproximaciones FLOAT. */
return new class extends Migration
{
    public function getConnection()
    {
        return config('print-kit.database.connection');
    }

    public function up(): void
    {
        Schema::connection($this->getConnection())->create('print_paper_sizes', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('width', 10, 4);
            $table->decimal('height', 10, 4);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('print_paper_sizes');
    }
};
