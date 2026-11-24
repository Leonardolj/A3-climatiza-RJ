
<?php
require_once 'config.php';

function h($valor) {
    return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

$mensagem = '';
$erro = '';

// Cadastrar um novo plano preventivo
if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['acao'] ?? '') === 'cadastrar') {

    $equipamentoId = filter_input(
        INPUT_POST,
        'equipamento_id',
        FILTER_VALIDATE_INT
    );

    $periodicidade = filter_input(
        INPUT_POST,
        'periodicidade_dias',
        FILTER_VALIDATE_INT
    );

    $dataInicial = $_POST['proxima_manutencao'] ?? '';

    $dataValida = DateTime::createFromFormat('Y-m-d', $dataInicial);

    if (!$equipamentoId || !$periodicidade ||
        $periodicidade < 1 || !$dataValida ||
        $dataValida->format('Y-m-d') !== $dataInicial) {

        $erro = 'Confira o equipamento, a periodicidade e a data.';

    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO planos_preventivos
                (equipamento_id, periodicidade_dias,
                 ultima_manutencao, proxima_manutencao,
                 tecnico, observacoes)
                VALUES
                (:equipamento, :periodicidade, NULL,
                 :proxima, :tecnico, :observacoes)
            ");

            $stmt->execute([
                ':equipamento' => $equipamentoId,
                ':periodicidade' => $periodicidade,
                ':proxima' => $dataInicial,
                ':tecnico' => trim($_POST['tecnico'] ?? ''),
                ':observacoes' => trim($_POST['observacoes'] ?? '')
            ]);

            $mensagem = 'Plano preventivo cadastrado!';

        } catch (PDOException $e) {
            $erro = 'Não foi possível cadastrar o plano. Verifique os dados.';
        }
    }
}

