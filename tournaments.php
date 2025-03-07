<?php
require_once 'includes/config.php';
require_once 'includes/header.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Get all game categories
$stmt = $pdo->prepare("SELECT * FROM games ORDER BY name");
$stmt->execute();
$games = $stmt->fetchAll();

// Get selected game filter
$selected_game = isset($_GET['game']) ? $_GET['game'] : 'all';

// Build tournament query
$query = "
    SELECT t.*, g.name as game_name, 
           (SELECT COUNT(*) FROM tournament_players WHERE tournament_id = t.id) as current_players,
           (SELECT status FROM tournament_registrations WHERE tournament_id = t.id AND player_id = ?) as registration_status,
           (SELECT COUNT(*) FROM tournament_players WHERE tournament_id = t.id AND player_id = ?) as is_player,
           (SELECT tournament_type FROM tournaments WHERE id = t.id) as tournament_type,
           (SELECT team_size FROM tournaments WHERE id = t.id) as team_size,
           (SELECT total_teams FROM tournaments WHERE id = t.id) as total_teams
    FROM tournaments t 
    JOIN games g ON t.game_id = g.id 
    WHERE t.status = 'active'";

if ($selected_game !== 'all') {
    $query .= " AND g.id = :game_id";
}
$query .= " ORDER BY t.start_date ASC";

$stmt = $pdo->prepare($query);
if ($selected_game !== 'all') {
    $stmt->bindParam(':game_id', $selected_game);
}
$stmt->bindValue(1, $_SESSION['user_id']);
$stmt->bindValue(2, $_SESSION['user_id']);
$stmt->execute();
$tournaments = $stmt->fetchAll();

