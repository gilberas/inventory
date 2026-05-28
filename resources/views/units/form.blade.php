@extends('layouts.app')
@section('title', isset($unit) ? 'Edit Unit' : 'New Unit')
@section('content')
<div class="card" style="max-width:480px">
    <div class="card-header">
        <h2 class="card-title">{{ isset($unit) ? 'Edit Unit' : 'New Unit' }}</h2>
    </div>
    <form method="POST" action="{{ isset($unit) ? route('units.update', $unit) : route('units.store') }}">
        @csrf
        @if(isset($unit)) @method('PUT') @endif
        <div class="form-grid">
            <div class="form-group">
                <label>Unit Name *</label>
                <input type="text" name="name" value="{{ old('name', $unit->name ?? '') }}" placeholder="e.g. Kilogram" required>
            </div>
            <div class="form-group">
                <label>Abbreviation *</label>
                <input type="text" name="abbreviation" value="{{ old('abbreviation', $unit->abbreviation ?? '') }}" placeholder="e.g. kg" maxlength="10" required>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Save</button>
            <a href="{{ route('units.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
