<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class FrontendController extends Controller
{
    public function index()
    {
        return view('frontend.index');
    }

    public function features()
    {
        return view('frontend.features');
    }

    public function modules()
    {
        return view('frontend.modules');
    }

    public function pricing()
    {
        $shaarvikUrl = rtrim(env('SHAARVIK_URL', 'https://shaarviktechnologies.com'), '/');
        $plans = [];
        try {
            $response = Http::timeout(5)->get("{$shaarvikUrl}/api/public/pricing-plans");
            if ($response->successful()) {
                $plans = $response->json();
            } else {
                Log::warning("Failed to fetch pricing plans from Shaarvik. Status: " . $response->status());
            }
        } catch (\Exception $e) {
            Log::error("Error fetching pricing plans: " . $e->getMessage());
        }

        // If plans couldn't be fetched, define fallback static plans
        if (empty($plans)) {
            $plans = [
                [
                    'id' => 'starter',
                    'name' => 'starter',
                    'price' => 750,
                    'strikethroughPrice' => 1499,
                    'isCustom' => false,
                    'maxUsers' => 10,
                    'maxSites' => 10,
                    'billingCycle' => 'monthly',
                    'description' => '',
                    'moduleNames' => ['Site Bills', 'Cost Category', 'Attendance Management', 'Task Management', 'Expense', 'Contacts', 'Documents', 'Site & User Management', 'Materials']
                ],
                [
                    'id' => 'growth',
                    'name' => 'Growth',
                    'price' => 1499,
                    'strikethroughPrice' => 2999,
                    'isCustom' => false,
                    'maxUsers' => 25,
                    'maxSites' => 15,
                    'billingCycle' => 'monthly',
                    'description' => '',
                    'moduleNames' => ['Site & User Management', 'Expense', 'Materials', 'Attendance Management', 'Task Management', 'Cost Category', 'Documents', 'Contacts', 'System Management', 'Site Bills', 'Payment Vouchers', 'Sales']
                ]
            ];
        }

        return view('frontend.pricing', compact('plans', 'shaarvikUrl'));
    }

    public function pricingNextUid(Request $request)
    {
        $name = $request->query('name', '');
        $shaarvikUrl = rtrim(env('SHAARVIK_URL', 'https://shaarviktechnologies.com'), '/');
        
        try {
            $response = Http::timeout(5)->get("{$shaarvikUrl}/api/leads/next-uid", [
                'name' => $name
            ]);
            return response()->json($response->json(), $response->status());
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function savePricingLead(Request $request)
    {
        $shaarvikUrl = rtrim(env('SHAARVIK_URL', 'https://shaarviktechnologies.com'), '/');
        
        try {
            $response = Http::timeout(10)->post("{$shaarvikUrl}/api/public/pricing-lead", $request->all());
            return response()->json($response->json(), $response->status());
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function createPricingRazorpayOrder(Request $request)
    {
        $shaarvikUrl = rtrim(env('SHAARVIK_URL', 'https://shaarviktechnologies.com'), '/');
        
        try {
            $response = Http::timeout(10)->post("{$shaarvikUrl}/api/payments/razorpay/create-order", $request->all());
            return response()->json($response->json(), $response->status());
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function convertPricingLead(Request $request)
    {
        $shaarvikUrl = rtrim(env('SHAARVIK_URL', 'https://shaarviktechnologies.com'), '/');
        
        try {
            $response = Http::timeout(10)->post("{$shaarvikUrl}/api/public/pricing-lead/convert", $request->all());
            return response()->json($response->json(), $response->status());
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function contact()
    {
        return view('frontend.contact');
    }

    public function submitContact(Request $request)
    {
        $validated = $request->validate([
            'name'    => 'required|string|max:100',
            'email'   => 'required|email|max:100',
            'company' => 'nullable|string|max:100',
            'phone'   => 'nullable|string|max:20',
            'message' => 'nullable|string|max:2000',
        ]);

        // Hit Shaarvik external leads API
        $this->pushToShaarvik($validated, 'contact_form');

        // Send internal email notification (non-blocking)
        try {
            $adminEmail = config('mail.from.address', 'hello@buildarya.in');
            Mail::raw(
                "New contact form submission:\n\n" .
                "Name: {$validated['name']}\n" .
                "Email: {$validated['email']}\n" .
                "Company: " . ($validated['company'] ?? 'N/A') . "\n" .
                "Phone: " . ($validated['phone'] ?? 'N/A') . "\n" .
                "Message: " . ($validated['message'] ?? 'N/A'),
                function ($mail) use ($validated, $adminEmail) {
                    $mail->to($adminEmail)
                         ->subject('New Contact Form Submission — ' . $validated['name']);
                }
            );
        } catch (\Exception $e) {
            Log::error('Contact form email failed: ' . $e->getMessage());
        }

        return redirect()->route('contact')->with('success', "Thank you, {$validated['name']}! We'll get back to you within 24 hours.");
    }

    public function submitApkDownload(Request $request)
    {
        $isAjax = $request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest';

        $validated = $request->validate([
            'name'    => 'required|string|max:100',
            'email'   => 'required|email|max:100',
            'company' => 'nullable|string|max:100',
            'phone'   => 'nullable|string|max:20',
            'message' => 'nullable|string|max:2000',
        ]);

        // Hit Shaarvik external leads API
        $this->pushToShaarvik($validated, 'apk_download');

        // Trigger APK download
        $apkPath = public_path('uploads/apk/buildarya_latest.apk');
        if (!file_exists($apkPath)) {
            if ($isAjax) {
                return response()->json([
                    'success' => false,
                    'message' => 'APK file is not available yet. Please try again later.',
                ], 404);
            }
            return redirect()->back()->with('apk_error', 'APK file is not available yet. Please try again later.');
        }

        return response()->download($apkPath, 'buildarya_latest.apk');
    }

    /**
     * Push lead data to the Shaarvik external API.
     */
    private function pushToShaarvik(array $data, string $source = 'buildarya_form'): void
    {
        $baseUrl = rtrim(config('app.shaarvik_url', env('SHAARVIK_URL', '')), '/');

        if (empty($baseUrl)) {
            Log::warning('SHAARVIK_URL is not configured in .env');
            return;
        }

        try {
            $response = Http::timeout(10)->post($baseUrl . '/api/public/leads', [
                'name'    => $data['name'],
                'email'   => $data['email'],
                'company' => $data['company'] ?? null,
                'phone'   => $data['phone'] ?? null,
                'notes'   => $data['message'] ?? null,
                'source'  => $source,
            ]);

            if (!$response->successful()) {
                Log::warning('Shaarvik API returned non-success: ' . $response->status() . ' ' . $response->body());
            }
        } catch (\Exception $e) {
            Log::error('Shaarvik API call failed: ' . $e->getMessage());
        }
    }

    public function privacy()
    {
        return view('frontend.privacy');
    }

    public function terms()
    {
        return view('frontend.terms');
    }
}
