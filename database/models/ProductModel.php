<?php
require_once __DIR__ . '/../connection.php';

class ProductModel {
    private $conn;

    public function __construct() {
        global $conn;
        $this->conn = $conn;
    }

    public function getAll() {
        $sql = 'SELECT
            product_id,
            product_name,
            product_author,
            product_category,
            product_description,
            product_image,
            barcode,
            COALESCE(buy_price, price, 0.00) AS buy_price,
            COALESCE(stock_count, stock, 0) AS stock,
            COALESCE(product_status, "available") AS product_status
        FROM products';
        $result = $this->conn->query($sql);
        $products = [];
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
        return $products;
    }

    public function findById($id) {
        $stmt = $this->conn->prepare('SELECT * FROM products WHERE product_id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $product = $result->fetch_assoc();
        $stmt->close();
        return $product ?: null;
    }
}
