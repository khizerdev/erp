<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use DataTables;
use Illuminate\Validation\ValidationException;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Product;
use App\Models\Vendor;
use App\Models\Particular;
use App\Models\ErpDepartment;


use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */

     public static function middleware(): array
     {
         return [
             'permission:list-product|create-product|edit-product|delete-product' => ['only' => ['index', 'store']],
             'permission:create-product' => ['only' => ['create', 'store']],
             'permission:edit-product' => ['only' => ['edit', 'update']],
             'permission:delete-product' => ['only' => ['destroy']],
         ];
     }

     public function index(Request $request)
     {
         if ($request->ajax()) {
             $data = Product::latest()->get();
             return DataTables::of($data)
                ->addColumn('action', function($row){
                    $editUrl = route('products.edit', $row->id);
                    $deleteUrl = route('products.destroy', $row->id);

                    $btn = '<a href="'.$editUrl.'" class="edit btn btn-primary btn-sm">Edit</a>';
                    $btn .= ' <a href="'.$deleteUrl.'" data-id="'.$row->id.'" class="delete btn btn-danger btn-sm">Delete</a>';
                    return $btn;
                })
                 ->rawColumns(['action'])
                 ->make(true);
         }
         return view('pages.products.index');
     }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $particulars= Particular::all();
        $departments= ErpDepartment::all();
        return view('pages.products.create', compact('particulars','departments'));
    }

    /**
     * Store a newly created resource in storage.
     */

public function store(Request $request)
{
    // Validate the incoming request data
    $validatedData = $request->validate([
        'name' => 'required|string|max:255',
        'sub_department' => 'nullable', // Changed from 'required' to 'nullable'
        'category' => 'required',
        'type' => 'nullable', // Changed from 'required' to 'nullable'
        'opening_quantity' => 'required|integer|min:0',
        'opening_inventory' => 'required|numeric|min:0',
        'total_price' => 'required|numeric|min:0',
        'min_qty_limit' => 'required|string|max:255',
        'unit' => 'required|string|max:11',
    ]);
    
    // Generate a serial number (you can customize this as needed)
    $serialNo = 'PROD-' . Str::upper(Str::random(8));
    
    // Create the product with mapped fields
    $product = Product::create([
        'name' => $validatedData['name'],
        'serial_no' => $serialNo,
        'department_id' => $validatedData['sub_department'] ?: null, // Use null if empty
        'material_id' => $validatedData['type'] ?: null, // Use null if empty
        'particular_id' => $validatedData['category'], // Mapping category to particular_id
        'qty' => $validatedData['opening_quantity'], // Mapping opening_quantity to qty
        'inventory_price' => $validatedData['opening_inventory'], // Mapping opening_inventory to inventory_price
        'total_price' => $validatedData['total_price'],
        'min_qty_limit' => $validatedData['min_qty_limit'],
        'unit' => $validatedData['unit'],
    ]);
    
    // Redirect with success message
    return redirect()->route('products.index')
        ->with('success', 'Product created successfully.');
}

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $product = Product::findOrFail($id);
        return view('pages.products.edit',compact('product'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
{
    // Validate with same rules as store
    $validatedData = $request->validate([
        'name' => 'required|string|max:255',
        'sub_department' => 'nullable',
        'category' => 'required',
        'type' => 'nullable',
        'qty' => 'required|integer|min:0',
        'inventory_price' => 'required|numeric|min:0',
        'total_price' => 'required|numeric|min:0',
        'min_qty_limit' => 'required|string|max:255',
        'unit' => 'required|string|max:11',
    ]);

    try {
        $product = Product::findOrFail($id);

        // Update with proper mappings
        $product->update([
            'name' => $validatedData['name'],
            'department_id' => $validatedData['sub_department'] ?: null,
            'material_id' => $validatedData['type'] ?: null,
            'particular_id' => $validatedData['category'],
            'qty' => $validatedData['qty'],
            'inventory_price' => $validatedData['inventory_price'],
            'total_price' => $validatedData['total_price'],
            'min_qty_limit' => $validatedData['min_qty_limit'],
            'unit' => $validatedData['unit'],
        ]);

        return redirect()->route('products.index')
            ->with('success', 'Product updated successfully.');
    } catch (\Exception $e) {
        return redirect()->back()
            ->with('error', 'Failed to update product: ' . $e->getMessage());
    }
}


    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $product = Product::findOrFail($id);
            $product->delete();
    
            return response()->json(['message' => 'Product deleted successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to delete product', 'error' => $e->getMessage()], 500);
        }
    }

    public function count()
    {
        $data = [
            'products' => Product::count(),
            'vendors' => Vendor::count(),
            'customers' => Customer::count(),
            'employees' => Employee::count(),
        ];

        return response()->json($data);
    }
    
}
