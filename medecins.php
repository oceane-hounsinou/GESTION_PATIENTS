<?php
require_once 'db.php';
require_once __DIR__ . '/includes/layout.php';

$doctorId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$notice = getNoticeConfig($_GET['notice'] ?? null);
$error = '';

$formData = [
    'nom_complet' => '',
    'specialite' => '',
    'telephone' => '',
    'email' => '',
];

$editingDoctor = null;

if ($doctorId > 0) {
    $doctorStmt = $pdo->prepare("SELECT * FROM medecins WHERE id = :id");
    $doctorStmt->execute(['id' => $doctorId]);
    $editingDoctor = $doctorStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($editingDoctor) {
        $formData = [
            'nom_complet' => $editingDoctor['nom_complet'],
            'specialite' => $editingDoctor['specialite'],
            'telephone' => $editingDoctor['telephone'] ?? '',
            'email' => $editingDoctor['email'] ?? '',
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'nom_complet' => trim($_POST['nom_complet'] ?? ''),
        'specialite' => trim($_POST['specialite'] ?? ''),
        'telephone' => trim($_POST['telephone'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
    ];

    if ($formData['nom_complet'] === '' || $formData['specialite'] === '') {
        $error = 'Le nom du docteur et la specialite sont obligatoires.';
    } elseif ($formData['email'] !== '' && !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'L adresse email est invalide.';
    } elseif ($formData['telephone'] !== '' && !preg_match('/^\+?[0-9 ]{8,20}$/', $formData['telephone'])) {
        $error = 'Le numero de telephone est invalide.';
    } else {
        $payload = [
            'nom_complet' => $formData['nom_complet'],
            'specialite' => $formData['specialite'],
            'telephone' => $formData['telephone'] !== '' ? $formData['telephone'] : null,
            'email' => $formData['email'] !== '' ? $formData['email'] : null,
        ];

        if ($doctorId > 0 && $editingDoctor) {
            $payload['id'] = $doctorId;

            $updateStmt = $pdo->prepare("
                UPDATE medecins
                SET nom_complet = :nom_complet,
                    specialite = :specialite,
                    telephone = :telephone,
                    email = :email
                WHERE id = :id
            ");
            $updateStmt->execute($payload);

            $snapshotStmt = $pdo->prepare("
                UPDATE rendezvous
                SET medecin = :medecin
                WHERE medecin_id = :medecin_id
            ");
            $snapshotStmt->execute([
                'medecin' => formatDoctorSnapshot($formData),
                'medecin_id' => $doctorId,
            ]);

            header('Location: medecins.php?notice=doctor_updated');
            exit;
        }

        $insertStmt = $pdo->prepare("
            INSERT INTO medecins (nom_complet, specialite, telephone, email)
            VALUES (:nom_complet, :specialite, :telephone, :email)
        ");
        $insertStmt->execute($payload);

        header('Location: medecins.php?notice=doctor_created');
        exit;
    }
}

$doctorsStmt = $pdo->query("
    SELECT
        m.*,
        COUNT(r.id) AS total_rendezvous,
        SUM(CASE WHEN r.statut IN ('programme', 'confirme') AND TIMESTAMP(r.date_rendezvous, r.heure_rendezvous) >= NOW() THEN 1 ELSE 0 END) AS upcoming_rendezvous
    FROM medecins m
    LEFT JOIN rendezvous r ON r.medecin_id = m.id
    GROUP BY m.id
    ORDER BY m.nom_complet ASC
");
$doctors = $doctorsStmt->fetchAll(PDO::FETCH_ASSOC);

renderPageStart('Gestion des docteurs', 'medecins');
?>
<?php if ($notice): ?>
    <div class="alert alert-<?= e($notice['type']) ?> alert-dismissible fade show mb-4" role="alert">
        <?= e($notice['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger mb-4" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<section class="page-header mb-4">
    <div>
        <h1 class="page-title">Docteurs</h1>
        <p class="page-subtitle">Centralisez les profils des medecins et reutilisez-les dans les rendez-vous.</p>
    </div>
</section>

<section class="row g-4">
    <div class="col-lg-5">
        <div class="simple-card p-4">
            <div class="section-heading mb-3">
                <h2 class="h4 mb-0"><?= $editingDoctor ? 'Modifier un docteur' : 'Ajouter un docteur' ?></h2>
                <?php if ($editingDoctor): ?>
                    <a href="medecins.php" class="btn btn-sm btn-outline-secondary">Annuler</a>
                <?php endif; ?>
            </div>

            <form method="POST" novalidate>
                <input type="hidden" name="id" value="<?= (int) $doctorId ?>">

                <div class="mb-3">
                    <label for="nom_complet" class="form-label">Nom complet</label>
                    <input type="text" class="form-control" id="nom_complet" name="nom_complet" value="<?= e($formData['nom_complet']) ?>" required>
                </div>

                <div class="mb-3">
                    <label for="specialite" class="form-label">Specialite</label>
                    <input type="text" class="form-control" id="specialite" name="specialite" value="<?= e($formData['specialite']) ?>" required>
                </div>

                <div class="mb-3">
                    <label for="telephone" class="form-label">Telephone</label>
                    <input type="text" class="form-control" id="telephone" name="telephone" value="<?= e($formData['telephone']) ?>" placeholder="+229 01020304">
                </div>

                <div class="mb-4">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?= e($formData['email']) ?>" placeholder="docteur@clinique.com">
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><?= $editingDoctor ? 'Mettre a jour' : 'Ajouter' ?></button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="simple-card p-4">
            <div class="section-heading mb-3">
                <h2 class="h4 mb-0">Equipe medicale</h2>
                <span class="badge rounded-pill text-bg-light"><?= count($doctors) ?> docteur(s)</span>
            </div>

            <?php if ($doctors): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Docteur</th>
                                <th>Contact</th>
                                <th>Rendez-vous</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($doctors as $doctor): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= e($doctor['nom_complet']) ?></div>
                                        <small class="text-secondary"><?= e($doctor['specialite']) ?></small>
                                    </td>
                                    <td><?= e(formatDoctorContact($doctor['telephone'], $doctor['email'])) ?></td>
                                    <td>
                                        <span class="badge rounded-pill text-bg-light">
                                            <?= (int) ($doctor['upcoming_rendezvous'] ?? 0) ?> a venir / <?= (int) ($doctor['total_rendezvous'] ?? 0) ?> total
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex flex-wrap justify-content-end gap-2">
                                            <a href="medecins.php?id=<?= (int) $doctor['id'] ?>" class="btn btn-sm btn-outline-secondary">Modifier</a>
                                            <a
                                                href="delete_medecin.php?id=<?= (int) $doctor['id'] ?>"
                                                class="btn btn-sm btn-outline-danger"
                                                onclick="return confirm('Supprimer ce docteur ? Les rendez-vous conserveront son nom en historique.');"
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
                    <h3 class="h5 mb-2">Aucun docteur enregistre</h3>
                    <p class="text-secondary mb-0">Ajoutez au moins un docteur pour l'utiliser dans les rendez-vous.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php renderPageEnd(); ?>
