<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewPtoRequest as ReviewPtoFormRequest;
use App\Http\Requests\StorePtoRequest as StorePtoFormRequest;
use App\Http\Resources\PtoRequestResource;
use App\Models\PtoBalance;
use App\Models\PtoRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PtoController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $ptoRequests = PtoRequest::query()
            ->when($request->query('employee_id'), fn ($q, $id) => $q->where('employee_id', $id))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('type'), fn ($q, $type) => $q->where('type', $type))
            ->with('employee')
            ->orderByDesc('created_at')
            ->paginate($request->query('per_page', 25));

        return PtoRequestResource::collection($ptoRequests);
    }

    public function store(StorePtoFormRequest $request): JsonResponse
    {
        // Check balance (skip for UNPAID type)
        if ($request->type !== 'UNPAID') {
            $balance = PtoBalance::where('employee_id', $request->employee_id)
                ->where('type', $request->type)
                ->where('year', date('Y'))
                ->first();

            if (! $balance || (float) $balance->balance_hours < (float) $request->hours) {
                return response()->json([
                    'message' => 'Insufficient PTO balance',
                ], 422);
            }
        }

        $pto = PtoRequest::create([
            ...$request->validated(),
            'status' => 'PENDING',
        ]);

        return (new PtoRequestResource($pto))
            ->response()
            ->setStatusCode(201);
    }

    public function review(ReviewPtoFormRequest $request, PtoRequest $ptoRequest): JsonResponse
    {
        if ($ptoRequest->status !== 'PENDING') {
            return response()->json(['message' => 'PTO request is not pending'], 422);
        }

        $newStatus = $request->action === 'approve' ? 'APPROVED' : 'DENIED';

        $ptoRequest->update([
            'status' => $newStatus,
            'reviewed_by' => $request->user()->id,
            'review_reason' => $request->reason,
            'reviewed_at' => now(),
        ]);

        // If approved and not UNPAID, deduct from balance
        if ($newStatus === 'APPROVED' && $ptoRequest->type !== 'UNPAID') {
            $balance = PtoBalance::where('employee_id', $ptoRequest->employee_id)
                ->where('type', $ptoRequest->type)
                ->where('year', $ptoRequest->start_date->year)
                ->first();

            if ($balance) {
                $balance->update([
                    'used_hours' => (float) $balance->used_hours + (float) $ptoRequest->hours,
                    'balance_hours' => (float) $balance->balance_hours - (float) $ptoRequest->hours,
                ]);
            }
        }

        return response()->json([
            'data' => new PtoRequestResource($ptoRequest->fresh()),
            'message' => "PTO request {$newStatus}",
        ]);
    }

    public function balance(Request $request, string $employeeId): JsonResponse
    {
        $year = $request->query('year', date('Y'));

        $balances = PtoBalance::where('employee_id', $employeeId)
            ->where('year', $year)
            ->get()
            ->map(fn ($b) => [
                'type' => $b->type,
                'balance_hours' => (float) $b->balance_hours,
                'accrued_hours' => (float) $b->accrued_hours,
                'used_hours' => (float) $b->used_hours,
                'year' => $b->year,
            ]);

        return response()->json(['data' => $balances]);
    }
}
