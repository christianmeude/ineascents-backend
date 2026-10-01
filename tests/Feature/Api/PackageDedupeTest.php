<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\Package;
use App\Models\Scent;
use App\Services\DuplicateCatalogMerger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PackageDedupeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Twins predate the unique indexes, so drop them to fabricate the
        // pre-fix state — exactly what the merge migration sees in prod.
        Schema::table('packages', fn ($table) => $table->dropUnique(['name']));
        Schema::table('scents', fn ($table) => $table->dropUnique(['name']));
    }

    public function test_merge_packages_keeps_enriched_survivor_and_repoints_bookings(): void
    {
        // NOTE: the seed migration already inserts the real offering on
        // migrate, so fabricate a distinct twin name here.
        $stale = Package::create(['name' => 'Twin Package', 'price' => 4499]);
        $enriched = Package::create(['name' => 'Twin Package', 'price' => 4499]);

        $s1 = Scent::create(['name' => 'Twin Lavender']);
        $s2 = Scent::create(['name' => 'Twin Vanilla']);
        $enriched->scents()->attach([$s1->id, $s2->id]);

        $booking = Booking::create([
            'booking_reference' => 'BOOKING-DUPE01',
            'package_id' => $stale->id,
            'customer_name' => 'Dupe Customer',
            'customer_email' => 'dupe@example.com',
            'pax' => 50,
            'event_date' => now()->addDays(5)->format('Y-m-d'),
            'event_time' => '14:00:00',
            'venue_address' => 'Scent Studio A',
            'status' => 'Confirmed',
            'payment_method' => 'online',
            'total_price' => 4499,
        ]);

        $cancelled = Booking::create([
            'booking_reference' => 'BOOKING-DUPE01X',
            'package_id' => $stale->id,
            'customer_name' => 'Cancelled Customer',
            'customer_email' => 'cancelled@example.com',
            'pax' => 70,
            'event_date' => now()->addDays(6)->format('Y-m-d'),
            'event_time' => '10:00:00',
            'venue_address' => 'Scent Studio B',
            'status' => 'Cancelled',
            'payment_method' => 'cash',
            'total_price' => 6399,
        ]);

        $merged = (new DuplicateCatalogMerger)->mergePackages();

        $this->assertSame(1, $merged);
        $this->assertSame(1, Package::where('name', 'Twin Package')->count());

        $survivor = Package::where('name', 'Twin Package')->first();
        $this->assertSame($enriched->id, $survivor->id);
        $this->assertEqualsCanonicalizing(
            [$s1->id, $s2->id],
            $survivor->scents()->pluck('scents.id')->all()
        );

        // Booking history survives on the survivor — never cascade-wiped.
        // Cancelled rows repoint too: they stay readable, FK stays valid.
        $this->assertSame($survivor->id, $booking->fresh()->package_id);
        $this->assertSame('Confirmed', $booking->fresh()->status->value);
        $this->assertSame($survivor->id, $cancelled->fresh()->package_id);
        $this->assertSame('Cancelled', $cancelled->fresh()->status->value);
    }

    public function test_merge_scents_repoints_pivots_and_booking_links(): void
    {
        $package = Package::create(['name' => 'Solo Package', 'price' => 100]);

        $original = Scent::create(['name' => 'Lavender Dream']);
        $clone = Scent::create(['name' => 'Lavender Dream']);
        $package->scents()->attach($original->id);

        $booking = Booking::create([
            'booking_reference' => 'BOOKING-DUPE02',
            'package_id' => $package->id,
            'customer_name' => 'Scent Customer',
            'customer_email' => 'scent@example.com',
            'pax' => 50,
            'event_date' => now()->addDays(5)->format('Y-m-d'),
            'event_time' => '14:00:00',
            'venue_address' => 'Scent Studio A',
            'status' => 'Pending',
            'payment_method' => 'cash',
            'total_price' => 100,
        ]);
        $booking->scents()->attach($clone->id);

        $merged = (new DuplicateCatalogMerger)->mergeScents();

        $this->assertSame(1, $merged);
        $this->assertSame(1, Scent::where('name', 'Lavender Dream')->count());

        $survivor = Scent::where('name', 'Lavender Dream')->first();
        $this->assertSame($original->id, $survivor->id);
        $this->assertTrue($package->scents()->where('scents.id', $survivor->id)->exists());
        $this->assertTrue($booking->scents()->where('scents.id', $survivor->id)->exists());
    }

    public function test_api_lists_each_package_id_once_after_merge(): void
    {
        Package::create(['name' => 'Essential 10ml Perfume Bar', 'price' => 4499]);
        Package::create(['name' => 'Essential 10ml Perfume Bar', 'price' => 4499]);

        (new DuplicateCatalogMerger)();

        $response = $this->getJson('/api/packages');

        $response->assertStatus(200)
            ->assertValidRequest()
            ->assertValidResponse(200);

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame($ids, array_unique($ids));
        $this->assertCount(
            1,
            collect($response->json('data'))->where('name', 'Essential 10ml Perfume Bar')->all()
        );
    }

    public function test_seeder_is_idempotent(): void
    {
        Artisan::call('db:seed');
        $first = [
            Package::where('name', 'Essential 10ml Perfume Bar')->count(),
            Scent::count(),
            Booking::count(),
        ];

        Artisan::call('db:seed');
        $second = [
            Package::where('name', 'Essential 10ml Perfume Bar')->count(),
            Scent::count(),
            Booking::count(),
        ];

        $this->assertSame([1, 5, 0], $first);
        $this->assertSame($first, $second);
    }
}
