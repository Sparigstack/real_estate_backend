<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\LeadSource;

class LeadSourceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        $sources = ['Reference', 'Social Media', 'Call', 'In Person'];
        
        foreach ($sources as $source) {
            LeadSource::create(['name' => $source]);
        }
    }
}
