<?php

use App\Enums\TipoIngrediente;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compartimentos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 2)->unique();
            $table->enum('tipo_atual', array_column(TipoIngrediente::cases(), 'value'))->nullable();
            $table->date('tipo_definido_em')->nullable();
            $table->decimal('quantidade_atual', 10, 3)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compartimentos');
    }
};
