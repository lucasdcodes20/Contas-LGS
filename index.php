<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
require_once __DIR__ . '/config.php';
$isAdmin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
$userName = $_SESSION['user_name'] ?? 'Usuário';
$userInitials = strtoupper(substr($userName, 0, 2));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>contasLGS - Dashboard Financeiro</title>
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<div class="app-container">
    
    <!-- OVERLAY MOBILE -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">
        <div class="brand-section">
            <div class="brand">
                <div class="brand-icon"><i class='bx bx-wallet-alt'></i></div>
                <div>
                    <div>contasLGS</div>
                    <div class="brand-subtitle">Financeiro</div>
                </div>
            </div>
            <button class="btn-sidebar-close" id="btnSidebarClose"><i class='bx bx-x'></i></button>
        </div>

        <ul class="nav-menu">
            <li class="nav-item active" data-view="dashboard">
                <a href="#dashboard"><i class='bx bx-grid-alt'></i> Dashboard</a>
            </li>
            <li class="nav-item" data-view="transactions">
                <a href="#transactions"><i class='bx bx-list-ul'></i> Transações</a>
            </li>
            <li class="nav-item" data-view="links">
                <a href="#links"><i class='bx bx-link-alt'></i> Links Úteis</a>
            </li>
            <?php if ($isAdmin): ?>
            <li class="nav-item" data-view="admin">
                <a href="#admin"><i class='bx bx-shield-quarter'></i> Gestão de Usuários</a>
            </li>
            <?php endif; ?>
        </ul>

        <div class="sidebar-footer">
            <div class="profile-dropdown">
                <div class="user-profile" id="userProfileBtn">
                    <div class="avatar"><?= $userInitials ?></div>
                    <div class="user-info">
                        <span class="user-name" id="displayUserName"><?= htmlspecialchars($userName) ?></span>
                        <span class="user-role"><?= $isAdmin ? 'Administrador' : 'Usuário' ?></span>
                    </div>
                    <i class='bx bx-chevron-up' style="margin-left: auto; color: var(--text-muted)"></i>
                </div>
                
                <div class="profile-menu" id="profileMenu">
                    <a href="#" class="profile-menu-item" id="btnEditProfile"><i class='bx bx-user-circle'></i> Meu Perfil</a>
                    <div class="profile-menu-divider"></div>
                    <a href="logout.php" class="profile-menu-item danger"><i class='bx bx-log-out'></i> Sair do Sistema</a>
                </div>
            </div>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main-content">
        <!-- HEADER -->
        <header class="header">
            <div class="header-left">
                <button class="btn-hamburger" id="btnHamburger"><i class='bx bx-menu'></i></button>
                <div class="header-title">
                    <h1 id="pageTitle">Dashboard</h1>
                    <p id="pageSubtitle">Acompanhamento financeiro em tempo real</p>
                </div>
            </div>
            
            <div class="header-actions">
                <div class="alert-banner" id="overdueAlertBanner">
                    <i class='bx bx-error-circle alert-banner-icon'></i>
                    <div class="alert-banner-text">
                        Você tem <strong id="overdueCountAlert">0</strong> pendências atrasadas totalizando <strong id="overdueTotalAlert">R$ 0,00</strong>.
                    </div>
                    <button class="alert-banner-close" id="btnDismissAlert"><i class='bx bx-x'></i></button>
                </div>

                <select id="globalMonthSelector" class="month-selector">
                    <option value="all">Todo o Período</option>
                    <!-- Options preenchidas via JS -->
                </select>
                <button class="btn btn-primary" id="btnNewTransaction"><i class='bx bx-plus'></i> Nova Transação</button>
            </div>
        </header>

        <!-- VIEW: DASHBOARD -->
        <div id="view-dashboard" class="view-section">
            <div class="kpi-grid">
                <div class="kpi-card kpi-balance">
                    <div class="kpi-header">
                        <span class="kpi-title">Saldo Líquido</span>
                        <div class="kpi-icon"><i class='bx bx-wallet'></i></div>
                    </div>
                    <div class="kpi-value" id="kpiBalance">R$ 0,00</div>
                    <div class="kpi-footer">Considera recebimentos e pagamentos</div>
                </div>
                
                <div class="kpi-card kpi-income">
                    <div class="kpi-header">
                        <span class="kpi-title">Receitas Pagas</span>
                        <div class="kpi-icon"><i class='bx bx-trending-up'></i></div>
                    </div>
                    <div class="kpi-value" id="kpiIncomePaid">R$ 0,00</div>
                    <div class="kpi-footer">Pendente: <span id="kpiIncomePending">R$ 0,00</span></div>
                </div>

                <div class="kpi-card kpi-expense">
                    <div class="kpi-header">
                        <span class="kpi-title">Despesas Pagas</span>
                        <div class="kpi-icon"><i class='bx bx-trending-down'></i></div>
                    </div>
                    <div class="kpi-value" id="kpiExpensePaid">R$ 0,00</div>
                    <div class="kpi-footer">Pendente: <span id="kpiExpensePending">R$ 0,00</span></div>
                </div>

                <div class="kpi-card kpi-expense" style="border-color: rgba(245, 158, 11, 0.3);">
                    <div class="kpi-header">
                        <span class="kpi-title" style="color: var(--color-pending);">A Pagar / Vencidas</span>
                        <div class="kpi-icon" style="background: rgba(245, 158, 11, 0.1); color: var(--color-pending);"><i class='bx bx-time-five'></i></div>
                    </div>
                    <div class="kpi-value" id="kpiTotalPending" style="color: var(--color-pending);">R$ 0,00</div>
                    <div class="kpi-footer" id="kpiOverdueCount">0 pendências atrasadas</div>
                </div>
            </div>

            <div class="dashboard-grid">
                <div class="chart-card">
                    <div class="chart-header">
                        <span class="chart-title">Fluxo de Caixa (Despesas)</span>
                    </div>
                    <div class="chart-container">
                        <canvas id="expensesChart"></canvas>
                    </div>
                </div>

                <div class="breakdown-card">
                    <div class="chart-header">
                        <span class="chart-title">Despesas por Categoria</span>
                    </div>
                    <div class="breakdown-list" id="categoryBreakdownList">
                        <!-- Itens gerados via JS -->
                    </div>
                </div>
            </div>
        </div>

        <!-- VIEW: TRANSACTIONS -->
        <div id="view-transactions" class="view-section" style="display:none;">
            <div class="filter-card">
                <div class="search-box">
                    <i class='bx bx-search search-box-icon'></i>
                    <input type="text" id="filterSearch" class="search-input" placeholder="Buscar transação...">
                </div>
                <select id="filterType" class="filter-select">
                    <option value="">Todos os Tipos</option>
                    <option value="Receita">Receita</option>
                    <option value="Despesa">Despesa</option>
                </select>
                <select id="filterCategory" class="filter-select">
                    <option value="">Todas Categorias</option>
                    <!-- Preenchido via JS -->
                </select>
                <select id="filterStatus" class="filter-select">
                    <option value="">Qualquer Status</option>
                    <option value="Pago">Pago</option>
                    <option value="Pendente">Pendente</option>
                </select>

                <div class="filter-date-group">
                    <span class="filter-date-label">De:</span>
                    <input type="date" id="filterDateFrom" class="filter-date-input">
                    <span class="filter-date-label">Até:</span>
                    <input type="date" id="filterDateTo" class="filter-date-input">
                </div>

                <button class="btn btn-secondary btn-sm" id="btnResetFilters" title="Limpar Filtros"><i class='bx bx-reset'></i></button>
            </div>

            <div class="table-card">
                <div class="table-header-row">
                    <span class="table-title">Registros</span>
                    <div class="table-header-actions">
                        <button class="btn btn-secondary btn-sm" id="btnExportCSV"><i class='bx bx-export'></i> Exportar CSV</button>
                        <button class="btn btn-secondary btn-sm" id="btnImportCSV"><i class='bx bx-import'></i> Importar</button>
                    </div>
                </div>
                <div class="table-container">
                    <table id="transactionsTable">
                        <thead>
                            <tr>
                                <th>Descrição</th>
                                <th>Categoria</th>
                                <th>Valor</th>
                                <th>Data</th>
                                <th>Status</th>
                                <th style="text-align: right;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Preenchido via JS -->
                        </tbody>
                    </table>
                </div>
                
                <!-- Paginação -->
                <div class="pagination" id="paginationControls">
                    <div class="pagination-info" id="paginationInfo">Mostrando 0 - 0 de 0</div>
                    <div class="pagination-controls" id="paginationButtons">
                        <!-- Botoes -->
                    </div>
                </div>
            </div>
        </div>

        <!-- VIEW: LINKS -->
        <div id="view-links" class="view-section" style="display:none;">
            <div style="display:flex; justify-content: flex-end; margin-bottom: 1rem;">
                <button class="btn btn-secondary" id="btnNewLink"><i class='bx bx-plus'></i> Adicionar Link</button>
            </div>
            <div class="links-grid" id="linksContainer">
                <!-- Links via JS -->
            </div>
        </div>

        <!-- VIEW: ADMIN -->
        <?php if ($isAdmin): ?>
        <div id="view-admin" class="view-section" style="display:none;">
            <div style="display:flex; justify-content: flex-end; margin-bottom: 1rem;">
                <button class="btn btn-primary" id="btnNewUser"><i class='bx bx-user-plus'></i> Novo Usuário</button>
            </div>
            <div class="admin-table-card">
                <div class="table-header-row">
                    <span class="table-title">Usuários do Sistema</span>
                </div>
                <div class="table-container">
                    <table id="usersTable">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Papel</th>
                                <th>Cadastro</th>
                                <th style="text-align: right;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- via JS -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </main>
