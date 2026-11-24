
<?php
require_once 'config.php';

function h($valor) {
    return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    die('ID do laudo inválido.');
}

$stmt = $pdo->prepare("
    SELECT l.*, c.nome AS cliente_nome,
           e.codigo AS equipamento_codigo
    FROM laudos_condenacao l
    INNER JOIN clientes c ON c.id = l.cliente_id
    INNER JOIN equipamentos e ON e.id = l.equipamento_id
    WHERE l.id = ?
");

$stmt->execute([$id]);
$laudo = $stmt->fetch();

if (!$laudo) {
    die('Laudo não encontrado.');
}

$mensagem = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($laudo['status'] === 'Finalizado') {
        $erro = 'Este laudo já foi finalizado e não pode ser alterado.';
    } else {

        $campos = [
            'defeitos_encontrados',
            'diagnostico_tecnico',
            'justificativa_condenacao',
            'recomendacao',
            'conclusao',
            'responsavel_tecnico',
            'registro_profissional',
            'observacoes'
        ];

        $dados = [];

        foreach ($campos as $campo) {
            $dados[$campo] = trim($_POST[$campo] ?? '');
        }

        if (
            $dados['defeitos_encontrados'] === '' ||
            $dados['diagnostico_tecnico'] === '' ||
            $dados['justificativa_condenacao'] === '' ||
            $dados['conclusao'] === '' ||
            $dados['responsavel_tecnico'] === ''
        ) {
            $erro = 'Preencha todos os campos obrigatórios.';
        } else {

            $novoStatus = $_POST['status'] ?? 'Em elaboração';

            if (!in_array($novoStatus, ['Em elaboração', 'Finalizado'], true)) {
                $novoStatus = 'Em elaboração';
            }

            try {
                $sql = "
                    UPDATE laudos_condenacao SET
                        defeitos_encontrados = :defeitos,
                        diagnostico_tecnico = :diagnostico,
                        justificativa_condenacao = :justificativa,
                        recomendacao = :recomendacao,
                        conclusao = :conclusao,
                        responsavel_tecnico = :responsavel,
                        registro_profissional = :registro,
                        observacoes = :observacoes,
                        status = :status
                    WHERE id = :id
                      AND status = 'Em elaboração'
                ";

                $stmt = $pdo->prepare($sql);

                $stmt->execute([
                    ':defeitos' => $dados['defeitos_encontrados'],
                    ':diagnostico' => $dados['diagnostico_tecnico'],
                    ':justificativa' => $dados['justificativa_condenacao'],
                    ':recomendacao' => $dados['recomendacao'],
                    ':conclusao' => $dados['conclusao'],
                    ':responsavel' => $dados['responsavel_tecnico'],
                    ':registro' => $dados['registro_profissional'],
                    ':observacoes' => $dados['observacoes'],
                    ':status' => $novoStatus,
                    ':id' => $id
                ]);

                header(
                    'Location: editar_laudo_condenacao.php?id=' .
                    $id . '&salvo=1'
                );
                exit;

            } catch (PDOException $e) {
                $erro = 'Erro ao atualizar o laudo. Verifique os dados.';
            }
        }

        $laudo = array_merge($laudo, $dados);
        $laudo['status'] = $novoStatus ?? $laudo['status'];
    }
}

if (isset($_GET['salvo'])) {
    $mensagem = 'Laudo atualizado com sucesso!';
}

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Editar Laudo | ClimaGestão</title>

<style>
body {
    margin: 0;
    padding: 25px;
    background: #f1f5f9;
    font-family: Arial, sans-serif;
    color: #1e293b;
}

.container {
    max-width: 950px;
    margin: auto;
}

.card {
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 3px 12px rgba(0,0,0,.06);
}

h1 {
    margin-top: 0;
    color: #0f172a;
}

.identificacao {
    background: #eff6ff;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    line-height: 1.8;
}

label {
    display: block;
    font-weight: bold;
    margin: 16px 0 7px;
}

