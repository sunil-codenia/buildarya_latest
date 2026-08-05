<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class SaaSInvoiceController extends Controller
{
    /**
     * Fetch invoices for the given company UID from Shaarvik.
     * Tries companyUid param first (newer API), falls back to companyId + client-side filtering (older API).
     */
    private function fetchInvoicesForCompany(string $shaarvikUrl, string $companyUid): array
    {
        // Try companyUid first (newer Shaarvik API)
        $response = Http::timeout(10)->get("{$shaarvikUrl}/api/mysql/invoices", [
            'companyUid' => $companyUid,
        ]);

        if ($response->successful()) {
            return $response->json() ?: [];
        }

        if ($response->status() == 400) {
            // Fallback: older Shaarvik API only supports companyId.
            Log::info("Shaarvik companyUid not supported (400), falling back to companyId filter for: {$companyUid}");
            $fallbackResponse = Http::timeout(10)->get("{$shaarvikUrl}/api/mysql/invoices", [
                'companyId' => 1,
            ]);

            if ($fallbackResponse->successful()) {
                $allInvoices = $fallbackResponse->json() ?: [];
                $companyName = session()->get('comp_name', '');
                $companyEmail = session()->get('comp_email', '');

                return array_values(array_filter($allInvoices, function ($inv) use ($companyUid, $companyName, $companyEmail) {
                    $clientName = strtolower($inv['client']['name'] ?? $inv['client_name'] ?? '');
                    $clientEmail = strtolower($inv['client']['email'] ?? $inv['client_email'] ?? '');
                    $uidLower = strtolower($companyUid);
                    $nameLower = strtolower($companyName);
                    $emailLower = strtolower($companyEmail);

                    // Match by client name against company UID or company name, or by email
                    return $clientName === $uidLower
                        || (!empty($nameLower) && $clientName === $nameLower)
                        || (!empty($emailLower) && !empty($clientEmail) && $clientEmail === $emailLower);
                }));
            }

            Log::error("Shaarvik Invoices API fallback error: " . $fallbackResponse->body());
        } else {
            Log::error("Shaarvik Invoices API error ({$response->status()}): " . $response->body());
        }

        return [];
    }

    public function index(Request $request)
    {
        // Enforce superadmin authorization
        if (!isSuperAdmin() && checkmodulepermission(16, 'can_report') != 1) {
            abort(403, 'Unauthorized access. Only superadmins can view invoices.');
        }

        $companyUid = session()->get('comp_id');
        $invoices = [];
        $error = null;
        $shaarvikUrl = rtrim(env('SHAARVIK_URL', 'https://shaarviktechnologies.com'), '/');

        if ($companyUid) {
            try {
                $invoices = $this->fetchInvoicesForCompany($shaarvikUrl, $companyUid);
                if (empty($invoices)) {
                    $error = null; // No invoices found is not an error
                }
            } catch (\Exception $e) {
                $error = 'Could not connect to Shaarvik service to fetch invoices.';
                Log::error("Shaarvik connection failed: " . $e->getMessage());
            }
        } else {
            $error = 'Company UID not found in session.';
        }
        $company = null;
        $compDbId = session()->get('comp_db_id');
        if ($compDbId) {
            $company = DB::table('companies')->where('id', $compDbId)->first();
        }

        // Fetch plans from Shaarvik
        $plans = [];
        try {
            $response = Http::timeout(5)->get("{$shaarvikUrl}/api/public/pricing-plans");
            if ($response->successful()) {
                $plans = $response->json();
            }
        } catch (\Exception $e) {
            Log::error("Failed to fetch plans: " . $e->getMessage());
        }

        if (empty($plans)) {
            $plans = [
                [
                    'id' => '6',
                    'name' => 'Starter',
                    'price' => 750,
                    'maxUsers' => 10,
                    'maxSites' => 12,
                    'billingCycle' => 'monthly',
                ],
                [
                    'id' => '7',
                    'name' => 'Growth',
                    'price' => 1499,
                    'maxUsers' => 18,
                    'maxSites' => 16,
                    'billingCycle' => 'monthly',
                ]
            ];
        }

        return view('layouts.invoices', compact('invoices', 'error', 'shaarvikUrl', 'company', 'plans'));
    }

    public function downloadPdf($id)
    {
        if (!isSuperAdmin() && checkmodulepermission(16, 'can_report') != 1) {
            abort(403, 'Unauthorized access. Only superadmins can download invoices.');
        }

        $companyUid = session()->get('comp_id');
        if (!$companyUid) {
            return back()->with('error', 'Company session not found.');
        }

        $shaarvikUrl = rtrim(env('SHAARVIK_URL', 'https://shaarviktechnologies.com'), '/');

        try {
            $invoices = $this->fetchInvoicesForCompany($shaarvikUrl, $companyUid);
            if (!empty($invoices)) {
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
        if (!isSuperAdmin() && checkmodulepermission(16, 'can_report') != 1) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $amount = (float)$request->input('amount');
        if ($amount <= 0) {
            return response()->json(['error' => 'Invalid amount.'], 400);
        }

        $shaarvikUrl = rtrim(env('SHAARVIK_URL', 'https://shaarviktechnologies.com'), '/');
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
        if (!isSuperAdmin() && checkmodulepermission(16, 'can_report') != 1) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $shaarvikUrl = rtrim(env('SHAARVIK_URL', 'https://shaarviktechnologies.com'), '/');
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

    public function createAddonOrder(Request $request)
    {
        if (!isSuperAdmin() && checkmodulepermission(16, 'can_report') != 1) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $type = $request->input('type'); // 'user' or 'site'
        $quantity = (int)$request->input('quantity', 1);

        if ($quantity <= 0) {
            return response()->json(['error' => 'Invalid quantity.'], 400);
        }

        $unitPrice = ($type === 'user') ? 100.0 : 200.0;
        $amount = $unitPrice * $quantity;

        $shaarvikUrl = rtrim(env('SHAARVIK_URL', 'https://shaarviktechnologies.com'), '/');
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
                Log::error("Failed to create Razorpay addon order from Shaarvik: " . $response->body());
                return response()->json(['error' => 'Failed to create payment order.'], 500);
            }
        } catch (\Exception $e) {
            Log::error("Failed to call Shaarvik create-order API for addon: " . $e->getMessage());
            return response()->json(['error' => 'Failed to connect to billing server.'], 500);
        }
    }

    public function finalizeAddonPayment(Request $request)
    {
        if (!isSuperAdmin() && checkmodulepermission(16, 'can_report') != 1) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $shaarvikUrl = rtrim(env('SHAARVIK_URL', 'https://shaarviktechnologies.com'), '/');
        try {
            // 1. Verify Razorpay Payment Signature via Shaarvik
            $verifyResponse = Http::timeout(10)->post("{$shaarvikUrl}/api/payments/razorpay/verify", [
                'razorpay_order_id' => $request->input('razorpay_order_id'),
                'razorpay_payment_id' => $request->input('razorpay_payment_id'),
                'razorpay_signature' => $request->input('razorpay_signature')
            ]);

            if (!$verifyResponse->successful() || !$verifyResponse->json('success')) {
                Log::error("Razorpay verification failed on Shaarvik for addon: " . $verifyResponse->body());
                return response()->json(['error' => 'Payment signature verification failed.'], 400);
            }

            // 2. Register Invoice/Addon Payment in Shaarvik
            $companyUid = session()->get('comp_id');
            $type = $request->input('type'); // 'user' or 'site'
            $quantity = (int)$request->input('quantity', 1);
            $amount = (float)$request->input('amount');

            // Fetch subscriptionId from Shaarvik invoices/subscriptions API
            $subscriptionId = null;
            try {
                $invoicesResponse = Http::timeout(10)->get("{$shaarvikUrl}/api/mysql/invoices", [
                    'companyUid' => $companyUid
                ]);
                if ($invoicesResponse->successful()) {
                    $invoicesList = $invoicesResponse->json();
                    if (!empty($invoicesList)) {
                        $subscriptionId = $invoicesList[0]['subscription_id'] ?? null;
                    }
                }
            } catch (\Exception $e) {
                Log::error("Failed to fetch invoices for subscriptionId lookup: " . $e->getMessage());
            }

            if (!$subscriptionId) {
                Log::error("Could not find subscriptionId for companyUid: {$companyUid}");
                return response()->json(['error' => 'Could not retrieve active subscription. Please contact support.'], 400);
            }

            // Add addon in Shaarvik database and generate an invoice for it
            $renewResponse = Http::timeout(10)->post("{$shaarvikUrl}/api/mysql/subscriptions/addon", [
                'subscriptionId' => $subscriptionId,
                'action' => 'add',
                'amount' => $amount,
                'notes' => "Addon: {$quantity} Extra " . ($type === 'user' ? 'Users' : 'Sites') . " Purchased",
                'transactionId' => $request->input('razorpay_payment_id'),
                'gateway' => 'razorpay'
            ]);

            if (!$renewResponse->successful()) {
                Log::error("Failed to register addon subscription renewal in Shaarvik: " . $renewResponse->body());
                return response()->json(['error' => 'Failed to log payment on billing server.'], 500);
            }

            // 3. Update companies table in Buildarya master database
            $compDbId = session()->get('comp_db_id');
            if ($compDbId) {
                $company = DB::table('companies')->where('id', $compDbId)->first();
                if ($company) {
                    $newExpiry = date('Y-m-d H:i:s', strtotime('+30 days'));
                    if ($type === 'user') {
                        $currentExtra = (int)$company->extra_users;
                        $newExtra = $currentExtra + $quantity;
                        DB::table('companies')->where('id', $compDbId)->update([
                            'extra_users' => $newExtra,
                            'extra_users_expired' => $newExpiry
                        ]);
                    } else {
                        $currentExtra = (int)$company->extra_sites;
                        $newExtra = $currentExtra + $quantity;
                        DB::table('companies')->where('id', $compDbId)->update([
                            'extra_sites' => $newExtra,
                            'extra_sites_expired' => $newExpiry
                        ]);
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Addon purchased successfully.'
            ]);

        } catch (\Exception $e) {
            Log::error("Exception in finalizeAddonPayment: " . $e->getMessage());
            return response()->json(['error' => 'Billing server connection failed during payment finalization.'], 500);
        }
    }

    public function removeAddon(Request $request)
    {
        if (!isSuperAdmin() && checkmodulepermission(16, 'can_report') != 1) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $type = $request->input('type');
        
        $compDbId = session()->get('comp_db_id');
        if (!$compDbId) {
            return response()->json(['error' => 'Company not found.'], 400);
        }

        $company = DB::table('companies')->where('id', $compDbId)->first();
        if (!$company) {
            return response()->json(['error' => 'Company not found in DB.'], 400);
        }

        $subscriptionId = null;
        $shaarvikUrl = rtrim(env('SHAARVIK_URL', 'https://shaarviktechnologies.com'), '/');
        try {
            $companyUid = session()->get('comp_id');
            $invoicesResponse = Http::timeout(10)->get("{$shaarvikUrl}/api/mysql/invoices", [
                'companyUid' => $companyUid
            ]);
            if ($invoicesResponse->successful()) {
                $invoicesList = $invoicesResponse->json();
                if (!empty($invoicesList)) {
                    $subscriptionId = $invoicesList[0]['subscription_id'] ?? null;
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to fetch invoices for subscriptionId lookup: " . $e->getMessage());
        }

        $success = false;
        $amountToDecrease = 0;

        if ($type === 'user') {
            $currentExtra = (int)$company->extra_users;
            if ($currentExtra > 0) {
                DB::table('companies')->where('id', $compDbId)->update(['extra_users' => $currentExtra - 1]);
                $success = true;
                $amountToDecrease = 100;
            }
        } elseif ($type === 'site') {
            $currentExtra = (int)$company->extra_sites;
            if ($currentExtra > 0) {
                DB::table('companies')->where('id', $compDbId)->update(['extra_sites' => $currentExtra - 1]);
                $success = true;
                $amountToDecrease = 200;
            }
        }

        if ($success) {
            if ($subscriptionId) {
                try {
                    Http::timeout(10)->post("{$shaarvikUrl}/api/mysql/subscriptions/addon", [
                        'subscriptionId' => $subscriptionId,
                        'action' => 'remove',
                        'amount' => $amountToDecrease,
                        'notes' => "Addon: 1 Extra " . ($type === 'user' ? 'User' : 'Site') . " Removed",
                    ]);
                } catch (\Exception $e) {
                    Log::error("Failed to update subscription in Shaarvik: " . $e->getMessage());
                }
            }
            return response()->json(['success' => true, 'message' => 'Extra ' . ($type === 'user' ? 'user' : 'site') . ' removed.']);
        }

        return response()->json(['error' => 'No extra addons to remove.'], 400);
    }
}
