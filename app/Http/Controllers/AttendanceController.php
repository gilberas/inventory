<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function clockIn(Request $request, Employee $employee): RedirectResponse|JsonResponse
    {
        $today = today()->toDateString();

        $existing = Attendance::where('employee_id', $employee->id)
            ->whereDate('date', $today)
            ->first();

        if ($existing) {
            $msg = 'Already clocked in today.';
            return $request->wantsJson()
                ? response()->json(['error' => $msg], 422)
                : back()->with('error', $msg);
        }

        $record = Attendance::create([
            'tenant_id'   => auth()->user()->tenant_id,
            'employee_id' => $employee->id,
            'date'        => $today,
            'clock_in'    => now(),
            'status'      => 'present',
        ]);

        ActivityLog::create([
            'user_id'    => auth()->id(),
            'action'     => 'clock_in',
            'model_type' => Attendance::class,
            'model_id'   => $record->id,
            'new_values' => json_encode(['employee_id' => $employee->id, 'clock_in' => $record->clock_in]),
            'ip_address' => $request->ip(),
        ]);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Clocked in.', 'clock_in' => $record->clock_in]);
        }

        return back()->with('success', "Clocked in at {$record->clock_in->format('H:i')}.");
    }

    public function clockOut(Request $request, Employee $employee): RedirectResponse|JsonResponse
    {
        $today = today()->toDateString();

        $record = Attendance::where('employee_id', $employee->id)
            ->whereDate('date', $today)
            ->whereNull('clock_out')
            ->first();

        if (! $record) {
            $msg = 'No active clock-in found for today.';
            return $request->wantsJson()
                ? response()->json(['error' => $msg], 422)
                : back()->with('error', $msg);
        }

        $record->update(['clock_out' => now()]);

        ActivityLog::create([
            'user_id'    => auth()->id(),
            'action'     => 'clock_out',
            'model_type' => Attendance::class,
            'model_id'   => $record->id,
            'new_values' => json_encode(['employee_id' => $employee->id, 'clock_out' => $record->clock_out]),
            'ip_address' => $request->ip(),
        ]);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Clocked out.', 'clock_out' => $record->clock_out]);
        }

        return back()->with('success', "Clocked out at {$record->clock_out->format('H:i')}.");
    }

    public function today(Request $request): View
    {
        $branchId = $request->get('branch_id');

        $employees = Employee::with(['attendance' => fn ($q) => $q->whereDate('date', today())])
            ->where('status', 'active')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->get();

        $branches = \App\Models\Branch::where('is_active', true)->orderBy('name')->get();

        return view('attendance.today', compact('employees', 'branches', 'branchId'));
    }

    public function monthlyReport(Request $request, Employee $employee): View
    {
        $month = $request->get('month', today()->format('Y-m'));
        [$year, $mon] = explode('-', $month);

        $records = $employee->attendance()
            ->whereYear('date', $year)
            ->whereMonth('date', $mon)
            ->orderBy('date')
            ->get();

        $summary = [
            'present'  => $records->whereIn('status', ['present', 'late'])->count(),
            'absent'   => $records->where('status', 'absent')->count(),
            'late'     => $records->where('status', 'late')->count(),
            'half_day' => $records->where('status', 'half_day')->count(),
        ];

        return view('attendance.monthly', compact('employee', 'month', 'records', 'summary'));
    }
}
