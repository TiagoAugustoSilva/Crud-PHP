<?php
require_once('conexao.php');

// Inicializa a sessão
session_start();

// Buscar produtos no banco de dados
$stmt = $conn->query('SELECT * FROM cadastrar_produto');
$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!isset($_SESSION['carrinho'])) {
    $_SESSION['carrinho'] = [];
}

$resultadoPesquisa = ""; // Variável para armazenar o resultado da pesquisa

// Verifica se há uma solicitação de pesquisa por ID
if (isset($_GET['produtoId'])) {
    $produtoId = filter_input(INPUT_GET, 'produtoId', FILTER_SANITIZE_NUMBER_INT);

    // Busca o índice do produto no array
    $produtoIndex = array_search($produtoId, array_column($produtos, 'id'));

    if ($produtoIndex !== false) {
        // Exibe as informações do produto encontrado
        $produto = $produtos[$produtoIndex];
        $resultadoPesquisa = " Produto:  {$produto['produto']}, Total Estoque: {$produto['quantidade']}";
    } else {
        $resultadoPesquisa = "Produto não encontrado.";
    }
}

// Verifica se há uma solicitação para excluir um produto do carrinho
if (isset($_GET['excluir'])) {
    $produtoExcluirId = filter_input(INPUT_GET, 'excluir', FILTER_SANITIZE_NUMBER_INT);

    // Remove o produto do carrinho
    if (isset($_SESSION['carrinho'][$produtoExcluirId])) {
        // Adiciona a quantidade de volta ao estoque
        $produtoIndex = array_search($produtoExcluirId, array_column($produtos, 'id'));
        if ($produtoIndex !== false) {
            $produtos[$produtoIndex]['quantidade'] += $_SESSION['carrinho'][$produtoExcluirId];
        }

        // Remove o produto do carrinho
        unset($_SESSION['carrinho'][$produtoExcluirId]);
    }
}

// Verifica se há uma solicitação para excluir todos os produtos do carrinho
if (isset($_GET['excluirTodos'])) {
    // Remove todos os produtos do carrinho e adiciona a quantidade de volta ao estoque
    foreach ($_SESSION['carrinho'] as $produtoId => $quantidade) {
        $produtoIndex = array_search($produtoId, array_column($produtos, 'id'));
        if ($produtoIndex !== false) {
            $produtos[$produtoIndex]['quantidade'] += $quantidade;
        }
    }
    // Limpa o carrinho
    $_SESSION['carrinho'] = [];
}

// Adiciona produtos ao carrinho quando o formulário for enviado
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $totalVenda = 0; // Variável para armazenar o total da venda

    foreach ($_POST['produto'] as $produtoId => $quantidade) {
        // Busca o índice do produto no array
        $produtoIndex = array_search($produtoId, array_column($produtos, 'id'));

        if ($produtoIndex !== false && $produtos[$produtoIndex]['quantidade'] >= $quantidade) {
            if (isset($_SESSION['carrinho'][$produtoId])) {
                $_SESSION['carrinho'][$produtoId] += $quantidade;
            } else {
                $_SESSION['carrinho'][$produtoId] = $quantidade;
            }

            $produtos[$produtoIndex]['quantidade'] -= $quantidade;

            // Atualiza o total da venda
            $totalVenda += $quantidade * $produtos[$produtoIndex]['valor_unitario'];
        } else {
            echo "Estoque insuficiente para o Produto {$produtoId}.";
        }
    }
    // Insere a venda na tabela de vendas
    if (!empty($_SESSION['carrinho'])) {
        try {
            $conn->beginTransaction();

            foreach ($_SESSION['carrinho'] as $produtoId => $quantidade) {
                // Certifique-se de que o produto exista no array de produtos
                if (isset($produtos[$produtoId])) {
                    $produto = $produtos[$produtoId];
                    // Insert into irá buscar os valores dentro da tabela. atenção quanto ao nome da tabela 
                    $stmt = $conn->prepare('INSERT INTO vendas (produto_id, quantidade, valor_unitario) VALUES (?, ?, ?)');
                    $stmt->execute([$produtoId, $quantidade, $produto['valor_unitario']]);
                } else {
                    echo "Erro: Produto com ID {$produtoId} não encontrado.";
                }
            }

            $conn->commit();
            $_SESSION['carrinho'] = []; // Limpa o carrinho após a venda ser registrada
        } catch (Exception $e) {
            $conn->rollBack();
            echo "Erro ao registrar a venda: " . $e->getMessage();
        }
    }
}



