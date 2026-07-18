<?php
require_once 'db.php';
require_once __DIR__ . '/includes/layout.php';

$patientId = (int) ($_GET['patient_id'] ?? $_POST['patient_id'] ?? 0);

if ($patientId <= 0) {
    header('Location: index.php');
    exit;
}

$patientStmt = $pdo->prepare("SELECT * FROM patients WHERE id = :id");
$patientStmt->execute(['id' => $patientId]);
$patient = $patientStmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    header('Location: index.php');
    exit;
}

$doctors = $pdo->query("SELECT id, nom_complet, specialite FROM medecins ORDER BY nom_complet ASC")->fetchAll(PDO::FETCH_ASSOC);
$doctorMap = [];
foreach ($doctors as $doctor) {
    $doctorMap[(int) $doctor['id']] = $doctor;
}

$statuses = getAppointmentStatuses();
$notice = getNoticeConfig($_GET['notice'] ?? null);
$error = '';

$editAppointmentId = (int) ($_GET['appointment_id'] ?? $_POST['appointment_id'] ?? 0);
$editAppointment = null;

if ($editAppointmentId > 0) {
    $editStmt = $pdo->prepare("SELECT * FROM rendezvous WHERE id = :id AND patient_id = :patient_id");
    $editStmt->execute([
        'id' => $editAppointmentId,
        'patient_id' => $patientId,
    ]);
    $editAppointment = $editStmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$formData = [
    'date_rendezvous' => date('Y-m-d'),
    'heure_rendezvous' => date('H:00'),
    'medecin_id' => 0,
    'motif' => '',
    'statut' => 'programme',
    'notes' => '',
];

if ($editAppointment) {
    $formData = [
        'date_rendezvous' => $editAppointment['date_rendezvous'],
        'heure_rendezvous' => substr($editAppointment['heure_rendezvous'], 0, 5),
        'medecin_id' => (int) ($editAppointment['medecin_id'] ?? 0),
        'motif' => $editAppointment['motif'],
        'statut' => $editAppointment['statut'],
        'notes' => $editAppointment['notes'] ?? '',
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'date_rendezvous' => trim($_POST['date_rendezvous'] ?? ''),
        'heure_rendezvous' => trim($_POST['heure_rendezvous'] ?? ''),
        'medecin_id' => (int) ($_POST['medecin_id'] ?? 0),
        'motif' => trim($_POST['motif'] ?? ''),
        'statut' => trim($_POST['statut'] ?? 'programme'),
        'notes' => trim($_POST['notes'] ?? ''),
    ];

    $selectedDoctor = $doctorMap[$formData['medecin_id']] ?? null;

    if (
        $formData['date_rendezvous'] === '' ||
        $formData['heure_rendezvous'] === '' ||
        $formData['motif'] === ''
    ) {
        $error = 'La date, l heure et le motif sont obligatoires.';
    } elseif (!isValidDateValue($formData['date_rendezvous'])) {
        $error = 'La date du rendez-vous est invalide.';
    } elseif (!isValidTimeValue($formData['heure_rendezvous'])) {
        $error = 'L heure du rendez-vous est invalide.';
    } elseif (!$selectedDoctor) {
        $error = 'Selectionnez un docteur pour ce rendez-vous.';
    } elseif (!array_key_exists($formData['statut'], $statuses)) {
        $error = 'Le statut selectionne est invalide.';
    } else {
        $payload = [
            'patient_id' => $patientId,
            'medecin_id' => $formData['medecin_id'],
            'date_rendezvous' => $formData['date_rendezvous'],
            'heure_rendezvous' => $formData['heure_rendezvous'] . ':00',
            'medecin' => formatDoctorSnapshot($selectedDoctor),
            'motif' => $formData['motif'],
            'statut' => $formData['statut'],
            'notes' => $formData['notes'] !== '' ? $formData['notes'] : null,
        ];

        if ($editAppointmentId > 0) {
            $payload['id'] = $editAppointmentId;

            $updateStmt = $pdo->prepare("
                UPDATE rendezvous
                SET date_rendezvous = :date_rendezvous,
                    heure_rendezvous = :heure_rendezvous,
                    medecin_id = :medecin_id,
                    medecin = :medecin,
                    motif = :motif,
                    statut = :statut,
                    notes = :notes
                WHERE id = :id AND patient_id = :patient_id
            ");
            $updateStmt->execute($payload);

            header('Location: rendezvous.php?patient_id=' . $patientId . '&notice=appointment_updated');
            exit;
        }

        $insertStmt = $pdo->prepare("
            INSERT INTO rendezvous (patient_id, medecin_id, date_rendezvous, heure_rendezvous, medecin, motif, statut, notes)
            VALUES (:patient_id, :medecin_id, :date_rendezvous, :heure_rendezvous, :medecin, :motif, :statut, :notes)
        ");
        $insertStmt->execute($payload);

        header('Location: rendezvous.php?patient_id=' . $patientId . '&notice=appointment_created');
        exit;
    }
}

$statsStmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN statut IN ('programme', 'confirme') AND TIMESTAMP(date_rendezvous, heure_rendezvous) >= NOW() THEN 1 ELSE 0 END) AS upcoming,
        SUM(CASE WHEN statut = 'termine' THEN 1 ELSE 0 END) AS completed,
        SUM(CASE WHEN statut = 'annule' THEN 1 ELSE 0 END) AS cancelled
    FROM rendezvous
    WHERE patient_id = :patient_id
");
$statsStmt->execute(['patient_id' => $patientId]);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

$appointmentsStmt = $pdo->prepare("
    SELECT
        r.*,
        COALESCE(m.nom_complet, r.medecin) AS doctor_name,
        m.specialite AS doctor_specialite
    FROM rendezvous
    r
    LEFT JOIN medecins m ON m.id = r.medecin_id
    WHERE patient_id = :patient_id
    ORDER BY date_rendezvous DESC, heure_rendezvous DESC, id DESC
");
$appointmentsStmt->execute(['patient_id' => $patientId]);
$appointments = $appointmentsStmt->fetchAll(PDO::FETCH_ASSOC);

renderPageStart('Rendez-vous patient', 'agenda');
?>
<section class="page-header mb-4">
    <div>
        <h1 class="page-title">Rendez-vous patient</h1>
        <p class="page-subtitle"><?= e(formatPatientName($patient)) ?></p>
    </div>
    <div class="page-actions">
        <span class="inline-stat"><strong><?= (int) ($stats['total'] ?? 0) ?></strong> total</span>
        <span class="inline-stat"><strong><?= (int) ($stats['upcoming'] ?? 0) ?></strong> a venir</span>
        <span class="inline-stat"><strong><?= (int) ($stats['completed'] ?? 0) ?></strong> termines</span>
        <a href="edit.php?id=<?= (int) $patient['id'] ?>" class="btn btn-outline-secondary">Modifier le patient</a>
    </div>
</section>

<?php if ($notice): ?>
    <div class="alert alert-<?= e($notice['type']) ?> alert-dismissible fade show mb-4" role="alert">
        <?= e($notice['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger mb-4" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<section class="simple-card p-4 mb-4">
    <div class="section-heading mb-4">
        <h2 class="h4 mb-0">Resume du patient</h2>
        <div class="d-flex flex-wrap gap-2">
            <a href="agenda.php" class="btn btn-outline-primary">Agenda global</a>
            <a href="index.php" class="btn btn-outline-secondary">Patients</a>
        </div>
    </div>

    <div class="patient-meta">
        <div class="meta-block">
            <p class="meta-label">Contact</p>
            <p class="meta-value"><?= e($patient['contact']) ?></p>
        </div>
        <div class="meta-block">
            <p class="meta-label">Sexe</p>
            <p class="meta-value"><?= e($patient['sexe']) ?></p>
        </div>
        <div class="meta-block">
            <p class="meta-label">Age</p>
            <p class="meta-value"><?= calculateAge($patient['date_naissance']) ?> ans</p>
        </div>
        <div class="meta-block">
            <p class="meta-label">Groupe sanguin</p>
            <p class="meta-value"><?= e($patient['groupe_sanguin']) ?></p>
        </div>
    </div>
</section>

<section class="simple-card p-4 mb-4">
    <div class="section-heading mb-3">
        <h2 class="h4 mb-0"><?= $editAppointment ? 'Modifier le rendez-vous' : 'Planifier un rendez-vous' ?></h2>
        <?php if ($editAppointment): ?>
            <a href="rendezvous.php?patient_id=<?= (int) $patientId ?>" class="btn btn-sm btn-outline-secondary">Nouveau rendez-vous</a>
        <?php endif; ?>
    </div>

    <?php if (!$doctors): ?>
        <div class="alert alert-warning mb-0" role="alert">
            Aucun docteur n'est encore enregistre. Ajoutez-en un d'abord dans
            <a href="medecins.php" class="alert-link">la gestion des docteurs</a>.
        </div>
    <?php else: ?>
        <form method="POST" novalidate>
            <input type="hidden" name="patient_id" value="<?= (int) $patientId ?>">
            <input type="hidden" name="appointment_id" value="<?= (int) $editAppointmentId ?>">

            <div class="row g-3">
                <div class="col-md-4">
                    <label for="date_rendezvous" class="form-label">Date</label>
                    <input type="date" class="form-control" id="date_rendezvous" name="date_rendezvous" value="<?= e($formData['date_rendezvous']) ?>" required>
                </div>
                <div class="col-md-3">
                    <label for="heure_rendezvous" class="form-label">Heure</label>
                    <input type="time" class="form-control" id="heure_rendezvous" name="heure_rendezvous" value="<?= e($formData['heure_rendezvous']) ?>" required>
                </div>
                <div class="col-md-5">
                    <label for="medecin_id" class="form-label">Docteur</label>
                    <select class="form-select" id="medecin_id" name="medecin_id" required>
                        <option value="0">Selectionnez un docteur</option>
                        <?php foreach ($doctors as $doctor): ?>
                            <option value="<?= (int) $doctor['id'] ?>" <?= (int) $formData['medecin_id'] === (int) $doctor['id'] ? 'selected' : '' ?>>
                                <?= e($doctor['nom_complet']) ?> - <?= e($doctor['specialite']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="motif" class="form-label">Motif</label>
                    <input type="text" class="form-control" id="motif" name="motif" value="<?= e($formData['motif']) ?>" required>
                </div>
                <div class="col-md-3">
                    <label for="statut" class="form-label">Statut</label>
                    <select class="form-select" id="statut" name="statut" required>
                        <?php foreach ($statuses as $statusValue => $statusLabel): ?>
                            <option value="<?= e($statusValue) ?>" <?= $formData['statut'] === $statusValue ? 'selected' : '' ?>>
                                <?= e($statusLabel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Docteurs</label>
                    <div class="d-grid">
                        <a href="medecins.php" class="btn btn-outline-secondary">Gerer</a>
                    </div>
                </div>
                <div class="col-12">
                    <label for="notes" class="form-label">Notes</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Notes internes ou consignes pour le rendez-vous"><?= e($formData['notes']) ?></textarea>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mt-4">
                <button type="submit" class="btn btn-primary">
                    <?= $editAppointment ? 'Mettre a jour' : 'Ajouter le rendez-vous' ?>
                </button>
                <?php if ($editAppointment): ?>
                    <a href="rendezvous.php?patient_id=<?= (int) $patientId ?>" class="btn btn-outline-secondary">Annuler</a>
                <?php endif; ?>
            </div>
        </form>
    <?php endif; ?>
</section>

<section class="simple-card p-4">
    <div class="section-heading mb-3">
        <h2 class="h4 mb-0">Historique des rendez-vous</h2>
        <span class="badge rounded-pill text-bg-light"><?= (int) ($stats['total'] ?? 0) ?> entree(s)</span>
    </div>

    <?php if ($appointments): ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Medecin</th>
                        <th>Motif</th>
                        <th>Statut</th>
                        <th>Notes</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($appointments as $appointment): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= e(formatShortDate($appointment['date_rendezvous'])) ?></div>
                                <small class="text-secondary"><?= e(substr($appointment['heure_rendezvous'], 0, 5)) ?></small>
                            </td>
                            <td>
                                <div class="fw-semibold"><?= e($appointment['doctor_name']) ?></div>
                                <small class="text-secondary"><?= e($appointment['doctor_specialite'] ?: 'Specialite non definie') ?></small>
                            </td>
                            <td><?= e($appointment['motif']) ?></td>
                            <td>
                                <span class="badge rounded-pill <?= e(getAppointmentBadgeClass($appointment['statut'])) ?>">
                                    <?= e(getAppointmentStatusLabel($appointment['statut'])) ?>
                                </span>
                            </td>
                            <td><?= e($appointment['notes'] ?: '-') ?></td>
                            <td class="text-end">
                                <div class="d-flex flex-wrap justify-content-end gap-2">
                                    <a href="rendezvous.php?patient_id=<?= (int) $patientId ?>&appointment_id=<?= (int) $appointment['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                        Modifier
                                    </a>
                                    <a
                                        href="delete_rendezvous.php?id=<?= (int) $appointment['id'] ?>&patient_id=<?= (int) $patientId ?>"
                                        class="btn btn-sm btn-outline-danger"
                                        onclick="return confirm('Supprimer ce rendez-vous ?');"
                                    >Supprimer</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-state text-center py-5 px-4">
            <h3 class="h5 mb-2">Aucun rendez-vous enregistre</h3>
            <p class="text-secondary mb-0">Utilisez le formulaire ci-dessus pour ajouter la premiere consultation du patient.</p>
        </div>
    <?php endif; ?>
</section>
<?php renderPageEnd(); ?>
