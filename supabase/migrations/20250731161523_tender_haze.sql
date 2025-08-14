-- Base de données pour l'application de gestion de pharmacie
-- Création de la base de données
CREATE DATABASE IF NOT EXISTS pharma_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pharma_db;

-- Table des utilisateurs
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('directeur', 'caissier') NOT NULL DEFAULT 'caissier',
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    telephone VARCHAR(20),
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_modification TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    actif BOOLEAN DEFAULT TRUE
);

-- Table des fournisseurs
CREATE TABLE IF NOT EXISTS fournisseurs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(150) NOT NULL,
    email VARCHAR(100),
    telephone VARCHAR(20),
    adresse TEXT,
    ville VARCHAR(100),
    code_postal VARCHAR(10),
    contact_principal VARCHAR(100),
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_modification TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    actif BOOLEAN DEFAULT TRUE
);

-- Table des catégories de médicaments
CREATE TABLE IF NOT EXISTS categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(100) NOT NULL,
    description TEXT,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table des médicaments
CREATE TABLE IF NOT EXISTS medicaments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(200) NOT NULL,
    code_barre VARCHAR(50) UNIQUE,
    description TEXT,
    prix_achat DECIMAL(10,2) NOT NULL,
    prix_vente DECIMAL(10,2) NOT NULL,
    stock_actuel INT DEFAULT 0,
    stock_minimum INT DEFAULT 10,
    unite VARCHAR(20) DEFAULT 'unité',
    date_expiration DATE,
    categorie_id INT,
    fournisseur_id INT,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_modification TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    actif BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (categorie_id) REFERENCES categories(id),
    FOREIGN KEY (fournisseur_id) REFERENCES fournisseurs(id)
);

-- Table des clients
CREATE TABLE IF NOT EXISTS clients (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    telephone VARCHAR(20),
    adresse TEXT,
    date_naissance DATE,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_modification TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    actif BOOLEAN DEFAULT TRUE
);

-- Table des ventes
CREATE TABLE IF NOT EXISTS ventes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    numero_facture VARCHAR(50) UNIQUE NOT NULL,
    client_id INT NULL,
    user_id INT NOT NULL,
    montant_total DECIMAL(10,2) NOT NULL,
    tva DECIMAL(10,2) DEFAULT 0,
    remise DECIMAL(10,2) DEFAULT 0,
    statut ENUM('en_cours', 'termine', 'annule') DEFAULT 'termine',
    mode_paiement ENUM('especes', 'carte', 'cheque', 'virement') DEFAULT 'especes',
    date_vente TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    FOREIGN KEY (client_id) REFERENCES clients(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Table des détails de vente
CREATE TABLE IF NOT EXISTS vente_details (
    id INT PRIMARY KEY AUTO_INCREMENT,
    vente_id INT NOT NULL,
    medicament_id INT NOT NULL,
    quantite INT NOT NULL,
    prix_unitaire DECIMAL(10,2) NOT NULL,
    sous_total DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (vente_id) REFERENCES ventes(id) ON DELETE CASCADE,
    FOREIGN KEY (medicament_id) REFERENCES medicaments(id)
);

-- Table des mouvements de stock
CREATE TABLE IF NOT EXISTS stock_mouvements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    medicament_id INT NOT NULL,
    type_mouvement ENUM('entree', 'sortie', 'ajustement') NOT NULL,
    quantite INT NOT NULL,
    quantite_avant INT NOT NULL,
    quantite_apres INT NOT NULL,
    user_id INT NOT NULL,
    motif VARCHAR(200),
    numero_lot VARCHAR(50),
    date_mouvement TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (medicament_id) REFERENCES medicaments(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Insertion des données de test
INSERT INTO categories (nom, description) VALUES 
('Antibiotiques', 'Médicaments contre les infections bactériennes'),
('Antalgiques', 'Médicaments contre la douleur'),
('Vitamines', 'Suppléments nutritionnels'),
('Dermatologie', 'Soins de la peau'),
('Cardiologie', 'Médicaments cardiovasculaires');

INSERT INTO fournisseurs (nom, email, telephone, adresse, ville, contact_principal) VALUES 
('Pharma Distribution', 'contact@pharmadist.com', '0123456789', '123 Rue de la Santé', 'Paris', 'Jean Dupont'),
('MediSupply', 'info@medisupply.com', '0987654321', '456 Avenue des Médicaments', 'Lyon', 'Marie Martin'),
('BioPharm', 'vente@biopharm.fr', '0147258369', '789 Boulevard de la Pharmacie', 'Marseille', 'Pierre Durand');

INSERT INTO users (username, email, password, role, nom, prenom, telephone) VALUES 
('admin', 'admin@pharmacie.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'directeur', 'Directeur', 'Principal', '0123456789'),
('caissier1', 'caissier@pharmacie.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'caissier', 'Caissier', 'Premier', '0987654321');

INSERT INTO medicaments (nom, code_barre, prix_achat, prix_vente, stock_actuel, stock_minimum, categorie_id, fournisseur_id) VALUES 
('Paracétamol 500mg', '1234567890123', 2.50, 4.90, 150, 20, 2, 1),
('Amoxicilline 500mg', '2345678901234', 8.20, 15.50, 80, 15, 1, 1),
('Vitamine C 1000mg', '3456789012345', 3.80, 7.90, 200, 25, 3, 2),
('Crème hydratante', '4567890123456', 5.50, 12.90, 45, 10, 4, 3),
('Aspirine 100mg', '5678901234567', 1.80, 3.50, 120, 30, 2, 1);

-- Vue pour les alertes de stock
CREATE VIEW stock_alerts AS
SELECT 
    m.id,
    m.nom,
    m.stock_actuel,
    m.stock_minimum,
    f.nom as fournisseur,
    c.nom as categorie
FROM medicaments m
LEFT JOIN fournisseurs f ON m.fournisseur_id = f.id
LEFT JOIN categories c ON m.categorie_id = c.id
WHERE m.stock_actuel <= m.stock_minimum AND m.actif = TRUE;

-- Index pour optimiser les performances
CREATE INDEX idx_medicaments_stock ON medicaments(stock_actuel, stock_minimum);
CREATE INDEX idx_ventes_date ON ventes(date_vente);
CREATE INDEX idx_stock_mouvements_date ON stock_mouvements(date_mouvement);
CREATE INDEX idx_medicaments_nom ON medicaments(nom);