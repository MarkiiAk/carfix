<?php

namespace Twilio\Rest;

use Twilio\Http\CurlClient;
use Twilio\Exceptions\RestException;

/**
 * Cliente Twilio REST simplificado para cPanel
 */
class Client {
    private $accountSid;
    private $authToken;
    private $httpClient;
    public $messages;
    
    public function __construct($accountSid, $authToken) {
        $this->accountSid = $accountSid;
        $this->authToken = $authToken;
        $this->httpClient = new CurlClient();
        $this->messages = new MessagesContext($this);
    }
    
    public function request($method, $uri, $params = [], $data = []) {
        $url = "https://api.twilio.com" . $uri;
        
        return $this->httpClient->request(
            $method, 
            $url, 
            $params, 
            $data, 
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            $this->accountSid,
            $this->authToken
        );
    }
    
    public function getAccountSid() {
        return $this->accountSid;
    }
}

/**
 * Contexto de mensajes
 */
class MessagesContext {
    private $client;
    
    public function __construct($client) {
        $this->client = $client;
    }
    
    public function create($to, $options = []) {
        $data = [
            'To' => $to,
            'From' => $options['from'] ?? '',
            'Body' => $options['body'] ?? ''
        ];
        
        // Filtrar datos vacíos
        $data = array_filter($data);
        
        $response = $this->client->request(
            'POST',
            "/2010-04-01/Accounts/{$this->client->getAccountSid()}/Messages.json",
            [],
            $data
        );
        
        if (!$response->ok()) {
            $errorData = json_decode($response->getContent(), true);
            $message = isset($errorData['message']) ? $errorData['message'] : 'Unknown Twilio error';
            throw new RestException($message, $response->getStatusCode(), null, $response->getStatusCode(), $errorData);
        }
        
        $messageData = json_decode($response->getContent(), true);
        return new MessageInstance($messageData);
    }
}

/**
 * Instancia de mensaje
 */
class MessageInstance {
    public $sid;
    public $status;
    public $to;
    public $from;
    public $body;
    public $dateCreated;
    
    public function __construct($data) {
        $this->sid = $data['sid'] ?? '';
        $this->status = $data['status'] ?? '';
        $this->to = $data['to'] ?? '';
        $this->from = $data['from'] ?? '';
        $this->body = $data['body'] ?? '';
        
        if (isset($data['date_created'])) {
            try {
                $this->dateCreated = new \DateTime($data['date_created']);
            } catch (Exception $e) {
                $this->dateCreated = new \DateTime();
            }
        } else {
            $this->dateCreated = new \DateTime();
        }
    }
}

?>