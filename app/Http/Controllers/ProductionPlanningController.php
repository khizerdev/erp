<?php

namespace App\Http\Controllers;

use App\Models\ProductionPlanning;
use App\Models\SaleOrderItem;
use Illuminate\Http\Request;
use DataTables;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProductionPlanningController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = ProductionPlanning::with('machine')->latest()->get();
            return Datatables::of($data)
                ->addIndexColumn()
                ->addColumn('action', function($row){
                    $editUrl = route('production-plannings.edit', $row->id);
                    $showUrl = route('production-plannings.show', $row->id);
                    
                    $btn = '<a href="'.$editUrl.'" class="edit btn btn-primary btn-sm mr-2">Edit</a>';
                    $btn .= '<a href="'.$showUrl.'" target="blank" class="edit btn btn-success btn-sm">View</a>';
                    $btn .= ' <button onclick="deleteRecord('.$row->id.')" class="delete btn btn-danger btn-sm">Delete</button>';
                    
                    // $btn = $edit;
                    return $btn;
                })
                ->rawColumns(['action'])
                ->make(true);
        }
        
        return view('pages.production_plannings.index');
    }
public function create()
{
    // Fetch all open sale orders with their items
    $openSaleOrders = \App\Models\SaleOrder::with(['items.design', 'items.color', 'customer'])
                        ->where('order_status', 'open') // adjust column name for open status
                        ->get();

    return view('pages.production_plannings.create', compact('openSaleOrders'));
}

    public function reportForm()
    {
        return view('pages.reports.production_planning.index');
    }

    public function generateReport(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = Carbon::parse($request->start_date)->startOfDay();
        $endDate = Carbon::parse($request->end_date)->endOfDay();

        $productionPlannings = ProductionPlanning::with([
            'machine',
            'items.saleOrder.customer',
            'items.design',
            'items.color'
        ])
        ->whereBetween('date', [$startDate, $endDate])
        ->orderBy('date', 'asc')
        ->orderBy('machine_id', 'asc')
        ->get();

        return view('pages.reports.production_planning.show', compact('productionPlannings', 'startDate', 'endDate'));
    }
    
    public function show($id)
    {
        $productionPlanning = ProductionPlanning::with('planningItems')->find($id);
        
        return view('pages.production_plannings.view', compact('productionPlanning'));
    }

   public function store(Request $request)
{
    $request->validate([
        'date' => 'required|date',
        'machine_id' => 'required|exists:machines,id',
        'selected_items' => 'required|array|min:1',
        'selected_items.*' => 'exists:sale_order_items,id',
    ]);

    $selectedItems = $request->input('selected_items', []);
    $errors = [];
    $itemsData = [];

    // Use database transaction for data consistency
    DB::beginTransaction();
    
    try {
        // First, validate all selected items and collect their data
        foreach ($selectedItems as $itemId) {
            // Get the planned quantities for this specific item
            $plannedQtyKey = "planned_qty_{$itemId}";
            $plannedLaceQtyKey = "planned_lace_qty_{$itemId}";
            
            $plannedQty = $request->input($plannedQtyKey);
            $plannedLaceQty = $request->input($plannedLaceQtyKey);
            
            // Validate that quantities are provided
            if (empty($plannedQty) || (empty($plannedLaceQty) && $plannedLaceQty !== '0')) {
                $errors[] = "Missing planned quantities for item ID: {$itemId}";
                continue;
            }
            
            // Additional validation for numeric values
            if (!is_numeric($plannedQty) || !is_numeric($plannedLaceQty)) {
                $errors[] = "Invalid quantity format for item ID: {$itemId}";
                continue;
            }
            
            $plannedQty = (int) $plannedQty;
            $plannedLaceQty = (int) $plannedLaceQty;
            
            // Basic validation
            if ($plannedQty < 1) {
                $errors[] = "Planned quantity must be at least 1 for item ID: {$itemId}";
                continue;
            }
            
            if ($plannedLaceQty < 0) {
                $errors[] = "Planned lace quantity cannot be negative for item ID: {$itemId}";
                continue;
            }
            
            // Get the sale order item to validate quantities
            $saleOrderItem = SaleOrderItem::findOrFail($itemId);
            
            // Validate against original quantities
            if ($plannedQty > $saleOrderItem->qty) {
                $errors[] = "Planned quantity ({$plannedQty}) cannot exceed original quantity ({$saleOrderItem->qty}) for item: {$saleOrderItem->design->design_code}";
                continue;
            }
            
            if ($plannedLaceQty > $saleOrderItem->lace_qty) {
                $errors[] = "Planned lace quantity ({$plannedLaceQty}) cannot exceed original quantity ({$saleOrderItem->lace_qty}) for item: {$saleOrderItem->design->design_code}";
                continue;
            }
            
            // Store valid item data
            $itemsData[] = [
                'sale_order_item_id' => $itemId,
                'planned_qty' => $plannedQty,
                'planned_lace_qty' => $plannedLaceQty,
                'design_code' => $saleOrderItem->design->design_code
            ];
        }
        
        // If there are validation errors, return them
        if (!empty($errors)) {
            DB::rollBack();
            return back()->withErrors([
                'validation_errors' => 'Please fix the following errors:',
                'item_errors' => $errors
            ])->withInput();
        }
        
        // Check if production planning already exists for this date/machine/sale_order combination
        $existingPlanning = ProductionPlanning::where([
            'date' => $request->date,
            'machine_id' => $request->machine_id,
            'sale_order_id' => $request->sale_order_id,
        ])->first();
        
        if ($existingPlanning) {
            DB::rollBack();
            return back()->withErrors([
                'duplicate_error' => 'Production planning already exists for this sale order on the selected date and machine.'
            ])->withInput();
        }
        
        // Calculate total quantities across all items
        $totalPlannedQty = array_sum(array_column($itemsData, 'planned_qty'));
        $totalPlannedLaceQty = array_sum(array_column($itemsData, 'planned_lace_qty'));
        
        // Create the single production planning record
        $productionPlanning = ProductionPlanning::create([
            'date' => $request->date,
            'machine_id' => $request->machine_id,
            'sale_order_id' => $request->sale_order_id,
        ]);
        
        // Create the linking records in a pivot/junction table
        foreach ($itemsData as $itemData) {
    DB::table('production_planning_items')->updateOrInsert(
        [
            'production_planning_id' => $productionPlanning->id,
            'sale_order_item_id' => $itemData['sale_order_item_id'],
        ],
        [
            'planned_qty' => $itemData['planned_qty'],
            'planned_lace_qty' => $itemData['planned_lace_qty'],
            'updated_at' => now(),
            'created_at' => now(),
        ]
    );
}

        
        DB::commit();
        
        $itemCount = count($itemsData);
        
        
        return redirect()->route('production-plannings.index')
                         ->with('success', 'Success');
                         
    } catch (\Exception $e) {
        DB::rollBack();
        dd($e);
        return back()->withErrors([
            'system_error' => 'An error occurred while creating the production planning record. Please try again.'
        ])->withInput();
    }
}

    public function edit(ProductionPlanning $productionPlanning)
    {
        $productionPlanning->load([
            'saleOrder.customer',
            'saleOrder.items.design',
            'saleOrder.items.color'
        ]);
        
        return view('pages.production_plannings.edit', compact('productionPlanning'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'date' => 'required|date',
        ]);

        $productionPlanning = ProductionPlanning::find($id);
        $productionPlanning->update($request->only('date','machine_id','sale_order_id'));

        return redirect()->route('production-plannings.index')
                         ->with('success', 'Production Planning updated successfully');
    }

    public function destroy($id)
    {
        ProductionPlanning::find($id)->delete();
        return response()->json(['success' => 'Production Planning deleted successfully.']);
    }
}