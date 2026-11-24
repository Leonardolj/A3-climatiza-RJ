
<?php
require_once 'config.php';

// Função para proteger a exibição de textos
if (!function_exists('h')) {
    function h($valor) {
        return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

// Define o mês exibido
$mesInformado = $_GET['mes'] ?? date('Y-m');

$dataMes = DateTimeImmutable::createFromFormat('!Y-m', $mesInformado);

if (!$dataMes || $dataMes->format('Y-m') !== $mesInformado) {
    $dataMes = new DateTimeImmutable('first day of this month');
}

$inicioMes = $dataMes->modify('first day of this month');
$fimMes = $dataMes->modify('last day of this month');

$inicio = $inicioMes->format('Y-m-d');
$fim = $fimMes->format('Y-m-d');

$mesAnterior = $inicioMes->modify('-1 month')->format('Y-m');
$mesSeguinte = $inicioMes->modify('+1 month')->format('Y-m');

$meses = [
    1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março',
    4 => 'Abril', 5 => 'Maio', 6 => 'Junho',
    7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro',
    10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
];

$tituloMes = $meses[(int)$inicioMes->format('n')] . ' de ' . $inicioMes->format('Y');

// Busca as ordens de serviço agendadas no mês
$sql = "
    SELECT
        os.id,
        os.tipo,
        os.prioridade,
        os.tecnico,
        os.data_agendada,
        os.status,
        e.codigo,
        e.marca,
        e.modelo,
        COALESCE(c.nome, e.cliente) AS cliente_nome
    FROM ordens_servico os
    INNER JOIN equipamentos e
        ON e.id = os.equipamento_id
    LEFT JOIN clientes c
        ON c.id = e.cliente_id
    WHERE os.data_agendada BETWEEN :inicio AND :fim
    ORDER BY os.data_agendada ASC, os.id ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':inicio' => $inicio,
    ':fim' => $fim
]);

$ordens = $stmt->fetchAll();

// Organiza as ordens por dia
$agenda = [];

foreach ($ordens as $ordem) {
    $dia = (int)date('j', strtotime($ordem['data_agendada']));
    $agenda[$dia][] = $ordem;
}

$totalOrdens = count($ordens);

$abertas = 0;
$andamento = 0;
$concluidas = 0;

foreach ($ordens as $ordem) {
    if ($ordem['status'] === 'Aberta') $abertas++;
    if ($ordem['status'] === 'Em andamento') $andamento++;
    if ($ordem['status'] === 'Concluída') $concluidas++;
}

