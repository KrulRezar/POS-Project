<?php
// inventory.php - Inventory Management Module

// --- Dependency Includes ---

// NOTE: We assume 'config.php' defines the database connection ($conn) 
// and the 'require_auth()' function for session/login enforcement.
require_once 'config.php';
require_auth(); 

// RBAC: Only Admin and Manager can access this page
$is_admin = ($_SESSION['role_name'] === 'Admin');
$is_manager = ($_SESSION['role_name'] === 'Manager');
if (!$is_admin && !$is_manager) {
    // Redirect if unauthorized
    header("Location: dashboard.php");
    exit();
}

$message = ''; // Message for status updates (success/error)

// --- Sort Parameters Initialization ---
// Allowed sortable fields (whitelist)
$allowed_sorts = ['product_id', 'name', 'price', 'stock_quantity', 'category'];

// Default sort
$sort_by = 'product_id';
$sort_order = 'DESC'; // Default to descending for ID

// Check for user-requested sorting via URL parameters
if (isset($_GET['sort_by']) && in_array($_GET['sort_by'], $allowed_sorts)) {
    $sort_by = $_GET['sort_by'];
}

// Validate and set sort order
if (isset($_GET['sort_order']) && in_array(strtoupper($_GET['sort_order']), ['ASC', 'DESC'])) {
    $sort_order = strtoupper($_GET['sort_order']);
}


// --- CRUD Operations ---

// 1. ADD Product
if (isset($_POST['action']) && $_POST['action'] === 'add') {
    // Re-connect to DB if needed after closing the connection earlier for fetch (best practice to close/re-open if needed)
    $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

    $name = trim($_POST['name']);
    $price = (float)$_POST['price'];
    $quantity = (int)$_POST['quantity'];
    $category = trim($_POST['category']);

    $sql = "INSERT INTO products (name, price, stock_quantity, category) VALUES (?, ?, ?, ?)";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("sdis", $name, $price, $quantity, $category);
        if ($stmt->execute()) {
            $message = "<div class='bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-lg shadow-md mb-6'>Product added successfully!</div>";
        } else {
            $message = "<div class='bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg shadow-md mb-6'>Error adding product: " . $stmt->error . "</div>";
        }
        $stmt->close();
    }
}

// 2. DELETE Product (Handled via POST from the custom confirmation modal)
if (isset($_POST['action']) && $_POST['action'] === 'confirm_delete' && isset($_POST['id'])) {
    // Re-connect to DB if needed
    $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

    $id = (int)$_POST['id'];
    
    // Check for references in sale_items (prevents orphaned records)
    $check_sql = "SELECT COUNT(*) FROM sale_items WHERE product_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $id);
    $check_stmt->execute();
    $check_stmt->bind_result($count);
    $check_stmt->fetch();
    $check_stmt->close();

    if ($count > 0) {
           $message = "<div class='bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg shadow-md mb-6'>Cannot delete product ID $id. It has been sold in $count transactions.</div>";
    } else {
        $sql = "DELETE FROM products WHERE product_id = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $message = "<div class='bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-lg shadow-md mb-6'>Product deleted successfully!</div>";
            } else {
                $message = "<div class='bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg shadow-md mb-6'>Error deleting product: " . $stmt->error . "</div>";
            }
            $stmt->close();
        }
    }
}

// 3. EDIT (UPDATE) Product
if (isset($_POST['action']) && $_POST['action'] === 'edit') {
    // Re-connect to DB if needed
    $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

    $id = (int)$_POST['product_id'];
    $name = trim($_POST['name']);
    $price = (float)$_POST['price'];
    $quantity = (int)$_POST['stock_quantity']; 
    $category = trim($_POST['category']);

    $sql = "UPDATE products SET name = ?, price = ?, stock_quantity = ?, category = ? WHERE product_id = ?";
    if ($stmt = $conn->prepare($sql)) {
        // sdisi: String, Double, Integer, String, Integer
        $stmt->bind_param("sdisi", $name, $price, $quantity, $category, $id);
        if ($stmt->execute()) {
            $message = "<div class='bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-lg shadow-md mb-6'>Product ID $id updated successfully!</div>";
        } else {
            $message = "<div class='bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg shadow-md mb-6'>Error updating product: " . $stmt->error . "</div>";
        }
        $stmt->close();
    }
}

// Re-connect to DB if needed for the final data fetch
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }


// 4. FETCH all Products - UPDATED WITH DYNAMIC SORTING
$products = [];
// Use the validated variables in the SQL query
$sql = "SELECT product_id, name, price, stock_quantity, category FROM products 
        ORDER BY " . $sort_by . " " . $sort_order;

$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Ensure price is treated as a float
        $row['price'] = (float)$row['price'];
        $products[] = $row;
    }
}
$conn->close();

