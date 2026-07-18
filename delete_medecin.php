<?php
require_once 'db.php';

$doctorId = (int) ($_GET['id'] ?? 0);

if ($doctorId > 0) {
    $doctorStmt = $pdo->prepare("SELECT nom_complet, specialite FROM medecins WHERE id = :id");
    $doctorStmt->execute(['id' => $doctorId]);
    $doctor = $doctorStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($doctor) {
        $snapshotStmt = $pdo->prepare("
            UPDATE rendezvous
            SET medecin = :medecin
            WHERE medecin_id = :medecin_id
        ");
        $snapshotStmt->execute([
            'medecin' => formatDoctorSnapshot($doctor),
            'medecin_id' => $doctorId,
        ]);

        $deleteStmt = $pdo->prepare("DELETE FROM medecins WHERE id = :id");
        $deleteStmt->execute(['id' => $doctorId]);
    }
}

header('Location: medecins.php?notice=doctor_deleted');
exit;
