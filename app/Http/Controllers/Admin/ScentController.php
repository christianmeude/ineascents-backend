<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Scent;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class ScentController extends Controller
{
    public function index()
    {
        $scents = Scent::orderBy('id')->get();

        return Inertia::render('Scents/Index', [
            'scents' => $scents,
        ]);
    }

    public function toggle(Scent $scent)
    {
        $scent->is_available = ! $scent->is_available;
        $scent->save();

        Cache::forget('catalog:packages:index');
        foreach ($scent->packages()->pluck('packages.id') as $packageId) {
            Cache::forget("catalog:packages:{$packageId}");
        }

        return redirect()->back()->with('success', 'Scent availability updated successfully.');
    }
}
