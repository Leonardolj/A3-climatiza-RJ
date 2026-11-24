
<?php
require_once 'config.php';

$mensagem = '';

function h($valor) {
    return htmlspecialchars(
        (string)($valor ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// Buscar clientes cadastrados
$clientes = $pdo->query(
    "SELECT id, nome FROM clientes ORDER BY nome ASC"
)->fetchAll();

// Cadastrar equipamento
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $clienteId = filter_input(
            INPUT_POST,
            'cliente_id',
            FILTER_VALIDATE_INT
        );

        if (!$clienteId) {
            throw new Exception('Selecione um cliente válido.');
        }

        $codigo = trim($_POST['codigo'] ?? '');

        if ($codigo === '') {
            throw new Exception('Informe o código patrimonial.');
        }

        $sql = "INSERT INTO equipamentos
        (
            codigo, cliente, cliente_id, local_instalacao,
            marca, modelo, capacidade_btu, tipo,
            numero_serie, data_instalacao, status, observacoes
        )
        VALUES
        (
            :codigo, :cliente, :cliente_id, :local,
            :marca, :modelo, :btu, :tipo,
            :serie, :data, :status, :obs
        )";

        // Confirmar que o cliente existe
        $busca = $pdo->prepare(
            "SELECT nome FROM clientes WHERE id = ?"
        );
        $busca->execute([$clienteId]);
        $cliente = $busca->fetchColumn();

        if (!$cliente) {
            throw new Exception('Cliente não encontrado.');
        }

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            ':codigo' => $codigo,
            ':cliente' => $cliente,
            ':cliente_id' => $clienteId,
            ':local' => trim($_POST['local'] ?? ''),
            ':marca' => trim($_POST['marca'] ?? ''),
            ':modelo' => trim($_POST['modelo'] ?? ''),
            ':btu' => ($_POST['btu'] ?? '') !== ''
                ? (int)$_POST['btu'] : null,
            ':tipo' => $_POST['tipo'] ?? 'Split',
            ':serie' => trim($_POST['serie'] ?? ''),
            ':data' => ($_POST['data'] ?? '') !== ''
                ? $_POST['data'] : null,
            ':status' => $_POST['status'] ?? 'Ativo',
            ':obs' => trim($_POST['obs'] ?? '')
        ]);

        header('Location: equipamentos.php?sucesso=1');
        exit;

    } catch (Throwable $e) {
        $mensagem = 'Erro ao cadastrar: ' . $e->getMessage();
    }
}

if (isset($_GET['sucesso'])) {
    $mensagem = 'Equipamento cadastrado com sucesso!';
}

// Buscar equipamentos e nome dos clientes
$equipamentos = $pdo->query(
    "SELECT
        e.*,
        COALESCE(c.nome, e.cliente) AS nome_cliente
     FROM equipamentos e
     LEFT JOIN clientes c ON c.id = e.cliente_id
     ORDER BY e.id DESC"
)->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ClimaGestão - Equipamentos</title>

<style>
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

.formulario, .lista {
    background: white;
    padding: 25px;
    border-radius: 12px;
    margin-bottom: 25px;
}

form {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
}

label {
    font-size: 14px;
    font-weight: bold;
}

input, select, textarea {
    width: 100%;
    padding: 11px;
    margin-top: 7px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    box-sizing: border-box;
}

textarea {
    min-height: 80px;
}

.completo {
    grid-column: 1 / -1;
}

button {
    background: #2563eb;
    color: white;
    border: 0;
    padding: 13px 20px;
    border-radius: 7px;
    cursor: pointer;
    font-weight: bold;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 700px;
}

th, td {
    text-align: left;
    padding: 13px;
    border-bottom: 1px solid #e2e8f0;
}

th {
    color: #64748b;
}

.tabela {
    overflow-x: auto;
}

.mensagem {
    padding: 12px;
    background: #dcfce7;
    margin-bottom: 15px;
    border-radius: 6px;
}

@media(max-width: 650px) {
    form {
        grid-template-columns: 1fr;
    }

    .completo {
        grid-column: auto;
    }
}
</style>
</head>

<body>

<header>
    <h1>❄ A3 Climatização</h1>
    <p>Cadastro e controle de equipamentos</p>
    <a href="index.php" style="color:white">
        Voltar ao painel
    </a>
</header>

<main>

<section class="formulario">
    <h2>Novo equipamento</h2>

    <?php if ($mensagem): ?>
        <p class="mensagem"><?= h($mensagem) ?></p>
    <?php endif; ?>

    <?php if (!$clientes): ?>
        <p>
            Nenhum cliente cadastrado.
            <a href="clientes.php">Cadastrar cliente primeiro</a>.
        </p>
    <?php else: ?>

    <form method="POST">

        <label>
            Código patrimonial
            <input name="codigo" required>
        </label>

        <label>
            Cliente
            <select name="cliente_id" required>
                <option value="">Selecione um cliente</option>

                <?php foreach ($clientes as $cliente): ?>
                    <option value="<?= h($cliente['id']) ?>">
                        <?= h($cliente['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            Local de instalação
            <input name="local">
        </label>

        <label>
            Marca
            <input name="marca">
        </label>

        <label>
            Modelo
            <input name="modelo">
        </label>

        <label>
            Capacidade BTU
            <input type="number" name="btu" min="1">
        </label>

        <label>
            Tipo
            <select name="tipo">
                <option value="Split">Split</option>
                <option value="Janela">Janela</option>
                <option value="Cassete">Cassete</option>
                <option value="Piso Teto">Piso Teto</option>
                <option value="VRF">VRF</option>
                <option value="Outro">Outro</option>
            </select>
        </label>

        <label>
            Número de série
            <input name="serie">
        </label>

        <label>
            Data de instalação
            <input type="date" name="data">
        </label>

        <label>
            Status
            <select name="status">
                <option>Ativo</option>
                <option>Inativo</option>
                <option>Em manutenção</option>
            </select>
        </label>

        <label class="completo">
            Observações técnicas
            <textarea name="obs"></textarea>
        </label>

        <div class="completo">
            <button type="submit">
                Cadastrar equipamento
            </button>
        </div>

    </form>

    <?php endif; ?>
</section>

<section class="lista">
    <h2>Equipamentos cadastrados</h2>

    <div class="tabela">
    <table>
        <thead>
            <tr>
                <th>Código</th>
                <th>Cliente</th>
                <th>Marca</th>
                <th>Modelo</th>
                <th>BTU</th>
                <th>Status</th>
            </tr>
        </thead>

        <tbody>
        <?php foreach ($equipamentos as $eq): ?>
            <tr>
                <td><?= h($eq['codigo']) ?></td>
                <td><?= h($eq['nome_cliente']) ?></td>
                <td><?= h($eq['marca']) ?></td>
                <td><?= h($eq['modelo']) ?></td>
                <td><?= h($eq['capacidade_btu']) ?></td>
                <td><?= h($eq['status']) ?></td>
            </tr>
        <?php endforeach; ?>

        <?php if (!$equipamentos): ?>
            <tr>
                <td colspan="6">Nenhum equipamento cadastrado.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</section>

</main>
</body>
</html>
