@if (session('success'))
    <div class="alert alert-success" id="flash-success">
        <i class="fas fa-circle-check"></i>
        <span>{{ session('success') }}</span>
        <button onclick="this.parentElement.remove()" style="margin-left:auto;background:none;border:none;color:inherit;cursor:pointer;font-size:1rem;">&times;</button>
    </div>
@endif

@if (session('error'))
    <div class="alert alert-error" id="flash-error">
        <i class="fas fa-circle-xmark"></i>
        <span>{{ session('error') }}</span>
        <button onclick="this.parentElement.remove()" style="margin-left:auto;background:none;border:none;color:inherit;cursor:pointer;font-size:1rem;">&times;</button>
    </div>
@endif

@if (session('warning'))
    <div class="alert alert-warning" id="flash-warning">
        <i class="fas fa-triangle-exclamation"></i>
        <span>{{ session('warning') }}</span>
        <button onclick="this.parentElement.remove()" style="margin-left:auto;background:none;border:none;color:inherit;cursor:pointer;font-size:1rem;">&times;</button>
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-error">
        <i class="fas fa-circle-xmark"></i>
        <ul style="list-style:none;padding:0;margin:0;">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<script>
// Auto-dismiss flash messages after 5s
['flash-success','flash-error','flash-warning'].forEach(id => {
    const el = document.getElementById(id);
    if (el) setTimeout(() => el.remove(), 5000);
});
</script>