</div>

<!-- MODAL TRANSAÇÃO -->
<div class="modal-overlay" id="modalTransaction">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title" id="modalTitle">Nova Transação</h3>
            <button class="btn-icon" onclick="closeModal('modalTransaction')"><i class='bx bx-x'></i></button>
        </div>
        <div class="modal-body">
            <form id="transactionForm">
                <input type="hidden" id="formId">
                <div class="form-group full-width" style="margin-bottom: 1.25rem;">
                    <div class="radio-group">
                        <div class="radio-btn radio-btn-receita">
                            <input type="radio" name="type" id="typeIncome" value="Receita" required>
                            <label for="typeIncome" class="radio-label"><i class='bx bx-trending-up'></i> Receita</label>
                        </div>
                        <div class="radio-btn radio-btn-despesa">
                            <input type="radio" name="type" id="typeExpense" value="Despesa" checked required>
                            <label for="typeExpense" class="radio-label"><i class='bx bx-trending-down'></i> Despesa</label>
                        </div>
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label>Descrição</label>
                        <input type="text" id="formDesc" class="form-input" placeholder="Ex: Conta de Luz" required>
                    </div>
                    <div class="form-group">
                        <label>Valor (R$)</label>
                        <input type="number" step="0.01" min="0.01" id="formAmount" class="form-input" placeholder="0.00" required>
                    </div>
                    <div class="form-group">
                        <label>Data</label>
                        <input type="date" id="formDate" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label>Categoria</label>
                        <select id="formCategory" class="form-input" required></select>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select id="formStatus" class="form-input" required>
                            <option value="Pago">Pago</option>
                            <option value="Pendente" selected>Pendente</option>
                        </select>
                    </div>
                    <div class="form-group full-width">
                        <label>
                            <input type="checkbox" id="formRecurring"> 
                            <div class="recurring-toggle">
                                <div class="toggle-switch"></div>
                                <span>Lançamento Recorrente (Mensal)</span>
                            </div>
                        </label>
                    </div>
                    <div class="form-group full-width" id="recurringDayGroup" style="display:none;">
                        <label>Dia do Vencimento</label>
                        <input type="number" id="formRecurringDay" min="1" max="31" class="form-input" placeholder="Ex: 5">
                    </div>
                    <div class="form-group full-width">
                        <label>Observações</label>
                        <input type="text" id="formNotes" class="form-input" placeholder="Opcional">
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('modalTransaction')">Cancelar</button>
            <button class="btn btn-primary" onclick="saveTransaction()"><i class='bx bx-save'></i> Salvar</button>
        </div>
    </div>
