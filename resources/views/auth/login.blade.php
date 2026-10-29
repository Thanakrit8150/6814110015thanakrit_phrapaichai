@extends('layouts.app')
@section('title','เข้าสู่ระบบ')
@section('content')
<div style="max-width:440px;margin:6vh auto"><div class="card"><div style="font-size:25px;font-weight:800;margin-bottom:3px">GAME<span style="color:#3458e8">TRADE</span> POS</div><p class="muted" style="margin-top:0">ระบบจัดการร้านค้าไอเทมและบัญชีเกม</p><h1 style="font-size:21px;margin-top:25px">เข้าสู่ระบบ</h1><form method="post" action="{{ route('login.store') }}">@csrf<label for="email">อีเมล</label><input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"><label for="password">รหัสผ่าน</label><input id="password" name="password" type="password" required autocomplete="current-password"><label style="display:flex;align-items:center;gap:8px;font-weight:400"><input style="width:auto" type="checkbox" name="remember" value="1">จดจำฉัน</label><button style="width:100%;margin-top:18px">เข้าสู่ระบบ</button></form></div></div>
@endsection
