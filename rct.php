
<?php
require_once 'config.php';

function h($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

/*
 * ESTIMATIVA PRELIMINAR DE CARGA TÉRMICA
 *
 * Base: 600 BTU/h por m²
 * Pessoa: 600 BTU/h por pessoa
 * Iluminação: conversão de W para BTU/h
 * Equipamentos: conversão de W para BTU/h
 *
 * Solar:
 * Baixa: 500 BTU/h por m² de janela
 * Média: 900 BTU/h por m² de janela
 * Alta: 1400 BTU/h por m² de janela
 *
 * Estes coeficientes são simplificados e devem ser
 * ajustados e validados tecnicamente para cada projeto.
 */

function calcularCarga($dados) {
    $area = $dados['comprimento'] * $dados['largura'];
    $volume = $area * $dados['pe_direito'];

    $base = $area * 600;
    $pessoas = $dados['quantidade_pessoas'] * 600;

    $iluminacao = $dados['potencia_iluminacao_w'] * 3.412;
    $equipamentos = $dados['potencia_equipamentos_w'] * 3.412;

    $fatoresSolar = [
        'Baixa' => 500,
        'Média' => 900,
        'Alta' => 1400
    ];

    $fatorSolar = $fatoresSolar[$dados['incidencia_solar']] ?? 900;
    $solar = $dados['area_janelas'] * $fatorSolar;

    $carga = $base
        + $pessoas
        + $iluminacao
        + $equipamentos
        + $solar
        + ($dados['carga_adicional_w'] * 3.412);

    $instalada = (int)$dados['capacidade_instalada_btu'];

    return [
        'area' => round($area, 2),
        'volume' => round($volume, 2),
        'carga' => (int)ceil($carga),
        'deficit' => max(0, (int)ceil($carga) - $instalada)
    ];
}

$mensagem = '';
$erro = '';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$rct = null;
$ambientes = [];

/* CADASTRAR NOVO RELATÓRIO */
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['acao'] ?? '') === 'criar_rct') {

    $cliente_id = (int)($_POST['cliente_id'] ?? 0);
    $unidade = trim($_POST['unidade'] ?? '');
    $endereco = trim($_POST['endereco'] ?? '');
    $data = $_POST['data_avaliacao'] ?? date('Y-m-d');
    $responsavel = trim($_POST['responsavel_tecnico'] ?? '');
    $objetivo = trim($_POST['objetivo'] ?? '');

    if ($cliente_id <= 0 || $unidade === '') {
        $erro = 'Selecione um cliente e informe a unidade.';
    } else {
        try {
            $sql = "INSERT INTO rcts
                (cliente_id, unidade, endereco, data_avaliacao,
                 responsavel_tecnico, objetivo)
                VALUES
                (:cliente, :unidade, :endereco, :data,
                 :responsavel, :objetivo)";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':cliente' => $cliente_id,
                ':unidade' => $unidade,
                ':endereco' => $endereco,
                ':data' => $data,
                ':responsavel' => $responsavel,
                ':objetivo' => $objetivo
            ]);

            $novoId = $pdo->lastInsertId();

            header("Location: rct.php?id=" . $novoId);
            exit;

        } catch (PDOException $e) {
            $erro = 'Erro ao cadastrar relatório: ' . $e->getMessage();
        }
    }
}

