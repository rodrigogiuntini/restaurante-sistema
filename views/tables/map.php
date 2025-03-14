<?php
/**
 * View do mapa de mesas
 * 
 * Esta view apresenta uma interface visual e interativa para
 * gerenciar a disposição e o status das mesas do restaurante.
 * 
 * Status: 80% Completo
 * Pendente:
 * - Melhorar a responsividade para diferentes tamanhos de tela
 * - Adicionar filtros e busca de mesas
 * - Implementar seleção múltipla de mesas
 */

// Incluir cabeçalho
require_once __DIR__ . '/../partials/header.php';

// Definir cores de status para uso no CSS
$statusColors = [
    'available' => '#28a745', // Verde
    'occupied' => '#dc3545',  // Vermelho
    'reserved' => '#ffc107',  // Amarelo
    'cleaning' => '#17a2b8',  // Azul claro
    'inactive' => '#6c757d'   // Cinza
];

// Definir labels de status
$statusLabels = [
    'available' => 'Disponível',
    'occupied' => 'Ocupada',
    'reserved' => 'Reservada',
    'cleaning' => 'Em limpeza',
    'inactive' => 'Inativa'
];
?>

<div class="content-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="page-title-box">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="page-title">Mapa de Mesas</h4>
                        <div>
                            <a href="/tables/list" class="btn btn-outline-secondary btn-sm">Ver lista</a>
                            <a href="/tables/add" class="btn btn-primary btn-sm">Nova Mesa</a>
                        </div>
                    </div>
                    <div class="breadcrumb">
                        <a href="/dashboard" class="breadcrumb-item">Dashboard</a>
                        <span class="breadcrumb-item active">Mapa de Mesas</span>
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
        
        <!-- Opções e legenda -->
        <div class="row mb-3">
            <div class="col-md-6">
                <!-- Seletor de área -->
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Área</h5>
                        <select id="area-selector" class="form-control">
                            <option value="all">Todas as áreas</option>
                            <?php foreach ($areas as $area): ?>
                                <option value="<?php echo $area['id']; ?>"><?php echo htmlspecialchars($area['name']); ?></option>
                            <?php endforeach; ?>
                            <option value="0">Sem área definida</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <!-- Legenda de status -->
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Status</h5>
                        <div class="d-flex flex-wrap">
                            <?php foreach ($statusLabels as $status => $label): ?>
                                <div class="mr-3 mb-2">
                                    <span class="status-dot" style="background-color: <?php echo $statusColors[$status]; ?>"></span>
                                    <span><?php echo $label; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Mapa das mesas -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-3">
                            <h5 class="card-title" id="area-title">Todas as áreas</h5>
                            <div>
                                <button id="edit-mode-btn" class="btn btn-outline-primary">Modo Edição</button>
                                <button id="save-positions-btn" class="btn btn-success d-none">Salvar Posições</button>
                                <button id="cancel-edit-btn" class="btn btn-outline-secondary d-none">Cancelar</button>
                            </div>
                        </div>
                        
                        <div id="tables-map-container" class="tables-map-container">
                            <?php if (empty($tables)): ?>
                                <div class="text-center p-5">
                                    <h4>Nenhuma mesa cadastrada</h4>
                                    <p>Adicione mesas para visualizar o mapa.</p>
                                    <a href="/tables/add" class="btn btn-primary">Adicionar Mesa</a>
                                </div>
                            <?php else: ?>
                                <!-- As mesas serão renderizadas aqui via JavaScript -->
                                <div id="tables-map" class="tables-map">
                                    <!-- Template de mesa para clonar via JS -->
                                    <div id="table-template" class="table-item d-none" data-id="">
                                        <div class="table-content">
                                            <div class="table-number"></div>
                                            <div class="table-capacity"></div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de ações da mesa -->
