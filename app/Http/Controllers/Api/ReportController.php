<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function getFinancialReport(Request $request)
    {
        $startDate = $request->query('start_date', \Carbon\Carbon::today()->toDateString());
        $endDate = $request->query('end_date', \Carbon\Carbon::today()->toDateString());

        $start = \Carbon\Carbon::parse($startDate)->startOfDay();
        $end = \Carbon\Carbon::parse($endDate)->endOfDay();

        // 1. Gross Profit from Sales
        $grossProfit = (float) \App\Models\Sale::whereBetween('created_at', [$start, $end])
            ->sum('total_profit');

        // 2. Total Expenses
        $totalExpenses = (float) \App\Models\Expense::whereBetween('expense_date', [$startDate, $endDate])
            ->sum('amount');

        // 3. Net Profit
        $netProfit = $grossProfit - $totalExpenses;

        // 4. Expenses Breakdown (Category sums)
        $expensesBreakdown = \App\Models\Expense::whereBetween('expense_date', [$startDate, $endDate])
            ->selectRaw('category, SUM(amount) as total_amount')
            ->groupBy('category')
            ->orderByDesc('total_amount')
            ->get();

        // Detailed expenses list
        $expenseDetails = \App\Models\Expense::with('user:id,name')
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'date_range' => [
                'start' => $startDate,
                'end' => $endDate
            ],
            'gross_profit' => $grossProfit,
            'total_expenses' => $totalExpenses,
            'net_profit' => $netProfit,
            'expenses_breakdown' => $expensesBreakdown,
            'expenses_detailed' => $expenseDetails
        ]);
    }
}
