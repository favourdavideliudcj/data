<?php
session_start();

// 1. PRODUCT CATALOG (Backend PHP Data)
$products = [
    1 => [
        "id" => 1,
        "name" => "Tecno Camon 30 Pro 5G",
        "category" => "Smartphones",
        "price" => 38500,
        "stock" => 12,
        "image" => "📱",
        "desc" => "256GB Storage, 12GB RAM, 50MP Sony OIS Camera."
    ],
    2 => [
        "id" => 2,
        "name" => "HP EliteBook 840 G7",
        "category" => "Laptops",
        "price" => 46000,
        "stock" => 7,
        "image" => "💻",
        "desc" => "Core i7 10th Gen, 16GB RAM, 512GB NVMe SSD, Refurbished Grade A."
    ],
    3 => [
        "id" => 3,
        "name" => "Oraimo SpaceBox Pro Speaker",
        "category" => "Audio",
        "price" => 6800,
        "stock" => 25,
        "image" => "🔊",
        "desc" => "Deep Bass Bluetooth 5.3, LED Light Show, 16-Hour Playtime."
    ],
    4 => [
        "id" => 4,
        "name" => "Kikoy Maasai Shuka Throw",
        "category" => "Fashion & Home",
        "price" => 1850,
        "stock" => 30,
        "image" => "🧣",
        "desc" => "100% Pure Kenyan Cotton woven blanket, vibrant red & black."
    ],
    5 => [
        "id" => 5,
        "name" => "Ramtons 2-Burner Gas Cooker",
        "category" => "Appliances",
        "price" => 5400,
        "stock" => 15,
        "image" => "🔥",
        "desc" => "Auto-ignition stainless steel tabletop stove with brass burners."
    ],
    6 => [
        "id" => 6,
        "name" => "Starlink Standard Kit (V4)",
        "category" => "Internet",
        "price" => 45000,
        "stock" => 5,
        "image" => "🛰️",
        "desc" => "High-speed low-latency satellite internet kit for home & office."
    ]
];

// Initialize cart & orders in PHP Session
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
if (!isset($_SESSION['orders'])) {
    $_SESSION['orders'] = [];
}