<div class="modal fade" id="table-actions-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Mesa <span id="modal-table-name"></span></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="table-info mb-3">
                    <div class="row">
                        <div class="col-6">
                            <p><strong>Número:</strong> <span id="modal-table-number"></span></p>
                        </div>
                        <div class="col-6">
                            <p><strong>Capacidade:</strong> <span id="modal-table-capacity"></span> pessoas</p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <p><strong>Área:</strong> <span id="modal-table-area"></span></p>
                        </div>
                        <div class="col-6">
                            <p><strong>Status:</strong> <span id="modal-table-status"></span></p>
                        </div>
                    </div>
                    <div id="occupied-since-row" class="row d-none">
                        <div class="col-12">
                            <p><strong>Ocupada desde:</strong> <span id="modal-occupied-since"></span></p>
                        </div>
                    </div>
                </div>
                
                <div class="actions">
                    <h6>Alterar Status</h6>
                    <form id="change-status-form" action="" method="post">
                        <div class="form-group">
                            <select name="status" id="table-status-select" class="form-control">
                                <?php foreach ($statusLabels as $status => $label): ?>
                                    <option value="<?php echo $status; ?>"><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Atualizar Status</button>
                    </form>
                    
                    <hr>
                    
                    <div class="d-flex justify-content-between">
                        <div>
                            <a id="edit-table-link" href="#" class="btn btn-outline-primary btn-sm">Editar Mesa</a>
                            <a id="history-table-link" href="#" class="btn btn-outline-info btn-sm">Histórico</a>
                        </div>
                        <div>
                            <a id="new-order-link" href="#" class="btn btn-success btn-sm">Novo Pedido</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .tables-map-container {
        position: relative;
        overflow: auto;
        border: 1px solid #ddd;
        background-color: #f9f9f9;
        height: 600px;
    }
    
    .tables-map {
        position: relative;
        width: 2000px;
        height: 1500px;
    }
    
    .table-item {
        position: absolute;
        width: 120px;
        height: 80px;
        background-color: #ffffff;
        border: 3px solid #28a745; /* Default: available */
        border-radius: 10px;
        display: flex;
        justify-content: center;
        align-items: center;
        cursor: pointer;
        user-select: none;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        transition: transform 0.2s, box-shadow 0.2s;
    }
    
    .table-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }
    
    .table-item.ui-draggable-dragging {
        transform: scale(1.05);
        z-index: 1000;
    }
    
    .table-content {
        text-align: center;
    }
    
    .table-number {
        font-weight: bold;
        font-size: 1.25rem;
    }
    
    .table-capacity {
        font-size: 0.9rem;
        color: #666;
    }
    
    .status-dot {
        display: inline-block;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        margin-right: 5px;
    }
    
    /* Status colors */
    .table-item[data-status="available"] { border-color: #28a745; }
    .table-item[data-status="occupied"] { border-color: #dc3545; }
    .table-item[data-status="reserved"] { border-color: #ffc107; }
    .table-item[data-status="cleaning"] { border-color: #17a2b8; }
    .table-item[data-status="inactive"] { border-color: #6c757d; background-color: #f8f9fa; }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Dados das mesas
        const tables = <?php echo json_encode($tables); ?>;
        const statusLabels = <?php echo json_encode($statusLabels); ?>;
        const areaMap = {};
        
        <?php foreach ($areas as $area): ?>
            areaMap[<?php echo $area['id']; ?>] = '<?php echo addslashes($area['name']); ?>';
        <?php endforeach; ?>
        
        // Referências DOM
        const tablesMap = document.getElementById('tables-map');
        const tableTemplate = document.getElementById('table-template');
        const areaSelector = document.getElementById('area-selector');
        const areaTitle = document.getElementById('area-title');
        const editModeBtn = document.getElementById('edit-mode-btn');
        const savePositionsBtn = document.getElementById('save-positions-btn');
        const cancelEditBtn = document.getElementById('cancel-edit-btn');
        
        // Estado da aplicação
        let currentAreaId = 'all';
        let editMode = false;
        let tablePositions = {}; // Para armazenar posições originais
        
        // Renderizar mesas
        function renderTables() {
            // Limpar mapa
            const existingTables = tablesMap.querySelectorAll('.table-item:not(#table-template)');
            existingTables.forEach(table => table.remove());
            
            // Filtrar por área se necessário
            let filteredTables = tables;
            if (currentAreaId !== 'all') {
                const areaIdNum = parseInt(currentAreaId);
                filteredTables = tables.filter(table => {
                    if (areaIdNum === 0) {
                        return !table.area_id;
                    }
                    return table.area_id === areaIdNum;
                });
            }
            
            // Renderizar mesas filtradas
            filteredTables.forEach(table => {
                const tableElement = tableTemplate.cloneNode(true);
                tableElement.id = '';
                tableElement.classList.remove('d-none');
                tableElement.setAttribute('data-id', table.id);
                tableElement.setAttribute('data-status', table.status);
                tableElement.setAttribute('data-area-id', table.area_id || 0);
                
                // Posicionar
                tableElement.style.left = `${table.position_x}px`;
                tableElement.style.top = `${table.position_y}px`;
                
                // Preencher detalhes
                const tableNumber = tableElement.querySelector('.table-number');
                const tableCapacity = tableElement.querySelector('.table-capacity');
                
                tableNumber.textContent = table.number;
                tableCapacity.textContent = `${table.capacity} lugares`;
                
                // Adicionar dados extras como atributos para uso nos modais
                tableElement.setAttribute('data-name', table.name || '');
                tableElement.setAttribute('data-number', table.number);
                tableElement.setAttribute('data-capacity', table.capacity);
                tableElement.setAttribute('data-area-name', table.area_name || 'Sem área');
                tableElement.setAttribute('data-occupied-since', table.occupied_since || '');
                
                // Adicionar ao mapa
                tablesMap.appendChild(tableElement);
                
                // Adicionar evento de clique
                tableElement.addEventListener('click', function() {
                    if (!editMode) {
                        showTableActionsModal(table.id);
                    }
                });
            });
            
            // Inicializar arraste se estiver em modo de edição
            if (editMode) {
                initDraggable();
            }
        }
        
        // Exibir modal de ações da mesa
        function showTableActionsModal(tableId) {
            const tableElement = tablesMap.querySelector(`.table-item[data-id="${tableId}"]`);
            if (!tableElement) return;
            
            // Obter dados da mesa
            const tableInfo = tables.find(t => t.id == tableId);
            if (!tableInfo) return;
            
            // Preencher modal
            document.getElementById('modal-table-name').textContent = tableInfo.name || `#${tableInfo.number}`;
            document.getElementById('modal-table-number').textContent = tableInfo.number;
            document.getElementById('modal-table-capacity').textContent = tableInfo.capacity;
            document.getElementById('modal-table-area').textContent = tableInfo.area_name || 'Sem área';
            document.getElementById('modal-table-status').textContent = statusLabels[tableInfo.status];
            
            const occupiedSinceRow = document.getElementById('occupied-since-row');
            if (tableInfo.status === 'occupied' && tableInfo.occupied_since) {
                occupiedSinceRow.classList.remove('d-none');
                document.getElementById('modal-occupied-since').textContent = formatDateTime(tableInfo.occupied_since);
            } else {
                occupiedSinceRow.classList.add('d-none');
            }
            
            // Atualizar seletor de status
            const statusSelect = document.getElementById('table-status-select');
            statusSelect.value = tableInfo.status;
            
            // Atualizar links
            document.getElementById('edit-table-link').href = `/tables/edit/${tableId}`;
            document.getElementById('history-table-link').href = `/tables/history/${tableId}`;
            document.getElementById('new-order-link').href = `/orders/create?table_id=${tableId}`;
            
            // Atualizar formulário
            const form = document.getElementById('change-status-form');
            form.action = `/tables/update-status/${tableId}`;
            
            // Exibir modal
            $('#table-actions-modal').modal('show');
        }
        
        // Inicializar elementos arrastáveis
        function initDraggable() {
            $('.table-item:not(#table-template)').draggable({
                containment: 'parent',
                grid: [10, 10],
                start: function(event, ui) {
                    const tableId = ui.helper.data('id');
                    // Armazenar posição original se ainda não estiver armazenada
                    if (!tablePositions[tableId]) {
                        tablePositions[tableId] = {
                            x: ui.position.left,
                            y: ui.position.top
                        };
                    }
                }
            });
        }
        
        // Salvar posições das mesas
        function savePositions() {
            const tablesToUpdate = [];
            const tableElements = tablesMap.querySelectorAll('.table-item:not(#table-template)');
            
            tableElements.forEach(el => {
                const tableId = el.getAttribute('data-id');
                const posX = parseInt(el.style.left);
                const posY = parseInt(el.style.top);
                
                tablesToUpdate.push({
                    id: tableId,
                    position_x: posX,
                    position_y: posY
                });
            });
            
            // Enviar ao servidor
            fetch('/tables/save-positions', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ tables: tablesToUpdate })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Atualizar dados em memória
                    tablesToUpdate.forEach(update => {
                        const table = tables.find(t => t.id == update.id);
                        if (table) {
                            table.position_x = update.position_x;
                            table.position_y = update.position_y;
                        }
                    });
                    
                    // Limpar posições armazenadas
                    tablePositions = {};
                    
                    // Desativar modo de edição
                    toggleEditMode(false);
                    
                    // Mostrar mensagem de sucesso
                    alert(`Posições atualizadas com sucesso! ${data.updated_count} mesas foram atualizadas.`);
                } else {
                    alert('Erro ao salvar posições: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erro ao salvar posições:', error);
                alert('Erro ao salvar posições. Verifique o console para mais detalhes.');
            });
        }
        
        // Cancelar modo de edição
        function cancelEdit() {
            // Restaurar posições originais
            Object.keys(tablePositions).forEach(tableId => {
                const tableElement = tablesMap.querySelector(`.table-item[data-id="${tableId}"]`);
                if (tableElement) {
                    tableElement.style.left = `${tablePositions[tableId].x}px`;
                    tableElement.style.top = `${tablePositions[tableId].y}px`;
                }
            });
            
            // Limpar posições armazenadas
            tablePositions = {};
            
            // Desativar modo de edição
            toggleEditMode(false);
        }
        
        // Ativar/desativar modo de edição
        function toggleEditMode(enabled) {
            editMode = enabled;
            
            if (enabled) {
                editModeBtn.classList.add('d-none');
                savePositionsBtn.classList.remove('d-none');
                cancelEditBtn.classList.remove('d-none');
                initDraggable();
            } else {
                editModeBtn.classList.remove('d-none');
                savePositionsBtn.classList.add('d-none');
                cancelEditBtn.classList.add('d-none');
                $('.table-item').draggable('destroy');
            }
        }
        
        // Formatar data/hora
        function formatDateTime(dateTimeStr) {
            const date = new Date(dateTimeStr);
            const day = String(date.getDate()).padStart(2, '0');
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            
            return `${day}/${month} ${hours}:${minutes}`;
        }
        
        // Event listeners
        areaSelector.addEventListener('change', function() {
            currentAreaId = this.value;
            
            // Atualizar título
            if (currentAreaId === 'all') {
                areaTitle.textContent = 'Todas as áreas';
            } else if (currentAreaId === '0') {
                areaTitle.textContent = 'Mesas sem área definida';
            } else {
                areaTitle.textContent = areaMap[currentAreaId] || `Área ${currentAreaId}`;
            }
            
            renderTables();
        });
        
        editModeBtn.addEventListener('click', function() {
            toggleEditMode(true);
        });
        
        savePositionsBtn.addEventListener('click', savePositions);
        cancelEditBtn.addEventListener('click', cancelEdit);
        
        // Renderização inicial
        renderTables();
    });
</script>

<?php
// Incluir rodapé
require_once __DIR__ . '/../partials/footer.php';
?>