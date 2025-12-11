<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Inventory;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Quotation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    private function calculateOnTimeCompletion($start)
    {
        $completedProjects = Project::where('status', 'completed')
                                    ->where('updated_at', '>=', $start)
                                    ->get();
        
        if ($completedProjects->isEmpty()) {
            return 0;
        }

        $onTime = $completedProjects->filter(function($project) {
            return $project->update_at <= $project->end_date;
        })->count();

        return round(($onTime / $completedProjects->count()) * 100, 2);
    }

    public function index()
    {
        $stats = [
            'total_costumers' => Customer::active()->count(),
            'active_projects' => Project::inProgress()->count(),
            'total_employees' => Employee::active()->count(),
            'low_stock_items' => Inventory::lowStock()->count()
        ];

        $startOfMounth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        $financial = [
            'monthly_revenue' => Invoice::whereBetween('invoice_date', [$startOfMounth, $endOfMonth])
                                        ->where('status', 'paid')
                                        ->sum('total_amount'),
            'pending_invoices' => Invoice::unpaid()->sum('remaining_amount'),
            'monthly_expenses' => Expense::whereBetween('expense_date', [$startOfMounth, $endOfMonth])
                                        ->where('status', 'approved')
                                        ->sum('amount'),
            'monthly_profit' => 0,
        ];

        $financial['monthly_profit'] = $financial['monthly_revenue'] - $financial['monthly_expenses'];

        $recentProjects = Project::with(['customer', 'projectManager'])
                                    ->latest()
                                    ->take(5)
                                    ->get();
            
        $pendingQuotations = Quotation::pending()->with('customer')
                                        ->latest()
                                        ->take(5)
                                        ->get();
        
        $overdueInvoices = Invoice::overdue()
                                    ->with('customer')
                                    ->latest()
                                    ->take(5)
                                    ->get();
        
        $projectsByStatus = Project::select('status', DB::raw('count(*) as count'))
                                    ->groupBy('status')
                                    ->get()
                                    ->pluck('count', 'status');
        
        $monthlyRevenueChart = [];
        for ($i = 5; $i >= 0; $i--) { 
            $month = Carbon::now()->subMonths($i);
            $revenue = Invoice::whereYear('invoice_date', $month->year)
                                ->whereMonth('invoice_date', $month->month)
                                ->where('status', 'paid')
                                ->sum('total_amount');
            
            $monthlyRevenueChart[] = [
                'month' => $month->format('M Y'),
                'revenue' => $revenue
            ];
        }

        $todayAttendance = [
            'present' => Attendance::today()->where('status', 'present')->count(),
            'absent' => Attendance::today()->where('status', 'absent')->count(),
            'leave' => Attendance::today()->where('status','leave')->count(),
        ];

        $lowStockItems = Inventory::lowStock()
                                    ->orderBy('quantity', 'asc')
                                    ->take(5)
                                    ->get();
        
        $pendingExpenses = Expense::pending()
                                    ->with(['employee', 'project'])
                                    ->latest('expense_date')
                                    ->take(5)
                                    ->get();
            
        $projectProgress = Project::whereIn('status', ['planning', 'in_progress'])
                                ->select('project_name', 'progress_percentage', 'status')
                                ->orderBy('progress_percentage', 'asc')
                                ->take(10)
                                ->get();
            
        $topCustomers = Customer::withCount('projects')
                                ->with('invoices')
                                ->get()
                                ->map(function($customer) {
                                    return [
                                        'customer' => $customer,
                                        'total_revenue' => $customer->invoices()
                                                                    ->where('status', 'paid')
                                                                    ->sum('total_amount')
                                    ];
                                })
                                ->sortByDesc('total_revenue')
                                ->take(5);
        
        return view('dashboard', compact(
            'stats', 'financial', 'recentProjects', 'pendingQuotations', 'overdueInvoices', 'projectsByStatus',
            'monthlyRevenueChart', 'todayAttendance', 'lowStockItems', 'pendingExpenses', 'projectProgress', 'topCustomers'
        ));
    }

    public function getProjectStats(Request $request)
    {
        $period = $request->get('period', 'month');

        $stats = [];

        switch ($period) {
            case 'day':
                $start = Carbon::today();
                break;
            case 'week':
                $start = Carbon::now()->startOfWeek();
                break;
            case 'year':
                $start = Carbon::now()->startOfYear();
                break;
            default:
                $start = Carbon::now()->startOfMonth();
        }

        $stats = [
            'new_projects' => Project::where('created_at', '>=', $start)->count(),
            'completed_projects' => Project::where('status', 'completed')
                                            ->where('updated_at', '>=', $start)
                                            ->count(),
            'on_time_completion' => $this->calculateOnTimeCompletion($start),
            'average_progress' => Project::whereIn('status', ['planning', 'in_progress'])
                                        ->avg('progress_percentage'),
        ];

        return response()->json($stats);
    }

    

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