</div>

<!-- MODAL LINK -->
<div class="modal-overlay" id="modalLink">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title" id="modalLinkTitle">Novo Link</h3>
            <button class="btn-icon" onclick="closeModal('modalLink')"><i class='bx bx-x'></i></button>
        </div>
        <div class="modal-body">
            <form id="linkForm" class="form-grid">
                <input type="hidden" id="formLinkId">
                <div class="form-group full-width">
                    <label>Nome do Serviço</label>
                    <input type="text" id="formLinkName" class="form-input" required>
                </div>
                <div class="form-group full-width">
                    <label>URL</label>
                    <input type="url" id="formLinkUrl" class="form-input" required>
                </div>
                <div class="form-group full-width">
                    <label>Categoria</label>
                    <select id="formLinkCategory" class="form-input" required>
                        <option value="Bancos">Bancos</option>
                        <option value="Impostos">Impostos</option>
                        <option value="Serviços">Serviços / Fornecedores</option>
                        <option value="Outros">Outros</option>
                    </select>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('modalLink')">Cancelar</button>
            <button class="btn btn-primary" onclick="saveLink()"><i class='bx bx-save'></i> Salvar Link</button>
        </div>
    </div>
</div>

<!-- MODAL ADMIN USUÁRIO -->
<div class="modal-overlay" id="modalUser">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title" id="modalUserTitle">Novo Usuário</h3>
            <button class="btn-icon" onclick="closeModal('modalUser')"><i class='bx bx-x'></i></button>
        </div>
        <div class="modal-body">
            <form id="userForm" class="form-grid">
                <input type="hidden" id="formUserId">
                <div class="form-group full-width">
                    <label>Nome Completo</label>
                    <input type="text" id="formUserName" class="form-input" required>
                </div>
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" id="formUserUsername" class="form-input" required>
                </div>
                <div class="form-group">
                    <label>Papel</label>
                    <select id="formUserRole" class="form-input" required>
                        <option value="user">Usuário Padrão</option>
                        <option value="admin">Administrador</option>
                    </select>
                </div>
                <div class="form-group full-width">
                    <label>Email</label>
                    <input type="email" id="formUserEmail" class="form-input">
                </div>
                <div class="form-group full-width">
                    <label>Senha <span id="userPasswordHelp" style="color:var(--text-muted);font-weight:normal;font-size:0.8rem;"></span></label>
                    <input type="password" id="formUserPassword" class="form-input">
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('modalUser')">Cancelar</button>
            <button class="btn btn-primary" onclick="saveUser()"><i class='bx bx-save'></i> Salvar Usuário</button>
        </div>
    </div>
