<?php
require_once 'db.php';
require_once __DIR__ . '/includes/layout.php';

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM patients WHERE id = :id");
$stmt->execute(['id' => $id]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    header('Location: index.php');
    exit;
}

$notice = getNoticeConfig($_GET['notice'] ?? null);
$error = '';
$formData = $patient;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'id' => $id,
        'nom' => trim($_POST['nom'] ?? ''),
        'prenom' => trim($_POST['prenom'] ?? ''),
        'sexe' => trim($_POST['sexe'] ?? ''),
        'date_naissance' => trim($_POST['date_naissance'] ?? ''),
        'contact' => trim($_POST['contact'] ?? ''),
        'groupe_sanguin' => trim($_POST['groupe_sanguin'] ?? ''),
    ];

    if (
        $formData['nom'] === '' ||
        $formData['prenom'] === '' ||
        $formData['sexe'] === '' ||
        $formData['date_naissance'] === '' ||
        $formData['contact'] === '' ||
        $formData['groupe_sanguin'] === ''
    ) {
        $error = 'Tous les champs sont obligatoires.';
    } elseif (!isValidDateValue($formData['date_naissance'])) {
        $error = 'La date de naissance est invalide.';
    } elseif ($formData['date_naissance'] > date('Y-m-d')) {
        $error = 'La date de naissance ne peut pas etre dans le futur.';
    } elseif (!preg_match('/^229\d{8}$/', $formData['contact'])) {
        $error = 'Le numero de telephone doit commencer par 229 suivi de 8 chiffres.';
    } else {
        $update = $pdo->prepare("
            UPDATE patients
            SET nom = :nom,
                prenom = :prenom,
                sexe = :sexe,
                date_naissance = :date_naissance,
                contact = :contact,
                groupe_sanguin = :groupe_sanguin
            WHERE id = :id
        ");
        $update->execute($formData);

        header('Location: edit.php?id=' . $id . '&notice=patient_updated');
        exit;
    }
}

renderPageStart('Modifier un patient', 'patients');
?>
<section class="page-header mb-4">
    <div>
        <h1 class="page-title">Modifier le patient</h1>
        <p class="page-subtitle"><?= e(formatPatientName($patient)) ?></p>
    </div>
    <div class="page-actions">
        <a href="rendezvous.php?patient_id=<?= (int) $patient['id'] ?>" class="btn btn-primary">Rendez-vous</a>
        <a href="index.php" class="btn btn-outline-secondary">Retour</a>
    </div>
</section>

<?php if ($notice): ?>
    <div class="alert alert-<?= e($notice['type']) ?> alert-dismissible fade show mb-4" role="alert">
        <?= e($notice['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
    </div>
<?php endif; ?>

<section class="simple-card p-4 p-lg-5">
    <div class="section-heading mb-4">
        <h2 class="h4 mb-0">Informations du patient</h2>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" novalidate>
        <input type="hidden" name="id" value="<?= (int) $id ?>">

        <div class="row g-4">
            <div class="col-md-6">
                <label for="nom" class="form-label">Nom</label>
                <input type="text" class="form-control" id="nom" name="nom" value="<?= e($formData['nom']) ?>" required>
            </div>
            <div class="col-md-6">
                <label for="prenom" class="form-label">Prenom</label>
                <input type="text" class="form-control" id="prenom" name="prenom" value="<?= e($formData['prenom']) ?>" required>
            </div>

            <div class="col-md-6">
                <label class="form-label d-block">Sexe</label>
                <div class="d-flex flex-wrap gap-3">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="sexe" id="sexeM" value="M" <?= $formData['sexe'] === 'M' ? 'checked' : '' ?> required>
                        <label class="form-check-label" for="sexeM">Masculin</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="sexe" id="sexeF" value="F" <?= $formData['sexe'] === 'F' ? 'checked' : '' ?> required>
                        <label class="form-check-label" for="sexeF">Feminin</label>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <label for="date_naissance" class="form-label">Date de naissance</label>
                <input
                    type="date"
                    class="form-control"
                    id="date_naissance"
                    name="date_naissance"
                    value="<?= e($formData['date_naissance']) ?>"
                    max="<?= e(date('Y-m-d')) ?>"
                    required
                >
            </div>

            <div class="col-md-6">
                <label for="contact" class="form-label">Contact</label>
                <input
                    type="text"
                    class="form-control"
                    id="contact"
                    name="contact"
                    value="<?= e($formData['contact']) ?>"
                    placeholder="229XXXXXXXX"
                    pattern="229[0-9]{8}"
                    required
                >
                <div class="form-hint mt-2">Format attendu: 229 suivi exactement de 8 chiffres.</div>
            </div>

            <div class="col-md-6">
                <label for="groupe_sanguin" class="form-label">Groupe sanguin</label>
                <select class="form-select" id="groupe_sanguin" name="groupe_sanguin" required>
                    <option value="">Selectionnez un groupe</option>
                    <?php foreach (getBloodGroups() as $group): ?>
                        <option value="<?= e($group) ?>" <?= $formData['groupe_sanguin'] === $group ? 'selected' : '' ?>>
                            <?= e($group) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 mt-4">
            <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
            <a href="rendezvous.php?patient_id=<?= (int) $patient['id'] ?>" class="btn btn-outline-primary">Voir les rendez-vous</a>
        </div>
    </form>
</section>
<?php renderPageEnd(); ?>