/**
 * Helper function to generate the sorting arrow icon HTML.
 * @param string $column_name The name of the column being checked.
 * @param string $current_sort_by The currently active sort column.
 * @param string $current_sort_order The currently active sort order (ASC/DESC).
 * @return string HTML for the arrow or empty string.
 */
function get_sort_arrow($column_name, $current_sort_by, $current_sort_order) {
    if ($column_name === $current_sort_by) {
        return $current_sort_order === 'ASC' 
            ? '<i class="fas fa-arrow-up ml-1 text-indigo-200"></i>' 
            : '<i class="fas fa-arrow-down ml-1 text-indigo-200"></i>';
    }
    return '';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management</title>
    <!-- Load Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Load Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="icon" type="image/png" href="favicon.png">
    <style>
        /* Custom font import for a professional look */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap');
        body {
            font-family: 'Inter', sans-serif;
        }
        /* Custom scrollbar style for tables */
        .overflow-x-auto::-webkit-scrollbar {
            height: 8px;
        }
        .overflow-x-auto::-webkit-scrollbar-thumb {
            background-color: #a5b4fc; /* Indigo-300 */
            border-radius: 4px;
        }
        .overflow-x-auto::-webkit-scrollbar-track {
            background: #eef2ff; /* Indigo-50 */
        }
    </style>
</head>
<body class="bg-gray-50 p-4 sm:p-8">
    
    <div class="max-w-7xl mx-auto bg-white p-4 sm:p-8 rounded-2xl shadow-2xl">
        
        <!-- Header -->
        <header class="mb-8 pb-4 border-b-2 border-indigo-100 flex flex-col sm:flex-row justify-between items-start sm:items-center">
            <h1 class="text-3xl sm:text-4xl font-extrabold text-gray-900 mb-2 sm:mb-0">
                <i class="fas fa-boxes-stacked mr-3 text-indigo-600"></i> Inventory Management
            </h1>
            <a href="dashboard.php" class="text-indigo-600 hover:text-indigo-700 font-semibold transition duration-150 flex items-center bg-indigo-50 p-2 rounded-lg hover:shadow-md">
                <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
            </a>
        </header>
        
        <!-- Status Message -->
        <?php echo $message; ?>

        <!-- Add New Product Form -->
        <div class="bg-indigo-50 p-6 rounded-xl shadow-inner mb-10 border border-indigo-200">
            <h2 class="text-xl font-bold text-indigo-700 mb-5 flex items-center">
                <i class="fas fa-plus-square mr-2"></i> Add New Product
            </h2>
            <form action="inventory.php" method="POST" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <input type="hidden" name="action" value="add">
                
                <input type="text" name="name" placeholder="Product Name" required
                        class="md:col-span-1 p-3 border border-indigo-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 shadow-sm transition duration-150">
                
                <input type="number" name="price" step="0.01" min="0.01" placeholder="Price (e.g., 237.50)" required
                        class="md:col-span-1 p-3 border border-indigo-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 shadow-sm transition duration-150">
                
                <input type="number" name="quantity" min="0" placeholder="Stock Quantity" required
                        class="md:col-span-1 p-3 border border-indigo-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 shadow-sm transition duration-150">

                <input type="text" name="category" placeholder="Category (e.g., Coffee)" 
                        class="md:col-span-1 p-3 border border-indigo-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 shadow-sm transition duration-150">
                
                <button type="submit" 
                        class="md:col-span-1 py-3 px-4 rounded-lg text-white font-bold text-lg bg-indigo-600 hover:bg-indigo-700 transition duration-150 shadow-md hover:shadow-lg">
                    <i class="fas fa-plus-circle mr-1"></i> Add Product
                </button>
            </form>
        </div>

        <!-- Current Inventory Table -->
        <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center">
            <i class="fas fa-list-ul mr-2"></i> Current Inventory
        </h2>
        <div class="overflow-x-auto rounded-xl border border-gray-200 shadow-lg">
            <table class="min-w-full bg-white">
                <thead class="bg-indigo-600 text-white shadow-md">
                    <tr>
                        <!-- ID Header -->
                        <th class="py-3 px-4 text-left text-sm font-bold uppercase tracking-wider rounded-tl-xl hover:bg-indigo-700 transition duration-150">
                            <?php 
                                $new_order = ($sort_by === 'product_id' && $sort_order === 'DESC') ? 'ASC' : 'DESC';
                                $arrow = get_sort_arrow('product_id', $sort_by, $sort_order);
                            ?>
                            <a href="inventory.php?sort_by=product_id&sort_order=<?php echo $new_order; ?>" class="flex items-center">
                                ID <?php echo $arrow; ?>
                            </a>
                        </th>
                        <!-- Name Header -->
                        <th class="py-3 px-4 text-left text-sm font-bold uppercase tracking-wider hover:bg-indigo-700 transition duration-150">
                            <?php 
                                $new_order = ($sort_by === 'name' && $sort_order === 'ASC') ? 'DESC' : 'ASC';
                                $arrow = get_sort_arrow('name', $sort_by, $sort_order);
                            ?>
                            <a href="inventory.php?sort_by=name&sort_order=<?php echo $new_order; ?>" class="flex items-center">
                                Name <?php echo $arrow; ?>
                            </a>
                        </th>
                        <!-- Category Header -->
                        <th class="py-3 px-4 text-left text-sm font-bold uppercase tracking-wider hover:bg-indigo-700 transition duration-150">
                            <?php 
                                $new_order = ($sort_by === 'category' && $sort_order === 'ASC') ? 'DESC' : 'ASC';
                                $arrow = get_sort_arrow('category', $sort_by, $sort_order);
                            ?>
                            <a href="inventory.php?sort_by=category&sort_order=<?php echo $new_order; ?>" class="flex items-center">
                                Category <?php echo $arrow; ?>
                            </a>
                        </th>
                        <!-- Price Header -->
                        <th class="py-3 px-4 text-left text-sm font-bold uppercase tracking-wider hover:bg-indigo-700 transition duration-150">
                            <?php 
                                $new_order = ($sort_by === 'price' && $sort_order === 'ASC') ? 'DESC' : 'ASC';
                                $arrow = get_sort_arrow('price', $sort_by, $sort_order);
                            ?>
                            <a href="inventory.php?sort_by=price&sort_order=<?php echo $new_order; ?>" class="flex items-center">
                                Price (₱) <?php echo $arrow; ?>
                            </a>
                        </th>
                        <!-- Stock Header -->
                        <th class="py-3 px-4 text-left text-sm font-bold uppercase tracking-wider hover:bg-indigo-700 transition duration-150">
                            <?php 
                                $new_order = ($sort_by === 'stock_quantity' && $sort_order === 'ASC') ? 'DESC' : 'ASC';
                                $arrow = get_sort_arrow('stock_quantity', $sort_by, $sort_order);
                            ?>
                            <a href="inventory.php?sort_by=stock_quantity&sort_order=<?php echo $new_order; ?>" class="flex items-center">
                                Stock <?php echo $arrow; ?>
                            </a>
                        </th>
                        <th class="py-3 px-4 text-center text-sm font-bold uppercase tracking-wider rounded-tr-xl">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr><td colspan="6" class="text-center py-6 text-gray-500 italic">No products found. Use the form above to add one.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($products as $product): ?>
                    <tr class="border-b border-gray-100 hover:bg-indigo-50 transition duration-100">
                        <td class="py-3 px-4 text-sm text-gray-600 font-mono"><?php echo htmlspecialchars($product['product_id']); ?></td>
                        <td class="py-3 px-4 text-sm text-gray-900 font-medium"><?php echo htmlspecialchars($product['name']); ?></td>
                        <td class="py-3 px-4 text-sm text-gray-600"><?php echo htmlspecialchars($product['category']); ?></td>
                        <td class="py-3 px-4 text-sm text-gray-900 font-semibold">₱<?php echo number_format($product['price'], 2); ?></td>
                        <td class="py-3 px-4 text-sm font-semibold">
                            <!-- Stock quantity with conditional color -->
                            <?php 
                                $quantity = (int)$product['stock_quantity'];
                                $stock_class = 'text-green-600 bg-green-100';
                                if ($quantity <= 5) {
                                    $stock_class = 'text-red-600 bg-red-100';
                                } else if ($quantity <= 20) {
                                    $stock_class = 'text-yellow-600 bg-yellow-100';
                                }
                            ?>
                            <span class="px-2 py-0.5 rounded-full <?php echo $stock_class; ?> inline-block min-w-[50px] text-center">
                                <?php echo htmlspecialchars($quantity); ?>
                            </span>
                        </td>
                        <td class="py-3 px-4 text-center space-x-3 whitespace-nowrap">
                            <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($product)); ?>)" 
                                    class="text-blue-600 hover:text-blue-800 text-sm font-medium transition duration-150 hover:bg-blue-100 p-1.5 rounded-md">
                                <i class="fas fa-edit mr-1"></i> Edit
                            </button>
                            <button onclick="openDeleteModal(<?php echo htmlspecialchars($product['product_id']); ?>, '<?php echo htmlspecialchars($product['name']); ?>')"
                                    class="text-red-600 hover:text-red-800 text-sm font-medium transition duration-150 hover:bg-red-100 p-1.5 rounded-md">
                                <i class="fas fa-trash mr-1"></i> Delete
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Edit Modal Structure -->
    <div id="editModal" class="fixed inset-0 bg-black bg-opacity-60 hidden items-center justify-center p-4 z-50 transition-opacity duration-300">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg p-8 relative transform scale-100 transition-transform duration-300">
            <!-- Close Button -->
            <button onclick="closeEditModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-700 transition">
                <i class="fas fa-times text-2xl"></i>
            </button>

            <h3 class="text-2xl font-bold text-gray-900 mb-6 border-b pb-2 text-indigo-700">
                <i class="fas fa-pencil-alt mr-2"></i> Edit Product
            </h3>
            
            <form action="inventory.php" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="product_id" id="edit-product-id">

                <div>
                    <label for="edit-name" class="block text-sm font-semibold text-gray-700 mb-1">Product Name</label>
                    <input type="text" name="name" id="edit-name" required
                            class="mt-1 block w-full p-3 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 shadow-sm">
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="edit-price" class="block text-sm font-semibold text-gray-700 mb-1">Price (₱)</label>
                        <input type="number" name="price" id="edit-price" step="0.01" min="0.01" required
                                class="mt-1 block w-full p-3 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 shadow-sm">
                    </div>
                    <div>
                        <label for="edit-stock-quantity" class="block text-sm font-semibold text-gray-700 mb-1">Stock Quantity</label>
                        <input type="number" name="stock_quantity" id="edit-stock-quantity" min="0" required
                                class="mt-1 block w-full p-3 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 shadow-sm">
                    </div>
                </div>

                <div>
                    <label for="edit-category" class="block text-sm font-semibold text-gray-700 mb-1">Category</label>
                    <input type="text" name="category" id="edit-category"
                            class="mt-1 block w-full p-3 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 shadow-sm">
                </div>

                <button type="submit"
                        class="w-full py-3 rounded-lg text-white font-bold text-lg bg-indigo-600 hover:bg-indigo-700 transition duration-150 mt-6 shadow-md hover:shadow-lg">
                    <i class="fas fa-save mr-1"></i> Save Changes
                </button>
            </form>
        </div>
    </div>
    
    <!-- Custom Delete Confirmation Modal Structure -->
    <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-60 hidden items-center justify-center p-4 z-50 transition-opacity duration-300">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm p-6 relative transform scale-100 transition-transform duration-300 border-t-8 border-red-500">
            <h3 class="text-xl font-bold text-red-600 mb-3 flex items-center">
                <i class="fas fa-exclamation-triangle mr-2"></i> Confirm Deletion
            </h3>
            
            <p class="text-gray-700 mb-6">Are you sure you want to delete product: <span id="delete-product-name" class="font-semibold text-gray-900"></span>?</p>
            <p class="text-sm text-red-500 italic mb-6">Warning: Deletion will fail if this product is associated with any previous sales transactions.</p>
            
            <form action="inventory.php" method="POST" class="flex justify-end space-x-3">
                <input type="hidden" name="action" value="confirm_delete">
                <input type="hidden" name="id" id="delete-product-id">
                
                <button type="button" onclick="closeDeleteModal()" 
                        class="py-2 px-4 rounded-lg bg-gray-200 text-gray-700 font-semibold hover:bg-gray-300 transition">
                    Cancel
                </button>
                <button type="submit" 
                        class="py-2 px-4 rounded-lg bg-red-600 text-white font-bold hover:bg-red-700 transition shadow-md">
                    <i class="fas fa-trash-alt mr-1"></i> Delete Permanently
                </button>
            </form>
        </div>
    </div>


    <script>
        /**
         * Closes the Edit Modal.
         */
        function closeEditModal() {
            document.getElementById('editModal').classList.remove('flex');
            document.getElementById('editModal').classList.add('hidden');
        }

        /**
         * Opens the Edit Modal and populates it with product data.
         */
        function openEditModal(product) {
            document.getElementById('edit-product-id').value = product.product_id;
            document.getElementById('edit-name').value = product.name;
            // Ensure price is formatted correctly for the input type="number"
            document.getElementById('edit-price').value = parseFloat(product.price).toFixed(2);
            document.getElementById('edit-stock-quantity').value = product.stock_quantity;
            document.getElementById('edit-category').value = product.category;
            
            document.getElementById('editModal').classList.remove('hidden');
            document.getElementById('editModal').classList.add('flex');
        }
        
        /**
         * Closes the Delete Confirmation Modal.
         */
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('flex');
            document.getElementById('deleteModal').classList.add('hidden');
        }

        /**
         * Opens the Delete Confirmation Modal and populates product ID/Name.
         */
        function openDeleteModal(id, name) {
            document.getElementById('delete-product-id').value = id;
            document.getElementById('delete-product-name').textContent = name;
            
            document.getElementById('deleteModal').classList.remove('hidden');
            document.getElementById('deleteModal').classList.add('flex');
        }
    </script>

</body>
</html>