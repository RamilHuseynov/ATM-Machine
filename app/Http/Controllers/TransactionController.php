<?php

namespace App\Http\Controllers;

use App\Http\Requests\WithdrawRequest;
use App\Models\Account;
use App\Models\Bill;
use App\Models\Transaction;
use App\Models\TransactionType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    public function withdraw(WithdrawRequest $request, Account $account)
    {
        $amount = $request->amount;

        if ($account->balance < $amount) {
            return response()->json(['message' => 'Insufficient balance'], 400);
        }

        DB::beginTransaction();
        try {
            $bills = Bill::orderBy('denomination', 'desc')->get();
            $withdrawalBills = [];
            $remainingAmount = $amount;

            foreach ($bills as $bill) {
                if ($remainingAmount >= $bill->denomination && $bill->quantity > 0) {
                    $count = min(intdiv($remainingAmount, $bill->denomination), $bill->quantity);
                    $withdrawalBills[$bill->denomination] = $count;
                    $remainingAmount -= $count * $bill->denomination;
                    $bill->decrement('quantity', $count);
                }
            }

            if ($remainingAmount > 0) {
                DB::rollBack();
                return response()->json(['message' => 'Not enough bills to dispense'], 400);
            }

            $account->decrement('balance', $amount);

            $transaction = Transaction::create([
                'account_id' => $account->id,
                'amount' => $amount,
                'type' => TransactionType::WITHDRAW,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Withdrawal successful',
                'transaction' => $transaction,
                'bills' => $withdrawalBills,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Transaction failed', 'error' => $e->getMessage()], 500);
        }
    }

    public function history(Account $account)
    {
        return response()->json([
            'transactions' => $account->transactions,
        ], 200);
    }

    public function deleteTransaction(Request $request, Transaction $transaction)
    {
        if ($request->user()->is_admin) {
            $transaction->delete();
            return response()->json(['message' => 'Transaction deleted'], 200);
        }

        return response()->json(['message' => 'Unauthorized'], 403);
    }
}
