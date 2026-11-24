
<?php
require_once 'config.php';

function h($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    die('Relatório inválido.');
}

$stmt = $pdo->prepare("
    SELECT r.*, c.nome AS cliente_nome,
           c.documento, c.telefone, c.email
    FROM rcts r
    INNER JOIN clientes c ON c.id = r.cliente_id
    WHERE r.id = ?
");
$stmt->execute([$id]);
$rct = $stmt->fetch();

if (!$rct) {
    die('Relatório não encontrado.');
}

$stmt = $pdo->prepare("
    SELECT *
    FROM rct_ambientes
    WHERE rct_id = ?
    ORDER BY ambiente
");
$stmt->execute([$id]);
$ambientes = $stmt->fetchAll();

$totalInstalada = 0;
$totalEstimado = 0;
$totalArea = 0;

foreach ($ambientes as $a) {
    $totalInstalada += (int)$a['capacidade_instalada_btu'];
    $totalEstimado += (int)$a['carga_estimada_btu'];
    $totalArea += (float)$a['area_m2'];
}

$diferenca = $totalEstimado - $totalInstalada;

if ($diferenca > 0) {
    $parecerGeral = 'A capacidade instalada é inferior à carga térmica estimada. Recomenda-se avaliação técnica complementar.';
} elseif ($diferenca < 0) {
    $parecerGeral = 'A capacidade instalada é superior à carga térmica estimada. Recomenda-se verificar o dimensionamento e as condições de operação.';
} else {
    $parecerGeral = 'A capacidade instalada total corresponde à carga térmica estimada. Recomenda-se validar as condições reais de funcionamento.';
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>RCT - Relatório de Capacidade Térmica</title>

<style>
* {
    box-sizing: border-box;
}

body {
    font-family: Arial, sans-serif;
    color: #222;
    background: #e9edf2;
    margin: 0;
    padding: 20px;
}

.folha {
    background: white;
    width: 210mm;
    min-height: 297mm;
    padding: 16mm;
    margin: auto;
    box-shadow: 0 2px 10px rgba(0,0,0,.15);
}

.cabecalho {
    border-bottom: 3px solid #164e78;
    padding-bottom: 15px;
    margin-bottom: 20px;
    text-align: center;
}

.cabecalho h1 {
    color: #164e78;
    margin: 0;
    font-size: 23px;
}

.cabecalho p {
    margin: 6px 0;
    font-size: 12px;
}

.numero {
    text-align: right;
    font-size: 12px;
    font-weight: bold;
    margin-bottom: 15px;
}

.secao {
    margin-top: 22px;
    page-break-inside: avoid;
}

.secao h2 {
    background: #164e78;
    color: white;
    padding: 9px;
    font-size: 14px;
    margin-bottom: 10px;
}

.dados {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    font-size: 12px;
}

.dado {
    border-bottom: 1px solid #ddd;
    padding: 7px 0;
}

.ambiente {
    border: 1px solid #ccd5dd;
    padding: 12px;
    margin-bottom: 15px;
    page-break-inside: avoid;
}

.ambiente h3 {
    color: #164e78;
    margin-top: 0;
}

table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11px;
    margin-top: 10px;
}

th, td {
    border: 1px solid #ccc;
    padding: 8px;
    text-align: left;
}

th {
    background: #edf3f8;
}

.resumo {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
}

.resumo div {
    border: 1px solid #ccd5dd;
    padding: 12px;
    text-align: center;
}

.resumo strong {
    display: block;
    color: #164e78;
    font-size: 17px;
    margin-top: 7px;
}

.conclusao {
    border: 1px solid #ccd5dd;
    padding: 15px;
    line-height: 1.6;
    font-size: 12px;
}

.assinaturas {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 40px;
    margin-top: 65px;
    text-align: center;
    font-size: 11px;
}

.assinatura {
    border-top: 1px solid #222;
    padding-top: 8px;
}

.rodape {
    margin-top: 35px;
    padding-top: 10px;
    border-top: 1px solid #ccc;
    text-align: center;
    font-size: 10px;
    color: #555;
}

.botoes {
    text-align: center;
    margin: 20px auto;
}

button, a {
    padding: 12px 18px;
    border: none;
    border-radius: 6px;
    background: #164e78;
    color: white;
    text-decoration: none;
    cursor: pointer;
    margin: 5px;
}

@media print {
    @page {
        size: A4;
        margin: 12mm;
    }

    body {
        background: white;
        padding: 0;
    }

    .folha {
        width: auto;
        min-height: auto;
        padding: 0;
        margin: 0;
        box-shadow: none;
    }

    .botoes {
        display: none;
    }
}
</style>
</head>

<body>

<div class="botoes">
    <button onclick="window.print()">Imprimir / Salvar PDF</button>
    <a href="rct.php?id=<?= (int)$id ?>">Voltar ao relatório</a>
</div>

<div class="folha">

    <div class="cabecalho">
        <h1>RELATÓRIO DE CAPACIDADE TÉRMICA</h1>
        <p>❄ A3 Climatização - SISTEMA DE GESTÃO DE CLIMATIZAÇÃO</p>
        <p>Avaliação técnica das condições de climatização dos ambientes</p>
    </div>

    <div class="numero">
        RCT Nº <?= str_pad((string)$rct['id'], 5, '0', STR_PAD_LEFT) ?>
    </div>

    <div class="secao">
        <h2>1. IDENTIFICAÇÃO</h2>

        <div class="dados">
            <div class="dado">
                <strong>Cliente:</strong>
                <?= h($rct['cliente_nome']) ?>
            </div>

            <div class="dado">
                <strong>Documento:</strong>
                <?= h($rct['documento']) ?>
            </div>

            <div class="dado">
                <strong>Unidade:</strong>
                <?= h($rct['unidade']) ?>
            </div>

            <div class="dado">
                <strong>Data:</strong>
                <?= date('d/m/Y', strtotime($rct['data_avaliacao'])) ?>
            </div>

            <div class="dado">
                <strong>Endereço:</strong>
                <?= h($rct['endereco']) ?>
            </div>

            <div class="dado">
                <strong>Responsável técnico:</strong>
                <?= h($rct['responsavel_tecnico']) ?>
            </div>

            <div class="dado">
                <strong>Telefone:</strong>
                <?= h($rct['telefone']) ?>
            </div>

            <div class="dado">
                <strong>E-mail:</strong>
                <?= h($rct['email']) ?>
            </div>
        </div>
    </div>

    <div class="secao">
        <h2>2. OBJETIVO DA AVALIAÇÃO</h2>
        <p style="font-size:12px;line-height:1.6">
            <?= nl2br(h($rct['objetivo'])) ?>
        </p>
    </div>

    <div class="secao">
        <h2>3. RESULTADO GERAL</h2>

        <div class="resumo">
            <div>
                Área total
                <strong>
                    <?= number_format($totalArea, 2, ',', '.') ?> m²
                </strong>
            </div>

            <div>
                Capacidade instalada
                <strong>
                    <?= number_format($totalInstalada, 0, ',', '.') ?> BTU/h
                </strong>
            </div>

            <div>
                Carga estimada
                <strong>
                    <?= number_format($totalEstimado, 0, ',', '.') ?> BTU/h
                </strong>
            </div>
        </div>

        <p style="font-size:12px">
            <strong>Diferença entre carga estimada e capacidade instalada:</strong>
            <?= number_format($diferenca, 0, ',', '.') ?> BTU/h
        </p>
    </div>

    <div class="secao">
        <h2>4. AVALIAÇÃO INDIVIDUAL DOS AMBIENTES</h2>

        <?php foreach ($ambientes as $a): ?>

            <div class="ambiente">
                <h3><?= h($a['ambiente']) ?></h3>

                <p style="font-size:11px">
                    <strong>Setor:</strong>
                    <?= h($a['setor']) ?>
                </p>

                <table>
                    <tr>
                        <th>Característica</th>
                        <th>Resultado</th>
                    </tr>

                    <tr>
                        <td>Comprimento</td>
                        <td><?= h($a['comprimento']) ?> m</td>
                    </tr>

                    <tr>
                        <td>Largura</td>
                        <td><?= h($a['largura']) ?> m</td>
                    </tr>

                    <tr>
                        <td>Pé-direito</td>
                        <td><?= h($a['pe_direito']) ?> m</td>
                    </tr>

                    <tr>
                        <td>Área</td>
                        <td><?= number_format((float)$a['area_m2'], 2, ',', '.') ?> m²</td>
                    </tr>

                    <tr>
                        <td>Volume</td>
                        <td><?= number_format((float)$a['volume_m3'], 2, ',', '.') ?> m³</td>
                    </tr>

                    <tr>
                        <td>Quantidade de pessoas</td>
                        <td><?= (int)$a['quantidade_pessoas'] ?></td>
                    </tr>

                    <tr>
                        <td>Área de janelas</td>
                        <td><?= h($a['area_janelas']) ?> m²</td>
                    </tr>

                    <tr>
                        <td>Incidência solar</td>
                        <td><?= h($a['incidencia_solar']) ?></td>
                    </tr>

                    <tr>
                        <td>Iluminação</td>
                        <td><?= h($a['potencia_iluminacao_w']) ?> W</td>
                    </tr>

                    <tr>
                        <td>Equipamentos</td>
                        <td><?= h($a['potencia_equipamentos_w']) ?> W</td>
                    </tr>

                    <tr>
                        <td>Capacidade instalada</td>
                        <td><?= number_format((int)$a['capacidade_instalada_btu'], 0, ',', '.') ?> BTU/h</td>
                    </tr>

                    <tr>
                        <td>Carga térmica estimada</td>
                        <td><?= number_format((int)$a['carga_estimada_btu'], 0, ',', '.') ?> BTU/h</td>
                    </tr>

                    <tr>
                        <td>Déficit estimado</td>
                        <td><?= number_format((int)$a['deficit_btu'], 0, ',', '.') ?> BTU/h</td>
                    </tr>
                </table>

                <p style="font-size:11px">
                    <strong>Observações:</strong><br>
                    <?= nl2br(h($a['observacoes'])) ?>
                </p>
            </div>

        <?php endforeach; ?>

        <?php if (!$ambientes): ?>
            <p>Nenhum ambiente foi cadastrado.</p>
        <?php endif; ?>
    </div>

    <div class="secao">
        <h2>5. CONCLUSÃO TÉCNICA PRELIMINAR</h2>

        <div class="conclusao">
            <?= h($parecerGeral) ?>

            <p>
                A avaliação apresentada utiliza estimativa simplificada
                de carga térmica. A conclusão definitiva depende da
                verificação das condições construtivas, climáticas,
                de ocupação, ventilação, renovação de ar e operação
                dos equipamentos.
            </p>
        </div>
    </div>

    <div class="assinaturas">
        <div class="assinatura">
            Responsável técnico<br>
            Nome e assinatura
        </div>

        <div class="assinatura">
            Representante da unidade<br>
            Nome e assinatura
        </div>
    </div>

    <div class="rodape">
        ❄ A3 Climatização | Relatório nº
        <?= str_pad((string)$rct['id'], 5, '0', STR_PAD_LEFT) ?>
        | Documento gerado pelo sistema
    </div>

</div>

</body>
</html>
