<?php
// dashboard.php
// This is the main Point of Sale (POS) application terminal.
// It displays available products, manages the current cart, and handles the checkout process via AJAX.

// --- PHP Configuration and Security Setup ---

// 1. Load essential configuration (database connection, session handling).
require_once 'config.php';

// 2. Enforce authentication. Only logged-in users can access this page.
// No specific role is required by default, as this is the primary terminal.
require_auth(); 

// 3. Determine user role permissions for navigation (Role-Based Access Control).
$is_admin = ($_SESSION['role_name'] === 'Admin');
$is_manager = ($_SESSION['role_name'] === 'Manager');

// Permissions definition:
$can_manage_inventory = $is_admin || $is_manager; // Admins and Managers can access inventory
$can_manage_analytics = $is_admin; 			     // Only Admins can access analytics

// 4. Fetch Products Data for the POS terminal view.
$products = [];
// Select essential fields, order by name for display.
$sql = "SELECT product_id, name, price, stock_quantity, category FROM products ORDER BY name";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}
// Close the database connection once all data is fetched.
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS Dashboard | Terminal</title>
    <!-- Load Tailwind CSS for modern styling -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Load Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Custom Styles for Scrollbars (better UX for long lists) and Printing -->
    <style>
        .custom-scroll::-webkit-scrollbar { width: 6px; }
        .custom-scroll::-webkit-scrollbar-thumb { background-color: #7c3aed; border-radius: 3px; }
        .custom-scroll::-webkit-scrollbar-track { background-color: #e5e7eb; }
        .product-card-hover:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            transform: translateY(-2px);
        }
        /* Hide everything except the receipt modal when printing */
        @media print {
            body > * {
                visibility: hidden;
                overflow: hidden !important;
            }
            #receiptModal, #receiptModal * {
                visibility: visible;
            }
            /* Position receipt modal content for printing */
            #receiptModal {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: auto;
                background: none; /* Remove background overlay */
                display: block !important;
            }
            #receiptModal > div {
                box-shadow: none;
                max-width: none;
                margin: 0 auto;
                padding: 10mm;
            }
            #closeReceiptModal, #printReceiptBtn {
                display: none !important; /* Hide controls from printout */
            }
        }
    </style>
</head>
<body class="bg-gray-50 font-sans antialiased">