// Verificar planos vencidos ou previstos para hoje.
// Cada ciclo gera no máximo uma ordem de serviço.
try {
    $stmt = $pdo->query("
        SELECT *
        FROM planos_preventivos
        WHERE ativo = 1
          AND proxima_manutencao <= CURDATE()
        ORDER BY proxima_manutencao
    ");

    $planosVencidos = $stmt->fetchAll();

    foreach ($planosVencidos as $plano) {
        $pdo->beginTransaction();

        try {
            $descricao = 'Manutenção preventiva programada. '
                       . 'Plano nº ' . $plano['id']
                       . '. Data prevista: '
                       . $plano['proxima_manutencao'];

            $inserir = $pdo->prepare("
                INSERT IGNORE INTO ordens_servico
                (equipamento_id, tipo, prioridade, descricao,
                 tecnico, data_agendada, status,
                 plano_preventivo_id, data_referencia_preventiva)
                VALUES
                (:equipamento, 'Preventiva', 'Média', :descricao,
                 :tecnico, :data, 'Aberta', :plano, :referencia)
            ");

            $inserir->execute([
                ':equipamento' => $plano['equipamento_id'],
                ':descricao' => $descricao,
                ':tecnico' => $plano['tecnico'],
                ':data' => $plano['proxima_manutencao'],
                ':plano' => $plano['id'],
                ':referencia' => $plano['proxima_manutencao']
            ]);

            // Avança a próxima data somente quando uma OS foi criada.
            if ($inserir->rowCount() > 0) {
                $dias = (int)$plano['periodicidade_dias'];

                $proxima = (new DateTimeImmutable('today'))
                    ->modify('+' . $dias . ' days')
                    ->format('Y-m-d');

                $atualizar = $pdo->prepare("
                    UPDATE planos_preventivos
                    SET proxima_manutencao = :proxima
                    WHERE id = :id
                ");

                $atualizar->execute([
                    ':proxima' => $proxima,
                    ':id' => $plano['id']
                ]);
            }

            $pdo->commit();

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $erro = 'Houve um problema ao gerar uma OS preventiva.';
        }
    }

} catch (PDOException $e) {
    $erro = 'Não foi possível verificar os planos preventivos.';
}

// Listar equipamentos
$equipamentos = $pdo->query("
    SELECT id, codigo, cliente
    FROM equipamentos
    ORDER BY codigo
")->fetchAll();

// Listar planos e dados dos equipamentos
$planos = $pdo->query("
    SELECT p.*, e.codigo, e.cliente
    FROM planos_preventivos p
    INNER JOIN equipamentos e ON e.id = p.equipamento_id
    ORDER BY p.proxima_manutencao
")->fetchAll();

// Resumo das ordens preventivas
$resumo = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'Aberta') AS abertas,
        SUM(status = 'Em andamento') AS andamento,
        SUM(status = 'Concluída') AS concluidas
    FROM ordens_servico
    WHERE tipo = 'Preventiva'
")->fetch();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ClimaGestão - Manutenção Preventiva</title>

<style>
* { box-sizing: border-box; }

body {
    margin: 0;
    padding: 22px;
    font-family: Arial, sans-serif;
    background: #f1f5f9;
    color: #1e293b;
}

header, .bloco {
    background: white;
    padding: 22px;
    border-radius: 12px;
    margin-bottom: 22px;
}

header {
    background: #10243e;
    color: white;
}

header a { color: white; }

main {
    max-width: 1200px;
    margin: 25px auto;
}

form {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
}

label {
    font-weight: bold;
    font-size: 14px;
}

input, select, textarea {
    width: 100%;
    padding: 11px;
    margin-top: 7px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
}

textarea {
    min-height: 75px;
}

.completo { grid-column: 1 / -1; }

button {
    padding: 12px 18px;
    border: 0;
    border-radius: 7px;
    background: #2563eb;
    color: white;
    font-weight: bold;
    cursor: pointer;
}

.cards {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    margin-bottom: 22px;
}

.card {
    background: white;
    padding: 18px;
    border-radius: 10px;
}

.card strong {
    display: block;
    font-size: 28px;
    color: #2563eb;
    margin-top: 8px;
}

.mensagem, .erro {
    padding: 12px;
    border-radius: 7px;
    margin-bottom: 15px;
}

.mensagem {
    background: #dcfce7;
    color: #166534;
}

.erro {
    background: #fee2e2;
    color: #991b1b;
}

.tabela { overflow-x: auto; }

table {
    width: 100%;
    min-width: 750px;
    border-collapse: collapse;
}

th, td {
    padding: 13px;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
}

th { color: #64748b; }

@media(max-width: 700px) {
    form, .cards { grid-template-columns: 1fr; }
    .completo { grid-column: auto; }
}
</style>
</head>

<body>

<header>
    <h1>❄ A3 Climatização</h1>
    <p>Programação de manutenção preventiva</p>
    <a href="index.php">Voltar ao painel principal</a>
</header>

<main>

<?php if ($mensagem): ?>
    <div class="mensagem"><?= h($mensagem) ?></div>
<?php endif; ?>

<?php if ($erro): ?>
    <div class="erro"><?= h($erro) ?></div>
<?php endif; ?>

<section class="cards">
    <div class="card">
        Total de preventivas
        <strong><?= (int)($resumo['total'] ?? 0) ?></strong>
    </div>

    <div class="card">
        Abertas
        <strong><?= (int)($resumo['abertas'] ?? 0) ?></strong>
    </div>

    <div class="card">
        Em andamento
        <strong><?= (int)($resumo['andamento'] ?? 0) ?></strong>
    </div>

    <div class="card">
        Concluídas
        <strong><?= (int)($resumo['concluidas'] ?? 0) ?></strong>
    </div>
</section>

<section class="bloco">
    <h2>Novo plano preventivo</h2>

    <?php if (!$equipamentos): ?>
        <p>Cadastre um equipamento antes de criar um plano.</p>
        <a href="equipamentos.php">Cadastrar equipamento</a>
    <?php else: ?>

    <form method="POST">
        <input type="hidden" name="acao" value="cadastrar">

        <label>
            Equipamento
            <select name="equipamento_id" required>
                <option value="">Selecione</option>
                <?php foreach ($equipamentos as $eq): ?>
                    <option value="<?= h($eq['id']) ?>">
                        <?= h($eq['codigo']) ?> -
                        <?= h($eq['cliente']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            Periodicidade
            <select name="periodicidade_dias" required>
                <option value="30">Mensal - 30 dias</option>
                <option value="60">Bimestral - 60 dias</option>
                <option value="90" selected>Trimestral - 90 dias</option>
                <option value="180">Semestral - 180 dias</option>
                <option value="365">Anual - 365 dias</option>
            </select>
        </label>

        <label>
            Primeira manutenção prevista
            <input type="date" name="proxima_manutencao" required>
        </label>

        <label>
            Técnico responsável
            <input type="text" name="tecnico">
        </label>

        <label class="completo">
            Observações
            <textarea name="observacoes"></textarea>
        </label>

        <div class="completo">
            <button type="submit">Salvar plano preventivo</button>
        </div>
    </form>
    <?php endif; ?>
</section>

<section class="bloco">
    <h2>Planos preventivos cadastrados</h2>

    <div class="tabela">
    <table>
        <thead>
            <tr>
                <th>Equipamento</th>
                <th>Cliente</th>
                <th>Periodicidade</th>
                <th>Próxima manutenção</th>
                <th>Técnico</th>
                <th>Situação</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($planos as $plano): ?>
            <?php
            $hoje = date('Y-m-d');
            $data = $plano['proxima_manutencao'];

            if (!$plano['ativo']) {
                $situacao = 'Inativo';
            } elseif ($data < $hoje) {
                $situacao = 'Atrasada';
            } elseif ($data === $hoje) {
                $situacao = 'Vence hoje';
            } else {
                $situacao = 'Programada';
            }
            ?>
            <tr>
                <td><?= h($plano['codigo']) ?></td>
                <td><?= h($plano['cliente']) ?></td>
                <td><?= (int)$plano['periodicidade_dias'] ?> dias</td>
                <td><?= h($data) ?></td>
                <td><?= h($plano['tecnico']) ?></td>
                <td><?= h($situacao) ?></td>
            </tr>
        <?php endforeach; ?>

        <?php if (!$planos): ?>
            <tr>
                <td colspan="6">Nenhum plano cadastrado.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</section>

<section class="bloco">
    <h2>Ordens preventivas</h2>
    <p>
        As ordens geradas automaticamente aparecem na página
        <a href="ordens_servico.php">Ordens de Serviço</a>.
    </p>
</section>

</main>
</body>
</html>
