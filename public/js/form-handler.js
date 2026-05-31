document.addEventListener('DOMContentLoaded', () => {
    initAjaxForms();
    initEnrollButtons();
    initEquipmentRefresh();
    initProfilePhotoPreview();
    initFilterForms();
});

function initAjaxForms() {
    document.querySelectorAll('form[data-ajax]').forEach(form => {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn      = form.querySelector('[type="submit"]');
            const original = btn?.textContent;
            if (btn) { btn.disabled = true; btn.textContent = 'A processar...'; }

            clearFormErrors(form);

            try {
                const fd   = new FormData(form);
                const resp = await fetch(form.action || window.location.href, {
                    method:  form.method || 'POST',
                    body:    fd,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await resp.json();

                if (data.success) {
                    showToast(data.message || 'Operação realizada com sucesso.', 'success');
                    if (data.redirect) {
                        setTimeout(() => { window.location.href = data.redirect; }, 800);
                    } else if (form.dataset.reset !== 'false') {
                        form.reset();
                    }
                    form.dispatchEvent(new CustomEvent('ajax:success', { detail: data }));
                } else {
                    if (data.errors) {
                        showFormErrors(form, data.errors);
                    }
                    showToast(data.message || 'Ocorreu um erro.', 'danger');
                }
            } catch (err) {
                showToast('Erro de comunicação com o servidor.', 'danger');
                console.error(err);
            } finally {
                if (btn) { btn.disabled = false; btn.textContent = original; }
            }
        });
    });
}

function clearFormErrors(form) {
    form.querySelectorAll('.form-error').forEach(el => el.remove());
    form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
}

function showFormErrors(form, errors) {
    Object.entries(errors).forEach(([field, message]) => {
        const input = form.querySelector(`[name="${field}"]`);
        if (!input) return;
        input.classList.add('is-invalid');
        const err = document.createElement('div');
        err.className = 'form-error';
        err.textContent = message;
        input.parentNode.appendChild(err);
    });
}

function initEnrollButtons() {
    document.querySelectorAll('[data-enroll]').forEach(btn => {
        btn.addEventListener('click', async () => {
            const scheduleId = btn.dataset.enroll;
            const action     = btn.dataset.action;
            const csrfToken  = document.querySelector('input[name="csrf_token"]')?.value || '';

            btn.disabled = true;

            try {
                const data = await apiFetch('/api/classes.php', {
                    method: 'POST',
                    body: JSON.stringify({ action, schedule_id: scheduleId, csrf_token: csrfToken }),
                });

                if (data.success) {
                    showToast(data.message, 'success');
                    const countEl = document.getElementById(`count-${scheduleId}`);
                    if (countEl && data.enrolled_count !== undefined) {
                        countEl.textContent = data.enrolled_count;
                    }
                    if (action === 'enroll') {
                        btn.textContent    = 'Cancelar inscrição';
                        btn.dataset.action = 'cancel';
                        btn.classList.replace('btn-primary', 'btn-outline');
                    } else {
                        btn.textContent    = 'Inscrever-me';
                        btn.dataset.action = 'enroll';
                        btn.classList.replace('btn-outline', 'btn-primary');
                    }
                } else {
                    showToast(data.message || 'Erro na operação.', 'danger');
                }
            } catch (err) {
                showToast('Erro de comunicação.', 'danger');
            } finally {
                btn.disabled = false;
            }
        });
    });
}

function initEquipmentRefresh() {
    const refreshBtn = document.getElementById('refreshEquipment');
    if (!refreshBtn) return;

    async function loadEquipment() {
        const grid = document.getElementById('equipmentGrid');
        if (!grid) return;
        try {
            const data = await apiFetch('/api/equipment.php?action=list');
            if (data.equipment) {
                renderEquipmentGrid(grid, data.equipment);
            }
        } catch (e) {
            console.error('Erro ao atualizar equipamento:', e);
        }
    }

    refreshBtn.addEventListener('click', () => {
        const spinner = refreshBtn.querySelector('.loading-spinner');
        if (spinner) spinner.classList.remove('hidden');
        loadEquipment().finally(() => {
            if (spinner) spinner.classList.add('hidden');
        });
    });

    let autoRefresh;
    const autoEl = document.getElementById('autoRefreshToggle');
    if (autoEl) {
        autoEl.addEventListener('change', () => {
            if (autoEl.checked) {
                autoRefresh = setInterval(loadEquipment, 30000);
            } else {
                clearInterval(autoRefresh);
            }
        });
    }
}

function renderEquipmentGrid(container, items) {
    container.innerHTML = items.map(item => {
        const pct = item.total_quantity > 0
            ? Math.round((item.available_quantity / item.total_quantity) * 100)
            : 0;
        const statusClass = {
            disponivel:  'status-available',
            em_uso:      'status-in-use',
            manutencao:  'status-maintenance',
            inativo:     'status-inactive',
        }[item.status] || '';
        const statusLabel = {
            disponivel:  'Disponível',
            em_uso:      'Em Uso',
            manutencao:  'Manutenção',
            inativo:     'Inativo',
        }[item.status] || item.status;

        return `
            <div class="card">
                <div class="card-body">
                    <div class="flex justify-between items-center mb-2">
                        <h4 class="card-title" style="margin:0">${escapeHtml(item.name)}</h4>
                        <span class="badge badge-muted">${escapeHtml(item.category)}</span>
                    </div>
                    <p class="text-muted" style="font-size:0.85rem;margin-bottom:0.75rem">${escapeHtml(item.description || '')}</p>
                    <div class="flex justify-between" style="font-size:0.85rem;margin-bottom:0.5rem">
                        <span class="${statusClass} font-weight-600">${statusLabel}</span>
                        <span class="text-muted">${item.available_quantity}/${item.total_quantity}</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width:${pct}%"></div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function initProfilePhotoPreview() {
    const input   = document.getElementById('profilePhotoInput');
    const preview = document.getElementById('profilePhotoPreview');
    if (!input || !preview) return;

    input.addEventListener('change', () => {
        const file = input.files[0];
        if (!file) return;
        if (!file.type.startsWith('image/')) {
            showToast('Por favor selecione uma imagem.', 'warning');
            input.value = '';
            return;
        }
        if (file.size > 2 * 1024 * 1024) {
            showToast('A imagem não pode ultrapassar 2MB.', 'warning');
            input.value = '';
            return;
        }
        const reader = new FileReader();
        reader.onload = (e) => { preview.src = e.target.result; };
        reader.readAsDataURL(file);
    });
}

function initFilterForms() {
    document.querySelectorAll('[data-filter-auto]').forEach(select => {
        select.addEventListener('change', () => {
            select.closest('form')?.submit();
        });
    });
}