</div>

<!-- MODAL MEU PERFIL -->
<div class="modal-overlay" id="modalProfile">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title">Meu Perfil</h3>
            <button class="btn-icon" onclick="closeModal('modalProfile')"><i class='bx bx-x'></i></button>
        </div>
        <div class="modal-body">
            <form id="profileForm" class="form-grid">
                <div class="form-group full-width">
                    <label>Nome</label>
                    <input type="text" id="profileName" class="form-input" required>
                </div>
                <div class="form-group full-width">
                    <label>Email</label>
                    <input type="email" id="profileEmail" class="form-input" required>
                </div>
                
                <h4 style="grid-column: 1/-1; margin-top: 1rem; margin-bottom: -0.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Alterar Senha</h4>
                
                <div class="form-group full-width">
                    <label>Senha Atual</label>
                    <input type="password" id="profileCurrentPassword" class="form-input" placeholder="Obrigatório apenas para mudar a senha">
                </div>
                <div class="form-group full-width">
                    <label>Nova Senha</label>
                    <input type="password" id="profileNewPassword" class="form-input" placeholder="Mínimo 6 caracteres">
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('modalProfile')">Cancelar</button>
            <button class="btn btn-primary" onclick="saveProfile()"><i class='bx bx-save'></i> Atualizar Perfil</button>
        </div>
    </div>
</div>

<!-- MODAL IMPORTAÇÃO -->
<div class="modal-overlay" id="modalImport">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title">Importar Transações (CSV)</h3>
            <button class="btn-icon" onclick="closeModal('modalImport')"><i class='bx bx-x'></i></button>
        </div>
        <div class="modal-body">
            <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 1rem;">O arquivo CSV deve ter as colunas: Descrição, Valor, Data(AAAA-MM-DD), Categoria, Tipo(Receita/Despesa), Status(Pago/Pendente)</p>
            
            <div class="import-dropzone" id="importDropzone">
                <i class='bx bx-cloud-upload' style="font-size: 3rem; color: var(--color-primary); margin-bottom: 0.5rem;"></i>
                <h4 style="margin-bottom: 0.5rem;">Clique ou arraste um arquivo CSV</h4>
                <p style="font-size: 0.8rem; color: var(--text-muted);">Separado por ponto e vírgula (;)</p>
                <input type="file" id="fileImportCsv" accept=".csv" style="display:none;">
            </div>

            <div id="importPreviewContainer" style="display:none; margin-top: 1.5rem; overflow-x: auto;">
                <h5 style="margin-bottom: 0.5rem; color: var(--text-primary);">Pré-visualização (Primeiras 3 linhas)</h5>
                <table class="import-preview-table" id="importPreviewTable">
                    <thead></thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('modalImport')">Cancelar</button>
            <button class="btn btn-primary" id="btnConfirmImport" style="display:none;" onclick="processImport()"><i class='bx bx-check'></i> Confirmar Importação</button>
        </div>
    </div>
</div>

<!-- TOAST CONTAINER -->
<div class="toast-container" id="toastContainer"></div>

<!-- USER ID OCULTO -->
<input type="hidden" id="currentUserId" value="<?= $_SESSION['user_id'] ?>">
<script>
    const IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;
</script>
<script src="app.js?v=<?= time() ?>"></script>
</body>
</html>
