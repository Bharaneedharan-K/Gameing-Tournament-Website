<?php
require_once 'config.php';
require_once 'header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$tournament_id = isset($_GET['tournament_id']) ? $_GET['tournament_id'] : 0;

// Fetch tournament details
$stmt = $pdo->prepare("SELECT * FROM tournaments WHERE id = ? AND tournament_type = 'team'");
$stmt->execute([$tournament_id]);
$tournament = $stmt->fetch();

if (!$tournament) {
    $_SESSION['error'] = "Invalid tournament or not a team tournament.";
    header('Location: tournaments.php');
    exit();
}

// Handle team registration
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $team_name = trim($_POST['team_name']);
    $team_members = isset($_POST['team_members']) ? $_POST['team_members'] : [];
    
    $errors = [];
    
    // Validate team name
    if (empty($team_name)) {
        $errors[] = "Team name is required.";
    }
    
    // Validate team size
    if (count($team_members) + 1 != $tournament['team_size']) { // +1 for team leader
        $errors[] = "Team must have exactly " . $tournament['team_size'] . " players.";
    }
    
    // Check if team name is already taken for this tournament
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM teams WHERE team_name = ? AND tournament_id = ?");
    $stmt->execute([$team_name, $tournament_id]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = "Team name already exists in this tournament.";
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Create team
            $stmt = $pdo->prepare("INSERT INTO teams (team_name, team_leader_id, tournament_id) VALUES (?, ?, ?)");
            $stmt->execute([$team_name, $_SESSION['user_id'], $tournament_id]);
            $team_id = $pdo->lastInsertId();
            
            // Add team leader as member
            $stmt = $pdo->prepare("INSERT INTO team_members (team_id, user_id) VALUES (?, ?)");
            $stmt->execute([$team_id, $_SESSION['user_id']]);
            
            // Add team members
            foreach ($team_members as $member_id) {
                $stmt->execute([$team_id, $member_id]);
            }
            
            // Create tournament registration for the team
            $stmt = $pdo->prepare("INSERT INTO tournament_registrations (tournament_id, player_id, team_id) VALUES (?, ?, ?)");
            $stmt->execute([$tournament_id, $_SESSION['user_id'], $team_id]);
            
            $pdo->commit();
            $_SESSION['success'] = "Team registered successfully!";
            header('Location: view_tournament.php?id=' . $tournament_id);
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Error registering team. Please try again.";
        }
    }
}

// Fetch available players for team selection
$stmt = $pdo->prepare("
    SELECT id, username 
    FROM users 
    WHERE id != ? 
    AND id NOT IN (
        SELECT user_id 
        FROM team_members 
        JOIN teams ON team_members.team_id = teams.team_id 
        WHERE teams.tournament_id = ?
    )
");
$stmt->execute([$_SESSION['user_id'], $tournament_id]);
$available_players = $stmt->fetchAll();
?>

<div class="container mt-4">
    <h2>Register Team for <?= htmlspecialchars($tournament['title']) ?></h2>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-body">
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="team_name" class="form-label">Team Name</label>
                    <input type="text" class="form-control" id="team_name" name="team_name" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Team Leader (You)</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($_SESSION['username']) ?>" disabled>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Select Team Members (<?= $tournament['team_size'] - 1 ?> players needed)</label>
                    <select class="form-select" name="team_members[]" multiple size="5" required>
                        <?php foreach ($available_players as $player): ?>
                            <option value="<?= $player['id'] ?>"><?= htmlspecialchars($player['username']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Hold Ctrl/Cmd to select multiple players</small>
                </div>
                
                <?php if ($tournament['entry_fee'] > 0): ?>
                    <div class="alert alert-info">
                        <h5>Payment Required</h5>
                        <p>Entry Fee: ₹<?= number_format($tournament['entry_fee'], 2) ?></p>
                        <p>UPI ID: <?= htmlspecialchars($tournament['upi_id']) ?></p>
                        
                        <div class="mb-3">
                            <label for="transaction_id" class="form-label">Transaction ID</label>
                            <input type="text" class="form-control" id="transaction_id" name="transaction_id" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="payment_screenshot" class="form-label">Payment Screenshot</label>
                            <input type="file" class="form-control" id="payment_screenshot" name="payment_screenshot" accept="image/*" required>
                        </div>
                    </div>
                <?php endif; ?>
                
                <button type="submit" class="btn btn-primary">Register Team</button>
            </form>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?> 