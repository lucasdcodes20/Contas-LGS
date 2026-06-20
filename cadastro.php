<?php
/**
 * Página de Cadastro de Usuários - contasLGS
 */
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// Se já estiver logado, redireciona para o dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($name) || empty($email) || empty($username) || empty($password)) {
        $error = 'Todos os campos são obrigatórios.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'E-mail fornecido é inválido.';
    } elseif (strlen($password) < 6) {
        $error = 'A senha deve possuir no mínimo 6 caracteres.';
    } else {
        try {
            $userRepo = Database::getUserRepository();
            
            // Verifica se o usuário já existe
            if ($userRepo->getByUsername($username) !== null) {
                $error = 'Este nome de usuário já está sendo utilizado.';
            } else {
                $created = $userRepo->create([
                    'name' => $name,
                    'email' => $email,
                    'username' => $username,
                    'password' => $password,
                    'role' => 'user'
                ]);

                if ($created) {
                    $success = 'Cadastro realizado com sucesso! Redirecionando para login...';
                    header('Refresh: 2; URL=login.php');
                } else {
                    $error = 'Erro desconhecido ao registrar usuário. Tente novamente.';
                }
            }
        } catch (Exception $e) {
            $error = 'Falha na comunicação com o banco: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>contasLGS — Cadastro</title>
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background: linear-gradient(135deg, #070a13 0%, #0e1424 100%);
        }
        
        .auth-container {
            width: 100%;
            max-width: 440px;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .auth-card {
            background: rgba(14, 19, 34, 0.7);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-lg);
            padding: 2.25rem;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.7);
        }

        .auth-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .auth-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.35rem;
        }

        .auth-header p {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .alert {
            padding: 0.75rem 1rem;
            border-radius: var(--border-radius-md);
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-error {
            background: rgba(244, 63, 94, 0.1);
            border: 1px solid var(--color-expense);
            color: var(--color-expense);
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid var(--color-income);
            color: var(--color-income);
        }

        .auth-footer {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .auth-footer a {
            color: var(--color-primary);
            text-decoration: none;
            font-weight: 600;
        }

        .auth-footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <div class="auth-container">
        
        <div class="auth-card">
            <div class="auth-header">
                <h2>Crie sua Conta</h2>
                <p>Insira seus dados essenciais para se cadastrar</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i class="bx bx-error-circle"></i>
                    <span><?= $error ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="bx bx-check-circle"></i>
                    <span><?= $success ?></span>
                </div>
            <?php endif; ?>

            <form action="cadastro.php" method="POST" style="display: flex; flex-direction: column; gap: 1.25rem;">
                <!-- Nome Completo -->
                <div class="form-group">
                    <label for="name">Nome Completo</label>
                    <input type="text" id="name" name="name" class="form-input" placeholder="Seu nome completo" required value="<?= htmlspecialchars($name ?? '') ?>">
                </div>

                <!-- E-mail -->
                <div class="form-group">
                    <label for="email">E-mail</label>
                    <input type="email" id="email" name="email" class="form-input" placeholder="seu-email@exemplo.com" required value="<?= htmlspecialchars($email ?? '') ?>">
                </div>

                <!-- Nome de Usuário -->
                <div class="form-group">
                    <label for="username">Nome de Usuário (Login)</label>
                    <input type="text" id="username" name="username" class="form-input" placeholder="Escolha um usuário" required value="<?= htmlspecialchars($username ?? '') ?>">
                </div>

                <!-- Senha -->
                <div class="form-group">
                    <label for="password">Senha de Acesso</label>
                    <input type="password" id="password" name="password" class="form-input" placeholder="Mínimo 6 caracteres" required>
                </div>

                <button type="submit" class="btn btn-primary" style="margin-top: 0.5rem; width: 100%;">
                    Cadastrar Minha Conta
                </button>
            </form>

            <div class="auth-footer">
                Já possui registro? <a href="login.php">Faça Login</a>
            </div>
        </div>

    </div>

</body>
</html>
