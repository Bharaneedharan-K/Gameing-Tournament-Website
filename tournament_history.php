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
    SELECT 
        t.*, 
        g.name as game_name,
        (
            SELECT GROUP_CONCAT(CONCAT(u.username, ':', tp.position) ORDER BY tp.position ASC SEPARATOR '|')
            FROM tournament_players tp
            JOIN users u ON tp.player_id = u.id
            WHERE tp.tournament_id = t.id AND tp.position <= 3
        ) as winners
    FROM tournaments t 
    JOIN games g ON t.game_id = g.id 
    WHERE t.status = 'completed'";

if ($selected_game !== 'all') {
    $query .= " AND g.id = :game_id";
}
$query .= " ORDER BY t.end_date DESC";

$stmt = $pdo->prepare($query);
if ($selected_game !== 'all') {
    $stmt->bindParam(':game_id', $selected_game);
}
$stmt->execute();
$tournaments = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h4 class="float-start">Tournament History</h4>
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
                <?php if (empty($tournaments)): ?>
                    <p>No tournament history available.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Tournament Name</th>
                                    <th>Game</th>
                                    <th>End Date</th>
                                    <th>Type</th>
                                    <th>Winners</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tournaments as $tournament): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($tournament['name']); ?></td>
                                        <td><?php echo htmlspecialchars($tournament['game_name']); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($tournament['end_date'])); ?></td>
                                        <td>
                                            <?php if ($tournament['is_paid']): ?>
                                                <span class="badge bg-success">Paid</span>
                                                <br>
                                                <small>Prize: $<?php echo number_format($tournament['prize_pool'], 2); ?></small>
                                            <?php else: ?>
                                                <span class="badge bg-info">Free</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            if ($tournament['winners']) {
                                                $winners = explode('|', $tournament['winners']);
                                                echo '<ol class="mb-0">';
                                                foreach ($winners as $winner) {
                                                    list($username, $position) = explode(':', $winner);
                                                    echo '<li>' . htmlspecialchars($username);
                                                    if ($tournament['is_paid']) {
                                                        $prize = 0;
                                                        switch ($position) {
                                                            case 1:
                                                                $prize = $tournament['prize_pool'] * 0.5; // 50% for first place
                                                                break;
                                                            case 2:
                                                                $prize = $tournament['prize_pool'] * 0.3; // 30% for second place
                                                                break;
                                                            case 3:
                                                                $prize = $tournament['prize_pool'] * 0.2; // 20% for third place
                                                                break;
                                                        }
                                                        if ($prize > 0) {
                                                            echo ' ($' . number_format($prize, 2) . ')';
                                                        }
                                                    }
                                                    echo '</li>';
                                                }
                                                echo '</ol>';
                                            } else {
                                                echo 'No winners recorded';
                                            }
                                            ?>
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
</div>

<?php require_once 'includes/footer.php'; ?> 