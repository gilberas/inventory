@extends('layouts.app')
@section('title', isset($warehouse) ? 'Edit Warehouse' : 'New Warehouse')
@section('content')
<div class="card" style="max-width:700px">
    <div class="card-header">
        <h2 class="card-title">{{ isset($warehouse) ? 'Edit Warehouse' : 'New Warehouse' }}</h2>
    </div>
    <form method="POST" action="{{ isset($warehouse) ? route('warehouses.update', $warehouse) : route('warehouses.store') }}">
        @csrf
        @if(isset($warehouse)) @method('PUT') @endif
        <div class="form-grid">
            <div class="form-group">
                <label>Name</label>
                <input type="text" name="name" value="{{ old('name', $warehouse->name ?? '') }}" required>
            </div>
            <div class="form-group">
                <label>Code</label>
                <input type="text" name="code" value="{{ old('code', $warehouse->code ?? '') }}">
            </div>
            <div class="form-group full">
                <label>Address</label>
                <textarea name="address">{{ old('address', $warehouse->address ?? '') }}</textarea>
            </div>
            <div class="form-group">
                <label>Phone</label>
                <input type="text" name="phone" value="{{ old('phone', $warehouse->phone ?? '') }}">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="is_active">
                    <option value="1" {{ old('is_active', $warehouse->is_active ?? 1) ? 'selected' : '' }}>Active</option>
                    <option value="0" {{ !old('is_active', $warehouse->is_active ?? 1) ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>
            <div class="form-group">
                <label>Default Warehouse</label>
                <select name="is_default">
                    <option value="0">No</option>
                    <option value="1" {{ old('is_default', $warehouse->is_default ?? 0) ? 'selected' : '' }}>Yes</option>
                </select>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Save</button>
            <a href="{{ route('warehouses.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
