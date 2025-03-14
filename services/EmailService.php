<?php
/**
 * Serviço de Email
 * Responsável por enviar emails para usuários do sistema
 */

class EmailService {
    private $from;
    private $fromName;
    private $replyTo;
    
    /**
     * Construtor
     */
    public function __construct() {
        // Carregar configurações
        $config = require_once APP_PATH . '/config/email.php';
        
        $this->from = $config['from'] ?? 'no-reply@restaurantesaas.com.br';
        $this->fromName = $config['from_name'] ?? APP_NAME;
        $this->replyTo = $config['reply_to'] ?? 'suporte@restaurantesaas.com.br';
    }
    
    /**
     * Envia um email simples
     */
    public function send($to, $subject, $message, $options = []) {
        // Implementação básica usando a função mail do PHP
        // Em produção, usar PHPMailer, Swift Mailer ou outro sistema mais robusto
        
        $headers = [
            'From: ' . $this->fromName . ' <' . $this->from . '>',
            'Reply-To: ' . $this->replyTo,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'X-Mailer: PHP/' . phpversion()
        ];
        
        if (isset($options['cc'])) {
            $headers[] = 'Cc: ' . $options['cc'];
        }
        
        if (isset($options['bcc'])) {
            $headers[] = 'Bcc: ' . $options['bcc'];
        }
        
        // Log do email
        $this->logEmail($to, $subject, $message);
        
        // Em ambiente de desenvolvimento, apenas logar o email sem enviar
        if (getenv('APP_ENV') === 'development') {
            return true;
        }
        
        // Enviar o email
        return mail($to, $subject, $message, implode("\r\n", $headers));
    }
    
    /**
     * Envia um email HTML
     */
    public function sendHtml($to, $subject, $htmlMessage, $plainMessage = '', $options = []) {
        // Em uma implementação real, usaríamos PHPMailer ou outra biblioteca
        // Para este MVP, vamos manter simples
        
        // Gerar boundary para o email multipart
        $boundary = md5(time());
        
        $headers = [
            'From: ' . $this->fromName . ' <' . $this->from . '>',
            'Reply-To: ' . $this->replyTo,
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            'X-Mailer: PHP/' . phpversion()
        ];
        
        if (isset($options['cc'])) {
            $headers[] = 'Cc: ' . $options['cc'];
        }
        
        if (isset($options['bcc'])) {
            $headers[] = 'Bcc: ' . $options['bcc'];
        }
        
        // Preparar o corpo da mensagem multipart
        $body = "--" . $boundary . "\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($plainMessage ?: strip_tags($htmlMessage))) . "\r\n";
        
        $body .= "--" . $boundary . "\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($htmlMessage)) . "\r\n";
        
        $body .= "--" . $boundary . "--";
        
        // Log do email
        $this->logEmail($to, $subject, $htmlMessage, true);
        
        // Em ambiente de desenvolvimento, apenas logar o email sem enviar
        if (getenv('APP_ENV') === 'development') {
            return true;
        }
        
        // Enviar o email
        return mail($to, $subject, $body, implode("\r\n", $headers));
    }
    
    /**
     * Envia um email com anexo
     */
    public function sendWithAttachment($to, $subject, $message, $attachmentPath, $attachmentName = '', $options = []) {
        // Implementação simplificada - em produção usar biblioteca especializada
        
        // Esta funcionalidade será implementada posteriormente
        return false;
    }
    
    /**
     * Registra o email enviado em log
     */
    private function logEmail($to, $subject, $message, $isHtml = false) {
        $logFile = APP_PATH . '/logs/email.log';
        
        // Garantir que o diretório de logs existe
        if (!file_exists(dirname($logFile))) {
            mkdir(dirname($logFile), 0755, true);
        }
        
        // Formato do log
        $log = date('Y-m-d H:i:s') . " | To: {$to} | Subject: {$subject} | Type: " . ($isHtml ? 'HTML' : 'Plain') . "\n";
        
        // Adicionar ao arquivo de log
        file_put_contents($logFile, $log, FILE_APPEND);
    }
}

// ESTE ARQUIVO AINDA PRECISA:
// - Implementar integração com serviço de email externo (SendGrid, Mailgun, etc.)
// - Adicionar suporte para templates de email
// - Implementar fila de emails para envio assíncrono
// - Adicionar rastreamento de abertura de emails
?>