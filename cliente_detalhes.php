
<?php
require_once 'config.php';

function h($valor) {
    return htmlspecialchars(
        (string)($valor ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function dataBR($data) {
    if (!$data) return 'Não informada';
    return date('d/m/Y', strtotime($data));
}

// Identificar cliente
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    die('Cliente inválido.');
}

// Buscar dados do cliente
$stmt = $pdo->prepare(
    "SELECT * FROM clientes WHERE id = ?"
);
$stmt->execute([$id]);
$cliente = $stmt->fetch();

if (!$cliente) {
    die('Cliente não encontrado.');
}

// Buscar equipamentos vinculados ao cliente.
// Também considera equipamentos antigos associados pelo nome.
$stmt = $pdo->prepare(
    "SELECT *
     FROM equipamentos
     WHERE cliente_id = :cliente_id
        OR (cliente_id IS NULL AND cliente = :nome_cliente)
     ORDER BY id DESC"
);

$stmt->execute([
    ':cliente_id' => $id,
    ':nome_cliente' => $cliente['nome']
]);

$equipamentos = $stmt->fetchAll();

// Buscar ordens de serviço dos equipamentos do cliente
$stmt = $pdo->prepare(
    "SELECT
        os.*,
        e.codigo,
        e.marca,
        e.modelo,
        e.local_instalacao
     FROM ordens_servico os
     INNER JOIN equipamentos e
        ON e.id = os.equipamento_id
     WHERE
        e.cliente_id = :cliente_id
        OR (
            e.cliente_id IS NULL
            AND e.cliente = :nome_cliente
        )
     ORDER BY
        COALESCE(os.data_agendada, DATE(os.criado_em)) DESC,
        os.id DESC"
);

$stmt->execute([
    ':cliente_id' => $id,
    ':nome_cliente' => $cliente['nome']
]);

$ordens = $stmt->fetchAll();

// Indicadores
$totalEquipamentos = count($equipamentos);
$totalOS = count($ordens);
$osAbertas = 0;
$osConcluidas = 0;

foreach ($ordens as $os) {
    if (in_array($os['status'], ['Aberta', 'Em andamento'], true)) {
        $osAbertas++;
    }

    if ($os['status'] === 'Concluída') {
        $osConcluidas++;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Ficha do Cliente - ClimaGestão</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f1f5f9;
    color: #1e293b;
}

header {
    background: #10243e;
    color: white;
    padding: 22px;
}

main {
    max-width: 1150px;
    margin: 30px auto;
    padding: 0 18px;
}

.card {
    background: white;
    padding: 22px;
    border-radius: 12px;
    margin-bottom: 22px;
    box-shadow: 0 2px 8px #00000010;
}

.resumo {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
}

.numero {
    font-size: 30px;
    font-weight: bold;
    color: #2563eb;
}

.dados {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
}

.tabela {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 700px;
}

th, td {
    text-align: left;
    padding: 12px;
    border-bottom: 1px solid #e2e8f0;
}

th {
    background: #eaf2f8;
}

a {
    color: #2563eb;
    text-decoration: none;
}

.botao {
    display: inline-block;
    background: #2563eb;
    color: white;
    padding: 10px 15px;
    border-radius: 6px;
    margin: 5px 5px 5px 0;
}

.vazio {
    color: #64748b;
    padding: 15px 0;
}

@media(max-width: 650px) {
    .resumo, .dados {
        grid-template-columns: 1fr;
    }
}
</style>
</head>

<body>

<header>
    <h1>❄ A3 Climatização</h1>
    <p>Ficha completa do cliente</p>
    <a href="clientes.php" style="color:white">
        ← Voltar para clientes
    </a>
</header>

<main>

<section class="card">
    <h2><?= h($cliente['nome']) ?></h2>

    <div class="dados">
        <div>
            <strong>Tipo:</strong>
            <?= h($cliente['tipo']) ?>
        </div>

        <div>
            <strong>CPF / CNPJ:</strong>
            <?= h($cliente['documento']) ?>
        </div>

        <div>
            <strong>Telefone:</strong>
            <?= h($cliente['telefone']) ?>
        </div>

        <div>
            <strong>E-mail:</strong>
            <?= h($cliente['email']) ?>
        </div>

        <div>
            <strong>Endereço:</strong>
            <?= h($cliente['endereco']) ?>
        </div>

        <div>
            <strong>Cidade / Estado:</strong>
            <?= h($cliente['cidade']) ?> /
            <?= h($cliente['estado']) ?>
        </div>
    </div>

    <?php if (!empty($cliente['observacoes'])): ?>
        <p>
            <strong>Observações:</strong>
            <?= nl2br(h($cliente['observacoes'])) ?>
        </p>
    <?php endif; ?>

    <a class="botao" href="equipamentos.php">
        + Cadastrar equipamento
    </a>

    <a class="botao" href="ordens_servico.php">
        + Nova ordem de serviço
    </a>
</section>

<section class="resumo">

    <div class="card">
        <div class="numero"><?= $totalEquipamentos ?></div>
        <strong>Equipamentos</strong>
    </div>

    <div class="card">
        <div class="numero"><?= $totalOS ?></div>
        <strong>Total de OS</strong>
    </div>

    <div class="card">
        <div class="numero"><?= $osAbertas ?></div>
        <strong>OS em aberto ou andamento</strong>
    </div>

</section>

<section class="card">
    <h2>❄ Equipamentos do cliente</h2>

    <div class="tabela">
    <table>
        <thead>
            <tr>
                <th>Código</th>
                <th>Marca</th>
                <th>Modelo</th>
                <th>Capacidade BTU</th>
                <th>Local</th>
                <th>Status</th>
            </tr>
        </thead>

        <tbody>
        <?php foreach ($equipamentos as $eq): ?>
            <tr>
                <td><?= h($eq['codigo']) ?></td>
                <td><?= h($eq['marca']) ?></td>
                <td><?= h($eq['modelo']) ?></td>
                <td><?= h($eq['capacidade_btu']) ?></td>
                <td><?= h($eq['local_instalacao']) ?></td>
                <td><?= h($eq['status']) ?></td>
            </tr>
        <?php endforeach; ?>

        <?php if (!$equipamentos): ?>
            <tr>
                <td colspan="6" class="vazio">
                    Nenhum equipamento vinculado a este cliente.
                </td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</section>

<section class="card">
    <h2>📋 Histórico de ordens de serviço</h2>

    <div class="tabela">
    <table>
        <thead>
            <tr>
                <th>OS</th>
                <th>Equipamento</th>
                <th>Tipo</th>
                <th>Técnico</th>
                <th>Data agendada</th>
                <th>Status</th>
                <th>Detalhes</th>
            </tr>
        </thead>

        <tbody>
        <?php foreach ($ordens as $os): ?>
            <tr>
                <td><?= h($os['id']) ?></td>

                <td>
                    <?= h($os['codigo']) ?>
                    - <?= h($os['modelo']) ?>
                </td>

                <td><?= h($os['tipo']) ?></td>

                <td><?= h($os['tecnico']) ?></td>

                <td><?= h(dataBR($os['data_agendada'])) ?></td>

                <td><?= h($os['status']) ?></td>

                <td>
                    <a href="imprimir_os.php?id=<?= h($os['id']) ?>"
                       target="_blank">
                        Imprimir OS
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>

        <?php if (!$ordens): ?>
            <tr>
                <td colspan="7" class="vazio">
                    Nenhuma ordem de serviço encontrada.
                </td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</section>

<section class="card">
    <h2>Resumo do histórico</h2>

    <p>
        <strong>Ordens concluídas:</strong>
        <?= $osConcluidas ?>
    </p>

    <p>
        <strong>Ordens abertas ou em andamento:</strong>
        <?= $osAbertas ?>
    </p>
</section>

</main>
</body>
</html>
