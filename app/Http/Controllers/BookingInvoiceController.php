<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Stripe\Customer as StripeCustomer;
use Stripe\Invoice;
use Stripe\InvoiceItem;
use Stripe\Stripe;

class BookingInvoiceController extends Controller
{
    public function create(Request $request, int $bookingId)
    {
        $user = $request->user();
        if (! $user instanceof User || ! in_array((string) $user->user_type, ['admin', 'dispatcher'], true)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $company = Company::first();
        if (! $company) return response()->json(['message' => 'Company not found'], 404);
        $booking = Booking::where('company_id', $company->id)
            ->with('company')
            ->find($bookingId);
        if (! $booking) return response()->json(['message' => 'Booking not found'], 404);
        $isLateCancellation = $booking->status === 'cancelled' && $booking->payment_status === 'late_fee_unpaid';
        if (($booking->booking_origin !== 'admin' || $booking->status !== 'completed') && ! $isLateCancellation) {
            return response()->json(['message' => 'Invoices can only be sent for completed admin bookings or unpaid late cancellations'], 422);
        }
        if ($booking->payment_status === 'paid') {
            return response()->json(['message' => 'This booking has already been paid'], 422);
        }
        $invoiceAmount = (float) $booking->final_price;
        if ($isLateCancellation) {
            $captured = (float) BookingPayment::where('booking_id', $booking->id)
                ->whereIn('payment_kind', ['authorization', 'late_cancellation_charge'])
                ->where('status', 'succeeded')
                ->sum('captured_amount');
            $invoiceAmount = max(0, $invoiceAmount - $captured);
        }
        if (! $booking->email || $invoiceAmount <= 0) {
            return response()->json(['message' => 'A customer email and final amount are required'], 422);
        }

        Stripe::setApiKey((string) config('services.stripe.secret_key'));
        try {
            $existingPayment = BookingPayment::where('booking_id', $booking->id)
                ->where('provider', 'stripe_invoice')
                ->latest()
                ->first();
            if ($existingPayment?->stripe_invoice_id) {
                $invoice = Invoice::retrieve($existingPayment->stripe_invoice_id);
                if ($invoice->status === 'paid') {
                    return response()->json(['message' => 'This invoice has already been paid'], 422);
                }
                $invoice->sendInvoice();
                return response()->json(['message' => 'Stripe invoice resent successfully', 'data' => ['hosted_invoice_url' => $invoice->hosted_invoice_url]]);
            }

            $customer = StripeCustomer::create(['email' => $booking->email, 'name' => $booking->name, 'metadata' => ['booking_id' => $booking->id]]);
            $invoice = Invoice::create(['customer' => $customer->id, 'collection_method' => 'send_invoice', 'days_until_due' => 1, 'auto_advance' => false, 'metadata' => ['booking_id' => $booking->id, 'company_id' => $booking->company_id]]);
            InvoiceItem::create(['customer' => $customer->id, 'invoice' => $invoice->id, 'currency' => strtolower((string) ($booking->company?->currency ?? 'usd')), 'amount' => (int) round($invoiceAmount * 100), 'description' => $isLateCancellation ? "Booking #{$booking->id} late cancellation fee" : "Booking #{$booking->id}"]);
            $invoice = $invoice->finalizeInvoice();
            $invoice->sendInvoice();

            BookingPayment::create(['booking_id' => $booking->id, 'customer_id' => $booking->customer_id, 'provider' => 'stripe_invoice', 'currency' => strtolower((string) ($booking->company?->currency ?? 'usd')), 'payment_intent_id' => $invoice->payment_intent ?: 'invoice_'.$invoice->id, 'stripe_invoice_id' => $invoice->id, 'estimated_amount' => $invoiceAmount, 'authorized_amount' => 0, 'amount_to_capture' => $invoiceAmount, 'status' => $invoice->status, 'raw_payload' => $invoice->toArray()]);
            $booking->payment_method = 'stripe_invoice';
            $booking->payment_status = 'invoice_sent';
            $booking->save();

            return response()->json(['message' => 'Stripe invoice sent successfully', 'data' => ['hosted_invoice_url' => $invoice->hosted_invoice_url]]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Unable to create Stripe invoice', 'error' => $e->getMessage()], 422);
        }
    }
}
