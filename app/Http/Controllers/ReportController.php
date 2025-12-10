<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function financial(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->endOfMonth()->format('Y-m-d'));

        // Revenue 
        $revenue = Invoice::whereBetween('invoice_date', [$startDate, $endDate])
                            ->where('status', 'paid')
                            ->sum('total_amount');

        $pendingRevenue = Invoice::whereBetween('invoice_date', [$startDate, $endDate])
                                    ->whereIn('status', 'partial', 'overdue')
                                    ->sum('remaining_amount');

        // Expense 
        $totalExpense = Expense::whereBetween('expense_date', [$startDate, $endDate])
                                ->where('status', 'approved')
                                ->sum('amount');
                
        $expenseByCategory = Expense::whereBetween('expense_date', [$startDate, $endDate])
                                    ->where('status', 'approved')
                                    ->select('category', DB::raw('SUM(amount) as total'))
                                    ->groupBy('category')
                                    ->get();
        
        // Project Cost 
        $projectCost = Project::whereBetween('start_date', [$startDate, $endDate])
                                ->sum('actual_cost');
        
        // Profit 
        $profit = $revenue - $totalExpense - $projectCost;
        $profitMargin = $revenue > 0 ? ($profit / $revenue) * 100 : 0;

        // Monthly Trends 
        $monthlyRevenue = Invoice::whereBetween('invoice_date', [$startDate, $endDate])
                                    ->where('status', 'paid')
                                    ->select(
                                        DB::raw('YEAR(invoice_date) as year'),
                                        DB::raw('MONTH(invoice_date) as month'),
                                        DB::raw('SUM(total_amount) as total')
                                    )
                                    ->groupBy('year', 'month')
                                    ->orderBy('year')
                                    ->orderBy('month')
                                    ->get();
        
        return view('reports.financial', compact('revenue', 'pendingRevenue', 'totalExpense', 'expenseByCategory', 'projectCost', 'profit', 'profitMargin', 'monthlyRevenue', 'startDate', 'endDate'));
    }

    public function projectPerformance(Request $request)
    {
        $query = Project::with(['customer', 'projectManager']);

        if ($request->has('status') && $request->status != '') {
            $query->where('status', $request->satus);
        }

        $projects = $query->get();

        // Statistic
        $totalProjects = $projects->count();
        $completedProjects = $projects->where('status', 'completed')->count();
        $inProgressProjects = $projects->where('status', 'in_progress')->count();
        $onTimeProjects = $projects->where('status', 'completed')
                                    ->filter(function($project) {
                                        return $project->end_date >= now();
                                    })->count();
                
        // Budget Analysis
        $totalBudget = $projects->sum('estimated_budget');
        $totalActualCost = $projects->sum('actual_lost');
        $budgetVariance = $totalBudget - $totalActualCost;

        $projectsOverBudget = $projects->filter(function($project) {
            return $project->is_over_budget;
        })->count();

        // By type 
        $projectsByType = $projects->groupBy('project_type')->map(function($items) {
            return $items->count();
        });

        return view('reports.project-performance', compact('projects', 'totalProjects', 'completedProjects', 'inProgressProjects', 'onTimeProjects', 'totalBudget', 'totalActualCost', 'budgetVariance', 'projectsOverBudget', 'projectsByType'));
    }

    public function employeePerformance(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->endOfMonth()->format('Y-m-d'));

        $employees = Employee::active()
                            ->with(['attendances' => function($q) use ($startDate, $endDate) {
                                $q->whereBetween('date', [$startDate, $endDate]);
                            }])->get();
        
        // Calculate statistic employee 
        $employeeStats = $employees->map(function($employee) use ($startDate, $endDate) {
            $attendances = $employee->attendance;

            return [
                'employee' => $employee,
                'total_days' => $attendances->where('status', 'present')->count(),
                'total_hours' => $attendances->sum('work_hours'),
                'absent_days' => $attendances->where('status', 'absent')->count(),
                'leave_days' => $attendances->where('status', 'leave')->count(),
                'projects_count' => $employee->projects()->count(),
                'managed_projects' => $employee->managedProjects()
                                                ->whereBetween('start_date', [$startDate, $endDate])
                                                ->count()
            ];
        });

        // Departement statistic 
        $departmentStats = $employees->groupBy('department')->map(function($items) {
            return $items->count();
        });

        return view('reports.employee-performance', compact('employeeStats', 'departmentStats', 'startDate', 'endDate'));
    }

    public function customerAnalysis(Request $request)
    {
        $customers = Customer::with(['projects', 'invoice'])->get();

        // Statistic
        $totalCustomers = $customers->count();
        $activeCustomers = $customers->where('status', 'active')->count();

        // Top customer by revenue 
        $topCustomers = $customers->sortByDesc(function($customers) {
            return $customers->total_revenue;
        })->take(10);

        // Customer destribution
        $customersByCity = $customers->groupBy('city')->map(function($items) {
            return $items->count();
        })->sortDesc();

        $revenueByCustomer = $customers->map(function($customer) {
            return [
                'customer' => $customer,
                'total_revenue' => $customer->total_revenue,
                'total_projects' => $customer->total_projects,
                'pending_invoices' => $customer->invoices()->unpaid()->sum('remaining_amount')
            ];
        })->sortByDesc('total_revenue');

        return view('reports.customer-analysis', compact('totalCustomers', 'activeCustomers', 'topCustomers', 'revenueByCustomer'));
    }
}
