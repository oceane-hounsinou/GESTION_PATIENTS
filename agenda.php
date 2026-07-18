<?php
require_once 'db.php';
require_once __DIR__ . '/includes/layout.php';

$notice = getNoticeConfig($_GET['notice'] ?? null);

$filters = [
    'date' => trim($_GET['date'] ?? ''),
    'statut' => trim($_GET['statut'] ?? ''),
    'medecin_id' => (int) ($_GET['medecin_id'] ?? 0),
];

$doctors = $pdo->query("SELECT id, nom_complet, specialite FROM medecins ORDER BY nom_complet ASC")->fetchAll(PDO::FETCH_ASSOC);
$statuses = getAppointmentStatuses();

$sql = "
    SELECT
        r.*,
        p.nom,
        p.prenom,
        p.contact,
        COALESCE(m.nom_complet, r.medecin) AS doctor_name,
        m.specialite
    FROM rendezvous r
    INNER JOIN patients p ON p.id = r.patient_id
    LEFT JOIN medecins m ON m.id = r.medecin_id
    WHERE 1 = 1
";
$params = [];

if ($filters['date'] !== '') {
    $sql .= " AND r.date_rendezvous = :date_rendezvous";
    $params['date_rendezvous'] = $filters['date'];
}

if ($filters['statut'] !== '' && array_key_exists($filters['statut'], $statuses)) {
    $sql .= " AND r.statut = :statut";
    $params['statut'] = $filters['statut'];
}

if ($filters['medecin_id'] > 0) {
    $sql .= " AND r.medecin_id = :medecin_id";
    $params['medecin_id'] = $filters['medecin_id'];
}

$sql .= " ORDER BY r.date_rendezvous ASC, r.heure_rendezvous ASC, p.nom ASC, p.prenom ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stats = $pdo->query("
    SELECT
        (SELECT COUNT(*) FROM rendezvous WHERE date_rendezvous = CURDATE() AND statut IN ('programme', 'confirme')) AS today_total,
        (SELECT COUNT(*) FROM rendezvous WHERE TIMESTAMP(date_rendezvous, heure_rendezvous) >= NOW() AND statut IN ('programme', 'confirme')) AS upcoming_total,
        (SELECT COUNT(*) FROM medecins) AS doctors_total
")->fetch(PDO::FETCH_ASSOC);

renderPageStart('Agenda des rendez-vous', 'agenda');
?>
<?php if ($notice): ?>
    <div class="alert alert-<?= e($notice['type']) ?> alert-dismissible fade show mb-4" role="alert">
        <?= e($notice['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
    </div>
<?php endif; ?>

<section class="page-header mb-4">
    <div>
        <h1 class="page-title">Rendez-vous</h1>
        <p class="page-subtitle">Vue globale du planning medical avec filtres par date, statut et docteur.</p>
    </div>
    <div class="page-actions">
        <span class="inline-stat"><strong><?= (int) ($stats['today_total'] ?? 0) ?></strong> aujourd'hui</span>
        <span class="inline-stat"><strong><?= (int) ($stats['upcoming_total'] ?? 0) ?></strong> a venir</span>
        <span class="inline-stat"><strong><?= (int) ($stats['doctors_total'] ?? 0) ?></strong> docteurs</span>
    </div>
</section>

<section class="simple-card p-4 mb-4">
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-4">
            <label for="date" class="form-label">Date</label>
            <input type="date" class="form-control" id="date" name="date" value="<?= e($filters['date']) ?>">
        </div>
        <div class="col-md-4">
            <label for="medecin_id" class="form-label">Docteur</label>
            <select class="form-select" id="medecin_id" name="medecin_id">
                <option value="0">Tous les docteurs</option>
                <?php foreach ($doctors as $doctor): ?>
                    <option value="<?= (int) $doctor['id'] ?>" <?= $filters['medecin_id'] === (int) $doctor['id'] ? 'selected' : '' ?>>
                        <?= e($doctor['nom_complet']) ?> - <?= e($doctor['specialite']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label for="statut" class="form-label">Statut</label>
            <select class="form-select" id="statut" name="statut">
                <option value="">Tous les statuts</option>
                <?php foreach ($statuses as $statusValue => $statusLabel): ?>
                    <option value="<?= e($statusValue) ?>" <?= $filters['statut'] === $statusValue ? 'selected' : '' ?>><?= e($statusLabel) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-sm-6 col-lg-2">
            <button type="submit" class="btn btn-primary w-100">Filtrer</button>
        </div>
        <div class="col-sm-6 col-lg-2">
            <a href="agenda.php" class="btn btn-outline-secondary w-100">Reinitialiser</a>
        </div>
    </form>
</section>

<section class="simple-card p-4">
    <div class="section-heading mb-3">
        <h2 class="h4 mb-0">Planning</h2>
        <span class="badge rounded-pill text-bg-light"><?= count($appointments) ?> rendez-vous</span>
    </div>

    <?php if ($appointments): ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Patient</th>
                        <th>Docteur</th>
                        <th>Motif</th>
                        <th>Statut</th>
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
                                <div class="fw-semibold"><?= e(trim($appointment['prenom'] . ' ' . $appointment['nom'])) ?></div>
                                <small class="text-secondary"><?= e($appointment['contact']) ?></small>
                            </td>
                            <td>
                                <div class="fw-semibold"><?= e($appointment['doctor_name']) ?></div>
                                <small class="text-secondary"><?= e($appointment['specialite'] ?: 'Specialite non definie') ?></small>
                            </td>
                            <td><?= e($appointment['motif']) ?></td>
                            <td>
                                <span class="badge rounded-pill <?= e(getAppointmentBadgeClass($appointment['statut'])) ?>">
                                    <?= e(getAppointmentStatusLabel($appointment['statut'])) ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="rendezvous.php?patient_id=<?= (int) $appointment['patient_id'] ?>&appointment_id=<?= (int) $appointment['id'] ?>" class="btn btn-sm btn-outline-primary">
                                    Ouvrir
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-state text-center py-5 px-4">
            <h3 class="h5 mb-2">Aucun rendez-vous trouve</h3>
            <p class="text-secondary mb-0">Ajustez les filtres ou planifiez un nouveau rendez-vous depuis la fiche d'un patient.</p>
        </div>
    <?php endif; ?>
</section>
<?php renderPageEnd(); ?>
