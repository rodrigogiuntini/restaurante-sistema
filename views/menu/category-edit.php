<?php
/**
 * View para editar uma categoria do cardápio
 * 
 * Esta view apresenta um formulário para editar uma
 * categoria existente do cardápio do restaurante.
 * 
 * Status: 90% Completo
 * Pendente:
 * - Melhorar preview da imagem
 * - Adicionar campos específicos por tipo de restaurante
 */

// Definir título da página
$pageTitle = 'Editar Categoria';
$extraCss = ['menu.css'];
$extraJs = ['menu.js'];
require_once APP_PATH . '/views/partials/header.php';

// Recuperar dados do formulário em caso de erro
$formData = $_SESSION['form_data'] ?? $category;
unset($_SESSION['form_data']);
?>

<div class="content-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="page-title-box">
                    <h4 class="page-title">Editar Categoria</h4>
                    <div class="breadcrumb">
                        <a href="/dashboard" class="breadcrumb-item">Dashboard</a>
                        <a href="/menu" class="breadcrumb-item">Cardápio</a>
                        <a href="/menu/categories" class="breadcrumb-item">Categorias</a>
                        <span class="breadcrumb-item active">Editar</span>
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
        
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form action="/menu/categories/update/<?php echo $category['id']; ?>" method="post" enctype="multipart/form-data">
                            <div class="form-group">
                                <label for="name">Nome da Categoria <span class="text-danger">*</span></label>
                                <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($formData['name'] ?? ''); ?>" required>
                                <small class="form-text text-muted">Ex: Entradas, Pratos Principais, Sobremesas</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="description">Descrição</label>
                                <textarea id="description" name="description" class="form-control" rows="3"><?php echo htmlspecialchars($formData['description'] ?? ''); ?></textarea>
                                <small class="form-text text-muted">Uma breve descrição desta categoria (opcional)</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="image">Imagem</label>
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input" id="image" name="image" accept="image/jpeg,image/png,image/webp">
                                    <label class="custom-file-label" for="image">Escolher arquivo</label>
                                </div>
                                <small class="form-text text-muted">Formatos permitidos: JPEG, PNG, WebP. Tamanho máximo: 2MB.</small>
                                
                                <?php if (!empty($category['image'])): ?>
                                    <div class="current-image mt-2">
                                        <p>Imagem atual:</p>
                                        <div class="d-flex align-items-center">
                                            <img src="<?php echo $category['image']; ?>" alt="<?php echo htmlspecialchars($category['name']); ?>" class="img-thumbnail mr-3" style="max-height: 100px;">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="remove_image" name="remove_image" value="1">
                                                <label class="form-check-label" for="remove_image">
                                                    Remover imagem atual
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div id="image-preview" class="mt-2"></div>
                            </div>
                            
                            <div class="form-group">
                                <label for="sort_order">Ordem de Exibição</label>
                                <input type="number" id="sort_order" name="sort_order" class="form-control" value="<?php echo isset($formData['sort_order']) ? intval($formData['sort_order']) : 0; ?>" min="0">
                                <small class="form-text text-muted">Determina a ordem de exibição no cardápio (menor número = primeiro)</small>
                            </div>
                            
                            <div class="form-group">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="active" name="active" <?php echo (!isset($formData['active']) || $formData['active']) ? 'checked' : ''; ?>>
                                    <label class="custom-control-label" for="active">Ativa</label>
                                </div>
                                <small class="form-text text-muted">Desativar uma categoria a oculta do cardápio, mas mantém seus itens.</small>
                            </div>
                            
                            <div class="form-buttons">
                                <button type="submit" class="btn btn-primary">Salvar</button>
                                <a href="/menu/categories" class="btn btn-secondary">Cancelar</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Exibir nome do arquivo selecionado
        document.getElementById('image').addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name || 'Escolher arquivo';
            const label = e.target.nextElementSibling;
            label.textContent = fileName;
            
            // Preview da imagem
            const preview = document.getElementById('image-preview');
            preview.innerHTML = '';
            
            if (e.target.files[0]) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    const img = document.createElement('img');
                    img.src = event.target.result;
                    img.classList.add('img-thumbnail', 'mt-2');
                    img.style.maxHeight = '150px';
                    preview.appendChild(img);
                };
                reader.readAsDataURL(e.target.files[0]);
            }
        });
        
        // Desabilitar upload de imagem se "Remover imagem" estiver marcado
        const removeCheckbox = document.getElementById('remove_image');
        if (removeCheckbox) {
            removeCheckbox.addEventListener('change', function() {
                document.getElementById('image').disabled = this.checked;
            });
        }
    });
</script>

<?php require_once APP_PATH . '/views/partials/footer.php'; ?>