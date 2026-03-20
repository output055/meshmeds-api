<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PosService;
use Illuminate\Http\Request;
use App\Traits\LogsActivity;
use Exception;

class PosController extends Controller
{
    use LogsActivity;

    public function __construct(private PosService $posService)
    {
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'payment_method' => 'required|string|in:Cash,Mobile Money',
            'amount_tendered' => 'nullable|numeric|min:0',
            'change_due' => 'nullable|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer|exists:drugs,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0', // Allows scanning overrides
        ]);

        try {
            $sale = $this->posService->processSale($validated, $request->user()->id);
            
            $this->logActivity('create_sale', "Processed sale for GH₵" . number_format($sale->subtotal, 2) . " (Receipt: {$sale->receipt_number})");

            return response()->json([
                'message' => 'Sale completed successfully.',
                'sale' => $sale
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 400); // 400 Bad Request indicates business logic failure like insufficient stock
        }
    }
}
