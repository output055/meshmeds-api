<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SaleReversal;
use Illuminate\Http\Request;

class ReversalLogController extends Controller
{
    public function index(Request $request)
    {
        $query = SaleReversal::with(['sale', 'user'])->latest();

        // Optional filters
        if ($request->filled('receipt_number')) {
            $query->whereHas('sale', function($q) use ($request) {
                $q->where('receipt_number', 'like', '%' . $request->receipt_number . '%');
            });
        }
        
        if ($request->filled('staff_name')) {
            $query->whereHas('user', function($q) use ($request) {
                $q->where('name', 'like', '%' . $request->staff_name . '%');
            });
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('created_at', [
                $request->start_date . ' 00:00:00',
                $request->end_date . ' 23:59:59'
            ]);
        }

        // Return up to 100 recent reversals mapped to the frontend expected structure
        $reversals = $query->take(100)->get();

        $mapped = $reversals->map(function($reversal) {
            $returnedItems = $reversal->returned_items ?? [];
            
            // Handle legacy double-encoding: if it's still a string after Eloquent's cast, decode it once more
            if (is_string($returnedItems)) {
                $returnedItems = json_decode($returnedItems, true) ?? [];
            }
            
            // If the amount column is populated, use it. Otherwise fallback to summing items
            $totalRefund = (float) $reversal->amount;
            
            if ($totalRefund <= 0 && is_array($returnedItems)) {
                foreach($returnedItems as $item) {
                    $totalRefund += $item['refunded_amount'] ?? 0;
                }
            }
            
            // Last resort fallback for legacy records (pre-partial-returns update)
            if ($totalRefund <= 0 && $reversal->sale) {
                $totalRefund = (float) $reversal->sale->refunded_amount;
            }

            return [
                'id' => $reversal->id,
                'receiptNumber' => $reversal->sale->receipt_number ?? 'Unknown',
                'originalSaleDate' => $reversal->sale ? $reversal->sale->created_at->format('Y-m-d H:i') : null,
                'voidedDateTime' => $reversal->created_at->format('Y-m-d H:i'),
                'voidedBy' => $reversal->user->name ?? 'Unknown User',
                'totalAmountRefunded' => (float) $totalRefund,
                'reasonForReversal' => $reversal->reason,
                'items' => $returnedItems
            ];
        });

        return response()->json($mapped);
    }
}
