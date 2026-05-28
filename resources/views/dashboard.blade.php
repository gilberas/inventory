@extends('layouts.dashboard')

@section('content')

<h1 class="text-3xl font-bold mb-6">
    Dashboard
</h1>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6">

    <div class="bg-white p-6 rounded-xl shadow hover:shadow-xl transition">
        <h2 class="text-gray-500">Total Products</h2>
        <p class="text-3xl font-bold mt-2">1,248</p>
    </div>

    <div class="bg-white p-6 rounded-xl shadow hover:shadow-xl transition">
        <h2 class="text-gray-500">Low Stock</h2>
        <p class="text-3xl font-bold mt-2 text-red-500">18</p>
    </div>

    <div class="bg-white p-6 rounded-xl shadow hover:shadow-xl transition">
        <h2 class="text-gray-500">Sales Today</h2>
        <p class="text-3xl font-bold mt-2 text-green-500">$12,430</p>
    </div>

    <div class="bg-white p-6 rounded-xl shadow hover:shadow-xl transition">
        <h2 class="text-gray-500">Pending Orders</h2>
        <p class="text-3xl font-bold mt-2 text-yellow-500">9</p>
    </div>

</div>

<!-- Charts -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-8">

    <div class="bg-white p-6 rounded-xl shadow">
        <h2 class="text-xl font-bold mb-4">Monthly Sales</h2>
        <div id="salesChart"></div>
    </div>

    <div class="bg-white p-6 rounded-xl shadow">
        <h2 class="text-xl font-bold mb-4">Inventory Status</h2>
        <div id="inventoryChart"></div>
    </div>

</div>

<!-- Recent Activity -->
<div class="bg-white p-6 rounded-xl shadow mt-8">

    <h2 class="text-xl font-bold mb-4">
        Recent Activity
    </h2>

    <ul class="space-y-3">

        <li class="border-b pb-2">
            ✔ Product Added - Dell Laptop
        </li>

        <li class="border-b pb-2">
            ✔ Stock Updated - Printer Ink
        </li>

        <li class="border-b pb-2">
            ✔ Sale Completed - ₱12,000
        </li>

    </ul>

</div>

<script>
    // Sales Chart
    var salesOptions = {
        chart: {
            type: 'area',
            height: 300
        },
        series: [{
            name: 'Sales',
            data: [1200, 1900, 3000, 5000, 4200, 6000]
        }],
        xaxis: {
            categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun']
        },
        colors: ['#3B82F6']
    };

    var salesChart = new ApexCharts(
        document.querySelector("#salesChart"),
        salesOptions
    );

    salesChart.render();

    // Inventory Pie Chart
    var inventoryOptions = {
        chart: {
            type: 'donut',
            height: 300
        },
        series: [44, 55, 13],
        labels: ['In Stock', 'Low Stock', 'Out of Stock'],
        colors: ['#10B981', '#F59E0B', '#EF4444']
    };

    var inventoryChart = new ApexCharts(
        document.querySelector("#inventoryChart"),
        inventoryOptions
    );

    inventoryChart.render();

</script>

@endsection