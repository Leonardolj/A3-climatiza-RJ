
<?php
require_once 'config.php';

$mensagem = '';
$erro = '';

// Cadastrar uma nova ordem de serviço
if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['acao'] ?? '') === 'cadastrar') {

    try {
        $sql = "INSERT INTO ordens_servico
        (equipamento_id, tipo, prioridade, descricao,
         tecnico, data_agendada, status, observacoes)
        VALUES
        (:equipamento, :tipo, :prioridade, :descricao,
         :tecnico, :data, 'Aberta', :observacoes)";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            ':equipamento' => $_POST['equipamento_id'],
            ':tipo' => $_POST['tipo'],
            ':prioridade' => $_POST['prioridade'],
            ':descricao' => $_POST['descricao'],
            ':tecnico' => $_POST['tecnico'],
            ':data' => $_POST['data_agendada'] ?: null,
            ':observacoes' => $_POST['observacoes']
        ]);

        $mensagem = 'Ordem de serviço cadastrada com sucesso!';

    } catch (PDOException $e) {
        $erro = 'Não foi possível cadastrar a ordem de serviço. Verifique os dados.';
    }
}

// Atualizar o status de uma ordem
if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['acao'] ?? '') === 'atualizar') {

    $statusPermitidos = [
        'Aberta',
        'Em andamento',
        'Concluída',
        'Cancelada'
    ];

    $novoStatus = $_POST['status'] ?? '';

    if (in_array($novoStatus, $statusPermitidos, true)) {
        try {
            $sql = "UPDATE ordens_servico
                    SET status = :status,
                        data_conclusao = CASE
                            WHEN :status_data = 'Concluída'
                            THEN CURDATE()
                            ELSE NULL
                        END
                    WHERE id = :id";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':status' => $novoStatus,
                ':status_data' => $novoStatus,
                ':id' => (int) $_POST['os_id']
            ]);

            $mensagem = 'Status da ordem atualizado!';

        } catch (PDOException $e) {
            $erro = 'Erro ao atualizar a ordem de serviço.';
        }
    } else {
        $erro = 'Status inválido.';
    }
}

// Buscar equipamentos para o formulário
$equipamentos = $pdo->query(
    "SELECT id, codigo, cliente
     FROM equipamentos
     ORDER BY codigo"
)->fetchAll();

