<?php
require_once 'db.php';

$appointmentId = (int) ($_GET['id'] ?? 0);
$patientId = (int) ($_GET['patient_id'] ?? 0);

if ($appointmentId > 0 && $patientId > 0) {
    $stmt = $pdo->prepare("DELETE FROM rendezvous WHERE id = :id AND patient_id = :patient_id");
    $stmt->execute([
        'id' => $appointmentId,
        'patient_id' => $patientId,
    ]);
}

if ($patientId > 0) {
    header('Location: rendezvous.php?patient_id=' . $patientId . '&notice=appointment_deleted');
    exit;
}

header('Location: index.php');
exit;
