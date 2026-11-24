<?php
// Configuração de conexão com o MySQL (ajuste conforme seu ambiente).
$host = 'localhost';
$banco = 'chronus';
$usuario = 'root';
$senha = ''; // Informe a senha do MySQL, caso exista.

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$banco;charset=utf8mb4",
        $usuario,
        $senha,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    // Em produção, registre o erro em log e não exiba detalhes da conexão.
    die('Não foi possível conectar ao banco de dados. Verifique config/conexao.php.');
}
?>
