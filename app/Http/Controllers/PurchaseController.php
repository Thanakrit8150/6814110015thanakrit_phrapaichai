<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PurchaseController extends Controller
{
    public function index(): View
    {
        return view('purchases.index', ['movements' => StockMovement::with(['product', 'user'])->where('type', 'purchase')->latest()->paginate(20)]);
    }

    public function create(): View
    {
        return view('purchases.create', ['products' => Product::where('status', 'active')->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['product_id' => ['required', 'exists:products,id'], 'quantity' => ['required', 'integer', 'min:1', 'max:100000'], 'unit_cost' => ['required', 'numeric', 'min:0', 'max:99999999'], 'reference' => ['nullable', 'string', 'max:190'], 'note' => ['nullable', 'string', 'max:2000'], 'account_username' => ['nullable', 'string', 'max:190'], 'account_password' => ['nullable', 'string', 'max:500']]);
        DB::transaction(function () use ($data, $request) {
            $product = Product::whereKey($data['product_id'])->lockForUpdate()->firstOrFail();
            $newStock = $product->stock + (int) $data['quantity'];
            if ($product->type === 'account' && ($product->sold_at !== null || $newStock > 1)) throw ValidationException::withMessages(['quantity' => 'บัญชีเกมหนึ่งบัญชีขายได้ครั้งเดียว ไม่สามารถรับเข้าซ้ำหลังขายแล้ว']);
            $product->update(['stock' => $newStock, 'cost_price' => $data['unit_cost']]);
            if ($product->type === 'account' && (! ($data['account_username'] ?? null) || ! ($data['account_password'] ?? null))) {
                throw ValidationException::withMessages(['account_username' => 'กรุณาระบุข้อมูลบัญชีเกมเพื่อรับเข้าสต็อก']);
            }
            if ($product->type === 'account') $product->forceFill(['account_credentials' => ['username' => $data['account_username'], 'password' => $data['account_password']]])->save();
            StockMovement::create(['product_id' => $product->id, 'user_id' => $request->user()->id, 'type' => 'purchase', 'quantity' => $data['quantity'], 'unit_cost' => $data['unit_cost'], 'reference' => $data['reference'] ?? null, 'note' => $data['note'] ?? null]);
        });
        return redirect()->route('purchases.index')->with('success', 'บันทึกรับสินค้าเข้าแล้ว');
    }
}
