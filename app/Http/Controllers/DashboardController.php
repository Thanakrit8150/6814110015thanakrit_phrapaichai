<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $today = Order::whereDate('created_at', today())->where('status', '!=', 'cancelled');
        return view('dashboard', [
            'todaySales' => (clone $today)->sum('total'),
            'todayOrders' => (clone $today)->count(),
            'inventoryUnits' => Product::where('status', 'active')->sum('stock'),
            'lowStock' => Product::where('status', 'active')->where('stock', '<=', 3)->orderBy('stock')->limit(6)->get(),
            'recentOrders' => Order::with(['customer', 'user'])->latest()->limit(8)->get(),
            'topProducts' => DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('orders.status', 'paid')->select('order_items.product_name', DB::raw('SUM(order_items.quantity) as units'))
                ->groupBy('order_items.product_name')->orderByDesc('units')->limit(5)->get(),
        ]);
    }
}
