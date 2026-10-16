<?php
// HIDDEN DEBUG MODE (Lesson 3: PHP Extensions)
// To view this, add "?debug=1" to the end of your URL (e.g., localhost/index.php?debug=1)
if (isset($_GET['debug']) && $_GET['debug'] === '1') {
    phpinfo();
    exit(); // Stops the rest of the hotel site from loading
}
?>

<?php
session_start();

// ============================================================
// LESSON 5: COOKIES — Remember Guest's Room Preference
// setcookie()  |  $_COOKIE  |  Delete cookie (time() - 1)
// ============================================================

// Handle "Clear Preference" button — deletes the cookie
if (isset($_POST['clear_pref'])) {
    setcookie("luxehotel_lastroom", "", time() - 1); // Delete by setting past expiry
    unset($_COOKIE['luxehotel_lastroom']);
    header("Location: " . $_SERVER['PHP_SELF'] . "?tab=book");
    exit();
}

// Read cookie — retrieve saved room preference if it exists
$savedRoomPref = isset($_COOKIE['luxehotel_lastroom'])
    ? htmlspecialchars($_COOKIE['luxehotel_lastroom'], ENT_QUOTES, 'UTF-8')
    : null;

// ============================================================
// LESSON 5: SESSION CART — Hotel Add-ons (Shopping Cart Pattern)
// $_SESSION['cart']  |  add / remove items  |  cart total
// ============================================================

$addonPrices = [
    "Breakfast Package" => 350,
    "Airport Transfer"  => 800,
    "Spa Access"        => 600,
    "Extra Towels"      => 150,
];

// Handle add-ons form submission — update cart quantities
if (isset($_POST['update_addons'])) {
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    foreach ($addonPrices as $addon => $addonPrice) {
        $key = strtolower(str_replace(' ', '_', $addon));
        if (isset($_POST[$key])) {
            $qty = (int)$_POST[$key];
            if ($qty > 0) {
                $_SESSION['cart'][$addon] = $qty; // Add/update item
            } else {
                unset($_SESSION['cart'][$addon]); // Remove if qty = 0
            }
        }
    }
    header("Location: " . $_SERVER['PHP_SELF'] . "?tab=book");
    exit();
}

// Handle individual remove buttons inside the cart
if (isset($_POST['remove_addon'])) {
    $addonToRemove = $_POST['remove_addon'];
    if (isset($_SESSION['cart'][$addonToRemove])) {
        unset($_SESSION['cart'][$addonToRemove]); // Remove specific item
    }
    header("Location: " . $_SERVER['PHP_SELF'] . "?tab=book");
    exit();
}

// Compute running cart total
$cartTotal = 0;
if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $addon => $qty) {
        if (isset($addonPrices[$addon])) {
            $cartTotal += $addonPrices[$addon] * $qty;
        }
    }
}

// 1. Load the external class file
include 'Room.inc.php';

// ============================================================
// BUILDING FUNCTIONS
// ============================================================
function generateBookingRef(string $guestName): string
{
    $letters = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $guestName), 0, 3));
    $counter = 1000;
    $counter += rand(0, 8999);
    return $letters . $counter;
}

function calculateTotal(int|float $pricePerNight, int|float $nights): float
{
    $subtotal = $pricePerNight * $nights;
    $tax      = $subtotal * 0.12;
    $total    = $subtotal + $tax;
    return $total;
}

function validateGuestName(string $name): bool|string
{
    $name = trim($name);
    if (empty($name)) return "Name is required.";
    if (!preg_match("/^[a-zA-Z ]*$/", $name))
        return "Name must contain letters and spaces only.";
    if (strlen($name) < 3)
        return "Name must be at least 3 characters.";
    return true;
}

function validateEmail(string $email): bool|string
{
    $email = trim($email);
    if (empty($email)) return "Email is required.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        return "Invalid email format.";
    return true;
}

date_default_timezone_set("Asia/Manila");

$roomRates = array(
    "Standard" => 2500,
    "Deluxe"   => 4500,
    "Suite"    => 8000
);
$ratesRef = &$roomRates;

// 2. Instantiate Child Classes using Inheritance
$roomObjects = array(
    "Standard" => new StandardRoom($roomRates["Standard"]),
    "Deluxe"   => new DeluxeRoom($roomRates["Deluxe"]),
    "Suite"    => new SuiteRoom($roomRates["Suite"])
);
$roomTypes = array_keys($roomRates);

