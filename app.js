/**
 * contasLGS - Controlador Frontend SPA
 * Sincronizado com o index.php (Fase 3)
 */

// ==========================================
// ESTADO GLOBAL
// ==========================================
const state = {
    transactions: [],
    categories: null,
    stats: {},
    links: [],
    users: [],
    activeView: 'dashboard',
    chartInstance: null,
    debounceTimeout: null,
    pagination: { page: 1, per_page: 20, total: 0, total_pages: 1 },
    importData: null,
    alertsDismissed: false,
};

// ==========================================
// INICIALIZAÇÃO
// ==========================================
document.addEventListener('DOMContentLoaded', () => {
    initMonthSelector();
    setupNavigation();
    setupSidebar();
    setupModalCloseOnOverlay();
    setupFilterListeners();
    setupProfileMenu();

    // Abrir modal de nova transação pelo header
    const btnNew = document.getElementById('btnNewTransaction');
    if (btnNew) btnNew.addEventListener('click', () => openModal('modalTransaction'));

    // Botão novo link
    const btnNewLink = document.getElementById('btnNewLink');
    if (btnNewLink) btnNewLink.addEventListener('click', () => openLinkModal());

    // Botão novo usuário (admin)
    const btnNewUser = document.getElementById('btnNewUser');
    if (btnNewUser) btnNewUser.addEventListener('click', () => openUserModal());

    // Exportar / Importar CSV
    const btnExport = document.getElementById('btnExportCSV');
    if (btnExport) btnExport.addEventListener('click', exportCSV);

    const btnImport = document.getElementById('btnImportCSV');
    if (btnImport) btnImport.addEventListener('click', () => {
        document.getElementById('importPreviewContainer').style.display = 'none';
        document.getElementById('btnConfirmImport').style.display = 'none';
        state.importData = null;
        openModal('modalImport');
    });

    // Dropzone de importação
    setupImportDropzone();

    // Botão resetar filtros
    const btnReset = document.getElementById('btnResetFilters');
    if (btnReset) btnReset.addEventListener('click', resetFilters);

    // Alerta de pendências - fechar
    const btnDismiss = document.getElementById('btnDismissAlert');
    if (btnDismiss) btnDismiss.addEventListener('click', () => {
        document.getElementById('overdueAlertBanner').style.display = 'none';
        state.alertsDismissed = true;
    });

    // Formulário de transação - toggle recorrente
    const chkRecurring = document.getElementById('formRecurring');
    if (chkRecurring) chkRecurring.addEventListener('change', () => {
        const grp = document.getElementById('recurringDayGroup');
        if (grp) grp.style.display = chkRecurring.checked ? 'block' : 'none';
    });

    // Formulário de transação - troca de tipo atualiza categorias
    document.querySelectorAll('input[name="type"]').forEach(r => {
        r.addEventListener('change', updateFormCategories);
    });

    // Botão de perfil (editar)
    const btnEditProfile = document.getElementById('btnEditProfile');
    if (btnEditProfile) btnEditProfile.addEventListener('click', (e) => {
        e.preventDefault();
        closeProfileMenu();
        openProfileModal();
    });

    // Mês global
    const monthSel = document.getElementById('globalMonthSelector');
    if (monthSel) monthSel.addEventListener('change', () => {
        loadDashboardData();
        if (state.activeView === 'transactions') loadTransactions();
    });

    // Carregar dados iniciais
    navigateTo('dashboard');
    loadAlerts();
});

// ==========================================
// NAVEGAÇÃO / VIEWS
// ==========================================
function setupNavigation() {
    document.querySelectorAll('.nav-item').forEach(item => {
        item.addEventListener('click', () => {
            const view = item.dataset.view;
            if (view) navigateTo(view);
            // fechar sidebar no mobile
            document.getElementById('sidebar').classList.remove('open');
            document.getElementById('sidebarOverlay').classList.remove('active');
        });
    });
}

function navigateTo(view) {
    state.activeView = view;

    // Atualizar nav ativo
    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.toggle('active', item.dataset.view === view);
    });

    // Mostrar/esconder views
    document.querySelectorAll('.view-section').forEach(sec => {
        sec.style.display = 'none';
    });
    const target = document.getElementById(`view-${view}`);
    if (target) target.style.display = '';

    // Atualizar título do header
    const titles = {
        dashboard:    ['Dashboard', 'Acompanhamento financeiro em tempo real'],
        transactions: ['Transações', 'Visualize e gerencie todos os lançamentos'],
        links:        ['Links Úteis', 'Atalhos para portais e serviços financeiros'],
        admin:        ['Gestão de Usuários', 'Administração de contas do sistema'],
    };
    const [title, subtitle] = titles[view] || ['contasLGS', ''];
    const pageTitle = document.getElementById('pageTitle');
    const pageSub   = document.getElementById('pageSubtitle');
    if (pageTitle) pageTitle.textContent = title;
    if (pageSub)   pageSub.textContent   = subtitle;

    // Botão de nova transação visível apenas em dashboard e transactions
    const btnNew = document.getElementById('btnNewTransaction');
    if (btnNew) btnNew.style.display = ['dashboard','transactions'].includes(view) ? '' : 'none';

    // Carregar dados da view
    if (view === 'dashboard')    loadDashboardData();
    if (view === 'transactions') loadTransactions();
    if (view === 'links')        loadLinks();
    if (view === 'admin')        loadUsers();
}

