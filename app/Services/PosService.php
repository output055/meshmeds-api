<?php

namespace App\Services;

use App\Models\Drug;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;

class PosService
{
    /**
     * @param array $data Expected keys: 'items', 'payment_method', 'amount_tendered', 'change_due'
     * @param int $userId
     * @return Sale
     * @throws Exception
     */
    public function processSale(array $data, int $userId): Sale
    {
        return DB::transaction(function () use ($data, $userId) {
            
            $items = $data['items'];
            $totalSubtotal = 0;
            $totalProfit = 0;
            $saleItemsData = [];
            
            // Lock rows one by one to prevent highly concurrent cashier conflicts (pessimistic locking)
            foreach ($items as $item) {
                $drug = Drug::where('id', $item['id'])->lockForUpdate()->first();
                
                if (!$drug) {
                    throw new Exception("Drug with ID {$item['id']} not found.");
                }
                
                if (!$drug->is_service) {
                    if ($drug->stock_quantity < $item['quantity']) {
                        throw new Exception("Insufficient stock for {$drug->name}. Only {$drug->stock_quantity} left.");
                    }
                }
                
                // Track financials securely server-side based on actual database entries
                $unitPrice = $item['price']; 
                $unitProfit = $unitPrice - $drug->cost_price;
                $rowSubtotal = $unitPrice * $item['quantity'];
                
                $totalSubtotal += $rowSubtotal;
                $totalProfit += ($unitProfit * $item['quantity']);
                
                if (!$drug->is_service) {
                    // Deduct physical stock
                    $drug->stock_quantity -= $item['quantity'];
                    $drug->save();
                }
                
                $saleItemsData[] = [
                    'drug_id' => $drug->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'unit_profit' => $unitProfit,
                    'subtotal' => $rowSubtotal,
                ];
            }
            
            // Create immutable Sale Record 
            $receiptNumber = 'RCP-' . strtoupper(Str::random(8)) . '-' . time();
            
            $sale = Sale::create([
                'receipt_number' => $receiptNumber,
                'user_id'        => $userId,
                'subtotal'       => $totalSubtotal,
                'total_profit'   => $totalProfit,
                'payment_method' => $data['payment_method'],
                'amount_tendered'=> $data['amount_tendered'] ?? null,
                'change_due'     => $data['change_due'] ?? null,
                'status'         => 'Completed',
            ]);
            
            // Attach specific Sale Items to the master record
            foreach ($saleItemsData as $itemData) {
                $sale->items()->create($itemData);
            }
            
            return $sale->load('items.drug');
        });
    }
}
