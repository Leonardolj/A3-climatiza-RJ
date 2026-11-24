
<?php
require_once 'config.php';

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    die('Laudo inválido.');
}

$stmt = $pdo->prepare("
    SELECT
        l.*,
        c.nome AS cliente_nome,
        c.documento,
        c.telefone,
        c.email,
        e.codigo AS codigo_equipamento,
        e.local_instalacao,
        os.tipo AS tipo_os,
        os.descricao AS descricao_os
    FROM laudos_condenacao l
    INNER JOIN clientes c ON c.id = l.cliente_id
    INNER JOIN equipamentos e ON e.id = l.equipamento_id
    LEFT JOIN ordens_servico os ON os.id = l.ordem_servico_id
    WHERE l.id = ?
");

$stmt->execute([$id]);
$laudo = $stmt->fetch();

if (!$laudo) {
    die('Laudo não encontrado.');
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Laudo de Condenação <?= h($laudo['numero_laudo']) ?></title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    padding: 20px;
    background: #e9edf2;
    font-family: Arial, sans-serif;
    color: #222;
}

.folha {
    width: 210mm;
    min-height: 297mm;
    margin: auto;
    padding: 18mm;
    background: white;
    box-shadow: 0 2px 12px rgba(0,0,0,.12);
}

.cabecalho {
    text-align: center;
    border-bottom: 3px solid #164e78;
    padding-bottom: 15px;
    margin-bottom: 20px;
}

.cabecalho h1 {
    color: #164e78;
    font-size: 21px;
    margin: 0;
}

.cabecalho p {
    font-size: 11px;
    margin: 6px 0;
}

.numero {
    text-align: right;
    font-weight: bold;
    font-size: 12px;
    margin-bottom: 20px;
}

.secao {
    margin-top: 20px;
    page-break-inside: avoid;
}

.secao h2 {
    background: #164e78;
    color: white;
    padding: 9px;
    font-size: 13px;
    margin-bottom: 10px;
}

.dados {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.dado {
    border-bottom: 1px solid #ddd;
    padding: 7px 0;
    font-size: 11px;
}

.conteudo {
    border: 1px solid #ddd;
    padding: 13px;
    font-size: 12px;
    line-height: 1.7;
    min-height: 50px;
    overflow-wrap: anywhere;
}

.alerta {
    border: 2px solid #164e78;
    padding: 15px;
    margin-top: 20px;
    text-align: center;
    font-weight: bold;
    font-size: 13px;
}

.assinaturas {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 35px;
    margin-top: 70px;
    text-align: center;
    font-size: 11px;
}

.assinatura {
    border-top: 1px solid #222;
    padding-top: 9px;
}

.rodape {
    margin-top: 35px;
    border-top: 1px solid #ccc;
    padding-top: 10px;
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
    background: #164e78;
    color: white;
    text-decoration: none;
    border: none;
    border-radius: 6px;
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
        margin: 0;
        padding: 0;
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
    <button onclick="window.print()">
        Imprimir / Salvar PDF
    </button>

    <a href="laudo_condenacao.php?id=<?= (int)$laudo['id'] ?>">
        Voltar
    </a>
</div>

<div class="folha">

    <div class="cabecalho">
        <h1>LAUDO TÉCNICO DE CONDENAÇÃO</h1>
        <p>❄ A3 Climatização - GESTÃO DE REFRIGERAÇÃO E CLIMATIZAÇÃO</p>
        <p>Avaliação técnica de equipamentos de climatização</p>
    </div>

    <div class="numero">
        Nº <?= h($laudo['numero_laudo']) ?>
    </div>

    <div class="secao">
        <h2>1. IDENTIFICAÇÃO DO CLIENTE E UNIDADE</h2>

        <div class="dados">

            <div class="dado">
                <strong>Cliente:</strong>
                <?= h($laudo['cliente_nome']) ?>
            </div>

            <div class="dado">
                <strong>Documento:</strong>
                <?= h($laudo['documento']) ?>
            </div>

            <div class="dado">
                <strong>Unidade:</strong>
                <?= h($laudo['unidade']) ?>
            </div>

            <div class="dado">
                <strong>Ambiente:</strong>
                <?= h($laudo['ambiente']) ?>
            </div>

            <div class="dado">
                <strong>Endereço:</strong>
                <?= h($laudo['endereco'] ?? '') ?>
            </div>

            <div class="dado">
                <strong>Data do laudo:</strong>
                <?= date('d/m/Y', strtotime($laudo['data_laudo'])) ?>
            </div>

        </div>
    </div>

    <div class="secao">
        <h2>2. IDENTIFICAÇÃO DO EQUIPAMENTO</h2>

        <div class="dados">

            <div class="dado">
                <strong>Código:</strong>
                <?= h($laudo['codigo_equipamento']) ?>
            </div>

            <div class="dado">
                <strong>Marca:</strong>
                <?= h($laudo['marca']) ?>
            </div>

            <div class="dado">
                <strong>Modelo:</strong>
                <?= h($laudo['modelo']) ?>
            </div>

            <div class="dado">
                <strong>Capacidade:</strong>
                <?= number_format((int)$laudo['capacidade_btu'], 0, ',', '.') ?>
                BTU/h
            </div>

            <div class="dado">
                <strong>Número de série:</strong>
                <?= h($laudo['numero_serie']) ?>
            </div>

            <div class="dado">
                <strong>Local de instalação:</strong>
                <?= h($laudo['local_instalacao']) ?>
            </div>

            <div class="dado">
                <strong>Ordem de serviço:</strong>
                <?= $laudo['ordem_servico_id']
                    ? '#' . (int)$laudo['ordem_servico_id']
                    : 'Não vinculada' ?>
            </div>

        </div>
    </div>

    <div class="secao">
        <h2>3. DEFEITOS ENCONTRADOS</h2>

        <div class="conteudo">
            <?= nl2br(h($laudo['defeitos_encontrados'])) ?>
        </div>
    </div>

    <div class="secao">
        <h2>4. DIAGNÓSTICO TÉCNICO</h2>

        <div class="conteudo">
            <?= nl2br(h($laudo['diagnostico_tecnico'])) ?>
        </div>
    </div>

    <div class="secao">
        <h2>5. JUSTIFICATIVA TÉCNICA DA CONDENAÇÃO</h2>

        <div class="conteudo">
            <?= nl2br(h($laudo['justificativa_condenacao'])) ?>
        </div>
    </div>

    <div class="secao">
        <h2>6. RECOMENDAÇÃO TÉCNICA</h2>

        <div class="conteudo">
            <?= nl2br(h($laudo['recomendacao'])) ?>
        </div>
    </div>

    <div class="secao">
        <h2>7. CONCLUSÃO</h2>

        <div class="conteudo">
            <?= nl2br(h($laudo['conclusao'])) ?>
        </div>
    </div>

    <div class="secao">
        <h2>8. RESPONSABILIDADE TÉCNICA</h2>

        <div class="dados">

            <div class="dado">
                <strong>Responsável:</strong>
                <?= h($laudo['responsavel_tecnico']) ?>
            </div>

            <div class="dado">
                <strong>Registro profissional:</strong>
                <?= h($laudo['registro_profissional']) ?>
            </div>

        </div>
    </div>

    <?php if ($laudo['observacoes']): ?>
    <div class="secao">
        <h2>9. OBSERVAÇÕES</h2>

        <div class="conteudo">
            <?= nl2br(h($laudo['observacoes'])) ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="alerta">
        ESTE DOCUMENTO REGISTRA A AVALIAÇÃO TÉCNICA DO EQUIPAMENTO.
        SUA CONCLUSÃO DEVE SER VALIDADA E ASSINADA PELO PROFISSIONAL
        RESPONSÁVEL.
    </div>

    <div class="assinaturas">

        <div class="assinatura">
            <?= h($laudo['responsavel_tecnico']) ?><br>
            Responsável técnico<br>
            Registro: <?= h($laudo['registro_profissional']) ?>
        </div>

        <div class="assinatura">
            Representante da unidade<br>
            Nome e assinatura
        </div>

    </div>

    <div class="rodape">
        ❄ A3 Climatização | Laudo <?= h($laudo['numero_laudo']) ?>
    </div>

</div>

</body>
</html>
