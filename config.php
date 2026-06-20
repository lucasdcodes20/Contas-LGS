<?php
/**
 * Configurações Gerais do Sistema contasLGS
 */

// Definir fuso horário padrão para o Brasil
date_default_timezone_set('America/Sao_Paulo');

// Tipo de banco de dados: 'json' ou 'mysql'
if (!defined('DB_ENGINE')) {
    define('DB_ENGINE', 'json'); 
}

// Configurações do Banco de Dados MariaDB/MySQL (caso alterado para 'mysql')
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
}
if (!defined('DB_PORT')) {
    define('DB_PORT', '3306');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', 'contas_lgs');
}
if (!defined('DB_USER')) {
    define('DB_USER', 'lgs_user');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', 'lgs_password_2026');
}

// Configurações do Banco de Dados em arquivo JSON (caso seja 'json')
if (!defined('JSON_DB_DIR')) {
    define('JSON_DB_DIR', __DIR__ . '/data');
}
if (!defined('JSON_DB_FILE')) {
    define('JSON_DB_FILE', JSON_DB_DIR . '/transactions.json');
}

// Categorias padrões do sistema
$SYSTEM_CATEGORIES = [
    'Receita' => [
        'Salário',
        'Investimentos',
        'Freelance',
        'Outros Recebimentos'
    ],
    'Despesa' => [
        'Aluguel / Habitação',
        'Alimentação',
        'Transporte',
        'Saúde',
        'Educação',
        'Lazer',
        'Assinaturas & Serviços',
        'Contas de Consumo (Água/Luz/Internet)',
        'Outros Custos'
    ]
];

// Moeda padrão
if (!defined('CURRENCY_SYMBOL')) {
    define('CURRENCY_SYMBOL', 'R$');
}
if (!defined('CURRENCY_DECIMAL_SEP')) {
    define('CURRENCY_DECIMAL_SEP', ',');
}
if (!defined('CURRENCY_THOUSANDS_SEP')) {
    define('CURRENCY_THOUSANDS_SEP', '.');
}

// Garantir que a pasta de dados do JSON exista se a engine for JSON
if (DB_ENGINE === 'json' && !is_dir(JSON_DB_DIR)) {
    mkdir(JSON_DB_DIR, 0775, true);
}
