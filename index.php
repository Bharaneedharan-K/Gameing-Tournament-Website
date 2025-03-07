<?php
require_once 'includes/config.php';
require_once 'includes/header.php';

// Get featured tournaments
$stmt = $pdo->prepare("
    SELECT t.*, g.name as game_name, 
           (SELECT COUNT(*) FROM tournament_players WHERE tournament_id = t.id) as current_players 
    FROM tournaments t 
    JOIN games g ON t.game_id = g.id 
    WHERE t.status = 'active' 
    ORDER BY t.created_at DESC 
    LIMIT 6
");
$stmt->execute();
$featured_tournaments = $stmt->fetchAll();

// Get all games
$stmt = $pdo->prepare("SELECT * FROM games ORDER BY name");
$stmt->execute();
$games = $stmt->fetchAll();

// Game banner images and updated descriptions
$game_images = [
    'Among Us' => [
        'image' => 'https://cdn.cloudflare.steamstatic.com/steam/apps/945360/header.jpg',
        'description' => 'Join the ultimate social deduction game where teamwork and betrayal collide!'
    ],
    'Free Fire' => [
        'image' => 'https://wallpaperaccess.com/full/1429095.jpg',
        'description' => 'Experience intense battle royale action with fast-paced 10-minute matches!'
    ],
    'BGMI' => [
        'image' => 'https://wallpaperaccess.com/full/4475074.jpg',
        'description' => 'Dive into epic 100-player battlegrounds with stunning graphics and intense combat!'
    ],
    'Minecraft' => [
        'image' => 'https://wallpaperaccess.com/full/171177.jpg',
        'description' => 'Build, explore, and compete in the world\'s most creative gaming universe!'
    ]
];
?>

<!-- Hero Section -->
<div class="hero-section text-center py-5 mb-5">
    <div class="hero-content">
        <h1 class="display-4 text-white mb-4">Welcome to EpicClash</h1>
        <p class="lead text-white mb-4">Join competitive gaming tournaments and win amazing prizes!</p>
        <?php if(!isset($_SESSION['user_id'])): ?>
            <div class="cta-buttons">
                <a href="register.php" class="btn btn-primary btn-lg me-3">
                    <i class="fas fa-user-plus me-2"></i>Join Now
                </a>
                <a href="login.php" class="btn btn-outline-light btn-lg">
                    <i class="fas fa-sign-in-alt me-2"></i>Login
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Featured Tournaments Section -->
<section class="mb-5">
    <h2 class="section-title text-center mb-4">
        <i class="fas fa-star text-warning me-2"></i>Featured Tournaments
    </h2>
    <div class="row">
        <?php foreach ($featured_tournaments as $tournament): ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="tournament-card">
                    <div class="card h-100">
                        <div class="card-header position-relative">
                            <img src="<?php echo $game_images[$tournament['game_name']]['image'] ?? 'https://via.placeholder.com/400x200'; ?>" 
                                 class="card-img-top tournament-image" alt="<?php echo htmlspecialchars($tournament['game_name']); ?>">
                            <span class="badge bg-primary position-absolute top-0 end-0 m-2">
                                <?php echo htmlspecialchars($tournament['game_name']); ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($tournament['name']); ?></h5>
                            <div class="tournament-info">
                                <p class="mb-2">
                                    <i class="fas fa-users me-2"></i>
                                    <?php echo $tournament['current_players']; ?>/<?php echo $tournament['max_players']; ?> Players
                                </p>
                                <p class="mb-2">
                                    <i class="fas fa-calendar me-2"></i>
                                    <?php echo date('M d, Y H:i', strtotime($tournament['start_date'])); ?>
                                </p>
                                <p class="mb-2">
                                    <i class="fas fa-coins me-2"></i>
                                    <?php echo $tournament['is_paid'] ? 'Paid Tournament' : 'Free Tournament'; ?>
                                </p>
                                <?php if ($tournament['is_paid']): ?>
                                    <p class="prize-pool">
                                        <i class="fas fa-trophy me-2"></i>
                                        Prize Pool: $<?php echo number_format($tournament['prize_pool'], 2); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <a href="<?php echo isset($_SESSION['user_id']) ? 'tournaments.php' : 'login.php'; ?>" 
                               class="btn btn-primary w-100 mt-3">
                                <?php echo isset($_SESSION['user_id']) ? 'Join Tournament' : 'Login to Join'; ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- Game Categories Section -->
<section class="mb-5">
    <h2 class="section-title text-center mb-4">
        <i class="fas fa-gamepad text-primary me-2"></i>Game Categories
    </h2>
    <div class="row">
        <?php foreach ($games as $game): ?>
            <div class="col-md-6 col-lg-3 mb-4">
                <div class="game-category-card">
                    <div class="card h-100">
                        <img src="<?php echo $game_images[$game['name']]['image'] ?? 'https://via.placeholder.com/400x200'; ?>" 
                             class="card-img-top game-image" alt="<?php echo htmlspecialchars($game['name']); ?>">
                        <div class="card-body text-center">
                            <h5 class="card-title"><?php echo htmlspecialchars($game['name']); ?></h5>
                            <p class="card-text"><?php echo $game_images[$game['name']]['description'] ?? htmlspecialchars($game['description']); ?></p>
                            <a href="tournaments.php?game=<?php echo $game['id']; ?>" class="btn btn-outline-primary">
                                View Tournaments
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- Features Section -->
<section class="features-section py-5">
    <div class="container">
        <h2 class="section-title text-center mb-5">
            <i class="fas fa-star text-warning me-2"></i>Why Choose EpicClash?
        </h2>
        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="feature-card text-center">
                    <i class="fas fa-trophy feature-icon mb-3"></i>
                    <h4>Competitive Gaming</h4>
                    <p>Participate in tournaments across multiple popular games and compete with players worldwide.</p>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="feature-card text-center">
                    <i class="fas fa-coins feature-icon mb-3"></i>
                    <h4>Win Prizes</h4>
                    <p>Compete in paid tournaments with real cash prizes and earn points for participating.</p>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="feature-card text-center">
                    <i class="fas fa-users feature-icon mb-3"></i>
                    <h4>Growing Community</h4>
                    <p>Join our growing community of gamers and make new friends while competing.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?> 