// ============================================================
// PROCESS BOOKING POST
// ============================================================
if (isset($_POST['book_room'])) {

    $guestName = trim($_POST['guest_name']);
    $roomType  = trim($_POST['room_type']);
    $email     = trim($_POST['email']);
    $requests  = trim($_POST['requests'] ?? '');

    $nameCheck  = validateGuestName($guestName);
    $emailCheck = validateEmail($email);

    if ($nameCheck !== true) {
        $_SESSION['error'] = htmlspecialchars($nameCheck);
        header("Location: " . $_SERVER['PHP_SELF'] . "?tab=book");
        exit();
    }

    if ($emailCheck !== true) {
        $_SESSION['error'] = htmlspecialchars($emailCheck);
        header("Location: " . $_SERVER['PHP_SELF'] . "?tab=book");
        exit();
    }

    $inTimestamp  = strtotime($_POST['check_in']);
    $outTimestamp = strtotime($_POST['check_out']);

    if ($inTimestamp === false || $outTimestamp === false) {
        $_SESSION['error'] = "Invalid date provided.";
        header("Location: " . $_SERVER['PHP_SELF'] . "?tab=book");
        exit();
    }

    if ($inTimestamp < strtotime('today')) {
        $_SESSION['error'] = "Check-in date cannot be in the past.";
        header("Location: " . $_SERVER['PHP_SELF'] . "?tab=book");
        exit();
    }

    $nights = round(($outTimestamp - $inTimestamp) / (60 * 60 * 24));

    if ($nights <= 0) {
        $_SESSION['error'] = "Check-out must be after check-in.";
        header("Location: " . $_SERVER['PHP_SELF'] . "?tab=book");
        exit();
    }

    if (!array_key_exists($roomType, $roomRates)) {
        $_SESSION['error'] = "Invalid room type selected.";
        header("Location: " . $_SERVER['PHP_SELF'] . "?tab=book");
        exit();
    }

    $roomQty       = (int)($_POST['room_qty'] ?? 1);
    $guests        = (int)($_POST['guests']   ?? 1);
    $pricePerNight = $roomRates[$roomType];
    $totalCost     = calculateTotal($pricePerNight * $roomQty, $nights);

    // Include add-ons cart total in grand total
    $cartSnapshot  = $_SESSION['cart'] ?? [];
    $cartTotal     = 0;
    foreach ($cartSnapshot as $addon => $qty) {
        if (isset($addonPrices[$addon])) {
            $cartTotal += $addonPrices[$addon] * $qty;
        }
    }
    $grandTotal = $totalCost + $cartTotal;

    $bookingRef    = generateBookingRef($guestName);

    $requestLength = strlen($requests);
    $requestWords  = str_word_count($requests);
    $isUrgent      = strpos(strtolower($requests), "urgent") !== false;
    $isSuite       = strcmp($roomType, "Suite") === 0;
    $isDeluxe      = strcasecmp($roomType, "deluxe") === 0;
    $requestList   = !empty($requests) ? str_getcsv($requests) : [];
    $greeting      = "Dear " . $guestName . ", welcome to Sync LuxeHotel!";

    $typeChecks = array(
        "Guest Name is String"   => is_string($guestName),
        "Nights is Integer"      => is_int($nights),
        "Price is Float/Numeric" => is_numeric($pricePerNight),
        "Total is Float"         => is_float($totalCost),
        "Requests is Null/Empty" => is_null($requests) || empty($requests),
        "Is Urgent (Boolean)"    => is_bool($isUrgent),
    );

    switch ($roomType) {
        case "Standard":
            $roomTier = "Economy Tier";
            break;
        case "Deluxe":
            $roomTier = "Premium Tier";
            break;
        case "Suite":
            $roomTier = "Luxury Tier";
            break;
        default:
            $roomTier = "Unknown Tier";
            break;
    }

    $uploadStatus = "Pending Verification";
    if (isset($_FILES['id_upload']) && $_FILES['id_upload']['error'] == 0) {
        $uploadStatus = "ID attached: " . htmlspecialchars($_FILES['id_upload']['name'], ENT_QUOTES, 'UTF-8');
    }

    $conn = new mysqli("localhost", "root", "", "luxehotel");
    if ($conn->connect_error) {
        $_SESSION['error'] = "Database connection failed: " . $conn->connect_error;
        header("Location: " . $_SERVER['PHP_SELF'] . "?tab=book");
        exit();
    }

    $addonsJson = !empty($cartSnapshot) ? json_encode($cartSnapshot) : null;

    $stmt = $conn->prepare("INSERT INTO bookings 
    (booking_ref, guest_name, email, room_type, check_in, check_out, nights, price_per_night, total_cost, special_requests, id_upload, room_qty, guests, addons_json, addons_total) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");


    $stmt->bind_param(
    "ssssssiidssiisd",
    $bookingRef,
    $guestName,
    $email,
    $roomType,
    $_POST['check_in'],
    $_POST['check_out'],
    $nights,
    $pricePerNight,
    $grandTotal,
    $requests,
    $uploadStatus,
    $roomQty,
    $guests,
    $addonsJson,   // TEXT — JSON string of cart
    $cartTotal     // DECIMAL — add-ons subtotal
    );


    if (!$stmt->execute()) {
        $_SESSION['error'] = "Booking Failed: Could not save to database.";
        $stmt->close();
        $conn->close();
        header("Location: " . $_SERVER['PHP_SELF'] . "?tab=book");
        exit();
    }

    $stmt->close();
    $conn->close();

    // ── LESSON 5: COOKIE — Save preferred room type for 7 days ──
    // Next visit will show "Welcome back!" with their last room choice
    setcookie("luxehotel_lastroom", $roomType, time() + (7 * 24 * 60 * 60));

    // Clear the session cart after booking is saved
    unset($_SESSION['cart']);

    $_SESSION['booking'] = [
        'ref'           => $bookingRef,
        'guestName'     => $guestName,
        'email'         => $email,
        'roomType'      => $roomType,
        'roomTier'      => $roomTier,
        'nights'        => $nights,
        'roomQty'       => $roomQty,
        'guests'        => $guests,
        'checkIn'       => date("F d, Y", $inTimestamp),
        'checkOut'      => date("F d, Y", $outTimestamp),
        'bookedAt'      => date("h:i:s A"),
        'pricePerNight' => $pricePerNight,
        'totalCost'     => $totalCost,
        'cartItems'     => $cartSnapshot,   // Add-ons booked
        'cartTotal'     => $cartTotal,      // Add-ons subtotal
        'grandTotal'    => $grandTotal,     // Room cost + add-ons
        'uploadStatus'  => $uploadStatus,
        'greeting'      => $greeting,
        'requests'      => $requests,
        'requestList'   => $requestList,
        'requestLength' => $requestLength,
        'requestWords'  => $requestWords,
        'isUrgent'      => $isUrgent,
        'isSuite'       => $isSuite,
        'isDeluxe'      => $isDeluxe,
        'typeChecks'    => $typeChecks,
        'stars'         => min($nights, 5),
    ];

    header("Location: " . $_SERVER['PHP_SELF'] . "?status=confirmed&tab=book");
    exit();
}

// ============================================================
// PROCESS PAYMENT POST
// ============================================================
$paymentResult = null;
if (isset($_POST['pay_now'])) {

    $payRef    = trim($_POST['pay_ref'] ?? '');
    $payMethod = trim($_POST['pay_method'] ?? '');
    $payError  = null;

    if (empty($payRef)) {
        $payError = "Booking reference is required.";
    }

    if (!$payError) {
        $conn = new mysqli("localhost", "root", "", "luxehotel");
        if ($conn->connect_error) {
            $payError = "Database connection failed.";
        } else {
            $stmt = $conn->prepare("SELECT * FROM bookings WHERE booking_ref = ?");
            $stmt->bind_param("s", $payRef);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                $payError = "No booking found with reference <strong>" . htmlspecialchars($payRef, ENT_QUOTES, 'UTF-8') . "</strong>.";
            } else {
                $row = $result->fetch_assoc();

                if ($payMethod === 'card') {
                    $cardNumber     = preg_replace('/\s+/', '', trim($_POST['card_number']      ?? ''));
                    $cardExpiryYear = trim($_POST['card_expiry_year'] ?? '');
                    $cardCvv        = trim($_POST['card_cvv']         ?? '');
                    $cardName       = trim($_POST['card_name']        ?? '');

                    if (strlen($cardNumber) < 13 || strlen($cardNumber) > 19 || !ctype_digit($cardNumber)) {
                        $payError = "Invalid card number.";
                    } elseif (empty($cardExpiryYear) || !ctype_digit($cardExpiryYear) || (int)$cardExpiryYear < (int)date('Y')) {
                        $payError = "Please select a valid expiration year.";
                    } elseif (!preg_match('/^\d{3,4}$/', $cardCvv)) {
                        $payError = "Invalid CVV.";
                    } elseif (empty($cardName)) {
                        $payError = "Name on card is required.";
                    }
                }

                if (!$payError) {
                    $methodLabel = match ($payMethod) {
                        'card'  => 'Credit / Debit Card',
                        'gcash' => 'GCash',
                        'maya'  => 'Maya',
                        'bank'  => 'Bank Transfer (InstaPay/PESONet)',
                        default => 'Unknown'
                    };

                    $updStmt = $conn->prepare("UPDATE bookings SET payment_status = 'Paid', payment_method = ? WHERE booking_ref = ?");
                    $updStmt->bind_param("ss", $methodLabel, $payRef);
                    $updStmt->execute();
                    $updStmt->close();

                    $paymentResult = [
                        'success'     => true,
                        'ref'         => $row['booking_ref'],
                        'guestName'   => $row['guest_name'],
                        'roomType'    => $row['room_type'],
                        'checkIn'     => $row['check_in'],
                        'checkOut'    => $row['check_out'],
                        'nights'      => $row['nights'],
                        'totalCost'   => $row['total_cost'],
                        'method'      => $methodLabel,
                        'paidAt'      => date("F d, Y h:i A"),
                    ];
                }
            }

            $stmt->close();
            $conn->close();
        }
    }

    if ($payError) {
        $_SESSION['pay_error'] = $payError;
        header("Location: " . $_SERVER['PHP_SELF'] . "?tab=checkout");
        exit();
    }
}

