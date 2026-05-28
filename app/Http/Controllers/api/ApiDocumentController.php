<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use PDF;

class ApiDocumentController extends Controller
{
    protected $size = 0;
    protected $total_no_of_files = 0;
    protected $total_no_of_images = 0;
    protected $total_no_of_pdfs = 0;
    protected $total_no_of_otherFiles = 0;

    /**
     * Resolve active Database Connection and User ID from Request or Bearer Token fallback
     */
    private function resolveTenant(Request $request)
    {
        $conn = $request->get('conn') ?? $request->post('conn');
        $user_id = $request->get('uid') ?? $request->post('uid');

        // Fallback: Resolve from Bearer token
        if ((!$conn || !$user_id) && $request->bearerToken()) {
            $tokenStr = $request->bearerToken();
            $tokenId = null;
            if (strpos($tokenStr, '|') !== false) {
                [$tokenId, $tokenStr] = explode('|', $tokenStr, 2);
            }
            $token = DB::connection('mysql')->table('personal_access_tokens')->where('id', $tokenId)->first();
            if ($token) {
                $conn = $conn ?? $token->name;
                $user_id = $user_id ?? $token->tokenable_id;
            }
        }

        if (!$conn) {
            $conn = config('database.default');
        }

        return ['conn' => $conn, 'uid' => $user_id];
    }

    /**
     * Get directory hierarchy tree recursive helper
     */
    private function getDirectoryTree($disk, $path = '')
    {
        $tree = [];
        try {
            $directories = Storage::disk($disk)->directories($path);
            $files = Storage::disk($disk)->files($path);
            
            foreach ($directories as $directory) {
                $dirName = basename($directory);
                $tree[] = [
                    'type' => 'directory',
                    'name' => $dirName,
                    'path' => $directory,
                    'children' => $this->getDirectoryTree($disk, $directory)
                ];
            }
            foreach ($files as $file) {
                $tree[] = [
                    'type' => 'file',
                    'name' => basename($file),
                    'path' => $file,
                    'timestamp' => Storage::disk($disk)->lastModified($file),
                ];
                $this->size += Storage::disk($disk)->size($file);
                $filenamedet = explode('.', basename($file));
                $file_ext = end($filenamedet);
                if (strtolower($file_ext) == 'pdf') {
                    $this->total_no_of_pdfs++;
                } else if (in_array(strtolower($file_ext), ['jpg', 'jpeg', 'png', 'svg', 'gif', 'webp'])) {
                    $this->total_no_of_images++;
                } else {
                    $this->total_no_of_otherFiles++;
                }
                $this->total_no_of_files++;
            }
        } catch (\Exception $e) {
            // Silence storage disk faults gracefully
        }
        return $tree;
    }

    /**
     * Group directory tree array chronologically by date
     */
    private function organizeFilesByDate($data, $parentPath = '')
    {
        $organizedData = [];
        foreach ($data as $item) {
            if ($item['type'] == 'directory' && isset($item['children'])) {
                $children = $this->organizeFilesByDate($item['children'], $parentPath . $item['name'] . '/');
                $organizedData[] = [
                    'type' => 'directory',
                    'name' => $item['name'],
                    'path' => $item['path'],
                    'children' => $children
                ];
            } elseif ($item['type'] == 'file') {
                $year = date('Y', $item['timestamp']);
                $month = date('m', $item['timestamp']);
                $yearIndex = array_search($year, array_column($organizedData, 'name'));
                if ($yearIndex === false) {
                    $organizedData[] = [
                        'type' => 'directory',
                        'name' => $year,
                        'path' => $parentPath . $year,
                        'children' => []
                    ];
                    $yearIndex = count($organizedData) - 1;
                }
                $monthIndex = array_search($month, array_column($organizedData[$yearIndex]['children'], 'name'));
                if ($monthIndex === false) {
                    $organizedData[$yearIndex]['children'][] = [
                        'type' => 'directory',
                        'name' => $month,
                        'path' => $parentPath . $year . '/' . $month,
                        'children' => []
                    ];
                    $monthIndex = count($organizedData[$yearIndex]['children']) - 1;
                }
                $organizedData[$yearIndex]['children'][$monthIndex]['children'][] = $item;
            }
        }
        return $organizedData;
    }

