<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use DataTables;
use Illuminate\Validation\ValidationException;
use App\Http\Requests\Employee\StoreEmployeeRequest;
use App\Http\Requests\Employee\UpdateEmployeeRequest;
use App\Models\AdvanceSalary;
use App\Models\Employee;
use App\Models\Attachment;
use App\Models\Attendance;
use App\Models\Loan;
use App\Models\Salary;
use App\Models\UserInfo;
use App\Services\AttendanceService;
use App\Services\SalaryService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Yajra\DataTables\Facades\DataTables as FacadesDataTables;

class EmployeeController extends Controller
{
    /**
     * Display a listing of the resource.
     */

     public static function middleware(): array
     {
         return [
             'permission:list-employee|create-employee|edit-employee|delete-employee' => ['only' => ['index', 'store']],
             'permission:create-employee' => ['only' => ['create', 'store']],
             'permission:edit-employee' => ['only' => ['edit', 'update']],
             'permission:delete-employee' => ['only' => ['destroy']],
         ];
     }

     public function index(Request $request)
     {
         if ($request->ajax()) {
            $query = Employee::with('department');
        
            if ($request->has('department_id') && !empty($request->department_id)) {
                $query->where('department_id', $request->department_id);
            }

            if ($request->has('type_id') && !empty($request->type_id)) {
                $query->where('type_id', $request->type_id);
            }
    
            $data = $query->latest()->get();

             return FacadesDataTables::of($data)
                ->addColumn('department_name', function ($data) {
                    return $data->department_id ? $data->department->name : 'N/A';
                })
                ->addColumn('type_name', function ($data) {
                    return $data->type->id ? $data->type->name : 'N/A';
                })
                ->addColumn('action', function($row){
                    $editUrl = route('employees.edit', $row->id);
                    $attdUrl = route('employees.attd', $row->id);
                    $deleteUrl = route('employees.destroy', $row->id);

                    $btn = '<a href="'.$editUrl.'" class="edit btn btn-primary btn-sm mr-2"><i class="fas fa-edit" aria-hidden="true"></i></a>';
                    $btn .= '<button onclick="deleteData(\'' . $row->id . '\', \'/employees/\', \'GET\')" class="delete btn btn-danger btn-sm mr-2"><i class="fas fa-trash"></i></button>';
                    
                    // $btn .= '<a href="'.$attdUrl.'" class="btn btn-warning btn-sm">Payroll Information</a>';
                    $btn .= '<button data-toggle="modal" data-target="#exampleModal" class="btn btn-sm btn-warning btn-show-employee" data-employee-id="' . $row->id . '" data-employee-name="' . $row->name . '">Payroll</button>';
                    return $btn;
                })
                 ->rawColumns(['action'])
                 ->make(true);
         }
         return view('pages.employees.index');
     }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('pages.employees.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEmployeeRequest $request)
    {
        try {

            $validatedData = $request->validated();

            $employee = Employee::create($request->all());

            // Handle Profile Picture
            if ($request->hasFile('profile_picture')) {
                $profilePicture = $request->file('profile_picture');
                $profilePicturePath = $profilePicture->store('profile_pictures');

                $employee->attachments()->create([
                    'file_name' => $profilePicture->getClientOriginalName(),
                    'file_path' => $profilePicturePath,
                    'file_type' => $profilePicture->getClientMimeType(),
                    'file_size' => $profilePicture->getSize(),
                ]);
            }

            // Handle Resume
            if ($request->hasFile('resume')) {
                $resume = $request->file('resume');
                $resumePath = $resume->store('resumes');

                $employee->attachments()->create([
                    'file_name' => $resume->getClientOriginalName(),
                    'file_path' => $resumePath,
                    'file_type' => $resume->getClientMimeType(),
                    'file_size' => $resume->getSize(),
                ]);
            }

            // Handle Documents
            if ($request->hasFile('documents')) {
                foreach ($request->file('documents') as $document) {
                    $documentPath = $document->store('documents');

                    $employee->attachments()->create([
                        'file_name' => $document->getClientOriginalName(),
                        'file_path' => $documentPath,
                        'file_type' => $document->getClientMimeType(),
                        'file_size' => $document->getSize(),
                    ]);
                }
            }

            return response()->json([
                'message' => 'Employee created successfully',
            ], 200);

        } catch (ValidationException $e) {

            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Failed to create employee',
                'error' => $e->getMessage(),
            ], 500);

        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $employee = Employee::with('attachments')->findOrFail($id);
        return view('pages.employees.edit',compact('employee'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEmployeeRequest $request, $id)
    {
        $request->request->remove('profile_picture');
        $request->request->remove('resume');
        $request->request->remove('documents');
        try {
            $employee = Employee::findOrFail($id);
            $validatedData = $request->validated();

            $employee->update($request->all());

            // Handle Profile Picture
            if ($request->hasFile('profile_picture')) {
                $oldProfilePicture = $employee->attachments()->where('file_type', 'like', 'image%')->first();
                if ($oldProfilePicture) {
                    Storage::delete($oldProfilePicture->file_path);
                    $oldProfilePicture->delete();
                }

                $profilePicture = $request->file('profile_picture');
                $profilePicturePath = $profilePicture->store('profile_pictures');

                $employee->attachments()->create([
                    'file_name' => $profilePicture->getClientOriginalName(),
                    'file_path' => $profilePicturePath,
                    'file_type' => $profilePicture->getClientMimeType(),
                    'file_size' => $profilePicture->getSize(),
                ]);
            }

            // Handle Resume
            if ($request->hasFile('resume')) {
                // Delete the old resume if exists
                $oldResume = $employee->attachments()->where('file_type', 'application/pdf')->first();
                if ($oldResume) {
                    Storage::delete($oldResume->file_path);
                    $oldResume->delete();
                }

                $resume = $request->file('resume');
                $resumePath = $resume->store('resumes');

                $employee->attachments()->create([
                    'file_name' => $resume->getClientOriginalName(),
                    'file_path' => $resumePath,
                    'file_type' => $resume->getClientMimeType(),
                    'file_size' => $resume->getSize(),
                ]);
            }


            // Handle Documents
            if ($request->hasFile('documents')) {
                // Delete the old documents if exists
                $oldDocuments = $employee->attachments()->where('file_type', '!=', 'application/pdf')->where('file_type', '!=', 'image%')->get();
                foreach ($oldDocuments as $oldDocument) {
                    Storage::delete($oldDocument->file_path);
                    $oldDocument->delete();
                }

                foreach ($request->file('documents') as $document) {
                    $documentPath = $document->store('documents');

                    $employee->attachments()->create([
                        'file_name' => $document->getClientOriginalName(),
                        'file_path' => $documentPath,
                        'file_type' => $document->getClientMimeType(),
                        'file_size' => $document->getSize(),
                    ]);
                }
            }


            return redirect()->route('employees.index')->with('success', 'Updated successfully.');
        } catch (ValidationException $e) {
            return redirect()->route('employees.index')->with('error', 'Validation failed');
        } catch (\Exception $e) {
            dd($e);
            return redirect()->route('employees.index')->with('error', 'ailed to update employee');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $employee = Employee::findOrFail($id);
            $employee->delete();
    
            return response()->json(['message' => 'Employee deleted successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to delete employee', 'error' => $e->getMessage()], 500);
        }
    }

    public function download($id)
    {
        $attachment = Attachment::findOrFail($id);
        $pathToFile = storage_path('app/' . $attachment->file_path);

        return response()->download($pathToFile, $attachment->file_name);
    }

   
    public function payroll(Request $request, $employeeId)
    {
        $employee = Employee::findOrFail($employeeId);

        $currentMonth = intval($request->month);
        $timestamp = mktime(0, 0, 0, $currentMonth, 1, $request->year);
        $month_name = date("F", $timestamp);

        $period = $request->duration;

        $baseDate = Carbon::createFromDate($request->year, $currentMonth, 1);

        if ($period === 'first_half') {
            $startDate = $baseDate->copy()->startOfMonth();
            $endDate = $baseDate->copy()->startOfMonth()->addDays(14);
        } elseif ($period === 'second_half') {
            $startDate = $baseDate->copy()->startOfMonth()->addDays(15);
            $endDate = $baseDate->copy()->endOfMonth();
        } else {
            // full_month
            $startDate = $baseDate->copy()->startOfMonth();
            $endDate = $baseDate->copy()->endOfMonth();
        }

        // $startDate = Carbon::create($request->year, $request->month, 1)->startOfDay();
        // $endDate = Carbon::create($request->year, $request->month, 1)->endOfMonth()->endOfDay();

        $salary = Salary::where('employee_id' , intval ($employeeId))->where('month', $request->month)
        ->where('year', $request->year)->where('period', $request->duration)->first();
        
       
        if(!$salary){
            return redirect()->back()->with('error', 'Salary Not Found');
        }
        
        $processor = new AttendanceService($employee);
        $attendance = $processor->processAttendance($startDate, $endDate);
        
        if (!$attendance) {
            return redirect()->back()->with('error', 'Unable to process attendance');
        }

        $salaryCalculator = new SalaryService($employee, $attendance,$period,intval($request->month));
        $salaryComponent = $salaryCalculator->calculateSalary();
        
        $salaryData = json_decode($salary->salary_data);
        $hasSalaryData = $salary->salary_data && $salaryData && !empty($salaryData);
        
        
        $salaryData = json_decode($salary->salary_data);
$hasSalaryData = $salary->salary_data && $salaryData && !empty($salaryData) && is_iterable($salaryData);

if ($hasSalaryData) {
    $result = collect($salaryData);
} else {
    $result = collect(array_merge($attendance, $salaryComponent));
}
        
        
        return view('pages.employees.payroll', compact('attendance', 'result', 'salary'));
        
    }

    public function calculateSalaryForAdvance($employeeId)
    {
        $employee = Employee::findOrFail($employeeId);


        // $startDate = Carbon::create(Carbon::now()->year, Carbon::now()->month, 1)->startOfDay();
        // $endDate = Carbon::create(Carbon::now()->year, Carbon::now()->month, 1)->endOfMonth()->endOfDay();

        // $processor = new AttendanceService($employee);
        // $result = $processor->processAttendance($startDate, $endDate);

        // $salaryCalculator = new SalaryService($employee, $result);
        // $salaryComponents = $salaryCalculator->calculateSalary();

        return (object) ['actualSalaryEarned' => $employee->salary];

    }

}