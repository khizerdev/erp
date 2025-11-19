<?php

use App\Http\Controllers\AccountAdjustmentController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\VendorAdjustmentController;
use App\Http\Controllers\AdvanceSalaryController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\ColorCodeController;
use App\Http\Controllers\CustomerAdjustmentController;
use App\Http\Controllers\CustomerReceivableController;
use App\Http\Controllers\DailyProductionController;
use App\Http\Controllers\FabricMeasurementController;
use App\Http\Controllers\GazetteController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\LoanExceptionController;
use App\Http\Controllers\MissScanController;
use App\Http\Controllers\ProductionPlanningController;
use App\Http\Controllers\RawMaterialController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SalaryController;
use App\Http\Controllers\SaleOrderController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\ShiftTransferController;
use App\Http\Controllers\StockAdjustmentController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VendorPayableController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ProductGroupController;
use App\Http\Controllers\ThanIssueController;
use App\Http\Controllers\ThanSupplyController;
use App\Http\Controllers\SupplyReceiptController;
use App\Http\Controllers\ErpDepartmentController;
use App\Http\Controllers\SubErpDepartmentController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\BatchController;
use App\Http\Controllers\LotController;
use App\Http\Controllers\ProductSaleOrderController;
use App\Http\Controllers\ReceiveLoanController;
use App\Http\Controllers\NeedleReportController;
use App\Http\Controllers\DailyProductionReportController;
use App\Models\Attendance;
use Illuminate\Support\Facades\Route;


Route::get('/', function () {
    return view('dashboard')->name('dashboard');
});


