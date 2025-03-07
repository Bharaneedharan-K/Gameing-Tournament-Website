-- Create database
CREATE DATABASE IF NOT EXISTS gaming_tournament;
USE gaming_tournament;

-- Create users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    points INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create games table
CREATE TABLE IF NOT EXISTS games (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create tournaments table
CREATE TABLE IF NOT EXISTS tournaments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    game_id INT NOT NULL,
    creator_id INT NOT NULL,
    max_players INT NOT NULL,
    current_players INT DEFAULT 0,
    start_date DATETIME NOT NULL,
    end_date DATETIME,
    is_paid BOOLEAN DEFAULT FALSE,
    prize_pool DECIMAL(10,2) DEFAULT 0,
    registration_fee DECIMAL(10,2) DEFAULT 0,
    upi_id VARCHAR(100),
    room_id VARCHAR(50),
    room_password VARCHAR(100),
    requires_approval BOOLEAN DEFAULT TRUE,
    status ENUM('active', 'in_progress', 'completed', 'cancelled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    tournament_type ENUM('solo', 'team') NOT NULL DEFAULT 'solo',
    team_size INT DEFAULT NULL,
    total_teams INT DEFAULT NULL,
    FOREIGN KEY (game_id) REFERENCES games(id),
    FOREIGN KEY (creator_id) REFERENCES users(id)
);

-- Create tournament_registrations table
CREATE TABLE IF NOT EXISTS tournament_registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT NOT NULL,
    player_id INT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    transaction_id VARCHAR(100),
    transaction_screenshot VARCHAR(255),
    payment_status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    team_id INT DEFAULT NULL,
    FOREIGN KEY (tournament_id) REFERENCES tournaments(id),
    FOREIGN KEY (player_id) REFERENCES users(id),
    FOREIGN KEY (team_id) REFERENCES teams(team_id),
    UNIQUE KEY unique_tournament_player_reg (tournament_id, player_id)
);

-- Create tournament_players table
CREATE TABLE IF NOT EXISTS tournament_players (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT NOT NULL,
    player_id INT NOT NULL,
    position INT,
    transaction_id VARCHAR(100),
    joined_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tournament_id) REFERENCES tournaments(id),
    FOREIGN KEY (player_id) REFERENCES users(id),
    UNIQUE KEY unique_tournament_player (tournament_id, player_id)
);

-- Create tournament_reports table
CREATE TABLE IF NOT EXISTS tournament_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT NOT NULL,
    reporter_id INT NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tournament_id) REFERENCES tournaments(id),
    FOREIGN KEY (reporter_id) REFERENCES users(id)
);

-- Create teams table for team registrations
CREATE TABLE teams (
    team_id INT PRIMARY KEY AUTO_INCREMENT,
    team_name VARCHAR(100) NOT NULL,
    team_leader_id INT NOT NULL,
    tournament_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (team_leader_id) REFERENCES users(id),
    FOREIGN KEY (tournament_id) REFERENCES tournaments(id),
    UNIQUE KEY unique_team_name_tournament (team_name, tournament_id)
);

-- Create team members table
CREATE TABLE team_members (
    team_id INT,
    user_id INT,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (team_id, user_id),
    FOREIGN KEY (team_id) REFERENCES teams(team_id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Insert initial game data
INSERT INTO games (name, description) VALUES
('Among Us', 'Popular social deduction game'),
('Free Fire', 'Battle royale shooting game'),
('BGMI', 'Battle royale mobile game'),
('Minecraft', 'Sandbox building game');

-- Create trigger to update points after tournament completion
DELIMITER //

CREATE TRIGGER after_tournament_completion
AFTER UPDATE ON tournaments
FOR EACH ROW
BEGIN
    IF NEW.status = 'completed' AND OLD.status != 'completed' THEN
        -- Add points to winners
        UPDATE users u
        INNER JOIN tournament_players tp ON u.id = tp.player_id
        SET u.points = u.points + 100
        WHERE tp.tournament_id = NEW.id
        AND tp.position <= 3;
    END IF;
END //

DELIMITER ;

-- Create trigger to handle tournament reports
DELIMITER //

CREATE TRIGGER after_report_approval
AFTER UPDATE ON tournament_reports
FOR EACH ROW
BEGIN
    IF NEW.status = 'approved' AND OLD.status != 'approved' THEN
        -- Deduct points from reported player
        UPDATE users u
        INNER JOIN tournaments t ON t.creator_id = u.id
        SET u.points = u.points - 75
        WHERE t.id = NEW.tournament_id;
    END IF;
END //

DELIMITER ; 