<div class="flex h-screen overflow-hidden">
    
    <!-- Sidebar Navigation -->
    <aside class="w-64 bg-gray-900 text-white flex flex-col p-4 shadow-2xl">
        <div class="text-3xl font-extrabold mb-10 text-indigo-400 border-b border-gray-700 pb-4">
            <i class="fas fa-store mr-2"></i> POS Central
        </div>
        <nav class="flex-grow space-y-3">
            <!-- Current Page Link -->
            <a href="dashboard.php" class="flex items-center p-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 transition duration-150 font-semibold shadow-lg">
                <i class="fas fa-cash-register mr-3"></i> POS Terminal
            </a>

            <!-- Inventory Link (Role restricted) -->
            <?php if ($can_manage_inventory): ?>
            <a href="inventory.php" class="flex items-center p-3 rounded-xl text-gray-300 hover:bg-gray-800 hover:text-white transition duration-150">
                <i class="fas fa-boxes-stacked mr-3"></i> Products/Inventory
            </a>
            <?php endif; ?>

            <!-- Analytics Link (Admin restricted) -->
            <?php if ($can_manage_analytics): ?>
            <a href="analytics.php" class="flex items-center p-3 rounded-xl text-gray-300 hover:bg-gray-800 hover:text-white transition duration-150">
                <i class="fas fa-chart-line mr-3"></i> Sales Analytics
            </a>
            <?php endif; ?>
        </nav>
        
        <!-- User Profile and Logout -->
        <div class="border-t border-gray-700 pt-4 mt-auto">
            <p class="text-sm font-semibold mb-1">User: <?php echo htmlspecialchars($_SESSION['username'] ?? 'N/A'); ?></p>
            <p class="text-xs text-indigo-300 mb-4">Role: <?php echo htmlspecialchars($_SESSION['role_name'] ?? 'N/A'); ?></p>
            <a href="logout.php" class="flex items-center justify-center p-2 rounded-xl bg-red-600 hover:bg-red-700 transition duration-150 text-sm font-medium shadow-md">
                <i class="fas fa-sign-out-alt mr-2"></i> Logout
            </a>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main class="flex-1 overflow-y-auto p-6 md:p-8">
        <header class="mb-8 pb-4 border-b border-gray-200">
            <h1 class="text-3xl font-extrabold text-gray-900">POS Terminal</h1>
            <p class="text-sm text-gray-500">
                Welcome, <?php echo htmlspecialchars($_SESSION['role_name'] ?? 'User'); ?>. Process sales quickly and efficiently.
            </p>
        </header>

        <div id="pos-terminal" class="grid grid-cols-1 lg:grid-cols-3 gap-6 h-[85vh]">
            
            <!-- Product Selection Area -->
            <div class="lg:col-span-2 bg-white rounded-xl shadow-xl p-5 flex flex-col">
                <h2 class="text-xl font-bold mb-4 text-gray-800 border-b pb-3"><i class="fas fa-boxes-stacked mr-2 text-indigo-500"></i> Product Catalog</h2>
                
                <!-- Search and Filter Bar -->
                <div class="mb-4 flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-4">
                    <input type="text" id="productSearch" placeholder="Search by name..." 
                            class="flex-1 p-3 border border-gray-300 rounded-xl focus:ring-indigo-500 focus:border-indigo-500 transition duration-150">
                    <select id="productCategory" class="p-3 border border-gray-300 rounded-xl w-full sm:w-48">
                        <option value="">All Categories</option>
                    </select>
                </div>

                <!-- Product Grid -->
                <div id="productGrid" class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4 overflow-y-auto custom-scroll flex-grow">
                    <!-- Product cards will be rendered here by JavaScript -->
                </div>
            </div>

            <!-- Shopping Cart Area -->
            <div class="lg:col-span-1 bg-white rounded-xl shadow-xl p-5 flex flex-col h-full">
                <h2 class="text-xl font-bold mb-4 text-gray-800 border-b pb-3"><i class="fas fa-shopping-cart mr-2 text-green-500"></i> Current Cart</h2>
                
                <!-- Cart Items List -->
                <div id="cartItems" class="flex-grow overflow-y-auto custom-scroll border-b border-gray-200 mb-4 space-y-3 p-1">
                    <p class="text-center text-gray-500 mt-4">Cart is empty.</p>
                </div>

                <!-- Totals Summary -->
                <div class="space-y-3 border-t pt-4">
                    <div class="flex justify-between font-medium text-gray-700">
                        <span>Subtotal:</span>
                        <span id="subTotal" class="font-mono">₱0.00</span>
                    </div>
                    <div class="flex justify-between font-medium text-gray-700">
                        <span>Tax (10%):</span>
                        <span id="taxAmount" class="font-mono">₱0.00</span>
                    </div>
                    <div class="flex justify-between font-extrabold text-2xl text-indigo-600 border-t border-indigo-200 pt-3">
                        <span>TOTAL:</span>
                        <span id="grandTotal" class="font-mono">₱0.00</span>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="mt-6 space-y-3">
                    <button id="checkoutBtn" disabled
                            class="w-full py-4 rounded-xl text-white font-bold bg-green-600 hover:bg-green-700 disabled:bg-gray-400 transition duration-150 shadow-lg transform hover:scale-[1.01]">
                        <i class="fas fa-credit-card mr-2"></i> Complete Sale
                    </button>
                    <button id="clearCartBtn" disabled
                            class="w-full py-3 rounded-xl text-red-700 font-medium bg-red-100 hover:bg-red-200 disabled:text-gray-500 disabled:bg-gray-100 transition duration-150">
                        <i class="fas fa-trash-alt mr-2"></i> Clear Cart
                    </button>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- --------------------------------- -->
