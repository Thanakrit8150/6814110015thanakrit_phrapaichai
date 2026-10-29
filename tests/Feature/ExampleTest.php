<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::create(['name' => 'Demo Staff', 'email' => 'staff@example.test', 'password' => Hash::make('password-long-demo'), 'role' => 'staff', 'is_active' => true]);
    }

    private function admin(): User
    {
        return User::create(['name' => 'Demo Admin', 'email' => 'admin@example.test', 'password' => Hash::make('password-long-demo'), 'role' => 'admin', 'is_active' => true]);
    }

    private function product(string $type = 'item', int $stock = 2): Product
    {
        return Product::create(['sku' => 'SKU-'.str()->random(8), 'name' => 'Test Item', 'game_name' => 'Test Game', 'type' => $type, 'cost_price' => 10, 'selling_price' => 20, 'stock' => $stock, 'status' => 'active']);
    }

    public function test_guest_is_redirected_to_login_and_login_page_is_available(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('เข้าสู่ระบบ');
    }

    public function test_staff_can_make_sale_and_inventory_is_decremented(): void
    {
        $staff = $this->staff();
        $product = $this->product();
        $this->actingAs($staff)->post('/sales', ['discount' => 5, 'payment_status' => 'pending', 'items' => [['product_id' => $product->id, 'quantity' => 1]]])->assertRedirect();
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 1]);
        $this->assertDatabaseHas('orders', ['subtotal' => 20, 'discount' => 5, 'total' => 15, 'status' => 'pending']);
        $this->assertDatabaseHas('order_items', ['product_id' => $product->id, 'quantity' => 1]);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'type' => 'sale', 'quantity' => 1]);
    }

    public function test_overselling_fails_without_partial_order_or_stock_change(): void
    {
        $staff = $this->staff();
        $product = $this->product(stock: 1);
        $this->actingAs($staff)->from('/sales/create')->post('/sales', ['discount' => 0, 'payment_status' => 'pending', 'items' => [['product_id' => $product->id, 'quantity' => 2]]])->assertSessionHasErrors('items');
        $this->assertSame(1, $product->fresh()->stock);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
    }

    public function test_game_accounts_are_sold_and_received_only_once(): void
    {
        $staff = $this->staff();
        $product = $this->product(type: 'account', stock: 1);
        $product->forceFill(['account_credentials' => ['username' => 'fictional-user', 'password' => 'fictional-password']])->save();
        $this->actingAs($staff)->post('/sales', ['discount' => 0, 'payment_status' => 'pending', 'items' => [['product_id' => $product->id, 'quantity' => 1]]])->assertRedirect();
        $this->assertSame(0, $product->fresh()->stock);
        $this->assertNotNull($product->fresh()->sold_at);
        $this->actingAs($staff)->from('/sales/create')->post('/sales', ['discount' => 0, 'payment_status' => 'pending', 'items' => [['product_id' => $product->id, 'quantity' => 1]]])->assertSessionHasErrors('items');
        $this->actingAs($staff)->from('/purchases/create')->post('/purchases', ['product_id' => $product->id, 'quantity' => 1, 'unit_cost' => 10, 'account_username' => 'next-user', 'account_password' => 'next-password'])->assertSessionHasErrors('quantity');
        $this->assertSame(1, Order::count());
    }

    public function test_cancelled_sale_returns_inventory_and_account_can_be_sold_again_only_after_reversal(): void
    {
        $staff = $this->staff();
        $product = $this->product(type: 'account', stock: 1);
        $product->forceFill(['account_credentials' => ['username' => 'fictional-user', 'password' => 'fictional-password']])->save();
        $this->actingAs($staff)->post('/sales', ['discount' => 0, 'payment_status' => 'pending', 'items' => [['product_id' => $product->id, 'quantity' => 1]]])->assertRedirect();
        $order = Order::firstOrFail();
        $this->actingAs($staff)->patch("/sales/{$order->id}/cancel")->assertRedirect();
        $this->assertSame(1, $product->fresh()->stock);
        $this->assertNull($product->fresh()->sold_at);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled']);
    }

    public function test_account_credentials_are_encrypted_and_hidden_from_serialization(): void
    {
        $product = $this->product(type: 'account', stock: 1);
        $product->forceFill(['account_credentials' => ['username' => 'fictional-user', 'password' => 'fictional-secret']])->save();
        $this->assertSame(['username' => 'fictional-user', 'password' => 'fictional-secret'], $product->fresh()->account_credentials);
        $this->assertArrayNotHasKey('account_credentials', $product->fresh()->toArray());
        $this->assertStringNotContainsString('fictional-secret', $product->getRawOriginal('account_credentials'));
    }

    public function test_staff_cannot_access_employee_or_product_management(): void
    {
        $staff = $this->staff();
        $this->actingAs($staff)->get('/employees')->assertForbidden();
        $this->actingAs($staff)->get('/products/create')->assertForbidden();
        $this->actingAs($staff)->post('/products', [])->assertForbidden();
    }

    public function test_game_account_credentials_are_required_when_adding_account(): void
    {
        $this->actingAs($this->admin())->from('/products/create')->post('/products', ['sku' => 'ACC-'.str()->random(5), 'name' => 'Fictional account', 'game_name' => 'Test game', 'type' => 'account', 'cost_price' => 10, 'selling_price' => 20, 'stock' => 1, 'status' => 'active'])->assertSessionHasErrors('account_username');
        $this->assertDatabaseCount('products', 0);
    }

    public function test_game_account_without_secure_credentials_cannot_be_sold(): void
    {
        $staff = $this->staff();
        $product = $this->product(type: 'account', stock: 1);
        $this->actingAs($staff)->from('/sales/create')->post('/sales', ['discount' => 0, 'payment_status' => 'pending', 'items' => [['product_id' => $product->id, 'quantity' => 1]]])->assertSessionHasErrors('items');
        $this->assertSame(1, $product->fresh()->stock);
    }

    public function test_initial_product_stock_is_recorded_as_movement(): void
    {
        $this->actingAs($this->admin())->post('/products', ['sku' => 'ITEM-'.str()->random(5), 'name' => 'Test item', 'game_name' => 'Test game', 'type' => 'item', 'cost_price' => 10, 'selling_price' => 20, 'stock' => 3, 'status' => 'active'])->assertRedirect();
        $product = Product::firstOrFail();
        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'quantity' => 3, 'reference' => 'INITIAL-'.$product->sku]);
    }

    public function test_restock_updates_quantity_and_cost_in_one_transaction(): void
    {
        $staff = $this->staff();
        $product = $this->product(stock: 1);
        $this->actingAs($staff)->post('/purchases', ['product_id' => $product->id, 'quantity' => 4, 'unit_cost' => 12.5, 'reference' => 'TEST-PO-1'])->assertRedirect();
        $this->assertSame(5, $product->fresh()->stock);
        $this->assertSame('12.50', $product->fresh()->cost_price);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'type' => 'purchase', 'quantity' => 4, 'reference' => 'TEST-PO-1']);
    }

    public function test_payment_status_can_be_recorded_as_paid_without_external_charge(): void
    {
        $staff = $this->staff();
        $product = $this->product();
        $this->actingAs($staff)->post('/sales', ['discount' => 0, 'payment_status' => 'pending', 'items' => [['product_id' => $product->id, 'quantity' => 1]]])->assertRedirect();
        $order = Order::firstOrFail();
        $this->actingAs($staff)->patch("/sales/{$order->id}/payment", ['status' => 'paid', 'method' => 'cash'])->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'paid']);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'paid', 'method' => 'cash']);
    }

    public function test_report_endpoint_displays_paid_sales_and_manual_pdf_is_generated(): void
    {
        $staff = $this->staff();
        $product = $this->product();
        $this->actingAs($staff)->post('/sales', ['discount' => 5, 'payment_status' => 'paid', 'payment_method' => 'cash', 'items' => [['product_id' => $product->id, 'quantity' => 1]]])->assertRedirect();
        $this->actingAs($staff)->get('/reports')->assertOk()->assertSee('฿15.00');
        $this->actingAs($staff)->get('/reports/manual.pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
    }
}
