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
            $bills = Bill::where('quantity', '>', 0)->orderBy('denomination', 'desc')->get();
            $withdrawalBills = [];
            $remainingAmount = $amount;

            $possibleCombinations = [];

            // 1. Ən böyük əskinaslardan istifadə edərək məbləği çıxarmağa çalışırıq
            foreach ($bills as $bill) {
                if ($remainingAmount >= $bill->denomination && $bill->quantity > 0) {
                    $count = min(intdiv($remainingAmount, $bill->denomination), $bill->quantity);
                    $withdrawalBills[$bill->denomination] = $count;
                    $remainingAmount -= $count * $bill->denomination;
                }
            }

            // 2. Əgər məbləğ tam çıxarılmadısa, alternativ kombinasiyaları yoxlayırıq
            if ($remainingAmount > 0) {
                $bestAlternative = null;
                $minRemaining = PHP_INT_MAX;

                foreach ($bills as $bill1) {
                    foreach ($bills as $bill2) {
                        if ($bill1->id !== $bill2->id) {
                            $altRemaining = $amount;
                            $altCombination = [];

                            $count1 = min(intdiv($altRemaining, $bill1->denomination), $bill1->quantity);
                            $altCombination[$bill1->denomination] = $count1;
                            $altRemaining -= $count1 * $bill1->denomination;

                            $count2 = min(intdiv($altRemaining, $bill2->denomination), $bill2->quantity);
                            $altCombination[$bill2->denomination] = $count2;
                            $altRemaining -= $count2 * $bill2->denomination;

                            if ($altRemaining == 0 && $altRemaining < $minRemaining) {
                                $bestAlternative = $altCombination;
                                $minRemaining = $altRemaining;
                            }
                        }
                    }
                }

                if ($bestAlternative) {
                    $withdrawalBills = $bestAlternative;
                    $remainingAmount = 0;
                }
            }

            if ($remainingAmount > 0) {
                DB::rollBack();

                // 3. ATM-nin maksimum nə qədər pul verə biləcəyini müəyyən edirik
                $maxWithdrawableAmount = 0;
                $availableBills = [];
                $tempRemaining = $amount;

                foreach ($bills as $bill) {
                    if ($bill->quantity > 0) {
                        $count = min(intdiv($tempRemaining, $bill->denomination), $bill->quantity);
                        if ($count > 0) {
                            $availableBills[$bill->denomination] = $count;
                            $maxWithdrawableAmount += $count * $bill->denomination;
                            $tempRemaining -= $count * $bill->denomination;
                        }
                    }
                }

                return response()->json([
                    'message' => 'Not enough bills to dispense',
                    'max_withdrawable' => $maxWithdrawableAmount,
                    'available_bills' => $availableBills,
                ], 400);
            }

            // 4. Əgər ATM məbləği verə bilirsə, əskinasları yenilə
            foreach ($withdrawalBills as $denomination => $count) {
                Bill::where('denomination', $denomination)->decrement('quantity', $count);
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
