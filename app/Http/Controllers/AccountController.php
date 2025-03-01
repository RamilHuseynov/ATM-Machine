<?php

namespace App\Http\Controllers;

use App\Http\Requests\AccountRequest;
use App\Models\Account;
use Illuminate\Http\JsonResponse;

class AccountController extends Controller
{

    public function index(): JsonResponse
{
    return response()->json(Account::all());
}

    public function store(AccountRequest $request): JsonResponse
    {
        $account = Account::create($request->validated());

        return response()->json([
            'message' => 'Hesab yaradıldı!',
            'account' => $account
        ], 201);
    }

    public function show(Account $account): JsonResponse
    {
        return response()->json($account);
    }
}


