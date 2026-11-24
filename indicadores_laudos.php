
<?php
require_once 'config.php';

function h($valor) {
    return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

// Indicadores gerais
$total = (int)$pdo->query("
    SELECT COUNT(*) FROM laudos_condenacao
")->fetchColumn();

$finalizados = (int)$pdo->query("
    SELECT COUNT(*) FROM laudos_condenacao
    WHERE status = 'Finalizado'
")->fetchColumn();

$elaboracao = (int)$pdo->query("
    SELECT COUNT(*) FROM laudos_condenacao
    WHERE status = 'Em elaboração'
")->fetchColumn();

$clientes = (int)$pdo->query("
    SELECT COUNT(DISTINCT cliente_id)
    FROM laudos_condenacao
")->fetchColumn();

// Laudos por mês nos últimos 12 meses
$mensal = $pdo->query("
    SELECT
        DATE_FORMAT(data_laudo, '%Y-%m') AS mes,
        COUNT(*) AS quantidade
    FROM laudos_condenacao
    WHERE data_laudo >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
    GROUP BY DATE_FORMAT(data_laudo, '%Y-%m')
    ORDER BY mes
")->fetchAll();

// Clientes com mais laudos
$ranking = $pdo->query("
    SELECT
        c.nome,
        COUNT(l.id) AS quantidade
    FROM laudos_condenacao l
    INNER JOIN clientes c ON c.id = l.cliente_id
    GROUP BY c.id, c.nome
    ORDER BY quantidade DESC
    LIMIT 10
")->fetchAll();

// Últimos laudos
$ultimos = $pdo->query("
    SELECT
        l.id,
        l.numero_laudo,
        l.data_laudo,
        l.status,
        c.nome AS cliente,
        e.codigo AS equipamento
    FROM laudos_condenacao l
    INNER JOIN clientes c ON c.id = l.cliente_id
    INNER JOIN equipamentos e ON e.id = l.equipamento_id
    ORDER BY l.id DESC
    LIMIT 10
")->fetchAll();

$meses = [
    '01'=>'Jan','02'=>'Fev','03'=>'Mar',
    '04'=>'Abr','05'=>'Mai','06'=>'Jun',
    '07'=>'Jul','08'=>'Ago','09'=>'Set',
    '10'=>'Out','11'=>'Nov','12'=>'Dez'
];
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Indicadores de Laudos | ClimaGestão</title>

<style>
* { box-sizing: border-box; }

body {
    margin: 0;
    padding: 25px;
    background: #f1f5f9;
    font-family: Arial, sans-serif;
    color: #1e293b;
}

.container {
    max-width: 1200px;
    margin: auto;
}

.topo {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 25px;
}

h1 { margin: 0; }

.subtitulo {
    color: #64748b;
    margin-top: 7px;
}

.cards {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    margin-bottom: 25px;
}

.card {
    background: white;
    padding: 22px;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,.04);
}

.card small {
    color: #64748b;
}

.numero {
    display: block;
    font-size: 32px;
    font-weight: bold;
    margin-top: 12px;
}

.conteudo {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 25px;
}

.painel {
    background: white;
    padding: 22px;
    border-radius: 10px;
    overflow-x: auto;
}

h2 {
    font-size: 18px;
    margin-top: 0;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 400px;
}

td, th {
    padding: 12px 8px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
    font-size: 13px;
}

th { background: #f8fafc; }

.barra {
    background: #e2e8f0;
    height: 10px;
    border-radius: 10px;
    overflow: hidden;
    margin-top: 5px;
}

.preenchimento {
    height: 100%;
    background: #2563eb;
}

.botao {
    display: inline-block;
    padding: 11px 16px;
    border-radius: 7px;
    background: #2563eb;
    color: white;
    text-decoration: none;
    font-weight: bold;
}

.acao {
    color: #2563eb;
    text-decoration: none;
    font-weight: bold;
}

.status {
    font-size: 12px;
    font-weight: bold;
}

@media(max-width:850px) {
    .cards { grid-template-columns: repeat(2, 1fr); }
    .conteudo { grid-template-columns: 1fr; }
}

@media(max-width:500px) {
    body { padding: 12px; }
    .cards { grid-template-columns: 1fr 1fr; }
}
</style>
</head>

<body>

<div class="container">

<div class="topo">
    <div>
        <h1>Indicadores de Laudos</h1>
        <div class="subtitulo">
            Gestão e acompanhamento das avaliações técnicas
        </div>
    </div>

    <a href="laudo_condenacao.php" class="botao">
        + Novo Laudo
    </a>
</div>

<div class="cards">

    <div class="card">
        <small>Total de laudos</small>
        <span class="numero"><?= $total ?></span>
    </div>

    <div class="card">
        <small>Finalizados</small>
        <span class="numero"><?= $finalizados ?></span>
    </div>

    <div class="card">
        <small>Em elaboração</small>
        <span class="numero"><?= $elaboracao ?></span>
    </div>

    <div class="card">
        <small>Clientes avaliados</small>
        <span class="numero"><?= $clientes ?></span>
    </div>

</div>

<div class="conteudo">

    <div class="painel">
        <h2>Laudos por mês</h2>

        <?php if (!$mensal): ?>
            <p>Nenhum laudo registrado no período.</p>
        <?php else: ?>

            <?php
            $maximo = max(array_column($mensal, 'quantidade'));
            ?>

            <?php foreach ($mensal as $item): ?>
                <?php
                $partes = explode('-', $item['mes']);
                $nomeMes = $meses[$partes[1]] . '/' . $partes[0];
                $largura = ($item['quantidade'] / $maximo) * 100;
                ?>

                <div style="margin-bottom:15px">
                    <strong><?= h($nomeMes) ?></strong>
                    <span style="float:right">
                        <?= (int)$item['quantidade'] ?>
                    </span>

                    <div class="barra">
                        <div class="preenchimento"
                             style="width:<?= $largura ?>%">
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

        <?php endif; ?>
    </div>

    <div class="painel">
        <h2>Clientes com mais laudos</h2>

        <?php if (!$ranking): ?>
            <p>Nenhum cliente avaliado.</p>
        <?php else: ?>

        <table>
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th>Quantidade</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($ranking as $item): ?>
                <tr>
                    <td><?= h($item['nome']) ?></td>
                    <td><strong><?= (int)$item['quantidade'] ?></strong></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php endif; ?>
    </div>

</div>

<div class="painel">
    <h2>Últimos laudos cadastrados</h2>

    <table>
        <thead>
            <tr>
                <th>Número</th>
                <th>Data</th>
                <th>Cliente</th>
                <th>Equipamento</th>
                <th>Status</th>
                <th>Ação</th>
            </tr>
        </thead>

        <tbody>
        <?php if (!$ultimos): ?>
            <tr>
                <td colspan="6">Nenhum laudo cadastrado.</td>
            </tr>
        <?php else: ?>

            <?php foreach ($ultimos as $item): ?>
            <tr>
                <td><?= h($item['numero_laudo']) ?></td>

                <td>
                    <?= date('d/m/Y', strtotime($item['data_laudo'])) ?>
                </td>

                <td><?= h($item['cliente']) ?></td>

                <td><?= h($item['equipamento']) ?></td>

                <td><?= h($item['status']) ?></td>

                <td>
                    <a class="acao"
                       href="imprimir_laudo_condenacao.php?id=<?= (int)$item['id'] ?>"
                       target="_blank">
                       Abrir
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>

        <?php endif; ?>
        </tbody>
    </table>
</div>

<br>

<a href="listar_laudos.php" class="botao">
    Consultar todos os laudos
</a>

<a href="index.php" class="botao">
    Voltar ao painel principal
</a>

</div>

</body>
</html>