// Buscar ordens de serviço
$ordens = $pdo->query("
    SELECT os.*, e.codigo, e.cliente, e.local_instalacao
    FROM ordens_servico os
    INNER JOIN equipamentos e
        ON e.id = os.equipamento_id
    ORDER BY os.id DESC
")->fetchAll();

function escapar($valor) {
    return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ClimaGestão - Ordens de Serviço</title>

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

header a {
    color: white;
}

main {
    max-width: 1250px;
    margin: 30px auto;
    padding: 0 18px;
}

.bloco {
    background: white;
    padding: 25px;
    border-radius: 12px;
    margin-bottom: 25px;
}

form.cadastro {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
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
    font: inherit;
}

textarea {
    min-height: 90px;
    resize: vertical;
}

.completo {
    grid-column: 1 / -1;
}

button {
    border: 0;
    border-radius: 6px;
    padding: 10px 14px;
    cursor: pointer;
    font-weight: bold;
    background: #2563eb;
    color: white;
}

.botao-status {
    background: #0f766e;
}

.mensagem, .erro {
    padding: 13px;
    border-radius: 7px;
    margin-bottom: 18px;
}

.mensagem {
    background: #dcfce7;
    color: #166534;
}

.erro {
    background: #fee2e2;
    color: #991b1b;
}

.tabela {
    overflow-x: auto;
}

table {
    width: 100%;
    min-width: 950px;
    border-collapse: collapse;
    margin-top: 18px;
}

th, td {
    padding: 13px;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
    vertical-align: top;
}

th {
    color: #64748b;
    font-size: 13px;
}

form.atualizar {
    display: flex;
    gap: 7px;
    align-items: center;
}

form.atualizar select {
    min-width: 135px;
    margin: 0;
}

@media(max-width: 650px) {
    form.cadastro {
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
    <h1>❄ A3 SOLUÇÕES</h1>
    <p>Gerenciamento de ordens de serviço</p>
    <a href="index.php">Voltar ao painel principal</a>
</header>

<main>

<section class="bloco">
    <h2>Nova ordem de serviço</h2>

    <?php if ($mensagem): ?>
        <div class="mensagem"><?= escapar($mensagem) ?></div>
    <?php endif; ?>

    <?php if ($erro): ?>
        <div class="erro"><?= escapar($erro) ?></div>
    <?php endif; ?>

    <?php if (count($equipamentos) === 0): ?>

        <p>Cadastre um equipamento antes de abrir uma ordem de serviço.</p>
        <a href="equipamentos.php">Ir para cadastro de equipamentos</a>

    <?php else: ?>

    <form class="cadastro" method="POST">
        <input type="hidden" name="acao" value="cadastrar">

        <label>
            Equipamento
            <select name="equipamento_id" required>
                <option value="">Selecione um equipamento</option>
                <?php foreach ($equipamentos as $eq): ?>
                    <option value="<?= escapar($eq['id']) ?>">
                        <?= escapar($eq['codigo']) ?> -
                        <?= escapar($eq['cliente']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            Tipo de serviço
            <select name="tipo" required>
                <option value="Preventiva">Manutenção preventiva</option>
                <option value="Corretiva">Manutenção corretiva</option>
                <option value="Instalação">Instalação</option>
                <option value="Inspeção">Inspeção técnica</option>
            </select>
        </label>

        <label>
            Prioridade
            <select name="prioridade" required>
                <option value="Baixa">Baixa</option>
                <option value="Média" selected>Média</option>
                <option value="Alta">Alta</option>
                <option value="Urgente">Urgente</option>
            </select>
        </label>

        <label>
            Técnico responsável
            <input type="text" name="tecnico"
                   placeholder="Nome do técnico">
        </label>

        <label>
            Data agendada
            <input type="date" name="data_agendada">
        </label>

        <label class="completo">
            Descrição do serviço
            <textarea name="descricao" required
                placeholder="Descreva o serviço solicitado..."></textarea>
        </label>

        <label class="completo">
            Observações técnicas
            <textarea name="observacoes"
                placeholder="Informações adicionais..."></textarea>
        </label>

        <div class="completo">
            <button type="submit">Cadastrar ordem de serviço</button>
        </div>
        
    </form>

    <?php endif; ?>
</section>

<section class="bloco">
    <h2>Ordens de serviço cadastradas</h2>

    <div class="tabela">
        <table>
            <thead>
                <tr>
                    <th>Nº OS</th>
                    <th>Equipamento</th>
                    <th>Cliente</th>
                    <th>Tipo</th>
                    <th>Prioridade</th>
                    <th>Técnico</th>
                    <th>Agendamento</th>
                    <th>Status</th>
                    <th>Atualizar</th>
                </tr>
            </thead>

            <tbody>
            <?php foreach ($ordens as $os): ?>
                <tr>
                    <td><?= escapar($os['id']) ?></td>
                    <td><?= escapar($os['codigo']) ?></td>
                    <td><?= escapar($os['cliente']) ?></td>
                    <td><?= escapar($os['tipo']) ?></td>
                    <td><?= escapar($os['prioridade']) ?></td>
                    <td><?= escapar($os['tecnico']) ?></td>
                    <td><?= escapar($os['data_agendada']) ?></td>
                    <td><?= escapar($os['status']) ?></td>
                    <td>
                        <form method="POST" class="atualizar">
                            <input type="hidden" name="acao"
                                   value="atualizar">
                            <input type="hidden" name="os_id"
                                   value="<?= escapar($os['id']) ?>">

                            <select name="status">
                                <?php foreach ([
                                    'Aberta',
                                    'Em andamento',
                                    'Concluída',
                                    'Cancelada'
                                    

                                ] as $status): ?>
                                    <option value="<?= escapar($status) ?>"
                                        <?= $os['status'] === $status
                                            ? 'selected' : '' ?>>
                                        <?= escapar($status) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <button type="submit"
                                    class="botao-status">
                                Salvar
                            </button>

                            <a href="imprimir_os.php?id=<?= $os['id'] ?>"
   target="_blank"
   style="background:#145da0; color:white;
   padding:8px 12px; text-decoration:none;
   border-radius:5px; display:inline-block;">

   🖨️ Imprimir OS


   <a href="rat.php?os_id=<?= (int)$os['id'] ?>">
    📋 Gerar relatorio técnico
</a>

</a>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (!$ordens): ?>
                <tr>
                    <td colspan="9">
                        Nenhuma ordem de serviço cadastrada.
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

</main>
</body>
</html>