// Calendário começa na segunda-feira
$quantidadeDias = (int)$fimMes->format('j');
$diaSemanaInicial = (int)$inicioMes->format('N');
$hoje = date('Y-m-d');
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agenda - ClimaGestão</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 20px;
            background: #f1f5f9;
            font-family: Arial, sans-serif;
            color: #1e293b;
        }

        .container {
            max-width: 1200px;
            margin: auto;
        }

        header {
            background: #123b67;
            color: white;
            padding: 22px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        header h1 {
            margin: 0 0 8px;
        }

        .voltar {
            color: white;
            text-decoration: none;
        }

        .resumo {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .card {
            background: white;
            padding: 18px;
            border-radius: 10px;
            box-shadow: 0 2px 6px #00000010;
        }

        .card strong {
            display: block;
            font-size: 27px;
            margin-top: 8px;
            color: #123b67;
        }

        .navegacao {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }

        .navegacao h2 {
            margin: 0;
        }

        .botao {
            display: inline-block;
            padding: 10px 15px;
            background: #123b67;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            border: none;
            cursor: pointer;
        }

        .calendario {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: 5px;
            background: #dbe3ed;
            padding: 5px;
            border-radius: 10px;
        }

        .dia-semana {
            background: #e2e8f0;
            padding: 12px 4px;
            text-align: center;
            font-weight: bold;
            font-size: 13px;
        }

        .dia {
            background: white;
            min-height: 135px;
            padding: 8px;
            border-radius: 5px;
            min-width: 0;
        }

        .dia.vazio {
            background: #f8fafc;
        }

        .dia.hoje {
            border: 2px solid #2563eb;
        }

        .numero-dia {
            font-weight: bold;
            margin-bottom: 8px;
        }

        .evento {
            display: block;
            padding: 6px;
            margin-bottom: 5px;
            border-radius: 5px;
            font-size: 11px;
            text-decoration: none;
            color: #1e293b;
            overflow-wrap: anywhere;
        }

        .Aberta {
            background: #fee2e2;
            border-left: 4px solid #dc2626;
        }

        .Em-andamento {
            background: #fef3c7;
            border-left: 4px solid #d97706;
        }

        .Concluida {
            background: #dcfce7;
            border-left: 4px solid #16a34a;
        }

        .Cancelada {
            background: #e2e8f0;
            border-left: 4px solid #64748b;
        }

        .legenda {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin: 18px 0;
            font-size: 13px;
        }

        .legenda span {
            padding: 7px 10px;
            border-radius: 5px;
        }

        .lista {
            margin-top: 25px;
        }

        .tabela {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }

        .tabela th,
        .tabela td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
        }

        .tabela th {
            background: #123b67;
            color: white;
        }

        .tabela a {
            color: #1d4ed8;
        }

        @media (max-width: 650px) {
            body {
                padding: 8px;
            }

            .calendario {
                gap: 2px;
                padding: 2px;
            }

            .dia {
                min-height: 85px;
                padding: 4px;
            }

            .dia-semana {
                font-size: 10px;
                padding: 8px 1px;
            }

            .evento {
                font-size: 9px;
                padding: 4px;
            }

            .tabela {
                font-size: 12px;
            }

            .tabela th,
            .tabela td {
                padding: 7px;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <header>
        <h1>📅 Agenda A3 CLIMATIZAÇÃO</h1>
        <p>Controle de manutenções e serviços programados</p>
        <a class="voltar" href="index.php">← Voltar ao painel principal</a>
    </header>

    <section class="resumo">

        <div class="card">
            Total de serviços
            <strong><?= $totalOrdens ?></strong>
        </div>

        <div class="card">
            Ordens abertas
            <strong><?= $abertas ?></strong>
        </div>

        <div class="card">
            Em andamento
            <strong><?= $andamento ?></strong>
        </div>

        <div class="card">
            Concluídas
            <strong><?= $concluidas ?></strong>
        </div>

    </section>

    <div class="navegacao">

        <a class="botao" href="?mes=<?= h($mesAnterior) ?>">
            ← Mês anterior
        </a>

        <h2><?= h($tituloMes) ?></h2>

        <a class="botao" href="?mes=<?= h($mesSeguinte) ?>">
            Próximo mês →
        </a>

    </div>

    <div class="calendario">

        <?php
        $diasSemana = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];

        foreach ($diasSemana as $diaNome):
        ?>
            <div class="dia-semana"><?= $diaNome ?></div>
        <?php endforeach; ?>

        <?php for ($i = 1; $i < $diaSemanaInicial; $i++): ?>
            <div class="dia vazio"></div>
        <?php endfor; ?>

        <?php for ($dia = 1; $dia <= $quantidadeDias; $dia++): ?>

            <?php
            $dataCompleta = $inicioMes->setDate(
                (int)$inicioMes->format('Y'),
                (int)$inicioMes->format('m'),
                $dia
            )->format('Y-m-d');

            $classeHoje = ($dataCompleta === $hoje) ? 'hoje' : '';
            ?>

            <div class="dia <?= $classeHoje ?>">

                <div class="numero-dia"><?= $dia ?></div>

                <?php if (!empty($agenda[$dia])): ?>

                    <?php foreach ($agenda[$dia] as $evento): ?>

                        <?php
                        $classeStatus = str_replace(
                            [' ', 'í'],
                            ['-', 'i'],
                            $evento['status']
                        );
                        ?>

                        <a
                            class="evento <?= h($classeStatus) ?>"
                            href="imprimir_os.php?id=<?= (int)$evento['id'] ?>"
                            target="_blank"
                            title="Abrir ordem de serviço"
                        >
                            <strong>OS #<?= (int)$evento['id'] ?></strong><br>
                            <?= h($evento['tipo']) ?><br>
                            <?= h($evento['codigo']) ?><br>
                            <?= h($evento['tecnico'] ?: 'Sem técnico') ?><br>
                            <?= h($evento['status']) ?>
                        </a>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

        <?php endfor; ?>

    </div>

    <div class="legenda">
        <span class="Aberta">Aberta</span>
        <span class="Em-andamento">Em andamento</span>
        <span class="Concluida">Concluída</span>
        <span class="Cancelada">Cancelada</span>
    </div>

    <section class="lista">

        <h2>Serviços agendados em <?= h($tituloMes) ?></h2>

        <?php if (empty($ordens)): ?>

            <div class="card">
                Nenhuma ordem de serviço agendada para este mês.
            </div>

        <?php else: ?>

            <div style="overflow-x:auto">

                <table class="tabela">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>OS</th>
                            <th>Cliente</th>
                            <th>Equipamento</th>
                            <th>Serviço</th>
                            <th>Técnico</th>
                            <th>Status</th>
                            <th>Ação</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($ordens as $ordem): ?>

                            <tr>
                                <td>
                                    <?= date('d/m/Y', strtotime($ordem['data_agendada'])) ?>
                                </td>

                                <td>#<?= (int)$ordem['id'] ?></td>

                                <td><?= h($ordem['cliente_nome']) ?></td>

                                <td>
                                    <?= h($ordem['codigo']) ?><br>
                                    <?= h($ordem['marca']) ?>
                                    <?= h($ordem['modelo']) ?>
                                </td>

                                <td><?= h($ordem['tipo']) ?></td>

                                <td><?= h($ordem['tecnico']) ?></td>

                                <td><?= h($ordem['status']) ?></td>

                                <td>
                                    <a
                                        href="imprimir_os.php?id=<?= (int)$ordem['id'] ?>"
                                        target="_blank"
                                    >
                                        Abrir OS
                                    </a>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    </tbody>
                </table>

            </div>

        <?php endif; ?>

    </section>

</div>

</body>
</html>
