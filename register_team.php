<?php
require_once 'includes/config.php';
require_once 'includes/header.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Get tournament ID from URL
$tournament_id = isset($_GET['tournament_id']) ? $_GET['tournament_id'] : 0;

// Fetch tournament details
$stmt = $pdo->prepare("
    SELECT t.*, g.name as game_name 
    FROM tournaments t 
    JOIN games g ON t.game_id = g.id 
    WHERE t.id = ? AND t.status = 'active' AND t.tournament_type = 'team'
");
$stmt->execute([$tournament_id]);
$tournament = $stmt->fetch();

if (!$tournament) {
    $_SESSION['error'] = "Tournament not found or not a team tournament.";
    header("Location: tournaments.php");
    exit();
}

// Check if tournament is full
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM teams 
    WHERE tournament_id = ?
");
$stmt->execute([$tournament_id]);
$current_teams = $stmt->fetchColumn();

if ($current_teams >= $tournament['total_teams']) {
    $_SESSION['error'] = "This tournament is already full.";
    header("Location: tournaments.php");
    exit();
}

// Process team registration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $team_name = trim($_POST['team_name']);
    $player_usernames = isset($_POST['player_ids']) ? array_filter($_POST['player_ids']) : [];
    $errors = [];

    // Validate team name
    if (empty($team_name)) {
        $errors[] = "Team name is required.";
    }

    // Check if team name already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM teams WHERE team_name = ? AND tournament_id = ?");
    $stmt->execute([$team_name, $tournament_id]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = "Team name already exists in this tournament.";
    }

    // Validate team size
    if (count($player_usernames) + 1 != $tournament['team_size']) {
        $errors[] = "Team must have exactly " . $tournament['team_size'] . " players (including you).";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // First, create temporary users for team members
            $temp_user_ids = [];
            foreach ($player_usernames as $username) {
                if (!empty($username)) {
                    // Create a temporary user with a unique email
                    $temp_email = 'temp_' . uniqid() . '@temporary.com';
                    $temp_password = password_hash(uniqid(), PASSWORD_DEFAULT);
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO users (username, email, password)
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$username, $temp_email, $temp_password]);
                    $temp_user_ids[] = $pdo->lastInsertId();
                }
            }
            
            // Create team
            $stmt = $pdo->prepare("
                INSERT INTO teams (team_name, tournament_id, team_leader_id)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$team_name, $tournament_id, $_SESSION['user_id']]);
            $team_id = $pdo->lastInsertId();
            
            // Add team leader to team_members
            $stmt = $pdo->prepare("
                INSERT INTO team_members (team_id, user_id)
                VALUES (?, ?)
            ");
            $stmt->execute([$team_id, $_SESSION['user_id']]);
            
            // Add other members to team_members
            foreach ($temp_user_ids as $temp_user_id) {
                $stmt->execute([$team_id, $temp_user_id]);
            }
            
            // Create a single tournament registration for the entire team
            $stmt = $pdo->prepare("
                INSERT INTO tournament_registrations (tournament_id, player_id, team_id, status)
                VALUES (?, ?, ?, 'pending')
            ");
            $stmt->execute([$tournament_id, $_SESSION['user_id'], $team_id]);
            
            $pdo->commit();
            $_SESSION['success'] = "Team registration submitted successfully! Waiting for admin approval.";
            header("Location: tournaments.php");
            exit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h4>Register Team for <?php echo htmlspecialchars($tournament['name']); ?></h4>
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
                    
                    <div class="tournament-info mb-4">
                        <h5>Tournament Details</h5>
                        <p><strong>Game:</strong> <?php echo htmlspecialchars($tournament['game_name']); ?></p>
                        <p><strong>Team Size:</strong> <?php echo $tournament['team_size']; ?> players</p>
                        <p><strong>Total Teams:</strong> <?php echo $tournament['total_teams']; ?></p>
                        <p><strong>Start Date:</strong> <?php echo date('Y-m-d H:i', strtotime($tournament['start_date'])); ?></p>
                    </div>

                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label">Team Name</label>
                            <input type="text" name="team_name" class="form-control" required 
                                   value="<?php echo isset($_POST['team_name']) ? htmlspecialchars($_POST['team_name']) : ''; ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Team Leader (You)</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($_SESSION['username']); ?>" disabled>
                        </div>

                        <h5 class="mb-3">Other Team Members</h5>
                        <?php for ($i = 0; $i < $tournament['team_size'] - 1; $i++): ?>
                            <div class="mb-3">
                                <label class="form-label">Player <?php echo $i + 2; ?></label>
                                <input type="text" name="player_ids[]" class="form-control" required
                                       value="<?php echo isset($_POST['player_ids'][$i]) ? htmlspecialchars($_POST['player_ids'][$i]) : ''; ?>"
                                       placeholder="Enter player name or username">
                                <small class="text-muted">Enter player name/username</small>
                            </div>
                        <?php endfor; ?>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-users me-2"></i>Register Team
                            </button>
                            <a href="tournaments.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Back to Tournaments
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 