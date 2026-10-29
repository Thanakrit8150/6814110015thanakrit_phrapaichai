<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function index(): View { return view('employees.index', ['employees' => User::latest()->paginate(15)]); }

    public function create(): View { return view('employees.form', ['employee' => new User]); }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'email' => ['required', 'email', 'unique:users,email'], 'phone' => ['nullable', 'string', 'max:40'], 'role' => ['required', 'in:admin,staff'], 'password' => ['required', 'string', 'min:10', 'confirmed']]);
        $data['password'] = Hash::make($data['password']);
        User::create($data);
        return redirect()->route('employees.index')->with('success', 'เพิ่มพนักงานแล้ว');
    }

    public function edit(User $employee): View { return view('employees.form', compact('employee')); }

    public function update(Request $request, User $employee): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'email' => ['required', 'email', 'unique:users,email,'.$employee->id], 'phone' => ['nullable', 'string', 'max:40'], 'role' => ['required', 'in:admin,staff'], 'is_active' => ['required', 'boolean'], 'password' => ['nullable', 'string', 'min:10', 'confirmed']]);
        if ($employee->is($request->user()) && ($data['role'] !== 'admin' || ! $data['is_active'])) return back()->withErrors(['role' => 'ไม่สามารถลดสิทธิ์หรือระงับบัญชีที่กำลังใช้งานได้']);
        if ($employee->role === 'admin' && ($data['role'] !== 'admin' || ! $data['is_active']) && User::where('role', 'admin')->where('is_active', true)->count() <= 1) return back()->withErrors(['role' => 'ต้องมีผู้ดูแลระบบที่ใช้งานได้อย่างน้อยหนึ่งบัญชี']);
        if (! empty($data['password'])) $data['password'] = Hash::make($data['password']); else unset($data['password']);
        $employee->update($data);
        return redirect()->route('employees.index')->with('success', 'บันทึกข้อมูลพนักงานแล้ว');
    }
}
