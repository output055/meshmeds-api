<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    use LogsActivity;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $expenses = Expense::with('user:id,name')
            ->orderBy('expense_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();
            
        return response()->json($expenses);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'amount'       => 'required|numeric|min:0',
            'category'     => 'required|string|max:100',
            'description'  => 'nullable|string',
            'expense_date' => 'nullable|date',
        ]);

        $validated['user_id'] = $request->user()->id;
        
        if (!isset($validated['expense_date'])) {
            $validated['expense_date'] = now()->toDateString();
        }

        $expense = Expense::create($validated);
        $expense->load('user:id,name');

        $this->logActivity('add_expense', "Logged expense: {$expense->category} — GH₵" . number_format($expense->amount, 2));

        return response()->json($expense, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Expense $expense)
    {
        return response()->json($expense->load('user:id,name'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Expense $expense)
    {
        $validated = $request->validate([
            'amount'       => 'sometimes|required|numeric|min:0',
            'category'     => 'sometimes|required|string|max:100',
            'description'  => 'nullable|string',
            'expense_date' => 'sometimes|required|date',
        ]);

        $expense->update($validated);
        $expense->load('user:id,name');

        $this->logActivity('update_expense', "Updated expense: {$expense->category} — GH₵" . number_format($expense->amount, 2));

        return response()->json($expense);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Expense $expense)
    {
        $category = $expense->category;
        $expense->delete();

        $this->logActivity('delete_expense', "Deleted expense record: {$category}");

        return response()->json(['message' => 'Expense deleted successfully']);
    }
}
