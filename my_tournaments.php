<?php
require_once 'includes/config.php';
require_once 'includes/header.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Get all games
$stmt = $pdo->prepare("SELECT * FROM games ORDER BY name");
$stmt->execute();
$games = $stmt->fetchAll();

// Get selected game filter
$selected_game = isset($_GET['game']) ? $_GET['game'] : 'all';

// Build query for user's tournaments
$query = "
    SELECT t.*, g.name as game_name, 
           (SELECT COUNT(*) FROM tournament_players WHERE tournament_id = t.id) as current_players,
           (SELECT COUNT(*) FROM tournament_registrations WHERE tournament_id = t.id AND status = 'pending') as pending_registrations
    FROM tournaments t 
    JOIN games g ON t.game_id = g.id 
    WHERE t.creator_id = ?";

if ($selected_game !== 'all') {
    $query .= " AND g.id = :game_id";
}
$query .= " ORDER BY t.start_date ASC";

$stmt = $pdo->prepare($query);
if ($selected_game !== 'all') {
    $stmt->bindParam(':game_id', $selected_game);
}
$stmt->execute([$_SESSION['user_id']]);
$my_tournaments = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0">My Tournaments</h4>
                <div>
                    <a href="create_tournament.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Create New Tournament
                    </a>
                    <a href="tournaments.php" class="btn btn-secondary ms-2">
                        <i class="fas fa-list me-2"></i>View All Tournaments
                    </a>
                </div>
            </div>
            <div class="card-body">
                <!-- Game Filter -->
                <div class="mb-4">
                    <form method="GET" class="d-inline">
                        <select name="game" class="form-select d-inline-block w-auto" onchange="this.form.submit()">
                            <option value="all">All Games</option>
                            <?php foreach ($games as $game): ?>
                                <option value="<?php echo $game['id']; ?>" <?php echo $selected_game == $game['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($game['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>

                <?php if (empty($my_tournaments)): ?>
                    <p>You haven't created any tournaments yet.</p>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($my_tournaments as $tournament): ?>
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
                                                    <p class="h4 mb-1">₹<?php echo number_format($tournament['registration_fee'], 2); ?></p>
                                                    <small class="text-muted">Prize Pool: ₹<?php echo number_format($tournament['prize_pool'], 2); ?></small>
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

                                        <?php if ($tournament['pending_registrations'] > 0): ?>
                                            <div class="alert alert-warning">
                                                <i class="fas fa-clock me-2"></i>
                                                <?php echo $tournament['pending_registrations']; ?> pending registration(s)
                                            </div>
                                        <?php endif; ?>

                                        <div class="d-flex justify-content-between align-items-center mt-3">
                                            <a href="manage_registrations.php?id=<?php echo $tournament['id']; ?>" class="btn btn-info btn-sm">
                                                <i class="fas fa-users-cog me-2"></i>Manage Registrations
                                            </a>
                                            <span class="badge bg-<?php echo $tournament['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                <?php echo ucfirst($tournament['status']); ?>
                                            </span>
                                        </div>
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

<?php require_once 'includes/footer.php'; ?> 