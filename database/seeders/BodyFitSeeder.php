<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BodyFitSeeder extends Seeder
{
    public function run()
    {
        DB::table('body_fits')->insert([
            ['name' => 'regular'],
            ['name' => 'slim'],
            ['name' => 'oversized'],
            ['name' => 'relaxed'],
            ['name' => 'skinny'],
            ['name' => 'tailored'],
            ['name' => 'boxy'],
            ['name' => 'crop'],
        ]);
    }
}
