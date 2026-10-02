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
        // Promo catalog: 8 inspired scents, image_url matches client bundle filenames.
        $scentsData = [
            ['name' => 'Ariana Grande Cloud Eau de Parfum', 'category' => 'women', 'image_url' => 'ariana-cloud.png', 'description' => 'Bright, sweet gourmand for women.'],
            ['name' => 'Burberry Her Eau de Parfum', 'category' => 'women', 'image_url' => 'burberry.png', 'description' => 'Fruity-floral signature for women.'],
            ['name' => 'Versace Bright Crystal Eau de Toilette', 'category' => 'women', 'image_url' => 'versace-bright.png', 'description' => 'Fresh, radiant floral for women.'],
            ['name' => 'Jo Malone London Nectarine Blossom & Honey Cologne', 'category' => 'women', 'image_url' => 'jm-nectarine.png', 'description' => 'Juicy nectarine with honeyed warmth.'],
            ['name' => 'Rabanne 1 Million Eau de Toilette', 'category' => 'men', 'image_url' => 'one-million.png', 'description' => 'Bold, spicy statement for men.'],
            ['name' => 'Creed Aventus Eau de Parfum', 'category' => 'men', 'image_url' => 'creed-aventus.png', 'description' => 'Smoky pineapple icon for men.'],
            ['name' => 'Versace Eros Eau de Toilette', 'category' => 'men', 'image_url' => 'versace-eros.png', 'description' => 'Fresh, magnetic classic for men.'],
            ['name' => 'Clinique Happy for Men Cologne Spray', 'category' => 'men', 'image_url' => 'clinique-happy.png', 'description' => 'Crisp citrus uplift for men.'],
        ];

        $scents = [];
        foreach ($scentsData as $data) {
            $scents[] = \App\Models\Scent::updateOrCreate(
                ['name' => $data['name']],
                [
                    'category' => $data['category'],
                    'image_url' => $data['image_url'],
                    'description' => $data['description'],
                    'is_available' => true,
                ]
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

        // 4. Attach Scents to Package — the full promo set of 8.
        // sync (not attach): re-runs converge instead of stacking links.
        $package->scents()->sync(collect($scents)->map->id->all());
    }
}