// 2. HANDLE AJAX API REQUESTS (PHP Backend Logic)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    // Add item to cart
    if ($action === 'add_to_cart') {
        $id = (int)($_POST['product_id'] ?? 0);
        if (isset($products[$id])) {
            $_SESSION['cart'][$id] = ($_SESSION['cart'][$id] ?? 0) + 1;
            echo json_encode(["status" => "success", "message" => "{$products[$id]['name']} added to cart!"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Product not found."]);
        }
        exit;
    }

    // Update quantity or remove item
    if ($action === 'update_qty') {
        $id = (int)($_POST['product_id'] ?? 0);
        $delta = (int)($_POST['delta'] ?? 0);
        if (isset($_SESSION['cart'][$id])) {
            $_SESSION['cart'][$id] += $delta;
            if ($_SESSION['cart'][$id] <= 0) {
                unset($_SESSION['cart'][$id]);
            }
        }
        echo json_encode(["status" => "success"]);
        exit;
    }

    // Process M-Pesa / Card Checkout
    if ($action === 'checkout') {
        $customer = trim($_POST['customer_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $county = trim($_POST['county'] ?? '');
        $method = trim($_POST['payment_method'] ?? 'M-Pesa');

        if (empty($_SESSION['cart'])) {
            echo json_encode(["status" => "error", "message" => "Your cart is empty."]);
            exit;
        }
        if ($customer === '' || !preg_match('/^(07|01)\d{8}$/', $phone)) {
            echo json_encode(["status" => "error", "message" => "Enter a valid name and 10-digit Kenyan phone number (07... or 01...)."]);
            exit;
        }

        $total = 0;
        $orderItems = [];
        foreach ($_SESSION['cart'] as $pid => $qty) {
            $itemTotal = $products[$pid]['price'] * $qty;
            $total += $itemTotal;
            $orderItems[] = "{$products[$pid]['name']} (x{$qty})";
        }

        // Generate Kenyan M-Pesa style transaction code
        $receiptCode = "S" . strtoupper(substr(md5(uniqid()), 0, 9));
        $orderRecord = [
            "receipt" => $receiptCode,
            "customer" => htmlspecialchars($customer),
            "phone" => htmlspecialchars($phone),
            "county" => htmlspecialchars($county),
            "method" => htmlspecialchars($method),
            "items" => $orderItems,
            "total" => $total,
            "date" => date("d M Y, H:i")
        ];

        array_unshift($_SESSION['orders'], $orderRecord);
        $_SESSION['cart'] = []; // Clear cart after payment

        echo json_encode([
            "status" => "success",
            "message" => "Payment confirmed! Receipt: {$receiptCode}",
            "order" => $orderRecord
        ]);
        exit;
    }

    // Fetch current cart state
    if ($action === 'get_state') {
        $cartDetails = [];
        $subtotal = 0;
        $count = 0;
        foreach ($_SESSION['cart'] as $pid => $qty) {
            if (isset($products[$pid])) {
                $lineTotal = $products[$pid]['price'] * $qty;
                $subtotal += $lineTotal;
                $count += $qty;
                $cartDetails[] = [
                    "id" => $pid,
                    "name" => $products[$pid]['name'],
                    "price" => $products[$pid]['price'],
                    "qty" => $qty,
                    "lineTotal" => $lineTotal,
                    "image" => $products[$pid]['image']
                ];
            }
        }
        echo json_encode([
            "cart" => $cartDetails,
            "subtotal" => $subtotal,
            "count" => $count,
            "orders" => $_SESSION['orders']
        ]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>DukaFlow KE | Modern PHP E-Commerce Store</title>
  <style>
    :root {
      --primary: #0f766e;
      --primary-dark: #115e59;
      --mpesa-green: #16a34a;
      --dark: #0f172a;
      --bg: #f1f5f9;
      --card: #ffffff;
      --border: #e2e8f0;
      --muted: #64748b;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', system-ui, sans-serif; }
    body { background: var(--bg); color: var(--dark); }

    /* Navbar */
    header {
      background: var(--dark);
      color: white;
      padding: 1rem 2rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      position: sticky;
      top: 0;
      z-index: 100;
      box-shadow: 0 4px 12px rgba(0,0,0,0.12);
    }
    .brand { font-size: 1.4rem; font-weight: 800; letter-spacing: -0.5px; }
    .brand span { color: #2dd4bf; }
    .nav-actions { display: flex; gap: 0.75rem; align-items: center; }

    button {
      cursor: pointer;
      border: none;
      border-radius: 8px;
      font-weight: 600;
      transition: all 0.2s;
    }
    .cart-toggle-btn {
      background: var(--primary);
      color: white;
      padding: 0.6rem 1.1rem;
      font-size: 0.95rem;
    }
    .cart-toggle-btn:hover { background: var(--primary-dark); }
    .orders-btn {
      background: #1e293b;
      color: #e2e8f0;
      padding: 0.6rem 1rem;
      font-size: 0.9rem;
    }

    /* Filter & Search Bar */
    .toolbar {
      max-width: 1150px;
      margin: 1.5rem auto 0;
      padding: 0 1.25rem;
      display: flex;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 1rem;
    }
    .search-box {
      flex: 1;
      min-width: 240px;
      padding: 0.7rem 1rem;
      border: 1px solid #cbd5e1;
      border-radius: 8px;
      font-size: 0.95rem;
    }
    .cat-pills { display: flex; gap: 0.5rem; flex-wrap: wrap; }
    .pill {
      padding: 0.55rem 1rem;
      background: white;
      border: 1px solid var(--border);
      border-radius: 20px;
      font-size: 0.85rem;
    }
    .pill.active { background: var(--dark); color: white; border-color: var(--dark); }

    /* Product Grid */
    .container { max-width: 1150px; margin: 1.5rem auto 3rem; padding: 0 1.25rem; }
    .product-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(270px, 1fr));
      gap: 1.25rem;
    }
    .product-card {
      background: var(--card);
      border-radius: 12px;
      padding: 1.35rem;
      border: 1px solid var(--border);
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      transition: transform 0.2s, box-shadow 0.2s;
    }
    .product-card:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.06); }
    .product-icon {
      font-size: 3rem;
      background: #f8fafc;
      height: 110px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 10px;
      margin-bottom: 1rem;
    }
    .cat-tag { font-size: 0.75rem; font-weight: 700; color: var(--primary); text-transform: uppercase; }
    .product-title { font-size: 1.1rem; font-weight: 700; margin: 0.25rem 0; }
    .product-desc { font-size: 0.88rem; color: var(--muted); margin-bottom: 1rem; }
    .price-row { display: flex; justify-content: space-between; align-items: center; margin-top: auto; }
    .price { font-size: 1.15rem; font-weight: 800; color: var(--dark); }
    .add-btn { background: var(--dark); color: white; padding: 0.55rem 1rem; font-size: 0.88rem; }
    .add-btn:hover { background: var(--primary); }

    /* Slide-over Cart Drawer */
    .drawer-overlay {
      position: fixed; inset: 0; background: rgba(15, 23, 42, 0.55);
      display: none; justify-content: flex-end; z-index: 200;
    }
    .drawer-overlay.open { display: flex; }
    .drawer {
      background: white; width: 100%; max-width: 430px; height: 100%;
      padding: 1.5rem; display: flex; flex-direction: column; overflow-y: auto;
    }
    .drawer-header {
