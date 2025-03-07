<?php
require_once 'includes/config.php';
require_once 'includes/header.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$tournament_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get tournament details
$stmt = $pdo->prepare("
    SELECT t.*, g.name as game_name 
    FROM tournaments t 
    JOIN games g ON t.game_id = g.id 
    WHERE t.id = ?
");
$stmt->execute([$tournament_id]);
$tournament = $stmt->fetch();

if (!$tournament) {
    $_SESSION['error'] = "Tournament not found.";
    header("Location: tournaments.php");
    exit();
}

// Check if already registered
$stmt = $pdo->prepare("
    SELECT * FROM tournament_registrations 
    WHERE tournament_id = ? AND player_id = ?
");
$stmt->execute([$tournament_id, $_SESSION['user_id']]);
$registration = $stmt->fetch();

if ($registration) {
    $_SESSION['error'] = "You have already registered for this tournament.";
    header("Location: tournaments.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $transaction_id = $_POST['transaction_id'] ?? '';
    
    // Handle file upload for transaction screenshot
    $screenshot_path = '';
    if (isset($_FILES['transaction_screenshot']) && $_FILES['transaction_screenshot']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/transactions/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['transaction_screenshot']['name'], PATHINFO_EXTENSION);
        $filename = uniqid('transaction_') . '.' . $file_extension;
        $target_path = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['transaction_screenshot']['tmp_name'], $target_path)) {
            $screenshot_path = $target_path;
        }
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO tournament_registrations (
                tournament_id, player_id, status, transaction_id, 
                transaction_screenshot, payment_status
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $initial_status = $tournament['requires_approval'] ? 'pending' : 'approved';
        $payment_status = $tournament['is_paid'] ? 'pending' : 'verified';
        
        $stmt->execute([
            $tournament_id,
            $_SESSION['user_id'],
            $initial_status,
            $transaction_id,
            $screenshot_path,
            $payment_status
        ]);
        
        $_SESSION['success'] = "Registration submitted successfully!" . 
            ($tournament['is_paid'] ? " Payment verification pending." : "");
        header("Location: tournaments.php");
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = "Failed to register. Please try again.";
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h4>Register for Tournament: <?php echo htmlspecialchars($tournament['name']); ?></h4>
            </div>
            <div class="card-body">
                <div class="tournament-details mb-4">
                    <p><strong>Game:</strong> <?php echo htmlspecialchars($tournament['game_name']); ?></p>
                    <p><strong>Start Date:</strong> <?php echo date('Y-m-d H:i', strtotime($tournament['start_date'])); ?></p>
                    <p><strong>Players:</strong> <?php echo $tournament['current_players']; ?>/<?php echo $tournament['max_players']; ?></p>
                    <?php if ($tournament['is_paid']): ?>
                        <div class="alert alert-info">
                            <h5 class="alert-heading">Registration Fee</h5>
                            <p class="display-6 mb-0">$<?php echo number_format($tournament['registration_fee'], 2); ?></p>
                            <p class="mb-0 mt-2"><strong>Prize Pool:</strong> $<?php echo number_format($tournament['prize_pool'], 2); ?></p>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($tournament['is_paid']): ?>
                    <div id="payment-section">
                        <!-- Initial View - Only Pay Button -->
                        <div id="initial-view">
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-primary btn-lg" onclick="showPaymentDetails()">
                                    <i class="fas fa-credit-card me-2"></i>Pay Registration Fee
                                </button>
                            </div>
                        </div>

                        <!-- Payment Details View (Initially Hidden) -->
                        <div id="payment-details" style="display: none;">
                            <form method="POST" action="" enctype="multipart/form-data">
                                <div class="payment-info mb-4">
                                    <h5>Payment Information</h5>
                                    <div class="upi-details p-3 mb-3 border rounded">
                                        <p class="mb-2"><strong>Amount to Pay:</strong> $<?php echo number_format($tournament['registration_fee'], 2); ?></p>
                                        <p class="mb-2"><strong>UPI ID:</strong> <?php echo htmlspecialchars($tournament['upi_id']); ?></p>
                                        
                                        <!-- QR Code -->
                                        <div class="qr-code mb-3 text-center">
                                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=upi://pay?pa=<?php echo urlencode($tournament['upi_id']); ?>&pn=Tournament Registration&am=<?php echo $tournament['registration_fee']; ?>" 
                                                 alt="Payment QR Code" class="img-fluid">
                                            <p class="mt-2 text-muted">Scan this QR code to pay</p>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="transaction_id" class="form-label">Transaction ID</label>
                                            <input type="text" class="form-control" id="transaction_id" name="transaction_id" required>
                                            <small class="text-muted">Enter the UPI transaction ID after making the payment</small>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="transaction_screenshot" class="form-label">Payment Screenshot</label>
                                            <input type="file" class="form-control" id="transaction_screenshot" name="transaction_screenshot" accept="image/*" required>
                                            <small class="text-muted">Upload a screenshot of your payment confirmation</small>
                                        </div>
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-check-circle me-2"></i>Submit Registration
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" onclick="hidePaymentDetails()">
                                            <i class="fas fa-arrow-left me-2"></i>Back
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <form method="POST" action="">
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-user-plus me-2"></i>Register for Tournament
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function showPaymentDetails() {
    document.getElementById('initial-view').style.display = 'none';
    document.getElementById('payment-details').style.display = 'block';
}

function hidePaymentDetails() {
    document.getElementById('payment-details').style.display = 'none';
    document.getElementById('initial-view').style.display = 'block';
}
</script>

<?php require_once 'includes/footer.php'; ?> 