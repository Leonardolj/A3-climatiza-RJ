

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>A3 climatização | Painel Principal</title>

<style>
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: Arial, sans-serif;
    background: #f1f5f9;
    color: #1e293b;
}

.layout {
    display: flex;
    min-height: 100vh;
}

.sidebar {
    width: 245px;
    background: #10243e;
    color: white;
    padding: 25px 15px;
}

.logo {
    font-size: 24px;
    font-weight: bold;
    margin: 5px 10px 35px;
}

.menu a {
    display: block;
    padding: 14px;
    margin-bottom: 8px;
    color: #dbeafe;
    text-decoration: none;
    border-radius: 8px;
}

.menu a:hover,
.menu a.ativo {
    background: #2563eb;
    color: white;
}

.conteudo {
    flex: 1;
    padding: 30px;
    min-width: 0;
}

.topo {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 30px;
}

.topo h1 {
    font-size: 27px;
}

.cards {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 18px;
    margin-bottom: 30px;
}

.card {
    background: white;
    padding: 22px;
    border-radius: 12px;
    box-shadow: 0 2px 8px #0000000a;
}

.card p {
    color: #64748b;
    font-size: 14px;
}

.card h2 {
    font-size: 30px;
    margin-top: 12px;
    color: #2563eb;
}

.tabela {
    background: white;
    padding: 22px;
    border-radius: 12px;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

th, td {
    padding: 14px;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
}

th {
    color: #64748b;
    font-size: 13px;
}

.status {
    background: #dbeafe;
    color: #1d4ed8;
    padding: 5px 9px;
    border-radius: 20px;
    font-size: 12px;
}

@media (max-width: 900px) {
    .cards {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 600px) {
    .layout {
        display: block;
    }

    .sidebar {
        width: 100%;
    }

    .menu {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
    }

    .conteudo {
        padding: 18px;
    }

    .cards {
        grid-template-columns: 1fr;
    }

    .topo {
        align-items: flex-start;
        flex-direction: column;
    }
}
</style>
</head>

<body>

<div class="layout">

    <aside class="sidebar">
        <div class="logo">❄ A3 Climatização</div>

        <nav class="menu">
            <a href="dashboard.php" class="ativo">▦ Dashboard</a>
            <a href="equipamentos.php">❄ Equipamentos</a>
            <a href="ordens_servico.php">▤ Ordens de Serviço</a>
            <a href="clientes.php">♙ Clientes</a>
            <a href="agenda.php">▣ Agenda</a>
            <a href="rat.php">▥ Relatórios</a>
            <a href="listar_laudos.php">📄 Histórico de Laudos</a>
            <a href="indicadores_laudos.php">📊 Indicadores de Laudos</a>
            <a href="rct.php"> 📄 RCT´s</a>
            <a href="substituicao_equipamento.php"> 🔄 Substituição de Equipamentos</a>
            <a hidden href="preventivas.php">📅 Manutenção Preventiva</a>
            <a href="agenda.php">📅 Agenda</a>
        </nav>
    </aside>

    <main class="conteudo">

        <header class="topo">
            <div>
                <h1>Painel Principal</h1>
                <p>Visão geral da gestão de climatização</p>
            </div>

            <div>
    <a href="equipamentos.php"
       style="background:#2563eb;color:white;padding:11px 15px;
       text-decoration:none;border-radius:7px;">
       + Equipamentos
    </a>

    <a href="ordens_servico.php"
       style="background:#0f766e;color:white;padding:11px 15px;
       text-decoration:none;border-radius:7px;margin-left:8px;">
       + Nova OS
    </a>
   

</div>

            
        </header>

        <section class="cards">

            <div class="card">
                <p>Total de equipamentos</p>
                <h2>5</h2>
            </div>

            <div class="card">
                <p>Equipamentos ativos</p>
                <h2>4</h2>
            </div>

            <div class="card">
                <p>Total de ordens de serviço</p>
                <h2>6</h2>
            </div>

            <div class="card">
                <p>Ordens em aberto</p>
                <h2>2</h2>
            </div>

        </section>

        <section class="tabela">
            <h2>Últimas ordens de serviço</h2>

            <table>
                <thead>
                    <tr>
                        <th>Nº</th>
                        <th>Equipamento</th>
                        <th>Cliente</th>
                        <th>Tipo</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>
                                                        <tr>
                        <td>6</td>
                        <td>0000</td>
                        <td>Leonardo Jaime</td>
                        <td>Preventiva</td>
                        <td>
                            <span class="status">
                                Concluída                            </span>
                        </td>
                    </tr>
                                        <tr>
                        <td>5</td>
                        <td>1234</td>
                        <td>casa e video</td>
                        <td>Preventiva</td>
                        <td>
                            <span class="status">
                                Cancelada                            </span>
                        </td>
                    </tr>
                                        <tr>
                        <td>4</td>
                        <td>1234</td>
                        <td>casa e video</td>
                        <td>Preventiva</td>
                        <td>
                            <span class="status">
                                Aberta                            </span>
                        </td>
                    </tr>
                                        <tr>
                        <td>3</td>
                        <td>123234</td>
                        <td>farmacia manupuladora</td>
                        <td>Preventiva</td>
                        <td>
                            <span class="status">
                                Aberta                            </span>
                        </td>
                    </tr>
                                        <tr>
                        <td>2</td>
                        <td>123234</td>
                        <td>farmacia manupuladora</td>
                        <td>Preventiva</td>
                        <td>
                            <span class="status">
                                Concluída                            </span>
                        </td>
                    </tr>
                                                    </tbody>
            </table>
        </section>

    </main>
</div>

</body>
</html>