textarea, input, select {
    width: 100%;
    box-sizing: border-box;
    padding: 12px;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    font-family: Arial, sans-serif;
    font-size: 14px;
}

textarea {
    min-height: 95px;
    resize: vertical;
}

button, .botao {
    display: inline-block;
    border: none;
    padding: 12px 18px;
    border-radius: 7px;
    font-weight: bold;
    cursor: pointer;
    text-decoration: none;
    margin-top: 20px;
    font-size: 14px;
}

.salvar {
    background: #2563eb;
    color: white;
}

.voltar {
    background: #e2e8f0;
    color: #334155;
}

.sucesso {
    background: #dcfce7;
    color: #166534;
    padding: 13px;
    border-radius: 7px;
    margin-bottom: 15px;
}

.erro {
    background: #fee2e2;
    color: #991b1b;
    padding: 13px;
    border-radius: 7px;
    margin-bottom: 15px;
}

.bloqueado {
    background: #fef3c7;
    color: #92400e;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}
</style>
</head>

<body>

<div class="container">
<div class="card">

<h1>Editar Laudo de Condenação</h1>

<div class="identificacao">
    <strong>Número:</strong>
    <?= h($laudo['numero_laudo']) ?><br>

    <strong>Cliente:</strong>
    <?= h($laudo['cliente_nome']) ?><br>

    <strong>Equipamento:</strong>
    <?= h($laudo['equipamento_codigo']) ?><br>

    <strong>Status:</strong>
    <?= h($laudo['status']) ?>
</div>

<?php if ($mensagem): ?>
    <div class="sucesso"><?= h($mensagem) ?></div>
<?php endif; ?>

<?php if ($erro): ?>
    <div class="erro"><?= h($erro) ?></div>
<?php endif; ?>

<?php if ($laudo['status'] === 'Finalizado'): ?>

    <div class="bloqueado">
        Este laudo está finalizado. A edição está bloqueada.
    </div>

    <a class="botao voltar"
       href="listar_laudos.php">
       Voltar ao histórico
    </a>

    <a class="botao salvar"
       href="imprimir_laudo_condenacao.php?id=<?= (int)$id ?>"
       target="_blank">
       Imprimir laudo
    </a>

<?php else: ?>

<form method="POST">

    <label>Defeitos encontrados *</label>
    <textarea name="defeitos_encontrados" required><?= h($laudo['defeitos_encontrados']) ?></textarea>

    <label>Diagnóstico técnico *</label>
    <textarea name="diagnostico_tecnico" required><?= h($laudo['diagnostico_tecnico']) ?></textarea>

    <label>Justificativa da condenação *</label>
    <textarea name="justificativa_condenacao" required><?= h($laudo['justificativa_condenacao']) ?></textarea>

    <label>Recomendação técnica</label>
    <textarea name="recomendacao"><?= h($laudo['recomendacao']) ?></textarea>

    <label>Conclusão *</label>
    <textarea name="conclusao" required><?= h($laudo['conclusao']) ?></textarea>

    <label>Responsável técnico *</label>
    <input
        type="text"
        name="responsavel_tecnico"
        value="<?= h($laudo['responsavel_tecnico']) ?>"
        required
    >

    <label>Registro profissional</label>
    <input
        type="text"
        name="registro_profissional"
        value="<?= h($laudo['registro_profissional']) ?>"
    >

    <label>Observações</label>
    <textarea name="observacoes"><?= h($laudo['observacoes']) ?></textarea>

    <label>Status do laudo</label>
    <select name="status">
        <option value="Em elaboração"
            <?= $laudo['status'] === 'Em elaboração' ? 'selected' : '' ?>>
            Em elaboração
        </option>

        <option value="Finalizado">
            Finalizado
        </option>
    </select>

    <button type="submit" class="salvar">
        Salvar alterações
    </button>

    <a href="listar_laudos.php" class="botao voltar">
        Voltar
    </a>

</form>

<?php endif; ?>

</div>
</div>

</body>
</html>
