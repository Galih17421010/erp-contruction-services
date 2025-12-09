<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $query = Inventory::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('item_name', 'like', "%{$search}%")
                    ->orWhere('item_code', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        if ($request->has('category') && $request->category != '') {
            $query->where('category', $request->category);
        }

        if ($request->has('status') && $request->status != '') {
            if ($request->status == 'low_stock') {
                $query->lowStock();
            } elseif ($request->status == 'out_of_stock') {
                $query->outOfStock();
            }
        }

        $inventories = $query->latest()->paginate(20);

        return view('inventory.index', compact('inventories'));
    }

    public function create()
    {
        return view('inventory.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'item_name' => 'required|string|max:255',
            'category' => 'required|in:electrical,mechanical,tools,consumables',
            'description' => 'nullable|string',
            'unit' => 'required|string|max:50',
            'quantity' => 'required|numeric|min:0',
            'minimum_stock' => 'required|numeric|min:0',
            'unit_price' => 'required|numeric|min:0',
            'supplier' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255'
        ]);

        $validated['item_code'] = $this->generateItemCode();
        $validated['status'] = $this->determineStatus($validated['quantity'], $validated['minimum_stock']);

        Inventory::create($validated);

        return redirect()->route('inventory.index')->with('success', 'Item Inventory berhasil ditambahkan');
    }

    public function show(Inventory $inventory)
    {
        $inventory->load('stockMovement.project');

        return view('inventory.show', compact('inventory'));
    }

    public function edit(Inventory $inventory)
    {
        return view('inventory.edit', compact('inventory'));
    }

    public function update(Request $request, Inventory $inventory)
    {
        $validated = $request->validate([
            'item_name' => 'required|string|max:255',
            'category' => 'required|in:electrical,mechanical,tools,consumables',
            'description' => 'nullable|string',
            'unit' => 'required|string|max:50',
            'minimum_stock' => 'required|numeric|min:0',
            'unit_price' =>  'required|numeric|min:0',
            'supplier' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255'
        ]);

        $validated['status'] = $this->determineStatus($inventory->quantity, $validated['minimum_stock']);

        $inventory->update($validated);

        return redirect()->route('inventory.show', $inventory)->with('success', 'Item inventory berhasil diupdate');
    }

    public function destroy(Inventory $inventory)
    {
        $inventory->delete();

        return redirect()->route('inventory.index')->with('success', 'Item inventory berhasil dihapus');
    }

    public function adjustStock(Request $request, Inventory $inventory){
        $validated = $request->validate([
            'movement_type' => 'required|in:in,out,adjustment',
            'quantity' => 'required|numeric|min:0.01',
            'project_id' => 'nullable|exist:projects,id',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|strong'
        ]);

        DB::beginTransaction();
        try {
            // Calculate new quantity
        } catch (\Throwable $th) {
            //throw $th;
        }
    }
}
