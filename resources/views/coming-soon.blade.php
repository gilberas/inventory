@extends('layouts.app')
@section('title', 'Coming Soon')
@section('content')
<div class="card" style="max-width:480px;margin:4rem auto;text-align:center;padding:3rem 2rem">
    <div style="font-size:3rem;margin-bottom:1rem;color:var(--muted)"><i class="fas fa-hammer"></i></div>
    <h2 style="margin-bottom:.5rem">Coming Soon</h2>
    <p style="color:var(--muted)">This module is under development and will be available in a future update.</p>
    <a href="{{ url()->previous(route('dashboard')) }}" class="btn btn-secondary" style="margin-top:1.5rem">← Go Back</a>
</div>
@endsection
