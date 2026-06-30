<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Generate a large amount of fake email data.
     */
    public function run(): void
    {
        $this->command->call('emails:seed', ['--count' => 100_000]);
    }
}
