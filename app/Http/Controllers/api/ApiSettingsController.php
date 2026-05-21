<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class ApiSettingsController extends Controller
{
    /**
     * Check if the user has a specific permission for the Settings module (Module 9)
     */
    private function checkSettingsPermission(Request $request, $permission = 'can_view')
    {
        if (isSuperAdmin()) {
            return true;
        }

        $user = $request->user();
        if (!$user) {
            return false;
        }

        $conn = config('database.default');

        try {
            $perm = DB::connection($conn)->table('user_permission')
                ->where('user_id', $user->id)
                ->where('module_id', 9)
                ->first();

            if ($perm && $perm->{$permission} == 1) {
                return true;
            }
        } catch (\Exception $e) {
            // Fallback in case table does not exist or has schema difference
        }

        return false;
    }

    /**
     * Helper to upsert a settings record in the tenant DB
     */
    private function upsertSetting($conn, $name, $value, $uid)
    {
        DB::connection($conn)->table('settings')->upsert([
            [
                'name' => $name,
                'value' => (string)$value,
                'uid' => $uid,
                'updated_at' => Carbon::now()->toDateTimeString()
            ]
        ], ['name'], ['value', 'uid', 'updated_at']);
    }

    /**
     * List all settings
     */
    public function index(Request $request)
    {
        if (!$this->checkSettingsPermission($request, 'can_view')) {
            return response()->json(['status' => 'Error', 'message' => 'Unauthorized access to settings.'], 403);
        }

        try {
            $conn = config('database.default');
            $settings = DB::connection($conn)->table('settings')->get();

            // Build a convenient flat keyed map for the mobile client
            $settingsMap = [];
            foreach ($settings as $setting) {
                $settingsMap[$setting->name] = $setting->value;
            }

            return response()->json([
                'status' => 'Ok',
                'data' => $settings,
                'map' => $settingsMap,
                'server_time' => Carbon::now()->toDateTimeString()
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Update Theme setting
     */
    public function updateTheme(Request $request)
    {
        if (!$this->checkSettingsPermission($request, 'can_edit')) {
            return response()->json(['status' => 'Error', 'message' => 'Unauthorized to modify settings.'], 403);
        }

        $theme = $request->input('theme') ?? $request->input('color');

        if (!$theme) {
            return response()->json(['status' => 'Error', 'message' => 'Theme color value is required.'], 400);
        }

        try {
            $conn = config('database.default');
            $user = $request->user();

            $this->upsertSetting($conn, 'theme', $theme, $user->id);
            addActivity(0, 'settings', "System Theme Changed", 9, $user->id, $conn);

            return response()->json([
                'status' => 'Ok',
                'message' => 'Theme updated successfully.',
                'theme' => $theme
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Update Menu Theme setting
     */
    public function updateMenuTheme(Request $request)
    {
        if (!$this->checkSettingsPermission($request, 'can_edit')) {
            return response()->json(['status' => 'Error', 'message' => 'Unauthorized to modify settings.'], 403);
        }

        $menuTheme = $request->input('themecolor') ?? $request->input('menutheme');

        if (!$menuTheme) {
            return response()->json(['status' => 'Error', 'message' => 'Menu theme color value is required.'], 400);
        }

        try {
            $conn = config('database.default');
            $user = $request->user();

            $this->upsertSetting($conn, 'menutheme', $menuTheme, $user->id);
            addActivity(0, 'settings', "System Menu Theme Changed", 9, $user->id, $conn);

            return response()->json([
                'status' => 'Ok',
                'message' => 'Menu theme updated successfully.',
                'menutheme' => $menuTheme
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Update Custom Theme Colors setting
     */
    public function updateColors(Request $request)
    {
        if (!$this->checkSettingsPermission($request, 'can_edit')) {
            return response()->json(['status' => 'Error', 'message' => 'Unauthorized to modify settings.'], 403);
        }

        $input = $request->only(['primary_color', 'secondry_color', 'gradient_start', 'gradient_end']);

        if (empty($input)) {
            return response()->json(['status' => 'Error', 'message' => 'At least one color value is required.'], 400);
        }

        try {
            $conn = config('database.default');
            $user = $request->user();

            foreach ($input as $name => $value) {
                if ($value !== null) {
                    $this->upsertSetting($conn, $name, $value, $user->id);
                }
            }

            addActivity(0, 'settings', "System Theme Colors Changed", 9, $user->id, $conn);

            return response()->json([
                'status' => 'Ok',
                'message' => 'Theme colors updated successfully.',
                'colors' => $input
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Update Bill Sequence setting
     */
    public function updateBillSequence(Request $request)
    {
        if (!$this->checkSettingsPermission($request, 'can_edit')) {
            return response()->json(['status' => 'Error', 'message' => 'Unauthorized to modify settings.'], 403);
        }

        $billSequence = $request->input('bill_sequence');

        if (!$billSequence) {
            return response()->json(['status' => 'Error', 'message' => 'Bill sequence value is required.'], 400);
        }

        try {
            $conn = config('database.default');
            $user = $request->user();

            $this->upsertSetting($conn, 'bill_sequence', $billSequence, $user->id);
            addActivity(0, 'settings', "Site Bills Sequence Changed", 9, $user->id, $conn);

            return response()->json([
                'status' => 'Ok',
                'message' => 'Bill sequence updated successfully.',
                'bill_sequence' => $billSequence
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Update Payment Voucher Sequence setting
     */
    public function updatePaymentVoucherSequence(Request $request)
    {
        if (!$this->checkSettingsPermission($request, 'can_edit')) {
            return response()->json(['status' => 'Error', 'message' => 'Unauthorized to modify settings.'], 403);
        }

        $pvSequence = $request->input('payment_voucher_sequence');

        if (!$pvSequence) {
            return response()->json(['status' => 'Error', 'message' => 'Payment voucher sequence value is required.'], 400);
        }

        try {
            $conn = config('database.default');
            $user = $request->user();

            $this->upsertSetting($conn, 'payment_voucher_sequence', $pvSequence, $user->id);
            addActivity(0, 'settings', "Payment Voucher Sequence Changed", 9, $user->id, $conn);

            return response()->json([
                'status' => 'Ok',
                'message' => 'Payment voucher sequence updated successfully.',
                'payment_voucher_sequence' => $pvSequence
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Update Currency setting
     */
    public function updateCurrency(Request $request)
    {
        if (!$this->checkSettingsPermission($request, 'can_edit')) {
            return response()->json(['status' => 'Error', 'message' => 'Unauthorized to modify settings.'], 403);
        }

        $currency = $request->input('currency') ?? $request->input('currency_name');

        if (!$currency) {
            return response()->json(['status' => 'Error', 'message' => 'Currency value is required.'], 400);
        }

        try {
            $conn = config('database.default');
            $user = $request->user();

            $this->upsertSetting($conn, 'currency', $currency, $user->id);
            addActivity(0, 'settings', "System Currency Changed", 9, $user->id, $conn);

            return response()->json([
                'status' => 'Ok',
                'message' => 'System currency updated successfully.',
                'currency' => $currency
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Update Upload Sources settings
     */
    public function updateUploadSources(Request $request)
    {
        if (!$this->checkSettingsPermission($request, 'can_edit')) {
            return response()->json(['status' => 'Error', 'message' => 'Unauthorized to modify settings.'], 403);
        }

        $sources = $request->only([
            'expense_upload_src',
            'material_first_upload_src',
            'material_second_upload_src',
            'machinery_doc_upload_src',
            'machinery_service_upload_src',
            'document_upload_src'
        ]);

        if (empty($sources)) {
            return response()->json(['status' => 'Error', 'message' => 'No upload source values were provided.'], 400);
        }

        try {
            $conn = config('database.default');
            $user = $request->user();

            foreach ($sources as $name => $value) {
                if ($value !== null) {
                    $this->upsertSetting($conn, $name, $value, $user->id);
                }
            }

            addActivity(0, 'settings', "Mobile App Upload Source Updated!", 9, $user->id, $conn);

            return response()->json([
                'status' => 'Ok',
                'message' => 'Mobile upload sources updated successfully.',
                'upload_sources' => $sources
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * General batch update for settings
     */
    public function updateSettingGeneral(Request $request)
    {
        if (!$this->checkSettingsPermission($request, 'can_edit')) {
            return response()->json(['status' => 'Error', 'message' => 'Unauthorized to modify settings.'], 403);
        }

        // Support passing under 'settings' key or just key-values in root of the request payload
        $settingsData = $request->input('settings') ?? $request->all();

        if (empty($settingsData) || !is_array($settingsData)) {
            return response()->json(['status' => 'Error', 'message' => 'No setting key-value pairs provided.'], 400);
        }

        try {
            $conn = config('database.default');
            $user = $request->user();

            $updatedKeys = [];
            foreach ($settingsData as $key => $value) {
                // Ignore general laravel parameters
                if (in_array($key, ['_token', 'settings']) || is_array($value)) {
                    continue;
                }

                $this->upsertSetting($conn, $key, $value, $user->id);
                $updatedKeys[] = $key;
            }

            if (empty($updatedKeys)) {
                return response()->json(['status' => 'Error', 'message' => 'No valid settings attributes found to update.'], 400);
            }

            addActivity(0, 'settings', "Settings Updated in Batch", 9, $user->id, $conn);

            return response()->json([
                'status' => 'Ok',
                'message' => 'Settings updated successfully.',
                'updated_keys' => $updatedKeys
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }
}
