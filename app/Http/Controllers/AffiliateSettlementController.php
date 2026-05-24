<?php

namespace App\Http\Controllers;

use App\Mail\AffiliateDisbursementPaidMail;
use App\Models\Affiliate;
use App\Models\AffiliateBookingSettlement;
use App\Models\AffiliateDisbursement;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;
use Stripe\Transfer;

class AffiliateSettlementController extends Controller
{
    public function mySettlements(Request $request)
    {
        $user = $request->user();
        if (! $user instanceof User || (string) $user->user_type !== 'affiliate') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $affiliate = Affiliate::where('user_id', $user->id)->first();
        if (! $affiliate) {
            return response()->json(['message' => 'Affiliate profile not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => ['sometimes', Rule::in(['pending', 'ready', 'on_hold', 'paid', 'failed'])],
            'date_from' => 'sometimes|date',
            'date_to' => 'sometimes|date|after_or_equal:date_from',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $query = AffiliateBookingSettlement::with(['booking:id,status,pickup_time,total_price,final_price'])
            ->where('affiliate_id', $affiliate->id);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $perPage = (int) $request->input('per_page', 20);
        $data = $query->orderByDesc('id')->paginate($perPage)->withQueryString();

        return response()->json(['data' => $data]);
    }

    public function myDisbursements(Request $request)
    {
        $user = $request->user();
        if (! $user instanceof User || (string) $user->user_type !== 'affiliate') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $affiliate = Affiliate::where('user_id', $user->id)->first();
        if (! $affiliate) {
            return response()->json(['message' => 'Affiliate profile not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => ['sometimes', Rule::in(['paid', 'failed'])],
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $query = AffiliateDisbursement::with([
            'booking:id,status,pickup_time,total_price,final_price',
            'settlement:id,booking_id,status,status_reason',
        ])->where('affiliate_id', $affiliate->id);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $perPage = (int) $request->input('per_page', 20);
        $data = $query->orderByDesc('id')->paginate($perPage)->withQueryString();

        return response()->json(['data' => $data]);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user instanceof User || ! in_array((string) $user->user_type, ['admin', 'dispatcher'], true)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => ['sometimes', Rule::in(['pending', 'ready', 'on_hold', 'paid', 'failed'])],
            'affiliate_id' => ['sometimes', Rule::exists('affiliates', 'id')],
            'date_from' => 'sometimes|date',
            'date_to' => 'sometimes|date|after_or_equal:date_from',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $query = AffiliateBookingSettlement::with(['affiliate:id,name,email', 'booking:id,status,pickup_time,total_price,final_price']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('affiliate_id')) {
            $query->where('affiliate_id', $request->affiliate_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $perPage = (int) $request->input('per_page', 20);
        $data = $query->orderByDesc('id')->paginate($perPage)->withQueryString();

        return response()->json(['data' => $data]);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        if (! $user instanceof User || ! in_array((string) $user->user_type, ['admin', 'dispatcher'], true)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $settlement = AffiliateBookingSettlement::with([
            'affiliate:id,name,email,stripe_connect_account_id,payout_currency',
            'booking:id,status,payment_status,total_price,final_price,affiliate_status',
            'disbursements',
        ])->find($id);

        if (! $settlement) {
            return response()->json(['message' => 'Settlement not found'], 404);
        }

        return response()->json(['data' => $settlement]);
    }

    public function disburse(Request $request, $id)
    {
        $user = $request->user();
        if (! $user instanceof User || ! in_array((string) $user->user_type, ['admin', 'dispatcher'], true)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $settlement = AffiliateBookingSettlement::with(['affiliate', 'booking'])->find($id);
        if (! $settlement) {
            return response()->json(['message' => 'Settlement not found'], 404);
        }

        if (! in_array((string) $settlement->status, ['ready', 'failed', 'on_hold'], true)) {
            return response()->json(['message' => 'Settlement is not eligible for disbursement'], 422);
        }

        $affiliate = $settlement->affiliate;
        if (! $affiliate) {
            return response()->json(['message' => 'Affiliate not found'], 404);
        }

        if (empty($affiliate->stripe_connect_account_id)) {
            $settlement->status = 'on_hold';
            $settlement->status_reason = 'missing_stripe_account';
            $settlement->save();

            Log::warning('Affiliate disbursement blocked: missing Stripe Connect account', [
                'settlement_id' => $settlement->id,
                'affiliate_id' => $settlement->affiliate_id,
                'booking_id' => $settlement->booking_id,
                'amount' => $settlement->affiliate_amount,
                'currency' => $settlement->currency,
                'processed_by_user_id' => $user->id,
                'reason' => 'missing_stripe_account',
            ]);

            return response()->json([
                'message' => 'Settlement is on hold due to missing stripe account',
                'data' => $settlement,
            ], 422);
        }

        $amount = (float) $settlement->affiliate_amount;
        if ($amount <= 0) {
            $settlement->status = 'paid';
            $settlement->status_reason = 'zero_amount';
            $settlement->paid_at = now();
            $settlement->save();

            AffiliateDisbursement::create([
                'affiliate_booking_settlement_id' => $settlement->id,
                'affiliate_id' => $settlement->affiliate_id,
                'booking_id' => $settlement->booking_id,
                'amount' => 0,
                'currency' => $settlement->currency,
                'status' => 'paid',
                'processed_by_user_id' => $user->id,
            ]);

            return response()->json([
                'message' => 'Settlement marked as paid with zero amount',
                'data' => $settlement->fresh(),
            ]);
        }

        try {
            Stripe::setApiKey((string) config('services.stripe.secret_key'));

            $transfer = Transfer::create([
                'amount' => (int) round($amount * 100),
                'currency' => strtolower((string) $settlement->currency),
                'destination' => (string) $affiliate->stripe_connect_account_id,
                'metadata' => [
                    'settlement_id' => (string) $settlement->id,
                    'booking_id' => (string) $settlement->booking_id,
                    'affiliate_id' => (string) $settlement->affiliate_id,
                ],
            ]);

            $settlement->status = 'paid';
            $settlement->status_reason = null;
            $settlement->paid_at = now();
            $settlement->save();

            $disbursement = AffiliateDisbursement::create([
                'affiliate_booking_settlement_id' => $settlement->id,
                'affiliate_id' => $settlement->affiliate_id,
                'booking_id' => $settlement->booking_id,
                'amount' => $amount,
                'currency' => strtolower((string) $settlement->currency),
                'status' => 'paid',
                'stripe_transfer_id' => $transfer->id,
                'processed_by_user_id' => $user->id,
            ]);

            $this->sendDisbursementPaidMail($affiliate, $disbursement);

            return response()->json([
                'message' => 'Disbursement paid successfully',
                'data' => [
                    'settlement' => $settlement->fresh(),
                    'disbursement' => $disbursement,
                ],
            ]);
        } catch (ApiErrorException $e) {
            Log::error('Affiliate disbursement failed', [
                'settlement_id' => $settlement->id,
                'affiliate_id' => $settlement->affiliate_id,
                'booking_id' => $settlement->booking_id,
                'amount' => $amount,
                'currency' => strtolower((string) $settlement->currency),
                'processed_by_user_id' => $user->id,
                'stripe_connect_account_id' => $affiliate->stripe_connect_account_id,
                'stripe_error' => $e->getMessage(),
                'stripe_error_code' => $e->getStripeCode(),
                'stripe_http_status' => $e->getHttpStatus(),
            ]);

            $settlement->status = 'failed';
            $settlement->status_reason = $e->getMessage();
            $settlement->save();

            $disbursement = AffiliateDisbursement::create([
                'affiliate_booking_settlement_id' => $settlement->id,
                'affiliate_id' => $settlement->affiliate_id,
                'booking_id' => $settlement->booking_id,
                'amount' => $amount,
                'currency' => strtolower((string) $settlement->currency),
                'status' => 'failed',
                'failure_message' => $e->getMessage(),
                'processed_by_user_id' => $user->id,
            ]);

            return response()->json([
                'message' => 'Disbursement failed',
                'error' => $e->getMessage(),
                'data' => [
                    'settlement' => $settlement->fresh(),
                    'disbursement' => $disbursement,
                ],
            ], 422);
        }
    }

    public function disbursements(Request $request)
    {
        $user = $request->user();
        if (! $user instanceof User || ! in_array((string) $user->user_type, ['admin', 'dispatcher'], true)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => ['sometimes', Rule::in(['paid', 'failed'])],
            'affiliate_id' => ['sometimes', Rule::exists('affiliates', 'id')],
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $query = AffiliateDisbursement::with([
            'affiliate:id,name,email',
            'booking:id,status,pickup_time,total_price,final_price',
            'processedBy:id,name,email',
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('affiliate_id')) {
            $query->where('affiliate_id', $request->affiliate_id);
        }

        $perPage = (int) $request->input('per_page', 20);
        $data = $query->orderByDesc('id')->paginate($perPage)->withQueryString();

        return response()->json(['data' => $data]);
    }

    private function sendDisbursementPaidMail(Affiliate $affiliate, AffiliateDisbursement $disbursement): void
    {
        try {
            Mail::to($affiliate->email)->send(new AffiliateDisbursementPaidMail(
                affiliate: $affiliate,
                disbursement: $disbursement
            ));
        } catch (\Throwable $e) {
            Log::warning('Affiliate disbursement paid mail failed', [
                'affiliate_id' => $affiliate->id,
                'disbursement_id' => $disbursement->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
