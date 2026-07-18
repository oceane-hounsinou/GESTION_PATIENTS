<?php
require_once 'db.php';

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM patients WHERE id = :id");
    $stmt->execute(['id' => $id]);
}

header('Location: index.php?notice=patient_deleted');
exit;
