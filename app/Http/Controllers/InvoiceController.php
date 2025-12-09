<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Quotation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    private function generateInvoicenumber(){
        $lastInvoice = Invoice::latest('id')->first();
        $number = $lastInvoice ? intval(substr($lastInvoice->invoice_number, 4)) + 1 : 1;

        return 'INV' . date('Y') . str_pad($number, 5, '0', STR_PAD_LEFT);
    }

    public function index(Request $request)
    {
        $query = Invoice::with(['customer', 'project']);

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', function($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->has('status') && $request->status != '') {
            $query->where('status', $request->status);
        }

        $invoices = $query->latest()->paginate(15);

        return view('invoices.index', compact('invoices'));
    }

    public function create()
    {
        $customers = Customer::active()->get();
        $projects = Project::whereNotIn('status', ['cancelled'])->get();
        $quotations = Quotation::where('status', 'approved')->get();

        return view('invoices.create', compact('customers', 'projects', 'quotations'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'project_id' => 'nullable|exists:projects,id',
            'quotation_id' => 'nullable|exists:quotations,id',
            'invoice_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:invoice_date',
            'tax_percentage' => 'required|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'payment_terms' => 'nullable|string',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit' => 'required|string',
            'items.*.unit_price' => 'required|numeric|min:0'
        ]);

        DB::beginTransaction();
        try{
            // Calculate total
            $subtotal = 0;
            foreach ($request->items as $item) {
                $subtotal += $item['quantity'] * $item['unite_price'];
            }

            $taxAmount = ($subtotal * $validated['tax_percentage']) / 100;
            $discountAmount = $validated['discount_amount'] ?? 0;
            $totalAmount = $subtotal + $taxAmount - $discountAmount;

            // Create invoice 
            $invoice = Invoice::create([
                'customer_id' => $validated['customer_id'],
                'project_id' => $validated['project_id'],
                'quotation_id' => $validated['quotation_id'],
                'invoice_number' => $this->generateInvoiceNumber(),
                'invoice_date' => $validated['invoice_date'],
                'due_date' => $validated['due_date'],
                'subtotal' => $subtotal,
                'tax_percentage' => $validated['tax_percentage'],
                'tax_amount' => $taxAmount,
                'paid_amount' => 0,
                'status' => 'draft',
                'payment_terms' => $validated['payment_terms'],
                'notes' => $validated['notes']
            ]);

            foreach ($request->items as $item) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $item['quantity'] * $item['unit_price']
                ]);
            }

            DB::commit();

            return redirect()->route('invoices.show', $invoice)->with('success', 'Invoice berhasil dibuat');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());     
        }
    }

    public function show(Invoice $invoice)
    {
        $invoice->load(['cutomer', 'project', 'quotation', 'items', 'payments']);

        return view('invoices.show', compact('invoice'));
    }

    public function edit(Invoice $invoice)
    {   
        if (!in_array($invoice->status, ['draft'])) {
            return back()->with('error', 'Hanya invoice dengan status draft yang bisa diedit');
        }

        $customers = Customer::active()->get();
        $projects = Project::whereNotIn('status', ['cancelled'])->get();
        $quotations = Quotation::where('status', 'approved')->get();
        $invoice->load('items');

        return view('invoices.edit', compact('invoice', 'customers', 'projects', 'quotations'));
    }

    public function update(Request $request, Invoice $invoice)
    {
        if (!in_array($invoice->status, ['draft'])) {
            return back()->with('error', 'Hanya invoice dengan status draft yang bisa diedit');
        }

        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'project_id' => 'nullable|exists:projects,id',
            'quotation_id' => 'nullable|exists:quotations,id',
            'invoice_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:invoice_date',
            'tax_percentage' => 'required|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'payment_term' => 'nullable|string',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit' => 'required|string',
            'items.*.unit_price' => 'required|numeric|min:0'
        ]);


        DB::beginTransaction();
        try {
            // Calculate total
            $subtotal = 0;
            foreach ($request->items as $item) {
                $subtotal += $item['quantity'] * $item['unit_price'];
            }

            $taxAmount = ($subtotal * $validated['tax_percentage']) / 100;
            $discountAmount = $validated['discount_amount'] ?? 0;
            $totalAmount = $subtotal + $taxAmount - $discountAmount;

            // Update invoice
            $invoice->update([
                'customer_id' => $validated['customer_id'],
                'project_id' => $validated['project_id'],
                'quotation_id' => $validated['quotation_id'],
                'invoice_date' => $validated['invoice_date'],
                'due_date' => $validated['due_date'],
                'subtotal' => $subtotal,
                'tax_percentage' => $validated['tax_percentage'],
                'tax_amount' => $taxAmount,
                'discount_amount' => $discountAmount,
                'total_amount' => $$totalAmount,
                'payment_terms' => $validated['payment_terms'],
                'notes' => $validated['notes']
            ]);

            $invoice->items()->delete();
            foreach ($request->items as $item) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $item['quantity'] * $item['unit_price']
                ]);
            }

            DB::commit();

            return redirect()->route('invoices.show', $invoice)->with('success', 'Invoice berhasil diupdate');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function destroy(Invoice $invoice)
    {
        if (!in_array($invoice->status, ['draft'])) {
            return back()->with('error', 'Hanya invoice dengan status draft yang bisa dihapus');
        }

        $invoice->delete();

        return redirect()->route('invoices.index')->with('success', 'Invoice berhasil dihapus');
    }

    public function markPaid(Request $request, Invoice $invoice){
        $validated = $request->validate([
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01|max:' . $invoice->remaining_amount,
            'payment_method' => 'required|string',
            'reference_number' => 'nullable|string',
            'notes' => 'nullable|string'
        ]);

        DB::beginTransaction();
        try {
            // Create payment record
            Payment::create([
                'invoice_id' => $invoice->id,
                'payment_date' => $validated['payment_date'],
                'amount' => $validated['amount'],
                'payment_method' => $validated['payment_method'],
                'reference_number' => $validated['reference_number'],
                'notes' => $validated['notes']
            ]);

            // Update invoice
            $newPaidAmount = $invoice->paid_amount + $validated['amount'];
            $invoice->paid_amount = $newPaidAmount;

            if ($newPaidAmount >= $invoice->total_amount) {
                $invoice->status = 'paid';
                $invoice->paid_at = now();
            } else {
                $invoice->status = 'partial';
            }

            $invoice->save();

            DB::commit();

            return back()->with('success', 'Pembayaran berhasil dicatat');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function generatePdf(Invoice $invoice){
        $invoice->load(['customer', 'project', 'items', 'payments']);

        return view('invoice.pdf', compact('invoice'));
    }
}
