<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\Project;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockMovementController extends Controller
{
    private function determineStatus($quantity, $minimumStock){
        if ($quantity <= 0) {
            return 'out_of_stock';
        } elseif ($quantity <= $minimumStock) {
            return 'low_stock';
        } else {
            return 'available';
        }
    }
    
    private function generateReferenceNumber(){
        return 'SM' . date('Ymd') . str_pad(StockMovement::whereDate('created_at', today())->count() + 1, 4, '0', STR_PAD_LEFT);
    }

    public function index(Request $request)
    {
        $query = StockMovement::with(['inventory', 'project', 'createdBy']);

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                    ->orWhereHas('inventory', function($q) use ($search) {
                        $q->where('item_name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->has('movement_type') && $request->movement_type != '') {
            $query->where('movement_type', $request->movement_type);
        }

        if ($request->has('inventory_id') && $request->inventory_id != '') {
            $query->where('inventory_id', $request->inventory_id);
        }

        if ($request->has('project_id') && $request->project_id != '') {
            $query->where('project_id', $request->project_id);
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_form);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $movements = $query->latest()->paginate(30);

        $inventories = Inventory::all();
        $projects = Project::whereIn('status', ['planning', 'in_progress'])->get();

        return view('stock-movements.index', compact('movements', 'inventories', 'projects'));
    }
    
    public function create()
    {
        $inventories = Inventory::where('status', '!=', 'out_of_stock')->get();
        $projects = Project::whereIn('status', ['planning', 'in_progress'])->get();

        return view('stock-movements.create', compact('inventories', 'projects'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'inventory_id' => 'required|exist:inventories,id',
            'project_id' => 'nullable|exists:project,id',
            'movement_type' => 'required|in:in,out,adjustment',
            'quantity' => 'required|numeric|min:0.01',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string'
        ]);

        $inventory = Inventory::findOrFail($validated['inventory_id']);

        if ($validated['movement_type'] == 'out' && $inventory->quantity < $validated['quantity']) {
            return back()->withInput()
                        ->with('error', 'Stock tidak mencukupi. Stock terdedia: ' . $inventory->quantity);
        }

        DB::beginTransaction();
        try {
            $oldQuantity = $inventory->quantity;

            switch ($validated['movement_type']) {
                case 'in':
                case 'adjustment':
                    $newQuantity = $oldQuantity + $validated['quantity'];
                    break;
                case 'out':
                    $newQuantity = $oldQuantity - $validated['quantity'];
                    break;
            }

            $inventory->quantity = $newQuantity;
            $inventory->status = $this->determineStatus($newQuantity, $inventory->minimum_stock);
            $inventory->save();

            $movement = StockMovement::create([
                'inventory_id' => $validated['inventory_id'],
                'project_id' => $validated['project_id'],
                'movement_type' => $validated['movement_type'],
                'quantity' => $validated['quantity'],
                'reference_number' => $validated['reference_number'] ?? $this->generateReferenceNumber(),
                'notes' => $validated['notes'],
                'created_by' => auth()->id() ?? 1
            ]);

            if ($validated['movement_type'] == 'out' && $validated['project_id']) {
                $project = Project::find($validated['project_id']);
                $materialCost = $inventory->unit_price * $validated['quantity'];
                $project->actual_cost += $materialCost;
                $project->save();
            }

            DB::commit();

            return redirect()->route('stock-movements.index')
                            ->with('success', 'Stock movement berhasil dicatat');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()
                        ->with('error', 'Terjadi kesalahan : ' . $e->getMessage());
        }
    }

    public function show(StockMovement $stockMovement){
        $stockMovement->load(['inventory', 'project', 'createdBy']);

        return view('stock-movements.show', compact('stockMovement'));
    }

    public function destroy(StockMovement $stockMovement){
        DB::beginTransaction();
        try {
            $inventory = $stockMovement->inventory;

            switch ($stockMovement->movement_type) {
                case 'in':
                case 'adjustment':
                    $inventory->quantity -= $stockMovement->quantity;
                    break;                
                case 'out':
                    $inventory->quantity += $stockMovement->quantity;
                    break;
            }

            $inventory->status = $this->determineStatus($inventory->quantity, $inventory->minimum_stock);
            $inventory->save();

            if ($stockMovement->movement_type == 'out' && $stockMovement->project_id) {
                $project = $stockMovement->project;
                $materialCost = $inventory->unit_price * $stockMovement->quantity;
                $project->actual_cost = max(0, $project->actual_cost - $materialCost);
                $project->save();
            }

            $stockMovement->delete();

            DB::commit();
            
            return redirect()->route('stock-movements.index')
                            ->with('success', 'Stock movement berhasil dihapus');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function report(Request $request){
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->endOfMonth()->format('Y-m-d'));

        $movements = StockMovement::with(['inventory', 'project'])
                                    ->whereBetween('created_at', [$startDate, $endDate])
                                    ->get();
        
        $summary = [
            'total_in' => $movements->where('movement_type', 'in')->sum('quantity'),
            'total_out' => $movements->where('movement_type', 'out')->sum('quantity'),
            'total_adjustment' => $movements->where('movement_type', 'adjustment')->sum('quanity'),
            'by_inventory' => $movements->groupBy('inventory_id')->map(function($items) {
                return [
                    'inventory' => $items->first()->inventory,
                    'total_in' => $items->where('movement_type', 'in')->sum('quantity'),
                    'total_out' => $items->where('movement_type', 'out')->sum('quantity'),
                ];
            }),
            'by_project' => $movements->whereNotNull('project_id')
                                        ->groupBy('project_id')
                                        ->map(function($items) {
                                            return [
                                                'project' => $items->first()->project,
                                                'total_quantity' => $items->sum('quantity'),
                                                'items_count' => $items->count()
                                            ];
                                        })
        ];

        return view('stock-movements.report', compact('movements', 'summary', 'startDate', 'endDate'));
    }

}
