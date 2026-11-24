
<?php
require_once 'config.php';

function h($valor) {
    return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

$mensagem = '';
$erro = '';

if (isset($_GET['salvo'])) {
    $mensagem = 'Substituição registrada com sucesso!';
}

if (isset($_GET['concluida'])) {
    $mensagem = 'Substituição concluída! Equipamento novo cadastrado ou vinculado e antigo inativado.';
}

if (isset($_GET['erro'])) {
    $erro = $_GET['erro'];
}

// Buscar laudos
$laudos = $pdo->query("
    SELECT
        l.id,
        l.numero_laudo,
        c.nome AS cliente,
        e.id AS equipamento_id,
        e.codigo,
        e.marca,
        e.modelo,
        e.capacidade_btu
    FROM laudos_condenacao l
    INNER JOIN clientes c ON c.id = l.cliente_id
    INNER JOIN equipamentos e ON e.id = l.equipamento_id
    ORDER BY l.id DESC
")->fetchAll();

// Buscar equipamentos ativos
$equipamentos = $pdo->query("
    SELECT id, codigo, marca, modelo
    FROM equipamentos
    WHERE status = 'Ativo'
    ORDER BY codigo
")->fetchAll();

// Salvar substituição
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $laudo_id = filter_input(INPUT_POST, 'laudo_id', FILTER_VALIDATE_INT);

    $equipamento_novo_id = !empty($_POST['equipamento_novo_id'])
        ? filter_input(INPUT_POST, 'equipamento_novo_id', FILTER_VALIDATE_INT)
        : null;

    $marca = trim($_POST['marca_novo'] ?? '');
    $modelo = trim($_POST['modelo_novo'] ?? '');
    $capacidade = trim($_POST['capacidade_nova_btu'] ?? '');
    $serie = trim($_POST['numero_serie_novo'] ?? '');
    $data = $_POST['data_substituicao'] ?? '';
    $tecnico = trim($_POST['tecnico_responsavel'] ?? '');
    $empresa = trim($_POST['empresa_instaladora'] ?? '');
    $observacoes = trim($_POST['observacoes'] ?? '');
    $status = $_POST['status'] ?? 'Programada';

    if (!$laudo_id || !$data || !$tecnico) {
        $erro = 'Preencha o laudo, a data e o técnico.';
    } elseif (!in_array($status, ['Programada', 'Cancelada'], true)) {
        $erro = 'Use Programada ou Cancelada. A conclusão é feita pelo botão do histórico.';
    } elseif ($capacidade !== '' &&
        (!ctype_digit($capacidade) || (int)$capacidade <= 0)) {
        $erro = 'Capacidade inválida.';
    } else {

        try {
            $stmt = $pdo->prepare("
                SELECT equipamento_id
                FROM laudos_condenacao
                WHERE id = ?
            ");

            $stmt->execute([$laudo_id]);
            $laudo = $stmt->fetch();

            if (!$laudo) {
                throw new Exception('Laudo não encontrado.');
            }

            if ($equipamento_novo_id) {
                $stmt = $pdo->prepare("
                    SELECT id
                    FROM equipamentos
                    WHERE id = ?
                ");

                $stmt->execute([$equipamento_novo_id]);

                if (!$stmt->fetch()) {
                    throw new Exception('Equipamento novo não encontrado.');
                }

                if ((int)$equipamento_novo_id === (int)$laudo['equipamento_id']) {
                    throw new Exception('Não é permitido selecionar o equipamento antigo.');
                }
            }

            $stmt = $pdo->prepare("
                INSERT INTO substituicoes_equipamentos (
                    laudo_id,
                    equipamento_antigo_id,
                    equipamento_novo_id,
                    marca_novo,
                    modelo_novo,
                    capacidade_nova_btu,
                    numero_serie_novo,
                    data_substituicao,
                    tecnico_responsavel,
                    empresa_instaladora,
                    observacoes,
                    status
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                )
            ");

            $stmt->execute([
                $laudo_id,
                $laudo['equipamento_id'],
                $equipamento_novo_id,
                $marca,
                $modelo,
                $capacidade !== '' ? (int)$capacidade : null,
                $serie,
                $data,
                $tecnico,
                $empresa,
                $observacoes,
                $status
            ]);

            header('Location: substituicao_equipamento.php?salvo=1');
            exit;

        } catch (Exception $e) {
            $erro = $e->getMessage();
        } catch (PDOException $e) {
            $erro = 'Erro ao salvar substituição no banco de dados.';
        }
    }
}

// Histórico
$historico = $pdo->query("
    SELECT
        s.*,
        l.numero_laudo,
        ea.codigo AS equipamento_antigo,
        en.codigo AS codigo_novo
    FROM substituicoes_equipamentos s
    INNER JOIN laudos_condenacao l ON l.id = s.laudo_id
    INNER JOIN equipamentos ea ON ea.id = s.equipamento_antigo_id
    LEFT JOIN equipamentos en ON en.id = s.equipamento_novo_id
    ORDER BY s.id DESC
")->fetchAll();

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Substituição de Equipamentos | ClimaGestão</title>

<style>
* { box-sizing:border-box; }

body {
    margin:0;
    padding:25px;
    background:#f1f5f9;
    font-family:Arial,sans-serif;
    color:#1e293b;
}

.container {
    max-width:1200px;
    margin:auto;
}

.card {
    background:white;
    padding:25px;
    border-radius:12px;
    margin-bottom:25px;
}

h1,h2 { color:#0f172a; }

label {
    display:block;
    margin:15px 0 7px;
    font-weight:bold;
}

input,select,textarea {
    width:100%;
    padding:12px;
    border:1px solid #cbd5e1;
    border-radius:7px;
    font-size:14px;
}

textarea {
    min-height:90px;
}

.grid {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:15px;
}

.botao {
    display:inline-block;
    padding:11px 15px;
    border:0;
    border-radius:7px;
    background:#2563eb;
    color:white;
    text-decoration:none;
    font-weight:bold;
    cursor:pointer;
    margin-top:15px;
}

.voltar { background:#64748b; }
.concluir { background:#15803d; }
.ver { background:#475569; }

.sucesso {
    background:#dcfce7;
    color:#166534;
    padding:13px;
    border-radius:7px;
    margin-bottom:15px;
}

.erro {
    background:#fee2e2;
    color:#991b1b;
    padding:13px;
    border-radius:7px;
    margin-bottom:15px;
}

.info {
    background:#eff6ff;
    padding:15px;
    border-radius:7px;
    margin-top:15px;
    color:#1e40af;
    line-height:1.7;
}

.tabela { overflow-x:auto; }

table {
    width:100%;
    border-collapse:collapse;
    min-width:1050px;
}

th,td {
    padding:12px;
    text-align:left;
    border-bottom:1px solid #e2e8f0;
    font-size:13px;
}

th { background:#f8fafc; }

@media(max-width:650px) {
    body { padding:12px; }
    .grid { grid-template-columns:1fr; }
}
</style>
</head>

<body>

<div class="container">

<div class="card">

<h1>Substituição de Equipamentos</h1>

<p>Registre a substituição de aparelhos vinculados aos laudos de condenação.</p>

<?php if ($mensagem): ?>
<div class="sucesso"><?= h($mensagem) ?></div>
<?php endif; ?>

<?php if ($erro): ?>
<div class="erro"><?= h($erro) ?></div>
<?php endif; ?>

<form method="POST">

<label>Laudo de condenação *</label>

<select name="laudo_id" id="laudo_id" required>
<option value="">Selecione o laudo</option>

<?php foreach ($laudos as $l): ?>
<option
    value="<?= (int)$l['id'] ?>"
    data-cliente="<?= h($l['cliente']) ?>"
    data-codigo="<?= h($l['codigo']) ?>"
    data-marca="<?= h($l['marca']) ?>"
    data-modelo="<?= h($l['modelo']) ?>"
    data-capacidade="<?= h($l['capacidade_btu']) ?>"
>
<?= h($l['numero_laudo']) ?> -
<?= h($l['cliente']) ?> -
<?= h($l['codigo']) ?>
</option>
<?php endforeach; ?>

</select>

<div class="info" id="dados_antigo">
Selecione um laudo para visualizar o equipamento condenado.
</div>

<h2>Novo equipamento</h2>

<label>Selecionar equipamento já cadastrado (opcional)</label>

<select name="equipamento_novo_id">
<option value="">Cadastrar novo equipamento ao concluir</option>

<?php foreach ($equipamentos as $eq): ?>
<option value="<?= (int)$eq['id'] ?>">
<?= h($eq['codigo']) ?> -
<?= h($eq['marca']) ?> -
<?= h($eq['modelo']) ?>
</option>
<?php endforeach; ?>

</select>

<div class="grid">

<div>
<label>Marca do novo equipamento</label>
<input type="text" name="marca_novo">
</div>

<div>
<label>Modelo do novo equipamento</label>
<input type="text" name="modelo_novo">
</div>

<div>
<label>Capacidade nova (BTU/h)</label>
<input type="number" name="capacidade_nova_btu" min="1">
</div>

<div>
<label>Número de série novo</label>
<input type="text" name="numero_serie_novo">
</div>

</div>

<h2>Dados da substituição</h2>

<div class="grid">

<div>
<label>Data *</label>
<input type="date" name="data_substituicao"
value="<?= date('Y-m-d') ?>" required>
</div>

<div>
<label>Técnico responsável *</label>
<input type="text" name="tecnico_responsavel" required>
</div>

<div>
<label>Empresa instaladora</label>
<input type="text" name="empresa_instaladora">
</div>

<div>
<label>Status inicial</label>
<select name="status">
<option value="Programada">Programada</option>
<option value="Cancelada">Cancelada</option>
</select>
</div>

</div>

<label>Observações</label>
<textarea name="observacoes"></textarea>

<button type="submit" class="botao">
Salvar substituição
</button>


<a href="index.php" class="botao voltar">
Voltar ao painel
</a>

</form>
</div>

<div class="card">

<h2>Histórico de substituições</h2>

<div class="tabela">

<table>
<thead>
<tr>
<th>Laudo</th>
<th>Equipamento antigo</th>
<th>Novo equipamento</th>
<th>Capacidade</th>
<th>Data</th>
<th>Técnico</th>
<th>Status</th>
<th>Ação</th>
</tr>
</thead>

<tbody>

<?php if (!$historico): ?>

<tr>
<td colspan="8">Nenhuma substituição registrada.</td>
</tr>

<?php else: ?>

<?php foreach ($historico as $s): ?>

<tr>

<td><?= h($s['numero_laudo']) ?></td>

<td><?= h($s['equipamento_antigo']) ?></td>

<td>
<?= h($s['codigo_novo'] ?: trim(($s['marca_novo'] ?? '') . ' ' . ($s['modelo_novo'] ?? ''))) ?>
</td>

<td>
<?= $s['capacidade_nova_btu']
? number_format((int)$s['capacidade_nova_btu'],0,',','.') . ' BTU/h'
: '-' ?>
</td>

<td><?= date('d/m/Y', strtotime($s['data_substituicao'])) ?></td>

<td><?= h($s['tecnico_responsavel']) ?></td>

<td><?= h($s['status']) ?></td>

<td>

<?php if ($s['status'] === 'Programada'): ?>

<a class="botao concluir"
href="concluir_substituicao.php?id=<?= (int)$s['id'] ?>"
onclick="return confirm('Confirma a conclusão? O equipamento antigo será inativado.');">
Concluir substituição
</a>

<?php elseif (!empty($s['equipamento_novo_id'])): ?>

<a href="index.php?pagina=ver_equipamento&id=<?= (int)$s['equipamento_novo_id'] ?>">
    Ver novo equipamento
</a>

<?php else: ?>
-
<?php endif; ?>

</td>

</tr>

<?php endforeach; ?>

<?php endif; ?>

</tbody>
</table>

</div>
</div>
</div>

<script>
const seletor = document.getElementById('laudo_id');
const dados = document.getElementById('dados_antigo');

seletor.addEventListener('change', function() {

    const opcao = this.options[this.selectedIndex];

    if (!opcao.value) {
        dados.textContent = 'Selecione um laudo para visualizar o equipamento condenado.';
        return;
    }

    dados.innerHTML = '';

    const itens = [
        ['Cliente: ', opcao.dataset.cliente],
        ['Código: ', opcao.dataset.codigo],
        ['Marca: ', opcao.dataset.marca],
        ['Modelo: ', opcao.dataset.modelo],
        ['Capacidade: ', opcao.dataset.capacidade]
    ];

    itens.forEach(function(item) {

        const linha = document.createElement('div');
        const titulo = document.createElement('strong');

        titulo.textContent = item[0];

        linha.appendChild(titulo);
        linha.appendChild(
            document.createTextNode(item[1] || '-')
        );

        dados.appendChild(linha);
    });

});
</script>

</body>
</html>
