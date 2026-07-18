<?php
// Fichier de configuration de la base de donnees pour les patients
$host = 'localhost';
$dbname = 'gestion_clinique';
$username = 'root';
$password = '';

function schemaColumnExists(PDO $pdo, string $database, string $table, string $column): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = :database
          AND TABLE_NAME = :table_name
          AND COLUMN_NAME = :column_name
    ");
    $stmt->execute([
        'database' => $database,
        'table_name' => $table,
        'column_name' => $column,
    ]);

    return (int) $stmt->fetchColumn() > 0;
}

function schemaIndexExists(PDO $pdo, string $database, string $table, string $index): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = :database
          AND TABLE_NAME = :table_name
          AND INDEX_NAME = :index_name
    ");
    $stmt->execute([
        'database' => $database,
        'table_name' => $table,
        'index_name' => $index,
    ]);

    return (int) $stmt->fetchColumn() > 0;
}

function schemaForeignKeyExists(PDO $pdo, string $database, string $table, string $constraint): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA = :database
          AND TABLE_NAME = :table_name
          AND CONSTRAINT_NAME = :constraint_name
          AND CONSTRAINT_TYPE = 'FOREIGN KEY'
    ");
    $stmt->execute([
        'database' => $database,
        'table_name' => $table,
        'constraint_name' => $constraint,
    ]);

    return (int) $stmt->fetchColumn() > 0;
}

try {
    // Connexion au serveur MySQL sans selectionner la base au depart
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Creation de la base si elle n'existe pas
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    // Selection de la base
    $pdo->exec("USE `$dbname`");

    // Table des patients
    $sql = "CREATE TABLE IF NOT EXISTS patients (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nom VARCHAR(100) NOT NULL,
        prenom VARCHAR(100) NOT NULL,
        sexe VARCHAR(10) NOT NULL,
        date_naissance DATE NOT NULL,
        contact VARCHAR(15) NOT NULL,
        groupe_sanguin VARCHAR(10) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $pdo->exec($sql);

    // Table des docteurs
    $sql = "CREATE TABLE IF NOT EXISTS medecins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nom_complet VARCHAR(150) NOT NULL,
        specialite VARCHAR(120) NOT NULL,
        telephone VARCHAR(20) NULL,
        email VARCHAR(120) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $pdo->exec($sql);

    // Table des rendez-vous patients
    $sql = "CREATE TABLE IF NOT EXISTS rendezvous (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        medecin_id INT NULL,
        date_rendezvous DATE NOT NULL,
        heure_rendezvous TIME NOT NULL,
        medecin VARCHAR(120) NOT NULL,
        motif VARCHAR(255) NOT NULL,
        statut VARCHAR(20) NOT NULL DEFAULT 'programme',
        notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_rendezvous_patient_date (patient_id, date_rendezvous, heure_rendezvous),
        CONSTRAINT fk_rendezvous_patient
            FOREIGN KEY (patient_id) REFERENCES patients(id)
            ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $pdo->exec($sql);

    if (!schemaColumnExists($pdo, $dbname, 'rendezvous', 'medecin_id')) {
        $pdo->exec("ALTER TABLE rendezvous ADD COLUMN medecin_id INT NULL AFTER patient_id");
    }

    if (!schemaIndexExists($pdo, $dbname, 'rendezvous', 'idx_rendezvous_medecin')) {
        $pdo->exec("ALTER TABLE rendezvous ADD INDEX idx_rendezvous_medecin (medecin_id)");
    }

    if (!schemaForeignKeyExists($pdo, $dbname, 'rendezvous', 'fk_rendezvous_medecin')) {
        $pdo->exec("
            ALTER TABLE rendezvous
            ADD CONSTRAINT fk_rendezvous_medecin
            FOREIGN KEY (medecin_id) REFERENCES medecins(id)
            ON DELETE SET NULL
        ");
    }

    // Migration legere des rendez-vous existants vers la table medecins
    $legacyDoctors = $pdo->query("
        SELECT DISTINCT TRIM(medecin) AS medecin
        FROM rendezvous
        WHERE medecin_id IS NULL
          AND medecin IS NOT NULL
          AND TRIM(medecin) <> ''
    ")->fetchAll(PDO::FETCH_COLUMN);

    if ($legacyDoctors) {
        $findDoctorStmt = $pdo->prepare("SELECT id FROM medecins WHERE nom_complet = :nom_complet LIMIT 1");
        $insertDoctorStmt = $pdo->prepare("
            INSERT INTO medecins (nom_complet, specialite)
            VALUES (:nom_complet, :specialite)
        ");
        $updateAppointmentDoctorStmt = $pdo->prepare("
            UPDATE rendezvous
            SET medecin_id = :medecin_id
            WHERE medecin_id IS NULL AND medecin = :medecin
        ");

        foreach ($legacyDoctors as $legacyDoctor) {
            $legacyDoctor = trim((string) $legacyDoctor);

            if ($legacyDoctor === '') {
                continue;
            }

            $doctorName = $legacyDoctor;
            $specialite = 'Generaliste';

            if (str_contains($legacyDoctor, ' - ')) {
                [$doctorName, $specialitePart] = array_map('trim', explode(' - ', $legacyDoctor, 2));
                $specialite = $specialitePart !== '' ? $specialitePart : $specialite;
            }

            $findDoctorStmt->execute(['nom_complet' => $doctorName]);
            $doctorId = $findDoctorStmt->fetchColumn();

            if (!$doctorId) {
                $insertDoctorStmt->execute([
                    'nom_complet' => $doctorName,
                    'specialite' => $specialite,
                ]);
                $doctorId = (int) $pdo->lastInsertId();
            }

            $updateAppointmentDoctorStmt->execute([
                'medecin_id' => $doctorId,
                'medecin' => $legacyDoctor,
            ]);
        }
    }

} catch (PDOException $e) {
    die("Erreur de connexion / creation de la base de donnees : " . $e->getMessage());
}
?>
