-- Structure de la base de donnees pour le projet 2 : Patients et rendez-vous
CREATE DATABASE IF NOT EXISTS `gestion_clinique` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `gestion_clinique`;

CREATE TABLE IF NOT EXISTS `patients` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nom` VARCHAR(100) NOT NULL,
    `prenom` VARCHAR(100) NOT NULL,
    `sexe` VARCHAR(10) NOT NULL,
    `date_naissance` DATE NOT NULL,
    `contact` VARCHAR(15) NOT NULL,
    `groupe_sanguin` VARCHAR(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `medecins` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nom_complet` VARCHAR(150) NOT NULL,
    `specialite` VARCHAR(120) NOT NULL,
    `telephone` VARCHAR(20) NULL,
    `email` VARCHAR(120) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rendezvous` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `patient_id` INT NOT NULL,
    `medecin_id` INT NULL,
    `date_rendezvous` DATE NOT NULL,
    `heure_rendezvous` TIME NOT NULL,
    `medecin` VARCHAR(120) NOT NULL,
    `motif` VARCHAR(255) NOT NULL,
    `statut` VARCHAR(20) NOT NULL DEFAULT 'programme',
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_rendezvous_patient_date` (`patient_id`, `date_rendezvous`, `heure_rendezvous`),
    INDEX `idx_rendezvous_medecin` (`medecin_id`),
    CONSTRAINT `fk_rendezvous_patient`
        FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_rendezvous_medecin`
        FOREIGN KEY (`medecin_id`) REFERENCES `medecins`(`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
