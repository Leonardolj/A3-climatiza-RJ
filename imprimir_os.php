
<?php
require_once 'config.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    die('Ordem de serviço inválida.');
}

$sql = "SELECT
            os.*,
            e.codigo,
            e.cliente,
            e.local_instalacao,
            e.marca,
            e.modelo,
            e.capacidade_btu,
            e.numero_serie
        FROM ordens_servico os
        INNER JOIN equipamentos e
            ON e.id = os.equipamento_id
        WHERE os.id = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$os = $stmt->fetch();

if (!$os) {
    die('Ordem de serviço não encontrada.');
}

function h($valor) {
    return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

function dataBR($data) {
    if (!$data) return '';
    return date('d/m/Y', strtotime($data));
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ordem de Serviço <?= h($os['id']) ?></title>

<style>
    body {
        font-family: Arial, sans-serif;
        color: #222;
        margin: 25px;
    }

    .folha {
        max-width: 850px;
        margin: auto;
        border: 1px solid #333;
        padding: 25px;
    }

    h1 {
        text-align: center;
        margin-bottom: 5px;
    }

    .subtitulo {
        text-align: center;
        margin-bottom: 25px;
    }

    .numero {
        text-align: right;
        font-weight: bold;
    }

    .secao {
        background: #eaeaea;
        padding: 8px;
        font-weight: bold;
        margin-top: 18px;
        border: 1px solid #ccc;
    }

    .campo {
        border: 1px solid #ccc;
        padding: 10px;
        min-height: 20px;
    }

    .linha {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        margin-top: 10px;
    }

    .assinaturas {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 40px;
        margin-top: 75px;
        text-align: center;
    }

    .assinatura {
        border-top: 1px solid #333;
        padding-top: 8px;
    }

    .botoes {
        text-align: center;
        margin: 20px;
    }

    button {
        padding: 12px 25px;
        background: #145da0;
        color: white;
        border: none;
        cursor: pointer;
        border-radius: 5px;
    }

    @media print {
        body {
            margin: 0;
        }

        .folha {
            border: none;
            max-width: none;
        }

        .botoes {
            display: none;
        }

        @page {
            size: A4;
            margin: 12mm;
        }

        @media print {

    @page {
        size: A4 portrait;
        margin: 8mm;
    }

    html, body {
        width: 100%;
        margin: 0;
        padding: 0;
    }

    .ordem-servico {
        width: 100%;
        max-width: 100%;
        margin: 0;
        padding: 5mm;
        box-sizing: border-box;
        font-size: 10px;
    }

    .ordem-servico h1 {
        font-size: 16px;
    }

    .ordem-servico h2 {
        font-size: 12px;
    }

    .ordem-servico p {
        margin: 4px 0;
    }

    .ordem-servico section {
        margin-bottom: 8px;
        padding: 5px;
    }

    .assinaturas {
        margin-top: 35px;
        page-break-inside: avoid;
    }

    * {
        box-sizing: border-box;
    }
}
    }
</style>
</head>

<body>

<div class="botoes">
    <button onclick="window.print()">Imprimir Ordem de Serviço</button>
</div>

<div class="folha">

    <h1>A3 climatização</h1>
    <div class="subtitulo">ORDEM DE SERVIÇO - REFRIGERAÇÃO E CLIMATIZAÇÃO</div>

    <div class="numero">
        OS Nº <?= h($os['id']) ?>
    </div>

    <div class="secao">1. DADOS DO CLIENTE E LOCAL</div>

    <div class="campo">
        <strong>Cliente:</strong> <?= h($os['cliente']) ?>
    </div>

    <div class="campo">
        <strong>setor de execução:</strong>
        <?= h($os['local_instalacao']) ?>
    </div>

    <div class="secao">2. IDENTIFICAÇÃO DO EQUIPAMENTO</div>

    <div class="linha">
        <div class="campo">
            <strong>Código:</strong> <?= h($os['codigo']) ?>
        </div>
        <div class="campo">
            <strong>Marca:</strong> <?= h($os['marca']) ?>
        </div>
    </div>

    <div class="linha">
        <div class="campo">
            <strong>Modelo:</strong> <?= h($os['modelo']) ?>
        </div>
        <div class="campo">
            <strong>Capacidade:</strong>
            <?= h($os['capacidade_btu']) ?> BTUs
        </div>
    </div>

    <div class="campo">
        <strong>Número de série:</strong>
        <?= h($os['numero_serie']) ?>
    </div>

    <div class="secao">3. INFORMAÇÕES DO SERVIÇO</div>

    <div class="linha">
        <div class="campo">
            <strong>Tipo:</strong> <?= h($os['tipo']) ?>
        </div>
        <div class="campo">
            <strong>Prioridade:</strong> <?= h($os['prioridade']) ?>
        </div>
    </div>

    <div class="linha">
        <div class="campo">
            <strong>Técnico:</strong> <?= h($os['tecnico']) ?>
        </div>
        <div class="campo">
            <strong>Data agendada:</strong>
            <?= dataBR($os['data_agendada']) ?>
        </div>
    </div>

    <div class="campo">
        <strong>Status:</strong> <?= h($os['status']) ?>
    </div>

    <div class="secao">4. DESCRIÇÃO DO SERVIÇO</div>

    <div class="campo" style="min-height: 90px;">
        <?= nl2br(h($os['descricao'])) ?>
    </div>

    <div class="secao">5. OBSERVAÇÕES E SERVIÇOS EXECUTADOS</div>

    <div class="campo" style="min-height: 100px;">
        <?= nl2br(h($os['observacoes'])) ?>
    </div>

    <div class="assinaturas">
        <div class="assinatura">
            Assinatura do técnico
        </div>

        <div class="assinatura">
            Assinatura do responsável com carimbo
        </div>
    </div>

</div>

</body>
</html>
