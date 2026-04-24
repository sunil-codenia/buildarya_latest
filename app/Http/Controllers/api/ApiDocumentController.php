<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class ApiDocumentController extends Controller
{
    /**
     * Get Document Summary (Heads and Options)
     */
    public function summary(Request $request)
    {
        try {
            $heads = DB::table('doc_head')->get();
            foreach ($heads as $head) {
                $head->options = DB::table('doc_head_option')->where('head_id', $head->id)->get();
            }
            return response()->json(['status' => 'Ok', 'data' => $heads]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * List Documents
     */
    public function index(Request $request)
    {
        try {
            $head_id = $request->get('head_id');
            $status = $request->get('status', 'Approved');

            $query = DB::table('doc_upload')
                ->leftJoin('users', 'users.id', '=', 'doc_upload.created_by')
                ->select('doc_upload.*', 'users.name as creator_name')
                ->orderBy('doc_upload.id', 'desc');

            if ($status) {
                $query->where('doc_upload.status', $status);
            }

            if ($head_id) {
                $docIds = DB::table('doc_meta')->where('head_id', $head_id)->pluck('doc_id');
                $query->whereIn('doc_upload.id', $docIds);
            }

            $docs = $query->paginate(20);

            // Fetch filters for each doc
            foreach ($docs->items() as $doc) {
                $doc->filters = DB::table('doc_meta')
                    ->leftJoin('doc_head', 'doc_head.id', '=', 'doc_meta.head_id')
                    ->leftJoin('doc_head_option', 'doc_head_option.id', '=', 'doc_meta.option_id')
                    ->where('doc_meta.doc_id', $doc->id)
                    ->select('doc_head.name as head_name', 'doc_head_option.name as option_name')
                    ->get();
            }

            return response()->json(['status' => 'Ok', 'data' => $docs]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Store New Document
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'date' => 'required|date',
            'file' => 'required|file',
            'filters' => 'required|array', // Array of {head_id, option_id}
        ]);

        try {
            $user = $request->user();
            $conn = config('database.default');
            
            $file = $request->file('file');
            $imageName = time() . rand(10000, 1000000) . '.' . $file->extension();
            $file->move(public_path('images/app_images/' . $conn . '/documents'), $imageName);
            $imagePath = "images/app_images/" . $conn . "/documents/" . $imageName;

            $status = getAppInitialEntryStatusByRole($user->role_id, $conn);

            return DB::transaction(function () use ($request, $imagePath, $status, $user, $conn) {
                $docId = DB::table('doc_upload')->insertGetId([
                    'name' => $request->name,
                    'date' => $request->date,
                    'particular' => $request->particular,
                    'remark' => $request->remark,
                    'path' => $imagePath,
                    'status' => $status,
                    'created_by' => $user->id,
                    'create_datetime' => Carbon::now()
                ]);

                $meta = [];
                foreach ($request->filters as $filter) {
                    $meta[] = [
                        'doc_id' => $docId,
                        'head_id' => $filter['head_id'],
                        'option_id' => $filter['option_id'],
                        'structure' => $filter['head_id'] . "=>" . $filter['option_id']
                    ];
                }
                DB::table('doc_meta')->insert($meta);

                addActivity($docId, 'doc_upload', "New Document Uploaded via API", 11, $user->id, $conn);

                return response()->json(['status' => 'Ok', 'message' => 'Document uploaded successfully', 'id' => $docId]);
            });
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete Document
     */
    public function destroy(Request $request, $id)
    {
        try {
            $user = $request->user();
            $conn = config('database.default');
            $doc = DB::table('doc_upload')->where('id', $id)->first();

            if (!$doc) return response()->json(['status' => 'Failed', 'message' => 'Document not found'], 404);

            return DB::transaction(function () use ($id, $doc, $user, $conn) {
                if (File::exists(public_path($doc->path))) {
                    File::delete(public_path($doc->path));
                }
                DB::table('doc_upload')->where('id', $id)->delete();
                DB::table('doc_meta')->where('doc_id', $id)->delete();

                addActivity($id, 'doc_upload', "Document Deleted via API", 11, $user->id, $conn);
                return response()->json(['status' => 'Ok', 'message' => 'Document deleted successfully']);
            });
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }
}
