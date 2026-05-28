@extends('layouts.app')
@section('title', isset($brand) ? 'Edit Brand' : 'New Brand')
@section('content')
<div class="card" style="max-width:480px">
    <div class="card-header">
        <h2 class="card-title">{{ isset($brand) ? 'Edit Brand' : 'New Brand' }}</h2>
    </div>
    <form method="POST" action="{{ isset($brand) ? route('brands.update', $brand) : route('brands.store') }}">
        @csrf
        @if(isset($brand)) @method('PUT') @endif
        <div class="form-grid">
            <div class="form-group full">
                <label>Brand Name *</label>
                <input type="text" name="name" value="{{ old('name', $brand->name ?? '') }}" required>
            </div>
            <div class="form-group full">
                <label>Description</label>
                <textarea name="description">{{ old('description', $brand->description ?? '') }}</textarea>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Save</button>
            <a href="{{ route('brands.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
