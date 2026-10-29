<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;

class SaleController extends Controller
{
    public function create(): View
    {
        return view('sales.create', ['products' => Product::where('status', 'active')->where('stock', '>', 0)->orderBy('name')->get(), 'customers' => Customer::orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'customer_id' => ['nullable', 'exists:customers,id'], 'discount' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:2000'], 'payment_status' => ['required', 'in:pending,paid'],
            'payment_method' => ['nullable', 'in:cash,bank_transfer,e_wallet,other'],
            'items' => ['required', 'array', 'min:1', 'max:50'], 'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000'],
        ]);

        $order = DB::transaction(function () use ($data, $request) {
            $lines = [];
            $subtotal = 0;
            foreach ($data['items'] as $line) {
                $product = Product::whereKey($line['product_id'])->lockForUpdate()->firstOrFail();
                if ($product->status !== 'active' || $line['quantity'] > $product->stock) {
                    throw ValidationException::withMessages(['items' => "สต็อกสินค้า {$product->name} ไม่เพียงพอ"]);
                }
                if ($product->type === 'account' && ($product->sold_at !== null || $line['quantity'] !== 1 || $product->stock !== 1)) {
                    throw ValidationException::withMessages(['items' => 'บัญชีเกมหนึ่งบัญชีขายได้เพียงครั้งเดียว']);
                }
                if ($product->type === 'account' && (empty($product->account_credentials['username']) || empty($product->account_credentials['password']))) {
                    throw ValidationException::withMessages(['items' => 'บัญชีเกมยังไม่มีข้อมูลรับรองที่บันทึกอย่างปลอดภัย']);
                }
                $lineTotal = (int) $line['quantity'] * (int) round((float) $product->selling_price * 100);
                $subtotal += $lineTotal;
                $lines[] = [$product, (int) $line['quantity'], $lineTotal];
            }
            $discount = (int) round((float) $data['discount'] * 100);
            if ($discount > $subtotal) throw ValidationException::withMessages(['discount' => 'ส่วนลดต้องไม่เกินยอดรวม']);
            $total = $subtotal - $discount;
            if ($data['payment_status'] === 'paid' && empty($data['payment_method'])) throw ValidationException::withMessages(['payment_method' => 'กรุณาระบุช่องทางชำระเงิน']);

            $order = Order::create([
                'order_no' => 'ORD-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
                'customer_id' => $data['customer_id'] ?? null, 'user_id' => $request->user()->id,
                'subtotal' => $subtotal / 100, 'discount' => $discount / 100, 'total' => $total / 100,
                'status' => $data['payment_status'], 'note' => $data['note'] ?? null,
            ]);
            foreach ($lines as [$product, $quantity, $lineTotal]) {
                $order->items()->create([
                    'product_id' => $product->id, 'product_name' => $product->name, 'sku' => $product->sku,
                    'quantity' => $quantity, 'unit_price' => $product->selling_price,
                    'unit_cost' => $product->cost_price, 'line_total' => $lineTotal / 100,
                ]);
                $product->decrement('stock', $quantity);
                if ($product->type === 'account') $product->forceFill(['sold_at' => now()])->save();
                StockMovement::create(['product_id' => $product->id, 'user_id' => $request->user()->id, 'type' => 'sale', 'quantity' => $quantity, 'unit_cost' => $product->cost_price, 'reference' => $order->order_no]);
            }
            Payment::create(['order_id' => $order->id, 'method' => $data['payment_method'] ?? null, 'amount' => $total / 100, 'status' => $data['payment_status'], 'paid_at' => $data['payment_status'] === 'paid' ? now() : null, 'recorded_by' => $request->user()->id]);
            return $order;
        }, 3);

        return redirect()->route('sales.show', $order)->with('success', 'บันทึกคำสั่งซื้อแล้ว (ระบบนี้บันทึกสถานะเท่านั้น ไม่ได้เรียกเก็บเงินจริง)');
    }

    public function index(): View
    {
        return view('sales.index', ['orders' => Order::with(['customer', 'user'])->latest()->paginate(20)]);
    }

    public function show(Order $order): View
    {
        return view('sales.show', ['order' => $order->load(['customer', 'user', 'items', 'payments'])]);
    }

    public function updatePayment(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:paid'], 'method' => ['required', 'in:cash,bank_transfer,e_wallet,other'], 'reference' => ['nullable', 'string', 'max:190']]);
        DB::transaction(function () use ($order, $data, $request) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'cancelled') throw ValidationException::withMessages(['status' => 'คำสั่งซื้อที่ยกเลิกแล้วเปลี่ยนสถานะไม่ได้']);
            $locked->update(['status' => 'paid']);
            $locked->payments()->latest()->first()?->update(['status' => 'paid', 'method' => $data['method'], 'reference' => $data['reference'] ?? null, 'paid_at' => now(), 'recorded_by' => $request->user()->id]);
        });
        return back()->with('success', 'ปรับปรุงสถานะแล้ว (ไม่มีการติดต่อผู้ให้บริการชำระเงินจริง)');
    }

    public function cancel(Order $order): RedirectResponse
    {
        DB::transaction(function () use ($order) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'cancelled') throw ValidationException::withMessages(['order' => 'คำสั่งซื้อนี้ยกเลิกแล้ว']);
            if ($locked->status === 'paid') throw ValidationException::withMessages(['order' => 'ไม่สามารถยกเลิกคำสั่งซื้อที่บันทึกว่าชำระแล้วได้']);
            foreach ($locked->items()->whereNotNull('product_id')->get() as $item) {
                $product = Product::whereKey($item->product_id)->lockForUpdate()->first();
                if ($product) {
                    $product->increment('stock', $item->quantity);
                    if ($product->type === 'account') $product->forceFill(['sold_at' => null])->save();
                    StockMovement::create(['product_id' => $product->id, 'user_id' => auth()->id(), 'type' => 'adjustment', 'quantity' => $item->quantity, 'unit_cost' => $item->unit_cost, 'reference' => $locked->order_no, 'note' => 'คืนสต็อกจากการยกเลิกคำสั่งซื้อ']);
                }
            }
            $locked->update(['status' => 'cancelled']);
            $locked->payments()->update(['status' => 'cancelled']);
        });
        return back()->with('success', 'ยกเลิกคำสั่งซื้อและคืนสต็อกแล้ว');
    }
}
