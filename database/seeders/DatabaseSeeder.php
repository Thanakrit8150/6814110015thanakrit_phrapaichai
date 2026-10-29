<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(['email' => 'admin@example.test'], ['name' => 'สมมติ ผู้ดูแลร้าน', 'password' => Hash::make('DemoAdmin-2026!'), 'role' => 'admin', 'phone' => '080-000-0001', 'is_active' => true]);
        $staff = User::updateOrCreate(['email' => 'staff@example.test'], ['name' => 'สมมติ พนักงานขาย', 'password' => Hash::make('DemoStaff-2026!'), 'role' => 'staff', 'phone' => '080-000-0002', 'is_active' => true]);

        $customer = Customer::updateOrCreate(['email' => 'demo.customer@example.test'], ['name' => 'ลูกค้าสมมติ ตัวอย่าง', 'phone' => '080-000-0100', 'note' => 'ข้อมูลสมมติสำหรับสาธิตเท่านั้น']);
        $products = [
            ['sku' => 'DEMO-ITEM-001', 'name' => 'ดาบมังกร (ข้อมูลสมมติ)', 'game_name' => 'Fantasy Realm (เกมสมมติ)', 'type' => 'item', 'cost_price' => 120, 'selling_price' => 190, 'stock' => 12, 'status' => 'active', 'description' => 'สินค้าตัวอย่าง ห้ามใช้แทนไอเทมจริง'],
            ['sku' => 'DEMO-ITEM-002', 'name' => 'กล่องสุ่ม (ข้อมูลสมมติ)', 'game_name' => 'Fantasy Realm (เกมสมมติ)', 'type' => 'item', 'cost_price' => 45, 'selling_price' => 80, 'stock' => 2, 'status' => 'active', 'description' => 'ข้อมูลสินค้าเพื่อทดสอบระบบ'],
            ['sku' => 'DEMO-ACC-001', 'name' => 'บัญชีเลเวล 20 (บัญชีสมมติ)', 'game_name' => 'Pixel Quest (เกมสมมติ)', 'type' => 'account', 'cost_price' => 350, 'selling_price' => 550, 'stock' => 1, 'status' => 'active', 'description' => 'บัญชีและ credential สมมติสำหรับสาธิต', 'account_credentials' => ['username' => 'demo-account-not-real', 'password' => 'NOT-A-REAL-GAME-ACCOUNT']],
            ['sku' => 'DEMO-ITEM-003', 'name' => 'ยาเพิ่มพลัง (ข้อมูลสมมติ)', 'game_name' => 'Pixel Quest (เกมสมมติ)', 'type' => 'item', 'cost_price' => 15, 'selling_price' => 30, 'stock' => 0, 'status' => 'active', 'description' => 'ข้อมูลสินค้าเพื่อทดสอบระบบ'],
        ];
        $seededProducts = [];
        foreach ($products as $data) {
            $credentials = $data['account_credentials'] ?? null;
            unset($data['account_credentials']);
            $product = Product::firstOrCreate(['sku' => $data['sku']], $data);
            if ($credentials && ! $product->account_credentials) $product->forceFill(['account_credentials' => $credentials])->save();
            $seededProducts[$product->sku] = $product;
        }
        foreach ($seededProducts as $product) {
            $openingStock = $product->stock + ($product->sku === 'DEMO-ITEM-001' ? 1 : ($product->sku === 'DEMO-ITEM-002' ? 1 : 0));
            StockMovement::firstOrCreate(['product_id' => $product->id, 'reference' => 'DEMO-SEED-'.$product->sku], ['user_id' => $admin->id, 'type' => 'purchase', 'quantity' => max(1, $openingStock), 'unit_cost' => $product->cost_price, 'note' => 'ข้อมูลสมมติสำหรับทดสอบ']);
        }

        $order = Order::firstOrCreate(['order_no' => 'DEMO-PAID-20261028'], ['customer_id' => $customer->id, 'user_id' => $staff->id, 'subtotal' => 270, 'discount' => 10, 'total' => 260, 'status' => 'paid', 'note' => 'คำสั่งซื้อข้อมูลสมมติสำหรับทดสอบ']);
        $newDemoOrder = $order->wasRecentlyCreated;
        $item = $seededProducts['DEMO-ITEM-001'];
        $order->items()->firstOrCreate(['sku' => $item->sku], ['product_id' => $item->id, 'product_name' => $item->name, 'quantity' => 1, 'unit_price' => 190, 'unit_cost' => 120, 'line_total' => 190]);
        if ($newDemoOrder) $item->decrement('stock');
        $box = $seededProducts['DEMO-ITEM-002'];
        $order->items()->firstOrCreate(['sku' => $box->sku], ['product_id' => $box->id, 'product_name' => $box->name, 'quantity' => 1, 'unit_price' => 80, 'unit_cost' => 45, 'line_total' => 80]);
        if ($newDemoOrder) $box->decrement('stock');
        Payment::firstOrCreate(['order_id' => $order->id], ['method' => 'cash', 'amount' => 260, 'status' => 'paid', 'reference' => 'DEMO-NOT-A-REAL-PAYMENT', 'paid_at' => now(), 'recorded_by' => $staff->id]);
        StockMovement::firstOrCreate(['product_id' => $item->id, 'reference' => $order->order_no], ['user_id' => $staff->id, 'type' => 'sale', 'quantity' => 1, 'unit_cost' => $item->cost_price, 'note' => 'ข้อมูลสมมติสำหรับทดสอบ']);
        StockMovement::firstOrCreate(['product_id' => $box->id, 'reference' => $order->order_no], ['user_id' => $staff->id, 'type' => 'sale', 'quantity' => 1, 'unit_cost' => $box->cost_price, 'note' => 'ข้อมูลสมมติสำหรับทดสอบ']);
    }
}