    /**
     * 1. Get List of Pending Documents
     */
    public function pending(Request $request)
    {
        try {
            $tenant = $this->resolveTenant($request);
            $conn = $tenant['conn'];
            
            $query = DB::connection($conn)->table('doc_upload')
                ->leftJoin('users', 'users.id', '=', 'doc_upload.created_by')
                ->select('doc_upload.*', 'users.name as creator_name')
                ->where('doc_upload.status', 'Pending')
                ->orderBy('doc_upload.id', 'desc');
            
            // Search implementation
            $search = $request->get('search');
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('doc_upload.name', 'LIKE', '%' . $search . '%')
                      ->orWhere('doc_upload.particular', 'LIKE', '%' . $search . '%')
                      ->orWhere('doc_upload.remark', 'LIKE', '%' . $search . '%');
                });
            }
            
            $head_id = $request->get('head_id');
            if ($head_id) {
                $subQuery = DB::connection($conn)->table('doc_meta')
                    ->where('head_id', $head_id)
                    ->pluck('doc_id');
                $query->whereIn('doc_upload.id', $subQuery);
            }
            
            $filtersJson = $request->get('filters');
            if ($filtersJson) {
                $filters = is_array($filtersJson) ? $filtersJson : json_decode($filtersJson, true);
                if (is_array($filters) && count($filters) > 0) {
                    $filters_count = count($filters);
                    $subQuery = DB::connection($conn)->table('doc_meta')
                        ->select('doc_id')
                        ->whereIn('structure', $filters)
                        ->groupBy('doc_id')
                        ->havingRaw('COUNT(DISTINCT structure) = ?', [$filters_count])
                        ->pluck('doc_id');
                    $query->whereIn('doc_upload.id', $subQuery);
                }
            }
            
            // CSV Export logic
            if ($request->get('export') === 'csv') {
                $docs = $query->get();
                foreach ($docs as $doc) {
                    $doc_filters = DB::connection($conn)->table('doc_meta')
                        ->leftJoin('doc_head', 'doc_head.id', '=', 'doc_meta.head_id')
                        ->leftJoin('doc_head_option', 'doc_head_option.id', '=', 'doc_meta.option_id')
                        ->where('doc_meta.doc_id', $doc->id)
                        ->select('doc_head.name as head_name', 'doc_head_option.name as option_name')
                        ->get();
                    $filterString = '';
                    $fc = 0;
                    foreach ($doc_filters as $filter) {
                        $filterString .= "[" . $filter->head_name . " => " . $filter->option_name . "]";
                        $fc++;
                        if ($fc < count($doc_filters)) $filterString .= " , ";
                    }
                    $doc->filter = $filterString;
                }

                $filename = "pending_documents_" . date('YmdHis') . ".csv";
                $headers = [
                    "Content-type"        => "text/csv",
                    "Content-Disposition" => "attachment; filename=$filename",
                    "Pragma"              => "no-cache",
                    "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
                    "Expires"             => "0"
                ];

                $callback = function() use($docs) {
                    $file = fopen('php://output', 'w');
                    fputcsv($file, ['ID', 'Name', 'Date', 'Particulars', 'Remark', 'Status', 'Creator', 'Filters', 'Document URL']);
                    foreach ($docs as $row) {
                        fputcsv($file, [
                            $row->id,
                            $row->name,
                            $row->date,
                            $row->particular,
                            $row->remark,
                            $row->status,
                            $row->creator_name ?: 'System',
                            $row->filter,
                            $row->path ? asset($row->path) : ''
                        ]);
                    }
                    fclose($file);
                };

                return response()->stream($callback, 200, $headers);
            }
            
            // Paginated JSON response
            $perPage = (int)$request->get('per_page', 10);
            $paginator = $query->paginate($perPage);
            
            foreach ($paginator->items() as $doc) {
                $doc_filters = DB::connection($conn)->table('doc_meta')
                    ->leftJoin('doc_head', 'doc_head.id', '=', 'doc_meta.head_id')
                    ->leftJoin('doc_head_option', 'doc_head_option.id', '=', 'doc_meta.option_id')
                    ->where('doc_meta.doc_id', $doc->id)
                    ->select('doc_head.name as head_name', 'doc_head_option.name as option_name', 'doc_meta.head_id', 'doc_meta.option_id')
                    ->get();
                
                $filterString = '';
                $filter_count = 0;
                foreach ($doc_filters as $filter) {
                    $filterString .= "[" . $filter->head_name . " => " . $filter->option_name . "]";
                    $filter_count++;
                    if ($filter_count < count($doc_filters)) {
                        $filterString .= " , ";
                    }
                }
                
                $doc->filter = $filterString;
                $doc->filters_list = $doc_filters;
                $doc->absolute_path = $doc->path ? asset($doc->path) : asset("images/noprofile.jpg");
            }
            
            return response()->json([
                'status' => 'Ok',
                'status_code' => '200',
                'data' => $paginator
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'Failed',
                'status_code' => '300',
                'message' => 'Failed to fetch pending documents: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * 2. Get List of My Documents (Approved)
     */
    public function myDocuments(Request $request)
    {
        try {
            $tenant = $this->resolveTenant($request);
            $conn = $tenant['conn'];
            
            $query = DB::connection($conn)->table('doc_upload')
                ->leftJoin('users', 'users.id', '=', 'doc_upload.created_by')
                ->select('doc_upload.*', 'users.name as creator_name')
                ->where('doc_upload.status', 'Approved')
                ->orderBy('doc_upload.id', 'desc');
            
            // Search implementation
            $search = $request->get('search');
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('doc_upload.name', 'LIKE', '%' . $search . '%')
                      ->orWhere('doc_upload.particular', 'LIKE', '%' . $search . '%')
                      ->orWhere('doc_upload.remark', 'LIKE', '%' . $search . '%');
                });
            }
            
            $head_id = $request->get('head_id');
            if ($head_id) {
                $subQuery = DB::connection($conn)->table('doc_meta')
                    ->where('head_id', $head_id)
                    ->pluck('doc_id');
                $query->whereIn('doc_upload.id', $subQuery);
            }
            
            $filtersJson = $request->get('filters');
            if ($filtersJson) {
                $filters = is_array($filtersJson) ? $filtersJson : json_decode($filtersJson, true);
                if (is_array($filters) && count($filters) > 0) {
                    $filters_count = count($filters);
                    $subQuery = DB::connection($conn)->table('doc_meta')
                        ->select('doc_id')
                        ->whereIn('structure', $filters)
                        ->groupBy('doc_id')
                        ->havingRaw('COUNT(DISTINCT structure) = ?', [$filters_count])
                        ->pluck('doc_id');
                    $query->whereIn('doc_upload.id', $subQuery);
                }
            }
            
            // CSV Export logic
            if ($request->get('export') === 'csv') {
                $docs = $query->get();
                foreach ($docs as $doc) {
                    $doc_filters = DB::connection($conn)->table('doc_meta')
                        ->leftJoin('doc_head', 'doc_head.id', '=', 'doc_meta.head_id')
                        ->leftJoin('doc_head_option', 'doc_head_option.id', '=', 'doc_meta.option_id')
                        ->where('doc_meta.doc_id', $doc->id)
                        ->select('doc_head.name as head_name', 'doc_head_option.name as option_name')
                        ->get();
                    $filterString = '';
                    $fc = 0;
                    foreach ($doc_filters as $filter) {
                        $filterString .= "[" . $filter->head_name . " => " . $filter->option_name . "]";
                        $fc++;
                        if ($fc < count($doc_filters)) $filterString .= " , ";
                    }
                    $doc->filter = $filterString;
                }

                $filename = "my_documents_" . date('YmdHis') . ".csv";
                $headers = [
                    "Content-type"        => "text/csv",
                    "Content-Disposition" => "attachment; filename=$filename",
                    "Pragma"              => "no-cache",
                    "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
                    "Expires"             => "0"
                ];

                $callback = function() use($docs) {
                    $file = fopen('php://output', 'w');
                    fputcsv($file, ['ID', 'Name', 'Date', 'Particulars', 'Remark', 'Status', 'Creator', 'Filters', 'Document URL']);
                    foreach ($docs as $row) {
                        fputcsv($file, [
                            $row->id,
                            $row->name,
                            $row->date,
                            $row->particular,
                            $row->remark,
                            $row->status,
                            $row->creator_name ?: 'System',
                            $row->filter,
                            $row->path ? asset($row->path) : ''
                        ]);
                    }
                    fclose($file);
                };

                return response()->stream($callback, 200, $headers);
            }
            
            // Paginated JSON response
            $perPage = (int)$request->get('per_page', 10);
            $paginator = $query->paginate($perPage);
            
            foreach ($paginator->items() as $doc) {
                $doc_filters = DB::connection($conn)->table('doc_meta')
                    ->leftJoin('doc_head', 'doc_head.id', '=', 'doc_meta.head_id')
                    ->leftJoin('doc_head_option', 'doc_head_option.id', '=', 'doc_meta.option_id')
                    ->where('doc_meta.doc_id', $doc->id)
                    ->select('doc_head.name as head_name', 'doc_head_option.name as option_name', 'doc_meta.head_id', 'doc_meta.option_id')
                    ->get();
                
                $filterString = '';
                $filter_count = 0;
                foreach ($doc_filters as $filter) {
                    $filterString .= "[" . $filter->head_name . " => " . $filter->option_name . "]";
                    $filter_count++;
                    if ($filter_count < count($doc_filters)) {
                        $filterString .= " , ";
                    }
                }
                
                $doc->filter = $filterString;
                $doc->filters_list = $doc_filters;
                $doc->absolute_path = $doc->path ? asset($doc->path) : asset("images/noprofile.jpg");
            }
            
            return response()->json([
                'status' => 'Ok',
                'status_code' => '200',
                'data' => $paginator
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'Failed',
                'status_code' => '300',
                'message' => 'Failed to fetch my documents: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * 3. Get System Documents organized chronologically
     */
    public function systemDocuments(Request $request)
    {
        try {
            $tenant = $this->resolveTenant($request);
            $conn = $tenant['conn'];
            
            $this->size = 0;
            $this->total_no_of_files = 0;
            $this->total_no_of_images = 0;
            $this->total_no_of_pdfs = 0;
            $this->total_no_of_otherFiles = 0;
            
            $director = $this->getDirectoryTree('uploaded_images', $conn);
            $directories = $this->organizeFilesByDate($director);
            
            return response()->json([
                'status' => 'Ok',
                'status_code' => '200',
                'data' => $directories
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'Failed',
                'status_code' => '300',
                'message' => 'Failed to fetch system documents: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * 4. Get Document / Category Heads List
     */
    public function heads(Request $request)
    {
        try {
            $tenant = $this->resolveTenant($request);
            $conn = $tenant['conn'];
            
            $heads = DB::connection($conn)->table('doc_head')->get();
            return response()->json([
                'status' => 'Ok',
                'status_code' => '200',
                'data' => $heads
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'Failed',
                'status_code' => '300',
                'message' => 'Failed to fetch document heads: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Store a new Document Head / Category
     */
    public function storeHead(Request $request)
    {
        try {
            $tenant = $this->resolveTenant($request);
            $conn = $tenant['conn'];
            $user_id = $tenant['uid'];
            
            $name = $request->input('name');
            if (!$name) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'Document Head Name is required!'
                ]);
            }
            
            // Check if head already exists (case-insensitive check)
            $exists = DB::connection($conn)->table('doc_head')
                ->whereRaw('LOWER(name) = ?', [strtolower($name)])
                ->exists();
                
            if ($exists) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'Document Head Already Exists!'
                ]);
            }
            
            $id = DB::connection($conn)->table('doc_head')->insertGetId([
                'name' => $name
            ]);
            
            addActivity($id, 'doc_head', "New Document Head Created via API - " . $name, 11, $user_id, $conn);
            
            $newHead = DB::connection($conn)->table('doc_head')->where('id', $id)->first();
            
            return response()->json([
                'status' => 'Ok',
                'status_code' => '200',
                'message' => 'Document Head Created Successfully!',
                'data' => $newHead
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'Failed',
                'status_code' => '300',
                'message' => 'Failed to create document head: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Show details of a Document Head with its options
     */
    public function showHead(Request $request, $id = null)
    {
        try {
            $tenant = $this->resolveTenant($request);
            $conn = $tenant['conn'];
            
            $head_id = $id ?? $request->input('id') ?? $request->get('id');
            if (!$head_id) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'Document Head ID is required!'
                ]);
            }
            
            $head = DB::connection($conn)->table('doc_head')->where('id', $head_id)->first();
            if (!$head) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'Document Head not found!'
                ]);
            }
            
            $options = DB::connection($conn)->table('doc_head_option')->where('head_id', $head_id)->get();
            $head->options = $options;
            
            return response()->json([
                'status' => 'Ok',
                'status_code' => '200',
                'data' => $head
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'Failed',
                'status_code' => '300',
                'message' => 'Failed to fetch document head: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Update an existing Document Head name
     */
    public function updateHead(Request $request, $id = null)
    {
        try {
            $tenant = $this->resolveTenant($request);
            $conn = $tenant['conn'];
            $user_id = $tenant['uid'];
            
            $head_id = $id ?? $request->input('id') ?? $request->get('id');
            if (!$head_id) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'Document Head ID is required!'
                ]);
            }
            
            $head = DB::connection($conn)->table('doc_head')->where('id', $head_id)->first();
            if (!$head) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'Document Head not found!'
                ]);
            }
            
            $name = $request->input('name');
            if (!$name) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'Document Head Name is required!'
                ]);
            }
            
            // Check if another head already has this name
            $exists = DB::connection($conn)->table('doc_head')
                ->where('id', '!=', $head_id)
                ->whereRaw('LOWER(name) = ?', [strtolower($name)])
                ->exists();
                
            if ($exists) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'Another Document Head already has this name!'
                ]);
            }
            
            DB::connection($conn)->table('doc_head')->where('id', $head_id)->update([
                'name' => $name
            ]);
            
            addActivity($head_id, 'doc_head', "Document Head Updated via API - " . $name, 11, $user_id, $conn);
            
            $updatedHead = DB::connection($conn)->table('doc_head')->where('id', $head_id)->first();
            
            return response()->json([
                'status' => 'Ok',
                'status_code' => '200',
                'message' => 'Document Head Updated Successfully!',
                'data' => $updatedHead
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'Failed',
                'status_code' => '300',
                'message' => 'Failed to update document head: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Delete an existing Document Head if not in use
     */
    public function destroyHead(Request $request, $id = null)
    {
        try {
            $tenant = $this->resolveTenant($request);
            $conn = $tenant['conn'];
            $user_id = $tenant['uid'];
            
            $head_id = $id ?? $request->input('id') ?? $request->get('id');
            if (!$head_id) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'Document Head ID is required!'
                ]);
            }
            
            $head = DB::connection($conn)->table('doc_head')->where('id', $head_id)->first();
            if (!$head) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'Document Head not found!'
                ]);
            }
            
            // Check if head has options
            $hasOptions = DB::connection($conn)->table('doc_head_option')->where('head_id', $head_id)->exists();
            if ($hasOptions) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'Document Head is in use by head options!'
                ]);
            }
            
            // Check if head has references in doc_meta
            $hasMeta = DB::connection($conn)->table('doc_meta')->where('head_id', $head_id)->exists();
            if ($hasMeta) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'Document Head is in use by uploaded documents!'
                ]);
            }
            
            DB::connection($conn)->table('doc_head')->where('id', $head_id)->delete();
            
            addActivity(0, 'doc_head', "Document Head Deleted via API - " . $head->name, 11, $user_id, $conn);
            
            return response()->json([
                'status' => 'Ok',
                'status_code' => '200',
                'message' => 'Document Head Deleted Successfully!'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'Failed',
                'status_code' => '300',
                'message' => 'Failed to delete document head: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get Document Summary (Heads and Options grouped)
     */
    public function summary(Request $request)
    {
        try {
            $tenant = $this->resolveTenant($request);
            $conn = $tenant['conn'];
            
            $heads = DB::connection($conn)->table('doc_head')->get();
            foreach ($heads as $head) {
                $head->options = DB::connection($conn)->table('doc_head_option')->where('head_id', $head->id)->get();
            }
            return response()->json([
                'status' => 'Ok',
                'status_code' => '200',
                'data' => $heads
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'Failed',
                'status_code' => '300',
                'message' => 'Failed to fetch summary: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get Document Dashboard Metrics
     */
    public function dashboard(Request $request)
    {
        try {
            $tenant = $this->resolveTenant($request);
            $conn = $tenant['conn'];
            
            $this->size = 0;
            $this->total_no_of_files = 0;
            $this->total_no_of_images = 0;
            $this->total_no_of_pdfs = 0;
            $this->total_no_of_otherFiles = 0;
            
            $this->getDirectoryTree('uploaded_images', $conn);
            
            $sizeInMB = round($this->size / (1024 * 1024), 2);
            $sizeFormatted = $sizeInMB . " MB";
            
            $percentUsed = round(($sizeInMB / 1024) * 100, 2);
            if ($percentUsed < 0.01 && $sizeInMB > 0) {
                $percentUsed = 0.01;
            }
            
            $totalDocHeads = DB::connection($conn)->table('doc_head')->count();
            
            return response()->json([
                'status' => 'Ok',
                'status_code' => '200',
                'data' => [
                    'storage_consume' => $sizeFormatted,
                    'storage_bytes' => $this->size,
                    'storage_limit' => '1 GB',
                    'storage_percent' => $percentUsed . "%",
                    'total_files' => $this->total_no_of_files,
                    'total_images' => $this->total_no_of_images,
                    'total_pdfs' => $this->total_no_of_pdfs,
                    'total_others' => $this->total_no_of_otherFiles,
                    'total_heads' => $totalDocHeads
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'Failed',
                'status_code' => '300',
                'message' => 'Failed to fetch document stats: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Store New Document
     */
    public function store(Request $request)
    {
        try {
            $tenant = $this->resolveTenant($request);
            $conn = $tenant['conn'];
            $user_id = $tenant['uid'];
            
            \Log::info('Document Upload Request received.', [
                'all_keys' => array_keys($request->all()),
                'has_file' => $request->hasFile('file'),
                'is_file_array' => is_array($request->file('file')),
                'file_class' => $request->file('file') ? (is_array($request->file('file')) ? 'array of ' . count($request->file('file')) : get_class($request->file('file'))) : 'null',
                'all_files' => array_keys($request->allFiles())
            ]);
            
            $nameInput = $request->input('name');
            if (!$nameInput) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'Document Name is required!'
                ]);
            }
            
            // Identify files and base64 inputs robustly across all keys
            $all_uploaded_files = [];
            foreach ($request->allFiles() as $key => $fileOrArray) {
                if (is_array($fileOrArray)) {
                    foreach ($fileOrArray as $file) {
                        if ($file) {
                            $all_uploaded_files[] = $file;
                        }
                    }
                } else {
                    if ($fileOrArray) {
                        $all_uploaded_files[] = $fileOrArray;
                    }
                }
            }

            $all_base64_strings = [];
            $base64_keys = ['file', 'img', 'document', 'attachment'];
            foreach ($base64_keys as $key) {
                $val = $request->post($key);
                if ($val) {
                    if (is_array($val)) {
                        foreach ($val as $b64) {
                            if ($b64 && preg_match('/^data:[\w\/\.\-]+;base64,/', $b64)) {
                                $all_base64_strings[] = $b64;
                            }
                        }
                    } else {
                        if ($val && preg_match('/^data:[\w\/\.\-]+;base64,/', $val)) {
                            $all_base64_strings[] = $val;
                        }
                    }
                }
            }

            $file_list = $all_uploaded_files;
            $base64_list = $all_base64_strings;
            
            // Treat as multiple if more than one file/base64 is sent, or if name is an array
            $is_multiple = (count($file_list) > 1 || count($base64_list) > 1 || is_array($request->input('name')));
            
            if (empty($file_list) && empty($base64_list)) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'Document file attachment is required!'
                ]);
            }
            
            $userObj = DB::connection('mysql')->table('users')->where('id', $user_id)->first();
            $role_id = $userObj ? $userObj->role_id : 2;
            $status = ($role_id == 1) ? 'Approved' : 'Pending';
            
            return DB::transaction(function () use ($conn, $status, $user_id, $file_list, $base64_list, $request, $is_multiple) {
                $uploadedDocs = [];
                $nameInput = $request->input('name');
                
                // Resolve loop count based on max number of name items or files
                $count = max(
                    !empty($file_list) ? count($file_list) : 0,
                    !empty($base64_list) ? count($base64_list) : 0,
                    is_array($nameInput) ? count($nameInput) : 1
                );

                $savedPaths = [];

                for ($i = 0; $i < $count; $i++) {
                    $imagePath = "";

                    // Resolve field values for this specific index
                    if (is_array($nameInput)) {
                        $nameVal = $nameInput[$i] ?? 'Document';
                    } else {
                        $nameVal = $nameInput;
                        if ($is_multiple && $nameVal) {
                            $nameVal = $nameVal . ' (' . ($i + 1) . ')';
                        }
                    }
                    if (!$nameVal) {
                        $nameVal = 'Document_' . ($i + 1);
                    }

                    $dateInput = $request->input('date', date('Y-m-d'));
                    $dateVal = is_array($dateInput) ? ($dateInput[$i] ?? date('Y-m-d')) : $dateInput;
                    if (!$dateVal) {
                        $dateVal = date('Y-m-d');
                    }

                    $particularInput = $request->input('particular', '');
                    $particularVal = is_array($particularInput) ? ($particularInput[$i] ?? '') : $particularInput;

                    $remarkInput = $request->input('remark', '');
                    $remarkVal = is_array($remarkInput) ? ($remarkInput[$i] ?? '') : $remarkInput;

                    // 1. Process file upload if it's a file list
                    if (!empty($file_list)) {
                        $fileIndex = isset($file_list[$i]) ? $i : 0;
                        if (isset($savedPaths[$fileIndex])) {
                            $imagePath = $savedPaths[$fileIndex];
                        } else {
                            $file = $file_list[$fileIndex];
                            if ($file) {
                                $dir = public_path('images/app_images/' . $conn . '/documents');
                                if (!File::exists($dir)) {
                                    File::makeDirectory($dir, 0777, true, true);
                                }
                                $imageName = time() . rand(10000, 1000000) . '.' . $file->getClientOriginalExtension();
                                $file->move($dir, $imageName);
                                $imagePath = "images/app_images/" . $conn . "/documents/" . $imageName;
                                $savedPaths[$fileIndex] = $imagePath;
                            }
                        }
                    } 
                    // 2. Process base64 if it's a base64 list
                    elseif (!empty($base64_list)) {
                        $b64Index = isset($base64_list[$i]) ? $i : 0;
                        if (isset($savedPaths[$b64Index])) {
                            $imagePath = $savedPaths[$b64Index];
                        } else {
                            $base64 = $base64_list[$b64Index];
                            if ($base64 && preg_match('/^data:[\w\/\.\-]+;base64,/', $base64)) {
                                $base64Data = substr($base64, strpos($base64, ',') + 1);
                                $decoded = base64_decode($base64Data);
                                if ($decoded !== false) {
                                    $extension = 'pdf';
                                    if (strpos($base64, 'image/jpeg') !== false || strpos($base64, 'image/jpg') !== false) {
                                        $extension = 'jpg';
                                    } elseif (strpos($base64, 'image/png') !== false) {
                                        $extension = 'png';
                                    } elseif (strpos($base64, 'image/gif') !== false) {
                                        $extension = 'gif';
                                    }
                                    
                                    $dir = public_path('images/app_images/' . $conn . '/documents');
                                    if (!File::exists($dir)) {
                                        File::makeDirectory($dir, 0777, true, true);
                                    }
                                    $imageName = time() . rand(10000, 1000000) . '.' . $extension;
                                    file_put_contents($dir . '/' . $imageName, $decoded);
                                    $imagePath = "images/app_images/" . $conn . "/documents/" . $imageName;
                                    $savedPaths[$b64Index] = $imagePath;
                                }
                            }
                        }
                    }

                    if (!$imagePath) {
                        throw new \Exception("Failed to upload file at index " . ($i + 1));
                    }

                    // Insert to database
                    $docId = DB::connection($conn)->table('doc_upload')->insertGetId([
                        'name' => $nameVal,
                        'date' => $dateVal,
                        'particular' => $particularVal,
                        'remark' => $remarkVal,
                        'path' => $imagePath,
                        'status' => $status,
                        'created_by' => $user_id
                    ]);

                    // Apply filters to database
                    $filtersInput = $request->input('filters');
                    if ($filtersInput) {
                        $decodedFilters = is_array($filtersInput) ? $filtersInput : json_decode($filtersInput, true);
                        if (is_array($decodedFilters)) {
                            // Check if it's a multidimensional array (nested array for each file)
                            $is_nested = false;
                            foreach ($decodedFilters as $key => $val) {
                                if (is_array($val)) {
                                    $is_nested = true;
                                    break;
                                }
                            }
                            $filtersVal = $is_nested ? ($decodedFilters[$i] ?? []) : $decodedFilters;

                            if (is_array($filtersVal) && count($filtersVal) > 0) {
                                $meta = [];
                                foreach ($filtersVal as $filt) {
                                    if (!empty($filt) && strpos($filt, '=>') !== false) {
                                        [$head, $option] = explode('=>', $filt, 2);
                                        $meta[] = [
                                            'doc_id' => $docId,
                                            'head_id' => $head,
                                            'option_id' => $option,
                                            'structure' => $filt
                                        ];
                                    }
                                }
                                if (count($meta) > 0) {
                                    DB::connection($conn)->table('doc_meta')->insert($meta);
                                }
                            }
                        }
                    }

                    addActivity($docId, 'doc_upload', "New Document Uploaded via API", 11, $user_id, $conn);

                    $uploadedDocs[] = [
                        'id' => $docId,
                        'name' => $nameVal,
                        'status' => $status,
                        'path' => asset($imagePath)
                    ];
                }

                $responseData = $is_multiple ? $uploadedDocs : ($uploadedDocs[0] ?? null);

                return response()->json([
                    'status' => 'Ok',
                    'status_code' => '200',
                    'message' => $is_multiple ? 'Documents Uploaded Successfully!' : 'Document Uploaded Successfully!',
                    'data' => $responseData
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'Failed',
                'status_code' => '300',
                'message' => 'Failed to store document: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Approve pending document
     */
    public function approve(Request $request)
    {
        try {
            $tenant = $this->resolveTenant($request);
            $conn = $tenant['conn'];
            $user_id = $tenant['uid'];
            
            $doc_id = $request->input('id');
            if (!$doc_id) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'Document ID is required!'
                ]);
            }
            
            $exists = DB::connection($conn)->table('doc_upload')->where('id', $doc_id)->exists();
            if (!$exists) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'Document not found!'
                ]);
            }
            
            DB::connection($conn)->table('doc_upload')->where('id', $doc_id)->update(['status' => 'Approved']);
            addActivity($doc_id, 'doc_upload', "Document Approved via API", 11, $user_id, $conn);
            
            return response()->json([
                'status' => 'Ok',
                'status_code' => '200',
                'message' => 'Document Approved Successfully!'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'Failed',
                'status_code' => '300',
                'message' => 'Failed to approve document: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get a single Document by ID with its associated filters
     */
    public function show(Request $request, $id = null)
    {
        try {
            $tenant = $this->resolveTenant($request);
            $conn = $tenant['conn'];
            
            $doc_id = $id ?? $request->input('id') ?? $request->get('id');
            if (!$doc_id) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'Document ID is required!'
                ]);
            }
            
            $doc = DB::connection($conn)->table('doc_upload')
                ->leftJoin('users', 'users.id', '=', 'doc_upload.created_by')
                ->select('doc_upload.*', 'users.name as creator_name')
                ->where('doc_upload.id', $doc_id)
                ->first();
                
            if (!$doc) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'Document not found!'
                ]);
            }
            
            // Get associated filters
            $doc_filters = DB::connection($conn)->table('doc_meta')
                ->leftJoin('doc_head', 'doc_head.id', '=', 'doc_meta.head_id')
                ->leftJoin('doc_head_option', 'doc_head_option.id', '=', 'doc_meta.option_id')
                ->where('doc_meta.doc_id', $doc->id)
                ->select('doc_head.name as head_name', 'doc_head_option.name as option_name', 'doc_meta.head_id', 'doc_meta.option_id', 'doc_meta.structure')
                ->get();
            
            $filterString = '';
            $filter_count = 0;
            $filtersArray = [];
            
            foreach ($doc_filters as $filter) {
                $filterString .= "[" . $filter->head_name . " => " . $filter->option_name . "]";
                $filter_count++;
                if ($filter_count < count($doc_filters)) {
                    $filterString .= " , ";
                }
                if ($filter->structure) {
                    $filtersArray[] = $filter->structure;
                } else if ($filter->head_id && $filter->option_id) {
                    $filtersArray[] = $filter->head_id . '=>' . $filter->option_id;
                }
            }
            
            $doc->filter = $filterString;
            $doc->filters_list = $doc_filters;
            $doc->filters = $filtersArray;
            $doc->absolute_path = $doc->path ? asset($doc->path) : asset("images/noprofile.jpg");
            
            return response()->json([
                'status' => 'Ok',
                'status_code' => '200',
                'data' => $doc
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'Failed',
                'status_code' => '300',
                'message' => 'Failed to fetch document: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Update a Document and its associated filters
     */
    public function update(Request $request, $id = null)
    {
        try {
            $tenant = $this->resolveTenant($request);
            $conn = $tenant['conn'];
            $user_id = $tenant['uid'];
            
            $doc_id = $id ?? $request->input('id') ?? $request->get('id');
            if (!$doc_id) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'Document ID is required!'
                ]);
            }
            
            $doc = DB::connection($conn)->table('doc_upload')->where('id', $doc_id)->first();
            if (!$doc) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'Document not found!'
                ]);
            }
            
            $nameInput = $request->input('name');
            if (!$nameInput) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'Document Name is required!'
                ]);
            }
            
            // Check if there is an uploaded file or base64 data
            $fileInput = $request->file('file') ?? $request->file('img') ?? $request->file('document') ?? $request->file('attachment');
            $base64Input = $request->post('file') ?? $request->post('img') ?? $request->post('document') ?? $request->post('attachment');
            
            return DB::transaction(function () use ($conn, $doc_id, $doc, $user_id, $request, $fileInput, $base64Input, $nameInput) {
                $imagePath = $doc->path;
                
                // Process file if present
                if ($fileInput) {
                    // Delete old file if it exists
                    if ($doc->path && File::exists(public_path($doc->path))) {
                        File::delete(public_path($doc->path));
                    }
                    
                    $dir = public_path('images/app_images/' . $conn . '/documents');
                    if (!File::exists($dir)) {
                        File::makeDirectory($dir, 0777, true, true);
                    }
                    $imageName = time() . rand(10000, 1000000) . '.' . $fileInput->getClientOriginalExtension();
                    $fileInput->move($dir, $imageName);
                    $imagePath = "images/app_images/" . $conn . "/documents/" . $imageName;
                }
                // Process base64 if present
                elseif ($base64Input && preg_match('/^data:[\w\/\.\-]+;base64,/', $base64Input)) {
                    $base64Data = substr($base64Input, strpos($base64Input, ',') + 1);
                    $decoded = base64_decode($base64Data);
                    if ($decoded !== false) {
                        // Delete old file if it exists
                        if ($doc->path && File::exists(public_path($doc->path))) {
                            File::delete(public_path($doc->path));
                        }
                        
                        $extension = 'pdf';
                        if (strpos($base64Input, 'image/jpeg') !== false || strpos($base64Input, 'image/jpg') !== false) {
                            $extension = 'jpg';
                        } elseif (strpos($base64Input, 'image/png') !== false) {
                            $extension = 'png';
                        } elseif (strpos($base64Input, 'image/gif') !== false) {
                            $extension = 'gif';
                        }
                        
                        $dir = public_path('images/app_images/' . $conn . '/documents');
                        if (!File::exists($dir)) {
                            File::makeDirectory($dir, 0777, true, true);
                        }
                        $imageName = time() . rand(10000, 1000000) . '.' . $extension;
                        file_put_contents($dir . '/' . $imageName, $decoded);
                        $imagePath = "images/app_images/" . $conn . "/documents/" . $imageName;
                    }
                }
                
                // Update the document record
                $dateVal = $request->input('date', $doc->date);
                $particularVal = $request->input('particular', $doc->particular);
                $remarkVal = $request->input('remark', $doc->remark);
                
                DB::connection($conn)->table('doc_upload')->where('id', $doc_id)->update([
                    'name' => $nameInput,
                    'date' => $dateVal,
                    'particular' => $particularVal,
                    'remark' => $remarkVal,
                    'path' => $imagePath
                ]);
                
                // Update filters/metadata
                DB::connection($conn)->table('doc_meta')->where('doc_id', $doc_id)->delete();
                
                $filtersInput = $request->input('filters');
                if ($filtersInput) {
                    $decodedFilters = is_array($filtersInput) ? $filtersInput : json_decode($filtersInput, true);
                    if (is_array($decodedFilters)) {
                        $meta = [];
                        foreach ($decodedFilters as $filt) {
                            if (!empty($filt) && strpos($filt, '=>') !== false) {
                                [$head, $option] = explode('=>', $filt, 2);
                                $meta[] = [
                                    'doc_id' => $doc_id,
                                    'head_id' => $head,
                                    'option_id' => $option,
                                    'structure' => $filt
                                ];
                            }
                        }
                        if (count($meta) > 0) {
                            DB::connection($conn)->table('doc_meta')->insert($meta);
                        }
                    }
                }
                
                addActivity($doc_id, 'doc_upload', "Document Updated via API - " . $nameInput, 11, $user_id, $conn);
                
                $updatedDoc = DB::connection($conn)->table('doc_upload')->where('id', $doc_id)->first();
                $updatedDoc->path = asset($updatedDoc->path);
                
                return response()->json([
                    'status' => 'Ok',
                    'status_code' => '200',
                    'message' => 'Document Updated Successfully!',
                    'data' => $updatedDoc
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'Failed',
                'status_code' => '300',
                'message' => 'Failed to update document: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Delete Document
     */
    public function destroy(Request $request, $id = null)
    {
        try {
            $tenant = $this->resolveTenant($request);
            $conn = $tenant['conn'];
            $user_id = $tenant['uid'];
            
            $doc_id = $id ?? $request->input('id') ?? $request->get('id');
            if (!$doc_id) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'Document ID is required!'
                ]);
            }
            
            $doc = DB::connection($conn)->table('doc_upload')->where('id', $doc_id)->first();
            if (!$doc) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'Document not found!'
                ]);
            }
            
            return DB::transaction(function () use ($conn, $doc_id, $doc, $user_id) {
                if ($doc->path && File::exists(public_path($doc->path))) {
                    File::delete(public_path($doc->path));
                }
                
                DB::connection($conn)->table('doc_upload')->where('id', $doc_id)->delete();
                DB::connection($conn)->table('doc_meta')->where('doc_id', $doc_id)->delete();
                
                addActivity(0, 'doc_upload', "Document Deleted via API - " . $doc->name, 11, $user_id, $conn);
                
                return response()->json([
                    'status' => 'Ok',
                    'status_code' => '200',
                    'message' => 'Document Deleted Successfully!'
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'Failed',
                'status_code' => '300',
                'message' => 'Failed to delete document: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Export Documents List Report as PDF
     */
    public function exportPdf(Request $request)
    {
        try {
            $tenant = $this->resolveTenant($request);
            $conn = $tenant['conn'];
            
            $type = $request->get('type', 'approved');
            $status = ($type === 'pending') ? 'Pending' : 'Approved';
            
            $query = DB::connection($conn)->table('doc_upload')
                ->leftJoin('users', 'users.id', '=', 'doc_upload.created_by')
                ->select('doc_upload.*', 'users.name as creator_name')
                ->where('doc_upload.status', $status)
                ->orderBy('doc_upload.id', 'desc');
            
            $head_id = $request->get('head_id');
            if ($head_id) {
                $subQuery = DB::connection($conn)->table('doc_meta')
                    ->where('head_id', $head_id)
                    ->pluck('doc_id');
                $query->whereIn('doc_upload.id', $subQuery);
            }
            
            $filtersJson = $request->get('filters');
            if ($filtersJson) {
                $filters = is_array($filtersJson) ? $filtersJson : json_decode($filtersJson, true);
                if (is_array($filters) && count($filters) > 0) {
                    $filters_count = count($filters);
                    $subQuery = DB::connection($conn)->table('doc_meta')
                        ->select('doc_id')
                        ->whereIn('structure', $filters)
                        ->groupBy('doc_id')
                        ->havingRaw('COUNT(DISTINCT structure) = ?', [$filters_count])
                        ->pluck('doc_id');
                    $query->whereIn('doc_upload.id', $subQuery);
                }
            }
            
            $docs = $query->get();
            
            foreach ($docs as $doc) {
                $doc_filters = DB::connection($conn)->table('doc_meta')
                    ->leftJoin('doc_head', 'doc_head.id', '=', 'doc_meta.head_id')
                    ->leftJoin('doc_head_option', 'doc_head_option.id', '=', 'doc_meta.option_id')
                    ->where('doc_meta.doc_id', $doc->id)
                    ->select('doc_head.name as head_name', 'doc_head_option.name as option_name')
                    ->get();
                
                $filterString = '';
                $filter_count = 0;
                foreach ($doc_filters as $filter) {
                    $filterString .= "[" . $filter->head_name . " => " . $filter->option_name . "]";
                    $filter_count++;
                    if ($filter_count < count($doc_filters)) {
                        $filterString .= " , ";
                    }
                }
                $doc->filter = $filterString;
            }
            
            $this->size = 0;
            $this->total_no_of_files = 0;
            $this->total_no_of_images = 0;
            $this->total_no_of_pdfs = 0;
            $this->total_no_of_otherFiles = 0;
            
            $this->getDirectoryTree('uploaded_images', $conn);
            $sizeInMB = round($this->size / (1024 * 1024), 2);
            
            $stats = [
                'storage_consume' => $sizeInMB . " MB",
                'total_files' => $this->total_no_of_files,
                'total_images' => $this->total_no_of_images,
                'total_pdfs' => $this->total_no_of_pdfs,
                'total_others' => $this->total_no_of_otherFiles
            ];
            
            $pdf = PDF::loadView('exports.documents', [
                'data' => $docs,
                'connection' => $conn,
                'type' => $type,
                'stats' => $stats
            ])->setPaper('a4', 'portrait');
            
            return $pdf->download('document_management_report.pdf');
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'Failed',
                'status_code' => '300',
                'message' => 'Failed to export PDF: ' . $e->getMessage()
            ], 500);
        }
    }
}
