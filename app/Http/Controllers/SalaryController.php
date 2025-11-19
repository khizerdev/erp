<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Http\Requests\Branch\StoreBranchRequest;
use App\Http\Requests\Branch\UpdateBranchRequest;
use App\Models\AdvanceSalary;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Loan;
use App\Models\Salary;
use App\Models\Shift;
use App\Services\AttendanceService;
use App\Services\SalaryService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class SalaryController extends Controller
{

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = Salary::query();

            if ($request->has('department') && $request->department != '') {
                $data->whereHas('employee', function($query) use ($request) {
                    $query->where('department_id', $request->department);
                });
            }

            if ($request->has('month') && $request->month != '') {
                $data->where('month', $request->month);
            }

            if ($request->has('year') && $request->year != '') {
                $data->where('year', $request->year);
            }
            
            if ($request->has('period') && $request->period != '') {
                $data->where('period', $request->period);
            }

            $data = $data->get();

            return DataTables::of($data)
            ->addColumn('employee_name', function ($row) {
                return $row->employee->name;
            })
            ->addColumn('code', function ($row) {
                return $row->employee->code;
            })
            // ->addColumn('overtime', function ($row) {
            //     try {
            //         $processor = new AttendanceService($row->employee);
            //     $result = $processor->processAttendance($row->start_date, Carbon::parse($row->end_date)->addDay()->copy()->subMinute());
            //     $salaryService = new SalaryService($row->employee, $result,$row->period,$row->month);
            //     $salary = $salaryService->calculateSalary($row->employee->id, $row->start_date, $row->end_date, $row->period, $row->month);
            //     // dd(Carbon::parse($row->end_date)->copy()->subMinute());
            //     return 'PKR '.$salary['totalOvertimePay'];
            //     } catch (Exception $e) {
            //         dd($e);
            //     }
                
            // })
            // ->addColumn('late', function ($row) {
            //     try {
            //         $processor = new AttendanceService($row->employee);
            //         $result = $processor->processAttendance($row->start_date, Carbon::parse($row->end_date)->addDay()->copy()->subMinute());
            //         $salaryService = new SalaryService($row->employee, $result,$row->period,$row->month);
            //         $salary = $salaryService->calculateSalary($row->employee->id, $row->start_date, $row->end_date, $row->period, $row->month);
            //         return 'PKR '.floor($salary['lateCutAmount']);
            //     } catch (Exception $e) {
            //         dd($e);
            //     }
                
            // })
            ->addColumn('loan', function ($row) {
                return $row->loan_deducted;
            })
            ->addColumn('advance', function ($row) {
                return $row->advance_deducted;
            })
            ->addColumn('month_year', function ($row) {
                return $row->month. '-' . $row->year;
            })
            // ->addColumn('salary', function ($row) {
            //     $processor = new AttendanceService($row->employee);
            //     $result = $processor->processAttendance($row->start_date, Carbon::parse($row->end_date)->addDay()->copy()->subMinute());
            //     $salaryService = new SalaryService($row->employee, $result,$row->period,$row->month);
            //     $salary = $salaryService->calculateSalary($row->employee->id, $row->start_date, $row->end_date, $row->period, $row->month);
            //     return 'PKR '.floor($salary['actualSalaryEarned']-$row->advance_deducted-$row->loan_deducted);
            // })
            ->addColumn('action', function($row) use($request){
                
                $salaryUrl = route('employees.attd', [
                    'id' => $row->employee->id,
                ]) . '?' . http_build_query([
                    'year' => $row->year,
                    'month' => $row->month,
                    'duration' => $row->period
                ]);
                // $deleteUrl = route('shifts.destroy', $row->id);

                // $btn = '<a href="'.$editUrl.'" class="edit btn btn-primary btn-sm mr-2">Edit</a>';
                $btn = '';
                if($request->user()->hasRole('hr|super-admin')){
                    $btn .= '<a href="'.$salaryUrl.'" class="edit btn btn-primary btn-sm mr-2 mb-1">View</a> ';
                }
                $btn .= '<button onclick="deleteData(\'' . $row->id . '\', \'/salaries/\', \'DELETE\')" class="delete btn btn-danger btn-sm">Delete</button>';
                return $btn;
            })
            ->rawColumns(['action'])
            ->make(true);
        }
        return view('pages.salary.index');
    }

    public function edit($id)
    {
        $shift = Shift::findOrFail($id);
        return view('pages.shifts.edit',compact('shift'));
    }

    public function destroy($id)
    {
        try {
            $salary = Salary::findOrFail($id);
            
            // Revert advance changes
            if ($salary->advance_id) {
                $advance = AdvanceSalary::find($salary->advance_id);
                if ($advance) {
                    $advance->is_paid = 0; // Set back to unpaid
                    $advance->save();
                }
            }
            
            // Revert loan changes
            if ($salary->loan_id && $salary->loan_deducted > 0) {
                $loan = Loan::find($salary->loan_id);
                if ($loan) {
                    $loan->paid -= $loan->month; // Subtract the paid amount
                    $loan->save();
                }
            }
            
            $salary->delete();
        
            return response()->json(['message' => 'Salary deleted successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to delete', 'error' => $e->getMessage()], 500);
        }
    }

    public function generateSalary()
    {
        $departments = Department::all();
        return view('pages.salary.generate-salary', compact('departments'));
    }

    public function processSalaryGeneration(Request $request)
    {
        $currentMonth = intval($request->month);
        $currentYear = intval($request->year);
        $period = $request->period;

        $timestamp = mktime(0, 0, 0, $currentMonth, 1, $currentYear);
        $month_name = date("F", $timestamp);

        $baseDate = Carbon::createFromDate($currentYear, $currentMonth, 1);

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

        $departmentId = $request->input('department_id');

        if($request->generate_for == "department"){
            $employees = Employee::where('department_id', $departmentId)
            ->when($period == "first_half" || $period == "second_half", function($query) {
                return $query->where('salary_duration', 'half_month');
            })
            ->when($period == "full_month", function($query) {
                return $query->where('salary_duration', 'full_month');
            })
            ->get();
        } else {
            $employees = Employee::where('id', $request->employee_id)
            ->when($period == "first_half" || $period == "second_half", function($query) {
                return $query->where('salary_duration', 'half_month');
            })
            ->when($period == "full_month", function($query) {
                return $query->where('salary_duration', 'full_month');
            })
            ->get();
        }
        
        foreach ($employees as $employee) {
            $salary = Salary::where('employee_id' , $employee->id)->where('month', (int) $request->month)
            ->where('year', $currentYear)->where('period' , $period)->first();
            // dd($salary,$employee->id,$request->month,$currentYear,$period);
            if(!$salary) {
                try {
                    
                    $processor = new AttendanceService($employee);
                    $result = $processor->processAttendance($startDate, $endDate);
                    if($result){
                        
                        if (empty(array_filter($result['groupedAttendances'], function ($value) {
                            return !empty($value);
                            // dd("react");
                        }))) {
                        } else {
                            $salaryService = new SalaryService($employee, $result,$period,$currentMonth);
                            $salary = $salaryService->calculateSalary($employee->id, $startDate, $endDate, $period, $currentMonth);
                            
                            $salaryData = array_merge($result,$salary);
                            // dd($salaryData);
            
                            $advance = AdvanceSalary::where('employee_id', $employee->id)->where('is_paid', 0)->latest()->first();
                            
                            $loan = Loan::where('employee_id', $employee->id)->whereColumn('paid', '<', 'amount')->first();
                            $remainingAmount = isset($loan) ? ($loan->amount - $loan->paid) : 0;
                            $loanInstallmentAmount = isset($loan) ? (int) min($loan->month, $remainingAmount) : 0;
            
                            $loanException = $employee->loanExceptions()->where('month', $currentMonth)
                            ->where('year', $currentYear)
                            ->where('salary_duration', $request->period)
                            ->first();
                            
                            DB::transaction(function () use ($loan, $loanException, $employee, $salaryData, $advance, $loanInstallmentAmount, $currentMonth, $currentYear, $period, $startDate, $endDate) {
    
                                $data = [
                                    'employee_id' => $employee->id,
                                    'month' => $currentMonth,
                                    'year' => $currentYear,
                                    'current_salary' => $employee->salary,
                                    'expected_hours' => $salaryData['totalExpectedWorkingHours'],
                                    'normal_hours' => $salaryData['actualHoursWorked'],
                                    'holiday_hours' => $salaryData['totalHolidayHoursWorked'],
                                    'overtime_hours' => ((int) $salaryData['totalOvertimeMinutes'])/60,
                                    'salary_per_hour' => $employee->salary/$salaryData['totalExpectedWorkingHours'],
                                    'holiday_pay_ratio' => $employee->type->holiday_ratio,
                                    'overtime_pay_ratio' => $employee->type->overtime_ratio,
                                    'overtime_hours' => $salaryData['totalOverTimeHoursWorked'],
                                    'holidays' => $employee->type->holidays,
                                    'advance_deducted' => $advance ? $advance->amount : 0,
                                    'period' => $period,
                                    'start_date' => $startDate,
                                    'end_date' => $endDate,
                                    'loan_deducted' => $loan ? ($loanException ? 0 : $loanInstallmentAmount) : 0,
                                    'loan_id' => $loan ? ($loanException ? null : $loan->id) : null,
                                    'advance_id' => $advance ? $advance->id : null,
                                    'salary_data' => json_encode($salaryData),
                                ];
                                
                                Salary::create($data);
                                
                                if ($advance) {
                                    $advance->is_paid = 1;
                                    $advance->save();
                                }
                            
                                if($loan && !$loanException){
                                    $loan->paid += $loan->month;
                                    $loan->save();
                                }
                            });
                        }
                    }
    
                } catch (Exception $e){
                    throw $e;
                }
            }
        }

        return redirect()->route('generate-salary')->with('success', 'Salaries generated successfully.');
    }
    
    public function showByDepartment(Request $request)
    {
    // Validate required parameters
    $request->validate([
        'department_id' => 'required|integer',
        'month' => 'required|integer|between:1,12',
        'year' => 'required|integer|digits:4',
        'period' => 'required'
    ]);

    $departmentId = $request->department_id;
    $currentMonth = intval($request->month);
    $currentYear = intval($request->year);
    $period = $request->period;
    $depart = Department::find($departmentId);

    // Get employees belonging to the specified department
    $employees = Employee::where('department_id', $departmentId)->get();
    
    if ($employees->isEmpty()) {
        return redirect()->back()->with('error', 'No employees found for this department');
    }

    // Set date ranges
    $timestamp = mktime(0, 0, 0, $currentMonth, 1, $currentYear);
    $month_name = date("F", $timestamp);
    $baseDate = Carbon::createFromDate($currentYear, $currentMonth, 1);
    
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

    $results = [];
    
    foreach ($employees as $employee) {
        $salary = Salary::where('employee_id', $employee->id)
            ->where('month', $currentMonth)
            ->where('year', $currentYear)
            ->where('period', $period)
            ->first();
        
        if (!$salary) {
            continue; // Skip employees without salary records
        }
        
        $processor = new AttendanceService($employee);
        $attendance = $processor->processAttendance($startDate, $endDate);
        
        if (!$attendance) {
            continue; // Skip employees with attendance processing issues
        }

        $salaryCalculator = new SalaryService($employee, $attendance, $period, $currentMonth);
        $salaryComponent = $salaryCalculator->calculateSalary();
        // dd($salaryCalculator);
        
        $results[$employee->id] = [
            'employee' => $employee,
            'attendance' => $attendance,
            'salary_data' => array_merge($attendance, $salaryComponent),
            'salary' => $salary
        ];
    }
    
    if (empty($results)) {
        return redirect()->back()->with('error', 'No valid payroll data found for this department');
    }
    

    return view('pages.salary.by-depart', compact('results','depart','period','currentMonth','currentYear'));
}
    
}