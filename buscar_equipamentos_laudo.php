
<?php
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

try {

    if (isset($_GET['cliente_id'])) {

        $cliente_id = (int)$_GET['cliente_id'];

        $stmt = $pdo->prepare("
            SELECT e.id, e.codigo, e.marca, e.modelo
            FROM equipamentos e
            LEFT JOIN clientes c ON c.id = e.cliente_id
            LEFT JOIN clientes c2 ON c2.nome = e.cliente
            WHERE COALESCE(c.id, c2.id) = ?
            ORDER BY e.codigo
        ");

        $stmt->execute([$cliente_id]);

        echo json_encode(
            $stmt->fetchAll(),
            JSON_UNESCAPED_UNICODE
        );

        exit;
    }

    if (isset($_GET['equipamento_id'])) {

        $id = (int)$_GET['equipamento_id'];

        $stmt = $pdo->prepare("
            SELECT id, codigo, marca, modelo,
                   capacidade_btu, numero_serie,
                   local_instalacao
            FROM equipamentos
            WHERE id = ?
        ");

        $stmt->execute([$id]);

        echo json_encode(
            $stmt->fetch() ?: [],
            JSON_UNESCAPED_UNICODE
        );

        exit;
    }

    if (isset($_GET['os_equipamento_id'])) {

        $id = (int)$_GET['os_equipamento_id'];

        $stmt = $pdo->prepare("
            SELECT id, tipo, status
            FROM ordens_servico
            WHERE equipamento_id = ?
            ORDER BY id DESC
        ");

        $stmt->execute([$id]);

        echo json_encode(
            $stmt->fetchAll(),
            JSON_UNESCAPED_UNICODE
        );

        exit;
    }

    echo json_encode([]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'erro' => 'Erro ao consultar o banco de dados.'
    ]);
}