// ============================================================
// Determine active tab
// ============================================================
$activeTab = $_GET['tab'] ?? 'book';
if (!in_array($activeTab, ['book', 'checkout', 'policies'])) {
    $activeTab = 'book';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sync LuxeHotel — Reserve</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;1,300;1,400&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --ivory: #faf8f4;
            --warm-white: #ffffff;
            --stone: #e8e4dc;
            --parchment: #f0ece4;
            --ink: #1a1814;
            --charcoal: #2d2a25;
            --muted: #7a756b;
            --accent: #8b6f47;
            --accent-lt: #c4a882;
            --success: #3d6b4f;
            --danger: #8b3a3a;
            --radius: 4px;
            --transition: 0.22s ease;
        }

        html {
            font-size: 32px;
        }

        body {
            background-color: var(--ivory);
            font-family: 'DM Sans', sans-serif;
            color: var(--ink);
            min-height: 100vh;
            padding: 0;
        }

        /* ─── LAYOUT ──────────────────────────── */
        .page-wrap {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 48px 20px 80px;
        }

        /* ─── MASTHEAD ────────────────────────── */
        .masthead {
            text-align: center;
            margin-bottom: 48px;
            animation: fadeUp 0.6s ease both;
        }

        .masthead-eyebrow {
            font-family: 'DM Sans', sans-serif;
            font-size: 10px;
            font-weight: 500;
            letter-spacing: 0.25em;
            text-transform: uppercase;
            color: var(--accent);
            margin-bottom: 10px;
        }

        .masthead-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(2.4rem, 5vw, 3.6rem);
            font-weight: 300;
            letter-spacing: 0.04em;
            color: var(--ink);
            line-height: 1.1;
        }

        .masthead-title em {
            font-style: italic;
            color: var(--accent);
        }

        .masthead-rule {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-top: 18px;
            justify-content: center;
        }

        .masthead-rule span {
            display: block;
            width: 48px;
            height: 1px;
            background: var(--accent-lt);
        }

        .masthead-diamond {
            width: 5px;
            height: 5px;
            background: var(--accent);
            transform: rotate(45deg);
        }

        .masthead-sub {
            font-family: 'DM Sans', sans-serif;
            font-size: 13px;
            font-weight: 300;
            color: var(--muted);
            margin-top: 14px;
            letter-spacing: 0.02em;
        }

        /* ─── CARD ────────────────────────────── */
        .card {
            width: 100%;
            max-width: 720px;
            background: var(--warm-white);
            border: 1px solid var(--stone);
            border-radius: 2px;
            animation: fadeUp 0.7s 0.1s ease both;
            overflow: hidden;
        }

        /* ─── TABS ────────────────────────────── */
        .tab-bar {
            display: flex;
            border-bottom: 1px solid var(--stone);
            background: var(--parchment);
        }

        .tab-btn {
            flex: 1;
            padding: 16px 8px;
            font-family: 'DM Sans', sans-serif;
            font-size: 11px;
            font-weight: 500;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--muted);
            background: transparent;
            border: none;
            border-bottom: 2px solid transparent;
            cursor: pointer;
            transition: all var(--transition);
            position: relative;
        }

        .tab-btn:hover {
            color: var(--charcoal);
            background: rgba(0, 0, 0, 0.02);
        }

        .tab-btn.active {
            color: var(--ink);
            border-bottom-color: var(--accent);
            background: var(--warm-white);
        }

        .tab-icon {
            display: block;
            font-size: 14px;
            margin-bottom: 4px;
        }

        /* ─── PANES ───────────────────────────── */
        .pane {
            display: none;
            padding: 40px 44px;
        }

        .pane.active {
            display: block;
            animation: fadeIn 0.3s ease both;
        }

        /* ─── SECTION HEADERS ─────────────────── */
        .section-label {
            font-family: 'DM Sans', sans-serif;
            font-size: 20px;
            font-weight: 500;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: var(--accent);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-label::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--stone);
        }

        /* ─── ROOM PILLS ──────────────────────── */
        .room-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 32px;
        }

        .room-pill {
            font-size: 17px;
            font-weight: 400;
            color: var(--muted);
            background: var(--parchment);
            border: 1px solid var(--stone);
            border-radius: 2px;
            padding: 6px 12px;
            letter-spacing: 0.03em;
        }

        /* ─── FORM ────────────────────────────── */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-grid .full {
            grid-column: 1 / -1;
        }

        .form-grid .third {
            grid-column: span 1;
        }

        .form-row-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .field label {
            font-size: 17px;
            font-weight: 500;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: var(--muted);
        }

        .field input,
        .field select,
        .field textarea {
            font-family: 'DM Sans', sans-serif;
            font-size: 15px;
            font-weight: 300;
            color: var(--ink);
            background: var(--ivory);
            border: 1px solid var(--stone);
            border-radius: var(--radius);
            padding: 11px 14px;
            outline: none;
            transition: border-color var(--transition), background var(--transition);
            appearance: none;
            -webkit-appearance: none;
            width: 100%;
        }

        .field select {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' fill='none'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%237a756b' stroke-width='1.5' stroke-linecap='round'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            padding-right: 36px;
        }

        .field input:focus,
        .field select:focus {
            border-color: var(--accent);
            background: #fff;
        }

        .field input[type="file"] {
            font-size: 17px;
            padding: 9px 14px;
            cursor: pointer;
        }

        .field input[type="file"]::-webkit-file-upload-button {
            background: var(--parchment);
            border: 1px solid var(--stone);
            border-radius: 2px;
            font-family: 'DM Sans', sans-serif;
            font-size: 17px;
            letter-spacing: 0.08em;
            color: var(--charcoal);
            padding: 4px 12px;
            cursor: pointer;
            margin-right: 10px;
        }

        /* ─── BUTTON ──────────────────────────── */
        .btn-primary {
            display: block;
            width: 100%;
            margin-top: 28px;
            padding: 15px;
            font-family: 'DM Sans', sans-serif;
            font-size: 11px;
            font-weight: 500;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: var(--warm-white);
            background: var(--ink);
            border: none;
            border-radius: var(--radius);
            cursor: pointer;
            transition: background var(--transition), transform var(--transition);
        }

        .btn-primary:hover {
            background: var(--charcoal);
            transform: translateY(-1px);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .btn-pay {
            background: var(--accent);
        }

        .btn-pay:hover {
            background: #7a5f3a;
        }

        /* ─── ALERTS ──────────────────────────── */
        .alert {
            font-size: 13px;
            font-weight: 300;
            padding: 13px 16px;
            border-radius: var(--radius);
            margin-bottom: 24px;
            border-left: 3px solid;
        }

        .alert-error {
            background: #fdf2f2;
            border-color: var(--danger);
            color: var(--danger);
        }

        .alert-success {
            background: #f2f8f4;
            border-color: var(--success);
            color: var(--success);
        }

        .alert-info {
            background: var(--parchment);
            border-color: var(--accent-lt);
            color: var(--charcoal);
        }

        .alert-warn {
            background: #fdf8f0;
            border-color: #c9973a;
            color: #6b4f1a;
        }

        /* ─── RECEIPT ─────────────────────────── */
        .receipt {
            margin-top: 32px;
            border: 1px solid var(--stone);
            border-radius: var(--radius);
            overflow: hidden;
        }

        .receipt-header {
            background: var(--ink);
            color: var(--ivory);
            padding: 20px 24px;
            display: flex;
            justify-content: space-between;
            align-items: baseline;
        }

        .receipt-header-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 18px;
            font-weight: 300;
            letter-spacing: 0.05em;
        }

        .receipt-ref {
            font-size: 15px;
            font-weight: 500;
            letter-spacing: 0.18em;
            color: var(--accent-lt);
        }

        .receipt-body {
            padding: 24px;
            background: var(--warm-white);
        }

        .receipt-greeting {
            font-family: 'Cormorant Garamond', serif;
            font-size: 16px;
            font-style: italic;
            color: var(--muted);
            margin-bottom: 20px;
        }

        .receipt-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px 24px;
            padding-top: 16px;
            border-top: 1px solid var(--stone);
        }

        .receipt-row {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .receipt-row .r-label {
            font-size: 9px;
            font-weight: 500;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: var(--accent);
        }

        .receipt-row .r-value {
            font-size: 14px;
            font-weight: 300;
            color: var(--ink);
        }

        .receipt-row .r-value strong {
            font-weight: 500;
        }

        .receipt-tier {
            display: inline-block;
            font-size: 15px;
            font-weight: 500;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: var(--accent);
            border: 1px solid var(--accent-lt);
            border-radius: 2px;
            padding: 2px 8px;
            margin-left: 6px;
        }

        .receipt-total {
            margin-top: 20px;
            padding: 20px 24px;
            background: var(--ink);
            color: var(--ivory);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .receipt-total-label {
            font-size: 15px;
            font-weight: 400;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.5);
        }

        .receipt-total-amount {
            font-family: 'Cormorant Garamond', serif;
            font-size: 28px;
            font-weight: 400;
            letter-spacing: 0.02em;
        }

        .receipt-stars {
            font-size: 13px;
            letter-spacing: 0.05em;
            color: var(--accent);
        }

        /* Request list */
        .request-list {
            list-style: none;
            padding: 0;
            margin: 8px 0 0 0;
        }

        .request-list li {
            font-size: 12px;
            color: var(--muted);
            padding: 4px 0;
            border-bottom: 1px dashed var(--stone);
        }

        .request-list li:last-child {
            border-bottom: none;
        }

        .request-list li::before {
            content: '— ';
            color: var(--accent-lt);
        }

        /* Debug section */
        .debug-block {
            margin-top: 20px;
            padding: 16px;
            background: var(--parchment);
            border-radius: var(--radius);
            border: 1px solid var(--stone);
        }

        .debug-block .debug-title {
            font-size: 9px;
            font-weight: 500;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 10px;
        }

        .debug-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 4px 0;
            border-bottom: 1px solid var(--stone);
            font-size: 12px;
        }

        .debug-row:last-child {
            border-bottom: none;
        }

        .debug-row .d-key {
            color: var(--charcoal);
            font-weight: 300;
        }

        .badge-true {
            font-size: 9px;
            font-weight: 500;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            background: var(--success);
            color: #fff;
            padding: 2px 7px;
            border-radius: 2px;
        }

        .badge-false {
            font-size: 9px;
            font-weight: 500;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            background: var(--muted);
            color: #fff;
            padding: 2px 7px;
            border-radius: 2px;
        }

        .ref-callout {
            margin-top: 24px;
            padding: 16px 20px;
            background: var(--parchment);
            border: 1px solid var(--stone);
            border-radius: var(--radius);
            font-size: 13px;
            color: var(--charcoal);
            text-align: center;
        }

        .ref-callout strong {
            color: var(--accent);
        }

        .ref-callout a {
            color: var(--ink);
            text-decoration: underline;
            text-underline-offset: 3px;
            cursor: pointer;
        }

        /* ─── PAYMENT ─────────────────────────── */
        .pay-methods {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 28px;
        }

        .pay-method {
            border: 1px solid var(--stone);
            border-radius: var(--radius);
            padding: 14px 10px;
            text-align: center;
            cursor: pointer;
            transition: all var(--transition);
            background: var(--ivory);
        }

        .pay-method:hover {
            border-color: var(--accent-lt);
            background: var(--parchment);
        }

        .pay-method.selected {
            border-color: var(--accent);
            background: var(--parchment);
        }

        .pay-method .pm-icon {
            font-size: 20px;
            display: block;
            margin-bottom: 6px;
        }

        .pay-method .pm-name {
            font-size: 11px;
            font-weight: 500;
            letter-spacing: 0.05em;
            color: var(--charcoal);
            display: block;
        }

        .pay-method .pm-sub {
            font-size: 10px;
            color: var(--muted);
            display: block;
            margin-top: 2px;
        }

        .card-fields-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 16px;
        }

        .card-fields-grid .span-full {
            grid-column: 1 / -1;
        }

        .info-box {
            background: var(--parchment);
            border: 1px solid var(--stone);
            border-radius: var(--radius);
            padding: 16px 18px;
            font-size: 13px;
            font-weight: 300;
            color: var(--charcoal);
            line-height: 1.6;
        }

        .info-box strong {
            font-weight: 500;
        }

        .pay-note {
            text-align: center;
            font-size: 11px;
            color: var(--muted);
            margin-top: 14px;
            letter-spacing: 0.03em;
        }

        /* Pay receipt */
        .pay-receipt {
            border: 1px solid var(--stone);
            border-radius: var(--radius);
            overflow: hidden;
        }

        .pay-receipt-header {
            background: var(--success);
            color: var(--warm-white);
            padding: 20px 24px;
        }

        .pay-receipt-header h4 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 20px;
            font-weight: 300;
            letter-spacing: 0.05em;
        }

        .pay-receipt-header p {
            font-size: 11px;
            opacity: 0.8;
            margin-top: 4px;
        }

        /* ─── POLICIES ────────────────────────── */
        .policy-list {
            list-style: none;
            padding: 0;
        }

        .policy-item {
            padding: 18px 0;
            border-bottom: 1px solid var(--stone);
            display: flex;
            gap: 16px;
            align-items: flex-start;
        }

        .policy-item:last-child {
            border-bottom: none;
        }

        .policy-icon {
            width: 32px;
            height: 32px;
            background: var(--parchment);
            border: 1px solid var(--stone);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 17px;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .policy-content .policy-title {
            font-size: 17px;
            font-weight: 500;
            color: var(--ink);
            margin-bottom: 4px;
        }

        .policy-content .policy-desc {
            font-size: 17px;
            font-weight: 300;
            color: var(--muted);
            line-height: 1.6;
        }

        .rate-table {
            margin-top: 12px;
            width: 100%;
            border-collapse: collapse;
        }

        .rate-table td {
            font-size: 17px;
            padding: 5px 0;
            color: var(--charcoal);
            border-bottom: 1px dashed var(--stone);
        }

        .rate-table td:last-child {
            text-align: right;
            font-weight: 500;
        }

        .rate-table tr:last-child td {
            border-bottom: none;
        }

        /* ─── ANIMATIONS ──────────────────────── */
        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        /* ─── ROOM PREVIEW ────────────────────── */
        .room-preview {
            position: relative;
            width: 100%;
            height: 200px;
            border-radius: var(--radius);
            overflow: hidden;
            margin-bottom: 32px;
            border: 1px solid var(--stone);
        }

        .room-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: opacity 0.4s ease;
        }

        .room-preview-badge {
            position: absolute;
            bottom: 12px;
            left: 12px;
            background: rgba(26, 24, 20, 0.75);
            backdrop-filter: blur(6px);
            color: var(--ivory);
            font-size: 10px;
            font-weight: 500;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            padding: 5px 12px;
            border-radius: 2px;
        }

        /* ─── LESSON 5: COOKIE BANNER ─────────── */
        .cookie-banner {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--parchment);
            border: 1px solid var(--accent-lt);
            border-left: 3px solid var(--accent);
            border-radius: var(--radius);
            padding: 12px 16px;
            margin-bottom: 24px;
            font-size: 13px;
            color: var(--charcoal);
        }

        .cookie-banner .cookie-text strong {
            color: var(--accent);
            font-weight: 500;
        }

        .cookie-banner .cookie-text span {
            display: block;
            font-size: 10px;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 3px;
        }

        .btn-cookie-clear {
            font-family: 'DM Sans', sans-serif;
            font-size: 10px;
            font-weight: 500;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--muted);
            background: transparent;
            border: 1px solid var(--stone);
            border-radius: var(--radius);
            padding: 6px 12px;
            cursor: pointer;
            transition: all var(--transition);
            white-space: nowrap;
        }

        .btn-cookie-clear:hover {
            border-color: var(--danger);
            color: var(--danger);
        }

        /* ─── LESSON 5: ADD-ONS CART ──────────── */
        .addons-section {
            margin-bottom: 32px;
            border: 1px solid var(--stone);
            border-radius: var(--radius);
            overflow: hidden;
        }

        .addons-header {
            background: var(--parchment);
            padding: 14px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--stone);
        }

        .addons-header-title {
            font-size: 10px;
            font-weight: 500;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: var(--accent);
        }

        .addons-header-sub {
            font-size: 11px;
            color: var(--muted);
        }

        .addons-available {
            padding: 16px 20px;
            border-bottom: 1px solid var(--stone);
            background: var(--warm-white);
        }

        .addons-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .addon-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            background: var(--ivory);
            border: 1px solid var(--stone);
            border-radius: var(--radius);
            padding: 10px 14px;
        }

        .addon-row .addon-info {
            flex: 1;
        }

        .addon-row .addon-name {
            font-size: 13px;
            font-weight: 400;
            color: var(--ink);
        }

        .addon-row .addon-price {
            font-size: 11px;
            color: var(--accent);
            font-weight: 500;
        }

        .addon-row input[type="number"] {
            width: 56px;
            font-family: 'DM Sans', sans-serif;
            font-size: 13px;
            font-weight: 300;
            color: var(--ink);
            background: var(--warm-white);
            border: 1px solid var(--stone);
            border-radius: var(--radius);
            padding: 6px 8px;
            text-align: center;
            outline: none;
            transition: border-color var(--transition);
        }

        .addon-row input[type="number"]:focus {
            border-color: var(--accent);
        }

        .addons-update-btn {
            font-family: 'DM Sans', sans-serif;
            font-size: 10px;
            font-weight: 500;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: var(--warm-white);
            background: var(--accent);
            border: none;
            border-radius: var(--radius);
            padding: 9px 20px;
            cursor: pointer;
            margin-top: 14px;
            transition: background var(--transition);
        }

        .addons-update-btn:hover {
            background: #7a5f3a;
        }

        /* Cart summary inside add-ons */
        .cart-summary {
            padding: 14px 20px;
            background: var(--warm-white);
        }

        .cart-summary-title {
            font-size: 9px;
            font-weight: 500;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 10px;
        }

        .cart-item-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 0;
            border-bottom: 1px dashed var(--stone);
            font-size: 13px;
        }

        .cart-item-row:last-of-type {
            border-bottom: none;
        }

        .cart-item-name {
            color: var(--charcoal);
            font-weight: 300;
        }

        .cart-item-qty {
            font-size: 11px;
            color: var(--muted);
            margin: 0 8px;
        }

        .cart-item-price {
            color: var(--ink);
            font-weight: 400;
        }

        .cart-remove-btn {
            font-family: 'DM Sans', sans-serif;
            font-size: 10px;
            color: var(--danger);
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 2px 6px;
            border-radius: 2px;
            transition: background var(--transition);
            margin-left: 8px;
        }

        .cart-remove-btn:hover {
            background: #fdf2f2;
        }

        .cart-total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0 0;
            margin-top: 6px;
            border-top: 1px solid var(--stone);
            font-size: 13px;
            font-weight: 500;
            color: var(--ink);
        }

        .cart-empty {
            font-size: 12px;
            color: var(--muted);
            font-style: italic;
            padding: 4px 0;
        }

        /* Receipt add-ons table */
        .addons-receipt-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }

        .addons-receipt-table td {
            font-size: 12px;
            padding: 4px 0;
            color: var(--charcoal);
            border-bottom: 1px dashed var(--stone);
            font-weight: 300;
        }

        .addons-receipt-table td:last-child {
            text-align: right;
            font-weight: 400;
        }

        .addons-receipt-table tr:last-child td {
            border-bottom: none;
        }

        /* ─── RESPONSIVE ──────────────────────── */
        @media (max-width: 600px) {
            .pane {
                padding: 28px 22px;
            }

            .form-grid,
            .form-row-3 {
                grid-template-columns: 1fr;
            }

            .form-grid .full {
                grid-column: 1;
            }

            .pay-methods {
                grid-template-columns: 1fr 1fr;
            }

            .receipt-grid {
                grid-template-columns: 1fr;
            }

            .receipt-total {
                flex-direction: column;
                gap: 6px;
                text-align: center;
            }

            .card-fields-grid {
                grid-template-columns: 1fr;
            }

            .card-fields-grid .span-full {
                grid-column: 1;
            }

            .addons-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

    <div class="page-wrap">

        <!-- MASTHEAD -->
        <header class="masthead">
            <p class="masthead-eyebrow">Santo Tomas, Batangas &nbsp;·&nbsp; Est. 2026</p>
            <h1 class="masthead-title">Sync <em>LuxeHotel</em></h1>
            <div class="masthead-rule">
                <span></span>
                <div class="masthead-diamond"></div>
                <span></span>
            </div>
            <p class="masthead-sub">Reserve your perfect stay with ease</p>
        </header>

        <!-- CARD -->
        <div class="card">

            <!-- TABS -->
            <nav class="tab-bar">
                <button class="tab-btn <?= $activeTab === 'book'     ? 'active' : '' ?>" onclick="switchTab('book')">
                    <span class="tab-icon">◇</span>Reserve
                </button>
                <button class="tab-btn <?= $activeTab === 'checkout' ? 'active' : '' ?>" onclick="switchTab('checkout')">
                    <span class="tab-icon">◈</span>Payment
                </button>
                <button class="tab-btn <?= $activeTab === 'policies' ? 'active' : '' ?>" onclick="switchTab('policies')">
                    <span class="tab-icon">◉</span>Policies
                </button>
            </nav>

            <!-- ══════════════════════════════ -->
            <!-- PANE 1: BOOK                  -->
            <!-- ══════════════════════════════ -->
            <div id="pane-book" class="pane <?= $activeTab === 'book' ? 'active' : '' ?>">

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-error"><?= $_SESSION['error'] ?></div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <?php
                // ── LESSON 5: COOKIE — Show "Welcome Back" banner if cookie is set ──
                // setcookie() was called on last booking; here we READ it with $_COOKIE
                if ($savedRoomPref): ?>
                    <div class="cookie-banner">
                        <div class="cookie-text">
                            <span>Welcome back &mdash; cookie detected</span>
                            <strong>Last preferred room: <?= $savedRoomPref ?></strong>
                        </div>
                        <!-- DELETE cookie: sets past expiry via POST handler above -->
                        <form method="post" style="margin:0;">
                            <button type="submit" name="clear_pref" class="btn-cookie-clear">
                                Clear Preference
                            </button>
                        </form>
                    </div>
                <?php endif; ?>

                <p class="section-label">Available Rooms</p>
                <div class="room-pills">
                    <?php foreach ($roomObjects as $key => $roomObj): ?>
                        <!-- Simply echoing $roomObj triggers the __toString() magic method automatically -->
                        <span class="room-pill"><?= htmlspecialchars($roomObj, ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endforeach; ?>
                </div>

                <!-- ROOM PREVIEW IMAGE -->
                <div class="room-preview">
                    <img id="room-preview-img"
                        src="https://grazia-prod.oss-ap-southeast-1.aliyuncs.com/resources/uid_100036644/hotel_standard_room_c4aa5274.png"
                        crossorigin="anonymous"
                        alt="Standard Room">
                    <span class="room-preview-badge" id="room-preview-badge">Standard Room</span>
                </div>

                <!-- ══════════════════════════════════════════════════════════════ -->
                <!-- LESSON 5: SESSION CART — Hotel Add-ons                        -->
                <!-- Pattern mirrors the lesson's shopping cart:                   -->
                <!--   $_SESSION['cart'] | add qty | remove item | display total   -->
                <!-- ══════════════════════════════════════════════════════════════ -->
                <p class="section-label">Add-ons &amp; Amenities</p>

                <div class="addons-section">
                    <div class="addons-header">
                        <span class="addons-header-title">Select Add-ons</span>
                        <span class="addons-header-sub">Enter qty (0 to remove)</span>
                    </div>

                    <!-- Available add-ons form — submits to update_addons POST handler -->
                    <div class="addons-available">
                        <form action="" method="post">
                            <div class="addons-grid">
                                <?php foreach ($addonPrices as $addon => $price):
                                    $key = strtolower(str_replace(' ', '_', $addon));
                                    $currentQty = $_SESSION['cart'][$addon] ?? 0;
                                ?>
                                    <div class="addon-row">
                                        <div class="addon-info">
                                            <div class="addon-name"><?= htmlspecialchars($addon, ENT_QUOTES, 'UTF-8') ?></div>
                                            <div class="addon-price">&#8369;<?= number_format($price, 2) ?></div>
                                        </div>
                                        <input type="number"
                                            name="<?= $key ?>"
                                            value="<?= $currentQty ?>"
                                            min="0" max="10"
                                            placeholder="0">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="submit" name="update_addons" class="addons-update-btn">
                                Update Cart
                            </button>
                        </form>
                    </div>

                    <!-- Cart summary — shows $_SESSION['cart'] contents + remove buttons -->
                    <div class="cart-summary">
                        <div class="cart-summary-title">Your Cart</div>
                        <?php if (empty($_SESSION['cart'])): ?>
                            <p class="cart-empty">No add-ons selected yet.</p>
                        <?php else: ?>
                            <?php foreach ($_SESSION['cart'] as $addon => $qty): ?>
                                <div class="cart-item-row">
                                    <span class="cart-item-name"><?= htmlspecialchars($addon, ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="cart-item-qty">x<?= $qty ?></span>
                                    <span class="cart-item-price">
                                        &#8369;<?= number_format($addonPrices[$addon] * $qty, 2) ?>
                                    </span>
                                    <!-- Individual remove button — POST with remove_addon -->
                                    <form action="" method="post" style="margin:0;">
                                        <input type="hidden" name="remove_addon" value="<?= htmlspecialchars($addon, ENT_QUOTES, 'UTF-8') ?>">
                                        <button type="submit" class="cart-remove-btn">Remove</button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                            <div class="cart-total-row">
                                <span>Add-ons Total</span>
                                <span>&#8369;<?= number_format($cartTotal, 2) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- /addons-section -->

                <p class="section-label">Guest Details</p>

                <form action="" method="post" enctype="multipart/form-data">
                    <div class="form-grid">

                        <!-- Row 1: Full Name | Email -->
                        <div class="field">
                            <label>Full Name</label>
                            <input type="text" name="guest_name" placeholder="e.g. Zander Atenciana" required>
                        </div>
                        <div class="field">
                            <label>Email Address</label>
                            <input type="email" name="email" placeholder="guest@example.com" required>
                        </div>

                        <!-- Row 2: Room Type | Rooms Required | Number of Guests (3-col) -->
                        <div class="full">
                            <div class="form-row-3">
                                <div class="field">
                                    <label>Room Type</label>
                                    <select name="room_type" required onchange="updateRoomPreview(this.value)">
                                        <?php foreach ($roomRates as $type => $rate): ?>
                                            <option value="<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>"
                                                <?= ($savedRoomPref === $type) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?> — &#8369;<?= number_format($rate, 2) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="field">
                                    <label>Rooms Needed</label>
                                    <select name="room_qty" required>
                                        <?php
                                        $r = 1;
                                        while ($r <= 5) {
                                            echo "<option value='$r'>$r Room(s)</option>";
                                            $r++;
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="field">
                                    <label>Guests Count</label>
                                    <select name="guests" required>
                                        <?php
                                        $g = 1;
                                        do {
                                            echo "<option value='$g'>$g Guest(s)</option>";
                                            $g++;
                                        } while ($g <= 4);
                                        ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Row 3: Check-in | Check-out -->
                        <div class="field">
                            <label>Check-in</label>
                            <input type="date" name="check_in" min="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="field">
                            <label>Check-out</label>
                            <input type="date" name="check_out" min="<?= date('Y-m-d') ?>" required>
                        </div>

                        <!-- Row 4: Special Requests (full width) -->
                        <div class="field full">
                            <label>Special Requests <span style="font-weight:300;letter-spacing:0">(Optional)</span></label>
                            <input type="text" name="requests" placeholder="e.g. Extra bed, late check-in, high floor...">
                        </div>

                        <!-- Row 5: Upload ID (full width) -->
                        <div class="field full">
                            <label>Upload Valid ID <span style="font-weight:300;letter-spacing:0">(For Verification)</span></label>
                            <input type="file" name="id_upload" accept="image/*" required>
                        </div>

                    </div>

                    <?php if ($cartTotal > 0): ?>
                        <div class="alert alert-info" style="margin-top:20px;margin-bottom:0;">
                            Add-ons total of <strong>&#8369;<?= number_format($cartTotal, 2) ?></strong> will be included in your booking.
                        </div>
                    <?php endif; ?>

                    <button type="submit" name="book_room" class="btn-primary">Confirm Reservation</button>
                </form>

                <!-- BOOKING RESULT -->
                <?php if (isset($_SESSION['booking'])): ?>
                    <?php $b = $_SESSION['booking'];
                    unset($_SESSION['booking']); ?>

                    <div class="alert alert-success" style="margin-top:32px;">
                        Reservation confirmed — please save your reference below.
                    </div>

                    <div class="receipt">
                        <div class="receipt-header">
                            <span class="receipt-header-title">Booking Summary</span>
                            <span class="receipt-ref">#<?= htmlspecialchars($b['ref'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <div class="receipt-body">
                            <p class="receipt-greeting"><?= htmlspecialchars($b['greeting'], ENT_QUOTES, 'UTF-8') ?></p>

                            <div class="receipt-grid">
                                <div class="receipt-row">
                                    <span class="r-label">Guest</span>
                                    <span class="r-value"><?= htmlspecialchars($b['guestName'], ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <div class="receipt-row">
                                    <span class="r-label">Email</span>
                                    <span class="r-value"><?= htmlspecialchars($b['email'], ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <div class="receipt-row">
                                    <span class="r-label">Room</span>
                                    <span class="r-value">
                                        <?= htmlspecialchars($b['roomType'], ENT_QUOTES, 'UTF-8') ?>
                                        <span class="receipt-tier"><?= htmlspecialchars($b['roomTier'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </span>
                                </div>
                                <div class="receipt-row">
                                    <span class="r-label">Rooms Reserved</span>
                                    <span class="r-value"><?= $b['roomQty'] ?> Room<?= $b['roomQty'] > 1 ? 's' : '' ?></span>
                                </div>
                                <div class="receipt-row">
                                    <span class="r-label">Guests</span>
                                    <span class="r-value"><?= $b['guests'] ?> Guest<?= $b['guests'] > 1 ? 's' : '' ?></span>
                                </div>
                                <div class="receipt-row">
                                    <span class="r-label">Duration</span>
                                    <span class="r-value"><?= $b['nights'] ?> night<?= $b['nights'] > 1 ? 's' : '' ?>
                                        <span class="receipt-stars" style="margin-left:8px;">
                                            <?php $s = 0;
                                            while ($s < $b['stars']) {
                                                echo "&#9733;";
                                                $s++;
                                            } ?>
                                        </span>
                                    </span>
                                </div>
                                <div class="receipt-row">
                                    <span class="r-label">Check-in</span>
                                    <span class="r-value"><?= htmlspecialchars($b['checkIn'], ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <div class="receipt-row">
                                    <span class="r-label">Check-out</span>
                                    <span class="r-value"><?= htmlspecialchars($b['checkOut'], ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <div class="receipt-row">
                                    <span class="r-label">Booked At</span>
                                    <span class="r-value"><?= htmlspecialchars($b['bookedAt'], ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <div class="receipt-row">
                                    <span class="r-label">ID Upload</span>
                                    <span class="r-value" style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($b['uploadStatus'], ENT_QUOTES, 'UTF-8') ?></span>
                                </div>

                                <?php if (!empty($b['requests'])): ?>
                                    <div class="receipt-row" style="grid-column:1/-1">
                                        <span class="r-label">Special Requests</span>
                                        <span class="r-value" style="font-size:12px;"><?= htmlspecialchars($b['requests'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php if (!empty($b['requestList'])): ?>
                                            <ul class="request-list">
                                                <?php $ri = 0;
                                                do {
                                                    echo "<li>" . htmlspecialchars(trim($b['requestList'][$ri]), ENT_QUOTES, 'UTF-8') . "</li>";
                                                    $ri++;
                                                } while ($ri < count($b['requestList'])); ?>
                                            </ul>
                                            <span style="font-size:11px;color:var(--muted);margin-top:4px;display:block"><?= $b['requestLength'] ?> chars · <?= $b['requestWords'] ?> word<?= $b['requestWords'] != 1 ? 's' : '' ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="receipt-row" style="grid-column:1/-1">
                                        <span class="r-label">Special Requests</span>
                                        <span class="r-value" style="color:var(--muted)">None</span>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($b['cartItems'])): ?>
                                    <!-- SESSION CART SNAPSHOT — displayed in receipt after booking -->
                                    <div class="receipt-row" style="grid-column:1/-1">
                                        <span class="r-label">Add-ons Booked</span>
                                        <table class="addons-receipt-table">
                                            <?php foreach ($b['cartItems'] as $addon => $qty): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($addon, ENT_QUOTES, 'UTF-8') ?> <span style="color:var(--muted)">x<?= $qty ?></span></td>
                                                    <td>&#8369;<?= number_format($addonPrices[$addon] * $qty, 2) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <tr>
                                                <td style="font-weight:500;">Add-ons Subtotal</td>
                                                <td style="font-weight:500;color:var(--accent);">&#8369;<?= number_format($b['cartTotal'], 2) ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($b['isUrgent']): ?>
                            <div style="padding: 0 24px 0;">
                                <div class="alert alert-warn" style="margin:0 0 0 0;border-radius:0;">&#9873; Urgent request noted — our team will prioritize your booking.</div>
                            </div>
                        <?php endif; ?>
                        <?php if ($b['isSuite']): ?>
                            <div style="padding: 0 24px 0;">
                                <div class="alert alert-info" style="margin:0 0 0 0;border-radius:0;">Complimentary breakfast and airport transfer included for Suite guests.</div>
                            </div>
                        <?php elseif ($b['isDeluxe']): ?>
                            <div style="padding: 0 24px 0;">
                                <div class="alert alert-info" style="margin:0 0 0 0;border-radius:0;">City view and premium toiletries included for Deluxe guests.</div>
                            </div>
                        <?php endif; ?>

                        <div class="receipt-total">
                            <div>
                                <div class="receipt-total-label">Grand Total (Room + Add-ons, incl. 12% VAT)</div>
                                <div style="font-size:11px;color:rgba(255,255,255,0.4);margin-top:3px;">
                                    &#8369;<?= number_format($b['pricePerNight'], 2) ?>/night &times; <?= $b['roomQty'] ?> room<?= $b['roomQty'] > 1 ? 's' : '' ?> &times; <?= $b['nights'] ?> night<?= $b['nights'] > 1 ? 's' : '' ?>
                                    <?php if ($b['cartTotal'] > 0): ?>
                                        + &#8369;<?= number_format($b['cartTotal'], 2) ?> add-ons
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="receipt-total-amount">&#8369;<?= number_format($b['grandTotal'], 2) ?></div>
                        </div>
                    </div>

                    <!-- Debug -->
                    <div class="debug-block">
                        <div class="debug-title">Data Type Diagnostics</div>
                        <?php foreach ($b['typeChecks'] as $label => $res): ?>
                            <div class="debug-row">
                                <span class="d-key"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
                                <?= $res ? "<span class='badge-true'>True</span>" : "<span class='badge-false'>False</span>" ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="ref-callout">
                        Your booking reference is <strong>#<?= htmlspecialchars($b['ref'], ENT_QUOTES, 'UTF-8') ?></strong>.
                        Proceed to <a onclick="switchTab('checkout'); return false;">Online Payment</a> to complete your reservation.
                    </div>

                <?php endif; ?>
            </div><!-- /pane-book -->


            <!-- ══════════════════════════════ -->
            <!-- PANE 2: PAYMENT               -->
            <!-- ══════════════════════════════ -->
            <div id="pane-checkout" class="pane <?= $activeTab === 'checkout' ? 'active' : '' ?>">

                <?php if (isset($_SESSION['pay_error'])): ?>
                    <div class="alert alert-error"><?= $_SESSION['pay_error'] ?></div>
                    <?php unset($_SESSION['pay_error']); ?>
                <?php endif; ?>

                <?php if ($paymentResult && $paymentResult['success']): ?>

                    <div class="alert alert-success">Payment processed — your reservation is fully confirmed.</div>

                    <div class="pay-receipt">
                        <div class="pay-receipt-header">
                            <h4>Payment Receipt</h4>
                            <p>Reference #<?= htmlspecialchars($paymentResult['ref'], ENT_QUOTES, 'UTF-8') ?> &middot; <?= htmlspecialchars($paymentResult['paidAt'], ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                        <div class="receipt-body">
                            <div class="receipt-grid">
                                <div class="receipt-row">
                                    <span class="r-label">Guest</span>
                                    <span class="r-value"><?= htmlspecialchars($paymentResult['guestName'], ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <div class="receipt-row">
                                    <span class="r-label">Room</span>
                                    <span class="r-value"><?= htmlspecialchars($paymentResult['roomType'], ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <div class="receipt-row">
                                    <span class="r-label">Check-in</span>
                                    <span class="r-value"><?= htmlspecialchars($paymentResult['checkIn'], ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <div class="receipt-row">
                                    <span class="r-label">Check-out</span>
                                    <span class="r-value"><?= htmlspecialchars($paymentResult['checkOut'], ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <div class="receipt-row">
                                    <span class="r-label">Duration</span>
                                    <span class="r-value"><?= $paymentResult['nights'] ?> night<?= $paymentResult['nights'] > 1 ? 's' : '' ?></span>
                                </div>
                                <div class="receipt-row">
                                    <span class="r-label">Method</span>
                                    <span class="r-value"><?= htmlspecialchars($paymentResult['method'], ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="receipt-total">
                            <div>
                                <div class="receipt-total-label">Amount Paid (incl. 12% VAT)</div>
                            </div>
                            <div class="receipt-total-amount">&#8369;<?= number_format($paymentResult['totalCost'], 2) ?></div>
                        </div>
                    </div>
                    <p style="font-size:12px;color:var(--muted);text-align:center;margin-top:14px;">
                        Please present this receipt upon hotel check-in. Thank you for choosing Sync LuxeHotel.
                    </p>
                    <a href="?tab=book&reset=1" class="btn-primary" style="display:block;text-align:center;text-decoration:none;margin-top:16px;">
                        Book Another Room
                    </a>

                <?php else: ?>

                    <p class="section-label">Settlement</p>
                    <p style="font-size:13px;font-weight:300;color:var(--muted);margin-bottom:28px;line-height:1.7;">
                        Enter your booking reference to look up your reservation, then choose a preferred payment method.
                    </p>

                    <form action="" method="post" id="payForm">

                        <div class="field" style="margin-bottom:28px;">
                            <label>Booking Reference</label>
                            <input type="text" name="pay_ref"
                                placeholder="e.g. ZAN4823"
                                value="<?= htmlspecialchars($_POST['pay_ref'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                style="letter-spacing:0.1em;text-transform:uppercase;" required>
                        </div>

                        <p class="section-label">Payment Method</p>
                        <input type="hidden" name="pay_method" id="pay_method_input" value="card">

                        <div class="pay-methods">
                            <div class="pay-method selected" id="pm-card" onclick="selectPayMethod('card')">
                                <span class="pm-icon">&#x1F4B3;</span>
                                <span class="pm-name">Card</span>
                                <span class="pm-sub">Visa / MC / JCB</span>
                            </div>
                            <div class="pay-method" id="pm-gcash" onclick="selectPayMethod('gcash')">
                                <span class="pm-icon">&#x1F4F1;</span>
                                <span class="pm-name">GCash</span>
                                <span class="pm-sub">Mobile wallet</span>
                            </div>
                            <div class="pay-method" id="pm-maya" onclick="selectPayMethod('maya')">
                                <span class="pm-icon">&#x1F4F1;</span>
                                <span class="pm-name">Maya</span>
                                <span class="pm-sub">Mobile wallet</span>
                            </div>
                            <div class="pay-method" id="pm-bank" onclick="selectPayMethod('bank')">
                                <span class="pm-icon">&#x1F3E6;</span>
                                <span class="pm-name">Bank</span>
                                <span class="pm-sub">InstaPay / PESONet</span>
                            </div>
                        </div>

                        <!-- Card fields -->
                        <div id="card-fields">
                            <div class="card-fields-grid">
                                <div class="field span-full">
                                    <label>Card Number</label>
                                    <input type="text" name="card_number"
                                        placeholder="0000  0000  0000  0000"
                                        maxlength="19" oninput="formatCardNumber(this)"
                                        style="letter-spacing:0.08em;">
                                </div>
                                <div class="field">
                                    <label>Expiration Year</label>
                                    <select name="card_expiry_year" required>
                                        <option value="">Select Year</option>
                                        <?php
                                        // FOR LOOP (Lesson 2) & Date Function (Lesson 3)
                                        $currentYear = date("Y"); // Automatically gets the current year
                                        for ($i = 0; $i <= 10; $i++) {
                                            $year = $currentYear + $i;
                                            echo "<option value='$year'>$year</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="field">
                                    <label>CVV</label>
                                    <input type="password" name="card_cvv" placeholder="&bull;&bull;&bull;" maxlength="4">
                                </div>
                                <div class="field">
                                    <label>Name on Card</label>
                                    <input type="text" name="card_name" placeholder="Full name">
                                </div>
                            </div>
                        </div>

                        <div id="ewallet-fields" style="display:none;">
                            <div class="info-box">
                                <strong>E-wallet Instructions</strong><br>
                                You will receive an OTP-based payment link via SMS/email after submission.
                                Please ensure your registered mobile number is active and available.
                            </div>
                        </div>

                        <div id="bank-fields" style="display:none;">
                            <div class="info-box">
                                <strong>Bank Transfer Details</strong><br>
                                Account Name: Sync LuxeHotel Inc.<br>
                                Bank: BDO &nbsp;&middot;&nbsp; Account No: <strong>0000-1234-5678</strong><br>
                                Use your booking reference as the transfer remarks.
                            </div>
                        </div>

                        <button type="submit" name="pay_now" class="btn-primary btn-pay" style="margin-top:28px;">Complete Payment</button>
                        <p class="pay-note">All transactions are encrypted and inclusive of 12% VAT.</p>
                    </form>

                <?php endif; ?>
            </div><!-- /pane-checkout -->


            <!-- ══════════════════════════════ -->
            <!-- PANE 3: POLICIES              -->
            <!-- ══════════════════════════════ -->
            <div id="pane-policies" class="pane <?= $activeTab === 'policies' ? 'active' : '' ?>">

                <p class="section-label">Hotel Guidelines</p>

                <ul class="policy-list">
                    <li class="policy-item">
                        <div class="policy-icon">&#x25F7;</div>
                        <div class="policy-content">
                            <div class="policy-title">Check-in &amp; Check-out</div>
                            <div class="policy-desc">Check-in begins at 2:00 PM. Check-out is by 12:00 NN. Early check-in and late check-out are subject to availability and additional charges.</div>
                        </div>
                    </li>
                    <li class="policy-item">
                        <div class="policy-icon">&#x21A9;</div>
                        <div class="policy-content">
                            <div class="policy-title">Cancellation &amp; Refunds</div>
                            <div class="policy-desc">Full refund for cancellations made at least 48 hours before arrival. No refund is issued for late cancellations or no-shows.</div>
                        </div>
                    </li>
                    <li class="policy-item">
                        <div class="policy-icon">&#x25CE;</div>
                        <div class="policy-content">
                            <div class="policy-title">Valid ID Requirement</div>
                            <div class="policy-desc">A physical copy of a government-issued photo ID must be presented at reception upon arrival for verification.</div>
                        </div>
                    </li>
                    <li class="policy-item">
                        <div class="policy-icon">&#x2717;</div>
                        <div class="policy-content">
                            <div class="policy-title">Pets &amp; Smoking</div>
                            <div class="policy-desc">Pets are not permitted on the premises. Smoking is strictly prohibited inside rooms. Designated smoking areas are available outside the building.</div>
                        </div>
                    </li>
                    <li class="policy-item">
                        <div class="policy-icon">+</div>
                        <div class="policy-content">
                            <div class="policy-title">Extra Occupants</div>
                            <div class="policy-desc">Additional charges apply for guests beyond each room's standard capacity. Please declare extra occupants at the time of booking.</div>
                        </div>
                    </li>
                    <li class="policy-item">
                        <div class="policy-icon">&#8369;</div>
                        <div class="policy-content">
                            <div class="policy-title">Room Rates</div>
                            <div class="policy-desc">Rates are per room per night. All prices are subject to 12% VAT.</div>
                            <table class="rate-table">
                                <?php for ($i = 0; $i < count($roomTypes); $i++):
                                    $t = $roomTypes[$i]; ?>
                                    <tr>
                                        <td><?= htmlspecialchars($t, ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>&#8369;<?= number_format($ratesRef[$t], 2) ?> / night</td>
                                    </tr>
                                <?php endfor; ?>
                            </table>
                        </div>
                    </li>
                </ul>

            </div><!-- /pane-policies -->

        </div><!-- /card -->

    </div><!-- /page-wrap -->

    <script>
        const roomImages = {
            'Standard': {
                url: 'https://grazia-prod.oss-ap-southeast-1.aliyuncs.com/resources/uid_100036644/hotel_standard_room_c4aa5274.png',
                label: 'Standard Room'
            },
            'Deluxe': {
                url: 'https://grazia-prod.oss-ap-southeast-1.aliyuncs.com/resources/uid_100036644/hotel_deluxe_room_42480e8d.png',
                label: 'Deluxe Room'
            },
            'Suite': {
                url: 'https://grazia-prod.oss-ap-southeast-1.aliyuncs.com/resources/uid_100036644/hotel_suite_room_c0e6099f.png',
                label: 'Suite'
            }
        };

        function updateRoomPreview(roomType) {
            const data = roomImages[roomType];
            if (!data) return;
            const img = document.getElementById('room-preview-img');
            const badge = document.getElementById('room-preview-badge');
            img.style.opacity = '0';
            setTimeout(() => {
                img.src = data.url;
                img.alt = data.label;
                badge.textContent = data.label;
                img.style.opacity = '1';
            }, 200);
        }

        function switchTab(tab) {
            document.querySelectorAll('.pane').forEach(p => p.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(t => t.classList.remove('active'));
            document.getElementById('pane-' + tab).classList.add('active');
            document.querySelector('[onclick="switchTab(\'' + tab + '\')"]').classList.add('active');
            history.replaceState(null, '', '?tab=' + tab);
        }

        function selectPayMethod(method) {
            document.querySelectorAll('.pay-method').forEach(c => c.classList.remove('selected'));
            document.getElementById('pm-' + method).classList.add('selected');
            document.getElementById('pay_method_input').value = method;
            document.getElementById('card-fields').style.display = method === 'card' ? 'block' : 'none';
            document.getElementById('ewallet-fields').style.display = (method === 'gcash' || method === 'maya') ? 'block' : 'none';
            document.getElementById('bank-fields').style.display = method === 'bank' ? 'block' : 'none';
        }

        function formatCardNumber(input) {
            var val = input.value.replace(/\D/g, '').substring(0, 16);
            input.value = val.replace(/(.{4})/g, '$1  ').trim();
        }

        function formatExpiry(input) {
            var val = input.value.replace(/\D/g, '').substring(0, 4);
            if (val.length >= 3) val = val.substring(0, 2) + '/' + val.substring(2);
            input.value = val;
        }

        <?php
        // Clear sessionStorage after booking confirmed OR after payment success
        $shouldClear = (isset($_GET['status']) && $_GET['status'] === 'confirmed')
            || ($paymentResult && $paymentResult['success'])
            || (isset($_GET['reset']) && $_GET['reset'] === '1');
        ?>
        <?php if ($shouldClear): ?>
            // Clear all saved form data
            sessionStorage.clear();
        <?php else: ?>
            // Restore saved form inputs
            document.querySelectorAll('input, select').forEach(el => {
                if (el.name && el.type !== 'file' && el.type !== 'password') {
                    var saved = sessionStorage.getItem('form_' + el.name);
                    if (saved) el.value = saved;
                }
                el.addEventListener('change', () => {
                    if (el.type !== 'file' && el.type !== 'password') {
                        sessionStorage.setItem('form_' + el.name, el.value);
                    }
                });
                el.addEventListener('input', () => {
                    if (el.type !== 'file' && el.type !== 'password') {
                        sessionStorage.setItem('form_' + el.name, el.value);
                    }
                });
            });
        <?php endif; ?>
    </script>
</body>

</html>
