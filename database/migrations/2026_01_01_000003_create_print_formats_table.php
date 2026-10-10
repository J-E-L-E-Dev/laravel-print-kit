<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** La FK protege el catálogo predeterminado; los JSON iniciales se asignan desde el modelo. */
return new class extends Migration
{
    public function getConnection()
    {
        return config('print-kit.database.connection');
    }

    public function up(): void
    {
        Schema::connection($this->getConnection())->create('print_formats', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('name');
            $table->unsignedInteger('copies')->default(1);
            $table->boolean('preview')->default(true);
            $table->string('printer')->nullable();
            $table->foreignId('paper_size_id')->constrained('print_paper_sizes')->restrictOnDelete();
            $table->char('orientation', 1)->default('V');
            $table->char('system', 3)->default('INV');
            $table->json('detail');
            $table->char('category', 2)->default('ET');
            $table->unsignedInteger('version')->default(1);
            $table->json('margins');
            $table->boolean('use_default_printer')->default(false);
            $table->boolean('disabled')->default(false);
            $table->json('parameters')->nullable();
            $table->timestamps();
            $table->index(['system', 'category', 'disabled']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('print_formats');
    }
};
