
<?php
require_once 'config.php';

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$erro = '';
$mensagem = '';

/* GERAR NUMERAÇÃO DO LAUDO */
function gerarNumeroLaudo($id) {
    return 'LC-' . date('Y') . '-' . str_pad($id, 5, '0', STR_PAD_LEFT);
}

/* SALVAR LAUDO */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $cliente_id = (int)($_POST['cliente_id'] ?? 0);
    $equipamento_id = (int)($_POST['equipamento_id'] ?? 0);

    $ordem_servico_id = !empty($_POST['ordem_servico_id'])
        ? (int)$_POST['ordem_servico_id'] : null;

    $data_laudo = $_POST['data_laudo'] ?? date('Y-m-d');
    $unidade = trim($_POST['unidade'] ?? '');
    $ambiente = trim($_POST['ambiente'] ?? '');

    $defeitos = trim($_POST['defeitos_encontrados'] ?? '');
    $diagnostico = trim($_POST['diagnostico_tecnico'] ?? '');
    $justificativa = trim($_POST['justificativa_condenacao'] ?? '');
    $recomendacao = trim($_POST['recomendacao'] ?? '');
    $conclusao = trim($_POST['conclusao'] ?? '');

    $responsavel = trim($_POST['responsavel_tecnico'] ?? '');
    $registro = trim($_POST['registro_profissional'] ?? '');
    $observacoes = trim($_POST['observacoes'] ?? '');

    if (
        $cliente_id <= 0 ||
        $equipamento_id <= 0 ||
        $defeitos === '' ||
        $diagnostico === '' ||
        $justificativa === '' ||
        $conclusao === '' ||
        $responsavel === ''
    ) {
        $erro = 'Preencha todos os campos obrigatórios.';
    } else {
        try {
            /* VALIDAR EQUIPAMENTO E CLIENTE */
            $stmt = $pdo->prepare("
                SELECT e.*, 
                       COALESCE(c.id, c2.id) AS cliente_real_id,
                       COALESCE(c.nome, c2.nome) AS cliente_nome
                FROM equipamentos e
                LEFT JOIN clientes c ON c.id = e.cliente_id
                LEFT JOIN clientes c2
                    ON c2.nome = e.cliente
                WHERE e.id = ?
            ");
            $stmt->execute([$equipamento_id]);
            $equipamento = $stmt->fetch();

            if (
                !$equipamento ||
                (int)$equipamento['cliente_real_id'] !== $cliente_id
            ) {
                throw new Exception(
                    'O equipamento não pertence ao cliente selecionado.'
                );
            }

            if ($ordem_servico_id !== null) {
                $stmt = $pdo->prepare("
                    SELECT id FROM ordens_servico
                    WHERE id = ? AND equipamento_id = ?
                ");
                $stmt->execute([
                    $ordem_servico_id,
                    $equipamento_id
                ]);

                if (!$stmt->fetch()) {
                    throw new Exception(
                        'A ordem de serviço selecionada não corresponde ao equipamento.'
                    );
                }
            }

            $stmt = $pdo->prepare("
                INSERT INTO laudos_condenacao (
                    cliente_id,
                    equipamento_id,
                    ordem_servico_id,
                    data_laudo,
                    unidade,
                    ambiente,
                    marca,
                    modelo,
                    capacidade_btu,
                    numero_serie,
                    defeitos_encontrados,
                    diagnostico_tecnico,
                    justificativa_condenacao,
                    recomendacao,
                    conclusao,
                    responsavel_tecnico,
                    registro_profissional,
                    observacoes
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                )
            ");

            $stmt->execute([
                $cliente_id,
                $equipamento_id,
                $ordem_servico_id,
                $data_laudo,
                $unidade,
                $ambiente,
                $equipamento['marca'],
                $equipamento['modelo'],
                $equipamento['capacidade_btu'],
                $equipamento['numero_serie'],
                $defeitos,
                $diagnostico,
                $justificativa,
                $recomendacao,
                $conclusao,
                $responsavel,
                $registro,
                $observacoes
            ]);

            $id = (int)$pdo->lastInsertId();
            $numero = gerarNumeroLaudo($id);

            $stmt = $pdo->prepare("
                UPDATE laudos_condenacao
                SET numero_laudo = ?
                WHERE id = ?
            ");
            $stmt->execute([$numero, $id]);

            header("Location: laudo_condenacao.php?id=" . $id . "&salvo=1");
            exit;

        } catch (Throwable $e) {
            $erro = 'Erro ao salvar laudo: ' . $e->getMessage();
        }
    }
}

/* LISTAR CLIENTES */
$clientes = $pdo->query("
    SELECT id, nome FROM clientes ORDER BY nome
")->fetchAll();

/* CARREGAR LAUDO SALVO */
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$laudo = null;

if ($id > 0) {
    $stmt = $pdo->prepare("
        SELECT l.*, c.nome AS cliente_nome,
               e.codigo AS codigo_equipamento,
               e.local_instalacao
        FROM laudos_condenacao l
        INNER JOIN clientes c ON c.id = l.cliente_id
        INNER JOIN equipamentos e ON e.id = l.equipamento_id
        WHERE l.id = ?
    ");
    $stmt->execute([$id]);
    $laudo = $stmt->fetch();
}

if (isset($_GET['salvo'])) {
    $mensagem = 'Laudo cadastrado com sucesso!';
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Laudo de Condenação - ClimaGestão</title>

<style>
* { box-sizing: border-box; }

body {
    font-family: Arial, sans-serif;
    background: #f1f5f9;
    color: #1e293b;
    padding: 20px;
    margin: 0;
}

.container {
    max-width: 1050px;
    margin: auto;
}

.card {
    background: white;
    padding: 25px;
    border-radius: 12px;
    margin-bottom: 20px;
    box-shadow: 0 3px 12px rgba(0,0,0,.06);
}

h1, h2 {
    color: #123b61;
}

.grade {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
}

.campo {
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.campo label {
    font-weight: bold;
    font-size: 14px;
}

input, select, textarea {
    width: 100%;
    padding: 11px;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    font-size: 15px;
}

textarea {
    min-height: 100px;
    resize: vertical;
}

button, .botao {
    display: inline-block;
    padding: 12px 18px;
    background: #145a91;
    color: white;
    border: 0;
    border-radius: 7px;
    cursor: pointer;
    text-decoration: none;
    margin-top: 15px;
}

.sucesso {
    background: #dcfce7;
    color: #166534;
    padding: 12px;
    border-radius: 7px;
}

.erro {
    background: #fee2e2;
    color: #991b1b;
    padding: 12px;
    border-radius: 7px;
}

.info {
    background: #eff6ff;
    padding: 15px;
    border-radius: 8px;
    margin-top: 15px;
}

.largura-total {
    grid-column: 1 / -1;
}

@media(max-width:700px) {
    .grade {
        grid-template-columns: 1fr;
    }
}
</style>
</head>

<body>
<div class="container">

<div class="card">
    <h1>Laudo Técnico de Condenação</h1>
    <p> ❄ A3 Climatização | Avaliação técnica de equipamentos</p>

    <?php if ($mensagem): ?>
        <div class="sucesso"><?= h($mensagem) ?></div>
    <?php endif; ?>

    <?php if ($erro): ?>
        <div class="erro"><?= h($erro) ?></div>
    <?php endif; ?>
</div>

<?php if ($laudo): ?>

<div class="card">
    <h2>Laudo <?= h($laudo['numero_laudo']) ?></h2>

    <p><strong>Cliente:</strong> <?= h($laudo['cliente_nome']) ?></p>
    <p><strong>Equipamento:</strong> <?= h($laudo['codigo_equipamento']) ?></p>
    <p><strong>Marca:</strong> <?= h($laudo['marca']) ?></p>
    <p><strong>Modelo:</strong> <?= h($laudo['modelo']) ?></p>
    <p><strong>Data:</strong> <?= date('d/m/Y', strtotime($laudo['data_laudo'])) ?></p>

    <a class="botao"
       href="imprimir_laudo_condenacao.php?id=<?= (int)$laudo['id'] ?>"
       target="_blank">
        Imprimir laudo
    </a>

    <a class="botao" href="laudo_condenacao.php">
        Novo laudo
    </a>

    <a class="botao" href="index.php">
        Voltar ao painel
    </a>
</div>

<?php else: ?>

<div class="card">
<h2>Identificação do equipamento</h2>

<form method="POST">

<div class="grade">

    <div class="campo">
        <label>Cliente *</label>
        <select name="cliente_id" id="cliente" required>
            <option value="">Selecione</option>
            <?php foreach ($clientes as $c): ?>
                <option value="<?= (int)$c['id'] ?>">
                    <?= h($c['nome']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="campo">
        <label>Equipamento *</label>
        <select name="equipamento_id" id="equipamento" required>
            <option value="">Selecione o cliente primeiro</option>
        </select>
    </div>

    <div class="campo">
        <label>Ordem de serviço (opcional)</label>
        <select name="ordem_servico_id" id="ordem">
            <option value="">Sem ordem vinculada</option>
        </select>
    </div>

    <div class="campo">
        <label>Data do laudo *</label>
        <input type="date" name="data_laudo"
               value="<?= date('Y-m-d') ?>" required>
    </div>

    <div class="campo">
        <label>Unidade</label>
        <input type="text" name="unidade">
    </div>

    <div class="campo">
        <label>Ambiente</label>
        <input type="text" name="ambiente">
    </div>

</div>

<div class="info" id="dadosEquipamento">
    Selecione um equipamento para consultar seus dados.
</div>

<h2>Diagnóstico técnico</h2>

<div class="grade">

    <div class="campo largura-total">
        <label>Defeitos encontrados *</label>
        <textarea name="defeitos_encontrados" required
        placeholder="Descreva as falhas encontradas na inspeção."></textarea>
    </div>

    <div class="campo largura-total">
        <label>Diagnóstico técnico *</label>
        <textarea name="diagnostico_tecnico" required
        placeholder="Registre os resultados da avaliação técnica."></textarea>
    </div>

    <div class="campo largura-total">
        <label>Justificativa da condenação *</label>
        <textarea name="justificativa_condenacao" required
        placeholder="Apresente os fundamentos técnicos da recomendação."></textarea>
    </div>

    <div class="campo largura-total">
        <label>Recomendação técnica</label>
        <textarea name="recomendacao"
        placeholder="Ex.: avaliar recuperação, substituição ou retirada de operação."></textarea>
    </div>

    <div class="campo largura-total">
        <label>Conclusão *</label>
        <textarea name="conclusao" required
        placeholder="Registre o parecer conclusivo e as condições da decisão."></textarea>
    </div>

</div>

<h2>Responsável técnico</h2>

<div class="grade">

    <div class="campo">
        <label>Nome do responsável *</label>
        <input type="text" name="responsavel_tecnico" required>
    </div>

    <div class="campo">
        <label>Registro profissional</label>
        <input type="text" name="registro_profissional"
        placeholder="CREA, CFT ou outro registro aplicável">
    </div>

    <div class="campo largura-total">
        <label>Observações</label>
        <textarea name="observacoes"></textarea>
    </div>

</div>

<button type="submit">Salvar laudo de condenação</button>

</form>
</div>

<?php endif; ?>

</div>

<script>
const cliente = document.getElementById('cliente');
const equipamento = document.getElementById('equipamento');
const ordem = document.getElementById('ordem');
const dados = document.getElementById('dadosEquipamento');

if (cliente) {

    cliente.addEventListener('change', async function() {

        equipamento.innerHTML =
            '<option value="">Carregando...</option>';

        ordem.innerHTML =
            '<option value="">Sem ordem vinculada</option>';

        dados.textContent = 'Selecione um equipamento.';

        if (!cliente.value) {
            equipamento.innerHTML =
                '<option value="">Selecione o cliente primeiro</option>';
            return;
        }

        try {
            const resposta = await fetch(
                'buscar_equipamentos_laudo.php?cliente_id=' +
                encodeURIComponent(cliente.value)
            );

            if (!resposta.ok) {
                throw new Error('Erro na consulta.');
            }

            const lista = await resposta.json();

            equipamento.innerHTML =
                '<option value="">Selecione o equipamento</option>';

            lista.forEach(item => {
                const opcao = document.createElement('option');
                opcao.value = item.id;
                opcao.textContent =
                    item.codigo + ' - ' + item.marca + ' ' + item.modelo;
                equipamento.appendChild(opcao);
            });

        } catch (erro) {
            equipamento.innerHTML =
                '<option value="">Erro ao carregar equipamentos</option>';
            dados.textContent = 'Não foi possível carregar os equipamentos.';
        }
    });

    equipamento.addEventListener('change', async function() {

        dados.textContent = 'Carregando dados...';
        ordem.innerHTML =
            '<option value="">Sem ordem vinculada</option>';

        if (!equipamento.value) {
            dados.textContent = 'Selecione um equipamento.';
            return;
        }

        try {
            const resposta = await fetch(
                'buscar_equipamentos_laudo.php?equipamento_id=' +
                encodeURIComponent(equipamento.value)
            );

            if (!resposta.ok) throw new Error();

            const item = await resposta.json();

            dados.innerHTML = '';

            [
                ['Código', item.codigo],
                ['Marca', item.marca],
                ['Modelo', item.modelo],
                ['Capacidade', item.capacidade_btu + ' BTU/h'],
                ['Número de série', item.numero_serie],
                ['Local de instalação', item.local_instalacao]
            ].forEach(([rotulo, valor]) => {
                const p = document.createElement('p');
                const strong = document.createElement('strong');
                strong.textContent = rotulo + ': ';
                p.appendChild(strong);
                p.appendChild(document.createTextNode(valor || 'Não informado'));
                dados.appendChild(p);
            });

            const osResposta = await fetch(
                'buscar_equipamentos_laudo.php?os_equipamento_id=' +
                encodeURIComponent(equipamento.value)
            );

            if (!osResposta.ok) throw new Error();

            const ordens = await osResposta.json();

            ordens.forEach(os => {
                const opcao = document.createElement('option');
                opcao.value = os.id;
                opcao.textContent =
                    'OS #' + os.id + ' - ' + os.tipo + ' - ' + os.status;
                ordem.appendChild(opcao);
            });

        } catch (erro) {
            dados.textContent =
                'Não foi possível consultar os dados do equipamento.';
        }
    });
}
</script>

</body>
</html>
