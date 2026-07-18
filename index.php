<?php
require_once 'db.php';
require_once __DIR__ . '/includes/layout.php';

$search = trim($_GET['search'] ?? '');
$notice = getNoticeConfig($_GET['notice'] ?? null);

$sql = "SELECT
            p.*,
            (
                SELECT COUNT(*)
                FROM rendezvous r
                WHERE r.patient_id = p.id
            ) AS total_rendezvous,
            (
                SELECT MIN(TIMESTAMP(r.date_rendezvous, r.heure_rendezvous))
                FROM rendezvous r
                WHERE r.patient_id = p.id
                  AND r.statut IN ('programme', 'confirme')
                  AND TIMESTAMP(r.date_rendezvous, r.heure_rendezvous) >= NOW()
            ) AS prochain_rendezvous
        FROM patients p";

$params = [];

if ($search !== '') {
    $sql .= " WHERE p.nom LIKE :search OR p.prenom LIKE :search OR p.contact LIKE :search";
    $params['search'] = '%' . $search . '%';
}

$sql .= " ORDER BY p.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

$dashboard = $pdo->query("
    SELECT
        (SELECT COUNT(*) FROM patients) AS total_patients,
        (SELECT COUNT(*) FROM medecins) AS total_doctors,
        (SELECT COUNT(*) FROM rendezvous WHERE date_rendezvous = CURDATE() AND statut IN ('programme', 'confirme')) AS today_appointments,
        (SELECT COUNT(*) FROM rendezvous WHERE TIMESTAMP(date_rendezvous, heure_rendezvous) >= NOW() AND statut IN ('programme', 'confirme')) AS upcoming_appointments
")->fetch(PDO::FETCH_ASSOC);

$todayAppointmentsStmt = $pdo->query("
    SELECT
        r.*,
        p.nom,
        p.prenom,
        p.contact,
        COALESCE(m.nom_complet, r.medecin) AS doctor_name
    FROM rendezvous r
    INNER JOIN patients p ON p.id = r.patient_id
    LEFT JOIN medecins m ON m.id = r.medecin_id
    WHERE r.date_rendezvous = CURDATE()
    ORDER BY r.heure_rendezvous ASC, p.nom ASC, p.prenom ASC
");
$todayAppointments = $todayAppointmentsStmt->fetchAll(PDO::FETCH_ASSOC);

renderPageStart('Gestion des patients', 'patients');
?>
<section class="page-header mb-4">
    <div>
        <h1 class="page-title">Patients</h1>
        <p class="page-subtitle">Dossiers patients, rendez-vous du jour et acces direct aux fiches essentielles.</p>
    </div>
    <div class="page-actions">
        <span class="inline-stat"><strong><?= (int) ($dashboard['total_patients'] ?? 0) ?></strong> patients</span>
        <span class="inline-stat"><strong><?= (int) ($dashboard['today_appointments'] ?? 0) ?></strong> aujourd'hui</span>
        <span class="inline-stat"><strong><?= (int) ($dashboard['upcoming_appointments'] ?? 0) ?></strong> a venir</span>
        <span class="inline-stat"><strong><?= (int) ($dashboard['total_doctors'] ?? 0) ?></strong> docteurs</span>
    </div>
</section>

<?php if ($notice): ?>
    <div class="alert alert-<?= e($notice['type']) ?> alert-dismissible fade show mb-4" role="alert">
        <?= e($notice['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
    </div>
<?php endif; ?>

<section class="simple-card p-4 mb-4">
    <div class="section-heading mb-3">
        <h2 class="h4 mb-0">Liste des patients</h2>
        <a href="create.php" class="btn btn-primary">Ajouter un patient</a>
    </div>

    <form method="GET" class="row g-3 align-items-end">
        <div class="col-lg-8">
            <label for="search" class="form-label">Recherche</label>
            <input
                type="text"
                class="form-control"
                id="search"
                name="search"
                value="<?= e($search) ?>"
                placeholder="Nom, prenom ou contact"
            >
        </div>
        <div class="col-sm-6 col-lg-2">
            <button type="submit" class="btn btn-outline-primary w-100">Rechercher</button>
        </div>
        <div class="col-sm-6 col-lg-2">
            <a href="index.php" class="btn btn-outline-secondary w-100">Reinitialiser</a>
        </div>
    </form>

    <?php if ($patients): ?>
        <div class="table-responsive mt-4">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Patient</th>
                        <th>Contact</th>
                        <th>Groupe</th>
                        <th>Prochain rendez-vous</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($patients as $patient): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= e(formatPatientName($patient)) ?></div>
                                <small class="text-secondary">
                                    <?= e($patient['sexe']) ?> • <?= calculateAge($patient['date_naissance']) ?> ans • <?= (int) ($patient['total_rendezvous'] ?? 0) ?> rendez-vous
                                </small>
                            </td>
                            <td><?= e($patient['contact']) ?></td>
                            <td><span class="badge rounded-pill text-bg-light"><?= e($patient['groupe_sanguin']) ?></span></td>
                            <td><?= e(formatDateTime($patient['prochain_rendezvous'], 'Aucun programme')) ?></td>
                            <td class="text-end">
                                <div class="d-flex flex-wrap justify-content-end gap-2">
                                    <a href="rendezvous.php?patient_id=<?= (int) $patient['id'] ?>" class="btn btn-sm btn-outline-primary">Rendez-vous</a>
                                    <a href="edit.php?id=<?= (int) $patient['id'] ?>" class="btn btn-sm btn-outline-secondary">Modifier</a>
                                    <a
                                        href="delete.php?id=<?= (int) $patient['id'] ?>"
                                        class="btn btn-sm btn-outline-danger"
                                        onclick="return confirm('Supprimer ce patient et tous ses rendez-vous ?');"
                                    >Supprimer</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-state text-center py-5 px-4 mt-4">
            <h3 class="h5 mb-2">Aucun patient trouve</h3>
            <p class="text-secondary mb-3">Ajoutez un premier dossier ou modifiez votre recherche.</p>
            <a href="create.php" class="btn btn-primary">Creer un patient</a>
        </div>
    <?php endif; ?>
</section>

<section id="rendezvous-du-jour" class="simple-card p-4">
    <div class="section-heading mb-3">
        <h2 class="h4 mb-0">Rendez-vous du jour</h2>
        <span class="badge rounded-pill text-bg-light"><?= formatShortDate(date('Y-m-d')) ?></span>
    </div>

    <?php if ($todayAppointments): ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Heure</th>
                        <th>Patient</th>
                        <th>Motif</th>
                        <th>Docteur</th>
                        <th>Statut</th>
                        <th class="text-end">Acces</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($todayAppointments as $appointment): ?>
                        <tr>
                            <td class="fw-semibold"><?= e(substr($appointment['heure_rendezvous'], 0, 5)) ?></td>
                            <td>
                                <div class="fw-semibold"><?= e(trim($appointment['prenom'] . ' ' . $appointment['nom'])) ?></div>
                                <small class="text-secondary"><?= e($appointment['contact']) ?></small>
                            </td>
                            <td><?= e($appointment['motif']) ?></td>
                            <td><?= e($appointment['doctor_name']) ?></td>
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
            <h3 class="h5 mb-2">Aucun rendez-vous programme aujourd'hui</h3>
            <p class="text-secondary mb-0">Le planning du jour apparaitra ici automatiquement.</p>
        </div>
    <?php endif; ?>
</section>
<?php renderPageEnd(); ?>
