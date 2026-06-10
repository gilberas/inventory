<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        $query = Employee::with(['branch', 'user'])
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%")
                ->orWhere('department', 'like', "%{$request->search}%"))
            ->when($request->branch_id, fn ($q) => $q->where('branch_id', $request->branch_id))
            ->when($request->status, fn ($q) => $q->where('status', $request->status));

        $employees = $query->latest()->paginate(20)->withQueryString();
        $branches  = Branch::where('is_active', true)->orderBy('name')->get();

        return view('employees.index', compact('employees', 'branches'));
    }

    public function create(): View
    {
        $branches = Branch::where('is_active', true)->orderBy('name')->get();
        $users    = User::where('tenant_id', auth()->user()->tenant_id)->orderBy('name')->get();

        return view('employees.create', compact('branches', 'users'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id'    => 'nullable|exists:users,id',
            'branch_id'  => 'required|exists:branches,id',
            'name'       => 'required|string|max:191',
            'department' => 'nullable|string|max:100',
            'position'   => 'nullable|string|max:100',
            'salary'     => 'nullable|numeric|min:0',
            'phone'      => 'required|string|max:30',
            'email'      => 'nullable|email|max:191',
            'join_date'  => 'required|date',
            'status'     => 'required|in:active,inactive',
        ]);

        $employee = Employee::create($data);

        ActivityLog::create([
            'user_id'    => auth()->id(),
            'action'     => 'created',
            'model_type' => Employee::class,
            'model_id'   => $employee->id,
            'new_values' => json_encode($data),
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('employees.show', $employee)->with('success', 'Employee created.');
    }

    public function show(Employee $employee): View
    {
        $employee->load(['branch', 'user', 'attendance' => fn ($q) => $q->latest('date')->limit(30)]);

        $totalDays    = $employee->attendance()->count();
        $presentDays  = $employee->attendance()->whereIn('status', ['present', 'late'])->count();
        $absentDays   = $employee->attendance()->where('status', 'absent')->count();
        $attendancePct = $totalDays > 0 ? round(($presentDays / $totalDays) * 100, 1) : 0;

        return view('employees.show', compact('employee', 'totalDays', 'presentDays', 'absentDays', 'attendancePct'));
    }

    public function edit(Employee $employee): View
    {
        $branches = Branch::where('is_active', true)->orderBy('name')->get();
        $users    = User::where('tenant_id', auth()->user()->tenant_id)->orderBy('name')->get();

        return view('employees.edit', compact('employee', 'branches', 'users'));
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $data = $request->validate([
            'user_id'    => 'nullable|exists:users,id',
            'branch_id'  => 'required|exists:branches,id',
            'name'       => 'required|string|max:191',
            'department' => 'nullable|string|max:100',
            'position'   => 'nullable|string|max:100',
            'salary'     => 'nullable|numeric|min:0',
            'phone'      => 'required|string|max:30',
            'email'      => 'nullable|email|max:191',
            'join_date'  => 'required|date',
            'status'     => 'required|in:active,inactive',
        ]);

        $old = $employee->toArray();
        $employee->update($data);

        ActivityLog::create([
            'user_id'    => auth()->id(),
            'action'     => 'updated',
            'model_type' => Employee::class,
            'model_id'   => $employee->id,
            'old_values' => json_encode($old),
            'new_values' => json_encode($data),
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('employees.show', $employee)->with('success', 'Employee updated.');
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        ActivityLog::create([
            'user_id'    => auth()->id(),
            'action'     => 'deleted',
            'model_type' => Employee::class,
            'model_id'   => $employee->id,
            'old_values' => json_encode($employee->toArray()),
            'new_values' => json_encode([]),
            'ip_address' => request()->ip(),
        ]);

        $employee->delete();

        return redirect()->route('employees.index')->with('success', 'Employee removed.');
    }

    public function linkUser(Request $request, Employee $employee): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $employee->update(['user_id' => $data['user_id']]);

        ActivityLog::create([
            'user_id'    => auth()->id(),
            'action'     => 'linked_user',
            'model_type' => Employee::class,
            'model_id'   => $employee->id,
            'new_values' => json_encode($data),
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'User account linked.');
    }

    public function performance(Request $request, Employee $employee): View
    {
        $start = $request->get('start_date', today()->startOfMonth()->toDateString());
        $end   = $request->get('end_date', today()->toDateString());

        $salesRevenue = 0;
        $salesCount   = 0;
        $grnCount     = 0;

        if ($employee->user_id) {
            $salesRevenue = DB::table('sales')
                ->where('tenant_id', auth()->user()->tenant_id)
                ->where('cashier_id', $employee->user_id)
                ->where('status', 'completed')
                ->whereBetween(DB::raw('DATE(created_at)'), [$start, $end])
                ->sum('grand_total');

            $salesCount = DB::table('sales')
                ->where('tenant_id', auth()->user()->tenant_id)
                ->where('cashier_id', $employee->user_id)
                ->where('status', 'completed')
                ->whereBetween(DB::raw('DATE(created_at)'), [$start, $end])
                ->count();

            $grnCount = DB::table('goods_received_notes')
                ->where('tenant_id', auth()->user()->tenant_id)
                ->where('received_by', $employee->user_id)
                ->whereBetween(DB::raw('DATE(created_at)'), [$start, $end])
                ->count();
        }

        $presentDays = $employee->attendance()
            ->whereIn('status', ['present', 'late'])
            ->whereBetween('date', [$start, $end])
            ->count();

        return view('employees.performance', compact(
            'employee', 'start', 'end',
            'salesRevenue', 'salesCount', 'grnCount', 'presentDays'
        ));
    }

    public function schedule(Request $request, Employee $employee): View
    {
        $month = $request->get('month', today()->format('Y-m'));
        [$year, $mon] = explode('-', $month);

        $shifts = $employee->shifts()
            ->with('shift')
            ->whereYear('date', $year)
            ->whereMonth('date', $mon)
            ->orderBy('date')
            ->get()
            ->keyBy(fn ($es) => $es->date->toDateString());

        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, (int) $mon, (int) $year);

        return view('employees.schedule', compact('employee', 'month', 'shifts', 'daysInMonth', 'year', 'mon'));
    }
}
