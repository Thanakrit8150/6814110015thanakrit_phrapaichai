<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        $orders = Order::where('status', 'paid')->when($filters['from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))->when($filters['to'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
        $stats = (clone $orders)->selectRaw('COUNT(*) as order_count, COALESCE(SUM(total),0) as revenue, COALESCE(SUM(discount),0) as discounts')->first();
        $costs = DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')->where('orders.status', 'paid')->when($filters['from'] ?? null, fn ($q, $date) => $q->whereDate('orders.created_at', '>=', $date))->when($filters['to'] ?? null, fn ($q, $date) => $q->whereDate('orders.created_at', '<=', $date))->selectRaw('COALESCE(SUM(order_items.unit_cost * order_items.quantity),0) as cost')->value('cost');
        $grossProfit = (float) $stats->revenue - (float) $costs;
        $topProducts = DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')->where('orders.status', 'paid')->when($filters['from'] ?? null, fn ($q, $date) => $q->whereDate('orders.created_at', '>=', $date))->when($filters['to'] ?? null, fn ($q, $date) => $q->whereDate('orders.created_at', '<=', $date))->select('order_items.product_name')->selectRaw('SUM(order_items.quantity) as units, SUM(order_items.line_total) as revenue')->groupBy('order_items.product_name')->orderByDesc('units')->limit(10)->get();
        $ordersList = (clone $orders)->with(['customer', 'user'])->latest()->limit(100)->get();
        return view('reports.index', compact('filters', 'stats', 'grossProfit', 'topProducts', 'ordersList'));
    }

    public function manual()
    {
        $pdf = Pdf::loadView('reports.manual')->setPaper('a4');
        $metrics = $pdf->getDomPDF()->getFontMetrics();
        foreach (['normal' => 'Regular', 'bold' => 'Bold'] as $weight => $variant) {
            $fontPath = resource_path("fonts/sarabun/Sarabun-{$variant}.ttf");
            $metrics->registerFont(['family' => 'sarabun', 'weight' => $weight, 'style' => 'normal'], $fontPath);
        }
        return $pdf->download('gametrade-pos-manual.pdf');
    }
}
