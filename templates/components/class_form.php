<?php $prefix = isset($edit_mode) && $edit_mode ? 'edit' : ''; ?>
<div class="form-group">
    <label class="form-label">Nome *</label>
    <input type="text" name="name" id="<?= $prefix ?>ClassName" class="form-control" required>
</div>
<div class="form-group">
    <label class="form-label">Tipo *</label>
    <input type="text" name="type" id="<?= $prefix ?>ClassType" class="form-control" required placeholder="Ex: Yoga, HIIT, Pilates, Spinning">
</div>
<div class="form-group">
    <label class="form-label">Descrição</label>
    <textarea name="description" id="<?= $prefix ?>ClassDescription" class="form-control" rows="3"></textarea>
</div>
<div class="form-group">
    <label class="form-label">Treinador</label>
    <select name="trainer_id" id="<?= $prefix ?>ClassTrainer" class="form-control">
        <option value="">Sem treinador atribuído</option>
        <?php foreach ($trainers as $tr): ?>
            <option value="<?= $tr['id'] ?>"><?= e($tr['name']) ?></option>
        <?php endforeach; ?>
    </select>
</div>
<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem">
    <div class="form-group">
        <label class="form-label">Capacidade</label>
        <input type="number" name="capacity" id="<?= $prefix ?>ClassCapacity" class="form-control" value="20" min="1" required>
    </div>
    <div class="form-group">
        <label class="form-label">Duração (min)</label>
        <input type="number" name="duration_minutes" id="<?= $prefix ?>ClassDuration" class="form-control" value="60" min="15" required>
    </div>
    <div class="form-group">
        <label class="form-label">Local</label>
        <input type="text" name="location" id="<?= $prefix ?>ClassLocation" class="form-control" value="Sala Principal">
    </div>
</div>
