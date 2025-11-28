<?php
// analytics.php
// This page provides a comprehensive overview of sales performance, accessible only to Administrators.

// Start the core services and security checks
require_once 'config.php';

// --- SECURITY CHECK: Restrict access to Admins only ---
// The 'Admin' role is required to view sensitive financial data.
require_auth('Admin'); 

// --- DATABASE: Data Retrieval Queries for Dashboard ---

// 1. Overall Business Summary (Total Revenue and Transactions)
// We calculate the lifetime stats by summing all records in the 'sales' table.
$total_sales_summary = ['total_transactions' => 0, 'total_revenue' => 0.00];
$sql_summary = "SELECT COUNT(sale_id) AS total_transactions, SUM(total_amount) AS total_revenue 
                FROM sales";
$result_summary = $conn->query($sql_summary);

// Pull the results into the summary array for easy display
if ($result_summary && $row = $result_summary->fetch_assoc()) {
    $total_sales_summary['total_transactions'] = (int)$row['total_transactions'];
    // PHP casts result to float for accurate monetary calculation
    $total_sales_summary['total_revenue'] = (float)$row['total_revenue']; 
}

// 2. Top 5 Best Selling Products (Ranked by Quantity Sold)
// Uses JOINs to link product names to the quantity sold in the sale_items table, then aggregates by name.
$top_products = [];
$sql_top = "SELECT p.name, SUM(si.quantity) AS total_sold
            FROM sale_items si
            JOIN products p ON si.product_id = p.product_id
            GROUP BY p.name
            ORDER BY total_sold DESC
            LIMIT 5";
$result_top = $conn->query($sql_top);
if ($result_top) {
    while ($row = $result_top->fetch_assoc()) {
        $top_products[] = $row;
    }
}

// Calculate the maximum sold quantity for relative bar calculation in the UI
$max_sold = 0;
if (!empty($top_products)) {
    // top_products is already sorted DESC, so the first element is the max
    $max_sold = $top_products[0]['total_sold'];
}

// 3. Recent Transactions (Last 10 Sales)
// Joining 'sales' with 'users' allows us to display the Cashier's username for accountability.
$recent_sales = [];
$sql_recent = "SELECT s.sale_id, s.total_amount, s.sale_date, u.username 
               FROM sales s
               JOIN users u ON s.user_id = u.user_id
               ORDER BY s.sale_date DESC
               LIMIT 10";
$result_recent = $conn->query($sql_recent);
if ($result_recent) {
    while ($row = $result_recent->fetch_assoc()) {
        $recent_sales[] = $row;
    }
}

