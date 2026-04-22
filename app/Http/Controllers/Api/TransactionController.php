<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TransactionController extends Controller
{
    public function __construct(private TransactionService $transactionService) {}
    
     /**
     * GET /api/transactions
     * List transactions (admin sees all, business sees own).
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'type'       => ['nullable', Rule::in(['credit', 'debit'])],
            'status'     => ['nullable', Rule::in(['pending', 'completed', 'failed', 'reversed'])],
            'from'       => ['nullable', 'date'],
            'to'         => ['nullable', 'date', 'after_or_equal:from'],
            'min_amount' => ['nullable', 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric', 'min:0'],
            'reference'  => ['nullable', 'string', 'max:100'],
            'user_id'    => ['nullable', 'integer', 'exists:users,id'],
            'per_page'   => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
 
        // Non-admins cannot filter by other user's transactions
        // if (! $request->user()->isAdmin()) {
        //     unset($filters['user_id']);
        // }
        // $transactions = $this->transactionService->listTransactions(
        //     user: $request->user(),
        //     filters: $filters,
        //     perPage: (int) ($filters['per_page'] ?? 15),
        // );
 
        // return response()->json(['data' => $transactions]);

        $transactions = $this->transactionService->listTransactions(
            user: $request->user(),
            filters: $filters,
            perPage: (int) ($filters['per_page'] ?? 15),
        );
 
        return response()->json(['data' => $transactions]);
    }
    
    
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
        // Pull idempotency key from header (standard pattern)
        $idempotencyKey = $request->header('Indempotency-Key');
        
        if ($idempotencyKey) {
            $data['idempotency_key'] = $idempotencyKey;
        }
 
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

    public function show(Request $request, string $id): JsonResponse
    {
        $transaction = $this->transactionService->findForUser($id, $request->user());
 
        return response()->json(['data' => $transaction]);
    }
}