Route::middleware(['auth'])->group(function () {
    
    Route::middleware(['role:super-admin|purchase|erp|production'])->group(function () {
        Route::get('vendors', [App\Http\Controllers\VendorController::class, 'index'])->name('vendors.index');
        Route::get('vendors/create', [App\Http\Controllers\VendorController::class, 'create'])->name('vendors.create');
        Route::post('vendors', [App\Http\Controllers\VendorController::class, 'store'])->name('vendors.store');
        Route::get('vendors/{id}/edit', [App\Http\Controllers\VendorController::class, 'edit'])->name('vendors.edit');
        Route::put('vendors/{id}', [App\Http\Controllers\VendorController::class, 'update'])->name('vendors.update');
        Route::get('vendors/{id}', [App\Http\Controllers\VendorController::class, 'destroy'])->name('vendors.destroy');
        Route::resource('product_sale_orders', ProductSaleOrderController::class);
        
        Route::get('/reports/needle', [NeedleReportController::class, 'index'])->name('needle.index');
        Route::post('/reports/needle', [NeedleReportController::class, 'report'])->name('needle.report');
        Route::get('/reports/daily-production', [DailyProductionReportController::class, 'index'])
    ->name('daily-production-report.index');
    

        Route::get('/production-planning/report', [ProductionPlanningController::class, 'reportForm'])->name('production-planning.report');
        Route::post('/production-planning/report', [ProductionPlanningController::class, 'generateReport'])->name('production-planning.report.generate');
        
        Route::resource('lots', LotController::class);
        Route::get('/lot/products-by-department', [LotController::class, 'getProductsByDepartment'])->name('lots.products-by-department');
        
        Route::resource('batches', BatchController::class);
        Route::get('/than-supply-items-by-department/{departmentId}', [BatchController::class, 'getThanSupplyItemsByDepartment']);
        Route::get('/batch/{batchId}/details', [LotController::class, 'getBatchDetails'])->name('batch.details');
        
        Route::get('get-than-supplies/{department}', [BatchController::class, 'getThanSupplies'])->name('get.than.supplies');
        Route::get('get-than-supply-items/{thanSupply}', [BatchController::class, 'getThanSupplyItems'])->name('get.than.supply.items');
        
        Route::get('than-supply/{department}', [BatchController::class, 'getThanSuppliesApi']);
        Route::get('than-supply-items/{thanSupply}', [BatchController::class, 'getThanSupplyItemsApi']);
        
        Route::get('products', [App\Http\Controllers\ProductController::class, 'index'])->name('products.index');
        Route::get('products/create', [App\Http\Controllers\ProductController::class, 'create'])->name('products.create');
        Route::post('products', [App\Http\Controllers\ProductController::class, 'store'])->name('products.store');
        Route::get('products/{id}/edit', [App\Http\Controllers\ProductController::class, 'edit'])->name('products.edit');
        Route::put('products/{id}', [App\Http\Controllers\ProductController::class, 'update'])->name('products.update');
        Route::get('products/{id}', [App\Http\Controllers\ProductController::class, 'destroy'])->name('products.destroy');
        
        
        Route::get('/sub-departments/{department}', [ErpDepartmentController::class, 'getSubDepartments']);
        Route::get('/materials-by-particular/{particular}', [MaterialController::class, 'getMaterial']);
        
        Route::get('purchases', [App\Http\Controllers\PurchaseController::class, 'index'])->name('purchases.index');
        Route::get('purchases/create', [App\Http\Controllers\PurchaseController::class, 'create'])->name('purchases.create');
        Route::post('purchases', [App\Http\Controllers\PurchaseController::class, 'store'])->name('purchases.store');
        Route::get('purchases/{id}/edit', [App\Http\Controllers\PurchaseController::class, 'edit'])->name('purchases.edit');
        Route::put('purchases/{id}', [App\Http\Controllers\PurchaseController::class, 'update'])->name('purchases.update');
        Route::get('purchases/{id}', [App\Http\Controllers\PurchaseController::class, 'destroy'])->name('purchases.destroy');
        Route::get('purchases/view/{id}', [App\Http\Controllers\PurchaseController::class, 'view'])->name('purchases.view');

        Route::get('purchase-receipts', [App\Http\Controllers\PurchaseReceiptController::class, 'index'])->name('purchase-receipts.index');
        Route::get('purchase-receipts/create', [App\Http\Controllers\PurchaseReceiptController::class, 'create'])->name('purchase-receipts.create');
        Route::post('purchase-receipts', [App\Http\Controllers\PurchaseReceiptController::class, 'store'])->name('purchase-receipts.store');
        Route::get('purchase-receipts/{id}/edit', [App\Http\Controllers\PurchaseReceiptController::class, 'edit'])->name('purchase-receipts.edit');
        Route::put('purchase-receipts/{id}', [App\Http\Controllers\PurchaseReceiptController::class, 'update'])->name('purchase-receipts.update');
        Route::get('purchase-receipts/delete/{id}', [App\Http\Controllers\PurchaseReceiptController::class, 'destroy'])->name('purchase-receipts.destroy');
        Route::get('purchase-receipts/view/{id}', [App\Http\Controllers\PurchaseReceiptController::class, 'view'])->name('purchase-receipts.view');

        Route::get('purchase-invoice', [App\Http\Controllers\PurchaseInvoiceController::class, 'index'])->name('purchase-invoice.index');
        Route::get('purchase-invoice/create', [App\Http\Controllers\PurchaseInvoiceController::class, 'create'])->name('purchase-invoice.create');
        Route::post('purchase-invoice', [App\Http\Controllers\PurchaseInvoiceController::class, 'store'])->name('purchase-invoice.store');
        Route::get('purchase-invoice/{id}/edit', [App\Http\Controllers\PurchaseInvoiceController::class, 'edit'])->name('purchase-invoice.edit');
        Route::put('purchase-invoice/{id}', [App\Http\Controllers\PurchaseInvoiceController::class, 'update'])->name('purchase-invoice.update');
        Route::get('purchase-invoice/{id}', [App\Http\Controllers\PurchaseInvoiceController::class, 'destroy'])->name('purchase-invoice.destroy');
        Route::get('purchase-invoice/view/{id}', [App\Http\Controllers\PurchaseInvoiceController::class, 'view'])->name('purchase-invoice.view');
        Route::get('purchase-invoices/vendors/invoices/{id}', [App\Http\Controllers\PurchaseInvoiceController::class, 'vendor_invoices'])->name('purchase-invoice.vendor_invoices');
        Route::post('purchase-invoices/add', [App\Http\Controllers\PurchaseInvoiceController::class, 'add'])->name('purchase-invoices.add');

        Route::get('inward-receipts', [App\Http\Controllers\InwardReceiptController::class, 'index'])->name('inward-receipts.index');
        Route::get('inward-receipts/create', [App\Http\Controllers\InwardReceiptController::class, 'create'])->name('inward-receipts.create');
        Route::post('inward-receipts', [App\Http\Controllers\InwardReceiptController::class, 'store'])->name('inward-receipts.store');
        Route::get('inward-receipts/{id}/edit', [App\Http\Controllers\InwardReceiptController::class, 'edit'])->name('inward-receipts.edit');
        Route::put('inward-receipts/{id}', [App\Http\Controllers\InwardReceiptController::class, 'update'])->name('inward-receipts.update');
        Route::get('inward-receipts/{id}', [App\Http\Controllers\InwardReceiptController::class, 'destroy'])->name('inward-receipts.destroy');
        Route::get('inward-receipts/view/{id}', [App\Http\Controllers\InwardReceiptController::class, 'view'])->name('inward-receipts.view');
        
         Route::get('product-types', [App\Http\Controllers\ProductTypeController::class, 'index'])->name('product-types.index');
        Route::get('product-types/create', [App\Http\Controllers\ProductTypeController::class, 'create'])->name('product-types.create');
        Route::post('product-types', [App\Http\Controllers\ProductTypeController::class, 'store'])->name('product-types.store');
        Route::get('product-types/{id}/edit', [App\Http\Controllers\ProductTypeController::class, 'edit'])->name('product-types.edit');
        Route::put('product-types/{id}', [App\Http\Controllers\ProductTypeController::class, 'update'])->name('product-types.update');
        Route::get('product-types/{id}', [App\Http\Controllers\ProductTypeController::class, 'destroy'])->name('product-types.destroy');
        Route::get('/getParticulars/{material_id}', [App\Http\Controllers\ProductTypeController::class, 'getParticulars']);
        
        Route::resource('sub-erp-departments', SubErpDepartmentController::class);
        Route::resource('erp-departments', ErpDepartmentController::class);
        
        Route::get('particulars', [App\Http\Controllers\ParticularController::class, 'index'])->name('particulars.index');
        Route::get('particulars/create', [App\Http\Controllers\ParticularController::class, 'create'])->name('particulars.create');
        Route::post('particulars/store', [App\Http\Controllers\ParticularController::class, 'store'])->name('particulars.store');
        Route::get('particulars/{id}/edit', [App\Http\Controllers\ParticularController::class, 'edit'])->name('particulars.edit');
        Route::put('particulars/{id}', [App\Http\Controllers\ParticularController::class, 'update'])->name('particulars.update');
        Route::get('particulars/{id}', [App\Http\Controllers\ParticularController::class, 'destroy'])->name('particulars.destroy');
        
            
        Route::get('materials', [App\Http\Controllers\MaterialController::class, 'index'])->name('materials.index');
        Route::get('materials/create', [App\Http\Controllers\MaterialController::class, 'create'])->name('materials.create');
        Route::post('materials', [App\Http\Controllers\MaterialController::class, 'store'])->name('materials.store');
        Route::get('materials/{id}/edit', [App\Http\Controllers\MaterialController::class, 'edit'])->name('materials.edit');
        Route::put('materials/{id}', [App\Http\Controllers\MaterialController::class, 'update'])->name('materials.update');
        Route::get('materials/{id}', [App\Http\Controllers\MaterialController::class, 'destroy'])->name('materials.destroy');
        
          
    });
    
    Route::middleware(['role:super-admin|store'])->group(function () {
        
        Route::resource('inventory', App\Http\Controllers\InventoryController::class);
            // Route::post('inventory/{product}/transfer', [InventoryController::class, 'transfer'])->name('inventory.transfer');
            Route::post('/inventory/bulk-transfer', [InventoryController::class, 'transfer'])
         ->name('inventory.bulk-transfer');
            Route::get('/bulk/inventory', [InventoryController::class, 'bulk_transfer'])
         ->name('inventory.bulk_transfer');
    });
    
    

    Route::middleware(['role:super-admin|erp|production'])->group(function () {
        
        Route::get('customers', [App\Http\Controllers\CustomerController::class, 'index'])->name('customers.index');
        Route::get('customers/create', [App\Http\Controllers\CustomerController::class, 'create'])->name('customers.create');
        Route::post('customers', [App\Http\Controllers\CustomerController::class, 'store'])->name('customers.store');
        Route::get('customers/{id}/edit', [App\Http\Controllers\CustomerController::class, 'edit'])->name('customers.edit');
        Route::put('customers/{id}', [App\Http\Controllers\CustomerController::class, 'update'])->name('customers.update');
        Route::get('customers/{id}', [App\Http\Controllers\CustomerController::class, 'destroy'])->name('customers.destroy');
    
        
    
        Route::get('departments', [App\Http\Controllers\DepartmentController::class, 'index'])->name('departments.index');
        Route::get('departments/create', [App\Http\Controllers\DepartmentController::class, 'create'])->name('departments.create');
        Route::post('departments', [App\Http\Controllers\DepartmentController::class, 'store'])->name('departments.store');
        Route::get('departments/{id}/edit', [App\Http\Controllers\DepartmentController::class, 'edit'])->name('departments.edit');
        Route::put('departments/{id}', [App\Http\Controllers\DepartmentController::class, 'update'])->name('departments.update');
        Route::get('departments/{id}', [App\Http\Controllers\DepartmentController::class, 'destroy'])->name('departments.destroy');
    
        Route::get('manufacturers', [App\Http\Controllers\ManufacturerController::class, 'index'])->name('manufacturers.index');
        Route::get('manufacturers/create', [App\Http\Controllers\ManufacturerController::class, 'create'])->name('manufacturers.create');
        Route::post('manufacturers', [App\Http\Controllers\ManufacturerController::class, 'store'])->name('manufacturers.store');
        Route::get('manufacturers/{id}/edit', [App\Http\Controllers\ManufacturerController::class, 'edit'])->name('manufacturers.edit');
        Route::put('manufacturers/{id}', [App\Http\Controllers\ManufacturerController::class, 'update'])->name('manufacturers.update');
        Route::get('manufacturers/{id}', [App\Http\Controllers\ManufacturerController::class, 'destroy'])->name('manufacturers.destroy');
    
        
    
        Route::get('machines', [App\Http\Controllers\MachineController::class, 'index'])->name('machines.index');
        Route::get('machines/create', [App\Http\Controllers\MachineController::class, 'create'])->name('machines.create');
        Route::post('machines', [App\Http\Controllers\MachineController::class, 'store'])->name('machines.store');
        Route::get('machines/{id}/edit', [App\Http\Controllers\MachineController::class, 'edit'])->name('machines.edit');
        Route::put('machines/{id}', [App\Http\Controllers\MachineController::class, 'update'])->name('machines.update');
        Route::get('machines/{id}', [App\Http\Controllers\MachineController::class, 'destroy'])->name('machines.destroy');

    
       
    
    
        
    
       

        Route::get('branches', [App\Http\Controllers\BranchController::class, 'index'])->name('branches.index');
        Route::get('branches/create', [App\Http\Controllers\BranchController::class, 'create'])->name('branches.create');
        Route::post('branches', [App\Http\Controllers\BranchController::class, 'store'])->name('branches.store');
        Route::get('branches/{id}/edit', [App\Http\Controllers\BranchController::class, 'edit'])->name('branches.edit');
        Route::put('branches/{id}', [App\Http\Controllers\BranchController::class, 'update'])->name('branches.update');
        Route::get('branches/{id}', [App\Http\Controllers\BranchController::class, 'destroy'])->name('branches.destroy');

        

        Route::resource('inward-general', App\Http\Controllers\InwardGeneralController::class);
        Route::resource('product-groups', ProductGroupController::class);
        
      
     
        Route::resource('than-issues', ThanIssueController::class);
        Route::resource('than-supplies', ThanSupplyController::class);
        
        Route::prefix('supply-receipts')->group(function() {
            Route::get('/create', [SupplyReceiptController::class, 'create'])->name('supply-receipts.create');
            Route::get('/index', [SupplyReceiptController::class, 'index'])->name('supply-receipts.index');
            Route::get('/destroy/{id}', [SupplyReceiptController::class, 'destroy'])->name('supply-receipts.destroy');
            Route::get('/get-supplies', [SupplyReceiptController::class, 'getSupplies'])->name('supply-receipts.get-supplies');
            Route::post('/', [SupplyReceiptController::class, 'store'])->name('supply-receipts.store');
        });
        
        
        
    });

    Route::middleware(['role:super-admin|hr|dataentry'])->group(function () {

        Route::get('employees', [App\Http\Controllers\EmployeeController::class, 'index'])->name('employees.index');
        Route::get('employees/create', [App\Http\Controllers\EmployeeController::class, 'create'])->name('employees.create');
        Route::post('employees', [App\Http\Controllers\EmployeeController::class, 'store'])->name('employees.store');
        Route::get('employees/{id}/edit', [App\Http\Controllers\EmployeeController::class, 'edit'])->name('employees.edit');
        Route::put('employees/{id}', [App\Http\Controllers\EmployeeController::class, 'update'])->name('employees.update');
        Route::get('employees/{id}', [App\Http\Controllers\EmployeeController::class, 'destroy'])->name('employees.destroy');
        Route::get('attachments/{id}/download', [App\Http\Controllers\EmployeeController::class, 'download'])->name('attachments.download');
        Route::get('employees/payroll/{id}', [App\Http\Controllers\EmployeeController::class, 'payroll'])->name('employees.attd');
        Route::get('employees/calculate-salary-for-advance/{id}', [App\Http\Controllers\EmployeeController::class, 'calculateSalaryForAdvance'])->name('employees.calculate.salary.for.advance');

        Route::resource('shifts', ShiftController::class)->only([
            'index','edit','destroy'
        ]);

        Route::resource('shift-transfers', ShiftTransferController::class);
        Route::get('/api/employees/by-department/{departmentId}', [ShiftTransferController::class, 'getEmployeesByDepartment'])->name('employees.by-department');
        
        // Optional: DataTables endpoint for shift transfers
        Route::get('/api/shift-transfers/data', [ShiftTransferController::class, 'getShiftTransfersData'])->name('shift-transfers.data');

        Route::post('/import-excel', [AttendanceController::class, 'import'])->name('import.excel');
        Route::get('/calculate-hours/{employeeId}', [AttendanceController::class, 'calculateHours'])->name('calculate.hours');

        Route::resource('employee-types', App\Http\Controllers\EmployeeTypeController::class);

        Route::resource('loans', App\Http\Controllers\LoanController::class);
        Route::get('/get-employee-loan', [LoanController::class, 'getEmployeeLoan'])->name('loan.employee');
        Route::resource('advance-salaries', AdvanceSalaryController::class);
        Route::resource('leaves', LeaveController::class);
        Route::resource('salaries', SalaryController::class);

        Route::view('attendance','pages.attendance.index')->name('attendance.index');
        Route::get('attendance/employee', [AttendanceController::class, 'viewAttendance'])->name('attendance.view');
        Route::view('attendance/create', 'pages.attendance.create')->name('attendance.create');
        Route::post('attendance/store', [AttendanceController::class, 'store'])->name('attendance.store');
        Route::post('/attendance/check-status', [AttendanceController::class, 'checkStatus'])->name('attendance.check-status');
        Route::get('/attendance/correction', [AttendanceController::class, 'showCorrectionForm'])->name('attendance.correction');
        Route::post('/attendance/get-entries', [AttendanceController::class, 'getAttendanceEntries'])->name('attendance.getEntries');
        Route::post('/attendance/update', [AttendanceController::class, 'updateAttendance'])->name('attendance.update');

        Route::get('/loan-exceptions', [LoanExceptionController::class, 'index'])->name('loan-exception.index');
        Route::put('/loan-exceptions/bulk-update', [LoanExceptionController::class, 'bulkUpdate'])->name('loan-exception.bulk-update');

        Route::get('/generate-salary', [SalaryController::class, 'generateSalary'])->name('generate-salary');
        Route::get('/salary/by-department', [SalaryController::class, 'showByDepartment'])->name('salaries.byDepartment');
        Route::post('/generate-salary/process', [SalaryController::class, 'processSalaryGeneration'])->name('generate-salary.process');

        Route::resource('gazette-holidays', GazetteController::class);

        Route::get('/miss-scan', [MissScanController::class, 'index'])->name('miss-scan.index');
        Route::post('/miss-scan/resolve', [MissScanController::class, 'resolve'])->name('miss-scan.resolve');
        Route::resource('receive_loans', ReceiveLoanController::class);
        
        
        Route::get('/fixed-dept-attendance', [App\Http\Controllers\AttendanceController::class, 'fixedDeptAttendance'])->name('fixed-dept-attendance');
        Route::post('/fixed-dept-attendance', [App\Http\Controllers\AttendanceController::class, 'generateFixedDeptReport'])->name('generate-fixed-dept-report');
        Route::post('/delete-day-entries', [App\Http\Controllers\AttendanceController::class, 'deleteDayEntries'])
    ->name('delete-day-entries');
    });

    Route::middleware(['role:super-admin|production'])->group(function () {
        Route::resource('accounts', AccountController::class);
        Route::resource('accounts-transfers', TransferController::class);
        Route::resource('accounts-customersreceivables', CustomerReceivableController::class);
        Route::get('customer-balance', [CustomerReceivableController::class, 'getBalance'])->name('customer-balance');

        Route::resource('stock-adjustments', StockAdjustmentController::class);
        Route::resource('account-adjustments', AccountAdjustmentController::class);
        Route::resource('vendor-adjustments', VendorAdjustmentController::class);
        Route::resource('customer-adjustments', CustomerAdjustmentController::class);
        Route::resource('raw-materials', RawMaterialController::class);

        Route::resource('sale-orders', SaleOrderController::class);

        Route::resource('fabric-measurements', FabricMeasurementController::class);
        Route::resource('color-codes', ColorCodeController::class);
        Route::resource('production-plannings', ProductionPlanningController::class);
        Route::resource('daily-productions', DailyProductionController::class);
        
        Route::resource('accounts-vendors-payables', VendorPayableController::class);
        Route::get('vendor-balance/{id}', [VendorPayableController::class, 'getBalance'])->name('vendor.balance');

        Route::resource('roles', RoleController::class);
        Route::resource('users', UserController::class);
    });

    Route::get('/check-last-record', [AttendanceController::class, 'updateAttendanceTable'])->name('check.last.record');

    
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

});

require __DIR__ . '/auth.php';