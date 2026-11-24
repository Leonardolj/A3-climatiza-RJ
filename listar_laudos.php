
<?php
require_once 'config.php';

function h($valor) {
    return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

$pesquisa = trim($_GET['pesquisa'] ?? '');
$status = $_GET['status'] ?? '';

$sql = "
    SELECT
        l.id,
        l.numero_laudo,
        l.data_laudo,
        l.unidade,
        l.ambiente,
        l.responsavel_tecnico,
        l.status,
        c.nome AS cliente_nome,
        e.codigo AS equipamento_codigo,
        e.marca,
        e.modelo,
        e.capacidade_btu
    FROM laudos_condenacao l
    INNER JOIN clientes c ON c.id = l.cliente_id
    INNER JOIN equipamentos e ON e.id = l.equipamento_id
    WHERE 1 = 1
";

$params = [];

if ($pesquisa !== '') {
    $sql .= "
        AND (
            l.numero_laudo LIKE :pesquisa1
            OR c.nome LIKE :pesquisa2
            OR e.codigo LIKE :pesquisa3
            OR e.marca LIKE :pesquisa4
            OR e.modelo LIKE :pesquisa5
            OR l.unidade LIKE :pesquisa6
        )
    ";

    $termo = '%' . $pesquisa . '%';

    for ($i = 1; $i <= 6; $i++) {
        $params['pesquisa' . $i] = $termo;
    }
}

if (in_array($status, ['Em elaboração', 'Finalizado'], true)) {
    $sql .= " AND l.status = :status";
    $params['status'] = $status;
}

$sql .= " ORDER BY l.data_laudo DESC, l.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$laudos = $stmt->fetchAll();

$total = count($laudos);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Laudos de Condenação | ClimaGestão</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    padding: 25px;
    background: #f1f5f9;
    font-family: Arial, sans-serif;
    color: #1e293b;
}

.container {
    max-width: 1250px;
    margin: auto;
}

.topo {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
    margin-bottom: 25px;
}

h1 {
    margin: 0;
    color: #0f172a;
}

.subtitulo {
    color: #64748b;
    margin-top: 7px;
}

.botao {
    display: inline-block;
    padding: 11px 16px;
    border-radius: 7px;
    text-decoration: none;
    font-weight: bold;
    border: none;
    cursor: pointer;
    font-size: 14px;
}

.novo {
    background: #2563eb;
    color: white;
}

.imprimir {
    background: #0f766e;
    color: white;
}

.filtros {
    background: white;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

input, select {
    padding: 11px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 14px;
}

input {
    flex: 1;
    min-width: 220px;
}

.filtrar {
    background: #334155;
    color: white;
}

.limpar {
    background: #e2e8f0;
    color: #334155;
}

.resumo {
    background: white;
    padding: 17px;
    border-radius: 10px;
    margin-bottom: 20px;
    font-weight: bold;
}

.tabela-container {
    background: white;
    padding: 15px;
    border-radius: 10px;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 950px;
}

th, td {
    padding: 13px 10px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
    font-size: 13px;
}

th {
    background: #f8fafc;
    color: #334155;
}

.status {
    display: inline-block;
    padding: 6px 9px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}

.em-elaboracao {
    background: #fef3c7;
    color: #92400e;
}

.finalizado {
    background: #dcfce7;
    color: #166534;
}

.acao {
    display: inline-block;
    padding: 7px 10px;
    margin: 2px;
    border-radius: 5px;
    background: #e0f2fe;
    color: #075985;
    text-decoration: none;
    font-weight: bold;
    white-space: nowrap;
}

.vazio {
    padding: 35px;
    text-align: center;
    color: #64748b;
}

@media (max-width: 600px) {
    body {
        padding: 12px;
    }

    .topo {
        align-items: flex-start;
    }
}
</style>
</head>

<body>

<div class="container">

    <div class="topo">
        <div>
            <h1>Laudos de Condenação</h1>
            <div class="subtitulo">
                Histórico e gerenciamento dos laudos técnicos
            </div>
        </div>

        <a href="laudo_condenacao.php" class="botao novo">
            + Novo Laudo
        </a>
    </div>

    <form method="GET" class="filtros">

        <input
            type="text"
            name="pesquisa"
            placeholder="Pesquisar número, cliente ou equipamento..."
            value="<?= h($pesquisa) ?>"
        >

        <select name="status">
            <option value="">Todos os status</option>

            <option value="Em elaboração"
                <?= $status === 'Em elaboração' ? 'selected' : '' ?>>
                Em elaboração
            </option>

            <option value="Finalizado"
                <?= $status === 'Finalizado' ? 'selected' : '' ?>>
                Finalizado
            </option>
        </select>

        <button type="submit" class="botao filtrar">
            Pesquisar
        </button>

        <a href="listar_laudos.php" class="botao limpar">
            Limpar
        </a>

    </form>

    <div class="resumo">
        Total de laudos encontrados: <?= $total ?>
    </div>

    <div class="tabela-container">

        <?php if ($total > 0): ?>

        <table>
            <thead>
                <tr>
                    <th>Número</th>
                    <th>Data</th>
                    <th>Cliente</th>
                    <th>Equipamento</th>
                    <th>Unidade</th>
                    <th>Responsável técnico</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>

            <tbody>

            <?php foreach ($laudos as $laudo): ?>

                <tr>
                    <td>
                        <strong><?= h($laudo['numero_laudo']) ?></strong>
                    </td>

                    <td>
                        <?= $laudo['data_laudo']
                            ? date('d/m/Y', strtotime($laudo['data_laudo']))
                            : '-' ?>
                    </td>

                    <td><?= h($laudo['cliente_nome']) ?></td>

                    <td>
                        <?= h($laudo['equipamento_codigo']) ?><br>
                        <?= h($laudo['marca']) ?>
                        <?= h($laudo['modelo']) ?>
                        <br>
                        <?= h($laudo['capacidade_btu']) ?> BTU/h
                    </td>

                    <td><?= h($laudo['unidade']) ?></td>

                    <td><?= h($laudo['responsavel_tecnico']) ?></td>

                    <td>
                        <?php if ($laudo['status'] === 'Finalizado'): ?>
                            <span class="status finalizado">
                                Finalizado
                            </span>
                        <?php else: ?>
                            <span class="status em-elaboracao">
                                Em elaboração
                            </span>
                        <?php endif; ?>
                    </td>

                    <td>
                        <a
                            class="acao"
                            href="imprimir_laudo_condenacao.php?id=<?= (int)$laudo['id'] ?>"
                            target="_blank"
                        >
                            Imprimir
                        </a>

                        <?php if ($laudo['status'] === 'Em elaboração'): ?>
    <a class="acao"
       href="editar_laudo_condenacao.php?id=<?= (int)$laudo['id'] ?>">
       Editar
    </a>
<?php endif; ?>
                    </td>
                </tr>

            <?php endforeach; ?>

            </tbody>
        </table>

        <?php else: ?>

            <div class="vazio">
                Nenhum laudo encontrado.
            </div>

        <?php endif; ?>

    </div>

    <br>

    <a href="index.php" class="botao limpar">
        ← Voltar ao painel principal
    </a>

</div>

</body>
</html>
