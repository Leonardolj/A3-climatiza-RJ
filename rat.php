
<?php
require_once 'config.php';

if (!function_exists('h')) {
    function h($valor) {
        return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

$osId = filter_input(INPUT_GET, 'os_id', FILTER_VALIDATE_INT);
$erro = '';
$rat = [];
$os = null;

// Lista de ordens de serviço para seleção
$stmtLista = $pdo->query("
    SELECT
        os.id,
        os.tipo,
        os.status,
        os.data_agendada,
        e.codigo,
        e.marca,
        e.modelo,
        COALESCE(c.nome, e.cliente) AS cliente_nome
    FROM ordens_servico os
    INNER JOIN equipamentos e ON e.id = os.equipamento_id
    LEFT JOIN clientes c ON c.id = e.cliente_id
    ORDER BY os.id DESC
");

$listaOS = $stmtLista->fetchAll();

// Se uma OS foi selecionada, busca seus dados
if ($osId) {
    $stmtOS = $pdo->prepare("
        SELECT
            os.*,
            e.codigo,
            e.marca,
            e.modelo,
            e.numero_serie,
            e.local_instalacao,
            COALESCE(c.nome, e.cliente) AS cliente_nome
        FROM ordens_servico os
        INNER JOIN equipamentos e ON e.id = os.equipamento_id
        LEFT JOIN clientes c ON c.id = e.cliente_id
        WHERE os.id = :id
    ");

    $stmtOS->execute([':id' => $osId]);
    $os = $stmtOS->fetch();

    if ($os) {
        $stmtRAT = $pdo->prepare("
            SELECT * FROM rats
            WHERE ordem_servico_id = :os_id
        ");
        $stmtRAT->execute([':os_id' => $osId]);
        $rat = $stmtRAT->fetch() ?: [];
    }
}

// Salva ou atualiza o RAT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_rat'])) {

    $osIdPost = filter_var(
        $_POST['ordem_servico_id'] ?? '',
        FILTER_VALIDATE_INT
    );

    $dataAtendimento = $_POST['data_atendimento'] ?? '';
    $horaInicio = $_POST['hora_inicio'] ?? '';
    $horaFim = $_POST['hora_fim'] ?? '';
    $problema = trim($_POST['problema_relatado'] ?? '');
    $diagnostico = trim($_POST['diagnostico_tecnico'] ?? '');
    $servicos = trim($_POST['servicos_executados'] ?? '');
    $materiais = trim($_POST['materiais_utilizados'] ?? '');
    $recomendacoes = trim($_POST['recomendacoes'] ?? '');
    $tecnico = trim($_POST['tecnico_responsavel'] ?? '');
    $responsavel = trim($_POST['responsavel_cliente'] ?? '');
    $observacoes = trim($_POST['observacoes'] ?? '');

    $dataValida = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $dataAtendimento
    );

    if (!$osIdPost || !$dataValida ||
        $dataValida->format('Y-m-d') !== $dataAtendimento) {
        $erro = 'Informe uma OS e uma data de atendimento válidas.';
    } elseif ($diagnostico === '' || $servicos === '' || $tecnico === '') {
        $erro = 'Preencha o diagnóstico, os serviços executados e o técnico.';
    } elseif (
        ($horaInicio !== '' && !preg_match('/^\d{2}:\d{2}$/', $horaInicio)) ||
        ($horaFim !== '' && !preg_match('/^\d{2}:\d{2}$/', $horaFim))
    ) {
        $erro = 'Informe horários válidos.';
    } else {
        try {
            $verificaOS = $pdo->prepare(
                "SELECT id FROM ordens_servico WHERE id = :id"
            );
            $verificaOS->execute([':id' => $osIdPost]);

            if (!$verificaOS->fetch()) {
                $erro = 'A Ordem de Serviço informada não existe.';
            } else {
                $sql = "
                    INSERT INTO rats (
                        ordem_servico_id,
                        data_atendimento,
                        hora_inicio,
                        hora_fim,
                        problema_relatado,
                        diagnostico_tecnico,
                        servicos_executados,
                        materiais_utilizados,
                        recomendacoes,
                        tecnico_responsavel,
                        responsavel_cliente,
                        observacoes
                    ) VALUES (
                        :os_id,
                        :data_atendimento,
                        :hora_inicio,
                        :hora_fim,
                        :problema,
                        :diagnostico,
                        :servicos,
                        :materiais,
                        :recomendacoes,
                        :tecnico,
                        :responsavel,
                        :observacoes
                    )
                    ON DUPLICATE KEY UPDATE
                        data_atendimento = VALUES(data_atendimento),
                        hora_inicio = VALUES(hora_inicio),
                        hora_fim = VALUES(hora_fim),
                        problema_relatado = VALUES(problema_relatado),
                        diagnostico_tecnico = VALUES(diagnostico_tecnico),
                        servicos_executados = VALUES(servicos_executados),
                        materiais_utilizados = VALUES(materiais_utilizados),
                        recomendacoes = VALUES(recomendacoes),
                        tecnico_responsavel = VALUES(tecnico_responsavel),
                        responsavel_cliente = VALUES(responsavel_cliente),
                        observacoes = VALUES(observacoes)
                ";

                $stmtSalvar = $pdo->prepare($sql);
                $stmtSalvar->execute([
                    ':os_id' => $osIdPost,
                    ':data_atendimento' => $dataAtendimento,
                    ':hora_inicio' => $horaInicio !== '' ? $horaInicio : null,
                    ':hora_fim' => $horaFim !== '' ? $horaFim : null,
                    ':problema' => $problema,
                    ':diagnostico' => $diagnostico,
                    ':servicos' => $servicos,
                    ':materiais' => $materiais,
                    ':recomendacoes' => $recomendacoes,
                    ':tecnico' => $tecnico,
                    ':responsavel' => $responsavel,
                    ':observacoes' => $observacoes
                ]);

                header('Location: rat.php?os_id=' . $osIdPost . '&salvo=1');
                exit;
            }
        } catch (PDOException $e) {
            $erro = 'Não foi possível salvar o RAT. Verifique a tabela e tente novamente.';
        }
    }

    // Mantém os dados preenchidos se houver erro
    $rat = [
        'data_atendimento' => $dataAtendimento,
        'hora_inicio' => $horaInicio,
        'hora_fim' => $horaFim,
        'problema_relatado' => $problema,
        'diagnostico_tecnico' => $diagnostico,
        'servicos_executados' => $servicos,
        'materiais_utilizados' => $materiais,
        'recomendacoes' => $recomendacoes,
        'tecnico_responsavel' => $tecnico,
        'responsavel_cliente' => $responsavel,
        'observacoes' => $observacoes
    ];
}

function valorRat($rat, $campo, $padrao = '') {
    return $rat[$campo] ?? $padrao;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RAT - ClimaGestão</title>

<style>
* { box-sizing: border-box; }

body {
    margin: 0;
    padding: 20px;
    background: #f1f5f9;
    font-family: Arial, sans-serif;
    color: #1e293b;
}

.container {
    max-width: 1000px;
    margin: auto;
}

header {
    background: #123b67;
    color: white;
    padding: 22px;
    border-radius: 10px;
    margin-bottom: 20px;
}

header h1 { margin-top: 0; }

header a { color: white; }

.card {
    background: white;
    padding: 22px;
    border-radius: 10px;
    margin-bottom: 20px;
    box-shadow: 0 2px 6px #00000010;
}

.info-os {
    background: #eff6ff;
    border-left: 4px solid #2563eb;
    padding: 15px;
    margin-bottom: 20px;
    line-height: 1.8;
}

.grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 15px;
}

.grupo {
    display: flex;
    flex-direction: column;
    gap: 7px;
    margin-bottom: 15px;
}

label { font-weight: bold; }

input, textarea, select {
    width: 100%;
    padding: 11px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font: inherit;
}

textarea {
    min-height: 100px;
    resize: vertical;
}

.botao {
    display: inline-block;
    background: #123b67;
    color: white;
    padding: 12px 18px;
    border: 0;
    border-radius: 6px;
    text-decoration: none;
    cursor: pointer;
    font-size: 15px;
}

.erro {
    background: #fee2e2;
    color: #991b1b;
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 15px;
}

.sucesso {
    background: #dcfce7;
    color: #166534;
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 15px;
}

.lista-os {
    width: 100%;
    border-collapse: collapse;
}

.lista-os th, .lista-os td {
    padding: 12px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
}

.lista-os th {
    background: #123b67;
    color: white;
}

@media(max-width: 600px) {
    body { padding: 8px; }
    .card { padding: 14px; }
}
</style>
</head>

<body>
<div class="container">

<header>
    <h1>📋 RAT - Relatório de Atendimento Técnico</h1>
    <p>❄ A3 Climatização - Registro técnico de atendimentos</p>
    <a href="index.php">← Voltar ao painel principal</a>
</header>

<?php if ($erro): ?>
    <div class="erro"><?= h($erro) ?></div>
<?php endif; ?>

<?php if (isset($_GET['salvo'])): ?>
    <div class="sucesso">RAT salvo com sucesso!</div>
<?php endif; ?>

<?php if (!$osId): ?>

    <div class="card">
        <h2>Selecione uma Ordem de Serviço</h2>

        <?php if (!$listaOS): ?>
            <p>Não existem ordens de serviço cadastradas.</p>
        <?php else: ?>

            <div style="overflow-x:auto;">
                <table class="lista-os">
                    <thead>
                        <tr>
                            <th>OS</th>
                            <th>Cliente</th>
                            <th>Equipamento</th>
                            <th>Serviço</th>
                            <th>Status</th>
                            <th>Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($listaOS as $item): ?>
                        <tr>
                            <td>#<?= (int)$item['id'] ?></td>
                            <td><?= h($item['cliente_nome']) ?></td>
                            <td><?= h($item['codigo']) ?> - <?= h($item['marca']) ?> <?= h($item['modelo']) ?></td>
                            <td><?= h($item['tipo']) ?></td>
                            <td><?= h($item['status']) ?></td>
                            <td>
                                <a href="rat.php?os_id=<?= (int)$item['id'] ?>">
                                    Gerar / Editar RAT
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>
    </div>

<?php elseif (!$os): ?>

    <div class="card">
        <p>Ordem de Serviço não encontrada.</p>
        <a class="botao" href="rat.php">Voltar</a>
    </div>

<?php else: ?>

    <div class="card">

        <h2>Dados da Ordem de Serviço #<?= (int)$os['id'] ?></h2>

        <div class="info-os">
            <strong>Cliente:</strong> <?= h($os['cliente_nome']) ?><br>
            <strong>Equipamento:</strong> <?= h($os['codigo']) ?> -
            <?= h($os['marca']) ?> <?= h($os['modelo']) ?><br>
            <strong>Número de série:</strong> <?= h($os['numero_serie']) ?><br>
            <strong>Local:</strong> <?= h($os['local_instalacao']) ?><br>
            <strong>Tipo de serviço:</strong> <?= h($os['tipo']) ?><br>
            <strong>Descrição original da OS:</strong> <?= nl2br(h($os['descricao'])) ?>
        </div>

        <h2><?= $rat ? 'Editar Relatório Técnico' : 'Preencher Relatório Técnico' ?></h2>

        <form method="POST">

            <input type="hidden" name="ordem_servico_id" value="<?= (int)$os['id'] ?>">

            <div class="grid">

                <div class="grupo">
                    <label>Data do atendimento *</label>
                    <input type="date" name="data_atendimento"
                        value="<?= h(valorRat($rat, 'data_atendimento', date('Y-m-d'))) ?>"
                        required>
                </div>

                <div class="grupo">
                    <label>Horário de início</label>
                    <input type="time" name="hora_inicio"
                        value="<?= h(valorRat($rat, 'hora_inicio')) ?>">
                </div>

                <div class="grupo">
                    <label>Horário de término</label>
                    <input type="time" name="hora_fim"
                        value="<?= h(valorRat($rat, 'hora_fim')) ?>">
                </div>

                <div class="grupo">
                    <label>Técnico responsável *</label>
                    <input type="text" name="tecnico_responsavel"
                        value="<?= h(valorRat($rat, 'tecnico_responsavel', $os['tecnico'])) ?>"
                        required>
                </div>

                <div class="grupo">
                    <label>Responsável pelo cliente</label>
                    <input type="text" name="responsavel_cliente"
                        value="<?= h(valorRat($rat, 'responsavel_cliente')) ?>">
                </div>

            </div>

            <div class="grupo">
                <label>Problema relatado pelo cliente</label>
                <textarea name="problema_relatado"><?= h(valorRat($rat, 'problema_relatado', $os['descricao'])) ?></textarea>
            </div>

            <div class="grupo">
                <label>Diagnóstico técnico *</label>
                <textarea name="diagnostico_tecnico" required><?= h(valorRat($rat, 'diagnostico_tecnico')) ?></textarea>
            </div>

            <div class="grupo">
                <label>Serviços executados *</label>
                <textarea name="servicos_executados" required><?= h(valorRat($rat, 'servicos_executados')) ?></textarea>
            </div>

            <div class="grupo">
                <label>Peças e materiais utilizados</label>
                <textarea name="materiais_utilizados"><?= h(valorRat($rat, 'materiais_utilizados')) ?></textarea>
            </div>

            <div class="grupo">
                <label>Recomendações técnicas</label>
                <textarea name="recomendacoes"><?= h(valorRat($rat, 'recomendacoes')) ?></textarea>
            </div>

            <div class="grupo">
                <label>Observações adicionais</label>
                <textarea name="observacoes"><?= h(valorRat($rat, 'observacoes')) ?></textarea>
            </div>

            <button class="botao" type="submit" name="salvar_rat" value="1">
                💾 Salvar RAT
            </button>

            <?php if ($rat): ?>
                <a class="botao"
                   href="imprimir_rat.php?os_id=<?= (int)$os['id'] ?>"
                   target="_blank">
                    🖨️ Imprimir RAT
                </a>
            <?php endif; ?>

            <a class="botao" href="rat.php">Voltar à lista</a>

        </form>
    </div>

<?php endif; ?>

</div>
</body>
</html>
