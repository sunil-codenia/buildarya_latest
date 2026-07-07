<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

use Illuminate\Http\Request;

class DocumentController extends Controller
{
    protected $size = 0;
    protected $total_no_of_files = 0;
    protected $total_no_of_docs = 0;
    protected $total_no_of_images = 0;
    protected $total_no_of_pdfs = 0;
    protected $total_no_of_otherFiles = 0;
    public function structure(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $doc_head = DB::connection($user_db_conn_name)->table('doc_head')->get();

        $directories = Storage::disk('real_public')->directories();
        $director = $this->getDirectoryTree('uploaded_images', $user_db_conn_name);


        $directories = $this->organizeFilesByDate($director);
        $storage_comsume = $this->size;
        $files_count = $this->total_no_of_files;
        $images_count = $this->total_no_of_images;
        $pdfs_count = $this->total_no_of_pdfs;
        $other_count = $this->total_no_of_otherFiles;
        $doc_array = DB::connection($user_db_conn_name)->table('doc_upload')->where('status','Approved')->get();
        $pending_doc_array = DB::connection($user_db_conn_name)->table('doc_upload')->where('status','Pending')->get();
        $docs = array();
        $pending_docs = array();
        foreach ($doc_array as $doc) {
            $doc_filters = DB::connection($user_db_conn_name)->table('doc_meta')->leftJoin('doc_head', 'doc_head.id', '=', 'doc_meta.head_id')->leftJoin('doc_head_option', 'doc_head_option.id', '=', 'doc_meta.option_id')->where('doc_meta.doc_id', $doc->id)->select('doc_head.name as head_name', 'doc_head_option.name as option_name')->get();
            $original_filters = DB::connection($user_db_conn_name)->table('doc_meta')->where('doc_id',$doc->id)->get();

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
            $doc->original_filter = json_encode($original_filters);
            array_push($docs, $doc);
        }
        foreach ($pending_doc_array as $doc) {
            $doc_filters = DB::connection($user_db_conn_name)->table('doc_meta')->leftJoin('doc_head', 'doc_head.id', '=', 'doc_meta.head_id')->leftJoin('doc_head_option', 'doc_head_option.id', '=', 'doc_meta.option_id')->where('doc_meta.doc_id', $doc->id)->select('doc_head.name as head_name', 'doc_head_option.name as option_name')->get();
            $original_filters = DB::connection($user_db_conn_name)->table('doc_meta')->where('doc_id',$doc->id)->get();

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
            $doc->original_filter = json_encode($original_filters);
            array_push($pending_docs, $doc);
        }
        $edit_perm = checkmodulepermission(11, 'can_edit') == 1;
        $delete_perm = checkmodulepermission(11, 'can_delete') == 1;
        $certify_perm = checkmodulepermission(11, 'can_certify') == 1;


        return view('layouts.doc.file_structure', compact(['directories', 'doc_head', 'storage_comsume', 'files_count', 'images_count', 'pdfs_count', 'other_count', 'docs','pending_docs', 'edit_perm', 'delete_perm','certify_perm']));
    }

    function organizeFilesByDate($data, $parentPath = '')
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

                $path = $parentPath . $year . '/' . $month;

                // Check if the year directory exists
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

                // Check if the month directory exists within the year directory
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

