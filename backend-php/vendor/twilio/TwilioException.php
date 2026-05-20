<?php

namespace Twilio\Exceptions;

/**
 * Excepción base de Twilio simplificada
 */
class TwilioException extends \Exception {
    
    public function __construct($message = "", $code = 0, \Exception $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}

class RestException extends TwilioException {
    protected $statusCode;
    protected $uri;
    protected $details;
    
    public function __construct($message, $code = 0, $uri = null, $statusCode = null, $details = null) {
        parent::__construct($message, $code);
        $this->uri = $uri;
        $this->statusCode = $statusCode;
        $this->details = $details;
    }
    
    public function getStatusCode() {
        return $this->statusCode;
    }
    
    public function getUri() {
        return $this->uri;
    }
    
    public function getDetails() {
        return $this->details;
    }
}

?>