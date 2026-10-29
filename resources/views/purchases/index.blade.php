@extends('layouts.app')
@section('title','รับสินค้าเข้า')
@section('content')
<div class="pagehead"><div><h1>รับสินค้าเข้า</h1><div class="muted">บันทึกการซื้อสินค้าและปรับปรุงต้นทุน</div></div><a class="btn" href="{{ route('purchases.create') }}">＋ บันทึกรับสินค้า</a></div><div class="card"><div class="table-wrap"><table><thead><tr><th>วันที่รับเข้า</th><th>สินค้า</th><th>SKU</th><th>จำนวน</th><th>ต้นทุน/หน่วย</th><th>เลขอ้างอิง</th><th>บันทึกโดย</th></tr></thead><tbody>@forelse($movements as $movement)<tr><td>{{ $movement->created_at->format('d/m/Y H:i') }}</td><td>{{ $movement->product->name }}</td><td>{{ $movement->product->sku }}</td><td>{{ $movement->quantity }}</td><td>฿{{ number_format($movement->unit_cost,2) }}</td><td>{{ $movement->reference ?? '—' }}</td><td>{{ $movement->user->name }}</td></tr>@empty<tr><td colspan="7" class="empty">ยังไม่มีประวัติรับสินค้า</td></tr>@endforelse</tbody></table></div><div class="pagination">{{ $movements->links() }}</div></div>
@endsection
