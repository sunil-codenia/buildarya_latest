<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;

class SaaSInvoiceController extends Controller
{
    public function index(Request $request)
    {
        // Enforce superadmin authorization
        if (!isSuperAdmin()) {
            abort(403, 'Unauthorized access. Only superadmins can view invoices.');
        }

        $companyUid = session()->get('comp_id');
        $invoices = [];
        $error = null;

        if ($companyUid) {
            $shaarvikUrl = rtrim(env('SHAARVIK_URL', 'http://localhost:3000'), '/');
            $apiUrl = "{$shaarvikUrl}/api/mysql/invoices?companyUid={$companyUid}";

            try {
                $response = Http::timeout(10)->get($apiUrl);
                if ($response->successful()) {
                    $invoices = $response->json();
                } else {
                    $error = 'Failed to retrieve invoices from Shaarvik. Status code: ' . $response->status();
                    Log::error("Shaarvik Invoices API error: " . $response->body());
                }
            } catch (\Exception $e) {
                $error = 'Could not connect to Shaarvik service to fetch invoices.';
                Log::error("Shaarvik connection failed: " . $e->getMessage());
            }
        } else {
            $error = 'Company UID not found in session.';
        }

        return view('layouts.invoices', compact('invoices', 'error', 'shaarvikUrl'));
    }

    public function downloadPdf($id)
    {
        if (!isSuperAdmin()) {
            abort(403, 'Unauthorized access. Only superadmins can download invoices.');
        }

        $companyUid = session()->get('comp_id');
        if (!$companyUid) {
            return back()->with('error', 'Company session not found.');
        }

        $shaarvikUrl = rtrim(env('SHAARVIK_URL', 'http://localhost:3000'), '/');
        $apiUrl = "{$shaarvikUrl}/api/mysql/invoices?companyUid={$companyUid}";

        try {
            $response = Http::timeout(10)->get($apiUrl);
            if ($response->successful()) {
                $invoices = $response->json();
                $invoice = collect($invoices)->firstWhere('id', $id);
                if (!$invoice) {
                    abort(404, 'Invoice not found.');
                }

                $pdf = Pdf::loadView('layouts.invoice_pdf', compact('invoice'));
                return $pdf->download("Invoice_{$invoice['invoice_number']}.pdf");
            }
        } catch (\Exception $e) {
            Log::error("Failed to generate invoice PDF: " . $e->getMessage());
        }

        return back()->with('error', 'Could not download invoice PDF at this time.');
    }

    public function createRazorpayOrder(Request $request)
    {
        if (!isSuperAdmin()) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $amount = (float)$request->input('amount');
        if ($amount <= 0) {
            return response()->json(['error' => 'Invalid amount.'], 400);
        }

        $shaarvikUrl = rtrim(env('SHAARVIK_URL', 'http://localhost:3000'), '/');
        try {
            $response = Http::timeout(10)->post("{$shaarvikUrl}/api/payments/razorpay/create-order", [
                'amount' => $amount,
                'currency' => 'INR'
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $data['key_id'] = env('RAZORPAY_KEY_ID', 'rzp_test_T0dSOhqB0vAipt');
                return response()->json($data);
            } else {
                Log::error("Failed to create Razorpay order from Shaarvik: " . $response->body());
                return response()->json(['error' => 'Failed to create payment order.'], 500);
            }
        } catch (\Exception $e) {
            Log::error("Failed to call Shaarvik create-order API: " . $e->getMessage());
            return response()->json(['error' => 'Failed to connect to billing server.'], 500);
        }
    }

    public function finalizePayment(Request $request)
    {
        if (!isSuperAdmin()) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $shaarvikUrl = rtrim(env('SHAARVIK_URL', 'http://localhost:3000'), '/');
        try {
            // 1. Verify Razorpay Payment Signature via Shaarvik
            $verifyResponse = Http::timeout(10)->post("{$shaarvikUrl}/api/payments/razorpay/verify", [
                'razorpay_order_id' => $request->input('razorpay_order_id'),
                'razorpay_payment_id' => $request->input('razorpay_payment_id'),
                'razorpay_signature' => $request->input('razorpay_signature')
            ]);

            if (!$verifyResponse->successful() || !$verifyResponse->json('success')) {
                Log::error("Razorpay verification failed on Shaarvik: " . $verifyResponse->body());
                return response()->json(['error' => 'Payment signature verification failed.'], 400);
            }

            // 2. Finalize Subscription Renewal in Shaarvik
            $renewResponse = Http::timeout(10)->post("{$shaarvikUrl}/api/mysql/subscriptions/renew", [
                'subscriptionId' => $request->input('subscriptionId'),
                'newPlanId' => $request->input('newPlanId'),
                'billingCycle' => $request->input('billingCycle'),
                'startDate' => $request->input('startDate'),
                'endDate' => $request->input('endDate'),
                'amount' => (float)$request->input('amount'),
                'amountPaid' => (float)$request->input('amount'),
                'paymentMode' => 'razorpay',
                'notes' => 'Subscription renewed via Buildarya client portal',
                'transactionId' => $request->input('razorpay_payment_id'),
                'gateway' => 'razorpay'
            ]);

            if ($renewResponse->successful()) {
                return response()->json($renewResponse->json());
            } else {
                Log::error("Failed to renew subscription in Shaarvik: " . $renewResponse->body());
                return response()->json(['error' => 'Payment succeeded but subscription activation failed. Please contact support.'], 500);
            }
        } catch (\Exception $e) {
            Log::error("Exception in finalizePayment: " . $e->getMessage());
            return response()->json(['error' => 'Billing server connection failed during payment finalization.'], 500);
        }
    }
}
