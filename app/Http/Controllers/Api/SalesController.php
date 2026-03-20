<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use Illuminate\Http\Request;

class SalesController extends Controller
{
    public function index(Request $request)
    {
        $isAdmin = $request->user()->isAdmin();

        $query = Sale::with(['items.drug', 'user'])
            ->orderBy('created_at', 'desc');

        // Date range filter
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('receipt_number', 'like', "%{$search}%");
        }

        // Payment method filter
        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        $paginated = $query->paginate(20);

        $items = collect($paginated->items())->map(function ($sale) use ($isAdmin) {
            $row = [
                'id'             => $sale->id,
                'receipt_number' => $sale->receipt_number,
                'cashier'        => $sale->user?->name ?? 'Unknown',
                'item_count'     => $sale->items->count(),
                'subtotal'       => (float) $sale->subtotal,
                'refunded_amount'=> (float) $sale->refunded_amount,
                'payment_method' => $sale->payment_method,
                'status'         => $sale->status ? ucwords(str_replace('_', ' ', $sale->status)) : 'Completed',
                'created_at'     => $sale->created_at,
                'items'          => $sale->items->map(fn($item) => [
                    'id'          => $item->id,
                    'drug_id'     => $item->drug_id,
                    'drug_name'   => $item->drug?->name ?? 'Unknown',
                    'quantity'    => $item->quantity,
                    'returned_quantity' => $item->returned_quantity,
                    'unit_price'  => (float) $item->unit_price,
                    'subtotal'    => (float) $item->subtotal,
                    'unit_profit' => $isAdmin ? (float) $item->unit_profit : null,
                ]),
            ];

            if ($isAdmin) {
                $row['total_profit'] = (float) $sale->total_profit;
            }

            return $row;
        });

        // Aggregate KPIs for the filtered result set (all pages)
        $aggregateQuery = Sale::query();
        if ($request->filled('date_from')) {
            $aggregateQuery->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $aggregateQuery->whereDate('created_at', '<=', $request->date_to);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $aggregateQuery->where('receipt_number', 'like', "%{$search}%");
        }
        if ($request->filled('payment_method')) {
            $aggregateQuery->where('payment_method', $request->payment_method);
        }

        $kpiRow = $aggregateQuery->selectRaw(
            'COUNT(*) as total_sales,
             SUM(subtotal) as total_revenue,
             SUM(total_profit) as total_profit'
        )->first();

        $totalSales   = (int)   ($kpiRow ? $kpiRow->total_sales   : 0);
        $totalRevenue = (float) ($kpiRow ? $kpiRow->total_revenue : 0);
        $totalProfit  = (float) ($kpiRow ? $kpiRow->total_profit  : 0);

        $response = [
            'data'         => $items,
            'total'        => $paginated->total(),
            'per_page'     => $paginated->perPage(),
            'current_page' => $paginated->currentPage(),
            'last_page'    => $paginated->lastPage(),
            'kpis'         => [
                'total_sales'   => $totalSales,
                'total_revenue' => $totalRevenue,
            ],
        ];

        if ($isAdmin) {
            $response['kpis']['total_profit'] = $totalProfit;
        }

        return response()->json($response);
    }

    public function show(Request $request, Sale $sale)
    {
        $isAdmin = $request->user()->isAdmin();

        $sale->load(['items.drug', 'user']);

        $result = [
            'id'             => $sale->id,
            'receipt_number' => $sale->receipt_number,
            'cashier'        => $sale->user?->name ?? 'Unknown',
            'payment_method' => $sale->payment_method,
            'amount_tendered'=> (float) $sale->amount_tendered,
            'change_due'     => (float) $sale->change_due,
            'subtotal'       => (float) $sale->subtotal,
            'refunded_amount'=> (float) $sale->refunded_amount,
            'status'         => $sale->status ?? 'Completed',
            'created_at'     => $sale->created_at,
            'items'          => $sale->items->map(fn($item) => [
                'id'          => $item->id,
                'drug_id'     => $item->drug_id,
                'drug_name'   => $item->drug?->name ?? 'Unknown',
                'quantity'    => $item->quantity,
                'returned_quantity' => $item->returned_quantity,
                'unit_price'  => (float) $item->unit_price,
                'subtotal'    => (float) $item->subtotal,
                'unit_profit' => $isAdmin ? (float) $item->unit_profit : null,
            ]),
        ];

        if ($isAdmin) {
            $result['total_profit'] = (float) $sale->total_profit;
        }

        return response()->json($result);
    }
}
