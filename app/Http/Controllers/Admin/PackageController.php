<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class PackageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $packages = Package::all();

        return Inertia::render('Packages/Index', [
            'packages' => $packages,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('Packages/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    /**
     * Tier map wins when present: options derive from its keys and price
     * becomes the starts-at minimum. Invalid pairs drop silently via the
     * sanitizer; legacy pax_options + explicit price apply otherwise.
     */
    private function withTiers(array $validated): array
    {
        $tiers = \App\Support\PackageSanitizer::paxPrices($validated['pax_prices'] ?? null);

        if (count($tiers) > 0) {
            ksort($tiers);
            $validated['pax_prices'] = $tiers;
            $validated['pax_options'] = array_map('intval', array_keys($tiers));
            $validated['price'] = min($tiers);
        } else {
            $validated['pax_prices'] = [];
            $validated['pax_options'] = \App\Support\PackageSanitizer::paxOptions($validated['pax_options'] ?? null);
        }

        return $validated;
    }

    private function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'inclusions' => 'nullable|array',
            'inclusions.*' => 'nullable|string|max:255',
            'pax_options' => 'nullable|array',
            'pax_options.*' => 'nullable|integer|min:1',
            'pax_prices' => 'nullable|array',
            'pax_prices.*' => 'nullable|numeric',
            'freebies' => 'nullable|array',
            'freebies.*' => 'nullable|string|max:255',
            'price' => 'required|numeric|min:0',
            'images' => 'nullable|array|max:3',
        ];
    }

    public function store(Request $request, \App\Services\ImageUploader $imageUploader)
    {
        $validated = $this->withTiers($request->validate($this->rules()));

        $validated['inclusions'] = \App\Support\PackageSanitizer::strings($validated['inclusions'] ?? null);
        $validated['freebies'] = \App\Support\PackageSanitizer::strings($validated['freebies'] ?? null);

        if (isset($validated['images'])) {
            $validated['images'] = $imageUploader->uploadMultiple($request->images);
        }

        Package::create($validated);

        Cache::forget('catalog:packages:index');

        return redirect()->route('admin.packages.index')->with('success', 'Package created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Package $package)
    {
        // Not used, using edit instead.
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Package $package)
    {
        return Inertia::render('Packages/Edit', [
            'package' => $package,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Package $package, \App\Services\ImageUploader $imageUploader)
    {
        $validated = $this->withTiers($request->validate($this->rules()));

        $validated['inclusions'] = \App\Support\PackageSanitizer::strings($validated['inclusions'] ?? null);
        $validated['freebies'] = \App\Support\PackageSanitizer::strings($validated['freebies'] ?? null);

        if (isset($validated['images'])) {
            $validated['images'] = $imageUploader->uploadMultiple($request->images);
        } else {
            $validated['images'] = [];
        }

        $package->update($validated);

        Cache::forget('catalog:packages:index');
        Cache::forget("catalog:packages:{$package->id}");

        return redirect()->route('admin.packages.index')->with('success', 'Package updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Package $package)
    {
        $package->delete();

        Cache::forget('catalog:packages:index');
        Cache::forget("catalog:packages:{$package->id}");

        return redirect()->route('admin.packages.index')->with('success', 'Package deleted successfully.');
    }

}
