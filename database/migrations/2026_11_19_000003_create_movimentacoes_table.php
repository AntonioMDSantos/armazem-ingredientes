<?php

use App\Enums\TipoIngrediente;
use App\Enums\TipoOperacao;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimentacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingrediente_id')->constrained('ingredientes')->restrictOnDelete();
            $table->foreignId('compartimento_id')->constrained('compartimentos')->restrictOnDelete();
            $table->enum('operacao', array_column(TipoOperacao::cases(), 'value'));
            $table->enum('tipo_ingrediente', array_column(TipoIngrediente::cases(), 'value'));
            $table->decimal('quantidade', 10, 3);
            $table->string('responsavel');
            $table->timestamp('occurred_at', 6);

            $table->index(['occurred_at', 'compartimento_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimentacoes');
    }
};
