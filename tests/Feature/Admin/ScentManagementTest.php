<?php

namespace Tests\Feature\Admin;

use App\Models\Package;
use App\Models\Scent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ScentManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    private function makeScent(array $overrides = []): Scent
    {
        return Scent::create(array_merge([
            'name' => 'Test Scent ' . uniqid(),
            'category' => 'women',
            'image_url' => 'test.png',
            'is_available' => true,
        ], $overrides));
    }

    public function test_index_lists_scents_with_category_and_availability()
    {
        $this->makeScent(['name' => 'Cloud', 'category' => 'women']);
        $this->makeScent(['name' => 'Aventus', 'category' => 'men', 'is_available' => false]);

        $this->actingAs($this->admin)->get(route('admin.scents.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Scents/Index')
                ->has('scents', 2)
                ->where('scents.0.category', 'women')
                ->where('scents.1.is_available', false)
            );
    }

    public function test_toggle_flips_is_available_only()
    {
        $scent = $this->makeScent(['name' => 'Cloud', 'category' => 'women']);

        $this->actingAs($this->admin)->post(route('admin.scents.toggle', $scent))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('scents', ['id' => $scent->id, 'is_available' => false]);

        // Extra payload cannot rename or recategorize: only the flag flips.
        $this->actingAs($this->admin)->post(route('admin.scents.toggle', $scent), [
            'name' => 'Hacked', 'category' => 'men', 'is_available' => false,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('scents', [
            'id' => $scent->id, 'name' => 'Cloud', 'category' => 'women', 'is_available' => true,
        ]);
    }

    public function test_package_api_reflects_toggled_availability()
    {
        $package = Package::create(['name' => 'Signature', 'description' => 'Test', 'price' => 1000]);
        $scent = $this->makeScent();
        $package->scents()->sync([$scent->id]);

        $this->getJson("/api/packages/{$package->id}")->assertOk()
            ->assertJsonPath('data.scents.0.is_available', true);

        $this->actingAs($this->admin)->post(route('admin.scents.toggle', $scent))
            ->assertSessionHasNoErrors();

        $this->getJson("/api/packages/{$package->id}")->assertOk()
            ->assertJsonPath('data.scents.0.is_available', false);
    }

    public function test_guest_is_redirected_to_admin_login()
    {
        $scent = $this->makeScent();

        $this->get(route('admin.scents.index'))->assertRedirect('/admin/login');
        $this->post(route('admin.scents.toggle', $scent))->assertRedirect('/admin/login');
    }

    public function test_non_admin_gets_not_found()
    {
        $user = User::factory()->create(['is_admin' => false]);
        $scent = $this->makeScent();

        $this->actingAs($user)->get(route('admin.scents.index'))->assertNotFound();
        $this->actingAs($user)->post(route('admin.scents.toggle', $scent))->assertNotFound();
    }
}
