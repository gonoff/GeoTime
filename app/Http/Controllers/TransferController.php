<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransferRequest;
use App\Http\Resources\TransferResource;
use App\Models\Transfer;
use App\Services\TransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TransferController extends Controller
{
    public function __construct(
        private readonly TransferService $transferService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $transfers = Transfer::query()
            ->when($request->query('employee_id'), fn ($q, $id) => $q->where('employee_id', $id))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->with(['employee', 'fromTeam', 'toTeam'])
            ->orderByDesc('created_at')
            ->paginate($request->query('per_page', 25));

        return TransferResource::collection($transfers);
    }

    public function store(StoreTransferRequest $request): JsonResponse
    {
        $user = $request->user();
        $isTeamLead = $user->role === 'team_lead';

        $transfer = Transfer::create([
            ...$request->validated(),
            'initiated_by' => $user->id,
            'status' => $isTeamLead ? 'PENDING' : 'COMPLETED',
        ]);

        // If admin/manager, execute immediately
        if (! $isTeamLead) {
            $transfer->update(['approved_by' => $user->id]);
            $this->transferService->executeTransfer($transfer);
        }

        return (new TransferResource($transfer->fresh()))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Transfer $transfer): TransferResource
    {
        $transfer->load(['employee', 'fromTeam', 'toTeam']);

        return new TransferResource($transfer);
    }

    public function approve(Request $request, Transfer $transfer): JsonResponse
    {
        if (! $request->user()->isAdmin() && ! $request->user()->isManager()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($transfer->status !== 'PENDING') {
            return response()->json(['message' => 'Transfer is not pending'], 422);
        }

        $transfer->update(['approved_by' => $request->user()->id]);
        $this->transferService->executeTransfer($transfer);

        return response()->json([
            'data' => new TransferResource($transfer->fresh()),
            'message' => 'Transfer approved and executed',
        ]);
    }

    public function reject(Request $request, Transfer $transfer): JsonResponse
    {
        if (! $request->user()->isAdmin() && ! $request->user()->isManager()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($transfer->status !== 'PENDING') {
            return response()->json(['message' => 'Transfer is not pending'], 422);
        }

        $transfer->update([
            'status' => 'REJECTED',
            'approved_by' => $request->user()->id,
        ]);

        return response()->json([
            'data' => new TransferResource($transfer->fresh()),
            'message' => 'Transfer rejected',
        ]);
    }
}
