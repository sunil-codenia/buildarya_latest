<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ApiContactController extends Controller
{
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

    public function categories(Request $request)
    {
        try {
            $categories = getContactCategories();
            return response()->json([
                'status' => 'Ok',
                'status_code' => '200',
                'data' => $categories
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'Failed',
                'status_code' => '300',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function storeCompany(Request $request)
    {
        try {
            $tenant = $this->resolveTenant($request);
            $conn = $tenant['conn'];
            $user_id = $tenant['uid'];

            $comp_name = $request->input('companyname');
            $contact_person = $request->input('contactperson');
            $mobile = $request->input('mobile');
            $email = $request->input('email');
            $category = $request->input('category');
            
            if (!$comp_name) {
                return response()->json(['status' => 'Failed', 'status_code' => '300', 'message' => 'Company Name is required!']);
            }

            $data = [
                'comp_name' => $comp_name,
                'contact_name' => $contact_person,
                'mobile' => $mobile,
                'email' => $email,
                'category' => $category,
            ];

            $id = DB::connection($conn)->table('contact_profile')->insertGetId($data);
            addActivity($id, 'contact_profile', "New Contact Profile Created via API", 10, $user_id, $conn);

            $newCompany = DB::connection($conn)->table('contact_profile')->where('id', $id)->first();

            return response()->json([
                'status' => 'Ok',
                'status_code' => '200',
                'message' => 'Company Profile Created Successfully!',
                'data' => $newCompany
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'Failed',
                'status_code' => '300',
                'message' => 'Failed to create company: ' . $e->getMessage()
            ], 500);
        }
    }

    public function companies(Request $request)
    {
        try {
            $tenant = $this->resolveTenant($request);
            $conn = $tenant['conn'];
            
            $search = $request->get('search');
            $limit = $request->get('limit', 20);
            
            $query = DB::connection($conn)->table('contact_profile')->orderBy('id', 'desc');
            
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('comp_name', 'like', "%$search%")
                      ->orWhere('contact_name', 'like', "%$search%")
                      ->orWhere('mobile', 'like', "%$search%")
                      ->orWhere('email', 'like', "%$search%")
                      ->orWhere('category', 'like', "%$search%");
                });
            }
            
            $companies = $query->paginate($limit);
            
            $companies->getCollection()->transform(function ($item) use ($conn) {
                $item->no_of_contacts = DB::connection($conn)->table('contact')->where('profile_id', $item->id)->count();
                return $item;
            });
            
            return response()->json([
                'status' => 'Ok',
                'status_code' => '200',
                'data' => $companies
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Failed', 'status_code' => '300', 'message' => $e->getMessage()]);
        }
    }

    public function exportCompaniesCsv(Request $request)
    {
        try {
            $tenant = $this->resolveTenant($request);
            $conn = $tenant['conn'];
            
            $search = $request->get('search');
            $query = DB::connection($conn)->table('contact_profile')->orderBy('id', 'desc');
            
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('comp_name', 'like', "%$search%")
                      ->orWhere('contact_name', 'like', "%$search%")
                      ->orWhere('mobile', 'like', "%$search%")
                      ->orWhere('email', 'like', "%$search%")
                      ->orWhere('category', 'like', "%$search%");
                });
            }
            
            $companies = $query->get();
            
            $csvFileName = 'companies_report.csv';
            $headers = [
                "Content-type"        => "text/csv",
                "Content-Disposition" => "attachment; filename=$csvFileName",
                "Pragma"              => "no-cache",
                "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
                "Expires"             => "0"
            ];

            $callback = function() use($companies, $conn) {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, ['ID', 'Company Name', 'Contact Person', 'Mobile', 'Email', 'Category', 'No Of Contacts']);

                foreach ($companies as $comp) {
                    $no_of_contacts = DB::connection($conn)->table('contact')->where('profile_id', $comp->id)->count();
                    fputcsv($handle, [
                        $comp->id,
                        $comp->comp_name,
                        $comp->contact_name,
                        $comp->mobile,
                        $comp->email,
                        $comp->category,
                        $no_of_contacts
                    ]);
                }
                fclose($handle);
            };

            return response()->stream($callback, 200, $headers);
            
        } catch (\Exception $e) {
            return response()->json(['status' => 'Failed', 'status_code' => '300', 'message' => $e->getMessage()]);
        }
    }

    public function showCompany(Request $request, $id = null)
    {
        try {
            $tenant = $this->resolveTenant($request);
            $conn = $tenant['conn'];
            
            $comp_id = $id ?? $request->get('id') ?? $request->input('id');
            if (!$comp_id) {
                return response()->json(['status' => 'Failed', 'status_code' => '300', 'message' => 'Company ID is required!']);
            }
            
            $company = DB::connection($conn)->table('contact_profile')->where('id', $comp_id)->first();
            if (!$company) {
                return response()->json(['status' => 'Failed', 'status_code' => '300', 'message' => 'Company not found!']);
            }
            
            $contacts = DB::connection($conn)->table('contact')->where('profile_id', $comp_id)->get();
            
            return response()->json([
                'status' => 'Ok',
                'status_code' => '200',
                'data' => [
                    'company' => $company,
                    'contacts_list' => $contacts
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Failed', 'status_code' => '300', 'message' => $e->getMessage()]);
        }
    }

    public function updateCompany(Request $request, $id = null)
    {
        try {
            $tenant = $this->resolveTenant($request);
            $conn = $tenant['conn'];
            $user_id = $tenant['uid'];
            
            $comp_id = $id ?? $request->get('id') ?? $request->input('id');
            if (!$comp_id) {
                return response()->json(['status' => 'Failed', 'status_code' => '300', 'message' => 'Company ID is required!']);
            }
            
            $company = DB::connection($conn)->table('contact_profile')->where('id', $comp_id)->first();
            if (!$company) {
                return response()->json(['status' => 'Failed', 'status_code' => '300', 'message' => 'Company not found!']);
            }
            
            $comp_name = $request->input('company_name') ?? $request->input('companyname') ?? $company->comp_name;
            $contact_name = $request->input('contact_name') ?? $request->input('contactperson') ?? $company->contact_name;
            $mobile = $request->input('mobile') ?? $company->mobile;
            $email = $request->input('email') ?? $company->email;
            $category = $request->input('category') ?? $company->category;
            
            $data = [
                'comp_name' => $comp_name,
                'contact_name' => $contact_name,
                'mobile' => $mobile,
                'email' => $email,
                'category' => $category,
            ];
            
            DB::connection($conn)->table('contact_profile')->where('id', $comp_id)->update($data);
            addActivity($comp_id, 'contact_profile', "Company Profile Updated via API", 10, $user_id, $conn);
            
            $updatedCompany = DB::connection($conn)->table('contact_profile')->where('id', $comp_id)->first();
            
            return response()->json([
                'status' => 'Ok',
                'status_code' => '200',
                'message' => 'Company Profile Updated Successfully!',
                'data' => $updatedCompany
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Failed', 'status_code' => '300', 'message' => $e->getMessage()]);
        }
    }

    public function destroyCompany(Request $request, $id = null)
    {
        try {
            $tenant = $this->resolveTenant($request);
            $conn = $tenant['conn'];
            $user_id = $tenant['uid'];
            
            $comp_id = $id ?? $request->get('id') ?? $request->input('id');
            if (!$comp_id) {
                return response()->json(['status' => 'Failed', 'status_code' => '300', 'message' => 'Company ID is required!']);
            }
            
            $company = DB::connection($conn)->table('contact_profile')->where('id', $comp_id)->first();
            if (!$company) {
                return response()->json(['status' => 'Failed', 'status_code' => '300', 'message' => 'Company not found!']);
            }
            
            DB::connection($conn)->table('contact')->where('profile_id', $comp_id)->delete();
            DB::connection($conn)->table('contact_profile')->where('id', $comp_id)->delete();
            
            addActivity(0, 'contact_profile', "Contact Profile Deleted via API - " . $company->comp_name, 10, $user_id, $conn);
            
            return response()->json([
                'status' => 'Ok',
                'status_code' => '200',
                'message' => 'Company Profile Deleted Successfully!'
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Failed', 'status_code' => '300', 'message' => $e->getMessage()]);
        }
    }

    public function storeCompanyContact(Request $request)
    {
        try {
            $tenant = $this->resolveTenant($request);
            $conn = $tenant['conn'];
            $user_id = $tenant['uid'];

            $profile_id = $request->input('profile_id') ?? $request->input('management_id') ?? $request->input('company_id');
            $name = $request->input('name');
            $phone = $request->input('phone') ?? $request->input('number');
            $email = $request->input('email');
            $position = $request->input('position');

            if (!$profile_id) {
                return response()->json(['status' => 'Failed', 'status_code' => '300', 'message' => 'Company Profile ID (or management_id) is required!']);
            }
            if (!$name) {
                return response()->json(['status' => 'Failed', 'status_code' => '300', 'message' => 'Contact Name is required!']);
            }

            $contactdata = [
                'profile_id' => $profile_id,
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'position' => $position,
            ];

            $id = DB::connection($conn)->table('contact')->insertGetId($contactdata);
            addActivity($id, 'contact', "New Contact Created via API", 10, $user_id, $conn);

            $newContact = DB::connection($conn)->table('contact')->where('id', $id)->first();

            return response()->json([
                'status' => 'Ok',
                'status_code' => '200',
                'message' => 'Contact Added Successfully!',
                'data' => $newContact
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Failed', 'status_code' => '300', 'message' => 'Failed to add contact: ' . $e->getMessage()]);
        }
    }

    public function updateCompanyContact(Request $request, $id = null)
    {
        try {
            $tenant = $this->resolveTenant($request);
            $conn = $tenant['conn'];
            $user_id = $tenant['uid'];

            $contact_id = $id ?? $request->get('id') ?? $request->input('id');
            if (!$contact_id) {
                return response()->json(['status' => 'Failed', 'status_code' => '300', 'message' => 'Contact ID is required!']);
            }

            $contact = DB::connection($conn)->table('contact')->where('id', $contact_id)->first();
            if (!$contact) {
                return response()->json(['status' => 'Failed', 'status_code' => '300', 'message' => 'Contact not found!']);
            }

            $name = $request->input('name') ?? $contact->name;
            $phone = $request->input('phone') ?? $request->input('number') ?? $contact->phone;
            $email = $request->input('email') ?? $contact->email;
            $position = $request->input('position') ?? $contact->position;

            $data = [
                'name' => $name,
                'phone' => $phone,
                'position' => $position,
                'email' => $email
            ];

            DB::connection($conn)->table('contact')->where('id', $contact_id)->update($data);
            addActivity($contact_id, 'contact', "Contact Data Updated via API", 10, $user_id, $conn);

            $updatedContact = DB::connection($conn)->table('contact')->where('id', $contact_id)->first();

            return response()->json([
                'status' => 'Ok',
                'status_code' => '200',
                'message' => 'Contact Updated Successfully!',
                'data' => $updatedContact
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Failed', 'status_code' => '300', 'message' => 'Failed to update contact: ' . $e->getMessage()]);
        }
    }

    public function destroyCompanyContact(Request $request, $id = null)
    {
        try {
            $tenant = $this->resolveTenant($request);
            $conn = $tenant['conn'];
            $user_id = $tenant['uid'];

            $contact_id = $id ?? $request->get('id') ?? $request->input('id');
            if (!$contact_id) {
                return response()->json(['status' => 'Failed', 'status_code' => '300', 'message' => 'Contact ID is required!']);
            }

            $contact = DB::connection($conn)->table('contact')->where('id', $contact_id)->first();
            if (!$contact) {
                return response()->json(['status' => 'Failed', 'status_code' => '300', 'message' => 'Contact not found!']);
            }

            DB::connection($conn)->table('contact')->where('id', $contact_id)->delete();
            addActivity(0, 'contact', "Contact Deleted via API - " . $contact->name, 10, $user_id, $conn);

            return response()->json([
                'status' => 'Ok',
                'status_code' => '200',
                'message' => 'Contact Deleted Successfully!'
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Failed', 'status_code' => '300', 'message' => 'Failed to delete contact: ' . $e->getMessage()]);
        }
    }

    public function index(Request $request)
    {
        try {
            $search = $request->get('search');
            $query = DB::table('contacts')->orderBy('id', 'desc');

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%$search%")
                      ->orWhere('company_name', 'like', "%$search%")
                      ->orWhere('mobile', 'like', "%$search%")
                      ->orWhere('email', 'like', "%$search%");
                });
            }

            $contacts = $query->paginate(20);
            return response()->json(['status' => 'Ok', 'data' => $contacts]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required', 'mobile' => 'required']);

        try {
            $id = DB::table('contacts')->insertGetId([
                'name' => $request->name,
                'company_name' => $request->company_name,
                'mobile' => $request->mobile,
                'email' => $request->email,
                'address' => $request->address,
                'remark' => $request->remark,
                'create_datetime' => Carbon::now()
            ]);

            return response()->json(['status' => 'Ok', 'message' => 'Contact created successfully', 'id' => $id]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            DB::table('contacts')->where('id', $id)->update($request->only(['name', 'company_name', 'mobile', 'email', 'address', 'remark']));
            return response()->json(['status' => 'Ok', 'message' => 'Contact updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            DB::table('contacts')->where('id', $id)->delete();
            return response()->json(['status' => 'Ok', 'message' => 'Contact deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function getCompanyContacts(Request $request, $company_id)
    {
        try {
            $tenant = $this->resolveTenant($request);
            $conn = $tenant['conn'];

            if (!$company_id) {
                return response()->json(['status' => 'Failed', 'status_code' => '300', 'message' => 'Company ID is required!']);
            }

            $company = DB::connection($conn)->table('contact_profile')->where('id', $company_id)->first();
            if (!$company) {
                return response()->json(['status' => 'Failed', 'status_code' => '300', 'message' => 'Company not found!']);
            }

            $contacts = DB::connection($conn)->table('contact')->where('profile_id', $company_id)->get();

            return response()->json([
                'status' => 'Ok',
                'status_code' => '200',
                'data' => [
                    'company' => $company,
                    'contacts_list' => $contacts
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Failed', 'status_code' => '300', 'message' => $e->getMessage()]);
        }
    }

    public function getCompanyContact(Request $request, $company_id, $contact_id)
    {
        try {
            $tenant = $this->resolveTenant($request);
            $conn = $tenant['conn'];

            if (!$company_id) {
                return response()->json(['status' => 'Failed', 'status_code' => '300', 'message' => 'Company ID is required!']);
            }
            if (!$contact_id) {
                return response()->json(['status' => 'Failed', 'status_code' => '300', 'message' => 'Contact ID is required!']);
            }

            $company = DB::connection($conn)->table('contact_profile')->where('id', $company_id)->first();
            if (!$company) {
                return response()->json(['status' => 'Failed', 'status_code' => '300', 'message' => 'Company not found!']);
            }

            $contact = DB::connection($conn)->table('contact')
                ->where('profile_id', $company_id)
                ->where('id', $contact_id)
                ->first();

            if (!$contact) {
                return response()->json(['status' => 'Failed', 'status_code' => '300', 'message' => 'Contact not found for this company!']);
            }

            return response()->json([
                'status' => 'Ok',
                'status_code' => '200',
                'data' => $contact
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Failed', 'status_code' => '300', 'message' => $e->getMessage()]);
        }
    }

    public function storeCompanyScopedContact(Request $request, $company_id)
    {
        try {
            $tenant = $this->resolveTenant($request);
            $conn = $tenant['conn'];
            $user_id = $tenant['uid'];

            if (!$company_id) {
                return response()->json(['status' => 'Failed', 'status_code' => '300', 'message' => 'Company ID is required!']);
            }

            $company = DB::connection($conn)->table('contact_profile')->where('id', $company_id)->first();
            if (!$company) {
                return response()->json(['status' => 'Failed', 'status_code' => '300', 'message' => 'Company not found!']);
            }

            $name = $request->input('name');
            $phone = $request->input('phone') ?? $request->input('number') ?? $request->input('phone_no') ?? $request->input('mobile');
            $email = $request->input('email');
            $position = $request->input('position');

            if (!$name) {
                return response()->json(['status' => 'Failed', 'status_code' => '300', 'message' => 'Contact Name is required!']);
            }
            if (!$phone) {
                return response()->json(['status' => 'Failed', 'status_code' => '300', 'message' => 'Contact Phone is required!']);
            }

            $contactdata = [
                'profile_id' => $company_id,
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'position' => $position,
            ];

            $id = DB::connection($conn)->table('contact')->insertGetId($contactdata);
            addActivity($id, 'contact', "New Contact Created under Company $company_id via API", 10, $user_id, $conn);

            $newContact = DB::connection($conn)->table('contact')->where('id', $id)->first();

            return response()->json([
                'status' => 'Ok',
                'status_code' => '200',
                'message' => 'Contact Added Successfully!',
                'data' => $newContact
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Failed', 'status_code' => '300', 'message' => 'Failed to add contact: ' . $e->getMessage()]);
        }
    }

    public function updateCompanyScopedContact(Request $request, $company_id, $contact_id)
    {
        try {
            $tenant = $this->resolveTenant($request);
            $conn = $tenant['conn'];
            $user_id = $tenant['uid'];

            if (!$company_id) {
                return response()->json(['status' => 'Failed', 'status_code' => '300', 'message' => 'Company ID is required!']);
            }
            if (!$contact_id) {
                return response()->json(['status' => 'Failed', 'status_code' => '300', 'message' => 'Contact ID is required!']);
            }

            $company = DB::connection($conn)->table('contact_profile')->where('id', $company_id)->first();
            if (!$company) {
                return response()->json(['status' => 'Failed', 'status_code' => '300', 'message' => 'Company not found!']);
            }

            $contact = DB::connection($conn)->table('contact')
                ->where('profile_id', $company_id)
                ->where('id', $contact_id)
                ->first();

            if (!$contact) {
                return response()->json(['status' => 'Failed', 'status_code' => '300', 'message' => 'Contact not found for this company!']);
            }

            $name = $request->input('name') ?? $contact->name;
            $phone = $request->input('phone') ?? $request->input('number') ?? $request->input('phone_no') ?? $request->input('mobile') ?? $contact->phone;
            $email = $request->input('email') ?? $contact->email;
            $position = $request->input('position') ?? $contact->position;

            $data = [
                'name' => $name,
                'phone' => $phone,
                'position' => $position,
                'email' => $email
            ];

            DB::connection($conn)->table('contact')->where('id', $contact_id)->update($data);
            addActivity($contact_id, 'contact', "Contact Data Updated under Company $company_id via API", 10, $user_id, $conn);

            $updatedContact = DB::connection($conn)->table('contact')->where('id', $contact_id)->first();

            return response()->json([
                'status' => 'Ok',
                'status_code' => '200',
                'message' => 'Contact Updated Successfully!',
                'data' => $updatedContact
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Failed', 'status_code' => '300', 'message' => 'Failed to update contact: ' . $e->getMessage()]);
        }
    }

    public function destroyCompanyScopedContact(Request $request, $company_id, $contact_id)
    {
        try {
            $tenant = $this->resolveTenant($request);
            $conn = $tenant['conn'];
            $user_id = $tenant['uid'];

            if (!$company_id) {
                return response()->json(['status' => 'Failed', 'status_code' => '300', 'message' => 'Company ID is required!']);
            }
            if (!$contact_id) {
                return response()->json(['status' => 'Failed', 'status_code' => '300', 'message' => 'Contact ID is required!']);
            }

            $company = DB::connection($conn)->table('contact_profile')->where('id', $company_id)->first();
            if (!$company) {
                return response()->json(['status' => 'Failed', 'status_code' => '300', 'message' => 'Company not found!']);
            }

            $contact = DB::connection($conn)->table('contact')
                ->where('profile_id', $company_id)
                ->where('id', $contact_id)
                ->first();

            if (!$contact) {
                return response()->json(['status' => 'Failed', 'status_code' => '300', 'message' => 'Contact not found for this company!']);
            }

            DB::connection($conn)->table('contact')->where('id', $contact_id)->delete();
            addActivity(0, 'contact', "Contact Deleted under Company $company_id via API - " . $contact->name, 10, $user_id, $conn);

            return response()->json([
                'status' => 'Ok',
                'status_code' => '200',
                'message' => 'Contact Deleted Successfully!'
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Failed', 'status_code' => '300', 'message' => 'Failed to delete contact: ' . $e->getMessage()]);
        }
    }
}
