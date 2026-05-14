<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class ApiAssetMachineryController extends Controller
{
    // ==========================================
    // ASSETS MODULE
    // ==========================================

    public function assetSummary(Request $request)
    {
        try {
            $data = [
                'asset_heads' => DB::table('asset_head')->get(),
                'sites' => DB::table('sites')->where('status', 'Active')->get(),
            ];
            return response()->json(['status' => 'Ok', 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function listAssets(Request $request)
    {
        try {
            $user = $request->user();
            $head_id = $request->get('head_id');
            $requested_site = $request->get('site_id');
            $status = $request->get('status', 'Working');
            $search = $request->get('search');
            $export = $request->get('export');

            // Default to 'all' if no site_id is provided
            $site_id = $requested_site ?? 'all';

            $query = DB::table('assets')
                ->leftJoin('sites', 'sites.id', '=', 'assets.site_id')
                ->leftJoin('asset_head', 'asset_head.id', '=', 'assets.head_id')
                ->select('assets.*', 'sites.name as site_name', 'asset_head.name as head_name')
                ->orderBy('assets.id', 'desc');

            if ($head_id) $query->where('assets.head_id', $head_id);
            if ($site_id && $site_id != 'all') $query->where('assets.site_id', $site_id);
            if ($status && $status != 'all') $query->where('assets.status', $status);
            
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('assets.name', 'LIKE', "%{$search}%")
                      ->orWhere('sites.name', 'LIKE', "%{$search}%")
                      ->orWhere('asset_head.name', 'LIKE', "%{$search}%");
                });
            }

            if ($export == 'csv') {
                $results = $query->get();
                $filename = "assets_report_" . time() . ".csv";
                $headers = array(
                    "Content-type" => "text/csv",
                    "Content-Disposition" => "attachment; filename=$filename",
                );

                $callback = function() use ($results) {
                    $file = fopen('php://output', 'w');
                    fputcsv($file, ['ID', 'Name', 'Head', 'Site', 'Cost Price', 'Status', 'Purchase Date']);
                    foreach ($results as $row) {
                        fputcsv($file, [
                            $row->id, 
                            $row->name, 
                            $row->head_name, 
                            $row->site_name, 
                            $row->cost_price, 
                            $row->status,
                            $row->create_datetime ?? 'N/A'
                        ]);
                    }
                    fclose($file);
                };
                return response()->stream($callback, 200, $headers);
            }

            $assets = $query->paginate(20);
            return response()->json(['status' => 'Ok', 'data' => $assets]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function storeAsset(Request $request)
    {
        // Handle JSON body if not automatically parsed
        if (!$request->has('name') && !empty($request->getContent())) {
            $json = json_decode($request->getContent(), true);
            if ($json) $request->request->add($json);
        }

        $request->validate([
            'name' => 'required',
            'head_id' => 'required',
            'site_id' => 'required',
            'cost_price' => 'required|numeric'
        ]);

        try {
            $user = $request->user();
            $conn = config('database.default');
            
            return DB::transaction(function () use ($request, $user, $conn) {
                $data = [
                    'name' => $request->name,
                    'head_id' => $request->head_id,
                    'site_id' => $request->site_id,
                    'cost_price' => $request->cost_price,
                    'status' => 'Working'
                ];
                $id = DB::table('assets')->insertGetId($data);
                addActivity($id, 'assets', "New Asset Purchased - " . $request->name, 5, $user->id, $conn);

                DB::table('asset_transaction')->insert([
                    'asset_id' => $id,
                    'to_site' => $request->site_id,
                    'transaction_type' => 'Purchase',
                    'remark' => 'Asset added manually via API'
                ]);

                return response()->json(['status' => 'Ok', 'message' => 'Asset created successfully', 'id' => $id]);
            });
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function transferAsset(Request $request, $id)
    {
        // Handle JSON body if not automatically parsed
        if (!$request->has('to_site') && !empty($request->getContent())) {
            $json = json_decode($request->getContent(), true);
            if ($json) $request->request->add($json);
        }

        $request->validate([
            'to_site' => 'required',
            'remark' => 'nullable'
        ]);

        try {
            $user = $request->user();
            $conn = config('database.default');
            $asset = DB::table('assets')->where('id', $id)->first();

            if (!$asset) return response()->json(['status' => 'Failed', 'message' => 'Asset not found'], 404);
            if ($asset->site_id == $request->to_site) return response()->json(['status' => 'Failed', 'message' => 'Asset already at this site'], 400);

            return DB::transaction(function () use ($id, $asset, $request, $user, $conn) {
                DB::table('assets')->where('id', $id)->update(['site_id' => $request->to_site]);
                
                DB::table('asset_transaction')->insert([
                    'asset_id' => $id,
                    'from_site' => $asset->site_id,
                    'to_site' => $request->to_site,
                    'transaction_type' => 'Transfer',
                    'remark' => $request->remark
                ]);

                addActivity($id, 'assets', "Asset Transferred to site: " . $request->to_site, 5, $user->id, $conn);
                return response()->json(['status' => 'Ok', 'message' => 'Asset transferred successfully']);
            });
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function assetTransferHistory(Request $request)
    {
        try {
            $asset_id = $request->get('asset_id');
            $search = $request->get('search');
            $export = $request->get('export');

            $query = DB::table('asset_transaction')
                ->leftJoin('assets', 'assets.id', '=', 'asset_transaction.asset_id')
                ->leftJoin('sites as from_sites', 'from_sites.id', '=', 'asset_transaction.from_site')
                ->leftJoin('sites as to_sites', 'to_sites.id', '=', 'asset_transaction.to_site')
                ->select('asset_transaction.*', 'assets.name as asset_name', 'from_sites.name as from_site_name', 'to_sites.name as to_site_name')
                ->orderBy('asset_transaction.id', 'desc');

            if ($asset_id) {
                $query->where('asset_transaction.asset_id', $asset_id);
            }

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('assets.name', 'LIKE', "%{$search}%")
                      ->orWhere('asset_transaction.remark', 'LIKE', "%{$search}%")
                      ->orWhere('asset_transaction.transaction_type', 'LIKE', "%{$search}%");
                });
            }

            if ($export == 'csv') {
                $results = $query->get();
                $filename = "asset_transfer_history_" . time() . ".csv";
                $headers = ["Content-type" => "text/csv", "Content-Disposition" => "attachment; filename=$filename"];
                $callback = function() use ($results) {
                    $file = fopen('php://output', 'w');
                    fputcsv($file, ['ID', 'Asset', 'Type', 'From Site', 'To Site', 'Remark', 'Date']);
                    foreach ($results as $row) {
                        fputcsv($file, [
                            $row->id, 
                            $row->asset_name, 
                            $row->transaction_type, 
                            $row->from_site_name ?? 'N/A', 
                            $row->to_site_name ?? 'N/A', 
                            $row->remark, 
                            $row->create_datetime ?? 'N/A'
                        ]);
                    }
                    fclose($file);
                };
                return response()->stream($callback, 200, $headers);
            }

            $history = $query->paginate(20);
            return response()->json(['status' => 'Ok', 'data' => $history]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function sellAsset(Request $request, $id)
    {
        // Handle JSON body if not automatically parsed
        if (!$request->has('sold_value') && !empty($request->getContent())) {
            $json = json_decode($request->getContent(), true);
            if ($json) $request->request->add($json);
        }

        $request->validate([
            'sold_value' => 'required|numeric',
            'remark' => 'nullable',
            'date' => 'required|date'
        ]);

        try {
            $user = $request->user();
            $conn = config('database.default');
            $asset = DB::table('assets')->where('id', $id)->first();

            if (!$asset) return response()->json(['status' => 'Failed', 'message' => 'Asset not found'], 404);
            if ($asset->status == 'Sold') return response()->json(['status' => 'Failed', 'message' => 'Asset already sold'], 400);

            return DB::transaction(function () use ($id, $asset, $request, $user, $conn) {
                $sold_value = $request->sold_value;
                $remark = $request->remark;
                $date = $request->date;

                // Update Asset
                DB::table('assets')->where('id', $id)->update([
                    'status' => 'Sold',
                    'sale_price' => $sold_value
                ]);

                // Record Transaction
                DB::table('asset_transaction')->insert([
                    'asset_id' => $id,
                    'from_site' => $asset->site_id,
                    'transaction_type' => 'Sold',
                    'remark' => $remark,
                    'create_datetime' => Carbon::now()
                ]);

                // Update Site Balance (Matches addsitesBalance logic)
                $pay_id = DB::table('site_payments')->insertGetId([
                    'site_id' => $asset->site_id,
                    'amount' => $sold_value,
                    'remark' => "Asset Sold - " . ($remark ?? $asset->name),
                    'date' => $date
                ]);

                DB::table('sites_transaction')->insert([
                    'site_id' => $asset->site_id,
                    'type' => 'Credit',
                    'payment_id' => $pay_id,
                    'create_datetime' => Carbon::now()
                ]);

                addActivity($id, 'assets', "Asset Sold For Amount - " . $sold_value, 5, $user->id, $conn);
                addActivity($pay_id, 'site_payments', "Payment Created At Site By Selling Asset.", 1, $user->id, $conn);

                return response()->json(['status' => 'Ok', 'message' => 'Asset sold successfully']);
            });
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function listAssetHeads(Request $request)
    {
        try {
            $search = $request->get('search');
            $export = $request->get('export');

            $query = DB::table('asset_head')
                ->select('asset_head.*')
                ->selectSub(function($q) {
                    $q->from('assets')->whereColumn('assets.head_id', 'asset_head.id')->selectRaw('count(*)');
                }, 'assets_count')
                ->orderBy('asset_head.id', 'desc');

            if ($search) {
                $query->where('asset_head.name', 'LIKE', "%{$search}%");
            }

            if ($export == 'csv') {
                $results = $query->get();
                $filename = "asset_heads_" . time() . ".csv";
                $headers = array(
                    "Content-type" => "text/csv",
                    "Content-Disposition" => "attachment; filename=$filename",
                );

                $callback = function() use ($results) {
                    $file = fopen('php://output', 'w');
                    fputcsv($file, ['ID', 'Name', 'Assets Count']);
                    foreach ($results as $row) {
                        fputcsv($file, [$row->id, $row->name, $row->assets_count]);
                    }
                    fclose($file);
                };
                return response()->stream($callback, 200, $headers);
            }

            $heads = $query->paginate(20);
            return response()->json(['status' => 'Ok', 'data' => $heads]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function getAssetHead($id)
    {
        try {
            $head = DB::table('asset_head')
                ->select('asset_head.*')
                ->selectSub(function($q) {
                    $q->from('assets')->whereColumn('assets.head_id', 'asset_head.id')->selectRaw('count(*)');
                }, 'assets_count')
                ->where('asset_head.id', $id)
                ->first();
                
            if (!$head) return response()->json(['status' => 'Error', 'message' => 'Asset Head not found'], 404);
            return response()->json(['status' => 'Ok', 'data' => $head]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function storeAssetHead(Request $request)
    {
        // Handle JSON body if not automatically parsed
        if (!$request->has('name') && !empty($request->getContent())) {
            $json = json_decode($request->getContent(), true);
            if ($json) $request->request->add($json);
        }

        $request->validate([
            'name' => 'required|unique:asset_head,name'
        ]);

        try {
            $user = $request->user();
            $conn = config('database.default');
            $id = DB::table('asset_head')->insertGetId(['name' => $request->name]);
            addActivity($id, 'asset_head', "New Asset Head Created via API: " . $request->name, 5, $user->id, $conn);
            return response()->json(['status' => 'Ok', 'message' => 'Asset head created successfully', 'id' => $id]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function updateAssetHead(Request $request, $id)
    {
        // Handle JSON body if not automatically parsed
        if (!$request->has('name') && !empty($request->getContent())) {
            $json = json_decode($request->getContent(), true);
            if ($json) $request->request->add($json);
        }

        try {
            $user = $request->user();
            $conn = config('database.default');
            
            $name = $request->input('name');
            if (!$name) return response()->json(['status' => 'Error', 'message' => 'Name is required'], 400);

            DB::table('asset_head')->where('id', $id)->update(['name' => $name]);
            addActivity($id, 'asset_head', "Asset Head Updated via API", 5, $user->id, $conn);
            return response()->json(['status' => 'Ok', 'message' => 'Asset Head updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function deleteAssetHead(Request $request, $id)
    {
        try {
            $user = $request->user();
            $conn = config('database.default');
            
            $check = DB::table('assets')->where('head_id', $id)->count();
            if ($check > 0) {
                return response()->json(['status' => 'Error', 'message' => 'Asset Head is in use and cannot be deleted'], 400);
            }

            $head = DB::table('asset_head')->where('id', $id)->first();
            if (!$head) return response()->json(['status' => 'Error', 'message' => 'Asset Head not found'], 404);

            DB::table('asset_head')->where('id', $id)->delete();
            addActivity(0, 'asset_head', "Asset Head Deleted via API - " . $head->name, 5, $user->id, $conn);
            return response()->json(['status' => 'Ok', 'message' => 'Asset Head deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    // ==========================================
    // MACHINERY MODULE
    // ==========================================

    public function machinerySummary(Request $request)
    {
        try {
            $data = [
                'machinery_heads' => DB::table('machinery_head')->get(),
                'sites' => DB::table('sites')->where('status', 'Active')->get(),
            ];
            return response()->json(['status' => 'Ok', 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function listMachineryHeads(Request $request)
    {
        try {
            $search = $request->get('search');
            $export = $request->get('export');

            $query = DB::table('machinery_head')
                ->select('machinery_head.*')
                ->selectSub(function($q) {
                    $q->from('machinery_details')->whereColumn('machinery_details.head_id', 'machinery_head.id')->selectRaw('count(*)');
                }, 'machinery_count')
                ->orderBy('machinery_head.id', 'desc');

            if ($search) {
                $query->where('machinery_head.name', 'LIKE', "%{$search}%");
            }

            if ($export == 'csv') {
                $results = $query->get();
                $filename = "machinery_heads_" . time() . ".csv";
                $headers = array(
                    "Content-type" => "text/csv",
                    "Content-Disposition" => "attachment; filename=$filename",
                );

                $callback = function() use ($results) {
                    $file = fopen('php://output', 'w');
                    fputcsv($file, ['ID', 'Name', 'Machinery Count']);
                    foreach ($results as $row) {
                        fputcsv($file, [$row->id, $row->name, $row->machinery_count]);
                    }
                    fclose($file);
                };
                return response()->stream($callback, 200, $headers);
            }

            $heads = $query->paginate(20);
            return response()->json(['status' => 'Ok', 'data' => $heads]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function storeMachineryHead(Request $request)
    {
        if (!$request->has('name') && !empty($request->getContent())) {
            $json = json_decode($request->getContent(), true);
            if ($json) {
                $request->request->add($json); // Add to ParameterBag
            }
        }

        $request->validate([
            'name' => 'required|unique:machinery_head,name'
        ]);

        try {
            $user = $request->user();
            $conn = config('database.default');
            $id = DB::table('machinery_head')->insertGetId(['name' => $request->name]);
            addActivity($id, 'machinery_head', "New Machinery Head Created via API: " . $request->name, 6, $user->id, $conn);
            return response()->json(['status' => 'Ok', 'message' => 'Machinery head created successfully', 'id' => $id]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function listMachinery(Request $request)
    {
        try {
            $user = $request->user();
            $head_id = $request->get('head_id');
            $requested_site = $request->get('site_id');
            $search = $request->get('search');
            $machinery_id = $request->get('id');
            $export = $request->get('export');

            // Default to 'all' if no site_id is provided, unless user explicitly wants their site
            $site_id = $requested_site ?? 'all';

            $query = DB::table('machinery_details')
                ->leftJoin('sites', 'sites.id', '=', 'machinery_details.site_id')
                ->leftJoin('machinery_head', 'machinery_head.id', '=', 'machinery_details.head_id')
                ->select('machinery_details.*', 'sites.name as site_name', 'machinery_head.name as head_name')
                ->orderBy('machinery_details.id', 'desc');

            if ($machinery_id) $query->where('machinery_details.id', $machinery_id);
            if ($head_id) $query->where('machinery_details.head_id', $head_id);
            if ($site_id && $site_id != 'all') $query->where('machinery_details.site_id', $site_id);
            
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('machinery_details.name', 'LIKE', "%{$search}%")
                      ->orWhere('machinery_head.name', 'LIKE', "%{$search}%");
                });
            }

            if ($export == 'csv') {
                $results = $query->get();
                $filename = "machinery_report_" . time() . ".csv";
                $headers = array(
                    "Content-type" => "text/csv",
                    "Content-Disposition" => "attachment; filename=$filename",
                    "Pragma" => "no-cache",
                    "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
                    "Expires" => "0"
                );

                $columns = array('ID', 'Name', 'Head', 'Site', 'Cost Price', 'Status', 'Sale Price', 'Next Service', 'Last Transfer', 'Purchase Date');

                $callback = function() use ($results, $columns) {
                    $file = fopen('php://output', 'w');
                    fputcsv($file, $columns);
                    foreach ($results as $row) {
                        $next_service = DB::table('machinery_services')->where('machinery_id', $row->id)->orderBy('id', 'desc')->value('next_service_on') ?? '';
                        $last_transfer = DB::table('machinery_transaction')->where('machinery_id', $row->id)->orderBy('id', 'desc')->value('create_datetime') ?? $row->create_datetime;

                        fputcsv($file, array(
                            $row->id,
                            $row->name,
                            $row->head_name,
                            $row->site_name,
                            $row->cost_price,
                            $row->status,
                            $row->sale_price ?? 'N/A',
                            $next_service,
                            $last_transfer,
                            $row->create_datetime
                        ));
                    }
                    fclose($file);
                };
                return response()->stream($callback, 200, $headers);
            }

            $machinery = $query->paginate(20);

            // Add calculated fields for JSON response
            $machinery->getCollection()->transform(function($item) {
                $item->next_service_on = DB::table('machinery_services')
                    ->where('machinery_id', $item->id)
                    ->orderBy('id', 'desc')
                    ->value('next_service_on') ?? '';
                
                $item->last_transfer_date = DB::table('machinery_transaction')
                    ->where('machinery_id', $item->id)
                    ->orderBy('id', 'desc')
                    ->value('create_datetime') ?? $item->create_datetime;
                
                $item->purchase_date = $item->create_datetime;
                
                return $item;
            });

            return response()->json(['status' => 'Ok', 'data' => $machinery]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function exportMachineryCsv(Request $request)
    {
        try {
            $user = $request->user();
            $head_id = $request->get('head_id');
            $site_id = $request->get('site_id', $user->site_id);
            $search = $request->get('search');
            $machinery_id = $request->get('id');

            $query = DB::table('machinery_details')
                ->leftJoin('sites', 'sites.id', '=', 'machinery_details.site_id')
                ->leftJoin('machinery_head', 'machinery_head.id', '=', 'machinery_details.head_id')
                ->select('machinery_details.*', 'sites.name as site_name', 'machinery_head.name as head_name')
                ->orderBy('machinery_details.id', 'desc');

            if ($machinery_id) $query->where('machinery_details.id', $machinery_id);
            if ($head_id) $query->where('machinery_details.head_id', $head_id);
            if ($site_id && $site_id != 'all') $query->where('machinery_details.site_id', $site_id);
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('machinery_details.name', 'LIKE', "%{$search}%")
                      ->orWhere('machinery_head.name', 'LIKE', "%{$search}%");
                });
            }

            $results = $query->get();

            $filename = "machinery_report_" . time() . ".csv";
            $headers = array(
                "Content-type" => "text/csv",
                "Content-Disposition" => "attachment; filename=$filename",
                "Pragma" => "no-cache",
                "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
                "Expires" => "0"
            );

            $columns = array('ID', 'Name', 'Head', 'Site', 'Cost Price', 'Status', 'Sale Price');

            $callback = function() use ($results, $columns) {
                $file = fopen('php://output', 'w');
                fputcsv($file, $columns);

                foreach ($results as $row) {
                    fputcsv($file, array(
                        $row->id,
                        $row->name,
                        $row->head_name,
                        $row->site_name,
                        $row->cost_price,
                        $row->status,
                        $row->sale_price ?? 'N/A'
                    ));
                }
                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function machineryReport(Request $request)
    {
        try {
            $report_code = $request->get('type'); // 1-12
            $start_date = $request->get('start_date');
            $end_date = $request->get('end_date');
            $site_id = $request->get('site_id');
            $head_id = $request->get('head_id');
            $search = $request->get('search');
            $export = $request->get('export');

            $query = null;
            $csv_headers = [];

            if (in_array($report_code, [1, 2, 3])) {
                // Purchase Reports
                $query = DB::table('machinery_details')
                    ->leftjoin('sites as ws', 'ws.id', '=', 'machinery_details.site_id')
                    ->leftjoin('expenses', 'expenses.id', '=', 'machinery_details.expense_id')
                    ->leftjoin('sites as ps', 'ps.id', '=', 'expenses.site_id')
                    ->leftjoin('users as u', 'u.id', '=', 'expenses.user_id')
                    ->leftJoin('machinery_head', 'machinery_head.id', '=', 'machinery_details.head_id')
                    ->leftJoin('bills_party', function ($join) {
                        $join->on('expenses.party_id', '=', 'bills_party.id')
                             ->where('expenses.party_type', '=', 'bill');
                    })
                    ->leftJoin('expense_party', function ($join) {
                        $join->on('expenses.party_id', '=', 'expense_party.id')
                             ->where('expenses.party_type', '=', 'expense');
                    })
                    ->selectRaw('machinery_details.*, ws.name as working_site, ps.name as purchase_site, machinery_head.name as head_name, expenses.date as purchase_date, u.name as user_name, CASE WHEN expenses.party_type = "bill" THEN bills_party.name WHEN expenses.party_type = "expense" THEN expense_party.name END AS supplier_name');

                if ($report_code == 1 && $head_id) $query->where('machinery_details.head_id', $head_id);
                if ($report_code == 2 && $site_id) $query->where('expenses.site_id', $site_id);
                if ($start_date && $end_date) $query->whereBetween('expenses.date', [$start_date, $end_date]);
                
                $csv_headers = ['ID', 'Name', 'Head', 'Working Site', 'Purchase Site', 'Supplier', 'Purchase Date', 'Cost Price'];
                $csv_callback = function($file, $data) {
                    fputcsv($file, [$data->id, $data->name, $data->head_name, $data->working_site, $data->purchase_site, $data->supplier_name, $data->purchase_date, $data->cost_price]);
                };

            } elseif (in_array($report_code, [4, 5, 6])) {
                // Sale Reports
                $query = DB::table('machinery_details')
                    ->leftJoin('machinery_head', 'machinery_head.id', 'machinery_details.head_id')
                    ->leftjoin('sites as ss', 'ss.id', '=', 'machinery_details.site_id')
                    ->leftjoin('machinery_transaction as mt', 'mt.machinery_id', '=', 'machinery_details.id')
                    ->selectRaw('machinery_details.*, ss.name as site_name, machinery_head.name as head_name, mt.create_datetime as sale_date')
                    ->where('mt.transaction_type', 'Sold');

                if ($report_code == 4 && $head_id) $query->where('machinery_details.head_id', $head_id);
                if ($report_code == 5 && $site_id) $query->where('machinery_details.site_id', $site_id);
                if ($start_date && $end_date) $query->whereBetween('mt.create_datetime', [$start_date . ' 00:00:00', $end_date . ' 23:59:59']);

                $csv_headers = ['ID', 'Name', 'Head', 'Sale Site', 'Sale Date', 'Cost Price', 'Sale Price'];
                $csv_callback = function($file, $data) {
                    fputcsv($file, [$data->id, $data->name, $data->head_name, $data->site_name, $data->sale_date, $data->cost_price, $data->sale_price]);
                };

            } elseif (in_array($report_code, [7, 8])) {
                // Transfer Reports
                $query = DB::table('machinery_transaction')
                    ->leftJoin('machinery_details', 'machinery_details.id', '=', 'machinery_transaction.machinery_id')
                    ->leftJoin('machinery_head', 'machinery_head.id', '=', 'machinery_details.head_id')
                    ->leftJoin('sites as fs', 'fs.id', '=', 'machinery_transaction.from_site')
                    ->leftJoin('sites as ts', 'ts.id', '=', 'machinery_transaction.to_site')
                    ->selectRaw('machinery_transaction.*, machinery_details.name as machinery_name, machinery_head.name as head_name, fs.name as from_site_name, ts.name as to_site_name')
                    ->where('machinery_transaction.transaction_type', 'Transfer');

                if ($report_code == 7 && $head_id) $query->where('machinery_details.head_id', $head_id);
                if ($start_date && $end_date) $query->whereBetween('machinery_transaction.create_datetime', [$start_date . ' 00:00:00', $end_date . ' 23:59:59']);

                $csv_headers = ['Transaction ID', 'Machinery', 'Head', 'From Site', 'To Site', 'Transfer Date', 'Remark'];
                $csv_callback = function($file, $data) {
                    fputcsv($file, [$data->id, $data->machinery_name, $data->head_name, $data->from_site_name, $data->to_site_name, $data->create_datetime, $data->remark]);
                };

            } elseif (in_array($report_code, [11, 12])) {
                // Service Reports
                $query = DB::table('machinery_service')
                    ->leftJoin('machinery_details', 'machinery_details.id', '=', 'machinery_service.machinery_id')
                    ->leftJoin('machinery_head', 'machinery_head.id', '=', 'machinery_details.head_id')
                    ->leftJoin('sites', 'sites.id', '=', 'machinery_service.site_id')
                    ->selectRaw('machinery_service.*, machinery_details.name as machinery_name, machinery_head.name as head_name, sites.name as site_name');

                if ($report_code == 11 && $head_id) $query->where('machinery_details.head_id', $head_id);
                if ($start_date && $end_date) $query->whereBetween('machinery_service.date', [$start_date, $end_date]);

                $csv_headers = ['ID', 'Machinery', 'Head', 'Site', 'Service Date', 'Nature of Work', 'Remark'];
                $csv_callback = function($file, $data) {
                    fputcsv($file, [$data->id, $data->machinery_name, $data->head_name, $data->site_name, $data->date, $data->nature_of_work, $data->remark]);
                };

            } else {
                // Default Machinery List
                $query = DB::table('machinery_details')
                    ->leftJoin('machinery_head', 'machinery_head.id', '=', 'machinery_details.head_id')
                    ->leftJoin('sites', 'sites.id', '=', 'machinery_details.site_id')
                    ->select('machinery_details.*', 'sites.name as site_name', 'machinery_head.name as head_name');
                
                $csv_headers = ['ID', 'Name', 'Head', 'Current Site', 'Status', 'Cost Price'];
                $csv_callback = function($file, $data) {
                    fputcsv($file, [$data->id, $data->name, $data->head_name, $data->site_name, $data->status, $data->cost_price]);
                };
            }

            // Search Filter
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('machinery_details.name', 'LIKE', "%$search%");
                    if (Schema::hasColumn('machinery_head', 'name')) {
                        $q->orWhere('machinery_head.name', 'LIKE', "%$search%");
                    }
                });
            }

            $query->orderBy('id', 'desc');

            $results = $query->get();

            // Fetch filter options for the user
            $filter_options = [
                'sites' => DB::table('sites')->select('id', 'name')->where('status', 'Active')->get(),
                'machinery_heads' => DB::table('machinery_head')->select('id', 'name')->get(),
                'report_types' => [
                    ['id' => 1, 'name' => 'Purchase Report by Head', 'requires' => 'head_id'],
                    ['id' => 2, 'name' => 'Purchase Report by Site', 'requires' => 'site_id'],
                    ['id' => 3, 'name' => 'Complete Purchase Report', 'requires' => 'none'],
                    ['id' => 4, 'name' => 'Sale Report by Head', 'requires' => 'head_id'],
                    ['id' => 5, 'name' => 'Sale Report by Site', 'requires' => 'site_id'],
                    ['id' => 6, 'name' => 'Complete Sale Report', 'requires' => 'none'],
                    ['id' => 7, 'name' => 'Transfer Report by Head', 'requires' => 'head_id'],
                    ['id' => 8, 'name' => 'Complete Transfer Report', 'requires' => 'none'],
                    ['id' => 11, 'name' => 'Service Report by Head', 'requires' => 'head_id'],
                    ['id' => 12, 'name' => 'Complete Service Report', 'requires' => 'none'],
                ]
            ];

            if ($export == 'csv') {
                $filename = "machinery_report_" . time() . ".csv";
                $headers = ["Content-type" => "text/csv", "Content-Disposition" => "attachment; filename=$filename"];
                $callback = function() use ($results, $csv_headers, $csv_callback) {
                    $file = fopen('php://output', 'w');
                    fputcsv($file, $csv_headers);
                    foreach ($results as $row) {
                        $csv_callback($file, $row);
                    }
                    fclose($file);
                };
                return response()->stream($callback, 200, $headers);
            }

            return response()->json([
                'status' => 'Ok', 
                'data' => $results,
                'filters' => $filter_options
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function getMachineryHead($id)
    {
        try {
            $head = DB::table('machinery_head')
                ->select('machinery_head.*')
                ->selectSub(function($q) {
                    $q->from('machinery_details')->whereColumn('machinery_details.head_id', 'machinery_head.id')->selectRaw('count(*)');
                }, 'machinery_count')
                ->where('machinery_head.id', $id)
                ->first();

            if (!$head) return response()->json(['status' => 'Error', 'message' => 'Machinery Head not found'], 404);
            return response()->json(['status' => 'Ok', 'data' => $head]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function updateMachineryHead(Request $request, $id)
    {
        try {
            $user = $request->user();
            $conn = config('database.default');
            
            $name = $request->input('name');
            if (!$name) {
                 $json = json_decode($request->getContent(), true);
                 $name = $json['name'] ?? null;
            }

            if (!$name) return response()->json(['status' => 'Error', 'message' => 'Name is required'], 400);

            DB::table('machinery_head')->where('id', $id)->update(['name' => $name]);
            addActivity($id, 'machinery_head', "Machinery Head Updated via API", 6, $user->id, $conn);
            return response()->json(['status' => 'Ok', 'message' => 'Machinery Head updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function deleteMachineryHead(Request $request, $id)
    {
        try {
            $user = $request->user();
            $conn = config('database.default');
            
            $check = DB::table('machinery_details')->where('head_id', $id)->count();
            if ($check > 0) {
                return response()->json(['status' => 'Error', 'message' => 'Machinery Head is in use and cannot be deleted'], 400);
            }

            $head = DB::table('machinery_head')->where('id', $id)->first();
            if (!$head) return response()->json(['status' => 'Error', 'message' => 'Machinery Head not found'], 404);

            DB::table('machinery_head')->where('id', $id)->delete();
            addActivity(0, 'machinery_head', "Machinery Head Deleted via API - " . $head->name, 6, $user->id, $conn);
            return response()->json(['status' => 'Ok', 'message' => 'Machinery Head deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function storeMachinery(Request $request)
    {
        if (!$request->has('name') && !empty($request->getContent())) {
            $json = json_decode($request->getContent(), true);
            if ($json) {
                $request->request->add($json);
            }
        }

        $request->validate([
            'name' => 'required',
            'head_id' => 'required',
            'site_id' => 'required',
            'cost_price' => 'required|numeric'
        ]);

        try {
            $user = $request->user();
            $conn = config('database.default');

            return DB::transaction(function () use ($request, $user, $conn) {
                $data = [
                    'name' => $request->name,
                    'head_id' => $request->head_id,
                    'site_id' => $request->site_id,
                    'cost_price' => $request->cost_price,
                    'status' => 'Working'
                ];
                $id = DB::table('machinery_details')->insertGetId($data);
                addActivity($id, 'machinery_details', "New Machinery Purchased - " . $request->name, 6, $user->id, $conn);

                DB::table('machinery_transaction')->insert([
                    'machinery_id' => $id,
                    'to_site' => $request->site_id,
                    'transaction_type' => 'Purchase',
                    'remark' => 'Machinery added manually via API'
                ]);

                return response()->json(['status' => 'Ok', 'message' => 'Machinery created successfully', 'id' => $id]);
            });
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    // ==========================================
    // MACHINERY DOCUMENTS & SERVICES
    // ==========================================

    public function getMachinery($id)
    {
        try {
            $machinery = DB::table('machinery_details')
                ->leftJoin('sites', 'sites.id', '=', 'machinery_details.site_id')
                ->leftJoin('machinery_head', 'machinery_head.id', '=', 'machinery_details.head_id')
                ->select('machinery_details.*', 'sites.name as site_name', 'machinery_head.name as head_name')
                ->where('machinery_details.id', $id)
                ->first();

            if (!$machinery) return response()->json(['status' => 'Error', 'message' => 'Machinery not found'], 404);
            return response()->json(['status' => 'Ok', 'data' => $machinery]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function updateMachinery(Request $request, $id)
    {
        try {
            $user = $request->user();
            $conn = config('database.default');
            
            $data = $request->only(['name', 'head_id', 'site_id', 'cost_price', 'status', 'sale_price']);
            
            // Fallback for raw JSON if body not parsed
            if (empty($data) && !empty($request->getContent())) {
                $json = json_decode($request->getContent(), true);
                if ($json) {
                    $data = array_intersect_key($json, array_flip(['name', 'head_id', 'site_id', 'cost_price', 'status', 'sale_price']));
                }
            }

            if (empty($data)) return response()->json(['status' => 'Error', 'message' => 'No data provided'], 400);

            DB::table('machinery_details')->where('id', $id)->update($data);
            addActivity($id, 'machinery_details', "Machinery Updated via API", 6, $user->id, $conn);
            return response()->json(['status' => 'Ok', 'message' => 'Machinery updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function deleteMachinery(Request $request, $id)
    {
        try {
            $user = $request->user();
            $conn = config('database.default');
            
            DB::table('machinery_details')->where('id', $id)->delete();
            addActivity($id, 'machinery_details', "Machinery Deleted via API", 6, $user->id, $conn);
            return response()->json(['status' => 'Ok', 'message' => 'Machinery deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function machineryDocuments(Request $request, $id)
    {
        try {
            $search = $request->get('search');
            $export = $request->get('export');

            $query = DB::table('machinery_documents')->where('machinery_id', $id)->orderBy('id', 'desc');

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('remark', 'LIKE', "%{$search}%");
                });
            }

            if ($export == 'csv') {
                $results = $query->get();
                $filename = "machinery_docs_" . $id . "_" . time() . ".csv";
                $headers = ["Content-type" => "text/csv", "Content-Disposition" => "attachment; filename=$filename"];
                $callback = function() use ($results) {
                    $file = fopen('php://output', 'w');
                    fputcsv($file, ['ID', 'Name', 'Issue Date', 'End Date', 'Remark', 'Attachment']);
                    foreach ($results as $row) {
                        fputcsv($file, [$row->id, $row->name, $row->issue_date, $row->end_date, $row->remark, url($row->attachment)]);
                    }
                    fclose($file);
                };
                return response()->stream($callback, 200, $headers);
            }

            $docs = $query->get();
            return response()->json(['status' => 'Ok', 'data' => $docs]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function storeMachineryDocument(Request $request, $id)
    {
        $request->validate([
            'name' => 'required',
            'issue_date' => 'required|date',
            'end_date' => 'nullable|date',
            'remark' => 'nullable'
        ]);

        $attachment = $request->file('attachment');
        if (!$attachment) {
            // Check for keys that might have trailing spaces or tabs (common in Postman)
            foreach ($_FILES as $key => $fileInfo) {
                if (trim($key) === 'attachment') {
                    $attachment = $request->file($key);
                    break;
                }
            }
        }

        if (!$attachment) {
            return response()->json([
                'status' => 'Error', 
                'message' => 'The attachment field is required and must be a file.',
                'received_keys' => array_keys($request->all()),
                'files_present' => array_keys($_FILES)
            ], 422);
        }

        try {
            $user = $request->user();
            $conn = config('database.default');

            $file = $attachment;
            $imageName = time() . rand(10000, 1000000) . '.' . $file->extension();
            $file->move(public_path('images/app_images/' . $conn . '/machinery_doc'), $imageName);
            $imagePath = "images/app_images/" . $conn . "/machinery_doc/" . $imageName;

            $docId = DB::table('machinery_documents')->insertGetId([
                'machinery_id' => $id,
                'name' => $request->name,
                'issue_date' => $request->issue_date,
                'end_date' => $request->end_date,
                'remark' => $request->remark,
                'attachment' => $imagePath
            ]);

            addActivity($docId, 'machinery_documents', "New Doc Uploaded via API: " . $request->name, 6, $user->id, $conn);
            return response()->json(['status' => 'Ok', 'message' => 'Document uploaded successfully', 'id' => $docId]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function getMachineryDocument($machinery_id, $id)
    {
        try {
            $doc = DB::table('machinery_documents')->where('id', $id)->where('machinery_id', $machinery_id)->first();
            if (!$doc) return response()->json(['status' => 'Error', 'message' => 'Document not found for this machinery'], 404);
            return response()->json(['status' => 'Ok', 'data' => $doc]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function updateMachineryDocument(Request $request, $machinery_id, $id)
    {
        try {
            $user = $request->user();
            $conn = config('database.default');
            $doc = DB::table('machinery_documents')->where('id', $id)->where('machinery_id', $machinery_id)->first();

            if (!$doc) return response()->json(['status' => 'Error', 'message' => 'Document not found for this machinery'], 404);

            $data = $request->only(['name', 'issue_date', 'end_date', 'remark']);
            
            // Clean null values from $data to avoid overwriting with nulls if fields are missing in request
            $data = array_filter($data, function($value) { return !is_null($value); });

            $attachment = $request->file('attachment');
            if (!$attachment) {
                foreach ($_FILES as $key => $fileInfo) {
                    if (trim($key) === 'attachment') {
                        $attachment = $request->file($key);
                        break;
                    }
                }
            }

            if ($attachment) {
                $file = $attachment;
                $imageName = time() . rand(10000, 1000000) . '.' . $file->extension();
                $file->move(public_path('images/app_images/' . $conn . '/machinery_doc'), $imageName);
                $data['attachment'] = "images/app_images/" . $conn . "/machinery_doc/" . $imageName;
            }

            if (!empty($data)) {
                DB::table('machinery_documents')->where('id', $id)->update($data);
                addActivity($id, 'machinery_documents', "Machinery Doc Updated via API", 6, $user->id, $conn);
            }

            return response()->json(['status' => 'Ok', 'message' => 'Document updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function deleteMachineryDocument(Request $request, $machinery_id, $id)
    {
        try {
            $user = $request->user();
            $conn = config('database.default');
            $doc = DB::table('machinery_documents')->where('id', $id)->where('machinery_id', $machinery_id)->first();

            if (!$doc) return response()->json(['status' => 'Error', 'message' => 'Document not found for this machinery'], 404);

            DB::table('machinery_documents')->where('id', $id)->delete();
            addActivity(0, 'machinery_documents', "Machinery Doc Deleted via API - " . $doc->name, 6, $user->id, $conn);
            return response()->json(['status' => 'Ok', 'message' => 'Document deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function machineryServices(Request $request, $id)
    {
        try {
            $search = $request->get('search');
            $export = $request->get('export');

            $query = DB::table('machinery_services')
                ->leftJoin('users', 'users.id', '=', 'machinery_services.user_id')
                ->select('machinery_services.*', 'users.name as user_name')
                ->where('machinery_id', $id)
                ->orderBy('create_date', 'desc');

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('maintainence_item', 'LIKE', "%{$search}%")
                      ->orWhere('remark', 'LIKE', "%{$search}%");
                });
            }

            if ($export == 'csv') {
                $results = $query->get();
                $filename = "machinery_service_history_" . $id . "_" . time() . ".csv";
                $headers = ["Content-type" => "text/csv", "Content-Disposition" => "attachment; filename=$filename"];
                $callback = function() use ($results) {
                    $file = fopen('php://output', 'w');
                    fputcsv($file, ['ID', 'Service Date', 'Next Service', 'Items', 'Remark', 'User']);
                    foreach ($results as $row) {
                        fputcsv($file, [$row->id, $row->create_date, $row->next_service_on, $row->maintainence_item, $row->remark, $row->user_name]);
                    }
                    fclose($file);
                };
                return response()->stream($callback, 200, $headers);
            }

            return response()->json(['status' => 'Ok', 'data' => $query->get()]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function storeMachineryService(Request $request, $id)
    {
        $request->validate([
            'create_date' => 'required|date',
            'maintainence_item' => 'required',
        ]);

        try {
            $user = $request->user();
            $conn = config('database.default');
            $images = [];

            for ($i = 1; $i <= 5; $i++) {
                $imageKey = 'image' . $i;
                $file = $request->file($imageKey);
                
                // Flexible check for keys with whitespace/tabs
                if (!$file) {
                    foreach ($_FILES as $key => $fileInfo) {
                        if (trim($key) === $imageKey) {
                            $file = $request->file($key);
                            break;
                        }
                    }
                }

                if ($file) {
                    $imageName = time() . rand(10000, 1000000) . '.' . $file->extension();
                    $file->move(public_path('images/app_images/' . $conn . '/machinery_service'), $imageName);
                    $images[$imageKey] = "images/app_images/" . $conn . "/machinery_service/" . $imageName;
                }
            }

            $serviceData = array_merge([
                'machinery_id' => $id,
                'create_date' => $request->create_date,
                'next_service_on' => $request->next_service_on,
                'maintainence_item' => $request->maintainence_item,
                'remark' => $request->remark,
                'user_id' => $user->id
            ], $images);

            $serviceId = DB::table('machinery_services')->insertGetId($serviceData);
            addActivity($serviceId, 'machinery_services', "New Service Record via API", 6, $user->id, $conn);

            return response()->json(['status' => 'Ok', 'message' => 'Service record added successfully', 'id' => $serviceId]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function getMachineryService($machinery_id, $id)
    {
        try {
            $service = DB::table('machinery_services')->where('id', $id)->where('machinery_id', $machinery_id)->first();
            if (!$service) return response()->json(['status' => 'Error', 'message' => 'Service record not found'], 404);
            return response()->json(['status' => 'Ok', 'data' => $service]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function updateMachineryService(Request $request, $machinery_id, $id)
    {
        try {
            $user = $request->user();
            $conn = config('database.default');
            $service = DB::table('machinery_services')->where('id', $id)->where('machinery_id', $machinery_id)->first();

            if (!$service) return response()->json(['status' => 'Error', 'message' => 'Service record not found'], 404);

            $data = $request->only(['create_date', 'next_service_on', 'maintainence_item', 'remark']);
            $data = array_filter($data, function($value) { return !is_null($value); });

            $images = [];
            for ($i = 1; $i <= 5; $i++) {
                $imageKey = 'image' . $i;
                $file = $request->file($imageKey);
                
                if (!$file) {
                    foreach ($_FILES as $key => $fileInfo) {
                        if (trim($key) === $imageKey) {
                            $file = $request->file($key);
                            break;
                        }
                    }
                }

                if ($file) {
                    $imageName = time() . rand(10000, 1000000) . '.' . $file->extension();
                    $file->move(public_path('images/app_images/' . $conn . '/machinery_service'), $imageName);
                    $images[$imageKey] = "images/app_images/" . $conn . "/machinery_service/" . $imageName;
                }
            }

            $data = array_merge($data, $images);

            if (!empty($data)) {
                DB::table('machinery_services')->where('id', $id)->update($data);
                addActivity($id, 'machinery_services', "Service Record Updated via API", 6, $user->id, $conn);
            }

            return response()->json(['status' => 'Ok', 'message' => 'Service record updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function deleteMachineryService(Request $request, $machinery_id, $id)
    {
        try {
            $user = $request->user();
            $conn = config('database.default');
            $service = DB::table('machinery_services')->where('id', $id)->where('machinery_id', $machinery_id)->first();

            if (!$service) return response()->json(['status' => 'Error', 'message' => 'Service record not found'], 404);

            DB::table('machinery_services')->where('id', $id)->delete();
            addActivity(0, 'machinery_services', "Service Record Deleted via API", 6, $user->id, $conn);
            return response()->json(['status' => 'Ok', 'message' => 'Service record deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function machineryTransferHistory(Request $request, $id)
    {
        try {
            $export = $request->get('export');
            $query = DB::table('machinery_transaction')
                ->leftJoin('sites as fs', 'fs.id', '=', 'machinery_transaction.from_site')
                ->leftJoin('sites as ts', 'ts.id', '=', 'machinery_transaction.to_site')
                ->select('machinery_transaction.*', 'fs.name as from_site_name', 'ts.name as to_site_name')
                ->where('machinery_id', $id)
                ->orderBy('id', 'desc');

            if ($export == 'csv') {
                $results = $query->get();
                $filename = "machinery_transfer_history_" . $id . "_" . time() . ".csv";
                $headers = ["Content-type" => "text/csv", "Content-Disposition" => "attachment; filename=$filename"];
                $callback = function() use ($results) {
                    $file = fopen('php://output', 'w');
                    fputcsv($file, ['ID', 'From Site', 'To Site', 'Type', 'Remark', 'Date']);
                    foreach ($results as $row) {
                        fputcsv($file, [$row->id, $row->from_site_name ?? 'N/A', $row->to_site_name ?? 'N/A', $row->transaction_type, $row->remark, $row->create_datetime]);
                    }
                    fclose($file);
                };
                return response()->stream($callback, 200, $headers);
            }

            return response()->json(['status' => 'Ok', 'data' => $query->get()]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function transferMachinery(Request $request, $id)
    {
        if (!empty($request->getContent())) {
            $json = json_decode($request->getContent(), true);
            if ($json) $request->request->add($json);
        }

        $request->validate([
            'to_site' => 'required',
            'remark' => 'nullable'
        ]);

        try {
            $user = $request->user();
            $conn = config('database.default');

            return DB::transaction(function () use ($request, $id, $user, $conn) {
                $machinery = DB::table('machinery_details')->where('id', $id)->first();
                if (!$machinery) return response()->json(['status' => 'Error', 'message' => 'Machinery not found'], 404);

                $from_site = $machinery->site_id;
                $to_site = $request->to_site;

                DB::table('machinery_transaction')->insert([
                    'machinery_id' => $id,
                    'from_site' => $from_site,
                    'to_site' => $to_site,
                    'transaction_type' => 'Transfer',
                    'remark' => $request->remark ?? 'Transferred via API',
                    'create_datetime' => now()
                ]);

                DB::table('machinery_details')->where('id', $id)->update(['site_id' => $to_site]);
                addActivity($id, 'machinery_details', "Machinery Transferred from Site $from_site to Site $to_site via API", 6, $user->id, $conn);

                return response()->json(['status' => 'Ok', 'message' => 'Machinery transferred successfully']);
            });
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function sellMachinery(Request $request, $id)
    {
        if (!empty($request->getContent())) {
            $json = json_decode($request->getContent(), true);
            if ($json) $request->request->add($json);
        }

        $request->validate([
            'sale_price' => 'required|numeric',
            'remark' => 'nullable'
        ]);

        try {
            $user = $request->user();
            $conn = config('database.default');

            DB::table('machinery_details')->where('id', $id)->update([
                'status' => 'Sold',
                'sale_price' => $request->sale_price
            ]);

            DB::table('machinery_transaction')->insert([
                'machinery_id' => $id,
                'transaction_type' => 'Sale',
                'remark' => $request->remark ?? 'Sold via API',
                'create_datetime' => now()
            ]);

            addActivity($id, 'machinery_details', "Machinery Sold for " . $request->sale_price . " via API", 6, $user->id, $conn);

            return response()->json(['status' => 'Ok', 'message' => 'Machinery marked as Sold successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function listMachineryExpenseHeads(Request $request)
    {
        try {
            $search = $request->get('search');
            $export = $request->get('export');

            $query = DB::table('machinery_expense_head')
                ->leftJoin('expense_head', 'expense_head.id', '=', 'machinery_expense_head.head_id')
                ->select('machinery_expense_head.*', 'expense_head.name as head_name')
                ->orderBy('machinery_expense_head.id', 'desc');

            if ($search) {
                $query->where('expense_head.name', 'LIKE', "%{$search}%");
            }

            if ($export == 'csv') {
                $results = $query->get();
                $filename = "machinery_expense_heads_" . time() . ".csv";
                $headers = ["Content-type" => "text/csv", "Content-Disposition" => "attachment; filename=$filename"];
                $callback = function() use ($results) {
                    $file = fopen('php://output', 'w');
                    fputcsv($file, ['ID', 'Expense Head Name']);
                    foreach ($results as $row) {
                        fputcsv($file, [$row->id, $row->head_name]);
                    }
                    fclose($file);
                };
                return response()->stream($callback, 200, $headers);
            }

            return response()->json(['status' => 'Ok', 'data' => $query->paginate(20)]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function storeMachineryExpenseHead(Request $request)
    {
        if (!empty($request->getContent())) {
            $json = json_decode($request->getContent(), true);
            if ($json) $request->merge($json);
        }

        $request->validate([
            'head_id' => 'required'
        ]);

        try {
            $user = $request->user();
            $conn = config('database.default');

            // Manual check for existence in tenant DB
            $head_exists = DB::table('expense_head')->where('id', $request->head_id)->exists();
            if (!$head_exists) {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => ['head_id' => ['The selected head id is invalid.']]
                ], 422);
            }

            $exists = DB::table('machinery_expense_head')->where('head_id', $request->head_id)->exists();
            if ($exists) return response()->json(['status' => 'Error', 'message' => 'Expense Head already added to machinery'], 400);

            $id = DB::table('machinery_expense_head')->insertGetId(['head_id' => $request->head_id]);
            addActivity($id, 'machinery_expense_head', "New Machinery Expense Head Added via API", 6, $user->id, $conn);

            return response()->json(['status' => 'Ok', 'message' => 'Expense Head added successfully', 'id' => $id]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function deleteMachineryExpenseHead(Request $request, $id)
    {
        try {
            $user = $request->user();
            $conn = config('database.default');

            DB::table('machinery_expense_head')->where('id', $id)->delete();
            addActivity($id, 'machinery_expense_head', "Machinery Expense Head Deleted via API", 6, $user->id, $conn);

            return response()->json(['status' => 'Ok', 'message' => 'Expense Head deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }
}