/* CADASTRAR AMBIENTE */
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['acao'] ?? '') === 'adicionar_ambiente') {

    $idPost = (int)($_POST['rct_id'] ?? 0);

    $dados = [
        'ambiente' => trim($_POST['ambiente'] ?? ''),
        'setor' => trim($_POST['setor'] ?? ''),
        'comprimento' => (float)($_POST['comprimento'] ?? 0),
        'largura' => (float)($_POST['largura'] ?? 0),
        'pe_direito' => (float)($_POST['pe_direito'] ?? 0),
        'quantidade_pessoas' => (int)($_POST['quantidade_pessoas'] ?? 0),
        'area_janelas' => (float)($_POST['area_janelas'] ?? 0),
        'incidencia_solar' => $_POST['incidencia_solar'] ?? 'Média',
        'potencia_iluminacao_w' => (float)($_POST['potencia_iluminacao_w'] ?? 0),
        'potencia_equipamentos_w' => (float)($_POST['potencia_equipamentos_w'] ?? 0),
        'capacidade_instalada_btu' => (int)($_POST['capacidade_instalada_btu'] ?? 0),
        'carga_adicional_w' => (float)($_POST['carga_adicional_w'] ?? 0),
        'observacoes' => trim($_POST['observacoes'] ?? '')
    ];

    if ($idPost <= 0 || $dados['ambiente'] === '') {
        $erro = 'Informe o ambiente e o relatório.';
    } elseif (
        $dados['comprimento'] <= 0 ||
        $dados['largura'] <= 0 ||
        $dados['pe_direito'] <= 0
    ) {
        $erro = 'As dimensões do ambiente devem ser maiores que zero.';
    } elseif (
        $dados['quantidade_pessoas'] < 0 ||
        $dados['area_janelas'] < 0 ||
        $dados['potencia_iluminacao_w'] < 0 ||
        $dados['potencia_equipamentos_w'] < 0 ||
        $dados['capacidade_instalada_btu'] < 0 ||
        $dados['carga_adicional_w'] < 0
    ) {
        $erro = 'Os valores de carga e quantidade não podem ser negativos.';
    } else {
        try {
            $verifica = $pdo->prepare(
                "SELECT id FROM rcts WHERE id = ?"
            );
            $verifica->execute([$idPost]);

            if (!$verifica->fetch()) {
                throw new Exception('Relatório não encontrado.');
            }

            $resultado = calcularCarga($dados);

            $sql = "INSERT INTO rct_ambientes (
                rct_id, ambiente, setor,
                comprimento, largura, pe_direito,
                quantidade_pessoas, area_janelas,
                incidencia_solar,
                potencia_iluminacao_w,
                potencia_equipamentos_w,
                capacidade_instalada_btu,
                carga_adicional_w,
                area_m2, volume_m3,
                carga_estimada_btu, deficit_btu,
                observacoes
            ) VALUES (
                :rct, :ambiente, :setor,
                :comprimento, :largura, :pe_direito,
                :pessoas, :janelas, :solar,
                :iluminacao, :equipamentos,
                :instalada, :adicional,
                :area, :volume, :carga, :deficit,
                :observacoes
            )";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                ':rct' => $idPost,
                ':ambiente' => $dados['ambiente'],
                ':setor' => $dados['setor'],
                ':comprimento' => $dados['comprimento'],
                ':largura' => $dados['largura'],
                ':pe_direito' => $dados['pe_direito'],
                ':pessoas' => $dados['quantidade_pessoas'],
                ':janelas' => $dados['area_janelas'],
                ':solar' => $dados['incidencia_solar'],
                ':iluminacao' => $dados['potencia_iluminacao_w'],
                ':equipamentos' => $dados['potencia_equipamentos_w'],
                ':instalada' => $dados['capacidade_instalada_btu'],
                ':adicional' => $dados['carga_adicional_w'],
                ':area' => $resultado['area'],
                ':volume' => $resultado['volume'],
                ':carga' => $resultado['carga'],
                ':deficit' => $resultado['deficit'],
                ':observacoes' => $dados['observacoes']
            ]);

            header("Location: rct.php?id=" . $idPost . "&salvo=1");
            exit;

        } catch (Throwable $e) {
            $erro = 'Erro ao salvar ambiente: ' . $e->getMessage();
        }
    }

    $id = $idPost;
}

/* BUSCAR CLIENTES */
$clientes = $pdo->query(
    "SELECT id, nome FROM clientes ORDER BY nome"
)->fetchAll();