// ==========================================
// SIDEBAR (MOBILE)
// ==========================================
function setupSidebar() {
    const btnHamburger = document.getElementById('btnHamburger');
    const btnClose     = document.getElementById('btnSidebarClose');
    const overlay      = document.getElementById('sidebarOverlay');
    const sidebar      = document.getElementById('sidebar');

    if (btnHamburger) btnHamburger.addEventListener('click', () => {
        sidebar.classList.add('open');
        overlay.classList.add('active');
    });
    if (btnClose) btnClose.addEventListener('click', () => {
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
    });
    if (overlay) overlay.addEventListener('click', () => {
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
    });
}

// ==========================================
// MENU DE PERFIL
// ==========================================
function setupProfileMenu() {
    const btn  = document.getElementById('userProfileBtn');
    const menu = document.getElementById('profileMenu');
    if (!btn || !menu) return;

    btn.addEventListener('click', (e) => {
        e.stopPropagation();
        menu.classList.toggle('active');
    });

    document.addEventListener('click', () => menu.classList.remove('active'));
}

function closeProfileMenu() {
    const menu = document.getElementById('profileMenu');
    if (menu) menu.classList.remove('active');
}

// ==========================================
// MÊS SELECTOR
// ==========================================
function initMonthSelector() {
    const sel = document.getElementById('globalMonthSelector');
    if (!sel) return;

    const now = new Date();
    const months = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];

    // Gera últimos 18 meses
    for (let i = 0; i < 18; i++) {
        const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
        const val = `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}`;
        const opt = document.createElement('option');
        opt.value = val;
        opt.textContent = `${months[d.getMonth()]} ${d.getFullYear()}`;
        if (i === 0) opt.selected = true;
        sel.appendChild(opt);
    }
}

function getSelectedMonth() {
    const sel = document.getElementById('globalMonthSelector');
    return sel ? sel.value : 'all';
}

// ==========================================
// DASHBOARD
// ==========================================
async function loadDashboardData() {
    const monthYear = getSelectedMonth();
    const params = new URLSearchParams({ route: 'transactions', month_year: monthYear, per_page: 100 });

    try {
        const res = await apiFetch('api.php?' + params);
        if (!res) return;

        state.transactions = res.transactions || [];
        state.stats        = res.stats        || {};

        if (!state.categories && res.categories) {
            state.categories = res.categories;
            populateCategoryFilter();
            updateFormCategories();
        }

        updateKPICards();
        renderCategoryBreakdown();
        renderChart(monthYear);
    } catch (e) {
        showToast('Erro ao carregar dados do dashboard.', 'error');
    }
}

function updateKPICards() {
    const s = state.stats;
    setEl('kpiBalance',       formatCurrency(s.balance));
    setEl('kpiIncomePaid',    formatCurrency(s.paid_income));
    setEl('kpiIncomePending', formatCurrency(s.pending_income));
    setEl('kpiExpensePaid',   formatCurrency(s.paid_expense));
    setEl('kpiExpensePending',formatCurrency(s.pending_expense));
    setEl('kpiTotalPending',  formatCurrency(s.pending_expense));

    // Cor do saldo
    const balEl = document.getElementById('kpiBalance');
    if (balEl) {
        balEl.style.color = s.balance >= 0 ? 'var(--color-income)' : 'var(--color-expense)';
    }
}

function renderCategoryBreakdown() {
    const container = document.getElementById('categoryBreakdownList');
    if (!container) return;

    const breakdown  = state.stats.category_breakdown || {};
    const total      = state.stats.total_expense || 0;
    const entries    = Object.entries(breakdown);

    if (entries.length === 0) {
        container.innerHTML = `
            <div style="text-align:center;padding:2rem;color:var(--text-muted);">
                <i class='bx bx-bar-chart-alt-2' style="font-size:2rem;"></i>
                <p style="margin-top:.5rem;font-size:.85rem;">Sem despesas neste período</p>
            </div>`;
        return;
    }

    container.innerHTML = entries.map(([name, amount]) => {
        const pct = total > 0 ? ((amount / total) * 100).toFixed(1) : 0;
        return `
            <div class="breakdown-item">
                <div class="breakdown-info">
                    <span class="breakdown-name">${name}</span>
                    <span class="breakdown-value">${formatCurrency(amount)} <span style="color:var(--text-muted);font-weight:normal;font-size:.75rem;">(${pct}%)</span></span>
                </div>
                <div class="progress-bar-bg">
                    <div class="progress-bar-fill" style="width:${pct}%"></div>
                </div>
            </div>`;
    }).join('');
}

function renderChart(monthYear) {
    const ctx = document.getElementById('expensesChart');
    if (!ctx) return;

    if (state.chartInstance) state.chartInstance.destroy();

    let labels = [], incomeData = [], expenseData = [];

    if (monthYear === 'all') {
        const monthly = {};
        state.transactions.forEach(t => {
            const m = t.date.substring(0, 7);
            if (!monthly[m]) monthly[m] = { income: 0, expense: 0 };
            if (t.type === 'Receita') monthly[m].income  += t.amount;
            else                      monthly[m].expense += t.amount;
        });
        const sorted = Object.keys(monthly).sort();
        const months = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
        labels      = sorted.map(m => { const [y,mm] = m.split('-'); return `${months[+mm-1]}/${y.slice(2)}`; });
        incomeData  = sorted.map(m => monthly[m].income);
        expenseData = sorted.map(m => monthly[m].expense);

        state.chartInstance = new Chart(ctx, {
            type: 'bar',
            data: { labels, datasets: [
                { label:'Receitas', data: incomeData,  backgroundColor:'rgba(16,185,129,.7)', borderRadius:6 },
                { label:'Despesas', data: expenseData, backgroundColor:'rgba(244,63,94,.7)',  borderRadius:6 },
            ]},
            options: chartOptions(false)
        });
    } else {
        const [year, month] = monthYear.split('-').map(Number);
        const days = new Date(year, month, 0).getDate();
        for (let d = 1; d <= days; d++) { labels.push(d); incomeData.push(0); expenseData.push(0); }
        state.transactions.forEach(t => {
            const day = parseInt(t.date.substring(8));
            if (t.date.substring(0,7) === monthYear && day <= days) {
                if (t.type === 'Receita') incomeData[day-1]  += t.amount;
                else                      expenseData[day-1] += t.amount;
            }
        });
        state.chartInstance = new Chart(ctx, {
            type: 'line',
            data: { labels, datasets: [
                { label:'Receitas', data: incomeData,  borderColor:'#10b981', backgroundColor:'rgba(16,185,129,.08)', fill:true, tension:.35, borderWidth:2, pointRadius:3 },
                { label:'Despesas', data: expenseData, borderColor:'#f43f5e', backgroundColor:'rgba(244,63,94,.08)',  fill:true, tension:.35, borderWidth:2, pointRadius:3 },
            ]},
            options: chartOptions(true)
        });
    }
}

