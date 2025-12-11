<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;
use Whoops\Run;

Route::get('/', function () {
    return view('dashboard');
})->name('dashboard');

Route::resource('customers', CustomerController::class);
Route::get('customers/{customer}/projects', [CustomerController::class, 'projects'])->name('customers.projects');

Route::resource('projects', ProjectController::class);
Route::post('projects/{project}/update-status', [ProjectController::class, 'updateStatus'])->name('projects.update-status');
Route::get('projects/{project}/timeline', [ProjectController::class, 'timeline'])->name('projects.timeline');

Route::resource('purchase-orders', PurchaseOrderController::class);
Route::post('purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive'])->name('purchase-order.receive');

Route::resource('quotations', QuotationController::class);
Route::post('quotations/{quotation}/approve', [QuotationController::class, 'approve'])->name('quotations.approve');
Route::post('quotations/{quotation}/reject', [QuotationController::class, 'reject'])->name('quotations.reject');
Route::get('quotations/{quotation}/pdf', [QuotationController::class, 'generatePDF'])->name('quotations.pdf');

Route::resource('purchase-orders', PurchaseOrderController::class);
Route::post('purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive'])->name('purchase-orders.receive');

Route::resource('invoices', InvoiceController::class);
Route::post('invoices/{invoice}/mark-paid', [InvoiceController::class, 'markPaid'])->name('invoices.mark-paid');
Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'generatePdf'])->name('invoies.pdf');

Route::resource('inventory', InventoryController::class);
Route::post('inventory/{inventory}/adjust', [InventoryController::class, 'adjustStock'])->name('inventory.adjust');
Route::get('inventory/low-stock/alert', [InventoryController::class, 'lowStock'])->name('inventory.low-stock');

Route::resource('employees', EmployeeController::class);
Route::get('employees/{employee}/attendance', [EmployeeController::class, 'attendance'])->name('employees.attendance');

Route::resource('attendances', AttendanceController::class);
Route::post('attendances/clock-in', [AttendanceController::class, 'clockIn'])->name('attendances.clock-in');
Route::post('attendances/clock-out', [AttendanceController::class, 'clockOut'])->name('attendances.clock-out');

Route::resource('expenses', ExpenseController::class);
Route::post('expenses/{expense}/approve', [ExpenseController::class, 'approve'])->name('expenses.approve');

Route::prefix('reports')->name('reports.')->group(function () {
    Route::get('financial', [ReportController::class, 'financial'])->name('financial');
    Route::get('project-performance', [ReportController::class, 'projectPerformance'])->name('project-performance');
    Route::get('inventory', [ReportController::class, 'inventory'])->name('inventory');
    Route::get('employee-performance', [ReportController::class, 'employeePerformance'])->name('employee-performance');
    Route::get('customer-analysis', [ReportController::class, 'customerAnalysis'])->name('customer-analysis');
});