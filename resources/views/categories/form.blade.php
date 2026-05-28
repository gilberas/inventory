@extends('layouts.app')
@section('title', isset($category) ? 'Edit Category' : 'New Category')
@section('content')
<div class="card" style="max-width:500px">
    <div class="card-header">
        <h2 class="card-title">{{ isset($category) ? 'Edit Category' : 'New Category' }}</h2>
    </div>
    <form method="POST" action="{{ isset($category) ? route('categories.update', $category) : route('categories.store') }}">
        @csrf
        @if(isset($category)) @method('PUT') @endif
        <div class="form-grid">
            <div class="form-group full">
                <label>Category Name *</label>
                <input type="text" name="name" value="{{ old('name', $category->name ?? '') }}" required>
            </div>
            <div class="form-group full">
                <label>Parent Category</label>
                <select name="parent_id">
                    <option value="">None (top-level)</option>
                    @foreach($parents as $parent)
                        <option value="{{ $parent->id }}" {{ old('parent_id', $category->parent_id ?? '') == $parent->id ? 'selected' : '' }}>
                            {{ $parent->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group full">
                <label>Description</label>
                <textarea name="description">{{ old('description', $category->description ?? '') }}</textarea>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Save</button>
            <a href="{{ route('categories.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
