<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\VoidSaleService;
use Illuminate\Http\Request;
use App\Traits\LogsActivity;
use Exception;

class VoidSaleController extends Controller
{
    use LogsActivity;

    public function __construct(private VoidSaleService $voidSaleService)
    {
    }

    public function reverse(Request $request, $receipt_number)
    {
        $validated = $request->validate([
            'reason' => 'required|string|min:3|max:255',
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer|exists:sale_items,id',
            'items.*.return_quantity' => 'required|integer|min:1'
        ]);

        try {
            $result = $this->voidSaleService->reverseSale(
                $receipt_number, 
                $validated['reason'], 
                $request->user()->id, 
                $validated['items']
            );
            
            $this->logActivity('void_sale', "Voided sale for GH₵" . number_format($result['refund_amount'], 2) . " (Receipt: {$result['receipt_number']})");

            return response()->json([
                'message' => 'Sale successfully reversed.',
                'refund_amount' => $result['refund_amount'],
                'receipt_number' => $result['receipt_number']
            ], 200);

        } catch (Exception $e) {
            $statusCode = 400; 
            if (str_contains($e->getMessage(), 'not found')) {
                $statusCode = 404;
            }

            return response()->json([
                'error' => $e->getMessage()
            ], $statusCode);
        }
    }
}
