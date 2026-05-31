<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/utils.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/validation.php';

require_login();
$current_user = current_user();
$user_id = (int)$current_user['id'];

$errors  = [];
$success = false;

$trainer_profile = null;
if ($current_user['role'] === 'trainer') {
    $stmt = db()->prepare('SELECT * FROM trainer_profiles WHERE user_id = ?');
    $stmt->execute([$user_id]);
    $trainer_profile = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $name     = sanitize_string($_POST['name']     ?? '');
    $username = sanitize_string($_POST['username'] ?? '');
    $email    = sanitize_string($_POST['email']    ?? '');
    $password = $_POST['password']  ?? '';
    $password2 = $_POST['password2'] ?? '';

    if (!validate_min_length($name, 2)) {
        $errors['name'] = 'O nome deve ter pelo menos 2 caracteres.';
    }
    if (!validate_username($username)) {
        $errors['username'] = 'Username inválido (3-30 caracteres, letras, números, pontos, hífens).';
    } elseif (username_exists($username, $user_id)) {
        $errors['username'] = 'Este username já está em uso.';
    }
    if (!validate_email($email)) {
        $errors['email'] = 'Email inválido.';
    } elseif (email_exists($email, $user_id)) {
        $errors['email'] = 'Este email já está em uso.';
    }
    if (!empty($password)) {
        if (!validate_password($password)) {
            $errors['password'] = 'A password deve ter pelo menos 6 caracteres.';
        } elseif ($password !== $password2) {
            $errors['password2'] = 'As passwords não coincidem.';
        }
    }

    $photo_filename = $current_user['profile_photo'];
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_photo'];
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
        ];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = $finfo ? finfo_file($finfo, $file['tmp_name']) : null;
        if ($finfo) {
            finfo_close($finfo);
        }

        if (!$mime || !isset($allowed[$mime]) || !is_uploaded_file($file['tmp_name']) || getimagesize($file['tmp_name']) === false) {
            $errors['profile_photo'] = 'Formato de imagem não suportado. Use JPG, PNG, GIF ou WebP.';
        } elseif ($file['size'] > 2 * 1024 * 1024) {
            $errors['profile_photo'] = 'A imagem não pode ultrapassar 2MB.';
        } else {
            $ext = $allowed[$mime];
            $filename = 'user_' . $user_id . '_' . time() . '.' . $ext;
            $dest = __DIR__ . '/../../public/uploads/' . $filename;
            if (move_uploaded_file($file['tmp_name'], $dest)) {
                if ($photo_filename && file_exists(__DIR__ . '/../../public/uploads/' . $photo_filename)) {
                    unlink(__DIR__ . '/../../public/uploads/' . $photo_filename);
                }
                $photo_filename = $filename;
            } else {
                $errors['profile_photo'] = 'Erro ao guardar a imagem.';
            }
        }
    }

    if (empty($errors)) {
        if (!empty($password)) {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => PASSWORD_BCRYPT_COST]);
            db()->prepare('UPDATE users SET name=?, username=?, email=?, password_hash=?, profile_photo=? WHERE id=?')
               ->execute([$name, $username, $email, $hash, $photo_filename, $user_id]);
        } else {
            db()->prepare('UPDATE users SET name=?, username=?, email=?, profile_photo=? WHERE id=?')
               ->execute([$name, $username, $email, $photo_filename, $user_id]);
        }

        if ($current_user['role'] === 'trainer' && $trainer_profile !== null) {
            $bio            = sanitize_string($_POST['bio']            ?? '');
            $specializations = sanitize_string($_POST['specializations'] ?? '');
            $certifications = sanitize_string($_POST['certifications'] ?? '');
            $experience     = (int)($_POST['experience_years'] ?? 0);
            db()->prepare('UPDATE trainer_profiles SET bio=?, specializations=?, certifications=?, experience_years=?, updated_at=datetime(\'now\') WHERE user_id=?')
               ->execute([$bio, $specializations, $certifications, $experience, $user_id]);
        }

        $success = true;
        $current_user = current_user();
        if ($current_user['role'] === 'trainer') {
            $stmt = db()->prepare('SELECT * FROM trainer_profiles WHERE user_id = ?');
            $stmt->execute([$user_id]);
            $trainer_profile = $stmt->fetch();
        }
    }
}

