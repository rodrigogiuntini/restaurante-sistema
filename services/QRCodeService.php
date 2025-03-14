<?php
/**
 * Serviço para geração e gerenciamento de QR Codes
 * 
 * Responsável pela criação, armazenamento e rastreamento de QR Codes
 * para mesas, cardápios, pagamentos e outros usos.
 * 
 * Status: 80% Completo
 * Pendente:
 * - Implementar rastreamento detalhado de escaneamentos
 * - Adicionar suporte a QR Codes temporários
 * - Melhorar sistema de verificação de segurança
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

class QRCodeService {
    private $db;
    
    public function __construct() {
        global $conn;
        $this->db = $conn;
    }
    
    /**
     * Gera um novo QR Code para uma mesa
     */
    public function generateTableQRCode($tenantId, $tableId, $tableName) {
        try {
            // Gerar um código único
            $uniqueCode = $this->generateUniqueCode();
            
            // Criar hash para verificação de segurança
            $hash = $this->generateSecurityHash($tenantId, $tableId, $uniqueCode);
            
            // URL para o QR Code
            $baseUrl = BASE_URL;
            $qrCodeData = "{$baseUrl}/menu/table/{$uniqueCode}";
            
            // Armazenar informações adicionais como JSON
            $data = json_encode([
                'table_name' => $tableName,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Verificar se já existe um QR Code para esta mesa
            $query = "SELECT * FROM qr_codes WHERE tenant_id = ? AND table_id = ? AND type = 'table'";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("ii", $tenantId, $tableId);
            $stmt->execute();
            $existingQrCode = $stmt->get_result()->fetch_assoc();
            
            if ($existingQrCode) {
                // Atualizar QR Code existente
                $updateQuery = "UPDATE qr_codes 
                                SET code = ?, hash = ?, data = ?, active = 1, updated_at = CURRENT_TIMESTAMP 
                                WHERE id = ?";
                $stmt = $this->db->prepare($updateQuery);
                $stmt->bind_param("sssi", $uniqueCode, $hash, $data, $existingQrCode['id']);
                $stmt->execute();
                
                // Atualizar também na tabela de mesas
                $updateTableQuery = "UPDATE tables SET qr_code = ?, qr_code_hash = ? WHERE id = ?";
                $stmt = $this->db->prepare($updateTableQuery);
                $stmt->bind_param("ssi", $uniqueCode, $hash, $tableId);
                $stmt->execute();
                
                $qrCodeId = $existingQrCode['id'];
            } else {
                // Inserir novo QR Code
                $insertQuery = "INSERT INTO qr_codes (tenant_id, table_id, code, hash, type, data, active) 
                                VALUES (?, ?, ?, ?, 'table', ?, 1)";
                $stmt = $this->db->prepare($insertQuery);
                $stmt->bind_param("iisss", $tenantId, $tableId, $uniqueCode, $hash, $data);
                $stmt->execute();
                $qrCodeId = $this->db->insert_id;
                
                // Atualizar tabela de mesas
                $updateTableQuery = "UPDATE tables SET qr_code = ?, qr_code_hash = ? WHERE id = ?";
                $stmt = $this->db->prepare($updateTableQuery);
                $stmt->bind_param("ssi", $uniqueCode, $hash, $tableId);
                $stmt->execute();
            }
            
            // Gerar imagem do QR Code
            $qrImagePath = $this->generateQRCodeImage($qrCodeData, $uniqueCode, $tenantId);
            
            return [
                'success' => true,
                'qr_code_id' => $qrCodeId,
                'code' => $uniqueCode,
                'hash' => $hash,
                'image_path' => $qrImagePath,
                'data' => $qrCodeData
            ];
        } catch (\Exception $e) {
            error_log('Erro ao gerar QR Code: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Gera um novo QR Code para o cardápio geral
     */
    public function generateMenuQRCode($tenantId, $extraData = []) {
        try {
            // Gerar um código único
            $uniqueCode = $this->generateUniqueCode();
            
            // Criar hash para verificação de segurança
            $hash = $this->generateSecurityHash($tenantId, null, $uniqueCode);
            
            // URL para o QR Code
            $baseUrl = BASE_URL;
            $qrCodeData = "{$baseUrl}/menu/{$uniqueCode}";
            
            // Armazenar informações adicionais como JSON
            $data = json_encode(array_merge([
                'created_at' => date('Y-m-d H:i:s')
            ], $extraData));
            
            // Inserir novo QR Code
            $insertQuery = "INSERT INTO qr_codes (tenant_id, code, hash, type, data, active) 
                            VALUES (?, ?, ?, 'menu', ?, 1)";
            $stmt = $this->db->prepare($insertQuery);
            $stmt->bind_param("isss", $tenantId, $uniqueCode, $hash, $data);
            $stmt->execute();
            $qrCodeId = $this->db->insert_id;
            
            // Gerar imagem do QR Code
            $qrImagePath = $this->generateQRCodeImage($qrCodeData, $uniqueCode, $tenantId);
            
            return [
                'success' => true,
                'qr_code_id' => $qrCodeId,
                'code' => $uniqueCode,
                'hash' => $hash,
                'image_path' => $qrImagePath,
                'data' => $qrCodeData
            ];
        } catch (\Exception $e) {
            error_log('Erro ao gerar QR Code do cardápio: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Gera um novo QR Code para pagamento
     */
    public function generatePaymentQRCode($tenantId, $orderId, $amount, $expiresInMinutes = 60) {
        try {
            // Gerar um código único
            $uniqueCode = $this->generateUniqueCode();
            
            // Criar hash para verificação de segurança
            $hash = $this->generateSecurityHash($tenantId, $orderId, $uniqueCode);
            
            // URL para o QR Code
            $baseUrl = BASE_URL;
            $qrCodeData = "{$baseUrl}/payment/{$uniqueCode}";
            
            // Calcular data de expiração
            $expirationDate = date('Y-m-d H:i:s', strtotime("+{$expiresInMinutes} minutes"));
            
            // Armazenar informações adicionais como JSON
            $data = json_encode([
                'order_id' => $orderId,
                'amount' => $amount,
                'created_at' => date('Y-m-d H:i:s'),
                'expires_at' => $expirationDate
            ]);
            
            // Inserir novo QR Code
            $insertQuery = "INSERT INTO qr_codes (tenant_id, code, hash, type, data, active) 
                            VALUES (?, ?, ?, 'payment', ?, 1)";
            $stmt = $this->db->prepare($insertQuery);
            $stmt->bind_param("isss", $tenantId, $uniqueCode, $hash, $data);
            $stmt->execute();
            $qrCodeId = $this->db->insert_id;
            
            // Gerar imagem do QR Code
            $qrImagePath = $this->generateQRCodeImage($qrCodeData, $uniqueCode, $tenantId);
            
            return [
                'success' => true,
                'qr_code_id' => $qrCodeId,
                'code' => $uniqueCode,
                'hash' => $hash,
                'image_path' => $qrImagePath,
                'data' => $qrCodeData,
                'expires_at' => $expirationDate
            ];
        } catch (\Exception $e) {
            error_log('Erro ao gerar QR Code de pagamento: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Gera um código único para o QR Code
     */
    private function generateUniqueCode() {
        return substr(md5(uniqid(mt_rand(), true)), 0, 10);
    }
    
    /**
     * Gera um hash de segurança para o QR Code
     */
    private function generateSecurityHash($tenantId, $resourceId, $code) {
        $secretKey = QR_CODE_SECRET_KEY;
        return hash('sha256', $tenantId . $resourceId . $code . $secretKey);
    }
    
    /**
     * Gera a imagem do QR Code e retorna o caminho
     */
    private function generateQRCodeImage($data, $code, $tenantId) {
        try {
            // Caminho para biblioteca de geração de QR Code
            require_once __DIR__ . '/../vendor/phpqrcode/qrlib.php';
            
            // Diretório para salvar os QR Codes
            $directory = __DIR__ . '/../public/qr';
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }
            
            // Diretório específico para o tenant
            $tenantDirectory = $directory . '/' . $tenantId;
            if (!file_exists($tenantDirectory)) {
                mkdir($tenantDirectory, 0755, true);
            }
            
            // Caminho completo para a imagem
            $filePath = $tenantDirectory . '/' . $code . '.png';
            $relativePath = '/qr/' . $tenantId . '/' . $code . '.png';
            
            // Gerar o QR Code
            \QRcode::png($data, $filePath, QR_ECLEVEL_H, 10, 2);
            
            return $relativePath;
        } catch (\Exception $e) {
            error_log('Erro ao gerar imagem do QR Code: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Verifica se um QR Code é válido
     */
    public function validateQRCode($code, $hash, $type = null) {
        try {
            $query = "SELECT * FROM qr_codes WHERE code = ? AND hash = ? AND active = 1";
            if ($type) {
                $query .= " AND type = ?";
            }
            
            $stmt = $this->db->prepare($query);
            
            if ($type) {
                $stmt->bind_param("sss", $code, $hash, $type);
            } else {
                $stmt->bind_param("ss", $code, $hash);
            }
            
            $stmt->execute();
            $qrCode = $stmt->get_result()->fetch_assoc();
            
            if (!$qrCode) {
                return [
                    'valid' => false,
                    'error' => 'QR Code inválido ou expirado'
                ];
            }
            
            // Verificar expiração para QR Codes de pagamento
            if ($qrCode['type'] === 'payment') {
                $data = json_decode($qrCode['data'], true);
                if (isset($data['expires_at']) && strtotime($data['expires_at']) < time()) {
                    return [
                        'valid' => false,
                        'error' => 'QR Code expirado'
                    ];
                }
            }
            
            // Registrar escaneamento
            $this->registerScan($qrCode['id']);
            
            return [
                'valid' => true,
                'qr_code' => $qrCode
            ];
        } catch (\Exception $e) {
            error_log('Erro ao validar QR Code: ' . $e->getMessage());
            return [
                'valid' => false,
                'error' => 'Erro ao validar QR Code'
            ];
        }
    }
    
    /**
     * Registra um escaneamento de QR Code
     */
    private function registerScan($qrCodeId) {
        try {
            $query = "UPDATE qr_codes SET scan_count = scan_count + 1, last_scanned = CURRENT_TIMESTAMP WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("i", $qrCodeId);
            $stmt->execute();
            
            // TODO: Implementar registro detalhado de escaneamentos
            return true;
        } catch (\Exception $e) {
            error_log('Erro ao registrar escaneamento: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Desativa um QR Code
     */
    public function deactivateQRCode($qrCodeId, $tenantId) {
        try {
            $query = "UPDATE qr_codes SET active = 0, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND tenant_id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("ii", $qrCodeId, $tenantId);
            $stmt->execute();
            
            return $stmt->affected_rows > 0;
        } catch (\Exception $e) {
            error_log('Erro ao desativar QR Code: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtém QR Codes de um tenant por tipo
     */
    public function getQRCodesByType($tenantId, $type) {
        try {
            $query = "SELECT * FROM qr_codes WHERE tenant_id = ? AND type = ? ORDER BY created_at DESC";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("is", $tenantId, $type);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $qrCodes = [];
            while ($row = $result->fetch_assoc()) {
                $qrCodes[] = $row;
            }
            
            return $qrCodes;
        } catch (\Exception $e) {
            error_log('Erro ao obter QR Codes: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtém um QR Code específico
     */
    public function getQRCodeById($qrCodeId, $tenantId) {
        try {
            $query = "SELECT * FROM qr_codes WHERE id = ? AND tenant_id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("ii", $qrCodeId, $tenantId);
            $stmt->execute();
            return $stmt->get_result()->fetch_assoc();
        } catch (\Exception $e) {
            error_log('Erro ao obter QR Code: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Obtém QR Code pelo código
     */
    public function getQRCodeByCode($code) {
        try {
            $query = "SELECT * FROM qr_codes WHERE code = ? AND active = 1";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("s", $code);
            $stmt->execute();
            return $stmt->get_result()->fetch_assoc();
        } catch (\Exception $e) {
            error_log('Erro ao obter QR Code por código: ' . $e->getMessage());
            return null;
        }
    }
}