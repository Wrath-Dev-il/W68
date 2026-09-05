<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Seed default logins for local testing of each account type
        \App\Models\Login::updateOrCreate(
            ['User_ID' => 'admin'],
            [
                'account_type' => 1,
                'Password' => 'admin123', // support plain text / fallback
                'User_First_Name' => 'Chief',
                'User_Middle_Name' => 'A.',
                'User_Last_Name' => 'Administrator',
                'Gender' => 'Male',
            ]
        );

        \App\Models\Login::updateOrCreate(
            ['User_ID' => 'special'],
            [
                'account_type' => 2,
                'Password' => 'special123',
                'User_First_Name' => 'Partner',
                'User_Middle_Name' => 'B.',
                'User_Last_Name' => 'Associate',
                'Gender' => 'Male',
            ]
        );

        \App\Models\Login::updateOrCreate(
            ['User_ID' => 'user'],
            [
                'account_type' => 3,
                'Password' => 'user123',
                'User_First_Name' => 'Regular',
                'User_Middle_Name' => 'C.',
                'User_Last_Name' => 'Staff',
                'Gender' => 'Male',
            ]
        );
    }
}
