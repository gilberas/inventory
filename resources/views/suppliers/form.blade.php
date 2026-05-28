@extends('layouts.app')
@section('title', isset($supplier) ? 'Edit Supplier' : 'New Supplier')
@section('content')
<div class="card" style="max-width:800px">
    <div class="card-header">
        <h2 class="card-title">{{ isset($supplier) ? 'Edit Supplier' : 'New Supplier' }}</h2>
    </div>
    <form method="POST" action="{{ isset($supplier) ? route('suppliers.update', $supplier) : route('suppliers.store') }}">
        @csrf
        @if(isset($supplier)) @method('PUT') @endif
        <div class="form-grid">
            <div class="form-group">
                <label>Supplier Name *</label>
                <input type="text" name="name" value="{{ old('name', $supplier->name ?? '') }}" required>
            </div>
            <div class="form-group">
                <label>Contact Person</label>
                <input type="text" name="contact_person" value="{{ old('contact_person', $supplier->contact_person ?? '') }}">
            </div>
            <div class="form-group">
                <label>Phone</label>
                <input type="text" name="phone" value="{{ old('phone', $supplier->phone ?? '') }}">
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" value="{{ old('email', $supplier->email ?? '') }}">
            </div>
            <div class="form-group full">
                <label>Address</label>
                <textarea name="address">{{ old('address', $supplier->address ?? '') }}</textarea>
            </div>
            <div class="form-group">
                <label>Tax Number</label>
                <input type="text" name="tax_number" value="{{ old('tax_number', $supplier->tax_number ?? '') }}">
            </div>
            <div class="form-group">
                <label>Payment Terms (days)</label>
                <input type="number" name="payment_terms" value="{{ old('payment_terms', $supplier->payment_terms ?? 30) }}" min="0">
            </div>
            <div class="form-group full">
                <label>Notes</label>
                <textarea name="notes">{{ old('notes', $supplier->notes ?? '') }}</textarea>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Save Supplier</button>
            <a href="{{ route('suppliers.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