$page_title = 'Editar Perfil';
$extra_css  = ['profile.css'];
include __DIR__ . '/../../templates/header.php';
include __DIR__ . '/../../templates/navbar.php';
?>
<div class="page-wrapper">
    <div class="container page-content">
        <div class="page-header">
            <h1>Editar Perfil</h1>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">Perfil atualizado com sucesso.</div>
        <?php endif; ?>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">Por favor corrija os erros abaixo.</div>
        <?php endif; ?>

        <div style="max-width:640px">
            <form method="post" action="/?page=profile/edit" enctype="multipart/form-data">
                <?= csrf_field() ?>

                <div class="card" style="margin-bottom:1.5rem">
                    <div class="card-body">
                        <h3 style="margin-bottom:1.25rem">Informações pessoais</h3>

                        <div style="display:flex;align-items:center;gap:1.5rem;margin-bottom:1.5rem">
                            <img src="<?= e(avatar_url($current_user['profile_photo'] ?? null, $current_user['name'])) ?>"
                                 alt="Foto de perfil" class="avatar-lg" id="profilePhotoPreview">
                            <div class="flex-1">
                                <label class="form-label">Foto de perfil</label>
                                <input type="file" id="profilePhotoInput" name="profile_photo" accept="image/*" class="form-control">
                                <div class="form-hint">JPG, PNG, GIF ou WebP. Máx. 2MB.</div>
                                <?php if (isset($errors['profile_photo'])): ?>
                                    <div class="form-error"><?= e($errors['profile_photo']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="name">Nome completo</label>
                            <input type="text" id="name" name="name" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                                value="<?= e($current_user['name']) ?>" required>
                            <?php if (isset($errors['name'])): ?>
                                <div class="form-error"><?= e($errors['name']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="username">Username</label>
                            <input type="text" id="username" name="username" class="form-control <?= isset($errors['username']) ? 'is-invalid' : '' ?>"
                                value="<?= e($current_user['username']) ?>" required>
                            <?php if (isset($errors['username'])): ?>
                                <div class="form-error"><?= e($errors['username']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="email">Email</label>
                            <input type="email" id="email" name="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                                value="<?= e($current_user['email']) ?>" required>
                            <?php if (isset($errors['email'])): ?>
                                <div class="form-error"><?= e($errors['email']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="card" style="margin-bottom:1.5rem">
                    <div class="card-body">
                        <h3 style="margin-bottom:1.25rem">Alterar password</h3>
                        <p class="text-muted" style="font-size:0.9rem;margin-bottom:1rem">Deixe em branco para manter a password atual.</p>

                        <div class="form-group">
                            <label class="form-label" for="password">Nova password</label>
                            <input type="password" id="password" name="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                                autocomplete="new-password">
                            <?php if (isset($errors['password'])): ?>
                                <div class="form-error"><?= e($errors['password']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="password2">Confirmar nova password</label>
                            <input type="password" id="password2" name="password2" class="form-control <?= isset($errors['password2']) ? 'is-invalid' : '' ?>"
                                autocomplete="new-password">
                            <?php if (isset($errors['password2'])): ?>
                                <div class="form-error"><?= e($errors['password2']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if ($current_user['role'] === 'trainer' && $trainer_profile !== null): ?>
                <div class="card" style="margin-bottom:1.5rem">
                    <div class="card-body">
                        <h3 style="margin-bottom:1.25rem">Perfil de treinador</h3>

                        <div class="form-group">
                            <label class="form-label" for="bio">Biografia</label>
                            <textarea id="bio" name="bio" class="form-control" rows="4" placeholder="Descreva a sua experiência e filosofia de treino..."><?= e($trainer_profile['bio'] ?? '') ?></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="specializations">Especializações</label>
                            <input type="text" id="specializations" name="specializations" class="form-control"
                                value="<?= e($trainer_profile['specializations'] ?? '') ?>"
                                placeholder="Ex: Musculação, CrossFit, Yoga">
                            <div class="form-hint">Separe as especializações por vírgulas.</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="certifications">Certificações</label>
                            <textarea id="certifications" name="certifications" class="form-control" rows="3"
                                placeholder="Ex: CREF, CrossFit Level 2..."><?= e($trainer_profile['certifications'] ?? '') ?></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="experience_years">Anos de experiência</label>
                            <input type="number" id="experience_years" name="experience_years" class="form-control"
                                value="<?= e($trainer_profile['experience_years'] ?? 0) ?>" min="0" max="50">
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div style="display:flex;gap:1rem">
                    <button type="submit" class="btn btn-primary">Guardar alterações</button>
                    <a href="/?page=profile" class="btn btn-outline">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
