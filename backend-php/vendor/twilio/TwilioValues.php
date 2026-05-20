<?php

namespace Twilio;

/**
 * Clase de valores y utilidades de Twilio
 */
class Values {
    const UNSET = 'UNSET';
    
    public static function of($value) {
        return $value;
    }
    
    public static function map($array, $callback) {
        return array_map($callback, $array);
    }
}

?>