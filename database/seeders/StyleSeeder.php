<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StyleSeeder extends Seeder
{
    public function run()
    {
        DB::table('styles')->insert([
            ['name' => 'casual'],
            ['name' => 'formal'],
            ['name' => 'streetwear'],
            ['name' => 'sportswear'],
            ['name' => 'vintage'],
            ['name' => 'party'],
            ['name' => 'bohemian'],
            ['name' => 'minimalist'],
            ['name' => 'elegant'],
            ['name' => 'smart casual'],
        ]);
    }
}
