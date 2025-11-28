<?php
// process_sale.php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized or invalid request method.']);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['cart']) || !isset($data['totals'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing cart or totals data.']);
    exit();
}

$cart = $data['cart'];
$totals = $data['totals'];
$user_id = $_SESSION['user_id'];
$total_amount = $totals['grandTotal'];

$conn->begin_transaction();

try {
    // 1. Insert into the Sales table
    $sql_sale = "INSERT INTO sales (user_id, total_amount) VALUES (?, ?)";
    $stmt_sale = $conn->prepare($sql_sale);
    $stmt_sale->bind_param("id", $user_id, $total_amount);
    $stmt_sale->execute();
    $sale_id = $conn->insert_id;
    $stmt_sale->close();

    // 2. Insert into the Sale_Items and Update Product Stock
    $sql_item = "INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)";
    $stmt_item = $conn->prepare($sql_item);

    $sql_update_stock = "UPDATE products SET stock_quantity = stock_quantity - ? WHERE product_id = ?";
    $stmt_stock = $conn->prepare($sql_update_stock);

    foreach ($cart as $item) {
        $product_id = $item['product']['product_id'];
        $quantity = $item['quantity'];
        $unit_price = $item['product']['price'];
        $subtotal = $unit_price * $quantity;
        
        // Insert item detail
        $stmt_item->bind_param("iiidd", $sale_id, $product_id, $quantity, $unit_price, $subtotal);
        $stmt_item->execute();
        
        // Update stock
        $stmt_stock->bind_param("ii", $quantity, $product_id);
        $stmt_stock->execute();
    }
    $stmt_item->close();
    $stmt_stock->close();

    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Sale recorded successfully.', 
        'sale_id' => $sale_id,
        'sale_date' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Sale failed: ' . $e->getMessage()]);
}

$conn->close();
?>