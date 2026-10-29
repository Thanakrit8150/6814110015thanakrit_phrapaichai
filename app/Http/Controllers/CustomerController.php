<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $q = $request->string('q')->toString();
        $customers = Customer::withCount('orders')->when($q, fn ($query) => $query->where(fn ($sub) => $sub->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%")))->latest()->paginate(15)->withQueryString();
        return view('customers.index', compact('customers', 'q'));
    }

    public function create(): View { return view('customers.form', ['customer' => new Customer]); }

    public function store(Request $request): RedirectResponse
    {
        Customer::create($this->validated($request));
        return redirect()->route('customers.index')->with('success', 'เพิ่มลูกค้าแล้ว');
    }

    public function edit(Customer $customer): View { return view('customers.form', compact('customer')); }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $customer->update($this->validated($request, $customer));
        return redirect()->route('customers.index')->with('success', 'บันทึกข้อมูลลูกค้าแล้ว');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        if ($customer->orders()->exists()) return back()->withErrors(['customer' => 'ลูกค้ามีประวัติคำสั่งซื้อ จึงลบไม่ได้']);
        $customer->delete();
        return back()->with('success', 'ลบข้อมูลลูกค้าแล้ว');
    }

    private function validated(Request $request, ?Customer $customer = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:190', 'unique:customers,email,'.($customer?->id ?? 'NULL')],
            'phone' => ['nullable', 'string', 'max:40'], 'note' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
