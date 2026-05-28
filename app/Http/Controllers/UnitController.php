<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    public function index()
    {
        $units = Unit::withCount('products')->orderBy('name')->paginate(20);
        return view('units.index', compact('units'));
    }

    public function create()
    {
        return view('units.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'         => 'required|string|max:255|unique:units',
            'abbreviation' => 'required|string|max:10',
        ]);
        Unit::create($request->only('name', 'abbreviation'));
        return redirect()->route('units.index')->with('success', 'Unit created.');
    }

    public function edit(Unit $unit)
    {
        return view('units.edit', compact('unit'));
    }

    public function update(Request $request, Unit $unit)
    {
        $request->validate([
            'name'         => 'required|string|max:255|unique:units,name,' . $unit->id,
            'abbreviation' => 'required|string|max:10',
        ]);
        $unit->update($request->only('name', 'abbreviation'));
        return redirect()->route('units.index')->with('success', 'Unit updated.');
    }

    public function destroy(Unit $unit)
    {
        if ($unit->products()->count() > 0) {
            return back()->with('error', 'Cannot delete unit in use by products.');
        }
        $unit->delete();
        return redirect()->route('units.index')->with('success', 'Unit deleted.');
    }
}
