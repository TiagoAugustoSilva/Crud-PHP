<?php
require_once('conexao.php');

// Inicializa a sessão
session_start();

// Lógica de processamento da venda
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $produtoId = key($_POST['produto']);
    $quantidade = $_POST['produto'][$produtoId];

    // Adicione a lógica de validação e processamento aqui
    // Certifique-se de retornar uma resposta JSON
    // Exemplo:
    $response = ['success' => true];
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
} else {
    $response = ['success' => false, 'message' => 'Método inválido'];
    header('Content-Type: application/json');
    http_response_code(400); // Bad Request
    echo json_encode($response);
    exit();
}
?>
