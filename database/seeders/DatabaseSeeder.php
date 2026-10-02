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
        // Promo catalog: 8 official inspired scents, image_url matches client bundle filenames.
        // Names mirror ineascents-app lib/config/scents.dart displayName (C168 asset map matches on these).
        $scentsData = [
            ['name' => 'Ariana Cloud', 'category' => 'women', 'image_url' => 'ariana-cloud.png', 'description' => 'Bright, sweet gourmand for women.'],
            ['name' => 'Burberry Her', 'category' => 'women', 'image_url' => 'burberry.png', 'description' => 'Fruity-floral signature for women.'],
            ['name' => 'Versace Bright Crystal', 'category' => 'women', 'image_url' => 'versace-bright.png', 'description' => 'Fresh, radiant floral for women.'],
            ['name' => 'Jo Malone Nectarine Blossom & Honey', 'category' => 'women', 'image_url' => 'jm-nectarine.png', 'description' => 'Juicy nectarine with honeyed warmth.'],
            ['name' => '1 Million', 'category' => 'men', 'image_url' => 'one-million.png', 'description' => 'Bold, spicy statement for men.'],
            ['name' => 'Creed Aventus', 'category' => 'men', 'image_url' => 'creed-aventus.png', 'description' => 'Smoky pineapple icon for men.'],
            ['name' => 'Versace Eros', 'category' => 'men', 'image_url' => 'versace-eros.png', 'description' => 'Fresh, magnetic classic for men.'],
            ['name' => 'Clinique Happy for Men', 'category' => 'men', 'image_url' => 'clinique-happy.png', 'description' => 'Crisp citrus uplift for men.'],
        ];

        // Rename map: prior long promo names → official bundle display names.
        // UPDATE in place so ids survive (booking_scent.scent_id and
        // package_scent.scent_id are cascadeOnDelete — delete+insert would
        // wipe linked rows and issue new ids).
        $renameMap = [
            'Ariana Grande Cloud Eau de Parfum' => 'Ariana Cloud',
            'Burberry Her Eau de Parfum' => 'Burberry Her',
            'Versace Bright Crystal Eau de Toilette' => 'Versace Bright Crystal',
            'Jo Malone London Nectarine Blossom & Honey Cologne' => 'Jo Malone Nectarine Blossom & Honey',
            'Rabanne 1 Million Eau de Toilette' => '1 Million',
            'Creed Aventus Eau de Parfum' => 'Creed Aventus',
            'Versace Eros Eau de Toilette' => 'Versace Eros',
            'Clinique Happy for Men Cologne Spray' => 'Clinique Happy for Men',
        ];

        $byName = [];
        foreach ($scentsData as $data) {
            $byName[$data['name']] = $data;
        }

        foreach ($renameMap as $old => $new) {
            $legacy = \App\Models\Scent::where('name', $old)->first();
            if (! $legacy) {
                continue;
            }
            $target = \App\Models\Scent::where('name', $new)->first();
            if ($target && $target->id !== $legacy->id) {
                // Both rows exist: move pivot links onto the canonical row,
                // skipping pairs it already has, then drop the duplicate.
                foreach (['package_scent', 'booking_scent'] as $pivot) {
                    $other = $pivot === 'package_scent' ? 'package_id' : 'booking_id';
                    $existing = \Illuminate\Support\Facades\DB::table($pivot)
                        ->where('scent_id', $target->id)
                        ->pluck($other)
                        ->all();
                    $rows = \Illuminate\Support\Facades\DB::table($pivot)
                        ->where('scent_id', $legacy->id)
                        ->whereNotIn($other, $existing ?: [0])
                        ->get();
                    foreach ($rows as $row) {
                        \Illuminate\Support\Facades\DB::table($pivot)->insert([
                            'scent_id' => $target->id,
                            $other => $row->{$other},
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                    \Illuminate\Support\Facades\DB::table($pivot)
                        ->where('scent_id', $legacy->id)
                        ->delete();
                }
                $legacy->delete();
            } else {
                $legacy->update(array_merge($byName[$new], ['is_available' => true]));
            }
        }

        // Prune pre-promo rows: updateOrCreate by name never deletes,
        // so remove anything outside the canonical 8 before sync.
        \App\Models\Scent::whereNotIn('name', array_column($scentsData, 'name'))->delete();

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
