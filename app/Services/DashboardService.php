<?php

namespace App\Services;

use App\Models\User;
use App\Models\Drug;
use App\Models\Sale;
use Carbon\Carbon;

class DashboardService
{
    public function getStatsForUser(User $user): array
    {
        $role = $user->role;
        $today = Carbon::today();

        $baseStats = [
            'role' => $role,
            'userName' => $user->name,
        ];

        // Shared Data (Low Stock & Expiring)
        $lowStock = Drug::where('stock_quantity', '<', 10)->get()->map(fn($d) => [
            'name' => $d->name,
            'currentQty' => $d->stock_quantity,
            'reorderLevel' => 50
        ]);

        $expiring = Drug::whereNotNull('expiry_date')
            ->where('expiry_date', '<=', Carbon::now()->addDays(30))
            ->get()
            ->map(fn($d) => [
                'name' => $d->name,
                'batch' => $d->barcode ?? 'N/A',
                'expiry' => $d->expiry_date,
                'daysLeft' => (int) Carbon::now()->diffInDays(Carbon::parse($d->expiry_date), false)
            ]);

        // Helper for mapping sales to activities
        $mapSaleToActivity = fn($s) => [
            'id'        => (string) $s->id,
            'action'    => 'Sales Transaction',
            'user'      => $s->user->name ?? 'System',
            'timestamp' => $s->created_at->toIso8601String(),
            'time'      => $s->created_at->format('h:i A'),
            'amount'    => (float) $s->subtotal,
            'details'   => "Receipt #{$s->receipt_number}"
        ];

        if ($role === User::ROLE_ADMIN) {
            $totalRevenue = (float) Sale::whereDate('created_at', $today)->sum('subtotal');
            $totalProfit  = (float) Sale::whereDate('created_at', $today)->sum('total_profit');
            
            return array_merge($baseStats, [
                'totalRevenue'      => $totalRevenue,
                'totalProfit'       => $totalProfit,
                'inventoryValue'    => (float) Drug::all()->sum(fn($d) => $d->stock_quantity * $d->cost_price),
                'totalTransactions' => Sale::whereDate('created_at', $today)->count(),
                'totalReturns'      => 0,
                'recentActivities'  => Sale::with('user')->orderBy('created_at', 'desc')->limit(10)->get()->map($mapSaleToActivity),
                'criticalAlerts'    => $this->formatAlerts($lowStock, $expiring),
                'revenueChart'      => $this->getRevenueChartData()
            ]);
        }

        if ($role === User::ROLE_PHARMACIST) {
            return array_merge($baseStats, [
                'totalRevenue'      => (float) Sale::whereDate('created_at', $today)->sum('subtotal'),
                'totalTransactions' => Sale::whereDate('created_at', $today)->count(),
                'totalReturns'      => 0,
                'dispensedToday'    => (int) Sale::whereDate('created_at', $today)->count(),
                'criticalAlerts'    => $this->formatAlerts($lowStock, $expiring),
                'recentActivities'  => Sale::with('user')->orderBy('created_at', 'desc')->limit(10)->get()->map($mapSaleToActivity)
            ]);
        }

        if ($role === User::ROLE_ATTENDANT) {
            $shiftSales        = (float) Sale::whereDate('created_at', $today)->where('user_id', $user->id)->sum('subtotal');
            $transactionsToday = Sale::whereDate('created_at', $today)->where('user_id', $user->id)->count();

            return array_merge($baseStats, [
                'totalRevenue'           => $shiftSales,
                'totalTransactions'      => $transactionsToday,
                'averageTransactionValue'=> $transactionsToday > 0 ? $shiftSales / $transactionsToday : 0,
                'recentActivities'       => Sale::with('user')->where('user_id', $user->id)->orderBy('created_at', 'desc')->limit(10)->get()->map($mapSaleToActivity),
                'criticalAlerts'         => $this->formatAlerts($lowStock, $expiring)
            ]);
        }

        return $baseStats;
    }

    private function formatAlerts($lowStock, $expiring)
    {
        $alerts = [];
        foreach ($lowStock as $item) {
            $alerts[] = [
                'id' => uniqid(),
                'drugName' => $item['name'],
                'type' => 'LOW_STOCK',
                'message' => "Only {$item['currentQty']} units remaining",
                'severity' => $item['currentQty'] < 5 ? 'CRITICAL' : 'WARNING'
            ];
        }
        foreach ($expiring as $item) {
            $alerts[] = [
                'id' => uniqid(),
                'drugName' => $item['name'],
                'type' => 'EXPIRING',
                'message' => "Expires in {$item['daysLeft']} days",
                'severity' => $item['daysLeft'] < 14 ? 'CRITICAL' : 'WARNING'
            ];
        }
        return $alerts;
    }

    private function getRevenueChartData()
    {
        $labels = [];
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $labels[] = $date->format('D');
            $data[] = (float) Sale::whereDate('created_at', $date)->sum('subtotal');
        }
        return ['labels' => $labels, 'data' => $data];
    }
}
