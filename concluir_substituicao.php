
<?php
require_once 'config.php';

function voltar($mensagem, $sucesso = false) {
    $url = $sucesso
        ? 'substituicao_equipamento.php?concluida=1'
        : 'substituicao_equipamento.php?erro=' . urlencode($mensagem);

    header('Location: ' . $url);
    exit;
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    voltar('ID da substituição inválido.');
}

try {
    $pdo->beginTransaction();

    // Buscar substituição e bloquear registro
    $sql = "
        SELECT
            s.*,
            l.cliente_id,
            l.unidade,
            l.ambiente,
            c.nome AS cliente_nome,
            ea.cliente AS cliente_antigo,
            ea.local_instalacao AS local_antigo,
            ea.tipo AS tipo_antigo
        FROM substituicoes_equipamentos s
        INNER JOIN laudos_condenacao l ON l.id = s.laudo_id
        INNER JOIN clientes c ON c.id = l.cliente_id
        INNER JOIN equipamentos ea ON ea.id = s.equipamento_antigo_id
        WHERE s.id = ?
        FOR UPDATE
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $sub = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sub) {
        throw new Exception('Substituição não encontrada.');
    }

    if ($sub['status'] !== 'Programada') {
        throw new Exception('A substituição não está programada ou já foi concluída.');
    }

    $novoId = !empty($sub['equipamento_novo_id'])
        ? (int)$sub['equipamento_novo_id']
        : 0;

    // Se já existe equipamento selecionado, apenas vincula.
    if ($novoId > 0) {

        $stmt = $pdo->prepare("
            SELECT id, cliente_id
            FROM equipamentos
            WHERE id = ?
            FOR UPDATE
        ");

        $stmt->execute([$novoId]);
        $novo = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$novo) {
            throw new Exception('Equipamento novo não encontrado.');
        }

        if ($novoId === (int)$sub['equipamento_antigo_id']) {
            throw new Exception('O equipamento novo não pode ser o antigo.');
        }

        if (
            !empty($novo['cliente_id']) &&
            (int)$novo['cliente_id'] !== (int)$sub['cliente_id']
        ) {
            throw new Exception('O equipamento selecionado pertence a outro cliente.');
        }

        $stmt = $pdo->prepare("
            UPDATE equipamentos
            SET status = 'Ativo',
                cliente_id = ?,
                cliente = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $sub['cliente_id'],
            $sub['cliente_nome'],
            $novoId
        ]);

    } else {

        // Dados necessários para criar o novo equipamento.
        $marca = trim($sub['marca_novo'] ?? '');
        $modelo = trim($sub['modelo_novo'] ?? '');
        $capacidade = (int)($sub['capacidade_nova_btu'] ?? 0);

        if ($marca === '' || $modelo === '' || $capacidade <= 0) {
            throw new Exception(
                'Informe marca, modelo e capacidade do novo equipamento.'
            );
        }

        $codigo = 'SUB-' . $id . '-' . strtoupper(bin2hex(random_bytes(4)));

        $local = $sub['ambiente']
            ?: ($sub['unidade'] ?: $sub['local_antigo']);

        $observacoes = 'Equipamento criado pela substituição #' . $id .
            '. Laudo: ' . $sub['laudo_id'] .
            '. Equipamento anterior: ' . $sub['equipamento_antigo_id'];

        $stmt = $pdo->prepare("
            INSERT INTO equipamentos (
                codigo,
                cliente,
                cliente_id,
                local_instalacao,
                marca,
                modelo,
                capacidade_btu,
                tipo,
                numero_serie,
                data_instalacao,
                status,
                observacoes
            ) VALUES (
                :codigo,
                :cliente,
                :cliente_id,
                :local,
                :marca,
                :modelo,
                :capacidade,
                :tipo,
                :serie,
                :data,
                'Ativo',
                :observacoes
            )
        ");

        $stmt->execute([
            ':codigo' => $codigo,
            ':cliente' => $sub['cliente_nome'] ?: $sub['cliente_antigo'],
            ':cliente_id' => $sub['cliente_id'],
            ':local' => $local,
            ':marca' => $marca,
            ':modelo' => $modelo,
            ':capacidade' => $capacidade,
            ':tipo' => $sub['tipo_antigo'],
            ':serie' => $sub['numero_serie_novo'],
            ':data' => $sub['data_substituicao'],
            ':observacoes' => $observacoes
        ]);

        $novoId = (int)$pdo->lastInsertId();
    }

    // Vincular equipamento e concluir substituição.
    $stmt = $pdo->prepare("
        UPDATE substituicoes_equipamentos
        SET equipamento_novo_id = ?,
            status = 'Concluída'
        WHERE id = ?
          AND status = 'Programada'
    ");

    $stmt->execute([$novoId, $id]);

    if ($stmt->rowCount() !== 1) {
        throw new Exception('Não foi possível concluir a substituição.');
    }

    // Inativar equipamento antigo sem apagar histórico.
    $stmt = $pdo->prepare("
        UPDATE equipamentos
        SET status = 'Inativo'
        WHERE id = ?
    ");

    $stmt->execute([$sub['equipamento_antigo_id']]);

    $pdo->commit();

    voltar('', true);

} catch (Exception $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    voltar($e->getMessage());
}
