<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    private function generateCustomerCode(){
        $lastCustomer = Customer::latest('id')->first();
        $number = $lastCustomer ? intval(substr($lastCustomer->code, 4)) + 1 : 1;
        
        return 'CUST' . str_pad($number, 6, '0', STR_PAD_LEFT);
    }
    
    public function index(Request $request)
    {
        $query = Customer::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search){
                $q->where('name', 'like', '%{$search}%')
                    ->orWhere('company_name', 'like', '%{$search}%')
                    ->orWhere('email', 'like', '%{$search}%')
                    ->orWhere('code', 'like', '%{$search}%');
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $customers = $query->latest()->paginate(15);

        return view('customers.index', compact('customers'));
    }

    public function create()
    {
        return view('customers.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'email' => 'required|email|unique:customers,email',
            'phone' => 'nullable|string|max:20',
            'mobile' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:10',
            'tax_number' => 'nullable|string|max:50',
            'contact_person' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'status' => 'required|in:active,inactive'
        ]);

        $validated['code'] = $this->generateCustomerCode();

        Customer::create($validated);

        return redirect()->route('customers.index')->with('success', 'Customer berhasil ditambahkan');
    }

    public function show(Customer $customer)
    {
        $customer->load(['projects', 'quotations', 'invoices']);

        return view('customers.edit', compact('customer'));
    }

    public function edit(Customer $customer)
    {
        return view('customers.edit', compact($customer));
    }

    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'email' => 'required|email|unique:customes,email,' . $customer->id,
            'phone' => 'nullable|string|max:20',
            'mobile' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:10',
            'tax_number' => 'nullable|string|max:50',
            'contact_person' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'status' => 'required:in:active,inactive'
        ]);

        $customer->update($validated);

        return redirect()->route('customers.show', $customer)->with('success', 'Customer berhasil diupdate');
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();

        return redirect()->route('customers.index')->with('success', 'Customer berhasil dihapus');
    }

    public function projects(Customer $customer){
        $projects = $customer->projects()->with('projectManager')->latest()->get();

        return view('customers.projects', compact('customer', 'projects'));
    }
}
