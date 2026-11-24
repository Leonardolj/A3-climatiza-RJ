
<?php
require_once 'config.php';

$mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $tipo = $_POST['tipo'] ?? 'Pessoa Jurídica';
    $documento = trim($_POST['documento'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $endereco = trim($_POST['endereco'] ?? '');
    $cidade = trim($_POST['cidade'] ?? '');
    $estado = strtoupper(trim($_POST['estado'] ?? ''));
    $observacoes = trim($_POST['observacoes'] ?? '');

    if ($nome === '') {
        $mensagem = 'Informe o nome do cliente.';
    } elseif (!in_array($tipo, ['Pessoa Física', 'Pessoa Jurídica'], true)) {
        $mensagem = 'Tipo de cliente inválido.';
    } else {
        $sql = "INSERT INTO clientes
            (nome, tipo, documento, telefone, email, endereco,
             cidade, estado, observacoes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $nome, $tipo, $documento, $telefone, $email,
            $endereco, $cidade, $estado, $observacoes
        ]);

        header('Location: clientes.php?sucesso=1');
        exit;
    }
}

if (isset($_GET['sucesso'])) {
    $mensagem = 'Cliente cadastrado com sucesso!';
}

$clientes = $pdo->query(
    "SELECT * FROM clientes ORDER BY nome ASC"
)->fetchAll();

function h($valor) {
    return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<th>Ações</th>
<title>Clientes - A3 Climatização</title>

<style>
body {
    font-family: Arial, sans-serif;
    background: #f1f5f9;
    margin: 0;
    padding: 25px;
    color: #1e293b;
}
.container {
    max-width: 1100px;
    margin: auto;
}
h1 { color: #145da0; }
.card {
    background: white;
    padding: 22px;
    border-radius: 10px;
    margin-bottom: 22px;
    box-shadow: 0 2px 8px #00000010;
}
form {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 14px;
}
.campo {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.campo.completo {
    grid-column: 1 / -1;
}
input, select, textarea {
    padding: 11px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 14px;
}
textarea {
    min-height: 70px;
}
button {
    background: #145da0;
    color: white;
    border: none;
    padding: 12px;
    border-radius: 6px;
    cursor: pointer;
}
.tabela {
    width: 100%;
    border-collapse: collapse;
}
.tabela th, .tabela td {
    padding: 12px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
}
.tabela th {
    background: #eaf2f8;
}
.mensagem {
    padding: 12px;
    background: #dcfce7;
    margin-bottom: 15px;
    border-radius: 6px;
}
.voltar {
    display: inline-block;
    margin-bottom: 15px;
    color: #145da0;
}
@media(max-width:700px) {
    form { grid-template-columns: 1fr; }
    .campo.completo { grid-column: auto; }
    .tabela { font-size: 12px; }
}
</style>
</head>

<body>
<div class="container">

<a class="voltar" href="index.php">← Voltar ao painel</a>

<h1>👥 Gestão de Clientes</h1>

<?php if ($mensagem): ?>
<div class="mensagem"><?= h($mensagem) ?></div>
<?php endif; ?>

<div class="card">
<h2>Cadastrar novo cliente</h2>

<form method="POST">

<div class="campo completo">
<label>Nome / Razão Social *</label>
<input type="text" name="nome" required>
</div>

<div class="campo">
<label>Tipo de cliente</label>
<select name="tipo">
<option value="Pessoa Jurídica">Pessoa Jurídica</option>
<option value="Pessoa Física">Pessoa Física</option>
</select>
</div>

<div class="campo">
<label>CPF / CNPJ</label>
<input type="text" name="documento">
</div>

<div class="campo">
<label>Telefone</label>
<input type="text" name="telefone">
</div>

<div class="campo">
<label>E-mail</label>
<input type="email" name="email">
</div>

<div class="campo completo">
<label>Endereço</label>
<input type="text" name="endereco">
</div>

<div class="campo">
<label>Cidade</label>
<input type="text" name="cidade">
</div>

<div class="campo">
<label>Estado (UF)</label>
<input type="text" name="estado" maxlength="2">
</div>

<div class="campo completo">
<label>Observações</label>
<textarea name="observacoes"></textarea>
</div>

<div class="campo completo">
<button type="submit">Salvar Cliente</button>
</div>

</form>
</div>

<div class="card">
<h2>Clientes cadastrados</h2>

<div style="overflow-x:auto">
<table class="tabela">
<thead>
<tr>
<th>ID</th>
<th>Nome</th>
<th>Tipo</th>
<th>Telefone</th>
<th>E-mail</th>
<th>Cidade</th>
</tr>
</thead>

<tbody>
<?php foreach ($clientes as $cliente): ?>
<tr>
<td>
    <a href="cliente_detalhes.php?id=<?= (int)$cliente['id'] ?>">
        <?= h($cliente['nome']) ?>
    </a>

    <td>
    <a href="editar_cliente.php?id=<?= (int)$cliente['id'] ?>">
        ✏️ Editar
    </a>
    |
    <a href="cliente_detalhes.php?id=<?= (int)$cliente['id'] ?>">
        Ver ficha
    </a>
</td>
</td>
</tr>
<?php endforeach; ?>

<?php if (!$clientes): ?>
<tr>
<td colspan="6">Nenhum cliente cadastrado.</td>
</tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>

</div>
</body>
</html>
