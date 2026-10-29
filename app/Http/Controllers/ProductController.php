<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use App\Models\StockMovement;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $q = $request->string('q')->toString();
        $products = Product::when($q, fn ($query) => $query->where(fn ($sub) => $sub->where('name', 'like', "%{$q}%")->orWhere('sku', 'like', "%{$q}%")->orWhere('game_name', 'like', "%{$q}%")))->when($request->filled('type'), fn ($query) => $query->where('type', $request->input('type')))->orderBy('game_name')->paginate(15)->withQueryString();
        return view('products.index', compact('products', 'q'));
    }

    public function create(): View { return view('products.form', ['product' => new Product]); }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        DB::transaction(function () use ($data, $request) {
            $product = Product::create($data);
            if ($product->stock > 0) StockMovement::create(['product_id' => $product->id, 'user_id' => $request->user()->id, 'type' => 'purchase', 'quantity' => $product->stock, 'unit_cost' => $product->cost_price, 'reference' => 'INITIAL-'.$product->sku, 'note' => 'สต็อกตั้งต้นเมื่อสร้างสินค้า']);
        });
        return redirect()->route('products.index')->with('success', 'เพิ่มสินค้าแล้ว');
    }

    public function edit(Product $product): View { return view('products.form', compact('product')); }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $this->validated($request, $product);
        if ($data['stock'] !== $product->stock) throw ValidationException::withMessages(['stock' => 'กรุณาปรับจำนวนผ่านเมนูรับสินค้าเข้า เพื่อให้มีประวัติสต็อกครบถ้วน']);
        if ($product->type === 'account' && $product->sold_at !== null && $data['type'] !== 'account') throw ValidationException::withMessages(['type' => 'บัญชีเกมที่ขายแล้วไม่สามารถเปลี่ยนประเภทหรือนำกลับมาขายซ้ำได้']);
        $product->update($data);
        return redirect()->route('products.index')->with('success', 'บันทึกสินค้าแล้ว');
    }

    public function destroy(Product $product): RedirectResponse
    {
        if ($product->orderItems()->exists() || $product->stockMovements()->exists()) return back()->withErrors(['product' => 'สินค้ามีประวัติธุรกรรม จึงลบไม่ได้']);
        $product->delete();
        return back()->with('success', 'ลบสินค้าแล้ว');
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        $data = $request->validate([
            'sku' => ['required', 'string', 'max:80', 'unique:products,sku,'.($product?->id ?? 'NULL')],
            'name' => ['required', 'string', 'max:150'], 'game_name' => ['required', 'string', 'max:120'],
            'type' => ['required', 'in:item,account'], 'cost_price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'selling_price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'stock' => ['required', 'integer', 'min:0', 'max:100000'], 'status' => ['required', 'in:active,inactive'],
            'description' => ['nullable', 'string', 'max:2000'],
            'account_username' => [$product ? 'nullable' : 'required_if:type,account', 'string', 'max:190'], 'account_password' => [$product ? 'nullable' : 'required_if:type,account', 'string', 'max:500'],
        ]);
        if ($data['type'] === 'account' && $data['stock'] > 1) throw ValidationException::withMessages(['stock' => 'สินค้าบัญชีเกมหนึ่งรายการจำกัดสต็อกได้ไม่เกิน 1']);
        if ($data['type'] === 'item') {
            $data['account_credentials'] = null;
        } elseif (($data['account_username'] ?? '') !== '' || ($data['account_password'] ?? '') !== '') {
            if (empty($data['account_username']) || empty($data['account_password'])) throw ValidationException::withMessages(['account_password' => 'กรุณากรอกชื่อผู้ใช้และรหัสผ่านให้ครบทั้งคู่']);
            if ($product?->sold_at !== null) throw ValidationException::withMessages(['account_password' => 'ไม่สามารถเปลี่ยนข้อมูลบัญชีที่ขายแล้วได้']);
        } elseif (! $product && $data['type'] === 'account') {
            throw ValidationException::withMessages(['account_username' => 'กรุณาระบุข้อมูลบัญชีเกม']);
        }
        if (isset($data['account_username'], $data['account_password']) && $data['account_username'] !== '' && $data['account_password'] !== '') {
            $data['account_credentials'] = ['username' => $data['account_username'], 'password' => $data['account_password']];
        }
        unset($data['account_username'], $data['account_password']);
        return $data;
    }
}
