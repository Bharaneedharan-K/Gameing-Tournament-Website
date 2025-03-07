<?php
require_once 'includes/config.php';
require_once 'includes/header.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Get user points
$user_points = getUserPoints($_SESSION['user_id']);

// Get all games
$stmt = $pdo->prepare("SELECT * FROM games ORDER BY name");
$stmt->execute();
$games = $stmt->fetchAll();

// Process tournament creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $game_id = $_POST['game_id'];
    $max_players = $_POST['max_players'];
    $start_date = $_POST['start_date'];
    $is_paid = isset($_POST['is_paid']) && $_POST['is_paid'] == 1;
    $prize_pool = $is_paid ? $_POST['prize_pool'] : 0;
    $registration_fee = $is_paid ? $_POST['registration_fee'] : 0;
    $upi_id = $is_paid ? $_POST['upi_id'] : '';
    $room_id = $_POST['room_id'];
    $room_password = $_POST['room_password'];
    $requires_approval = isset($_POST['requires_approval']) && $_POST['requires_approval'] == 1;
    $tournament_type = $_POST['tournament_type'];
    $team_size = ($tournament_type === 'team') ? $_POST['team_size'] : null;
    $total_teams = ($tournament_type === 'team') ? $_POST['total_teams'] : null;
    
    $errors = [];
    
    // Validate input
    if (empty($name)) {
        $errors[] = "Tournament name is required";
    }
    
    if ($max_players < 2) {
        $errors[] = "Minimum 2 players required";
    }
    
    if (strtotime($start_date) < time()) {
        $errors[] = "Start date must be in the future";
    }
    
    // Validate paid tournament fields
    if ($is_paid) {
        if (empty($registration_fee) || $registration_fee <= 0) {
            $errors[] = "Registration fee must be greater than 0 for paid tournaments";
        }
        
        if (empty($prize_pool) || $prize_pool <= 0) {
            $errors[] = "Prize pool must be greater than 0 for paid tournaments";
        }
        
        if ($prize_pool < $registration_fee) {
            $errors[] = "Prize pool must be at least equal to the registration fee";
        }
        
        if (empty($upi_id)) {
            $errors[] = "UPI ID is required for paid tournaments";
        } elseif (!preg_match('/^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+$/', $upi_id)) {
            $errors[] = "Invalid UPI ID format";
        }
        
        // Check user points for paid tournament
        if ($user_points < 1000) {
            $errors[] = "You need at least 1000 points to create a paid tournament";
        }
    }
    
    // Validate team tournament fields
    if ($tournament_type === 'team') {
        if (empty($team_size) || $team_size < 2) {
            $errors[] = "Team size must be at least 2 players";
        }
        if (empty($total_teams) || $total_teams < 2) {
            $errors[] = "Total teams must be at least 2";
        }
        // Update max_players based on team settings
        $max_players = $team_size * $total_teams;
    }
    
    if (empty($room_id)) {
        $errors[] = "Room ID is required";
    }
    
    if (empty($room_password)) {
        $errors[] = "Room password is required";
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO tournaments (
                    name, game_id, creator_id, max_players, start_date, 
                    is_paid, prize_pool, registration_fee, upi_id,
                    room_id, room_password, requires_approval,
                    status, created_at, tournament_type, team_size, total_teams
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW(), ?, ?, ?)
            ");
            
            $stmt->execute([
                $name, $game_id, $_SESSION['user_id'], $max_players,
                $start_date, $is_paid, $prize_pool, $registration_fee, $upi_id,
                $room_id, $room_password, $requires_approval,
                $tournament_type, $team_size, $total_teams
            ]);
            
            $_SESSION['success'] = "Tournament created successfully!";
            header("Location: tournaments.php");
            exit();
        } catch (Exception $e) {
            $errors[] = "Failed to create tournament. Please try again.";
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h4>Create Tournament</h4>
            </div>
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="name" class="form-label">Tournament Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="game_id" class="form-label">Game</label>
                        <select class="form-select" id="game_id" name="game_id" required>
                            <?php foreach ($games as $game): ?>
                                <option value="<?php echo $game['id']; ?>">
                                    <?php echo htmlspecialchars($game['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="tournament_type" class="form-label">Tournament Type</label>
                        <select class="form-select" id="tournament_type" name="tournament_type" required onchange="toggleTeamFields()">
                            <option value="solo">Solo Tournament</option>
                            <option value="team">Team Tournament</option>
                        </select>
                    </div>
                    
                    <div id="team_fields" style="display: none;">
                        <div class="mb-3">
                            <label for="team_size" class="form-label">Team Size (players per team)</label>
                            <input type="number" class="form-control" id="team_size" name="team_size" min="2" max="10">
                        </div>
                        <div class="mb-3">
                            <label for="total_teams" class="form-label">Total Number of Teams</label>
                            <input type="number" class="form-control" id="total_teams" name="total_teams" min="2" max="100">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="max_players" class="form-label">Number of Players</label>
                        <input type="number" class="form-control" id="max_players" name="max_players" min="2" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="start_date" class="form-label">Start Date and Time</label>
                        <input type="datetime-local" class="form-control" id="start_date" name="start_date" required>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="is_paid" name="is_paid" value="1" 
                                   onchange="togglePaidFields()" <?php echo $user_points < 1000 ? 'disabled' : ''; ?>>
                            <label class="form-check-label" for="is_paid">
                                Paid Tournament
                                <?php if ($user_points < 1000): ?>
                                    <small class="text-muted">(Requires 1000 points)</small>
                                <?php endif; ?>
                            </label>
                        </div>
                    </div>

                    <div id="paid_tournament_fields" style="display: none;">
                        <div class="mb-3">
                            <label for="registration_fee" class="form-label">Registration Fee (₹) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="registration_fee" name="registration_fee" min="1" step="1">
                            <small class="text-muted">Minimum registration fee is ₹1</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="prize_pool" class="form-label">Prize Pool (₹) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="prize_pool" name="prize_pool" min="1" step="1">
                            <small class="text-muted">Total prize money for winners</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="upi_id" class="form-label">UPI ID <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="upi_id" name="upi_id" placeholder="yourname@upi">
                            <small class="text-muted">UPI ID where players will send the registration fee</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="room_id" class="form-label">Room ID</label>
                        <input type="text" class="form-control" id="room_id" name="room_id" required>
                        <small class="text-muted">Enter the game room ID where the tournament will be held</small>
                    </div>

                    <div class="mb-3">
                        <label for="room_password" class="form-label">Room Password</label>
                        <input type="text" class="form-control" id="room_password" name="room_password" required>
                        <small class="text-muted">Enter the password for the game room</small>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="requires_approval" name="requires_approval" value="1" checked>
                            <label class="form-check-label" for="requires_approval">
                                Require Admin Approval for Players
                            </label>
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Create Tournament</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function toggleTeamFields() {
    const tournamentType = document.getElementById('tournament_type').value;
    const teamFields = document.getElementById('team_fields');
    const teamSize = document.getElementById('team_size');
    const totalTeams = document.getElementById('total_teams');
    
    if (tournamentType === 'team') {
        teamFields.style.display = 'block';
        teamSize.required = true;
        totalTeams.required = true;
    } else {
        teamFields.style.display = 'none';
        teamSize.required = false;
        totalTeams.required = false;
    }
}

function togglePaidFields() {
    const isPaid = document.getElementById('is_paid').checked;
    const paidFields = document.getElementById('paid_tournament_fields');
    const registrationFee = document.getElementById('registration_fee');
    const prizePool = document.getElementById('prize_pool');
    const upiId = document.getElementById('upi_id');
    
    paidFields.style.display = isPaid ? 'block' : 'none';
    
    // Toggle required attribute
    registrationFee.required = isPaid;
    prizePool.required = isPaid;
    upiId.required = isPaid;
    
    // Clear values if not paid
    if (!isPaid) {
        registrationFee.value = '';
        prizePool.value = '';
        upiId.value = '';
    }
}

// Add validation for prize pool
document.getElementById('registration_fee').addEventListener('input', function() {
    const registrationFee = parseFloat(this.value) || 0;
    const prizePool = document.getElementById('prize_pool');
    prizePool.min = registrationFee; // Prize pool should be at least equal to registration fee
    
    if (parseFloat(prizePool.value) < registrationFee) {
        prizePool.value = registrationFee;
    }
});

// Initialize paid fields on page load
document.addEventListener('DOMContentLoaded', function() {
    togglePaidFields();
});
</script>

<?php require_once 'includes/footer.php'; ?> 