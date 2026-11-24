
<?php
require_once 'config.php';

function h($valor) {
    return htmlspecialchars(
        (string)($valor ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    die('Cliente inválido.');
}

$mensagem = '';

// Buscar cliente
$stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
$stmt->execute([$id]);
$cliente = $stmt->fetch();

if (!$cliente) {
    die('Cliente não encontrado.');
}

// Atualizar cadastro
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $tipo = $_POST['tipo'] ?? '';
    $documento = trim($_POST['documento'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $endereco = trim($_POST['endereco'] ?? '');
    $cidade = trim($_POST['cidade'] ?? '');
    $estado = strtoupper(trim($_POST['estado'] ?? ''));
    $observacoes = trim($_POST['observacoes'] ?? '');

    if ($nome === '') {
        $mensagem = 'O nome do cliente é obrigatório.';
    } elseif (!in_array($tipo, ['Pessoa Física', 'Pessoa Jurídica'], true)) {
        $mensagem = 'Selecione um tipo de cliente válido.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensagem = 'Informe um e-mail válido.';
    } elseif ($estado !== '' && !preg_match('/^[A-Z]{2}$/', $estado)) {
        $mensagem = 'Informe uma UF com duas letras.';
    } else {
        try {
            $pdo->beginTransaction();

            // Atualizar cadastro
            $sql = "UPDATE clientes SET
                nome = ?,
                tipo = ?,
                documento = ?,
                telefone = ?,
                email = ?,
                endereco = ?,
                cidade = ?,
                estado = ?,
                observacoes = ?
                WHERE id = ?";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                $nome,
                $tipo,
                $documento,
                $telefone,
                $email,
                $endereco,
                $cidade,
                $estado,
                $observacoes,
                $id
            ]);

            // Atualizar o nome antigo dos equipamentos
            // que ainda não possuem cliente_id.
            $stmt = $pdo->prepare(
                "UPDATE equipamentos
                 SET cliente = ?
                 WHERE cliente_id = ?
                    OR (cliente_id IS NULL AND cliente = ?)"
            );

            $stmt->execute([
                $nome,
                $id,
                $cliente['nome']
            ]);

            $pdo->commit();

            header('Location: editar_cliente.php?id=' . $id . '&sucesso=1');
            exit;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $mensagem = 'Erro ao atualizar cliente: ' . $e->getMessage();
        }
    }

    // Manter os dados digitados em caso de erro
    $cliente = array_merge($cliente, [
        'nome' => $nome,
        'tipo' => $tipo,
        'documento' => $documento,
        'telefone' => $telefone,
        'email' => $email,
        'endereco' => $endereco,
        'cidade' => $cidade,
        'estado' => $estado,
        'observacoes' => $observacoes
    ]);
}

if (isset($_GET['sucesso'])) {
    $mensagem = 'Cliente atualizado com sucesso!';
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Editar Cliente - ClimaGestão</title>

<style>
body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f1f5f9;
    color: #1e293b;
}

header {
    background: #10243e;
    color: white;
    padding: 22px;
}

main {
    max-width: 850px;
    margin: 30px auto;
    padding: 0 18px;
}

.card {
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 2px 8px #00000010;
}

form {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
}

label {
    display: flex;
    flex-direction: column;
    gap: 7px;
    font-size: 14px;
    font-weight: bold;
}

input, select, textarea {
    width: 100%;
    padding: 11px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    box-sizing: border-box;
    font-size: 14px;
}

textarea {
    min-height: 90px;
}

.completo {
    grid-column: 1 / -1;
}

button {
    background: #2563eb;
    color: white;
    border: 0;
    padding: 13px 20px;
    border-radius: 7px;
    cursor: pointer;
    font-weight: bold;
}

.voltar {
    color: #2563eb;
    display: inline-block;
    margin-bottom: 15px;
}

.mensagem {
    background: #dcfce7;
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 15px;
}

.erro {
    background: #fee2e2;
}

@media(max-width: 650px) {
    form {
        grid-template-columns: 1fr;
    }

    .completo {
        grid-column: auto;
    }
}
</style>
</head>

<body>

<header>
    <h1>❄ A3 Climatização</h1>
    <p>Editar cadastro de cliente</p>
</header>

<main>

<a href="clientes.php" class="voltar">
    ← Voltar para clientes
</a>

<section class="card">

<h2>Editar Cliente: <?= h($cliente['nome']) ?></h2>

<?php if ($mensagem): ?>
    <div class="mensagem <?= strpos($mensagem, 'Erro') === 0 || strpos($mensagem, 'Informe') === 0 || strpos($mensagem, 'O nome') === 0 || strpos($mensagem, 'Selecione') === 0 ? 'erro' : '' ?>">
        <?= h($mensagem) ?>
    </div>
<?php endif; ?>

<form method="POST">

    <label class="completo">
        Nome / Razão Social *
        <input type="text" name="nome"
               value="<?= h($cliente['nome']) ?>" required>
    </label>

    <label>
        Tipo de cliente
        <select name="tipo">
            <option value="Pessoa Física"
                <?= $cliente['tipo'] === 'Pessoa Física' ? 'selected' : '' ?>>
                Pessoa Física
            </option>

            <option value="Pessoa Jurídica"
                <?= $cliente['tipo'] === 'Pessoa Jurídica' ? 'selected' : '' ?>>
                Pessoa Jurídica
            </option>
        </select>
    </label>

    <label>
        CPF / CNPJ
        <input type="text" name="documento"
               value="<?= h($cliente['documento']) ?>">
    </label>

    <label>
        Telefone
        <input type="text" name="telefone"
               value="<?= h($cliente['telefone']) ?>">
    </label>

    <label>
        E-mail
        <input type="email" name="email"
               value="<?= h($cliente['email']) ?>">
    </label>

    <label class="completo">
        Endereço
        <input type="text" name="endereco"
               value="<?= h($cliente['endereco']) ?>">
    </label>

    <label>
        Cidade
        <input type="text" name="cidade"
               value="<?= h($cliente['cidade']) ?>">
    </label>

    <label>
        Estado (UF)
        <input type="text" name="estado" maxlength="2"
               value="<?= h($cliente['estado']) ?>">
    </label>

    <label class="completo">
        Observações
        <textarea name="observacoes"><?= h($cliente['observacoes']) ?></textarea>
    </label>

    <div class="completo">
        <button type="submit">
            Salvar alterações
        </button>
    </div>

</form>

</section>
</main>

</body>
</html>
