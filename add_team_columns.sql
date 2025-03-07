-- Select the database first
USE gaming_tournament;

-- Add team-related columns to tournaments table
ALTER TABLE tournaments 
ADD COLUMN tournament_type ENUM('solo', 'team') NOT NULL DEFAULT 'solo' AFTER created_at,
ADD COLUMN team_size INT DEFAULT NULL AFTER tournament_type,
ADD COLUMN total_teams INT DEFAULT NULL AFTER team_size;

-- Create teams table if not exists
CREATE TABLE IF NOT EXISTS teams (
    team_id INT PRIMARY KEY AUTO_INCREMENT,
    team_name VARCHAR(100) NOT NULL,
    team_leader_id INT NOT NULL,
    tournament_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (team_leader_id) REFERENCES users(id),
    FOREIGN KEY (tournament_id) REFERENCES tournaments(id),
    UNIQUE KEY unique_team_name_tournament (team_name, tournament_id)
);

-- Create team_members table if not exists
CREATE TABLE IF NOT EXISTS team_members (
    team_id INT,
    user_id INT,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (team_id, user_id),
    FOREIGN KEY (team_id) REFERENCES teams(team_id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Add team_id column to tournament_registrations table
ALTER TABLE tournament_registrations
ADD COLUMN team_id INT DEFAULT NULL AFTER registration_date,
ADD FOREIGN KEY (team_id) REFERENCES teams(team_id); 