function chartOptions(isLine) {
    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position:'top', labels:{ color:'#94a3b8', font:{ family:'Plus Jakarta Sans', size:11 }, boxWidth:10, usePointStyle:true }},
            tooltip: {
                backgroundColor:'#0f172a', titleColor:'#f8fafc', bodyColor:'#94a3b8',
                borderColor:'rgba(255,255,255,.08)', borderWidth:1, padding:12,
                callbacks: { label: ctx => ` ${ctx.dataset.label}: ${formatCurrency(ctx.raw)}` }
            }
        },
        scales: {
            y: { grid:{ color:'rgba(255,255,255,.04)' }, ticks:{ color:'#64748b', callback: v => 'R$ '+v, font:{ size:10 }}},
            x: { grid:{ display:false }, ticks:{ color:'#64748b', font:{ size:10 }}}
        }
    };
}

// ==========================================
// ALERTAS DE PENDÊNCIAS VENCIDAS
// ==========================================
async function loadAlerts() {
    if (state.alertsDismissed) return;
    try {
        const res = await apiFetch('api.php?route=alerts');
        if (!res) return;
        const banner = document.getElementById('overdueAlertBanner');
        if (res.overdue_count > 0 && banner) {
            setEl('overdueCountAlert', res.overdue_count);
            setEl('overdueTotalAlert', formatCurrency(res.overdue_total));
            banner.style.display = 'flex';
        }
        // Atualizar KPI de pendências
        setEl('kpiOverdueCount', `${res.overdue_count} pendência${res.overdue_count !== 1 ? 's' : ''} atrasada${res.overdue_count !== 1 ? 's' : ''}`);
    } catch (e) { /* silencioso */ }
}

// ==========================================
// TRANSAÇÕES (VIEW + CRUD)
// ==========================================
async function loadTransactions() {
    const monthYear = getSelectedMonth();
    const search    = (document.getElementById('filterSearch')   ?.value || '').trim();
    const type      = document.getElementById('filterType')      ?.value || '';
    const category  = document.getElementById('filterCategory')  ?.value || '';
    const status    = document.getElementById('filterStatus')    ?.value || '';
    const dateFrom  = document.getElementById('filterDateFrom')  ?.value || '';
    const dateTo    = document.getElementById('filterDateTo')    ?.value || '';
    const page      = state.pagination.page;

    const params = new URLSearchParams({ route:'transactions', page, per_page: state.pagination.per_page });
    if (monthYear !== 'all') params.set('month_year', monthYear);
    if (search)   params.set('search',    search);
    if (type)     params.set('type',      type);
    if (category) params.set('category',  category);
    if (status)   params.set('status',    status);
    if (dateFrom) params.set('date_from', dateFrom);
    if (dateTo)   params.set('date_to',   dateTo);

    const tbody = document.querySelector('#transactionsTable tbody');
    if (tbody) tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:2rem;color:var(--text-muted);"><i class='bx bx-loader-alt bx-spin'></i> Carregando...</td></tr>`;

    try {
        const res = await apiFetch('api.php?' + params);
        if (!res) return;

        if (!state.categories && res.categories) {
            state.categories = res.categories;
            populateCategoryFilter();
            updateFormCategories();
        }

        state.pagination = res.pagination || state.pagination;
        renderTransactionTable(res.transactions || []);
        renderPagination();
    } catch (e) {
        showToast('Erro ao carregar transações.', 'error');
    }
}

