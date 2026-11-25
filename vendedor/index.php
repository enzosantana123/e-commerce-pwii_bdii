<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/conexao.php';
atualizarTipoUsuario($pdo);
exigirVendedor();

$idUsuario = (int)$_SESSION['usuario_id'];
$stmtVendedor = $pdo->prepare('SELECT id_vende, nome_vende FROM vendedor WHERE id_usuario = ? LIMIT 1');
$stmtVendedor->execute([$idUsuario]);
$vendedor = $stmtVendedor->fetch();
if (!$vendedor) {
    http_response_code(403);
    exit('Perfil de vendedor não encontrado. Entre em contato com o suporte.');
}
$idVendedor = (int)$vendedor['id_vende'];

$mensagem = '';
$tipoMensagem = 'sucesso';

function salvarImagemProduto(array $arquivo): ?string
{
    if (!isset($arquivo['error']) || $arquivo['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($arquivo['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Não foi possível enviar a imagem. Tente novamente.');
    }
    if (($arquivo['size'] ?? 0) > 4 * 1024 * 1024) {
        throw new RuntimeException('A imagem deve ter no máximo 4 MB.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($arquivo['tmp_name']);
    $extensoes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];
    if (!isset($extensoes[$mime])) {
        throw new RuntimeException('Use uma imagem JPG, PNG ou WEBP.');
    }

    $diretorio = __DIR__ . '/../uploads/produtos';
    if (!is_dir($diretorio) && !mkdir($diretorio, 0755, true) && !is_dir($diretorio)) {
        throw new RuntimeException('Não foi possível preparar a pasta de imagens.');
    }

    $nomeArquivo = bin2hex(random_bytes(16)) . '.' . $extensoes[$mime];
    if (!move_uploaded_file($arquivo['tmp_name'], $diretorio . '/' . $nomeArquivo)) {
        throw new RuntimeException('Não foi possível salvar a imagem enviada.');
    }
    return 'uploads/produtos/' . $nomeArquivo;
}

$categorias = $pdo->query("SELECT id_categoria, nome_categoria FROM categoria WHERE nome_categoria IN ('MAQUINAS', 'PERIFERICOS', 'VARIADOS') ORDER BY FIELD(nome_categoria, 'MAQUINAS', 'PERIFERICOS', 'VARIADOS')")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf();
    $acao = $_POST['acao'] ?? '';

    try {
        if ($acao === 'excluir') {
            $idProduto = filter_var($_POST['id_produto'] ?? null, FILTER_VALIDATE_INT);
            if (!$idProduto) {
                throw new RuntimeException('Produto inválido.');
            }
            // Preserve o histórico de pedidos associados ao produto.
            $historico = $pdo->prepare('SELECT 1 FROM item_pedido WHERE id_produ = ? LIMIT 1');
            $historico->execute([$idProduto]);
            if ($historico->fetch()) { throw new RuntimeException('Produtos com histórico de pedidos não podem ser excluídos.'); }
            $excluir = $pdo->prepare('DELETE FROM produto WHERE id_produ = ? AND id_vende = ?');
            $excluir->execute([$idProduto, $idVendedor]);
            if ($excluir->rowCount() === 0) {
                throw new RuntimeException('O produto não foi encontrado na sua conta.');
            }
            $mensagem = 'Produto excluído com sucesso.';
        } elseif ($acao === 'salvar') {
            $idProduto = (int)($_POST['id_produto'] ?? 0);
            $nome = trim($_POST['nome_produ'] ?? '');
            $descricao = trim($_POST['desc_produ'] ?? '');
            $precoTexto = str_replace(',', '.', trim($_POST['preco_produ'] ?? ''));
            $descontoTexto = str_replace(',', '.', trim($_POST['desconto_produ'] ?? '0'));
            $idCategoria = filter_var($_POST['id_categoria'] ?? null, FILTER_VALIDATE_INT);

            $preco = filter_var($precoTexto, FILTER_VALIDATE_FLOAT);
            $desconto = filter_var($descontoTexto, FILTER_VALIDATE_FLOAT);
            $tamanhoNome = function_exists('mb_strlen') ? mb_strlen($nome, 'UTF-8') : strlen($nome);
            $tamanhoDescricao = function_exists('mb_strlen') ? mb_strlen($descricao, 'UTF-8') : strlen($descricao);

            if ($nome === '' || $tamanhoNome > 30 || $tamanhoDescricao > 500 || $preco === false || $preco <= 0
                || $desconto === false || $desconto < 0 || $desconto > 100 || !$idCategoria) {
                throw new RuntimeException('Confira o nome, a descrição, a categoria, o preço e o desconto.');
            }

            $categoriaValida = $pdo->prepare('SELECT id_categoria FROM categoria WHERE id_categoria = ? AND nome_categoria IN (\'MAQUINAS\', \'PERIFERICOS\', \'VARIADOS\')');
            $categoriaValida->execute([$idCategoria]);
            if (!$categoriaValida->fetch()) {
                throw new RuntimeException('Selecione uma das categorias disponíveis.');
            }

            $imagem = null;
            if ($idProduto > 0) {
                $atual = $pdo->prepare('SELECT imagem_produ FROM produto WHERE id_produ = ? AND id_vende = ? LIMIT 1');
                $atual->execute([$idProduto, $idVendedor]);
                $produtoAtual = $atual->fetch();
                if (!$produtoAtual) {
                    throw new RuntimeException('O produto que você tentou editar não foi encontrado.');
                }
                $imagem = $produtoAtual['imagem_produ'];
            }

            $novaImagem = salvarImagemProduto($_FILES['imagem_produ'] ?? []);
            if ($novaImagem !== null) {
                $imagem = $novaImagem;
            }
            if (!$imagem) {
                $imagem = 'imgs/pc.png';
            }

            if ($idProduto > 0) {
                $atualizar = $pdo->prepare(
                    'UPDATE produto
                     SET id_categoria = ?, nome_produ = ?, desc_produ = ?, imagem_produ = ?, preco_produ = ?, desconto_produ = ?
                     WHERE id_produ = ? AND id_vende = ?'
                );
                $atualizar->execute([$idCategoria, $nome, $descricao, $imagem, $preco, $desconto, $idProduto, $idVendedor]);
                $mensagem = 'Produto atualizado com sucesso.';
            } else {
                $inserir = $pdo->prepare(
                    'INSERT INTO produto (id_vende, id_categoria, nome_produ, desc_produ, imagem_produ, preco_produ, desconto_produ)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                $inserir->execute([$idVendedor, $idCategoria, $nome, $descricao, $imagem, $preco, $desconto]);
                $mensagem = 'Produto cadastrado com sucesso.';
            }
        } else {
            throw new RuntimeException('Ação não reconhecida.');
        }
    } catch (Throwable $e) {
        $mensagem = $e instanceof RuntimeException ? $e->getMessage() : 'Não foi possível salvar a alteração. Verifique os dados e tente novamente.';
        $tipoMensagem = 'erro';
    }
}

$produtoEdicao = null;
if (isset($_GET['editar']) && ctype_digit((string)$_GET['editar'])) {
    $buscarEdicao = $pdo->prepare('SELECT * FROM produto WHERE id_produ = ? AND id_vende = ? LIMIT 1');
    $buscarEdicao->execute([(int)$_GET['editar'], $idVendedor]);
    $produtoEdicao = $buscarEdicao->fetch() ?: null;
}

$listar = $pdo->prepare(
    'SELECT p.id_produ, p.nome_produ, p.desc_produ, p.imagem_produ, p.preco_produ, p.desconto_produ,
            c.nome_categoria
     FROM produto p
     INNER JOIN categoria c ON c.id_categoria = p.id_categoria
     WHERE p.id_vende = ?
     ORDER BY p.id_produ DESC'
);
$listar->execute([$idVendedor]);
$produtos = $listar->fetchAll();

$valorNome = $produtoEdicao['nome_produ'] ?? '';
$valorDescricao = $produtoEdicao['desc_produ'] ?? '';
$valorPreco = $produtoEdicao['preco_produ'] ?? '';
$valorDesconto = $produtoEdicao['desconto_produ'] ?? '0.00';
$valorCategoria = $produtoEdicao['id_categoria'] ?? ($categorias[0]['id_categoria'] ?? '');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <link rel="icon" type="image/png" href="../imgs/favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Painel do vendedor | Chronnus</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;700;800&family=Orbitron:wght@500;700;800&display=swap" rel="stylesheet">
    <style>
        :root{--wine-dark:#2d334a;--wine-main:#293740;--green:#33ebaa;--green-bright:#3bffb9;--bg:#011815;--white:#fff;--text:#F8F0E3;--muted:#D9C5C5;--line:rgba(55,212,173,.2);--shadow:0 18px 50px rgba(0,0,0,.4)}
        *{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font-family:Inter,Arial,sans-serif}a{color:inherit;text-decoration:none}button,input,textarea,select{font:inherit}
        .topo{background:linear-gradient(100deg,var(--wine-dark),var(--wine-main) 60%,#44816d);border-bottom:1px solid var(--line);box-shadow:0 8px 25px rgba(0,0,0,.3)}
        .topo-interno{max-width:1380px;min-height:78px;margin:auto;padding:12px 30px;display:flex;align-items:center;justify-content:space-between;gap:18px}
        .marca{display:flex;align-items:center;gap:14px}.marca img{width:48px;height:48px;object-fit:contain}.marca strong{font-family:Orbitron,sans-serif;color:var(--green-bright);letter-spacing:.06em}.marca small{display:block;color:var(--muted);font-size:11px;margin-top:4px}
        .acoes-topo{display:flex;align-items:center;gap:12px;font-size:13px}.link-topo{padding:10px 14px;border:1px solid var(--green);border-radius:9px;color:var(--green-bright);font-weight:700}.link-topo:hover{background:var(--green);color:var(--wine-dark)}
        .container{max-width:1380px;margin:auto;padding:34px 30px 60px}.cabecalho{display:flex;justify-content:space-between;align-items:end;gap:20px;margin-bottom:24px}.cabecalho h1{font:700 27px Orbitron,sans-serif;color:var(--green-bright);margin:0 0 8px}.cabecalho p{margin:0;color:var(--muted);font-size:13px}
        .layout{display:grid;grid-template-columns:minmax(310px,390px) 1fr;gap:22px;align-items:start}.painel{background:linear-gradient(135deg,var(--wine-dark),var(--wine-main));border:1px solid var(--line);border-radius:18px;padding:22px;box-shadow:var(--shadow)}
        .painel h2{font:700 17px Orbitron,sans-serif;color:var(--green-bright);margin:0 0 18px}.campo{margin-bottom:14px}.campo label{display:block;font-size:12px;margin-bottom:7px;color:var(--text)}
        .campo input,.campo textarea,.campo select{width:100%;padding:12px 13px;border:1px solid rgba(55,212,173,.35);border-radius:9px;background:#011815;color:#fff;outline:none}.campo textarea{min-height:95px;resize:vertical}.campo input:focus,.campo textarea:focus,.campo select:focus{border-color:var(--green);box-shadow:0 0 0 3px rgba(51,235,170,.12)}
        .dica{color:var(--muted);font-size:11px;line-height:1.45;margin:7px 0 0}.botao{display:inline-flex;justify-content:center;align-items:center;width:100%;padding:13px 16px;border:0;border-radius:9px;background:var(--green);color:#102b25;font-weight:800;cursor:pointer}.botao:hover{background:var(--green-bright)}.botao-secundario{display:inline-block;width:100%;text-align:center;padding:12px;margin-top:9px;border:1px solid var(--green);border-radius:9px;color:var(--green-bright);font-weight:700}
        .mensagem{padding:12px 14px;border-radius:9px;margin-bottom:18px;font-size:13px}.mensagem.sucesso{background:#174d3c;color:#d7ffed}.mensagem.erro{background:#713b35;color:#ffe5df}
        .lista-topo{display:flex;align-items:center;justify-content:space-between;margin-bottom:15px}.lista-topo h2{margin:0;font:700 17px Orbitron,sans-serif;color:var(--green-bright)}.contador{font-size:11px;color:var(--muted)}
        .produtos{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:15px}.produto{position:relative;background:var(--wine-main);border:1px solid var(--line);border-radius:15px;padding:14px;display:flex;flex-direction:column;min-width:0}.produto-imagem{height:160px;background:linear-gradient(145deg,rgba(255,255,255,.05),rgba(0,0,0,.2));border-radius:11px;display:flex;align-items:center;justify-content:center;padding:9px;margin-bottom:12px}.produto-imagem img{width:100%;height:100%;object-fit:contain}.produto h3{font-size:14px;margin:0 0 6px;color:var(--white)}.produto p{font-size:11px;line-height:1.5;color:var(--muted);margin:0 0 10px;min-height:34px}.tag{display:inline-block;align-self:flex-start;background:rgba(51,235,170,.12);color:var(--green-bright);border:1px solid rgba(51,235,170,.3);border-radius:6px;padding:4px 7px;font-size:9px;font-weight:800;letter-spacing:.04em;margin-bottom:8px}.preco{font:700 15px Orbitron,sans-serif;color:var(--green-bright);margin-top:auto}.preco del{display:block;font:400 10px Inter,sans-serif;color:var(--muted);margin-bottom:4px}.produto-acoes{display:flex;gap:8px;margin-top:12px}.produto-acoes a,.produto-acoes button{flex:1;text-align:center;padding:8px 7px;border-radius:8px;font-size:11px;font-weight:700;cursor:pointer}.editar{color:var(--green-bright);background:rgba(51,235,170,.08);border:1px solid var(--green)}.excluir{color:#ffd8d2;background:rgba(180,65,65,.15);border:1px solid rgba(255,130,120,.5)}.vazio{padding:35px 20px;text-align:center;border:1px dashed var(--line);border-radius:14px;color:var(--muted);font-size:13px}
        @media(max-width:900px){.layout{grid-template-columns:1fr}.produtos{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:560px){.topo-interno{padding:12px 16px;align-items:flex-start}.acoes-topo{flex-wrap:wrap;justify-content:flex-end}.container{padding:25px 15px 45px}.cabecalho{display:block}.cabecalho .link-topo{display:inline-block;margin-top:15px}.produtos{grid-template-columns:1fr}.painel{padding:18px}}
    </style>
</head>
<body>
<header class="topo">
    <div class="topo-interno">
        <a class="marca" href="../index.php">
            <img src="../imgs/logo.png" alt="Chronnus">
            <span><strong>CHRONNUS</strong><small>Painel do vendedor</small></span>
        </a>
        <nav class="acoes-topo">
            <a href="../usuario.php">Olá, <?= h(nomeUsuario()) ?></a>
            <a class="link-topo" href="../index.php">Ver loja</a>
            <a class="link-topo" href="../logout.php">Sair</a>
        </nav>
    </div>
</header>

<main class="container">
    <div class="cabecalho">
        <div>
            <h1>Gerencie seus produtos</h1>
            <p>Cadastre, atualize ou remova os produtos publicados por <?= h($vendedor['nome_vende']) ?>.</p>
        </div>
        <a class="link-topo" href="../index.php#ofertas">Visualizar catálogo</a>
    </div>

    <?php if ($mensagem): ?><div class="mensagem <?= h($tipoMensagem) ?>"><?= h($mensagem) ?></div><?php endif; ?>

    <div class="layout">
        <section class="painel" id="formulario-produto">
            <h2><?= $produtoEdicao ? 'Atualizar produto' : 'Cadastrar produto' ?></h2>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="acao" value="salvar">
                <input type="hidden" name="id_produto" value="<?= h($produtoEdicao['id_produ'] ?? 0) ?>">

                <div class="campo">
                    <label for="nome_produ">Nome do produto</label>
                    <input id="nome_produ" name="nome_produ" maxlength="30" required value="<?= h($valorNome) ?>" placeholder="Ex.: Teclado Mecânico">
                </div>
                <div class="campo">
                    <label for="desc_produ">Descrição</label>
                    <textarea id="desc_produ" name="desc_produ" maxlength="500" placeholder="Descreva as características do produto."><?= h($valorDescricao) ?></textarea>
                </div>
                <div class="campo">
                    <label for="id_categoria">Categoria</label>
                    <select id="id_categoria" name="id_categoria" required>
                        <?php foreach ($categorias as $categoria): ?>
                            <option value="<?= h($categoria['id_categoria']) ?>" <?= (string)$valorCategoria === (string)$categoria['id_categoria'] ? 'selected' : '' ?>><?= h($categoria['nome_categoria']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="campo">
                    <label for="preco_produ">Preço (R$)</label>
                    <input id="preco_produ" name="preco_produ" type="number" min="0.01" step="0.01" required value="<?= h($valorPreco) ?>" placeholder="0,00">
                </div>
                <div class="campo">
                    <label for="desconto_produ">Desconto (%)</label>
                    <input id="desconto_produ" name="desconto_produ" type="number" min="0" max="100" step="0.01" value="<?= h($valorDesconto) ?>">
                </div>
                <div class="campo">
                    <label for="imagem_produ">Imagem do produto</label>
                    <input id="imagem_produ" name="imagem_produ" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                    <p class="dica">Opcional. Formatos JPG, PNG ou WEBP; tamanho máximo de 4 MB. Ao editar, deixe em branco para manter a imagem atual.</p>
                </div>
                <button class="botao" type="submit"><?= $produtoEdicao ? 'Salvar alterações' : 'Cadastrar produto' ?></button>
                <?php if ($produtoEdicao): ?><a class="botao-secundario" href="index.php#formulario-produto">Cancelar edição</a><?php endif; ?>
            </form>
        </section>

        <section>
            <div class="lista-topo">
                <h2>Seus produtos</h2>
                <span class="contador"><?= count($produtos) ?> produto(s)</span>
            </div>
            <?php if (!$produtos): ?>
                <div class="vazio">Você ainda não cadastrou produtos. Use o formulário ao lado para publicar o primeiro.</div>
            <?php else: ?>
                <div class="produtos">
                    <?php foreach ($produtos as $produto):
                        $desconto = (float)$produto['desconto_produ'];
                        $precoFinal = (float)$produto['preco_produ'] * (1 - $desconto / 100);
                        $imagem = $produto['imagem_produ'] ?: 'imgs/pc.png';
                    ?>
                        <article class="produto">
                            <div class="produto-imagem"><img src="../<?= h($imagem) ?>" alt="<?= h($produto['nome_produ']) ?>" onerror="this.src='../imgs/pc.png'"></div>
                            <span class="tag"><?= h($produto['nome_categoria']) ?><?= $desconto > 0 ? ' · -' . number_format($desconto, 0, ',', '.') . '%' : '' ?></span>
                            <h3><?= h($produto['nome_produ']) ?></h3>
                            <p><?= h($produto['desc_produ'] ?? '') ?></p>
                            <div class="preco">
                                <?php if ($desconto > 0): ?><del>R$ <?= number_format((float)$produto['preco_produ'], 2, ',', '.') ?></del><?php endif; ?>
                                R$ <?= number_format($precoFinal, 2, ',', '.') ?>
                            </div>
                            <div class="produto-acoes">
                                <a class="editar" href="index.php?editar=<?= (int)$produto['id_produ'] ?>#formulario-produto">Editar</a>
                                <form method="POST" onsubmit="return confirm('Deseja realmente excluir este produto?');" style="flex:1;display:flex">
                                    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                                    <input type="hidden" name="acao" value="excluir">
                                    <input type="hidden" name="id_produto" value="<?= (int)$produto['id_produ'] ?>">
                                    <button class="excluir" type="submit" style="width:100%">Excluir</button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</main>
</body>
</html>