/* BUSCAR RELATÓRIO */
if ($id > 0) {
    $stmt = $pdo->prepare("
        SELECT r.*, c.nome AS cliente_nome
        FROM rcts r
        INNER JOIN clientes c ON c.id = r.cliente_id
        WHERE r.id = ?
    ");
    $stmt->execute([$id]);
    $rct = $stmt->fetch();

    if ($rct) {
        $stmt = $pdo->prepare("
            SELECT * FROM rct_ambientes
            WHERE rct_id = ?
            ORDER BY id DESC
        ");
        $stmt->execute([$id]);
        $ambientes = $stmt->fetchAll();
    }
}

if (isset($_GET['salvo'])) {
    $mensagem = 'Ambiente cadastrado com sucesso!';
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RCT - ClimaGestão</title>

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
    max-width: 1100px;
    margin: auto;
}

.card {
    background: white;
    padding: 25px;
    margin-bottom: 20px;
    border-radius: 12px;
    box-shadow: 0 3px 12px rgba(0,0,0,.06);
}

h1, h2 {
    color: #123b61;
}

h1 {
    margin-top: 0;
}

.grade {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
}

.campo {
    display: flex;
    flex-direction: column;
    gap: 6px;
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
    min-height: 85px;
    resize: vertical;
}

button, .botao {
    display: inline-block;
    padding: 12px 18px;
    border: 0;
    border-radius: 7px;
    background: #145a91;
    color: white;
    cursor: pointer;
    text-decoration: none;
    font-weight: bold;
    margin-top: 15px;
}

button:hover, .botao:hover {
    background: #0c416b;
}

.sucesso {
    padding: 12px;
    background: #dcfce7;
    color: #166534;
    border-radius: 7px;
    margin-bottom: 15px;
}

.erro {
    padding: 12px;
    background: #fee2e2;
    color: #991b1b;
    border-radius: 7px;
    margin-bottom: 15px;
}

.resultados {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    margin-top: 20px;
}

.resultado {
    background: #eff6ff;
    border-radius: 8px;
    padding: 15px;
}

.resultado strong {
    display: block;
    font-size: 20px;
    color: #145a91;
    margin-top: 8px;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}

th, td {
    padding: 11px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
}

th {
    background: #eff6ff;
}

.aviso {
    background: #fff7ed;
    border-left: 4px solid #f59e0b;
    padding: 13px;
    margin-top: 15px;
    font-size: 13px;
    line-height: 1.5;
}

@media(max-width:700px) {
    body {
        padding: 12px;
    }

    .grade, .resultados {
        grid-template-columns: 1fr;
    }

    .card {
        padding: 16px;
        overflow-x: auto;
    }
}
</style>
</head>

<body>
<div class="container">

<div class="card">
    <h1>Relatório de Capacidade Térmica</h1>
    <p>❄ A3 Climatização | Avaliação técnica dos ambientes</p>

    <?php if ($mensagem): ?>
        <div class="sucesso"><?= h($mensagem) ?></div>
    <?php endif; ?>

    <?php if ($erro): ?>
        <div class="erro"><?= h($erro) ?></div>
    <?php endif; ?>
</div>

<?php if (!$rct): ?>

<div class="card">
    <h2>Novo relatório RCT</h2>

    <form method="POST">
        <input type="hidden" name="acao" value="criar_rct">

        <div class="grade">
            <div class="campo">
                <label>Cliente</label>
                <select name="cliente_id" required>
                    <option value="">Selecione o cliente</option>
                    <?php foreach ($clientes as $cliente): ?>
                        <option value="<?= (int)$cliente['id'] ?>">
                            <?= h($cliente['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="campo">
                <label>Unidade</label>
                <input type="text" name="unidade" required>
            </div>

            <div class="campo">
                <label>Endereço</label>
                <input type="text" name="endereco">
            </div>

            <div class="campo">
                <label>Data da avaliação</label>
                <input type="date" name="data_avaliacao"
                       value="<?= date('Y-m-d') ?>" required>
            </div>

            <div class="campo">
                <label>Responsável técnico</label>
                <input type="text" name="responsavel_tecnico">
            </div>

            <div class="campo">
                <label>Objetivo do relatório</label>
                <input type="text" name="objetivo"
                       placeholder="Avaliação da capacidade térmica">
            </div>
        </div>

        <button type="submit">Criar relatório</button>
    </form>
</div>

<?php else: ?>

<div class="card">
    <h2>Relatório nº <?= (int)$rct['id'] ?></h2>

    <p><strong>Cliente:</strong> <?= h($rct['cliente_nome']) ?></p>
    <p><strong>Unidade:</strong> <?= h($rct['unidade']) ?></p>
    <p><strong>Endereço:</strong> <?= h($rct['endereco']) ?></p>
    <p><strong>Data:</strong> <?= date('d/m/Y', strtotime($rct['data_avaliacao'])) ?></p>
    <p><strong>Responsável:</strong> <?= h($rct['responsavel_tecnico']) ?></p>
    <p><strong>Objetivo:</strong> <?= h($rct['objetivo']) ?></p>
</div>

<div class="card">
    <h2>Adicionar ambiente</h2>

    <form method="POST">
        <input type="hidden" name="acao" value="adicionar_ambiente">
        <input type="hidden" name="rct_id" value="<?= (int)$rct['id'] ?>">

        <div class="grade">
            <div class="campo">
                <label>Nome do ambiente</label>
                <input type="text" name="ambiente"
                       placeholder="Ex.: Recepção" required>
            </div>

            <div class="campo">
                <label>Setor</label>
                <input type="text" name="setor"
                       placeholder="Ex.: Administrativo">
            </div>

            <div class="campo">
                <label>Comprimento (metros)</label>
                <input type="number" name="comprimento"
                       id="comprimento" min="0.01" step="0.01"
                       required>
            </div>

            <div class="campo">
                <label>Largura (metros)</label>
                <input type="number" name="largura"
                       id="largura" min="0.01" step="0.01"
                       required>
            </div>

            <div class="campo">
                <label>Pé-direito (metros)</label>
                <input type="number" name="pe_direito"
                       id="pe_direito" min="0.01" step="0.01"
                       value="2.80" required>
            </div>

            <div class="campo">
                <label>Quantidade de pessoas</label>
                <input type="number" name="quantidade_pessoas"
                       id="pessoas" min="0" value="0">
            </div>

            <div class="campo">
                <label>Área total das janelas (m²)</label>
                <input type="number" name="area_janelas"
                       id="janelas" min="0" step="0.01" value="0">
            </div>

            <div class="campo">
                <label>Incidência solar</label>
                <select name="incidencia_solar" id="solar">
                    <option value="Baixa">Baixa</option>
                    <option value="Média" selected>Média</option>
                    <option value="Alta">Alta</option>
                </select>
            </div>

            <div class="campo">
                <label>Potência da iluminação (W)</label>
                <input type="number" name="potencia_iluminacao_w"
                       id="iluminacao" min="0" step="0.01" value="0">
            </div>

            <div class="campo">
                <label>Potência dos equipamentos (W)</label>
                <input type="number" name="potencia_equipamentos_w"
                       id="equipamentos" min="0" step="0.01" value="0">
            </div>

            <div class="campo">
                <label>Capacidade instalada (BTU/h)</label>
                <input type="number" name="capacidade_instalada_btu"
                       id="instalada" min="0" value="0">
            </div>

            <div class="campo">
                <label>Carga adicional estimada (W)</label>
                <input type="number" name="carga_adicional_w"
                       id="adicional" min="0" step="0.01" value="0">
            </div>

            <div class="campo" style="grid-column:1/-1">
                <label>Observações do ambiente</label>
                <textarea name="observacoes"></textarea>
            </div>
        </div>

        <div class="resultados">
            <div class="resultado">
                Área do ambiente
                <strong id="areaPreview">0 m²</strong>
            </div>

            <div class="resultado">
                Volume
                <strong id="volumePreview">0 m³</strong>
            </div>

            <div class="resultado">
                Carga térmica estimada
                <strong id="cargaPreview">0 BTU/h</strong>
            </div>
        </div>

        <div class="aviso">
            Estimativa preliminar com coeficientes simplificados.
            O resultado não substitui cálculo técnico completo,
            levantamento de cargas, condições externas e avaliação
            das características construtivas do ambiente.
        </div>

        <button type="submit">Salvar ambiente</button>
    </form>
</div>

<div class="card">
    <h2>Ambientes cadastrados</h2>

    <?php if (!$ambientes): ?>
        <p>Nenhum ambiente cadastrado neste relatório.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Ambiente</th>
                    <th>Área</th>
                    <th>Instalada</th>
                    <th>Estimativa</th>
                    <th>Déficit</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ambientes as $a): ?>
                    <tr>
                        <td><?= h($a['ambiente']) ?></td>
                        <td><?= number_format((float)$a['area_m2'], 2, ',', '.') ?> m²</td>
                        <td><?= number_format((int)$a['capacidade_instalada_btu'], 0, ',', '.') ?></td>
                        <td><?= number_format((int)$a['carga_estimada_btu'], 0, ',', '.') ?></td>
                        <td><?= number_format((int)$a['deficit_btu'], 0, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <a class="botao" href="rct.php">Novo relatório</a>
    <a class="botao" href="imprimir_rct.php?id=<?= (int)$rct['id'] ?>">
        Imprimir RCT
    </a>
</div>

<?php endif; ?>

</div>

<script>
function atualizarCalculo() {
    const valor = id => Number(document.getElementById(id).value) || 0;

    const comprimento = valor('comprimento');
    const largura = valor('largura');
    const pe = valor('pe_direito');

    const area = comprimento * largura;
    const volume = area * pe;

    const solar = document.getElementById('solar').value;

    const fatores = {
        'Baixa': 500,
        'Média': 900,
        'Alta': 1400
    };

    const carga =
        area * 600 +
        valor('pessoas') * 600 +
        valor('iluminacao') * 3.412 +
        valor('equipamentos') * 3.412 +
        valor('janelas') * fatores[solar] +
        valor('adicional') * 3.412;

    const formato = n => Math.ceil(n).toLocaleString('pt-BR');

    document.getElementById('areaPreview').textContent =
        area.toFixed(2).replace('.', ',') + ' m²';

    document.getElementById('volumePreview').textContent =
        volume.toFixed(2).replace('.', ',') + ' m³';

    document.getElementById('cargaPreview').textContent =
        formato(carga) + ' BTU/h';
}

document.querySelectorAll('input, select').forEach(campo => {
    campo.addEventListener('input', atualizarCalculo);
    campo.addEventListener('change', atualizarCalculo);
});

atualizarCalculo();
</script>

</body>
</html>