function renderTransactionTable(transactions) {
    const tbody = document.querySelector('#transactionsTable tbody');
    if (!tbody) return;

    if (transactions.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:2.5rem;color:var(--text-muted);"><i class='bx bx-search-alt' style="font-size:1.5rem;display:block;margin-bottom:.5rem;"></i>Nenhuma transação encontrada</td></tr>`;
        return;
    }

    tbody.innerHTML = transactions.map(t => {
        const isIncome   = t.type === 'Receita';
        const isPaid     = t.status === 'Pago';
        const amtClass   = isIncome ? 'color:var(--color-income)' : 'color:var(--color-expense)';
        const amtSign    = isIncome ? '+' : '-';
        const statusDot  = isPaid
            ? `<span style="display:inline-flex;align-items:center;gap:.35rem;color:var(--color-income);font-size:.78rem;font-weight:600;"><span style="width:6px;height:6px;border-radius:50%;background:var(--color-income);display:inline-block;"></span>Pago</span>`
            : `<span style="display:inline-flex;align-items:center;gap:.35rem;color:var(--color-pending);font-size:.78rem;font-weight:600;"><span style="width:6px;height:6px;border-radius:50%;background:var(--color-pending);display:inline-block;"></span>Pendente</span>`;
        const recurIcon  = t.recurring ? `<i class='bx bx-refresh' title="Recorrente" style="color:var(--color-primary);font-size:.85rem;margin-left:.25rem;"></i>` : '';

        return `<tr>
            <td>
                <div style="font-weight:600;color:var(--text-primary);font-size:.88rem;">${escHtml(t.description)}${recurIcon}</div>
                <div style="font-size:.75rem;color:var(--text-muted);margin-top:.1rem;">${escHtml(t.notes || '')}</div>
            </td>
            <td><span style="font-size:.8rem;background:rgba(99,102,241,.1);color:var(--color-primary);padding:.2rem .6rem;border-radius:20px;">${escHtml(t.category)}</span></td>
            <td style="${amtClass};font-weight:700;font-size:.92rem;">${amtSign} ${formatCurrency(t.amount)}</td>
            <td style="color:var(--text-secondary);font-size:.83rem;">${formatDate(t.date)}</td>
            <td>${statusDot}</td>
            <td style="text-align:right;">
                <button class="btn-icon" onclick="openTransactionModal(${t.id})" title="Editar" style="margin-right:.25rem;"><i class='bx bx-edit-alt'></i></button>
                <button class="btn-icon" onclick="deleteTransaction(${t.id})" title="Excluir" style="color:var(--color-expense);"><i class='bx bx-trash'></i></button>
            </td>
        </tr>`;
    }).join('');
}

function renderPagination() {
    const { page, total_pages, total, per_page } = state.pagination;
    const info    = document.getElementById('paginationInfo');
    const buttons = document.getElementById('paginationButtons');
    if (!info || !buttons) return;

    const from = total === 0 ? 0 : (page - 1) * per_page + 1;
    const to   = Math.min(page * per_page, total);
    info.textContent = `Mostrando ${from} - ${to} de ${total}`;

    buttons.innerHTML = '';

    if (total_pages <= 1) return;

    const addBtn = (label, targetPage, disabled = false, active = false) => {
        const btn = document.createElement('button');
        btn.className = `btn btn-sm btn-secondary${active ? ' btn-primary' : ''}`;
        btn.innerHTML = label;
        btn.disabled  = disabled;
        btn.style.minWidth = '36px';
        btn.addEventListener('click', () => { state.pagination.page = targetPage; loadTransactions(); });
        buttons.appendChild(btn);
    };

    addBtn('<i class="bx bx-chevron-left"></i>', page - 1, page === 1);

    const start = Math.max(1, page - 2);
    const end   = Math.min(total_pages, page + 2);
    for (let p = start; p <= end; p++) addBtn(p, p, false, p === page);

    addBtn('<i class="bx bx-chevron-right"></i>', page + 1, page === total_pages);
}

function setupFilterListeners() {
    ['filterSearch','filterType','filterCategory','filterStatus'].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('input',  () => debounce(triggerTransactionFilter));
        el.addEventListener('change', () => debounce(triggerTransactionFilter));
    });

    ['filterDateFrom','filterDateTo'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('change', () => debounce(triggerTransactionFilter));
    });
}

function triggerTransactionFilter() {
    state.pagination.page = 1;
    if (state.activeView === 'transactions') loadTransactions();
    else if (state.activeView === 'dashboard') loadDashboardData();
    // links/admin views não são afetados pelos filtros de transação
}

