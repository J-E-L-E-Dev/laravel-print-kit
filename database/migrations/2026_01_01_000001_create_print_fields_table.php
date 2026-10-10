<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Crea exclusivamente el esquema predeterminado; no adapta tablas de terceros. */
return new class extends Migration
{
    public function getConnection()
    {
        return config('print-kit.database.connection');
    }

    public function up(): void
    {
        Schema::connection($this->getConnection())->create('print_fields', function (Blueprint $table): void {
            $table->id();
            $table->string('driver', 100)->default('mysql');
            $table->char('system', 3)->default('INV');
            $table->string('category', 2)->default('ET');
            $table->string('placeholder', 100);
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->string('data_type', 32)->default('C');
            $table->boolean('usable_in_detail')->default(false);
            $table->string('associated_field', 128);
            $table->timestamps();
            $table->index(['system', 'category']);
            $table->unique(['driver', 'system', 'category', 'placeholder'], 'print_fields_identity_unique');
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('print_fields');
    }
};