<!-- MODAL 1: Transaction Receipt Modal -->
<!-- --------------------------------- -->
<div id="receiptModal" class="fixed inset-0 bg-black bg-opacity-60 hidden items-center justify-center p-4 z-50 transition-opacity duration-300">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm p-6 relative transform transition-all duration-300 scale-95 opacity-0" data-modal-target="receiptModal">
        <button id="closeReceiptModal" class="absolute top-3 right-3 text-gray-400 hover:text-gray-600 p-2">
            <i class="fas fa-times text-xl"></i>
        </button>

        <div class="text-center border-b border-gray-200 pb-4 mb-4">
            <h3 class="text-3xl font-extrabold text-green-600 mb-1">Sale Complete!</h3>
            <p class="text-xs text-gray-500">New POS Shop - Official Receipt</p>
        </div>

        <div id="receiptDetails" class="space-y-2 text-sm">
            <div class="flex justify-between border-b border-dashed pb-1">
                <span class="font-semibold text-gray-600">Sale ID:</span>
                <span id="receiptSaleId" class="font-mono text-gray-900">#0000</span>
            </div>
            <div class="flex justify-between border-b border-dashed pb-1">
                <span class="font-semibold text-gray-600">Date:</span>
                <span id="receiptDate" class="text-gray-900">2023-10-27 10:00 AM</span>
            </div>
            <div class="flex justify-between border-b border-dashed pb-1">
                <span class="font-semibold text-gray-600">Cashier:</span>
                <span id="receiptCashier" class="text-gray-900"><?php echo htmlspecialchars($_SESSION['username'] ?? 'N/A'); ?></span>
            </div>
        </div>

        <div class="mt-6 border-b pb-2">
            <h4 class="font-bold text-base text-gray-700">Items Purchased:</h4>
            <ul id="receiptItemsList" class="space-y-1 mt-2 text-sm text-gray-800">
                <!-- Items list populated by JS -->
            </ul>
        </div>
        
        <div class="mt-4 space-y-2 text-base">
            <div class="flex justify-between font-medium">
                <span>Subtotal:</span>
                <span id="receiptSubTotal" class="font-mono">₱0.00</span>
            </div>
            <div class="flex justify-between font-medium">
                <span>Tax (10%):</span>
                <span id="receiptTaxAmount" class="font-mono">₱0.00</span>
            </div>
            <div class="flex justify-between font-extrabold text-xl pt-3 border-t border-gray-300">
                <span>TOTAL:</span>
                <span id="receiptGrandTotal" class="text-green-600 font-mono">₱0.00</span>
            </div>
        </div>

        <!-- NEW: Print Receipt Button -->
        <div class="mt-6">
            <button id="printReceiptBtn" class="w-full py-2 rounded-xl text-white font-bold bg-indigo-600 hover:bg-indigo-700 transition duration-150 shadow-md">
                <i class="fas fa-print mr-2"></i> Print Receipt
            </button>
        </div>
        
        <div class="mt-4 text-center text-xs text-gray-500">
            ** Thank you for your business! **
        </div>
    </div>
</div>

<!-- --------------------------------- -->
<!-- MODAL 2: Generic Alert/Confirm Modal -->
<!-- --------------------------------- -->
<div id="customModal" class="fixed inset-0 bg-black bg-opacity-60 hidden items-center justify-center p-4 z-50 transition-opacity duration-300">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm p-6 relative transform transition-all duration-300 scale-95 opacity-0" data-modal-target="customModal">
        <h3 id="customModalTitle" class="text-xl font-bold mb-4 text-gray-900"></h3>
        <p id="customModalMessage" class="text-gray-700 mb-6"></p>
        <div class="flex justify-end space-x-3">
            <button id="customModalCancel" class="py-2 px-4 rounded-xl text-gray-700 bg-gray-200 hover:bg-gray-300 transition duration-150 hidden">Cancel</button>
            <button id="customModalConfirm" class="py-2 px-4 rounded-xl text-white bg-indigo-600 hover:bg-indigo-700 transition duration-150">OK</button>
        </div>
    </div>
