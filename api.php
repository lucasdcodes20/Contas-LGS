<?php
/**
 * API RESTful JSON Estendida - contasLGS (Fase 3)
 * Rotas: transactions, links, users, export, import, recurring
 */

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Sessão expirada. Por favor, faça login.'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $route  = $_GET['route'] ?? 'transactions';

    if ($route === 'links') {
        $repo = Database::getLinkRepository();
        match($method) {
            'GET'    => handleGetLinks($repo),
            'POST'   => handlePostLinks($repo),
            'DELETE' => handleDeleteLinks($repo),
            default  => methodNotAllowed()
        };
    } elseif ($route === 'users') {
        // Apenas admin pode gerenciar usuários
        if ($_SESSION['user_role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Acesso restrito a administradores.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $userRepo = Database::getUserRepository();
        match($method) {
            'GET'    => handleGetUsers($userRepo),
            'POST'   => handlePostUsers($userRepo),
            'PUT'    => handlePutUsers($userRepo),
            'DELETE' => handleDeleteUsers($userRepo),
            default  => methodNotAllowed()
        };
    } elseif ($route === 'profile') {
        $userRepo = Database::getUserRepository();
        if ($method === 'PUT') {
            handleUpdateProfile($userRepo);
        } else {
            methodNotAllowed();
        }
    } elseif ($route === 'export') {
        $repo = Database::getRepository();
        handleExport($repo);
    } elseif ($route === 'import') {
        if ($method !== 'POST') { methodNotAllowed(); return; }
        $repo = Database::getRepository();
        handleImport($repo);
    } elseif ($route === 'alerts') {
        $repo = Database::getRepository();
        handleGetAlerts($repo);
    } else {
        // Rota padrão: transactions
        $repo = Database::getRepository();
        match($method) {
            'GET'    => handleGetTransactions($repo),
            'POST'   => handlePostTransactions($repo),
            'DELETE' => handleDeleteTransactions($repo),
            default  => methodNotAllowed()
        };
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

function methodNotAllowed(): void {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido'], JSON_UNESCAPED_UNICODE);
}

// ==========================================
// TRANSAÇÕES
// ==========================================

function handleGetTransactions(TransactionRepository $repo): void {
    global $SYSTEM_CATEGORIES;

    $filters = [];
    if (isset($_GET['search']))    $filters['search']    = trim($_GET['search']);
    if (isset($_GET['type']) && in_array($_GET['type'], ['Receita','Despesa'])) $filters['type'] = $_GET['type'];
    if (isset($_GET['category']))  $filters['category']  = trim($_GET['category']);
    if (isset($_GET['status']) && in_array($_GET['status'], ['Pago','Pendente'])) $filters['status'] = $_GET['status'];
    if (isset($_GET['date_from'])) $filters['date_from'] = trim($_GET['date_from']);
    if (isset($_GET['date_to']))   $filters['date_to']   = trim($_GET['date_to']);

    if (isset($_GET['month_year'])) {
        if ($_GET['month_year'] !== 'all') $filters['month_year'] = $_GET['month_year'];
    } elseif (!isset($filters['date_from']) && !isset($filters['date_to'])) {
        $filters['month_year'] = date('Y-m');
    }

    $page    = isset($_GET['page'])     ? max(1, (int)$_GET['page'])    : 1;
    $perPage = isset($_GET['per_page']) ? min(100,(int)$_GET['per_page']) : 20;

    $paginated = $repo->getAllPaginated($filters, $page, $perPage);
    $stats     = $repo->getStats($filters);

    echo json_encode([
        'success'        => true,
        'filters_applied'=> $filters,
        'categories'     => $SYSTEM_CATEGORIES,
        'stats'          => $stats,
        'transactions'   => $paginated['items'],
        'pagination'     => [
            'page'        => $paginated['page'],
            'per_page'    => $paginated['per_page'],
            'total'       => $paginated['total'],
            'total_pages' => $paginated['total_pages'],
        ]
    ], JSON_UNESCAPED_UNICODE);
}

function handlePostTransactions(TransactionRepository $repo): void {
    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;

    if (empty($data['description'])) { http_response_code(400); echo json_encode(['error'=>'Descrição é obrigatória'],JSON_UNESCAPED_UNICODE); return; }
    if (!isset($data['amount']) || !is_numeric($data['amount']) || (float)$data['amount'] <= 0) { http_response_code(400); echo json_encode(['error'=>'Valor deve ser positivo'],JSON_UNESCAPED_UNICODE); return; }
    if (empty($data['date']) || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$data['date'])) { http_response_code(400); echo json_encode(['error'=>'Data obrigatória (AAAA-MM-DD)'],JSON_UNESCAPED_UNICODE); return; }
    if (empty($data['category'])) { http_response_code(400); echo json_encode(['error'=>'Categoria obrigatória'],JSON_UNESCAPED_UNICODE); return; }
    if (empty($data['type']) || !in_array($data['type'],['Receita','Despesa'])) { http_response_code(400); echo json_encode(['error'=>'Tipo inválido'],JSON_UNESCAPED_UNICODE); return; }
    if (empty($data['status']) || !in_array($data['status'],['Pago','Pendente'])) { http_response_code(400); echo json_encode(['error'=>'Status inválido'],JSON_UNESCAPED_UNICODE); return; }

    $saved = $repo->save($data);
    echo json_encode(['success'=>true,'message'=>isset($data['id'])?'Transação atualizada':'Transação adicionada','transaction'=>$saved], JSON_UNESCAPED_UNICODE);
}

function handleDeleteTransactions(TransactionRepository $repo): void {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
    if (!$id) { $body=json_decode(file_get_contents('php://input'),true); $id=isset($body['id'])?(int)$body['id']:null; }
    if (!$id || $id<=0) { http_response_code(400); echo json_encode(['error'=>'ID inválido'],JSON_UNESCAPED_UNICODE); return; }
    if ($repo->delete($id)) echo json_encode(['success'=>true,'message'=>'Transação excluída'],JSON_UNESCAPED_UNICODE);
    else { http_response_code(404); echo json_encode(['error'=>'Não encontrada'],JSON_UNESCAPED_UNICODE); }
}

// ==========================================
// LINKS
// ==========================================

function handleGetLinks(LinkRepository $repo): void {
    echo json_encode(['success'=>true,'links'=>$repo->getAll()], JSON_UNESCAPED_UNICODE);
}

function handlePostLinks(LinkRepository $repo): void {
    $data = json_decode(file_get_contents('php://input'),true) ?: $_POST;
    if (empty($data['name'])) { http_response_code(400); echo json_encode(['error'=>'Nome obrigatório'],JSON_UNESCAPED_UNICODE); return; }
    if (empty($data['url']) || !filter_var($data['url'],FILTER_VALIDATE_URL)) { http_response_code(400); echo json_encode(['error'=>'URL inválida'],JSON_UNESCAPED_UNICODE); return; }
    if (empty($data['category'])) { http_response_code(400); echo json_encode(['error'=>'Categoria obrigatória'],JSON_UNESCAPED_UNICODE); return; }
    $saved = $repo->save($data);
    echo json_encode(['success'=>true,'message'=>isset($data['id'])?'Link atualizado':'Link cadastrado','link'=>$saved], JSON_UNESCAPED_UNICODE);
}

function handleDeleteLinks(LinkRepository $repo): void {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id<=0) { http_response_code(400); echo json_encode(['error'=>'ID inválido'],JSON_UNESCAPED_UNICODE); return; }
    if ($repo->delete($id)) echo json_encode(['success'=>true,'message'=>'Link excluído'],JSON_UNESCAPED_UNICODE);
    else { http_response_code(404); echo json_encode(['error'=>'Link não encontrado'],JSON_UNESCAPED_UNICODE); }
}

// ==========================================
// USUÁRIOS (ADMIN)
// ==========================================

function handleGetUsers(UserRepository $repo): void {
    echo json_encode(['success'=>true,'users'=>$repo->getAll()], JSON_UNESCAPED_UNICODE);
}

function handlePostUsers(UserRepository $repo): void {
    $data = json_decode(file_get_contents('php://input'),true) ?: $_POST;
    if (empty($data['name']))     { http_response_code(400); echo json_encode(['error'=>'Nome obrigatório'],JSON_UNESCAPED_UNICODE); return; }
    if (empty($data['username'])) { http_response_code(400); echo json_encode(['error'=>'Username obrigatório'],JSON_UNESCAPED_UNICODE); return; }
    if (empty($data['password'])) { http_response_code(400); echo json_encode(['error'=>'Senha obrigatória'],JSON_UNESCAPED_UNICODE); return; }
    $data['email'] = $data['email'] ?? ($data['username'].'@lgs.local');
    if ($repo->create($data)) echo json_encode(['success'=>true,'message'=>'Usuário criado com sucesso'],JSON_UNESCAPED_UNICODE);
    else { http_response_code(409); echo json_encode(['error'=>'Username já existe'],JSON_UNESCAPED_UNICODE); }
}

function handlePutUsers(UserRepository $repo): void {
    $data = json_decode(file_get_contents('php://input'),true) ?: [];
    $id   = isset($data['id']) ? (int)$data['id'] : 0;
    if ($id<=0) { http_response_code(400); echo json_encode(['error'=>'ID inválido'],JSON_UNESCAPED_UNICODE); return; }

    // Troca de senha
    if (!empty($data['new_password'])) {
        if (strlen($data['new_password']) < 6) { http_response_code(400); echo json_encode(['error'=>'Senha mínimo 6 caracteres'],JSON_UNESCAPED_UNICODE); return; }
        $repo->updatePassword($id, password_hash($data['new_password'], PASSWORD_DEFAULT));
        echo json_encode(['success'=>true,'message'=>'Senha alterada com sucesso'],JSON_UNESCAPED_UNICODE);
        return;
    }

    $updateData = array_filter(['name'=>$data['name']??null,'email'=>$data['email']??null,'role'=>$data['role']??null]);
    if ($repo->update($id, $updateData)) echo json_encode(['success'=>true,'message'=>'Usuário atualizado'],JSON_UNESCAPED_UNICODE);
    else { http_response_code(404); echo json_encode(['error'=>'Usuário não encontrado'],JSON_UNESCAPED_UNICODE); }
}

function handleDeleteUsers(UserRepository $repo): void {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    // Proteger contra auto-exclusão
    if ($id === (int)$_SESSION['user_id']) { http_response_code(400); echo json_encode(['error'=>'Não pode excluir a própria conta'],JSON_UNESCAPED_UNICODE); return; }
    if ($id<=0) { http_response_code(400); echo json_encode(['error'=>'ID inválido'],JSON_UNESCAPED_UNICODE); return; }
    if ($repo->delete($id)) echo json_encode(['success'=>true,'message'=>'Usuário excluído'],JSON_UNESCAPED_UNICODE);
    else { http_response_code(404); echo json_encode(['error'=>'Usuário não encontrado'],JSON_UNESCAPED_UNICODE); }
}

// ==========================================
// PERFIL (TROCA DE SENHA DO PRÓPRIO USUÁRIO)
// ==========================================

function handleUpdateProfile(UserRepository $repo): void {
    $data = json_decode(file_get_contents('php://input'),true) ?: [];
    $id   = (int)$_SESSION['user_id'];

    if (!empty($data['new_password'])) {
        $user = $repo->getById($id);
        if (!$user) { http_response_code(404); echo json_encode(['error'=>'Usuário não encontrado'],JSON_UNESCAPED_UNICODE); return; }
        if (empty($data['current_password']) || !password_verify($data['current_password'], $user['password'])) {
            http_response_code(400); echo json_encode(['error'=>'Senha atual incorreta'],JSON_UNESCAPED_UNICODE); return;
        }
        if (strlen($data['new_password']) < 6) { http_response_code(400); echo json_encode(['error'=>'Nova senha mínimo 6 caracteres'],JSON_UNESCAPED_UNICODE); return; }
        $repo->updatePassword($id, password_hash($data['new_password'], PASSWORD_DEFAULT));
        echo json_encode(['success'=>true,'message'=>'Senha alterada com sucesso!'],JSON_UNESCAPED_UNICODE);
        return;
    }

    $updateData = array_filter(['name'=>$data['name']??null,'email'=>$data['email']??null]);
    if (!empty($updateData)) {
        $repo->update($id, $updateData);
        if (isset($updateData['name'])) $_SESSION['user_name'] = $updateData['name'];
        echo json_encode(['success'=>true,'message'=>'Perfil atualizado'],JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(400);
        echo json_encode(['error'=>'Nenhum dado para atualizar'],JSON_UNESCAPED_UNICODE);
    }
}

// ==========================================
// EXPORTAÇÃO CSV
// ==========================================

function handleExport(TransactionRepository $repo): void {
    $filters = [];
    if (isset($_GET['month_year']) && $_GET['month_year'] !== 'all') $filters['month_year'] = $_GET['month_year'];
    if (isset($_GET['date_from'])) $filters['date_from'] = trim($_GET['date_from']);
    if (isset($_GET['date_to']))   $filters['date_to']   = trim($_GET['date_to']);
    if (isset($_GET['type']) && in_array($_GET['type'],['Receita','Despesa'])) $filters['type']=$_GET['type'];

    $transactions = $repo->getAll($filters);

    // Override header for CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="contasLGS_export_'.date('Y-m-d').'.csv"');

    $out = fopen('php://output','w');
    // BOM para Excel reconhecer UTF-8
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['ID','Descrição','Valor','Data','Categoria','Tipo','Status','Observações','Criado em'], ';');

    foreach ($transactions as $t) {
        fputcsv($out, [
            $t['id'],
            $t['description'],
            number_format((float)$t['amount'], 2, ',', '.'),
            $t['date'],
            $t['category'],
            $t['type'],
            $t['status'],
            $t['notes'] ?? '',
            $t['created_at'] ?? ''
        ], ';');
    }
    fclose($out);
    exit;
}

// ==========================================
// IMPORTAÇÃO CSV
// ==========================================

function handleImport(TransactionRepository $repo): void {
    global $SYSTEM_CATEGORIES;

    $data = json_decode(file_get_contents('php://input'), true);
    if (empty($data['rows']) || !is_array($data['rows'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Dados de importação inválidos'], JSON_UNESCAPED_UNICODE);
        return;
    }

    $allCategories = array_merge($SYSTEM_CATEGORIES['Receita'], $SYSTEM_CATEGORIES['Despesa']);
    $imported = 0; $errors = [];

    foreach ($data['rows'] as $i => $row) {
        $line = $i + 2;
        if (empty($row['description'])) { $errors[] = "Linha $line: Descrição vazia"; continue; }
        if (!is_numeric($row['amount'] ?? '') || (float)$row['amount'] <= 0) { $errors[] = "Linha $line: Valor inválido"; continue; }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $row['date'] ?? '')) { $errors[] = "Linha $line: Data inválida (use AAAA-MM-DD)"; continue; }
        if (!in_array($row['type'] ?? '', ['Receita','Despesa'])) { $errors[] = "Linha $line: Tipo deve ser 'Receita' ou 'Despesa'"; continue; }
        if (!in_array($row['status'] ?? '', ['Pago','Pendente'])) { $errors[] = "Linha $line: Status deve ser 'Pago' ou 'Pendente'"; continue; }

        $category = trim($row['category'] ?? 'Outros Custos');
        if (!in_array($category, $allCategories)) $category = ($row['type']==='Receita') ? 'Outros Recebimentos' : 'Outros Custos';

        $repo->save(['description'=>$row['description'],'amount'=>$row['amount'],'date'=>$row['date'],'category'=>$category,'type'=>$row['type'],'status'=>$row['status'],'notes'=>$row['notes']??'']);
        $imported++;
    }

    echo json_encode(['success'=>true,'imported'=>$imported,'errors'=>$errors,'message'=>"$imported transações importadas com sucesso."], JSON_UNESCAPED_UNICODE);
}

// ==========================================
// ALERTAS DE PENDÊNCIAS VENCIDAS
// ==========================================

function handleGetAlerts(TransactionRepository $repo): void {
    $today = date('Y-m-d');
    // Busca todas as pendentes com data <= hoje
    $all = $repo->getAll(['status' => 'Pendente']);
    $overdue = array_filter($all, fn($t) => $t['date'] <= $today);
    $overdue = array_values($overdue);

    $totalOverdue = array_sum(array_column($overdue, 'amount'));
    echo json_encode([
        'success'       => true,
        'overdue_count' => count($overdue),
        'overdue_total' => $totalOverdue,
        'overdue'       => array_slice($overdue, 0, 10) // Máximo 10 no alerta
    ], JSON_UNESCAPED_UNICODE);
}