                // Add the file to the appropriate month directory
                $organizedData[$yearIndex]['children'][$monthIndex]['children'][] = $item;
            }
        }

        return $organizedData;
    }


    function getDirectoryTree($disk, $path = '')
    {
        $tree = [];


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
            $file_ext = $filenamedet[count($filenamedet) - 1];
            if (strtolower($file_ext) == 'pdf') {
                $this->total_no_of_pdfs++;
            } else if (strtolower($file_ext) == 'jpg' || strtolower($file_ext) == 'jpeg' || strtolower($file_ext) == 'png' || strtolower($file_ext) == 'svg' || strtolower($file_ext) == 'gif' || strtolower($file_ext) == 'webp') {
                $this->total_no_of_images++;
            } else {
                $this->total_no_of_otherFiles++;
            }
            $this->total_no_of_files++;
        }
        return $tree;
    }

    public function adddochead(Request $request)
    {
        $name = $request->input('name');
        $data = ['name' => $name];
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        try {
            $id = DB::connection($user_db_conn_name)->table('doc_head')->insertGetId($data);
            addActivity($id, 'doc_head', "New Document Head Created ", 11);
            return redirect('/file-structure')
                ->with('success', 'Document Head Created successfully!');
        } catch (\Exception $e) {
            if ($e->getCode() == 23000) {
                return redirect('/file-structure')
                    ->with('error', 'Document Head Already Exists!');
            } else {
                return redirect('/file-structure')
                    ->with('error', 'Error While Creating Document Head!');
            }
        }
    }

    public function updatedochead(Request $request)
    {
        $id = $request->input('id');
        $name = $request->input('name');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        try {
            DB::connection($user_db_conn_name)->table('doc_head')->where('id', $id)->update(['name' => $name]);
            addActivity($id, 'doc_head', "Document Head Data Updated  ", 11);

            return redirect('/file-structure')
                ->with('success', 'Document Head Updated Successfully!');;
        } catch (\Exception $e) {
            if ($e->getCode() == 23000) {
                return redirect('/file-structure')
                    ->with('error', 'Document Head Already Exists!');
            } else {
                return redirect('/file-structure')
                    ->with('error', 'Error While Updating Document Head!');
            }
        }
    }
    public function updatedocheadoption(Request $request)
    {
        $id = $request->input('id');
        $name = $request->input('name');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        try {
            DB::connection($user_db_conn_name)->table('doc_head_option')->where('id', $id)->update(['name' => $name]);
            addActivity($id, 'doc_head_option', "Document Head Option Updated  ", 11);
            return redirect('/file-structure')
                ->with('success', 'Document Head Option Updated Successfully!');;
        } catch (\Exception $e) {

            return redirect('/file-structure')
                ->with('error', 'Error While Updating Document Head Option!');
        }
    }


    public function delete_doc_head(Request $request)
    {
        $id = $request->get('id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $doc_head = DB::connection($user_db_conn_name)->table('doc_head')->where('id', '=', $id)->get()[0]->name;
        $check = DB::connection($user_db_conn_name)->table('doc_head_option')->where('head_id', '=', $id)->get();
        if (Count($check) > 0) {
            return redirect('/file-structure')
                ->with('error', 'Document Head Is In Use!');
        } else {
            DB::connection($user_db_conn_name)->table('doc_head')->where('id', '=', $id)->delete();
            addActivity(0, 'doc_head', "Document Head Deleted - " . $doc_head, 11);

            return redirect('/file-structure')
                ->with('success', 'Document Head Deleted Successfully!');
        }
    }
    public function delete_doc_head_option(Request $request)
    {
        $id = $request->get('id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $doc_head_op = DB::connection($user_db_conn_name)->table('doc_head_option')->where('id', '=', $id)->get()[0]->name;

        DB::connection($user_db_conn_name)->table('doc_head_option')->where('id', '=', $id)->delete();
        addActivity(0, 'doc_head', "Document Head Option Deleted - " . $doc_head_op, 11);

        return redirect('/file-structure')
            ->with('success', 'Document Head Option Deleted Successfully!');
    }

    public function adddocheadoption(Request $request)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'head_id' => 'required',
            'name' => 'required|array',
            'name.*' => 'required|string',
        ]);

        if ($validator->fails()) {
            return redirect('/file-structure')
                ->withErrors($validator)
                ->with('error', 'Validation Error: Invalid Document Option payload.');
        }

        $id = $request->input('head_id');
        $name = $request->input('name');
        $length = count($name);
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');

        try {
            for ($i = 0; $i < $length; $i++) {
                $data = ['head_id' => $id, 'name' => $name[$i]];

                $doc_id = DB::connection($user_db_conn_name)->table('doc_head_option')->insertGetId($data);
                addActivity($doc_id, 'doc_head_option', "New Document Head Option Created ", 11);
            }



            return redirect('/file-structure')
                ->with('success', 'Document Head Options Created successfully!');
        } catch (\Exception $e) {

            return redirect('/file-structure')
                ->with('error', 'Error While Creating Document Head Options!');
        }
    }

    public function fetchLinkedData(Request $request)
    {
        $table = $request->get('table');
        $path = $request->get('path');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');

        $not_found = [
            'status' => 'Not Found',
            'status_code' => '404',
        ];


        if ($table == 'expense') {
            $data =  DB::connection($user_db_conn_name)->table('expenses')->where('image', '=', $path)->get();
            if (count($data) > 0) {

                $resp = [
                    'Type' => 'Expense',
                    'Date' => $data[0]->date,
                    'Site' => getSiteDetailsById($data[0]->site_id)->name,
                    'User' => getUserDetailsById($data[0]->user_id)->name,
                    'Party' => $data[0]->party_type == 'expense' ? DB::connection($user_db_conn_name)->table('expense_party')->where('id', $data[0]->party_id)->get()[0]->name : DB::connection($user_db_conn_name)->table('bills_party')->where('id', $data[0]->party_id)->get()[0]->name,
                    'Party Type' => $data[0]->party_type,
                    'Head' => DB::connection($user_db_conn_name)->table('expense_head')->where('id', $data[0]->head_id)->get()[0]->name,
                    'Particular' => $data[0]->particular,
                    'Amount' => $data[0]->amount,
                    'Status' => $data[0]->status,
                    'Location' => $data[0]->location,
                    'Remark' => $data[0]->remark,
                ];
                $res = [
                    'status' => 'OK',
                    'status_code' => '200',
                    'data' => $resp
                ];
                return json_encode($res);
            } else {
                return json_encode($not_found);
            }
        } else if ($table == 'material') {
            $data =  DB::connection($user_db_conn_name)->table('material_entry')
                ->where('image', 'LIKE', '%' . $path . '%')
                ->orWhere('image2', '=', $path)
                ->orWhere('image3', '=', $path)
                ->orWhere('image4', '=', $path)
                ->orWhere('image5', '=', $path)
                ->get();
            if (count($data) > 0) {
                $resp = [
                    'Type' => 'Material',
                    'Date' => $data[0]->date,
                    'Site' => getSiteDetailsById($data[0]->site_id)->name,
                    'User' => getUserDetailsById($data[0]->user_id)->name,
                    'Supplier' => DB::connection($user_db_conn_name)->table('material_supplier')->where('id', $data[0]->supplier)->get()[0]->name,
                    'Material' => DB::connection($user_db_conn_name)->table('materials')->where('id', $data[0]->head_id)->get()[0]->name,
                    'Unit' => DB::connection($user_db_conn_name)->table('units')->where('id', $data[0]->unit)->get()[0]->name,
                    'Qty' => $data[0]->qty,
                    'Rate' => $data[0]->rate,
                    'Tax' => $data[0]->tax,
                    'Amount' => $data[0]->amount,
                    'Bill No.' => $data[0]->bill_no,
                    'Status' => $data[0]->status,
                    'Vehicle' => $data[0]->vehical,
                    'Location' => $data[0]->location,
                    'Remark' => $data[0]->remark,
                ];
                $res = [
                    'status' => 'OK',
                    'status_code' => '200',
                    'data' => $resp
                ];
                return json_encode($res);
            } else {
                return json_encode($not_found);
            }
        } else if ($table == 'machinery_doc') {
            $data =  DB::connection($user_db_conn_name)->table('machinery_documents')->where('attachment', '=', $path)->get();
            if (count($data) > 0) {

                $resp = [
                    'Type' => 'Machinery Document',
                    'Date' => $data[0]->create_date,
                    'Machine' => DB::connection($user_db_conn_name)->table('machinery_details')->where('id', $data[0]->machinery_id)->get()[0]->name,
                    'Doc Name' => $data[0]->name,
                    'Issue Date' => $data[0]->issue_date,
                    'End Date' => $data[0]->end_date,
                    'Remark' => $data[0]->remark,
                ];
                $res = [
                    'status' => 'OK',
                    'status_code' => '200',
                    'data' => $resp
                ];
                return json_encode($res);
            } else {
                return json_encode($not_found);
            }
        } else if ($table == 'machinery_service') {
            $data =  DB::connection($user_db_conn_name)->table('machinery_services')->where('image1', '=', $path)->orWhere('image2', '=', $path)->orWhere('image3', '=', $path)->orWhere('image4', '=', $path)->orWhere('image5', '=', $path)->get();
            if (count($data) > 0) {
                $resp = [
                    'Type' => 'Machinery Service',
                    'Date' => $data[0]->create_date,
                    'Machine' => DB::connection($user_db_conn_name)->table('machinery_details')->where('id', $data[0]->machinery_id)->get()[0]->name,
                    'Maintainence Items' => $data[0]->maintainence_item,
                    'User' => getUserDetailsById($data[0]->user_id)->name,
                    'Next Service' => $data[0]->next_service_on,
                    'Remark' => $data[0]->remark,
                ];
                $res = [
                    'status' => 'OK',
                    'status_code' => '200',
                    'data' => $resp
                ];
                return json_encode($res);
            } else {
                return json_encode($not_found);
            }
        } else if ($table == 'paymentvoucher') {
            $data =  DB::connection($user_db_conn_name)->table('payment_vouchers')->where('image', '=', $path)->orWhere('payment_image', '=', $path)->get();
            if (count($data) > 0) {
                $resp = [
                    'Type' => 'Payment Voucher',
                    'Date' => $data[0]->date,
                    'Voucher No.' => $data[0]->voucher_no,
                    'Site' => getSiteDetailsById($data[0]->site_id)->name,
                    'User' => getUserDetailsById($data[0]->created_by)->name,
                    'Party' => getPaymentVoucherPartyInfo($data[0]->party_id, $data[0]->party_type)['party_status']->name,
                    'Party Type' => $data[0]->party_type,
                    'Company' => DB::connection($user_db_conn_name)->table('sales_company')->where('id', $data[0]->company_id)->get()[0]->name,
                    'Amount' => $data[0]->amount,
                    'Payment Details' => $data[0]->payment_details,
                    'Payment Date' => $data[0]->payment_date,
                    'Amount' => $data[0]->amount,
                    'Status' => $data[0]->status,
                    'Remark' => $data[0]->remark,
                ];
                $res = [
                    'status' => 'OK',
                    'status_code' => '200',
                    'data' => $resp
                ];
                return json_encode($res);
            } else {
                return json_encode($not_found);
            }
        } else if ($table == 'invoices') {

            $data =  DB::connection($user_db_conn_name)->table('sales_invoice')->where('image', '=', $path)->orWhere('pdf', '=', $path)->get();
            $data2 =  DB::connection($user_db_conn_name)->table('sales_manage_invoice')->where('image', '=', $path)->orWhere('pdf', '=', $path)->get();
            if (count($data) > 0) {
                $resp = [
                    'Type' => 'Sales Invoice',
                    'Date' => $data[0]->date,
                    'Invoice No.' => $data[0]->invoice_no,
                    'Project' => DB::connection($user_db_conn_name)->table('sales_project')->where('id', $data[0]->project_id)->get()[0]->name,
                    'Company' => DB::connection($user_db_conn_name)->table('sales_company')->where('id', $data[0]->company_id)->get()[0]->name,
                    'Party' => DB::connection($user_db_conn_name)->table('sales_party')->where('id', $data[0]->party_id)->get()[0]->name,
                    'Financial Year' => $data[0]->financial_year,
                    'GST Rate' => $data[0]->gst_rate,
                    'Taxable Value' => $data[0]->taxable_value,
                    'Amount' => $data[0]->amount,
                    'Status' => $data[0]->status,
                ];
                $res = [
                    'status' => 'OK',
                    'status_code' => '200',
                    'data' => $resp
                ];
                return json_encode($res);
            } elseif (count($data2) > 0) {
                $resp = [
                    'Type' => 'Sales Invoice Ded/Add',
                    'Date' => $data2[0]->date,
                    'Invoice No.' => DB::connection($user_db_conn_name)->table('sales_invoice')->where('id', $data2[0]->invoice_id)->get()[0]->invoice_no,
                    'Type' => DB::connection($user_db_conn_name)->table('sales_dedadd')->where('id', $data2[0]->type_id)->get()[0]->name,
                    'Amount' => $data2[0]->amount,
                ];
                $res = [
                    'status' => 'OK',
                    'status_code' => '200',
                    'data' => $resp
                ];
                return json_encode($res);
            } else {
                return json_encode($not_found);
            }
        } else if ($table == 'projects') {
            $data =  DB::connection($user_db_conn_name)->table('sales_project')->where('attachment', '=', $path)->get();
            if (count($data) > 0) {
                $resp = [
                    'Type' => 'Sales Project',
                    'Project' => $data[0]->name,
                    'Details' => $data[0]->details,
                    'Status' => $data[0]->status,
                ];
                $res = [
                    'status' => 'OK',
                    'status_code' => '200',
                    'data' => $resp
                ];
                return json_encode($res);
            } else {
                return json_encode($not_found);
            }
        } else if ($table == 'users') {
            $data =  DB::connection($user_db_conn_name)->table('users')->where('image', '=', $path)->get();
            if (count($data) > 0) {
                $resp = [
                    'Type' => 'Users',
                    'Name' => $data[0]->name,
                    'Pan No.' => $data[0]->pan_no,
                    'Contact No.' => $data[0]->contact_no,
                    'Status' => $data[0]->status,
                ];
                $res = [
                    'status' => 'OK',
                    'status_code' => '200',
                    'data' => $resp
                ];
                return json_encode($res);
            } else {
                return json_encode($not_found);
            }
            // users
        } else if ($table == 'documents') {
            $data =  DB::connection($user_db_conn_name)->table('doc_upload')->where('path', '=', $path)->get();
            if (count($data) > 0) {

                $doc_filters = DB::connection($user_db_conn_name)->table('doc_meta')->leftJoin('doc_head', 'doc_head.id', '=', 'doc_meta.head_id')->leftJoin('doc_head_option', 'doc_head_option.id', '=', 'doc_meta.option_id')->where('doc_meta.doc_id', $data[0]->id)->select('doc_head.name as head_name', 'doc_head_option.name as option_name')->get();
                $filterString = '';
                $filter_count = 0;
                foreach ($doc_filters as $filter) {
                    $filterString .= "[" . $filter->head_name . " => " . $filter->option_name . "]";
                    $filter_count++;
                    if ($filter_count < count($doc_filters)) {
                        $filterString .= " , ";
                    }
                }
                $resp = [
                    'Type' => 'Document',
                    'Name' => $data[0]->name,
                    'Filters' => $filterString,
                    'User' => getUserDetailsById($data[0]->created_by)->name,
                    'Date' => $data[0]->create_datetime,
                ];

                $res = [
                    'status' => 'OK',
                    'status_code' => '200',
                    'data' => $resp
                ];
                return json_encode($res);
            } else {
                return json_encode($not_found);
            }
        }
        return json_encode($not_found);
    }
    public function my_doc_upload_file(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        // Get inputs
        $filter = $request->input('filter');
        $names = $request->input('name');
        $dates = $request->input('date');
        $particulars = $request->input('particular');
        $remarks = $request->input('remark');
        $images = $request->file('img');

        $role_id = session()->get('role');
        $status = getInitialEntryStatusByRole($role_id);

        // try {

        for ($i = 0; $i < count($names); $i++) {
            // Set default image path
            $imagePath = "";

            if (isset($images[$i])) {
                // Generate a unique name for the image
                $imageName = time() . rand(10000, 1000000) . '.' . $images[$i]->extension();

                // Move the image to the public path
                $images[$i]->move(public_path('images/app_images/' . $user_db_conn_name . '/documents'), $imageName);

                // Set the image path
                $imagePath = "images/app_images/" . $user_db_conn_name . "/documents/" . $imageName;
            } else {
                return redirect('/file-structure')
                    ->with('error', 'File Is Required!');
            }

            // Prepare data for insertion
            $doc_upload_data = [
                'name' => $names[$i],
                'date' => $dates[$i],
                'particular' => $particulars[$i],
                'remark' => $remarks[$i],
                'path' => $imagePath,
                'status'=>$status,
                'created_by' => $request->session()->get('uid')
            ];
            $doc_id = DB::connection($user_db_conn_name)->table('doc_upload')->insertGetId($doc_upload_data);
            addActivity($doc_id, 'doc_upload', "New Document Uploaded ", 11);

            for ($j = 0; $j < count($filter); $j++) {
                $filt = $filter[$j];
                if ($filt != '') {
                    $filter_explode = explode('=>', $filt);
                    $head = $filter_explode[0];
                    $option = $filter_explode[1];
                    $doc_meta_data = [
                        'doc_id' => $doc_id,
                        'head_id' => $head,
                        'option_id' => $option,
                        'structure' => $filt
                    ];
                    DB::connection($user_db_conn_name)->table('doc_meta')->insert($doc_meta_data);
                }
            }
        }

        // Insert into the database

        return redirect('/file-structure')
            ->with('success', 'Documents Uploaded Successfully!');
        // } catch (\Exception $e) {

        // return redirect('/file-structure')
        //     ->with('error', 'Error While Uploading Documents!');
        // }
    }
    public function update_my_doc_upload_file(Request $request){
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        // Get inputs
        $filter = $request->input('filter');
        $id = $request->input('id');
        $name = $request->input('name');
        $date = $request->input('date');
        $particular = $request->input('particular');
        $remark = $request->input('remark');
        $image = $request->file('img');


        // try {
        $doc = DB::connection($user_db_conn_name)->table('doc_upload')->where('id',$id)->first();
        $old_image = $doc->path;
        if (isset($image)) {
            
        if($image != '' || $image != null){
            // Set default image path
            $imagePath = "";
                // Generate a unique name for the image
                $imageName = time() . rand(10000, 1000000) . '.' . $image->extension();
                // Move the image to the public path
                $image->move(public_path('images/app_images/' . $user_db_conn_name . '/documents'), $imageName);
                // Set the image path
                $imagePath = "images/app_images/" . $user_db_conn_name . "/documents/" . $imageName;
                if (File::exists($old_image)) {
                    File::delete($old_image);
                }
            } else {
                $imagePath = $old_image;              
            }
        }else{
            $imagePath = $old_image;           
        }
            // Prepare data for insertion
            $doc_upload_data = [
                'name' => $name,
                'date' => $date,
                'particular' => $particular,
                'remark' => $remark,
                'path' => $imagePath,
                'created_by' => $request->session()->get('uid')
            ];
             DB::connection($user_db_conn_name)->table('doc_upload')->where('id',$id)->update($doc_upload_data);
            addActivity($id, 'doc_upload', "Document Updated -".$name, 11);
            DB::connection($user_db_conn_name)->table('doc_meta')->where('doc_id',$id)->delete();
            for ($j = 0; $j < count($filter); $j++) {
                $filt = $filter[$j];
                if ($filt != '') {
                    $filter_explode = explode('=>', $filt);
                    $head = $filter_explode[0];
                    $option = $filter_explode[1];
                    $doc_meta_data = [
                        'doc_id' => $id,
                        'head_id' => $head,
                        'option_id' => $option,
                        'structure' => $filt
                    ];
                    DB::connection($user_db_conn_name)->table('doc_meta')->insert($doc_meta_data);
                }
            
        }

        // Insert into the database

        return redirect('/file-structure')
            ->with('success', 'Documents Updated Successfully!');
        // } catch (\Exception $e) {

        // return redirect('/file-structure')
        //     ->with('error', 'Error While Uploading Documents!');
        // }
    }

    public function getDocListByHeadId(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $head_id = $request->get('id');
        if ($head_id == 0) {
            $doc_array = DB::connection($user_db_conn_name)->table('doc_upload')->where('status','Approved')->get();
        } else {
            $subQuery = DB::connection($user_db_conn_name)->table('doc_meta')
                ->select('doc_id')
                ->where('head_id', $head_id);

            // Then, use the subquery in the main query
            $doc_array = DB::connection($user_db_conn_name)->table('doc_upload')
                ->whereIn('id', $subQuery)->where('status','Approved')
                ->get();
        }

        $docs = array();
        foreach ($doc_array as $doc) {
            $doc_filters = DB::connection($user_db_conn_name)->table('doc_meta')->leftJoin('doc_head', 'doc_head.id', '=', 'doc_meta.head_id')->leftJoin('doc_head_option', 'doc_head_option.id', '=', 'doc_meta.option_id')->where('doc_meta.doc_id', $doc->id)->select('doc_head.name as head_name', 'doc_head_option.name as option_name')->get();
            $original_filters = DB::connection($user_db_conn_name)->table('doc_meta')->where('doc_id',$doc->id)->get();
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
             $doc->original_filter = json_encode($original_filters);
            array_push($docs, $doc);
        }
        return json_encode($docs);
    }
    public function searchDocByFilter(Request $request)
    {
        $user_db_conn_name = $request->get('conn');
        $filters = json_decode($request->get('filters'));
        $filters_count = count($filters);
        $subQuery = DB::connection($user_db_conn_name)->table('doc_meta')
            ->select('doc_id')
            ->whereIn('structure', $filters)
            ->groupBy('doc_id')
            ->havingRaw('COUNT(DISTINCT structure) = ?', [$filters_count])
            ->get();
        $doc_ids = array();
        foreach ($subQuery as $sub) {
            array_push($doc_ids, $sub->doc_id);
        }

        // Then, use the subquery in the main query
        $doc_array = DB::connection($user_db_conn_name)->table('doc_upload')
            ->whereIn('id', $doc_ids)->where('status','Approved')
            ->get();

        $docs = array();
        foreach ($doc_array as $doc) {
            $doc_filters = DB::connection($user_db_conn_name)->table('doc_meta')->leftJoin('doc_head', 'doc_head.id', '=', 'doc_meta.head_id')->leftJoin('doc_head_option', 'doc_head_option.id', '=', 'doc_meta.option_id')->where('doc_meta.doc_id', $doc->id)->select('doc_head.name as head_name', 'doc_head_option.name as option_name')->get();
            $original_filters = DB::connection($user_db_conn_name)->table('doc_meta')->where('doc_id',$doc->id)->get();

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
            $doc->original_filter = json_encode($original_filters);
            array_push($docs, $doc);
        }
        return json_encode($docs);
    }


    public function deleteDoc(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $doc_id = $request->get('id');
        try {
            $doc = DB::connection($user_db_conn_name)->table('doc_upload')->where('id', '=', $doc_id)->first();
            if (File::exists($doc->path)) {
                File::delete($doc->path);
            }
            DB::connection($user_db_conn_name)->table('doc_upload')->where('id', '=', $doc_id)->delete();
            DB::connection($user_db_conn_name)->table('doc_meta')->where('doc_id', '=', $doc_id)->delete();
            addActivity(0, 'doc_upload', "Document Deleted - " . $doc->name . ' of dated ' . $doc->date, 11);

            return redirect('/file-structure')
                ->with('success', 'Document Deleted Successfully!');
        } catch (\Exception $e) {
            return redirect('/file-structure')
                ->with('error', 'Error While Deleting Documents!');
        }
    }

    public function approveDoc(Request $request){
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $doc_id = $request->get('id');

        DB::connection($user_db_conn_name)->table('doc_upload')->where('id', '=', $doc_id)->update(['status'=>'Approved']);
        return redirect('/file-structure')
        ->with('success', 'Document Approved Successfully!');
    }


    //     SELECT doc_id
    // FROM doc_meta
    // WHERE structure IN ('1=>3', '3=>2', '4=>5')
    // GROUP BY doc_id
    // HAVING COUNT(DISTINCT structure) = 3;
}
