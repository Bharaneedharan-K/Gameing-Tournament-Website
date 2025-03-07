<?php
require_once 'includes/config.php';
require_once 'includes/header.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Get user information
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Get user's active tournaments
$stmt = $pdo->prepare("
    SELECT t.*, g.name as game_name 
    FROM tournaments t 
    JOIN tournament_players tp ON t.id = tp.tournament_id 
    JOIN games g ON t.game_id = g.id
    WHERE tp.player_id = ? AND t.status = 'active'
    ORDER BY t.start_date DESC
");
$stmt->execute([$_SESSION['user_id']]);
$active_tournaments = $stmt->fetchAll();

// Get user's tournament history
$stmt = $pdo->prepare("
    SELECT t.*, g.name as game_name, tp.position
    FROM tournaments t 
    JOIN tournament_players tp ON t.id = tp.tournament_id 
    JOIN games g ON t.game_id = g.id
    WHERE tp.player_id = ? AND t.status = 'completed'
    ORDER BY t.end_date DESC
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$recent_tournaments = $stmt->fetchAll();
?>

<div class="row">
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header">
                <h4>Profile Information</h4>
            </div>
            <div class="card-body">
                <h5><?php echo htmlspecialchars($user['username']); ?></h5>
                <p>Points: <?php echo $user['points']; ?></p>
                <p>Email: <?php echo htmlspecialchars($user['email']); ?></p>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header">
                <h4>Active Tournaments</h4>
            </div>
            <div class="card-body">
                <?php if (empty($active_tournaments)): ?>
                    <p>No active tournaments.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Game</th>
                                    <th>Tournament Name</th>
                                    <th>Start Date</th>
                                    <th>Players</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($active_tournaments as $tournament): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($tournament['game_name']); ?></td>
                                        <td><?php echo htmlspecialchars($tournament['name']); ?></td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($tournament['start_date'])); ?></td>
                                        <td><?php echo $tournament['current_players']; ?>/<?php echo $tournament['max_players']; ?></td>
                                        <td>
                                            <a href="view_tournament.php?id=<?php echo $tournament['id']; ?>" class="btn btn-primary btn-sm">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h4>Recent Tournament History</h4>
            </div>
            <div class="card-body">
                <?php if (empty($recent_tournaments)): ?>
                    <p>No tournament history.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Game</th>
                                    <th>Tournament</th>
                                    <th>End Date</th>
                                    <th>Position</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_tournaments as $tournament): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($tournament['game_name']); ?></td>
                                        <td><?php echo htmlspecialchars($tournament['name']); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($tournament['end_date'])); ?></td>
                                        <td><?php echo $tournament['position']; ?></td>
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