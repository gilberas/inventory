@extends('layouts.app')
@section('title', isset($customer) ? 'Edit Customer' : 'New Customer')
@section('content')
<div class="card" style="max-width:800px">
    <div class="card-header">
        <h2 class="card-title">{{ isset($customer) ? 'Edit Customer' : 'New Customer' }}</h2>
    </div>
    <form method="POST" action="{{ isset($customer) ? route('customers.update', $customer) : route('customers.store') }}">
        @csrf
        @if(isset($customer)) @method('PUT') @endif
        <div class="form-grid">
            <div class="form-group">
                <label>Customer Name *</label>
                <input type="text" name="name" value="{{ old('name', $customer->name ?? '') }}" required>
            </div>
            <div class="form-group">
                <label>Phone</label>
                <input type="text" name="phone" value="{{ old('phone', $customer->phone ?? '') }}">
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" value="{{ old('email', $customer->email ?? '') }}">
            </div>
            <div class="form-group">
                <label>Tax Number</label>
                <input type="text" name="tax_number" value="{{ old('tax_number', $customer->tax_number ?? '') }}">
            </div>
            <div class="form-group full">
                <label>Address</label>
                <textarea name="address">{{ old('address', $customer->address ?? '') }}</textarea>
            </div>
            <div class="form-group">
                <label>Credit Limit</label>
                <input type="number" name="credit_limit" step="0.01" value="{{ old('credit_limit', $customer->credit_limit ?? 0) }}">
            </div>
            <div class="form-group full">
                <label>Notes</label>
                <textarea name="notes">{{ old('notes', $customer->notes ?? '') }}</textarea>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Save Customer</button>
            <a href="{{ route('customers.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