include_once("./layout/_header.php");
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendas Produtos</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Inclua o Font Awesome para o ícone de carrinho -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!--Observação foi removido a integrity pois estava sendo bloqueado no navegador-->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css" crossorigin="anonymous">


</head>

<body class="bg-dark">
    <div class="container-fluid">
        <div class="row">
            <nav id="sidebar" class="col-md-3 col-lg-2 d-md-block sidebar bg-dark mt-4">
                <div class="position-sticky">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <h3 class="text-light">Menu</h3>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="movimentacoes.php">
                                <h5 class="text-light">Controle de Estoque</h5>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="vendas.php">
                                <h5 class="text-light">Vendas Produtos</h5>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="index.php">
                                <h5 class="text-light">Cadastro Produto</h5>
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="card mt-4">
                    <div class="card-header bg-warning">
                        <h1 class="text-center mb-4">Vendas Produtos</h1>
                    </div>

                    <div class="card-body">
                        <!-- Formulário de Pesquisa por ID -->
                        <form method="GET" action="vendas.php">
                            <div class="form-group">
                                <label for="produtoId">Pesquisar por ID do Produto:</label>
                                <input type="text" class="form-control" id="produtoId" name="produtoId" placeholder="Insira o Código do Produto">
                            </div>
                            <button type="submit" class="btn btn-primary">Pesquisar</button>
                        </form>

                        <!-- Exibição do resultado da pesquisa -->
                        <?php if (isset($_GET['produtoId'])) : ?>
                            <?php if ($produtoIndex !== false) : ?>
                                <!-- Se o produto foi encontrado, exibe as informações do produto -->
                                <p id="resultadoPesquisa" class="resultado-pesquisa">Produto: <?= $produto['produto'] ?>, Total Estoque: <?= $produto['quantidade'] ?></p>
                                <!-- Formulário para exibir código e produto -->
                                <form>
                                    <div class="form-group">
                                        <label for="codigoProduto">Código do Produto:</label>
                                        <input type="text" class="form-control" id="codigoProduto" value="<?= $produto['id'] ?>" readonly>
                                    </div>
                                    <div class="form-group">
                                        <label for="nomeProduto">Produto:</label>
                                        <input type="text" class="form-control" id="nomeProduto" value="<?= $produto['produto'] ?>" readonly>
                                    </div>
                                </form>
                            <?php else : ?>
                                <!-- Se a pesquisa foi realizada, mas o produto não foi encontrado, exibe mensagem de "Produto não encontrado" -->
                                <p id="resultadoPesquisa" class="resultado-pesquisa">Produto não encontrado.</p>
                            <?php endif; ?>
                        <?php endif; ?>

                        <h2 class="text-center mb-4">Produtos Disponíveis</h2>
                        <form method="POST" action="vendas.php" id="carrinhoForm"> <!-- Adicione um ID ao formulário -->
                            <table class="table table-bordered mt-5">
                                <thead>
                                    <tr>
                                        <th>Código</th>
                                        <th>Produto</th>
                                        <th>Estoque</th>
                                        <th>Quantidade</th>
                                        <th>Adicionar ao Carrinho</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($produtos as $produto) : ?>
                                        <tr>
                                            <td><?= $produto['id'] ?></td>
                                            <td><?= $produto['produto'] ?></td>
                                            <td><?= $produto['quantidade'] ?></td>
                                            <td>
                                                <input type="number" name="produto[<?= $produto['id'] ?>]" min="0" max="<?= $produto['quantidade'] ?>" value="0">
                                            </td>
                                            <!--Botão adicionar manda o produto para o carrinho-->
                                            <td>
                                                <button type="button" class="btn btn-primary adicionar-ao-carrinho" data-idproduto="<?= $produto['id'] ?>">Adicionar</button>

                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                        </form>

                        <!-- Área do botão carrinho -->
                        <button type="button" class="btn btn-primary bg-primary" data-toggle="modal" data-target="#carrinhoModal">
                            <i class="fas fa-shopping-cart"></i> Carrinho
                        </button>
                        <!--Button de limpar carrinho -->
                        <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#confirmarRemoverTodosModal">Limpar Carrinho</button>
                    </div>

                    <div class="card-footer">
                        <!-- Conteúdo do rodapé do card -->
                        <?php if (!empty($_SESSION['carrinho'])) : ?>
                            <button type="button" class="btn btn-success" data-toggle="modal" data-target="#carrinhoModal">
                                Finalizar Compra
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal para exibir carrinho de compras -->
    <div class="modal fade" id="carrinhoModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Carrinho de Compras</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <!-- Conteúdo do Carrinho de Compras -->
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Produto</th>
                                <th>Quantidade</th>
                                <th>Preço Unitário</th>
                                <th>Total</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($_SESSION['carrinho'] as $produtoId => $quantidade) : ?>
                                <?php $produtoIndex = $produtoId - 1; ?>
                                <?php if (isset($produtos[$produtoIndex])) : ?>
                                    <?php $produto = $produtos[$produtoIndex]; ?>
                                    <tr>
                                        <td><?= $produto['id'] ?></td>
                                        <td><?= $produto['produto'] ?></td>
                                        <td><?= $quantidade ?></td>
                                        <td>R$<?= $produto['valor_unitario'] ?></td>
                                        <td>R$<?= $quantidade * $produto['valor_unitario'] ?></td>
                                        <td>
                                            <a href="vendas.php?excluir=<?= $produto['id'] ?>" class="btn btn-sm btn-danger remover-do-carrinho">Remover</a </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
                </div>

                <!-- Modal para exibir carrinho de compras -->
                <div class="modal fade" id="carrinhoModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
                    <!-- ... Conteúdo do modal de carrinho ... -->
                </div>

                <!-- Modal para confirmar a remoção de todos os itens do carrinho -->
                <div class="modal fade" id="confirmarRemoverTodosModal" tabindex="-1" role="dialog" aria-labelledby="confirmarRemoverTodosModalLabel" aria-hidden="true">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="confirmarRemoverTodosModalLabel">Limpar Carrinho</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                Tem certeza de que deseja remover todos os itens do carrinho?
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                                <a href="vendas.php?excluirTodos=true" class="btn btn-danger">Limpar Carrinho</a>
                            </div>
                        </div>
                    </div>
                </div>


                <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
                <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
                <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>



                <script>
                    $(document).ready(function() {
                        $('.adicionar-ao-carrinho').on('click', function() {
                            var produtoId = $(this).data('idproduto');
                            var quantidade = $('input[name="produto[' + produtoId + ']"]').val();

                            if (quantidade !== "" && !isNaN(quantidade) && quantidade >= 0) {
                                // Criar um objeto FormData para enviar apenas os dados do produto selecionado
                                var formData = new FormData();
                                formData.append('produto[' + produtoId + ']', quantidade);

                                // Realizar uma requisição AJAX para adicionar o produto ao carrinho
                                $.ajax({
                                    type: 'POST',
                                    url: 'processar_vendas.php',
                                    data: formData,
                                    dataType: 'json',
                                    contentType: false,
                                    processData: false,
                                    success: function(response) {
                                        console.log('Sucesso:', response); // Esta linha foi adicionada
                                        if (response.success) {
                                            // Atualizar a página apenas se necessário
                                            location.reload();
                                        } else {
                                            alert('Erro ao adicionar produto ao carrinho. Por favor, tente novamente.');
                                        }
                                    },
                                    error: function(error) {
                                        console.error('Erro ao adicionar produto ao carrinho:', error);
                                        alert('Erro ao adicionar produto ao carrinho. Por favor, tente novamente.');
                                    }
                                });

                            } else {
                                alert('Por favor, insira uma quantidade válida.');
                            }
                        });

                        // JavaScript para confirmar a exclusão de um item do carrinho
                        $('.remover-do-carrinho').on('click', function() {
                            return confirm('Deseja remover este item do carrinho?');
                        });

                        // JavaScript para confirmar a exclusão de todos os itens do carrinho
                        $('#confirmarRemoverTodosModal .btn-danger').on('click', function() {
                            return confirm('Deseja remover todos os itens do carrinho?');
                        });
                    });
                </script>

</body>

</html>