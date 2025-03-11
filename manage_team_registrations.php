<?php
require_once 'includes/config.php';
require_once 'includes/header.php';

// Check if user is admin
if (!isLoggedIn() || !isAdmin()) {
    header("Location: login.php");
    exit();
}

// Get tournament ID from URL
$tournament_id = isset($_GET['tournament_id']) ? $_GET['tournament_id'] : 0;

// Handle team approval
if (isset($_POST['approve_team'])) {
    $team_id = $_POST['team_id'];
    
    try {
        $pdo->beginTransaction();
        
        // Get team details
        $stmt = $pdo->prepare("
            SELECT t.*, tr.status as registration_status
            FROM teams t
            JOIN tournament_registrations tr ON t.team_id = tr.team_id
            WHERE t.team_id = ?
        ");
        $stmt->execute([$team_id]);
        $team_data = $stmt->fetch();
        
        if (!empty($team_data)) {
            // Update registration status to approved
            $stmt = $pdo->prepare("
                UPDATE tournament_registrations 
                SET status = 'approved'
                WHERE team_id = ? AND tournament_id = ?
            ");
            $stmt->execute([$team_id, $tournament_id]);
            
            // Update current teams count in tournaments table
            $stmt = $pdo->prepare("
                UPDATE tournaments 
                SET current_teams = current_teams + 1
                WHERE id = ?
            ");
            $stmt->execute([$tournament_id]);
            
            $pdo->commit();
            $_SESSION['success'] = "Team has been approved successfully.";
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error approving team: " . $e->getMessage();
    }
}

// Fetch pending team registrations and tournament team limits
$stmt = $pdo->prepare("
    SELECT t.*, tr.status as registration_status,
           u.username as leader_username,
           tour.team_limit,
           tour.current_teams
    FROM teams t
    JOIN tournament_registrations tr ON t.team_id = tr.team_id
    JOIN users u ON t.team_leader_id = u.id
    JOIN tournaments tour ON tr.tournament_id = tour.id
    WHERE tr.tournament_id = ? AND tr.status = 'pending'
    GROUP BY t.team_id
");
$stmt->execute([$tournament_id]);
$pending_teams = $stmt->fetchAll();

// Fetch tournament details
$stmt = $pdo->prepare("
    SELECT t.*, g.name as game_name,
           t.team_limit, t.current_teams
    FROM tournaments t
    JOIN games g ON t.game_id = g.id
    WHERE t.id = ?
");
$stmt->execute([$tournament_id]);
$tournament = $stmt->fetch();
?>

<div class="container mt-4">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4>Manage Team Registrations - <?php echo htmlspecialchars($tournament['name']); ?></h4>
            <div>
                <span class="badge bg-primary">
                    Teams: <?php echo $tournament['current_teams']; ?>/<?php echo $tournament['team_limit']; ?>
                </span>
            </div>
        </div>
        <div class="card-body">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <?php if (empty($pending_teams)): ?>
                <div class="alert alert-info">No pending team registrations.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Team Name</th>
                                <th>Team Leader</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_teams as $team): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($team['team_name']); ?></td>
                                    <td><?php echo htmlspecialchars($team['leader_username']); ?></td>
                                    <td>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="team_id" value="<?php echo $team['team_id']; ?>">
                                            <button type="submit" name="approve_team" class="btn btn-success btn-sm" <?php echo ($tournament['current_teams'] >= $tournament['team_limit']) ? 'disabled' : ''; ?>>
                                                <i class="fas fa-check me-1"></i>Approve Team
                                            </button>
                                        </form>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="team_id" value="<?php echo $team['team_id']; ?>">
                                            <button type="submit" name="reject_team" class="btn btn-danger btn-sm">
                                                <i class="fas fa-times me-1"></i>Reject
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 