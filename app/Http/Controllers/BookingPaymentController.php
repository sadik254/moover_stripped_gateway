<?php

namespace App\Http\Controllers;

use App\Mail\BookingPaymentReceiptMail;
use App\Models\Affiliate;
use App\Models\AffiliateBookingSettlement;
use App\Models\Booking;
use App\Models\BookingAccessLink;
use App\Models\BookingPayment;
use App\Models\Company;
use App\Models\Customer;
use App\Models\SystemConfig;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\PaymentIntent;
use Stripe\Stripe;
use Stripe\Webhook;

class BookingPaymentController extends Controller
{
    public function webhook(Request $request)
    {
        $payload = $request->getContent();
        $signature = (string) $request->header('Stripe-Signature');
        $secret = (string) config('services.stripe.webhook_secret');

        if ($secret === '') {
            return response()->json(['message' => 'Webhook secret is not configured'], 500);
        }

        try {
            $event = Webhook::constructEvent($payload, $signature, $secret);
        } catch (\UnexpectedValueException $e) {
            return response()->json(['message' => 'Invalid payload'], 400);
        } catch (SignatureVerificationException $e) {
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        $intentObject = $event->data->object ?? null;
        $intentId = $intentObject->id ?? null;

        if (! $intentId) {
            return response()->json(['received' => true]);
        }

        $payment = BookingPayment::where('payment_intent_id', $intentId)->first();
        if (! $payment) {
            return response()->json(['received' => true]);
        }

        Log::info('Stripe webhook received event', [
            'event_type' => $event->type,
            'payment_intent_id' => $intentId,
            'booking_id' => $intentObject->metadata->booking_id ?? null,
            'company_id' => $intentObject->metadata->company_id ?? null,
        ]);

        $handledEvents = [
            'payment_intent.requires_capture',
            'payment_intent.succeeded',
            'payment_intent.payment_failed',
            'payment_intent.canceled',
        ];

        if (! in_array($event->type, $handledEvents, true)) {
            Log::info('Stripe webhook ignored event', [
                'event_type' => $event->type,
                'payment_intent_id' => $intentId,
            ]);

            return response()->json(['received' => true]);
        }

        if (in_array($event->type, $handledEvents, true)) {
            $payment->status = (string) ($intentObject->status ?? $payment->status);
            $payment->raw_payload = $intentObject ? (array) $intentObject : null;

            if (isset($intentObject->amount_received)) {
                $payment->captured_amount = round(((int) $intentObject->amount_received) / 100, 2);
            }

            if (! empty($intentObject->last_payment_error)) {
                $payment->failure_code = $intentObject->last_payment_error->code ?? null;
                $payment->failure_message = $intentObject->last_payment_error->message ?? null;
            }

            $payment->save();

            $booking = $payment->booking;
            if ($booking) {
                switch ($event->type) {
                    case 'payment_intent.succeeded':
                        $booking->payment_status = 'paid';
                        break;
                    case 'payment_intent.payment_failed':
                        $booking->payment_status = 'failed';
                        break;
                    case 'payment_intent.canceled':
                        $booking->payment_status = 'canceled';
                        break;
                    default:
                        $booking->payment_status = (string) ($intentObject->status ?? $booking->payment_status);
                        break;
                }

                $booking->save();

                if ($event->type === 'payment_intent.succeeded') {
                    $this->sendPublicReceipt($booking, $payment);
                }
            }
        }

        return response()->json(['received' => true]);
    }

    public function show(Request $request, $bookingId)
    {
        $booking = Booking::where('company_id', $this->companyId())
            ->where('id', $bookingId)
            ->first();

        if (! $booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        if (! $this->canAccessBooking($request->user(), $booking)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'data' => [
                'booking' => $booking,
                'latest_payment' => $booking->latestPayment,
                'payments' => $booking->payments()->latest()->get(),
            ],
        ]);
    }

    public function authorizePayment(Request $request, $bookingId)
    {
        $authUser = auth('sanctum')->user() ?? $request->user();

        $booking = Booking::where('company_id', $this->companyId())
            ->where('id', $bookingId)
            ->first();

        if (! $booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'payment_method_id' => 'required|string|max:255',
            'booking_access_token' => 'nullable|string|max:80',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($authUser) {
            if (! $this->canAccessBooking($authUser, $booking)) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        } else {
            if (! $request->filled('booking_access_token')) {
                return response()->json([
                    'message' => 'booking_access_token is required for guest authorization',
                ], 422);
            }

            if (
                empty($booking->booking_access_token) ||
                ! hash_equals((string) $booking->booking_access_token, (string) $request->booking_access_token)
            ) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        $config = SystemConfig::where('company_id', $booking->company_id)->first();
        $currency = strtolower((string) ($config->currency ?? 'usd'));

        $estimatedAmount = (float) ($booking->total_price ?? 0);
        if ($estimatedAmount <= 0) {
            return response()->json([
                'message' => 'Booking total_price must be greater than 0 for authorization',
            ], 422);
        }

        $bufferRate = (float) ($config->rate_buffer ?? 0);
        $bufferAmount = round($estimatedAmount * ($bufferRate / 100), 2);
        $authorizedAmount = round($estimatedAmount + $bufferAmount, 2);
        $amountInCents = (int) round($authorizedAmount * 100);

        try {
            Stripe::setApiKey((string) config('services.stripe.secret_key'));

            $intent = PaymentIntent::create([
                'amount' => $amountInCents,
                'currency' => $currency,
                'capture_method' => 'manual',
                'confirm' => true,
                'payment_method' => $request->payment_method_id,
                'payment_method_types' => ['card'],
                'description' => "Booking #{$booking->id} authorization",
                'metadata' => [
                    'booking_id' => (string) $booking->id,
                    'company_id' => (string) $booking->company_id,
                ],
            ]);

            $payment = BookingPayment::create([
                'booking_id' => $booking->id,
                'customer_id' => $booking->customer_id,
                'provider' => 'stripe',
                'currency' => $currency,
                'payment_intent_id' => $intent->id,
                'payment_method_id' => $request->payment_method_id,
                'estimated_amount' => $estimatedAmount,
                'authorized_amount' => $authorizedAmount,
                'status' => $intent->status,
                'raw_payload' => $intent->toArray(),
            ]);

            $booking->payment_method = 'stripe';
            $booking->payment_status = $intent->status === 'requires_capture' ? 'authorized' : $intent->status;
            $booking->rate_buffer = $bufferRate;
            $booking->rate_buffer_amount = $bufferAmount;
            $booking->save();

            return response()->json([
                'message' => 'Payment authorized',
                'data' => [
                    'booking_id' => $booking->id,
                    'payment' => $payment,
                    'payment_intent_status' => $intent->status,
                    'estimated_amount' => $estimatedAmount,
                    'rate_buffer_percent' => $bufferRate,
                    'rate_buffer_amount' => $bufferAmount,
                    'authorized_amount' => $authorizedAmount,
                ],
            ], 201);
        } catch (ApiErrorException $e) {
            $payment = $this->recordFailedAuthorization(
                booking: $booking,
                currency: $currency,
                estimatedAmount: $estimatedAmount,
                authorizedAmount: $authorizedAmount,
                error: $e,
                paymentMethodId: (string) $request->payment_method_id
            );

            $booking->payment_method = 'stripe';
            $booking->payment_status = 'failed';
            $booking->save();

            return response()->json([
                'message' => 'Stripe authorization failed',
                'error' => $e->getMessage(),
                'payment' => $payment,
            ], 422);
        }
    }

    public function capturePayment(Request $request, $bookingId)
    {
        $booking = Booking::where('company_id', $this->companyId())
            ->where('id', $bookingId)
            ->first();

        if (! $booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        if (! $this->canAccessBooking($request->user(), $booking, false)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $payment = BookingPayment::where('booking_id', $booking->id)
            ->latest()
            ->first();

        if (! $payment) {
            return response()->json(['message' => 'No payment authorization found'], 404);
        }

        $finalAmount = (float) ($booking->final_price ?? 0);
        if ($finalAmount <= 0) {
            return response()->json(['message' => 'Booking final_price must be greater than 0'], 422);
        }

        if ($finalAmount > (float) $payment->authorized_amount) {
            return response()->json([
                'message' => 'Final price exceeds authorized amount. Additional charge flow is required.',
            ], 422);
        }

        try {
            Stripe::setApiKey((string) config('services.stripe.secret_key'));
            $amountToCapture = (int) round($finalAmount * 100);

            $intent = PaymentIntent::retrieve($payment->payment_intent_id);
            $capturedIntent = $intent->capture([
                'amount_to_capture' => $amountToCapture,
            ]);

            $payment->captured_amount = $finalAmount;
            $payment->amount_to_capture = $finalAmount;
            $payment->status = $capturedIntent->status;
            $payment->raw_payload = $capturedIntent->toArray();
            $payment->save();

            $booking->payment_status = $capturedIntent->status === 'succeeded' ? 'paid' : $capturedIntent->status;
            $booking->save();

            if ($capturedIntent->status === 'succeeded') {
                $this->sendPublicReceipt($booking, $payment);
            }

            if (
                $booking->affiliate_id &&
                in_array((string) $booking->affiliate_status, ['accepted', 'in_progress', 'completed'], true)
            ) {
                $affiliate = Affiliate::find($booking->affiliate_id);
                if ($affiliate) {
                    $grossAmount = (float) ($booking->final_price ?? $booking->total_price ?? 0);
                    $affiliatePercent = (float) ($affiliate->affiliate_payout_percent ?? 0);
                    $platformPercent = (float) ($affiliate->platform_commission_percent ?? 0);

                    $settlement = AffiliateBookingSettlement::firstOrNew([
                        'booking_id' => $booking->id,
                    ]);

                    $settlement->affiliate_id = $affiliate->id;
                    $settlement->gross_amount = $grossAmount;
                    $settlement->affiliate_percent = $affiliatePercent;
                    $settlement->platform_percent = $platformPercent;
                    $settlement->affiliate_amount = round($grossAmount * ($affiliatePercent / 100), 2);
                    $settlement->platform_amount = round($grossAmount * ($platformPercent / 100), 2);
                    $settlement->currency = strtolower((string) ($affiliate->payout_currency ?: 'usd'));
                    $settlement->accepted_at = $settlement->accepted_at ?: now();

                    if (! empty($affiliate->stripe_connect_account_id)) {
                        $settlement->status = 'ready';
                        $settlement->status_reason = null;
                        $settlement->ready_at = now();
                    } else {
                        $settlement->status = 'on_hold';
                        $settlement->status_reason = 'missing_stripe_account';
                    }

                    $settlement->save();
                }
            }

            return response()->json([
                'message' => 'Payment captured',
                'data' => [
                    'booking_id' => $booking->id,
                    'payment' => $payment->fresh(),
                ],
            ]);
        } catch (ApiErrorException $e) {
            $payment->failure_message = $e->getMessage();
            $payment->status = 'failed';
            $payment->save();

            return response()->json([
                'message' => 'Stripe capture failed',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    private function companyId(): ?int
    {
        return Company::query()->value('id');
    }

    private function sendPublicReceipt(Booking $booking, BookingPayment $payment): void
    {
        $email = $booking->email ?: $booking->customer?->email;
        if (! $email) {
            return;
        }

        $issued = BookingAccessLink::issueIfMissing(
            $booking,
            BookingAccessLink::RECEIPT
        );
        if (! $issued) {
            return;
        }

        [$link, $token] = $issued;
        try {
            Mail::to($email)->send(new BookingPaymentReceiptMail(
                $booking->loadMissing(['company', 'customer', 'latestPayment', 'stops']),
                $payment,
                route('public.trip-receipt', ['token' => $token])
            ));
        } catch (\Throwable $exception) {
            // Allow a later successful payment event/capture retry to send a fresh link.
            $link->delete();
            Log::warning('Booking payment receipt email failed', [
                'booking_id' => $booking->id,
                'email' => $email,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function canAccessBooking($authUser, Booking $booking, bool $allowCustomer = true): bool
    {
        if ($authUser instanceof User) {
            return in_array((string) $authUser->user_type, ['admin', 'dispatcher'], true);
        }

        if ($allowCustomer && $authUser instanceof Customer) {
            return (int) $booking->customer_id === (int) $authUser->id;
        }

        return false;
    }

    private function recordFailedAuthorization(
        Booking $booking,
        string $currency,
        float $estimatedAmount,
        float $authorizedAmount,
        ApiErrorException $error,
        ?string $paymentMethodId = null
    ): BookingPayment {
        $errorBody = method_exists($error, 'getJsonBody') ? $error->getJsonBody() : null;
        $paymentIntentId = data_get($errorBody, 'error.payment_intent.id');

        return BookingPayment::create([
            'booking_id' => $booking->id,
            'customer_id' => $booking->customer_id,
            'provider' => 'stripe',
            'currency' => $currency,
            'payment_intent_id' => $paymentIntentId ?: 'failed_'.Str::uuid()->toString(),
            'payment_method_id' => $paymentMethodId,
            'estimated_amount' => $estimatedAmount,
            'authorized_amount' => $authorizedAmount,
            'status' => 'failed',
            'failure_code' => data_get($errorBody, 'error.code'),
            'failure_message' => $error->getMessage(),
            'raw_payload' => $errorBody ?: ['message' => $error->getMessage()],
        ]);
    }
}
