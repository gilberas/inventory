<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\EmployeeShift;
use App\Models\Shift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShiftController extends Controller
{
    public function index(): View
    {
        $shifts = Shift::latest()->paginate(20);

        return view('shifts.index', compact('shifts'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'start_time' => 'required|date_format:H:i',
            'end_time'   => 'required|date_format:H:i',
        ]);

        $shift = Shift::create($data);

        ActivityLog::create([
            'user_id'    => auth()->id(),
            'action'     => 'created',
            'model_type' => Shift::class,
            'model_id'   => $shift->id,
            'new_values' => json_encode($data),
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'Shift created.');
    }

    public function update(Request $request, Shift $shift): RedirectResponse
    {
        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'start_time' => 'required|date_format:H:i',
            'end_time'   => 'required|date_format:H:i',
        ]);

        $old = $shift->toArray();
        $shift->update($data);

        ActivityLog::create([
            'user_id'    => auth()->id(),
            'action'     => 'updated',
            'model_type' => Shift::class,
            'model_id'   => $shift->id,
            'old_values' => json_encode($old),
            'new_values' => json_encode($data),
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'Shift updated.');
    }

    public function assignToEmployee(Request $request, Employee $employee): RedirectResponse
    {
        $data = $request->validate([
            'shift_id' => 'required|exists:shifts,id',
            'date'     => 'required|date',
        ]);

        EmployeeShift::updateOrCreate(
            ['employee_id' => $employee->id, 'shift_id' => $data['shift_id'], 'date' => $data['date']],
            ['status' => 'scheduled']
        );

        ActivityLog::create([
            'user_id'    => auth()->id(),
            'action'     => 'assigned_shift',
            'model_type' => Employee::class,
            'model_id'   => $employee->id,
            'new_values' => json_encode($data),
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'Shift assigned.');
    }
}
