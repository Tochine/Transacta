<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class TransactionController extends Controller
{
    public function __construct(private TransactionService $transactionService) {}
    public function store(Request $request): JsonResponse
    {

        $validator = Validator::make($request->all(), [
            'amount'          => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'type'            => ['required'],
            'currency'        => ['nullable', 'string', 'size:3'],
            'description'     => ['nullable', 'string', 'max:500'],
            'metadata'        => ['nullable', 'array'],
            'metadata.*'      => ['nullable'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
 
        $data['channel'] = 'api';
 
        try {
            $transaction = $this->transactionService->createTransaction($request->user(), $data);
        } catch (InsufficientFundsException $e) {
            return response()->json(['message' => $e->getMessage(), 'error' => 'insufficient_funds'], 422);
        } catch (\App\Exceptions\DuplicateTransactionException $e) {
            return response()->json(['message' => $e->getMessage(), 'error' => 'duplicate_reference'], 409);
        }
 
        return response()->json([
            'message' => 'Transaction created successfully.',
            'data'    => $transaction,
        ], 201);
    }
}
