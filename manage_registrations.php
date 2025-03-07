<?php
require_once 'includes/config.php';
require_once 'includes/header.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Get tournament ID
$tournament_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Verify tournament exists and user is the creator
$stmt = $pdo->prepare("
    SELECT t.*, g.name as game_name 
    FROM tournaments t 
    JOIN games g ON t.game_id = g.id 
    WHERE t.id = ? AND t.creator_id = ?
");
$stmt->execute([$tournament_id, $_SESSION['user_id']]);
$tournament = $stmt->fetch();

if (!$tournament) {
    $_SESSION['error'] = "Tournament not found or you don't have permission to manage it.";
    header("Location: dashboard.php");
    exit();
}

// Handle payment verification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['verify_payment']) || isset($_POST['reject_payment'])) {
        $registration_id = $_POST['registration_id'];
        $new_payment_status = isset($_POST['verify_payment']) ? 'verified' : 'rejected';
        
        try {
            $pdo->beginTransaction();
            
            // Update payment status
            $stmt = $pdo->prepare("
                UPDATE tournament_registrations 
                SET payment_status = ? 
                WHERE id = ? AND tournament_id = ?
            ");
            $stmt->execute([$new_payment_status, $registration_id, $tournament_id]);
            
            // If payment is verified, automatically approve the registration
            if ($new_payment_status === 'verified') {
                $stmt = $pdo->prepare("
                    UPDATE tournament_registrations 
                    SET status = 'approved' 
                    WHERE id = ? AND tournament_id = ?
                ");
                $stmt->execute([$registration_id, $tournament_id]);
                
                // Add to tournament_players
                $stmt = $pdo->prepare("
                    INSERT INTO tournament_players (tournament_id, player_id, joined_date)
                    SELECT tournament_id, player_id, NOW()
                    FROM tournament_registrations
                    WHERE id = ?
                ");
                $stmt->execute([$registration_id]);
                
                // Update current players count
                $stmt = $pdo->prepare("
                    UPDATE tournaments 
                    SET current_players = current_players + 1 
                    WHERE id = ?
                ");
                $stmt->execute([$tournament_id]);
            }
            
            $pdo->commit();
            $_SESSION['success'] = "Payment " . ($new_payment_status === 'verified' ? 'verified' : 'rejected') . " successfully.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Failed to process payment verification. Please try again.";
        }
        
        header("Location: manage_registrations.php?id=" . $tournament_id);
        exit();
    }
    
    // Handle approval/rejection
    if (isset($_POST['approve']) || isset($_POST['reject'])) {
        $registration_id = $_POST['registration_id'];
        $new_status = isset($_POST['approve']) ? 'approved' : 'rejected';
        
        try {
            $pdo->beginTransaction();
            
            // Update registration status
            $stmt = $pdo->prepare("
                UPDATE tournament_registrations 
                SET status = ? 
                WHERE id = ? AND tournament_id = ?
            ");
            $stmt->execute([$new_status, $registration_id, $tournament_id]);
            
            // If approved, add to tournament_players
            if ($new_status === 'approved') {
                $stmt = $pdo->prepare("
                    INSERT INTO tournament_players (tournament_id, player_id, joined_date)
                    SELECT tournament_id, player_id, NOW()
                    FROM tournament_registrations
                    WHERE id = ?
                ");
                $stmt->execute([$registration_id]);
                
                // Update current players count
                $stmt = $pdo->prepare("
                    UPDATE tournaments 
                    SET current_players = current_players + 1 
                    WHERE id = ?
                ");
                $stmt->execute([$tournament_id]);
            }
            
            $pdo->commit();
            $_SESSION['success'] = "Player registration " . ($new_status === 'approved' ? 'approved' : 'rejected') . " successfully.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Failed to process registration. Please try again.";
        }
        
        header("Location: manage_registrations.php?id=" . $tournament_id);
        exit();
    }
}

// Get registrations with payment information
$stmt = $pdo->prepare("
    SELECT r.*, u.username,
           r.transaction_id, r.transaction_screenshot, r.payment_status
    FROM tournament_registrations r
    JOIN users u ON r.player_id = u.id
    WHERE r.tournament_id = ?
    ORDER BY r.registration_date ASC
");
$stmt->execute([$tournament_id]);
$registrations = $stmt->fetchAll();

// Get current players
$stmt = $pdo->prepare("
    SELECT tp.*, u.username 
    FROM tournament_players tp
    JOIN users u ON tp.player_id = u.id
    WHERE tp.tournament_id = ?
    ORDER BY tp.joined_date ASC
");
$stmt->execute([$tournament_id]);
$players = $stmt->fetchAll();
?>

<div class="row">
    <div class="col-md-12">
        <div class="card mb-4">
            <div class="card-header">
                <h4>Manage Tournament: <?php echo htmlspecialchars($tournament['name']); ?></h4>
                <p class="mb-0">
                    <strong>Game:</strong> <?php echo htmlspecialchars($tournament['game_name']); ?> |
                    <strong>Players:</strong> <?php echo $tournament['current_players']; ?>/<?php echo $tournament['max_players']; ?> |
                    <strong>Start Date:</strong> <?php echo date('Y-m-d H:i', strtotime($tournament['start_date'])); ?>
                </p>
            </div>
            <div class="card-body">
                <h5>Room Information</h5>
                <p>
                    <strong>Room ID:</strong> <?php echo htmlspecialchars($tournament['room_id']); ?><br>
                    <strong>Room Password:</strong> <?php echo htmlspecialchars($tournament['room_password']); ?>
                </p>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Pending Registrations</h5>
            </div>
            <div class="card-body">
                <?php if (empty($registrations)): ?>
                    <p>No pending registrations.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Player</th>
                                    <th>Registration Date</th>
                                    <th>Status</th>
                                    <?php if ($tournament['is_paid']): ?>
                                        <th>Payment Status</th>
                                        <th>Transaction Details</th>
                                    <?php endif; ?>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($registrations as $registration): ?>
                                    <?php if ($registration['status'] === 'pending' || ($tournament['is_paid'] && $registration['payment_status'] === 'pending')): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($registration['username']); ?></td>
                                            <td><?php echo date('Y-m-d H:i', strtotime($registration['registration_date'])); ?></td>
                                            <td>
                                                <?php if ($registration['status'] === 'pending'): ?>
                                                    <span class="badge bg-warning">Pending</span>
                                                <?php elseif ($registration['status'] === 'approved'): ?>
                                                    <span class="badge bg-success">Approved</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Rejected</span>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <?php if ($tournament['is_paid']): ?>
                                                <td>
                                                    <?php if ($registration['payment_status'] === 'pending'): ?>
                                                        <span class="badge bg-warning">Payment Pending</span>
                                                    <?php elseif ($registration['payment_status'] === 'verified'): ?>
                                                        <span class="badge bg-success">Payment Verified</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Payment Rejected</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($registration['transaction_id']): ?>
                                                        <p class="mb-1">
                                                            <strong>Transaction ID:</strong><br>
                                                            <?php echo htmlspecialchars($registration['transaction_id']); ?>
                                                        </p>
                                                        <?php if ($registration['transaction_screenshot']): ?>
                                                            <a href="<?php echo htmlspecialchars($registration['transaction_screenshot']); ?>" 
                                                               target="_blank" class="btn btn-sm btn-info">
                                                                <i class="fas fa-image"></i> View Screenshot
                                                            </a>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">No transaction details</span>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endif; ?>
                                            
                                            <td>
                                                <?php if ($tournament['is_paid'] && $registration['payment_status'] === 'pending'): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="registration_id" value="<?php echo $registration['id']; ?>">
                                                        <button type="submit" name="verify_payment" class="btn btn-success btn-sm">
                                                            <i class="fas fa-check"></i> Verify Payment
                                                        </button>
                                                        <button type="submit" name="reject_payment" class="btn btn-danger btn-sm">
                                                            <i class="fas fa-times"></i> Reject Payment
                                                        </button>
                                                    </form>
                                                <?php elseif (!$tournament['is_paid'] || $registration['payment_status'] === 'verified'): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="registration_id" value="<?php echo $registration['id']; ?>">
                                                        <button type="submit" name="approve" class="btn btn-success btn-sm">
                                                            <i class="fas fa-check"></i> Approve
                                                        </button>
                                                        <button type="submit" name="reject" class="btn btn-danger btn-sm">
                                                            <i class="fas fa-times"></i> Reject
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Current Players</h5>
            </div>
            <div class="card-body">
                <?php if (empty($players)): ?>
                    <p>No players have joined yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Player</th>
                                    <th>Join Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($players as $player): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($player['username']); ?></td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($player['joined_date'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 