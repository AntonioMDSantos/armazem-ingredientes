<?php

use App\Enums\TipoIngrediente;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingredientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compartimento_id')->constrained('compartimentos')->restrictOnDelete();
            $table->string('nome');
            $table->enum('tipo', array_column(TipoIngrediente::cases(), 'value'));
            $table->decimal('quantidade', 10, 3)->default(0);
            $table->timestamps();

            $table->unique(['compartimento_id', 'nome']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingredientes');
    }
};
