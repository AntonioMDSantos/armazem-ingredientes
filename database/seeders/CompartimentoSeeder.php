<?php

namespace Database\Seeders;

use App\Models\Compartimento;
use Illuminate\Database\Seeder;

class CompartimentoSeeder extends Seeder
{
    public function run(): void
    {
        foreach (range(1, 5) as $i) {
            Compartimento::firstOrCreate(['codigo' => "C{$i}"]);
        }
    }
}
