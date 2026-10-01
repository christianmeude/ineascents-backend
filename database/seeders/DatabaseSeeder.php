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
        // 1. Create a Super Admin User
        $user = User::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@example.com')],
            [
                'name' => env('ADMIN_NAME', 'Super Admin'),
                'password' => bcrypt(env('ADMIN_PASSWORD', 'password')),
                'is_admin' => true,
            ]
        );

        // 2. Create Scents — idempotent: re-runs reuse rows by name.
        $scentsData = [
            ['name' => 'Lavender Dream', 'description' => 'A calming, floral lavender aroma.'],
            ['name' => 'Vanilla Bean', 'description' => 'Sweet, warm, and comforting vanilla.'],
            ['name' => 'Ocean Breeze', 'description' => 'Crisp, clean, and refreshing marine notes.'],
            ['name' => 'Citrus Burst', 'description' => 'Energizing orange, lemon, and grapefruit.'],
            ['name' => 'Sandalwood Spice', 'description' => 'Earthy, woody, and slightly spicy.'],
        ];

        $scents = [];
        foreach ($scentsData as $data) {
            $scents[] = \App\Models\Scent::updateOrCreate(
                ['name' => $data['name']],
                ['description' => $data['description']]
            );
        }

        // 3. Create Packages — single real offering with pax tiers.
        // Idempotent: re-runs update the row by name instead of cloning it
        // (each clone used to render as another full set of package cards).
        // Prices live in pax_prices; scalar price is the "starts at" base.
        $tiers = [50 => 4499.00, 70 => 6399.00, 100 => 8799.00, 150 => 13119.00];
        $package = \App\Models\Package::updateOrCreate(
            ['name' => 'Essential 10ml Perfume Bar'],
            [
                'description' => 'Perfume bar service starting at Php 4,499.00. One booking lasts 3–4 hrs.',
                'price' => min($tiers),
                'inclusions' => [
                    'Featuring your logo and a hemp cord',
                    '4 inspired scents',
                    'Perfume Bar set up',
                    'Claim Stub',
                    'Duration: 3 hrs to 4 hrs',
                    '2 Staff Members',
                ],
                'pax_options' => array_keys($tiers),
                'pax_prices' => $tiers,
                'freebies' => ['Selfie Mirror', '1 gift for celebrant'],
                'images' => [],
                'gallery_images' => [],
                'rating' => 0.00, // Unrated
            ]
        );

        // 4. Attach Scents to Package — the frozen 4 included scents.
        // sync (not attach): re-runs converge instead of stacking links.
        $package->scents()->sync([$scents[0]->id, $scents[1]->id, $scents[2]->id, $scents[3]->id]);
    }
}
