-- Création de la base
CREATE DATABASE IF NOT EXISTS fai_gestion CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE fai_gestion;

-- Utilisateurs du système (admin, employés)
CREATE TABLE IF NOT EXISTS utilisateurFai (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    mot_de_passe VARCHAR(255) NOT NULL,
    role ENUM('admin', 'employe') DEFAULT 'employe',
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Clients (particuliers / entreprises)
CREATE TABLE IF NOT EXISTS clientsFai (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(150) NOT NULL,
    type_client ENUM('particulier','entreprise') DEFAULT 'particulier',
    contact VARCHAR(100),
    email VARCHAR(150),
    adresse TEXT,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


-- Fournisseurs (fournisseurs de matériel ou SIM)
CREATE TABLE IF NOT EXISTS fournisseurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(150) NOT NULL,
    contact VARCHAR(100),
    email VARCHAR(150),
    adresse TEXT,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Produits (matériels et services)
CREATE TABLE IF NOT EXISTS produits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(150) NOT NULL,
    type ENUM('equipement','sim','service') NOT NULL,
    description TEXT,
    prix_achat INT DEFAULT 0,
    prix_vente INT NOT NULL,
    quantite INT DEFAULT 0,
    seuil_minimum INT DEFAULT 5,
    fournisseur_id INT,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (fournisseur_id) REFERENCES fournisseurs(id) ON DELETE SET NULL
);

-- Mouvements de stock
CREATE TABLE IF NOT EXISTS mouvements_stock (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produit_id INT NOT NULL,
    type ENUM('entree','sortie') NOT NULL,
    quantite INT NOT NULL,
    utilisateur_id INT,
    date_mouvement TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    commentaire TEXT,
    FOREIGN KEY (produit_id) REFERENCES produits(id) ON DELETE CASCADE,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurFai(id) ON DELETE SET NULL
);

-- Abonnements (liés aux clients)
CREATE TABLE IF NOT EXISTS abonnements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    produit_id INT NOT NULL,
    date_debut DATE NOT NULL,
    date_fin DATE,
    statut ENUM('actif','suspendu','resilie') DEFAULT 'actif',
    FOREIGN KEY (client_id) REFERENCES clientsFai(id) ON DELETE CASCADE,
    FOREIGN KEY (produit_id) REFERENCES produits(id) ON DELETE CASCADE
);

-- Ventes ponctuelles
CREATE TABLE IF NOT EXISTS ventes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT,
    utilisateur_id INT,
    numero_facture VARCHAR(50) UNIQUE,
    date_vente TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    total INT NOT NULL,
    statut_paiement ENUM('non_paye','paye','partiel') DEFAULT 'non_paye',
    FOREIGN KEY (client_id) REFERENCES clientsFai(id) ON DELETE SET NULL,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurFai(id) ON DELETE SET NULL
);

-- Détails des ventes
CREATE TABLE IF NOT EXISTS ventes_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vente_id INT NOT NULL,
    produit_id INT NOT NULL,
    quantite INT NOT NULL,
    prix_unitaire INT NOT NULL,
    FOREIGN KEY (vente_id) REFERENCES ventes(id) ON DELETE CASCADE,
    FOREIGN KEY (produit_id) REFERENCES produits(id) ON DELETE CASCADE
);

-- Paiements
CREATE TABLE IF NOT EXISTS paiements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    montant INT NOT NULL,
    methode ENUM('cash','mobile_money','carte_bancaire','virement') DEFAULT 'cash',
    reference VARCHAR(100),
    date_paiement TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    utilisateur_id INT,
    FOREIGN KEY (client_id) REFERENCES clientsFai(id) ON DELETE CASCADE,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurFai(id) ON DELETE SET NULL
);

-- Création d’un compte admin par défaut (MD5, compatible login actuel)
INSERT INTO utilisateurFai (nom, email, mot_de_passe, role)
SELECT 'Admin', 'admin@gmail.com', MD5('admin'), 'admin'
WHERE NOT EXISTS (SELECT 1 FROM utilisateurFai WHERE email='admin@gmail.com');
