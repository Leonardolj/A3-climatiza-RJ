
<?php
require_once 'config.php';

function h($valor) {
    return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

$osId = filter_input(INPUT_GET, 'os_id', FILTER_VALIDATE_INT);

if (!$osId) {
    die('Ordem de Serviço inválida.');
}

$sql = "
    SELECT
        r.*,
        os.tipo,
        os.prioridade,
        os.descricao AS descricao_os,
        os.status,
        e.codigo,
        e.marca,
        e.modelo,
        e.numero_serie,
        e.local_instalacao,
        COALESCE(c.nome, e.cliente) AS cliente_nome,
        c.documento,
        c.telefone,
        c.endereco
    FROM rats r
    INNER JOIN ordens_servico os
        ON os.id = r.ordem_servico_id
    INNER JOIN equipamentos e
        ON e.id = os.equipamento_id
    LEFT JOIN clientes c
        ON c.id = e.cliente_id
    WHERE r.ordem_servico_id = :os_id
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':os_id' => $osId]);
$rat = $stmt->fetch();

if (!$rat) {
    die('RAT não encontrado. Cadastre o relatório antes de imprimir.');
}

function dataBR($data) {
    if (!$data) return '-';
    return date('d/m/Y', strtotime($data));
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RAT - OS #<?= (int)$rat['ordem_servico_id'] ?></title>

<style>
@page {
    size: A4;
    margin: 15mm;
}

* {
    box-sizing: border-box;
}

body {
    font-family: Arial, sans-serif;
    color: #222;
    font-size: 12px;
    margin: 0;
}

.topo {
    text-align: center;
    border-bottom: 3px solid #123b67;
    padding-bottom: 12px;
    margin-bottom: 18px;
}

.topo h1 {
    color: #123b67;
    margin: 0 0 6px;
    font-size: 23px;
}

.topo p {
    margin: 3px;
}

.numero {
    text-align: right;
    font-weight: bold;
    margin-bottom: 15px;
}

.secao {
    margin-bottom: 14px;
    page-break-inside: avoid;
}

.secao h2 {
    background: #e8eef5;
    border-left: 4px solid #123b67;
    padding: 8px;
    font-size: 13px;
    margin: 0 0 8px;
}

.dados {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px 18px;
    padding: 5px;
}

.campo {
    line-height: 1.6;
}

.texto {
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    padding: 10px;
    min-height: 45px;
    line-height: 1.5;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
}

.assinaturas {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 40px;
    margin-top: 55px;
    text-align: center;
}

.linha {
    border-top: 1px solid #222;
    padding-top: 8px;
}

.rodape {
    margin-top: 25px;
    border-top: 1px solid #ccc;
    padding-top: 8px;
    text-align: center;
    font-size: 10px;
}

.acoes {
    text-align: center;
    margin: 20px;
}

button, .voltar {
    padding: 10px 16px;
    background: #123b67;
    color: white;
    border: 0;
    border-radius: 5px;
    text-decoration: none;
    cursor: pointer;
    font-size: 13px;
}

@media print {
    .acoes {
        display: none;
    }
}
</style>
</head>

<body>

<div class="acoes">
    <button onclick="window.print()">🖨️ Imprimir RAT</button>
    <a class="voltar" href="rat.php?os_id=<?= (int)$rat['ordem_servico_id'] ?>">
        Voltar ao RAT
    </a>
</div>

<div class="topo">
    <h1>❄ A3 Climatização</h1>
    <h2>RELATÓRIO DE ATENDIMENTO TÉCNICO</h2>
    <p>Refrigeração e Climatização</p>
</div>

<div class="numero">
    RAT vinculado à Ordem de Serviço #<?= (int)$rat['ordem_servico_id'] ?>
</div>

<div class="secao">
    <h2>1. Identificação do atendimento</h2>

    <div class="dados">
        <div class="campo">
            <strong>Cliente:</strong> <?= h($rat['cliente_nome']) ?>
        </div>

        <div class="campo">
            <strong>Documento:</strong> <?= h($rat['documento']) ?>
        </div>

        <div class="campo">
            <strong>Telefone:</strong> <?= h($rat['telefone']) ?>
        </div>

        <div class="campo">
            <strong>Data do atendimento:</strong> <?= dataBR($rat['data_atendimento']) ?>
        </div>

        <div class="campo">
            <strong>Horário de início:</strong> <?= h($rat['hora_inicio'] ?: '-') ?>
        </div>

        <div class="campo">
            <strong>Horário de término:</strong> <?= h($rat['hora_fim'] ?: '-') ?>
        </div>

        <div class="campo">
            <strong>Técnico:</strong> <?= h($rat['tecnico_responsavel']) ?>
        </div>

        <div class="campo">
            <strong>Responsável pelo cliente:</strong> <?= h($rat['responsavel_cliente']) ?>
        </div>
    </div>
</div>

<div class="secao">
    <h2>2. Identificação do equipamento</h2>

    <div class="dados">
        <div class="campo">
            <strong>Código:</strong> <?= h($rat['codigo']) ?>
        </div>

        <div class="campo">
            <strong>Marca:</strong> <?= h($rat['marca']) ?>
        </div>

        <div class="campo">
            <strong>Modelo:</strong> <?= h($rat['modelo']) ?>
        </div>

        <div class="campo">
            <strong>Número de série:</strong> <?= h($rat['numero_serie']) ?>
        </div>

        <div class="campo">
            <strong>Local de instalação:</strong> <?= h($rat['local_instalacao']) ?>
        </div>

        <div class="campo">
            <strong>Tipo de serviço:</strong> <?= h($rat['tipo']) ?>
        </div>
    </div>
</div>

<div class="secao">
    <h2>3. Problema relatado pelo cliente</h2>
    <div class="texto"><?= h($rat['problema_relatado']) ?></div>
</div>

<div class="secao">
    <h2>4. Diagnóstico técnico</h2>
    <div class="texto"><?= h($rat['diagnostico_tecnico']) ?></div>
</div>

<div class="secao">
    <h2>5. Serviços executados</h2>
    <div class="texto"><?= h($rat['servicos_executados']) ?></div>
</div>

<div class="secao">
    <h2>6. Peças e materiais utilizados</h2>
    <div class="texto"><?= h($rat['materiais_utilizados']) ?></div>
</div>

<div class="secao">
    <h2>7. Recomendações técnicas</h2>
    <div class="texto"><?= h($rat['recomendacoes']) ?></div>
</div>

<div class="secao">
    <h2>8. Observações adicionais</h2>
    <div class="texto"><?= h($rat['observacoes']) ?></div>
</div>

<div class="assinaturas">
    <div class="linha">
        <?= h($rat['tecnico_responsavel']) ?><br>
        Técnico responsável
    </div>

    <div class="linha">
        <?= h($rat['responsavel_cliente']) ?><br>
        Responsável pelo cliente
    </div>
</div>

<div class="rodape">
    ❄ A3 Climatização - Relatório de Atendimento Técnico |
    OS #<?= (int)$rat['ordem_servico_id'] ?> |
    Emitido em <?= date('d/m/Y H:i') ?>
</div>

</body>
</html>
