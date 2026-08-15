<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReceiptResource;
use App\Models\Payment;
use App\Services\ReceiptService;
use Illuminate\Http\Request;

class ReceiptController extends Controller
{
    public function __construct(
        private ReceiptService $receiptService
    ) {}

    public function index(Request $request)
    {
        $receipts = $this->receiptService->listForUser($request->user()->id);

        return ReceiptResource::collection($receipts);
    }

    public function show(Request $request, int $id)
    {
        $receipt = $this->receiptService->showForUser($id, $request->user()->id);
        if (!$receipt) {
            return response()->json(['message' => 'Receipt not found'], 404);
        }

        return new ReceiptResource($receipt);
    }

    public function paymentReceipt(Payment $payment)
    {
        $receipt = $this->receiptService->findByPayment($payment->id);
        if (!$receipt) {
            return response()->json(['message' => 'Receipt not found for this payment'], 404);
        }

        return new ReceiptResource($receipt);
    }
}