function resetFilters() {
    ['filterSearch','filterType','filterCategory','filterStatus','filterDateFrom','filterDateTo'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    state.pagination.page = 1;
    loadTransactions();
}

function populateCategoryFilter() {
    const sel = document.getElementById('filterCategory');
    if (!sel || !state.categories) return;

    const all = [...new Set([...state.categories['Receita'], ...state.categories['Despesa']])].sort();
    sel.innerHTML = '<option value="">Todas Categorias</option>' + all.map(c => `<option value="${c}">${c}</option>`).join('');
}

// ==========================================
// MODAL DE TRANSAÇÃO
// ==========================================
function openTransactionModal(id = null) {
    const form = document.getElementById('transactionForm');
    if (form) form.reset();

    setFormVal('formId', '');
    setFormVal('formDate', today());
    document.getElementById('recurringDayGroup').style.display = 'none';

    if (id !== null) {
        document.getElementById('modalTitle').textContent = 'Editar Transação';
        // Buscar da lista local (dashboard) ou fazer fetch
        const t = state.transactions.find(x => x.id === id);
        if (t) fillTransactionForm(t);
        else fetchAndFillTransaction(id);
    } else {
        document.getElementById('modalTitle').textContent = 'Nova Transação';
        // Default: Despesa
        const rdDespesa = document.getElementById('typeExpense');
        if (rdDespesa) rdDespesa.checked = true;
        updateFormCategories();
    }

    openModal('modalTransaction');
}

async function fetchAndFillTransaction(id) {
    try {
        const res = await apiFetch(`api.php?route=transactions&id=${id}`);
        if (res && res.transactions) {
            const t = res.transactions.find(x => x.id === id);
            if (t) fillTransactionForm(t);
        }
    } catch(e) { /* sem dados para preencher */ }
}

function fillTransactionForm(t) {
    setFormVal('formId',     t.id);
    setFormVal('formDesc',   t.description);
    setFormVal('formAmount', t.amount);
    setFormVal('formDate',   t.date);
    setFormVal('formStatus', t.status);
    setFormVal('formNotes',  t.notes || '');

    const rdIncome  = document.getElementById('typeIncome');
    const rdExpense = document.getElementById('typeExpense');
    if (t.type === 'Receita' && rdIncome)  rdIncome.checked  = true;
    else if (rdExpense)                    rdExpense.checked  = true;

    updateFormCategories();
    setFormVal('formCategory', t.category);

    const chkRec = document.getElementById('formRecurring');
    if (chkRec) {
        chkRec.checked = !!t.recurring;
        document.getElementById('recurringDayGroup').style.display = t.recurring ? 'block' : 'none';
        if (t.recurring_day) setFormVal('formRecurringDay', t.recurring_day);
    }
}

function updateFormCategories() {
    const rdIncome = document.getElementById('typeIncome');
    const type     = rdIncome && rdIncome.checked ? 'Receita' : 'Despesa';
    const sel      = document.getElementById('formCategory');
    if (!sel || !state.categories) return;

    sel.innerHTML = (state.categories[type] || []).map(c => `<option value="${c}">${c}</option>`).join('');
}

async function saveTransaction() {
    const id          = document.getElementById('formId').value;
    const rdIncome    = document.getElementById('typeIncome');
    const type        = rdIncome && rdIncome.checked ? 'Receita' : 'Despesa';
    const description = document.getElementById('formDesc').value.trim();
    const amount      = parseFloat(document.getElementById('formAmount').value);
    const date        = document.getElementById('formDate').value;
    const category    = document.getElementById('formCategory').value;
    const status      = document.getElementById('formStatus').value;
    const notes       = document.getElementById('formNotes').value.trim();
    const chkRec      = document.getElementById('formRecurring');
    const recurring   = chkRec ? chkRec.checked : false;
    const recurringDay= recurring ? (parseInt(document.getElementById('formRecurringDay').value) || null) : null;

    if (!description) return showToast('Descrição é obrigatória.', 'error');
    if (!amount || amount <= 0) return showToast('Informe um valor válido.', 'error');
    if (!date) return showToast('Informe a data.', 'error');

    const payload = { description, amount, date, category, type, status, notes, recurring, recurring_day: recurringDay };
    if (id) payload.id = parseInt(id);

    try {
        const res = await apiFetch('api.php', { method:'POST', body: JSON.stringify(payload) });
        if (res && res.success) {
            showToast(res.message || 'Salvo com sucesso!', 'success');
            closeModal('modalTransaction');
            refreshCurrentView();
            loadAlerts();
        }
    } catch (e) {
        showToast('Erro ao salvar transação.', 'error');
    }
}

async function deleteTransaction(id) {
    if (!confirm('Deseja realmente excluir esta transação?')) return;
    try {
        const res = await apiFetch(`api.php?id=${id}`, { method:'DELETE' });
        if (res && res.success) {
            showToast('Transação excluída.', 'success');
            refreshCurrentView();
            loadAlerts();
        }
    } catch (e) {
        showToast('Erro ao excluir.', 'error');
    }
}

// ==========================================
// LINKS ÚTEIS
// ==========================================
async function loadLinks() {
    const container = document.getElementById('linksContainer');
    if (!container) return;
    container.innerHTML = `<div style="text-align:center;padding:2rem;color:var(--text-muted);"><i class='bx bx-loader-alt bx-spin'></i> Carregando...</div>`;

    try {
        const res = await apiFetch('api.php?route=links');
        if (!res) return;
        state.links = res.links || [];
        renderLinks();
    } catch (e) {
        showToast('Erro ao carregar links.', 'error');
    }
}

function renderLinks() {
    const container = document.getElementById('linksContainer');
    if (!container) return;

    if (state.links.length === 0) {
        container.innerHTML = `
            <div style="text-align:center;padding:3rem;color:var(--text-muted);width:100%;grid-column:1/-1;">
                <i class='bx bx-link-alt' style="font-size:3rem;display:block;margin-bottom:.75rem;"></i>
                <p>Nenhum link cadastrado ainda.</p>
                <p style="font-size:.8rem;margin-top:.25rem;">Clique em "Adicionar Link" para começar.</p>
            </div>`;
        return;
    }

    // Agrupar por categoria
    const groups = {};
    state.links.forEach(l => { if (!groups[l.category]) groups[l.category] = []; groups[l.category].push(l); });

    container.innerHTML = '';
    Object.entries(groups).forEach(([cat, links]) => {
        const section = document.createElement('div');
        section.style.cssText = 'grid-column:1/-1;';
        section.innerHTML = `<h3 style="font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--text-muted);margin-bottom:.75rem;display:flex;align-items:center;gap:.5rem;"><i class='bx bx-folder-open'></i>${cat}</h3>`;

        const grid = document.createElement('div');
        grid.style.cssText = 'display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:1rem;margin-bottom:1.5rem;';
        links.forEach(l => {
            const card = document.createElement('div');
            card.className = 'link-card';
            card.innerHTML = `
                <div class="link-card-header">
                    <div class="link-card-icon"><i class='bx bx-link-alt'></i></div>
                    <div>
                        <div class="link-card-title">${escHtml(l.name)}</div>
                        <div style="font-size:.72rem;color:var(--text-muted);">${escHtml(l.category)}</div>
                    </div>
                </div>
                <div class="link-card-footer">
                    <a href="${escHtml(l.url)}" target="_blank" rel="noopener" class="btn btn-primary btn-sm" style="flex:1;text-align:center;">
                        <i class='bx bx-link-external'></i> Acessar
                    </a>
                    <button class="btn-icon" onclick="openLinkModal(${l.id})" title="Editar"><i class='bx bx-edit-alt'></i></button>
                    <button class="btn-icon" onclick="deleteLink(${l.id})" title="Excluir" style="color:var(--color-expense);"><i class='bx bx-trash'></i></button>
                </div>`;
            grid.appendChild(card);
        });
        section.appendChild(grid);
        container.appendChild(section);
    });
}

function openLinkModal(id = null) {
    const form = document.getElementById('linkForm');
    if (form) form.reset();
    setFormVal('formLinkId', '');

    if (id !== null) {
        document.getElementById('modalLinkTitle').textContent = 'Editar Link';
        const l = state.links.find(x => x.id === id);
        if (l) {
            setFormVal('formLinkId',       l.id);
            setFormVal('formLinkName',     l.name);
            setFormVal('formLinkUrl',      l.url);
            setFormVal('formLinkCategory', l.category);
        }
    } else {
        document.getElementById('modalLinkTitle').textContent = 'Novo Link';
    }
    openModal('modalLink');
}

async function saveLink() {
    const id       = document.getElementById('formLinkId').value;
    const name     = document.getElementById('formLinkName').value.trim();
    const url      = document.getElementById('formLinkUrl').value.trim();
    const category = document.getElementById('formLinkCategory').value;

    if (!name) return showToast('Nome obrigatório.', 'error');
    if (!url)  return showToast('URL obrigatória.', 'error');

    const payload = { name, url, category };
    if (id) payload.id = parseInt(id);

    try {
        const res = await apiFetch('api.php?route=links', { method:'POST', body: JSON.stringify(payload) });
        if (res && res.success) {
            showToast(res.message || 'Link salvo!', 'success');
            closeModal('modalLink');
            loadLinks();
        }
    } catch (e) {
        showToast('Erro ao salvar link.', 'error');
    }
}

async function deleteLink(id) {
    if (!confirm('Remover este link?')) return;
    try {
        const res = await apiFetch(`api.php?route=links&id=${id}`, { method:'DELETE' });
        if (res && res.success) { showToast('Link removido.', 'success'); loadLinks(); }
    } catch (e) { showToast('Erro ao remover.', 'error'); }
}

// ==========================================
// GESTÃO DE USUÁRIOS (ADMIN)
// ==========================================
async function loadUsers() {
    const tbody = document.querySelector('#usersTable tbody');
    if (!tbody) return;
    tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:2rem;"><i class='bx bx-loader-alt bx-spin'></i></td></tr>`;

    try {
        const res = await apiFetch('api.php?route=users');
        if (!res) return;
        state.users = res.users || [];
        renderUsersTable();
    } catch (e) {
        showToast('Erro ao carregar usuários.', 'error');
    }
}

function renderUsersTable() {
    const tbody = document.querySelector('#usersTable tbody');
    if (!tbody) return;

    const currentUserId = app.currentUser?.id || 0;

    tbody.innerHTML = state.users.map(u => {
        const isSelf = u.id === currentUserId;
        const roleBadge = u.role === 'admin'
            ? `<span style="background:rgba(99,102,241,.15);color:var(--color-primary);padding:.2rem .6rem;border-radius:20px;font-size:.75rem;font-weight:600;">Admin</span>`
            : `<span style="background:rgba(100,116,139,.12);color:var(--text-secondary);padding:.2rem .6rem;border-radius:20px;font-size:.75rem;">Usuário</span>`;
        return `<tr>
            <td style="font-weight:600;color:var(--text-primary);">${escHtml(u.name)}</td>
            <td style="color:var(--text-secondary);font-size:.85rem;">@${escHtml(u.username)}</td>
            <td style="color:var(--text-secondary);font-size:.85rem;">${escHtml(u.email || '-')}</td>
            <td>${roleBadge}</td>
            <td style="color:var(--text-muted);font-size:.8rem;">${formatDate(u.created_at?.substring(0,10))}</td>
            <td style="text-align:right;">
                <button class="btn-icon" onclick="openUserModal(${u.id})" title="Editar"><i class='bx bx-edit-alt'></i></button>
                ${isSelf ? '' : `<button class="btn-icon" onclick="deleteUser(${u.id})" title="Excluir" style="color:var(--color-expense);margin-left:.25rem;"><i class='bx bx-trash'></i></button>`}
            </td>
        </tr>`;
    }).join('');
}

function openUserModal(id = null) {
    const form = document.getElementById('userForm');
    if (form) form.reset();
    setFormVal('formUserId', '');

    const helpEl = document.getElementById('userPasswordHelp');

    if (id !== null) {
        document.getElementById('modalUserTitle').textContent = 'Editar Usuário';
        const u = state.users.find(x => x.id === id);
        if (u) {
            setFormVal('formUserId',       u.id);
            setFormVal('formUserName',     u.name);
            setFormVal('formUserUsername', u.username);
            setFormVal('formUserRole',     u.role);
            setFormVal('formUserEmail',    u.email || '');
        }
        if (helpEl) helpEl.textContent = '(deixe em branco para não alterar)';
    } else {
        document.getElementById('modalUserTitle').textContent = 'Novo Usuário';
        if (helpEl) helpEl.textContent = '(obrigatória)';
    }
    openModal('modalUser');
}

async function saveUser() {
    const id       = document.getElementById('formUserId').value;
    const name     = document.getElementById('formUserName').value.trim();
    const username = document.getElementById('formUserUsername').value.trim();
    const role     = document.getElementById('formUserRole').value;
    const email    = document.getElementById('formUserEmail').value.trim();
    const password = document.getElementById('formUserPassword').value;

    if (!name)     return showToast('Nome obrigatório.', 'error');
    if (!username) return showToast('Username obrigatório.', 'error');

    const payload = { name, username, role, email };
    if (id)       payload.id = parseInt(id);
    if (password) payload.password = password;
    if (!id && !password) return showToast('Senha obrigatória para novo usuário.', 'error');

    try {
        const method = id ? 'PUT' : 'POST';
        const res = await apiFetch('api.php?route=users', { method, body: JSON.stringify(payload) });
        if (res && res.success) {
            showToast(res.message || 'Salvo!', 'success');
            closeModal('modalUser');
            loadUsers();
        }
    } catch (e) {
        showToast('Erro ao salvar usuário.', 'error');
    }
}

async function deleteUser(id) {
    if (!confirm('Excluir este usuário?')) return;
    try {
        const res = await apiFetch(`api.php?route=users&id=${id}`, { method:'DELETE' });
        if (res && res.success) { showToast('Usuário excluído.', 'success'); loadUsers(); }
    } catch (e) { showToast('Erro ao excluir.', 'error'); }
}

// ==========================================
// MEU PERFIL
// ==========================================
function openProfileModal() {
    const form = document.getElementById('profileForm');
    if (form) form.reset();

    // Preencher nome e email da sessão (via elementos do sidebar)
    const nameEl = document.getElementById('displayUserName');
    if (nameEl) setFormVal('profileName', nameEl.textContent.trim());

    openModal('modalProfile');
}

async function saveProfile() {
    const name            = document.getElementById('profileName').value.trim();
    const email           = document.getElementById('profileEmail').value.trim();
    const currentPassword = document.getElementById('profileCurrentPassword').value;
    const newPassword     = document.getElementById('profileNewPassword').value;

    const payload = {};
    if (name)            payload.name  = name;
    if (email)           payload.email = email;
    if (newPassword) {
        if (!currentPassword) return showToast('Informe a senha atual.', 'error');
        payload.current_password = currentPassword;
        payload.new_password     = newPassword;
    }

    if (Object.keys(payload).length === 0) return showToast('Nada para atualizar.', 'error');

    try {
        const res = await apiFetch('api.php?route=profile', { method:'PUT', body: JSON.stringify(payload) });
        if (res && res.success) {
            showToast(res.message || 'Perfil atualizado!', 'success');
            closeModal('modalProfile');
            // Atualizar nome no sidebar
            if (name) {
                const el = document.getElementById('displayUserName');
                if (el) el.textContent = name;
            }
        }
    } catch (e) {
        showToast('Erro ao salvar perfil.', 'error');
    }
}

// ==========================================
// EXPORTAÇÃO CSV
// ==========================================
function exportCSV() {
    const monthYear = getSelectedMonth();
    const type      = document.getElementById('filterType')?.value     || '';
    const category  = document.getElementById('filterCategory')?.value  || '';
    const status    = document.getElementById('filterStatus')?.value    || '';
    const dateFrom  = document.getElementById('filterDateFrom')?.value  || '';
    const dateTo    = document.getElementById('filterDateTo')?.value    || '';
    const search    = (document.getElementById('filterSearch')?.value   || '').trim().toLowerCase();

    // Busca todas as transações do usuário no localStorage (sem paginação)
    let txs = db.get('transactions').filter(t => t.user_id === app.currentUser.id);

    // Aplica os mesmos filtros ativos na tela
    if (monthYear !== 'all') txs = txs.filter(t => t.date.startsWith(monthYear));
    if (type)     txs = txs.filter(t => t.type     === type);
    if (category) txs = txs.filter(t => t.category === category);
    if (status)   txs = txs.filter(t => t.status   === status);
    if (dateFrom) txs = txs.filter(t => t.date     >= dateFrom);
    if (dateTo)   txs = txs.filter(t => t.date     <= dateTo);
    if (search)   txs = txs.filter(t =>
        (t.description || '').toLowerCase().includes(search) ||
        (t.notes       || '').toLowerCase().includes(search)
    );

    // Ordena por data crescente
    txs.sort((a, b) => a.date.localeCompare(b.date));

    if (txs.length === 0) return showToast('Nenhum dado para exportar.', 'error');

    // BOM (\uFEFF) garante acentos corretos ao abrir no Excel
    let csvContent = "data:text/csv;charset=utf-8,\uFEFF";
    csvContent += "Descrição;Valor;Data;Categoria;Tipo;Status;Observações\n";

    const escCsv = (v) => `"${String(v || '').replace(/"/g, '""')}"`;

    txs.forEach(t => {
        const row = [
            escCsv(t.description),
            t.amount,
            t.date,
            escCsv(t.category),
            t.type,
            t.status,
            escCsv(t.notes || ''),
        ];
        csvContent += row.join(';') + '\n';
    });

    const encodedUri = encodeURI(csvContent);
    const link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    const dateTag = new Date().toISOString().split('T')[0];
    link.setAttribute('download', `contasLGS_export_${dateTag}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    showToast(`${txs.length} transação(ões) exportada(s)!`, 'success');
}

// ==========================================
// IMPORTAÇÃO CSV
// ==========================================
function setupImportDropzone() {
    const dropzone  = document.getElementById('importDropzone');
    const fileInput = document.getElementById('fileImportCsv');
    if (!dropzone || !fileInput) return;

    dropzone.addEventListener('click', () => fileInput.click());
    dropzone.addEventListener('dragover', e => { e.preventDefault(); dropzone.classList.add('drag-over'); });
    dropzone.addEventListener('dragleave', () => dropzone.classList.remove('drag-over'));
    dropzone.addEventListener('drop', e => {
        e.preventDefault();
        dropzone.classList.remove('drag-over');
        if (e.dataTransfer.files[0]) parseImportFile(e.dataTransfer.files[0]);
    });
    fileInput.addEventListener('change', () => { if (fileInput.files[0]) parseImportFile(fileInput.files[0]); });
}

function parseImportFile(file) {
    const reader = new FileReader();
    reader.onload = e => {
        const text = e.target.result;
        const rows = parseCsv(text);
        if (rows.length < 2) { showToast('Arquivo CSV inválido ou vazio.', 'error'); return; }

        const headers = rows[0].map(h => h.trim().toLowerCase());
        const colMap  = { description:-1, amount:-1, date:-1, category:-1, type:-1, status:-1, notes:-1 };
        const aliases = {
            description:['descrição','descricao','description'],
            amount:['valor','amount','value'],
            date:['data','date'],
            category:['categoria','category'],
            type:['tipo','type'],
            status:['status','situação'],
            notes:['observações','observacoes','notas','notes'],
        };
        Object.entries(aliases).forEach(([key, names]) => {
            const idx = headers.findIndex(h => names.some(n => h.includes(n)));
            if (idx !== -1) colMap[key] = idx;
        });

        const data = rows.slice(1).filter(r => r.some(c => c.trim())).map(row => {
            const obj = {};
            Object.entries(colMap).forEach(([key, idx]) => {
                obj[key] = idx >= 0 ? (row[idx] || '').trim() : '';
            });
            // Normalizar valor (trocar vírgula por ponto)
            obj.amount = obj.amount.replace(',','.');
            return obj;
        });

        state.importData = data;

        // Preview
        const previewTable = document.getElementById('importPreviewTable');
        if (previewTable) {
            const previewRows = data.slice(0, 3);
            previewTable.innerHTML = `
                <thead><tr>${Object.keys(colMap).map(k => `<th>${k}</th>`).join('')}</tr></thead>
                <tbody>${previewRows.map(r => `<tr>${Object.values(r).map(v => `<td>${escHtml(v)}</td>`).join('')}</tr>`).join('')}</tbody>`;
        }
        document.getElementById('importPreviewContainer').style.display = 'block';
        document.getElementById('btnConfirmImport').style.display = 'inline-flex';
    };
    reader.readAsText(file, 'UTF-8');
}

function parseCsv(text) {
    // Suporta ; e ,
    const sep    = text.includes(';') ? ';' : ',';
    const lines  = text.replace(/\r\n/g,'\n').replace(/\r/g,'\n').split('\n');
    return lines.map(line => {
        const result = []; let cell = ''; let inQ = false;
        for (let i = 0; i < line.length; i++) {
            const ch = line[i];
            if (ch === '"') { inQ = !inQ; }
            else if (ch === sep && !inQ) { result.push(cell); cell = ''; }
            else { cell += ch; }
        }
        result.push(cell);
        return result;
    }).filter(r => r.length > 0);
}

async function processImport() {
    if (!state.importData || state.importData.length === 0) return showToast('Nenhum dado para importar.', 'error');

    const btn = document.getElementById('btnConfirmImport');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Importando...'; }

    try {
        const res = await apiFetch('api.php?route=import', { method:'POST', body: JSON.stringify({ rows: state.importData }) });
        if (res && res.success) {
            showToast(`${res.imported} transações importadas!`, 'success');
            if (res.errors?.length > 0) {
                setTimeout(() => showToast(`${res.errors.length} linha(s) com erro.`, 'error'), 600);
            }
            closeModal('modalImport');
            refreshCurrentView();
            loadAlerts();
        }
    } catch (e) {
        showToast('Erro ao importar.', 'error');
    } finally {
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bx bx-check"></i> Confirmar Importação'; }
    }
}

// ==========================================
// UTILITÁRIOS
// ==========================================
// apiFetch was moved to localdb.js

function openModal(id) {
    const el = document.getElementById(id);
    if (el) { el.style.display = 'flex'; requestAnimationFrame(() => el.classList.add('active')); }
}

function closeModal(id) {
    const el = document.getElementById(id);
    if (el) { el.classList.remove('active'); setTimeout(() => { if (!el.classList.contains('active')) el.style.display = ''; }, 300); }
}

function setupModalCloseOnOverlay() {
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', e => {
            if (e.target === overlay) closeModal(overlay.id);
        });
    });
    // Fechar com Escape
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.active').forEach(m => closeModal(m.id));
        }
    });
}

function refreshCurrentView() {
    if (state.activeView === 'dashboard')    { loadDashboardData(); }
    if (state.activeView === 'transactions') { loadTransactions(); }
}

function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    const icon = type === 'success' ? 'bx-check-circle' : 'bx-x-circle';
    toast.innerHTML = `<i class="bx ${icon}" style="font-size:1.1rem;"></i><span>${message}</span>`;
    container.appendChild(toast);

    requestAnimationFrame(() => toast.classList.add('show'));
    setTimeout(() => { toast.classList.remove('show'); setTimeout(() => toast.remove(), 350); }, 4000);
}

function formatCurrency(value) {
    return new Intl.NumberFormat('pt-BR', { style:'currency', currency:'BRL' }).format(value || 0);
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    const [y, m, d] = dateStr.split('-');
    return `${d}/${m}/${y}`;
}

function today() {
    return new Date().toISOString().split('T')[0];
}

function setEl(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = value;
}

function setFormVal(id, value) {
    const el = document.getElementById(id);
    if (el) el.value = value ?? '';
}

function escHtml(str) {
    return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function debounce(fn, ms = 350) {
    clearTimeout(state.debounceTimeout);
    state.debounceTimeout = setTimeout(fn, ms);
}