// Process tournament registration request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_tournament'])) {
    $tournament_id = $_POST['tournament_id'];
    
    // Check if already registered
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM tournament_registrations 
        WHERE tournament_id = ? AND player_id = ?
    ");
    $stmt->execute([$tournament_id, $_SESSION['user_id']]);
    
    if ($stmt->fetchColumn() == 0) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO tournament_registrations (tournament_id, player_id, status)
                VALUES (?, ?, 'pending')
            ");
            $stmt->execute([$tournament_id, $_SESSION['user_id']]);
            
            $_SESSION['success'] = "Registration submitted successfully! Waiting for admin approval.";
        } catch (Exception $e) {
            $_SESSION['error'] = "Failed to register. Please try again.";
        }
    } else {
        $_SESSION['error'] = "You have already registered for this tournament";
    }
    
    header("Location: tournaments.php" . ($selected_game !== 'all' ? "?game=" . $selected_game : ""));
    exit();
}
?>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h4 class="float-start">Available Tournaments</h4>
                <div class="float-end">
                    <form class="d-inline" method="GET">
                        <select name="game" class="form-select form-select-sm d-inline-block w-auto" onchange="this.form.submit()">
                            <option value="all">All Games</option>
                            <?php foreach ($games as $game): ?>
                                <option value="<?php echo $game['id']; ?>" <?php echo $selected_game == $game['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($game['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
            </div>
            <div class="card-body">
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
                <?php endif; ?>

                <?php if (empty($tournaments)): ?>
                    <p>No tournaments available.</p>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($tournaments as $tournament): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card h-100">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">
                                            <?php echo htmlspecialchars($tournament['name']); ?>
                                            <span class="badge bg-primary float-end">
                                                <?php echo htmlspecialchars($tournament['game_name']); ?>
                                            </span>
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <p class="card-text">
                                            <strong>Players:</strong> <?php echo $tournament['current_players']; ?>/<?php echo $tournament['max_players']; ?><br>
                                            <strong>Start Date:</strong> <?php echo date('Y-m-d H:i', strtotime($tournament['start_date'])); ?><br>
                                            <?php if ($tournament['is_paid']): ?>
                                                <div class="alert alert-info mt-2">
                                                    <h6 class="alert-heading mb-2">Registration Fee</h6>
                                                    <p class="h4 mb-1">$<?php echo number_format($tournament['registration_fee'], 2); ?></p>
                                                    <small class="text-muted">Prize Pool: $<?php echo number_format($tournament['prize_pool'], 2); ?></small>
                                                </div>
                                            <?php endif; ?>
                                        </p>
                                        
                                        <div class="tournament-info mb-2">
                                            <i class="fas fa-users me-2"></i>
                                            <?php if ($tournament['tournament_type'] === 'team'): ?>
                                                Team Tournament (<?= $tournament['team_size'] ?> players per team, <?= $tournament['total_teams'] ?> teams max)
                                            <?php else: ?>
                                                Solo Tournament (<?= $tournament['max_players'] ?> players max)
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if ($tournament['is_player']): ?>
                                            <div class="alert alert-success mb-3">
                                                <h6 class="mb-2">Room Information</h6>
                                                <strong>Room ID:</strong> <?php echo htmlspecialchars($tournament['room_id']); ?><br>
                                                <strong>Password:</strong> <?php echo htmlspecialchars($tournament['room_password']); ?>
                                            </div>
                                        <?php elseif ($tournament['registration_status'] === 'pending'): ?>
                                            <div class="alert alert-warning mb-3">
                                                Registration pending approval
                                            </div>
                                        <?php elseif ($tournament['registration_status'] === 'rejected'): ?>
                                            <div class="alert alert-danger mb-3">
                                                Registration was rejected
                                            </div>
                                        <?php elseif ($tournament['current_players'] < $tournament['max_players']): ?>
                                            <?php if ($tournament['is_paid']): ?>
                                                <!-- Payment Section -->
                                                <div class="payment-section-<?php echo $tournament['id']; ?>">
                                                    <!-- Initial View -->
                                                    <div class="initial-view">
                                                        <div class="d-grid">
                                                            <button type="button" class="btn btn-primary" 
                                                                    onclick="showPaymentQR(<?php echo $tournament['id']; ?>, 
                                                                                         '<?php echo $tournament['upi_id']; ?>', 
                                                                                         <?php echo $tournament['registration_fee']; ?>)">
                                                                <i class="fas fa-credit-card me-2"></i>Pay & Register
                                                            </button>
                                                        </div>
                                                    </div>

                                                    <!-- Payment QR View (Initially Hidden) -->
                                                    <div class="payment-qr-view" style="display: none;">
                                                        <div class="text-center mb-3">
                                                            <div class="qr-code-container mb-3"></div>
                                                            <p class="text-muted">Scan QR code to pay</p>
                                                        </div>
                                                        
                                                        <form method="POST" class="payment-form" enctype="multipart/form-data">
                                                            <input type="hidden" name="tournament_id" value="<?php echo $tournament['id']; ?>">
                                                            <div class="mb-3">
                                                                <label class="form-label">Transaction ID</label>
                                                                <input type="text" name="transaction_id" class="form-control" required>
                                                                <small class="text-muted">Enter the UPI transaction ID</small>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Payment Screenshot</label>
                                                                <input type="file" name="transaction_screenshot" class="form-control" accept="image/*" required>
                                                            </div>
                                                            <div class="d-grid gap-2">
                                                                <button type="submit" name="register_tournament" class="btn btn-success">
                                                                    <i class="fas fa-check-circle me-2"></i>Complete Registration
                                                                </button>
                                                                <button type="button" class="btn btn-outline-secondary" 
                                                                        onclick="hidePaymentQR(<?php echo $tournament['id']; ?>)">
                                                                    <i class="fas fa-arrow-left me-2"></i>Back
                                                                </button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <?php if ($tournament['tournament_type'] === 'team'): ?>
                                                    <a href="register_team.php?tournament_id=<?= $tournament['id'] ?>" class="btn btn-primary">
                                                        <i class="fas fa-users me-2"></i>Register Team
                                                    </a>
                                                <?php else: ?>
                                                    <form method="POST" action="register_tournament.php">
                                                        <input type="hidden" name="tournament_id" value="<?= $tournament['id'] ?>">
                                                        <button type="submit" class="btn btn-primary">
                                                            <i class="fas fa-user me-2"></i>Register
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <button class="btn btn-secondary" disabled>Tournament Full</button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <?php if ($tournament['tournament_type'] === 'team'): ?>
                                                <div class="alert alert-info">
                                                    <h6>Team: <?= htmlspecialchars($teamInfo['team_name']) ?></h6>
                                                    <p class="mb-0">Status: <?= ucfirst($registrationStatus) ?></p>
                                                </div>
                                            <?php else: ?>
                                                <div class="alert alert-info">
                                                    Registration Status: <?= ucfirst($registrationStatus) ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        
                                        <?php if ($tournament['creator_id'] === $_SESSION['user_id']): ?>
                                            <a href="manage_registrations.php?id=<?php echo $tournament['id']; ?>" class="btn btn-info btn-sm mt-2">
                                                <i class="fas fa-users-cog"></i> Manage Registrations
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function showPaymentQR(tournamentId, upiId, amount) {
    const paymentSection = document.querySelector(`.payment-section-${tournamentId}`);
    const qrContainer = paymentSection.querySelector('.qr-code-container');
    const initialView = paymentSection.querySelector('.initial-view');
    const qrView = paymentSection.querySelector('.payment-qr-view');

    // Generate QR code
    const qrData = `upi://pay?pa=${encodeURIComponent(upiId)}&pn=Tournament Registration&am=${amount}`;
    const qrImage = document.createElement('img');
    qrImage.src = `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(qrData)}`;
    qrImage.alt = 'Payment QR Code';
    qrImage.className = 'img-fluid';
    
    // Clear and add new QR code
    qrContainer.innerHTML = '';
    qrContainer.appendChild(qrImage);

    // Show QR view, hide initial view
    initialView.style.display = 'none';
    qrView.style.display = 'block';
}

function hidePaymentQR(tournamentId) {
    const paymentSection = document.querySelector(`.payment-section-${tournamentId}`);
    const initialView = paymentSection.querySelector('.initial-view');
    const qrView = paymentSection.querySelector('.payment-qr-view');

    // Show initial view, hide QR view
    qrView.style.display = 'none';
    initialView.style.display = 'block';
}
</script>

<?php
// Add this to your PHP section to fetch team information
if ($isRegistered && $tournament['tournament_type'] === 'team') {
    $stmt = $pdo->prepare("
        SELECT t.* 
        FROM teams t 
        JOIN tournament_registrations tr ON t.team_id = tr.team_id 
        WHERE tr.tournament_id = ? AND tr.player_id = ?
    ");
    $stmt->execute([$tournament['id'], $_SESSION['user_id']]);
    $teamInfo = $stmt->fetch();
}
?>

<?php require_once 'includes/footer.php'; ?> 