</div>


<script>
    // --- PHP Data to JS ---
    const PRODUCTS = <?php echo json_encode($products); ?>; 
    const TAX_RATE = 0.10; // 10% VAT/Tax rate

    // --- State Variables ---
    let cart = {}; // { productId: { product, quantity } }

    // --- DOM Elements ---
    const productGrid = document.getElementById('productGrid');
    const subTotalElem = document.getElementById('subTotal');
    const taxAmountElem = document.getElementById('taxAmount');
    const grandTotalElem = document.getElementById('grandTotal');
    const checkoutBtn = document.getElementById('checkoutBtn');
    const clearCartBtn = document.getElementById('clearCartBtn');
    const productSearch = document.getElementById('productSearch');
    const productCategory = document.getElementById('productCategory');
    const cartItemsList = document.getElementById('cartItems');

    // Receipt Modal Elements
    const receiptModal = document.getElementById('receiptModal');
    const closeReceiptModalBtn = document.getElementById('closeReceiptModal');
    const printReceiptBtn = document.getElementById('printReceiptBtn'); // NEW: Print Button
    const receiptItemsList = document.getElementById('receiptItemsList');
    const receiptSubTotalElem = document.getElementById('receiptSubTotal');
    const receiptTaxAmountElem = document.getElementById('receiptTaxAmount');
    const receiptGrandTotalElem = document.getElementById('receiptGrandTotal');
    const receiptSaleIdElem = document.getElementById('receiptSaleId');
    const receiptDateElem = document.getElementById('receiptDate');
    
    // Custom Alert/Confirm Modal Elements
    const customModal = document.getElementById('customModal');
    const customModalTitle = document.getElementById('customModalTitle');
    const customModalMessage = document.getElementById('customModalMessage');
    const customModalConfirm = document.getElementById('customModalConfirm');
    const customModalCancel = document.getElementById('customModalCancel');


    /**
     * Helper function to format currency to PHP Pesos (₱)
     */
    const formatCurrency = (amount) => {
        // Formats as ₱1,234.56
        return `₱${parseFloat(amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",")}`;
    };
    
    /**
     * Custom Modal Handler (Replaces alert() and confirm())
     * @param {string} title - Title of the modal.
     * @param {string} message - Content message.
     * @param {boolean} isConfirm - If true, displays the Cancel button for confirmation.
     * @param {function} callback - Function to execute on confirm/OK.
     */
    function showCustomModal(title, message, isConfirm = false, callback = () => {}) {
        customModalTitle.textContent = title;
        customModalMessage.textContent = message;

        customModalConfirm.textContent = isConfirm ? 'Yes, Clear' : 'OK';
        customModalCancel.classList.toggle('hidden', !isConfirm);
        
        // Ensure modal elements have correct initial state for transition
        const modalContent = customModal.querySelector('[data-modal-target="customModal"]');
        modalContent.classList.remove('scale-100', 'opacity-100');
        modalContent.classList.add('scale-95', 'opacity-0');

        customModal.classList.remove('hidden');
        
        setTimeout(() => {
            modalContent.classList.remove('scale-95', 'opacity-0');
            modalContent.classList.add('scale-100', 'opacity-100');
        }, 10); // Small delay to trigger transition

        // Clear existing listeners
        customModalConfirm.onclick = null;
        customModalCancel.onclick = null;
        
        // Handle Confirm/OK click
        customModalConfirm.onclick = () => {
            customModal.classList.add('hidden');
            callback(true);
        };

        // Handle Cancel click (for confirmations)
        customModalCancel.onclick = () => {
            customModal.classList.add('hidden');
            callback(false); // Pass false for cancellation
        };
    }

    /**
     * Finds and updates the stock quantity for a product in the client-side PRODUCTS array.
     */
    function updateProductStockInJS(productId, quantityChange) {
        const productIndex = PRODUCTS.findIndex(p => p.product_id == productId);
        if (productIndex !== -1) {
            // Update stock quantity (positive or negative change)
            PRODUCTS[productIndex].stock_quantity = parseInt(PRODUCTS[productIndex].stock_quantity) + quantityChange;
            // Re-filter and re-render the grid to reflect the new stock level
            filterProducts(); 
        }
    }

    /**
     * Updates the UI elements for subtotal, tax, and grand total.
     */
    function updateCartTotals() {
        let subtotal = 0;
        for (const id in cart) {
            subtotal += cart[id].product.price * cart[id].quantity;
        }

        const tax = subtotal * TAX_RATE;
        const grandTotal = subtotal + tax;

        // Update display elements
        subTotalElem.textContent = formatCurrency(subtotal);
        taxAmountElem.textContent = formatCurrency(tax);
        grandTotalElem.textContent = formatCurrency(grandTotal);

        // Enable/Disable checkout and clear buttons
        const hasItems = Object.keys(cart).length > 0;
        checkoutBtn.disabled = !hasItems;
        clearCartBtn.disabled = !hasItems;

        return { subtotal, tax, grandTotal };
    }

    /**
     * Renders the current state of the cart to the UI.
     */
    function renderCart() {
        cartItemsList.innerHTML = '';
        const cartItemsArray = Object.values(cart);

        if (cartItemsArray.length === 0) {
            cartItemsList.innerHTML = '<p class="text-center text-gray-500 mt-4 p-4 text-sm bg-gray-50 rounded-lg">Scan or click a product to begin a sale.</p>';
            updateCartTotals();
            return;
        }

        cartItemsArray.forEach(item => {
            const itemElem = document.createElement('div');
            // Enhanced styling for cart items
            itemElem.className = 'flex items-center justify-between p-3 bg-white border border-indigo-100 rounded-xl shadow-sm hover:shadow-md transition duration-150';
            itemElem.innerHTML = `
                <div class="flex-1 min-w-0">
                    <p class="text-base font-semibold truncate text-gray-800">${item.product.name}</p>
                    <p class="text-xs text-indigo-500">${formatCurrency(item.product.price)} x ${item.quantity}</p>
                </div>
                <div class="flex items-center space-x-2">
                    <!-- Quantity total display -->
                    <span class="font-extrabold text-base text-indigo-700 w-20 text-right">${formatCurrency(item.product.price * item.quantity)}</span>
                    <!-- Decrease quantity button -->
                    <button data-id="${item.product.product_id}" class="remove-item-btn text-red-500 hover:text-white p-2 rounded-full bg-red-100 hover:bg-red-500 transition duration-150 shadow-sm">
                        <i class="fas fa-minus text-xs"></i>
                    </button>
                </div>
            `;
            cartItemsList.appendChild(itemElem);
        });

        // Attach event listeners to all newly rendered remove buttons
        document.querySelectorAll('.remove-item-btn').forEach(button => {
            button.addEventListener('click', (e) => {
                const productId = e.currentTarget.getAttribute('data-id');
                removeItemFromCart(productId);
            });
        });

        updateCartTotals();
    }

    /**
     * Adds an item to the cart or increments its quantity, with stock limit check.
     */
    function addItemToCart(product) {
        const id = product.product_id;
        const currentStock = parseInt(product.stock_quantity);

        // 1. Initial check: Is product in stock?
        if (currentStock <= 0) {
            showCustomModal("Out of Stock", `Sorry, "${product.name}" is currently sold out and cannot be added.`, false);
            return;
        }
        
        // 2. Cart limit check: Will adding one more exceed stock?
        if (cart[id]) {
            if (cart[id].quantity >= currentStock) {
                 showCustomModal("Stock Limit Reached", `Cannot add more of "${product.name}". Only ${currentStock} available in stock.`, false);
                 return;
            }
            cart[id].quantity += 1; // Increment quantity
        } else {
            // Add new item to cart
            cart[id] = { product: product, quantity: 1 };
        }
        renderCart();
    }

    /**
     * Decrements the quantity of an item in the cart, removing it if quantity reaches zero.
     */
    function removeItemFromCart(productId) {
        if (cart[productId]) {
            if (cart[productId].quantity > 1) {
                cart[productId].quantity -= 1;
            } else {
                delete cart[productId]; // Remove item if quantity hits 0
            }
        }
        renderCart();
    }

    /**
     * Renders the product cards based on current search and filter criteria.
     */
    function renderProductGrid(filteredProducts = PRODUCTS) {
        productGrid.innerHTML = '';

        if (filteredProducts.length === 0) {
            productGrid.innerHTML = '<p class="col-span-full text-center text-gray-500 p-8">No products match your search or filter criteria.</p>';
            return;
        }

        filteredProducts.forEach(product => {
            const card = document.createElement('div');
            const currentStock = parseInt(product.stock_quantity);
            const isDisabled = currentStock <= 0;
            
            // Enhanced card styling
            card.className = `product-card-hover cursor-pointer rounded-xl transition duration-200 p-3 shadow-md ${isDisabled ? 'bg-gray-100 opacity-70' : 'bg-white border border-gray-100'}`;
            
            card.innerHTML = `
                <div class="flex flex-col h-full">
                    <p class="text-sm font-semibold truncate text-gray-800">${product.name}</p>
                    <p class="text-xs text-indigo-500 mb-2">${product.category}</p>
                    <div class="flex justify-between items-center mt-auto pt-2">
                        <span class="text-xl font-extrabold text-green-600">${formatCurrency(product.price)}</span>
                        <span class="text-xs text-gray-500 border border-gray-300 rounded-full px-2 py-0.5 ${currentStock < 10 && currentStock > 0 ? 'bg-yellow-100 text-yellow-800 font-bold' : ''}">
                            Stock: ${currentStock}
                        </span>
                    </div>
                    <!-- Add button inside the card -->
                    <button class="w-full mt-3 py-2 text-sm rounded-lg font-medium transition duration-150 shadow-sm ${isDisabled ? 'bg-gray-400 cursor-not-allowed text-gray-700' : 'bg-indigo-600 text-white hover:bg-indigo-700'}" ${isDisabled ? 'disabled' : ''}>
                        <i class="fas fa-plus mr-1"></i> ${isDisabled ? 'Out of Stock' : 'Add to Cart'}
                    </button>
                </div>
            `;
            
            if (!isDisabled) {
                // Use the button for the click event
                card.querySelector('button').addEventListener('click', () => addItemToCart(product));
            }

            productGrid.appendChild(card);
        });
    }

    /**
     * Filters the product list based on search term and category selection.
     */
    function filterProducts() {
        const searchTerm = productSearch.value.toLowerCase();
        const category = productCategory.value;

        const filtered = PRODUCTS.filter(product => {
            const nameMatch = product.name.toLowerCase().includes(searchTerm);
            const categoryMatch = category === "" || product.category === category;
            return nameMatch && categoryMatch;
        });

        renderProductGrid(filtered);
    }
    
    /**
     * Populates the category dropdown from the unique categories found in the product list.
     */
    function populateCategories() {
        // Use Set to get unique categories, filter out null/empty strings
        const categories = [...new Set(PRODUCTS.map(p => p.category).filter(c => c))];
        categories.sort().forEach(category => {
            const option = document.createElement('option');
            option.value = category;
            option.textContent = category;
            productCategory.appendChild(option);
        });
    }

    /**
     * Handles the checkout process: sends sale data via AJAX, and displays the receipt.
     */
    async function handleCheckout() {
        if (Object.keys(cart).length === 0) {
            showCustomModal("Cart Empty", "The cart is empty. Please add items before attempting to complete a sale.", false);
            return;
        }

        const totals = updateCartTotals();
        
        // Prepare data structure for the server
        const saleData = {
            cart: cart,
            totals: totals // Use the object returned by updateCartTotals
        };
        
        // Disable button and show loading state
        checkoutBtn.disabled = true;
        checkoutBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';

        try {
            // 1. Send sale data to process_sale.php
            const response = await fetch('process_sale.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(saleData)
            });

            // Check if HTTP status indicates success (e.g., 200-299)
            if (!response.ok) {
                 throw new Error(`Server returned HTTP status ${response.status}`);
            }

            const result = await response.json();

            if (result.success) {
                // 2. Success: Update client-side stock levels
                Object.values(cart).forEach(item => {
                    // Decrease stock in the client array by the sold quantity
                    updateProductStockInJS(item.product.product_id, -item.quantity);
                });

                // 3. Display the Receipt Modal
                showReceiptModal(cart, totals, result.sale_id, result.sale_date);

                // 4. Reset state for the next sale
                cart = {};
                renderCart();
                
            } else {
                showCustomModal("Checkout Error", `A server error occurred: ${result.message}`, false);
            }

        } catch (error) {
            console.error('Network or Server Error:', error);
            showCustomModal("Network Error", `A network or unexpected error occurred during checkout. Please check the console for details.`, false);
        } finally {
            // 5. Restore button state regardless of outcome
            checkoutBtn.disabled = false;
            checkoutBtn.innerHTML = '<i class="fas fa-credit-card mr-2"></i> Complete Sale';
        }
    }
    
    /**
     * Populates and displays the Receipt Modal with sale summary.
     */
    function showReceiptModal(finalCart, totals, saleId, saleDate) {
        receiptSaleIdElem.textContent = `#${saleId}`;
        receiptDateElem.textContent = new Date(saleDate).toLocaleString(); 

        receiptItemsList.innerHTML = '';
        Object.values(finalCart).forEach(item => {
            const li = document.createElement('li');
            li.className = 'flex justify-between';
            li.innerHTML = `
                <span>${item.quantity} x ${item.product.name}</span>
                <span class="font-bold">${formatCurrency(item.product.price * item.quantity)}</span>
            `;
            receiptItemsList.appendChild(li);
        });

        receiptSubTotalElem.textContent = formatCurrency(totals.subtotal);
        receiptTaxAmountElem.textContent = formatCurrency(totals.tax);
        receiptGrandTotalElem.textContent = formatCurrency(totals.grandTotal);

        // Show modal with transition effects
        const modalContent = receiptModal.querySelector('[data-modal-target="receiptModal"]');
        receiptModal.classList.remove('hidden');
        receiptModal.classList.add('flex');
        
        setTimeout(() => {
            modalContent.classList.remove('scale-95', 'opacity-0');
            modalContent.classList.add('scale-100', 'opacity-100');
        }, 10);
    }

    /**
     * Event listener setup for interactive elements.
     */
    function setupEventListeners() {
        checkoutBtn.addEventListener('click', handleCheckout);
        
        // NEW: Print button listener
        printReceiptBtn.addEventListener('click', () => {
             // Triggers the browser's print dialog
             window.print();
        });

        // Use custom modal for confirmation
        clearCartBtn.addEventListener('click', () => {
            showCustomModal(
                "Confirm Clear Cart", 
                "Are you sure you want to remove ALL items from the current cart? This action cannot be undone.", 
                true, // isConfirm = true
                (confirmed) => {
                    if (confirmed) {
                        cart = {};
                        renderCart();
                    }
                }
            );
        });
        
        // Hide receipt modal
        closeReceiptModalBtn.addEventListener('click', () => {
            const modalContent = receiptModal.querySelector('[data-modal-target="receiptModal"]');
            modalContent.classList.remove('scale-100', 'opacity-100');
            modalContent.classList.add('scale-95', 'opacity-0');
            
            setTimeout(() => {
                receiptModal.classList.remove('flex');
                receiptModal.classList.add('hidden');
            }, 300); // Wait for transition to complete
        });
        
        // Input filters
        productSearch.addEventListener('input', filterProducts);
        productCategory.addEventListener('change', filterProducts);
    }

    // --- Initialization ---
    document.addEventListener('DOMContentLoaded', () => {
        populateCategories();
        renderProductGrid();
        renderCart();
        setupEventListeners();
    });

</script>
</body>
</html>