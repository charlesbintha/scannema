<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create Organization
        $organization = DB::table('organizations')->insertGetId([
            'name' => 'Scannema',
            'slug' => 'scannema',
            'description' => 'Event check-in system with QR code scanning',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create Users
        DB::table('users')->insert([
            'organization_id' => $organization,
            'name' => 'Charles Bintha',
            'email' => 'charlesbintha@gmail.com',
            'username' => 'charlesbintha',
            'password_hash' => Hash::make('password'),
            'role' => 'SUPER_ADMIN',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('users')->insert([
            'organization_id' => $organization,
            'name' => 'Agent Scanner',
            'email' => 'scanner@scannema.com',
            'username' => 'scanner',
            'password_hash' => Hash::make('scanner2026'),
            'role' => 'CHECKER',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create Event
        $event = DB::table('events')->insertGetId([
            'organization_id' => $organization,
            'name' => 'Concert Solidaire',
            'code' => 'CONCERT-SOLIDAIRE-2026',
            'status' => 'LIVE',
            'timezone' => 'Africa/Abidjan',
            'expected_guests' => 1000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create Checkpoint
        $checkpoint = DB::table('checkpoints')->insertGetId([
            'event_id' => $event,
            'name' => 'Entree Principale',
            'location_label' => 'Porte A',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Run InvitationSeeder
        $this->call(InvitationSeeder::class);
    }
}