// Good practice: close the database connection immediately after fetching data
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Analytics | Admin</title>
    <!-- Load Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Load Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Configure Tailwind for custom colors (optional, using default colors for simplicity) -->
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap');
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen p-4 sm:p-8">
    
    <div class="max-w-7xl mx-auto bg-white p-6 sm:p-8 rounded-2xl shadow-2xl border border-gray-100">
        <!-- Page Header & Navigation -->
        <header class="mb-8 pb-4 border-b-2 border-indigo-100 flex flex-col sm:flex-row justify-between items-start sm:items-center">
            <h1 class="text-4xl font-extrabold text-indigo-800 mb-3 sm:mb-0">
                <i class="fas fa-chart-bar mr-3 text-indigo-500"></i> Sales Analytics Overview
            </h1>
            <a href="dashboard.php" class="inline-flex items-center text-sm font-semibold text-gray-500 hover:text-indigo-600 transition duration-150">
                <i class="fas fa-arrow-left mr-1"></i> Back to Dashboard
            </a>
        </header>

        <!-- Main Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
            
            <!-- Total Transactions Card -->
            <div class="p-6 rounded-xl shadow-lg transition duration-300 hover:shadow-xl bg-white border-l-4 border-indigo-500">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-medium text-gray-500 uppercase tracking-wider">Total Transactions</p>
                    <i class="fas fa-cash-register text-indigo-400 text-2xl"></i>
                </div>
                <p class="text-5xl font-extrabold text-indigo-800 mt-2">
                    <?php echo number_format($total_sales_summary['total_transactions']); ?>
                </p>
                <span class="text-xs text-gray-400 mt-2 block">Lifetime Count</span>
            </div>

            <!-- Total Revenue Card -->
            <div class="p-6 rounded-xl shadow-lg transition duration-300 hover:shadow-xl bg-white border-l-4 border-teal-500">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-medium text-gray-500 uppercase tracking-wider">Total Revenue</p>
                    <i class="fas fa-dollar-sign text-teal-400 text-2xl"></i>
                </div>
                <p class="text-5xl font-extrabold text-teal-800 mt-2">
                    ₱<?php echo number_format($total_sales_summary['total_revenue'], 2); ?>
                </p>
                <span class="text-xs text-gray-400 mt-2 block">All-time earnings</span>
            </div>

            <!-- Average Sale Value Card -->
            <div class="p-6 rounded-xl shadow-lg transition duration-300 hover:shadow-xl bg-white border-l-4 border-amber-500">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-medium text-gray-500 uppercase tracking-wider">Average Sale Value</p>
                    <i class="fas fa-receipt text-amber-400 text-2xl"></i>
                </div>
                <p class="text-5xl font-extrabold text-amber-800 mt-2">
                    <?php 
                        // Calculate average only if transactions exist to avoid division by zero
                        if ($total_sales_summary['total_transactions'] > 0) {
                            $avg = $total_sales_summary['total_revenue'] / $total_sales_summary['total_transactions'];
                            echo '₱' . number_format($avg, 2);
                        } else {
                            echo '₱0.00';
                        }
                    ?>
                </p>
                <span class="text-xs text-gray-400 mt-2 block">Per transaction mean</span>
            </div>
        </div>

        <!-- Detail Panels (Top Products and Recent Sales) -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            
            <!-- Top 5 Products List -->
            <div class="bg-white p-6 rounded-xl shadow-lg border border-gray-100">
                <h2 class="text-2xl font-bold text-gray-800 mb-5 border-b pb-3 flex items-center">
                    <i class="fas fa-trophy mr-2 text-yellow-500"></i> Top 5 Best Selling Products
                </h2>
                <ul class="space-y-4">
                    <?php if (empty($top_products)): ?>
                        <p class="text-gray-500 py-4 text-center">No sales data available yet.</p>
                    <?php endif; ?>
                    <?php foreach ($top_products as $i => $product): 
                        // Calculate percentage of sales relative to the top product for the bar indicator
                        $percentage = $max_sold > 0 ? ($product['total_sold'] / $max_sold) * 100 : 0;
                    ?>
                        <li class="p-3 bg-gray-50 rounded-lg transition duration-150 hover:bg-gray-100">
                            <div class="flex justify-between items-start mb-1">
                                <span class="text-lg font-semibold text-gray-700">
                                    <span class="w-6 inline-block text-center mr-1 font-extrabold text-indigo-600"><?php echo $i + 1; ?>.</span>
                                    <?php echo htmlspecialchars($product['name']); ?>
                                </span>
                                <span class="font-bold text-sm bg-indigo-500 text-white px-3 py-1 rounded-full shadow-md">
                                    <?php echo number_format($product['total_sold']); ?> units
                                </span>
                            </div>
                            <!-- Visual Bar Indicator -->
                            <div class="w-full bg-gray-200 rounded-full h-2.5">
                                <div class="bg-indigo-400 h-2.5 rounded-full transition-all duration-500" style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Recent Transactions Table -->
            <div class="bg-white p-6 rounded-xl shadow-lg border border-gray-100">
                <h2 class="text-2xl font-bold text-gray-800 mb-5 border-b pb-3 flex items-center">
                    <i class="fas fa-history mr-2 text-blue-500"></i> Last 10 Recent Transactions
                </h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                <th class="py-3 px-4">ID</th>
                                <th class="py-3 px-4 text-right">Amount (₱)</th>
                                <th class="py-3 px-4">Cashier</th>
                                <th class="py-3 px-4">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($recent_sales)): ?>
                                <tr><td colspan="4" class="text-center py-6 text-gray-500 italic">No recent sales found. Start selling!</td></tr>
                            <?php endif; ?>
                            <?php foreach ($recent_sales as $sale): ?>
                            <tr class="text-sm hover:bg-indigo-50 transition duration-100">
                                <td class="py-3 px-4 font-mono text-gray-600"><?php echo $sale['sale_id']; ?></td>
                                <td class="py-3 px-4 text-right font-extrabold text-teal-600">
                                    ₱<?php echo number_format($sale['total_amount'], 2); ?>
                                </td>
                                <td class="py-3 px-4 text-gray-700">
                                    <i class="fas fa-user-circle mr-1 text-gray-400"></i><?php echo htmlspecialchars($sale['username']); ?>
                                </td>
                                <td class="py-3 px-4 text-xs text-gray-500">
                                    <?php echo date('M d, Y | h:i A', strtotime($sale['sale_date'])); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</body>
</html>