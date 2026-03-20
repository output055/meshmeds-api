<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\SaleReversal;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class VoidSaleService
{
    /**
     * @param string $receiptNumber
     * @param string $reason
     * @param int $userId
     * @param array $returnedItems Array of ['id' => sale_item_id, 'return_quantity' => qty]
     * @return array
     * @throws Exception
     */
    public function reverseSale(string $receiptNumber, string $reason, int $userId, array $returnedItems): array
    {
        return DB::transaction(function () use ($receiptNumber, $reason, $userId, $returnedItems) {
            
            // 1. Fetch sale with physical items
            $sale = Sale::with(['items.drug'])->where('receipt_number', $receiptNumber)->lockForUpdate()->first();
            
            if (!$sale) {
                throw new Exception("Sale with receipt {$receiptNumber} not found.");
            }

            // 2. State Check
            if (strtolower($sale->status) === 'voided') {
                throw new Exception("This sale has already been fully reversed.");
            }

            // 3. The Strict 24-Hour Rule
            if ($sale->created_at->diffInHours(Carbon::now()) > 24) {
                throw new Exception("Sales can only be reversed strictly within 24 hours of the original transaction.");
            }

            $totalRefundForThisRequest = 0;
            $totalProfitRefundForThisRequest = 0;
            $itemsLog = [];

            // 4. Process each requested return item
            foreach ($returnedItems as $reqItem) {
                $saleItem = $sale->items->firstWhere('id', $reqItem['id']);
                
                if (!$saleItem) {
                    throw new Exception("Item ID {$reqItem['id']} does not belong to this sale.");
                }

                $qtyToReturn = (int) $reqItem['return_quantity'];
                
                if ($qtyToReturn <= 0) {
                    continue; // Skip zero-quantity requests implicitly
                }

                $availableToReturn = $saleItem->quantity - $saleItem->returned_quantity;
                
                if ($qtyToReturn > $availableToReturn) {
                    throw new Exception("Cannot return {$qtyToReturn} of {$saleItem->drug->name}. Only {$availableToReturn} available to return.");
                }

                // Increment returned quantity and DECREMENT actual quantity and subtotal to reflect net sale
                $saleItem->returned_quantity += $qtyToReturn;
                $saleItem->quantity -= $qtyToReturn;
                
                // Calculate monetary impact
                $itemRefund = $qtyToReturn * $saleItem->unit_price;
                $saleItem->subtotal -= $itemRefund;
                $saleItem->save();

                $totalRefundForThisRequest += $itemRefund;
                // Accumulate the profit to subtract from the main sale record
                $profitRefund = $qtyToReturn * $saleItem->unit_profit;
                $totalProfitRefundForThisRequest += $profitRefund;

                // Restock Inventory with Pessimistic Locking
                $drug = $saleItem->drug()->lockForUpdate()->first();
                if ($drug) {
                    $drug->stock_quantity += $qtyToReturn;
                    $drug->save();
                }

                $itemsLog[] = [
                    'sale_item_id' => $saleItem->id,
                    'drug_name' => $drug ? $drug->name : 'Unknown',
                    'returned_quantity' => $qtyToReturn,
                    'refunded_amount' => $itemRefund
                ];
            }

            if ($totalRefundForThisRequest <= 0) {
                throw new Exception("No valid items to return. Quantities must be greater than zero.");
            }

            // 5. Update Sale Totals & Status
            $sale->refunded_amount += $totalRefundForThisRequest;
            // Decrement the original sale actuals so the history reflects net sale
            $sale->subtotal -= $totalRefundForThisRequest;
            $sale->total_profit -= $totalProfitRefundForThisRequest;
            
            // Re-evaluate if the sale is fully voided or only partially refunded
            $allReturned = true;
            foreach ($sale->items as $item) {
                if ($item->quantity > 0) { // If any items still exist on the receipt
                    $allReturned = false;
                    break;
                }
            }

            $sale->status = $allReturned ? 'Voided' : 'Partially Refunded';
            $sale->save();

            // 6. Create Permanent Audit Ledger Record
            SaleReversal::create([
                'sale_id' => $sale->id,
                'user_id' => $userId,
                'amount' => $totalRefundForThisRequest,
                'reason' => $reason,
                'returned_items' => $itemsLog
            ]);

            return [
                'receipt_number' => $sale->receipt_number,
                'refund_amount' => $totalRefundForThisRequest,
                'status' => $sale->status
            ];
        });
    }
}
