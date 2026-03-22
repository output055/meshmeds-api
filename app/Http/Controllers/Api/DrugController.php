<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Drug;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DrugController extends Controller
{
    use LogsActivity;

    public function index()
    {
        $drugs = Drug::all()->map(function ($drug) {
            return [
                'id'                    => (int) $drug->id,
                'name'                  => $drug->name,
                'barcode'               => $drug->barcode ?? '',
                'category'              => $drug->category ?? 'Uncategorized',
                'cost_price'            => (float) $drug->cost_price,
                'selling_price'         => (float) $drug->selling_price,
                'stock_qty'             => (int) $drug->stock_quantity,
                'expiry_date'           => $drug->expiry_date,
                'last_restock_quantity' => (int) $drug->last_restock_quantity,
                'is_service'            => (bool) $drug->is_service,
            ];
        });

        return response()->json($drugs);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255|unique:drugs,name',
            'barcode'       => 'nullable|string|max:100|unique:drugs,barcode',
            'category'      => 'required|string|max:100',
            'cost_price'    => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'stock_qty'     => 'required|integer|min:0',
            'expiry_date'   => 'nullable|date',
            'is_service'    => 'sometimes|boolean',
        ]);

        $drug = Drug::create([
            'name'                  => $data['name'],
            'barcode'               => $data['barcode'],
            'category'              => $data['category'],
            'cost_price'            => $data['cost_price'],
            'selling_price'         => $data['selling_price'],
            'stock_quantity'        => $data['stock_qty'],
            'expiry_date'           => $data['expiry_date'] ?? null,
            'is_service'            => $data['is_service'] ?? false,
            'last_restock_quantity' => 0,
        ]);

        $isService = $data['is_service'] ?? false;
        $action = $isService ? 'add_service' : 'add_drug';
        $type = $isService ? 'service' : 'drug';
        $qtyText = $isService ? '' : " (qty: {$drug->stock_quantity})";
        $this->logActivity($action, "Added {$type} {$drug->name}{$qtyText}");

        return response()->json([
            'id'                    => $drug->id,
            'name'                  => $drug->name,
            'barcode'               => $drug->barcode,
            'category'              => $drug->category,
            'cost_price'            => (float) $drug->cost_price,
            'selling_price'         => (float) $drug->selling_price,
            'stock_qty'             => (int) $drug->stock_quantity,
            'expiry_date'           => $drug->expiry_date,
            'last_restock_quantity' => (int) $drug->last_restock_quantity,
            'is_service'            => (bool) $drug->is_service,
        ], 201);
    }

    public function update(Request $request, Drug $drug)
    {
        $data = $request->validate([
            'name'          => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('drugs')->ignore($drug->id),
            ],
            'barcode'       => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('drugs')->ignore($drug->id),
            ],
            'category'      => 'sometimes|required|string|max:100',
            'cost_price'    => 'sometimes|required|numeric|min:0',
            'selling_price' => 'sometimes|required|numeric|min:0',
            'expiry_date'   => 'nullable|date',
            'is_service'    => 'sometimes|boolean',
        ]);

        $drug->update($data);

        $isService = array_key_exists('is_service', $data) ? $data['is_service'] : $drug->is_service;
        $action = $isService ? 'update_service' : 'update_drug';
        $type = $isService ? 'service' : 'drug';
        $this->logActivity($action, "Updated {$type} {$drug->name}");

        return response()->json([
            'id'                    => $drug->id,
            'name'                  => $drug->name,
            'barcode'               => $drug->barcode,
            'category'              => $drug->category,
            'cost_price'            => (float) $drug->cost_price,
            'selling_price'         => (float) $drug->selling_price,
            'stock_qty'             => (int) $drug->stock_quantity,
            'expiry_date'           => $drug->expiry_date,
            'last_restock_quantity' => (int) $drug->last_restock_quantity,
            'is_service'            => (bool) $drug->is_service,
        ]);
    }

    public function destroy(Drug $drug)
    {
        $name = $drug->name;
        $drug->delete();

        $action = $drug->is_service ? 'delete_service' : 'delete_drug';
        $type = $drug->is_service ? 'service' : 'drug';
        $this->logActivity($action, "Deleted {$type} {$name}");

        return response()->json(['message' => 'Drug deleted successfully.']);
    }

    public function restock(Request $request, Drug $drug)
    {
        $data = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $drug->increment('stock_quantity', $data['quantity']);
        $drug->update(['last_restock_quantity' => $data['quantity']]);

        $this->logActivity('restock_drug', "Restocked {$drug->name} by +{$data['quantity']} units (new total: {$drug->stock_quantity})");

        return response()->json([
            'id'                    => $drug->id,
            'name'                  => $drug->name,
            'barcode'               => $drug->barcode,
            'category'              => $drug->category,
            'cost_price'            => (float) $drug->cost_price,
            'selling_price'         => (float) $drug->selling_price,
            'stock_qty'             => (int) $drug->stock_quantity,
            'expiry_date'           => $drug->expiry_date,
            'last_restock_quantity' => (int) $drug->last_restock_quantity,
            'is_service'            => (bool) $drug->is_service,
        ]);
    }
}
