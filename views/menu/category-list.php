<?php
/**
 * View para listar categorias do cardápio
 * 
 * Esta view exibe a lista de categorias do cardápio com opções
 * para adicionar, editar e excluir categorias.
 * 
 * Status: 85% Completo
 * Pendente:
 * - Implementar ordenação por drag-and-drop
 * - Aprimorar interface para exibição de imagens
 */

// Definir título da página
$pageTitle = 'Categorias do Cardápio';
$extraCss = ['menu.css'];
$extraJs = ['menu.js'];
require_once APP_PATH . '/views/partials/header.php';
?>

<div class="content-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="page-title-box">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="page-title">Categorias do Cardápio</h4>
                        <a href="/menu/categories/create" class="btn btn-primary">
                            <i class="icon-plus"></i> Nova Categoria
                        </a>
                    </div>
                    <div class="breadcrumb">
                        <a href="/dashboard" class="breadcrumb-item">Dashboard</a>
                        <a href="/menu" class="breadcrumb-item">Cardápio</a>
                        <span class="breadcrumb-item active">Categorias</span>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <?php 
                    echo $_SESSION['error_message']; 
                    unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php 
                    echo $_SESSION['success_message']; 
                    unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <?php if (empty($categories)): ?>
                            <div class="text-center p-5">
                                <h4>Nenhuma categoria cadastrada</h4>
                                <p>Adicione categorias para organizar seu cardápio.</p>
                                <a href="/menu/categories/create" class="btn btn-primary">
                                    <i class="icon-plus"></i> Adicionar Categoria
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-centered mb-0">
                                    <thead>
                                        <tr>
                                            <th width="5%">#</th>
                                            <th width="30%">Nome</th>
                                            <th width="30%">Descrição</th>
                                            <th width="10%">Itens</th>
                                            <th width="10%">Status</th>
                                            <th width="15%">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody id="sortable-categories">
                                        <?php foreach ($categories as $category): ?>
                                            <tr data-id="<?php echo $category['id']; ?>">
                                                <td>
                                                    <i class="fa fa-arrows-alt handle cursor-move mr-2 text-muted"></i>
                                                    <?php echo $category['sort_order']; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($category['image'])): ?>
                                                        <img src="<?php echo $category['image']; ?>" alt="<?php echo htmlspecialchars($category['name']); ?>" class="category-thumbnail mr-2">
                                                    <?php endif; ?>
                                                    <?php echo htmlspecialchars($category['name']); ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($category['description'] ?: ''); ?></td>
                                                <td>
                                                    <span class="badge badge-primary"><?php echo $category['item_count']; ?></span>
                                                </td>
                                                <td>
                                                    <?php if ($category['active']): ?>
                                                        <span class="badge badge-success">Ativo</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-secondary">Inativo</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="/menu/categories/edit/<?php echo $category['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="icon-edit"></i>
                                                    </a>
                                                    <?php if ($category['item_count'] == 0): ?>
                                                        <a href="javascript:void(0);" onclick="confirmDelete(<?php echo $category['id']; ?>, '<?php echo addslashes($category['name']); ?>')" class="btn btn-sm btn-outline-danger">
                                                            <i class="icon-trash"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-outline-danger" disabled title="Esta categoria possui itens associados">
                                                            <i class="icon-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <a href="/menu/items?category=<?php echo $category['id']; ?>" class="btn btn-sm btn-outline-info" title="Ver itens">
                                                        <i class="icon-list"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de confirmação de exclusão -->
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar Exclusão</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja excluir a categoria <strong id="categoryName"></strong>?</p>
                <p class="text-danger">Esta ação não pode ser desfeita.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <a href="#" id="deleteLink" class="btn btn-danger">Excluir</a>
            </div>
        </div>
    </div>
</div>

<script>
    // Função para confirmar exclusão
    function confirmDelete(id, name) {
        document.getElementById('categoryName').textContent = name;
        document.getElementById('deleteLink').href = '/menu/categories/delete/' + id;
        $('#deleteModal').modal('show');
    }
    
    // Inicializar ordenação por drag-and-drop
    document.addEventListener('DOMContentLoaded', function() {
        // Verificar se há suporte ao Sortable.js
        if (typeof Sortable !== 'undefined') {
            const sortableList = document.getElementById('sortable-categories');
            if (sortableList) {
                const sortable = Sortable.create(sortableList, {
                    handle: '.handle',
                    animation: 150,
                    onEnd: function(evt) {
                        saveOrder();
                    }
                });
            }
        }
        
        // Função para salvar a ordem
        function saveOrder() {
            const rows = document.querySelectorAll('#sortable-categories tr');
            const categories = [];
            
            rows.forEach(row => {
                categories.push(parseInt(row.getAttribute('data-id')));
            });
            
            // Enviar ao servidor
            fetch('/menu/categories/update-order', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ categories: categories })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Atualizar números de ordem na tabela
                    rows.forEach((row, index) => {
                        const orderCell = row.querySelector('td:first-child');
                        const iconElement = orderCell.querySelector('i');
                        const textNode = document.createTextNode(index + 1);
                        
                        // Limpar conteúdo anterior e adicionar ícone e novo número
                        orderCell.innerHTML = '';
                        orderCell.appendChild(iconElement);
                        orderCell.appendChild(document.createTextNode(' '));
                        orderCell.appendChild(textNode);
                    });
                    
                    // Notificar usuário
                    showToast('Ordem atualizada com sucesso!', 'success');
                } else {
                    showToast('Erro ao atualizar ordem: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Erro ao salvar ordem:', error);
                showToast('Erro ao atualizar ordem. Tente novamente.', 'error');
            });
        }
        
        // Função simples para exibir toast
        function showToast(message, type) {
            if (typeof toastr !== 'undefined') {
                toastr[type](message);
            } else {
                alert(message);
            }
        }
    });
</script>

<?php require_once APP_PATH . '/views/partials/footer.php'; ?>