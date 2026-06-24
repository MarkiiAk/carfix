<?php
/**
 * PhoneValidator - Validador y limpiador de números telefónicos
 * 
 * Esta clase se encarga de validar, limpiar y formatear números de teléfono
 * específicamente para el mercado mexicano y la integración con Twilio WhatsApp.
 * 
 * Características:
 * - Validación de números mexicanos (móviles y fijos)
 * - Limpieza automática de caracteres especiales
 * - Manejo de diferentes formatos de entrada
 * - Validación de longitud según tipo de número
 * - Logging de validaciones para debugging
 * 
 * @author Sistema SAG Garage - Refactorización 2026
 * @version 1.0.0
 */

class PhoneValidator 
{
    /**
     * Patrones de números válidos para México
     * @var array
     */
    private const PHONE_PATTERNS = [
        // Números móviles: 10 dígitos que empiecen con 1, 2, 3, 5, 6, 7, 8, 9
        'mobile' => '/^[1-9]\d{9}$/',
        // Números móviles con 1 inicial: 11 dígitos (1 + 10)
        'mobile_with_1' => '/^1[1-9]\d{9}$/',
        // Números fijos: 10 dígitos con códigos de área específicos
        'landline' => '/^[2-9]\d{9}$/'
    ];
    
    /**
     * Códigos de área válidos para números fijos mexicanos
     * @var array
     */
    private const VALID_AREA_CODES = [
        '55', '56', // Ciudad de México
        '33',       // Guadalajara
        '81',       // Monterrey
        '222',      // Puebla
        '844',      // Saltillo
        '662',      // Hermosillo
        // Agregar más según necesidades
    ];
    
    /**
     * Limpiar número de teléfono removiendo caracteres especiales
     * 
     * @param string $telefono Número de teléfono en cualquier formato
     * @return string|null Número limpio o null si es inválido
     */
    public function cleanPhoneNumber(string $telefono): ?string 
    {
        try {
            // Log del número original para debugging
            error_log("📱 PhoneValidator: Limpiando número: '{$telefono}'");
            
            // Remover espacios, guiones, paréntesis y otros caracteres
            $numeroLimpio = preg_replace('/[\s\-\(\)\+\.\_]+/', '', $telefono);
            
            // Remover prefijos comunes
            $numeroLimpio = $this->removePrefixes($numeroLimpio);
            
            // Validar longitud y formato
            if ($this->isValidPhoneNumber($numeroLimpio)) {
                error_log("✅ PhoneValidator: Número válido: '{$numeroLimpio}'");
                return $numeroLimpio;
            }
            
            error_log("❌ PhoneValidator: Número inválido después de limpieza: '{$numeroLimpio}'");
            return null;
            
        } catch (Exception $e) {
            error_log("❌ PhoneValidator: Error limpiando número: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Remover prefijos comunes de números mexicanos
     * 
     * @param string $numero Número a limpiar
     * @return string Número sin prefijos
     */
    private function removePrefixes(string $numero): string 
    {
        // Remover +52 (código país México)
        $numero = preg_replace('/^(\+?52)/', '', $numero);
        
        // Remover 01 (prefijo nacional anterior)
        $numero = preg_replace('/^01/', '', $numero);
        
        // Remover 001 (prefijo internacional anterior)
        $numero = preg_replace('/^001/', '', $numero);
        
        return $numero;
    }
    
    /**
     * Validar si un número es válido para México
     * 
     * @param string $numero Número limpio a validar
     * @return bool True si es válido
     */
    public function isValidPhoneNumber(string $numero): bool 
    {
        // Debe ser solo dígitos
        if (!ctype_digit($numero)) {
            return false;
        }
        
        // Longitud válida (10 u 11 dígitos)
        $longitud = strlen($numero);
        if ($longitud < 10 || $longitud > 11) {
            return false;
        }
        
        // Validar según patrones específicos
        foreach (self::PHONE_PATTERNS as $tipo => $patron) {
            if (preg_match($patron, $numero)) {
                error_log("📱 PhoneValidator: Número coincide con patrón '{$tipo}': {$numero}");
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Determinar el tipo de número telefónico
     * 
     * @param string $numero Número limpio a analizar
     * @return string Tipo: 'mobile', 'mobile_with_1', 'landline', 'unknown'
     */
    public function getPhoneNumberType(string $numero): string 
    {
        foreach (self::PHONE_PATTERNS as $tipo => $patron) {
            if (preg_match($patron, $numero)) {
                return $tipo;
            }
        }
        
        return 'unknown';
    }
    
    /**
     * Formatear número para mostrar al usuario
     * 
     * @param string $numero Número limpio
     * @return string Número formateado para visualización
     */
    public function formatForDisplay(string $numero): string 
    {
        $longitud = strlen($numero);
        
        if ($longitud === 10) {
            // Formato: (55) 1234-5678
            return '(' . substr($numero, 0, 2) . ') ' . 
                   substr($numero, 2, 4) . '-' . 
                   substr($numero, 6, 4);
        } elseif ($longitud === 11) {
            // Formato: 1 (55) 1234-5678
            return substr($numero, 0, 1) . ' (' . 
                   substr($numero, 1, 2) . ') ' . 
                   substr($numero, 3, 4) . '-' . 
                   substr($numero, 7, 4);
        }
        
        // Fallback: devolver como está
        return $numero;
    }
    
    /**
     * Formatear número para Twilio WhatsApp
     * 
     * @param string $numero Número limpio
     * @return string Número en formato whatsapp:+52XXXXXXXXXX
     */
    public function formatForWhatsApp(string $numero): string 
    {
        return "whatsapp:+52{$numero}";
    }
    
    /**
     * Validar múltiples números de una vez
     * 
     * @param array $numeros Array de números a validar
     * @return array Array con resultados de validación
     */
    public function validateMultipleNumbers(array $numeros): array 
    {
        $resultados = [];
        
        foreach ($numeros as $index => $numero) {
            $numeroLimpio = $this->cleanPhoneNumber($numero);
            
            $resultados[$index] = [
                'original' => $numero,
                'limpio' => $numeroLimpio,
                'valido' => $numeroLimpio !== null,
                'tipo' => $numeroLimpio ? $this->getPhoneNumberType($numeroLimpio) : null,
                'formateado' => $numeroLimpio ? $this->formatForDisplay($numeroLimpio) : null
            ];
        }
        
        return $resultados;
    }
    
    /**
     * Obtener información detallada sobre un número
     * 
     * @param string $telefono Número a analizar
     * @return array Información completa del número
     */
    public function getPhoneNumberInfo(string $telefono): array 
    {
        $numeroLimpio = $this->cleanPhoneNumber($telefono);
        
        $info = [
            'original' => $telefono,
            'limpio' => $numeroLimpio,
            'valido' => $numeroLimpio !== null,
            'longitud' => $numeroLimpio ? strlen($numeroLimpio) : 0,
            'tipo' => null,
            'formato_display' => null,
            'formato_whatsapp' => null,
            'es_movil' => false,
            'es_fijo' => false
        ];
        
        if ($numeroLimpio) {
            $tipo = $this->getPhoneNumberType($numeroLimpio);
            $info['tipo'] = $tipo;
            $info['formato_display'] = $this->formatForDisplay($numeroLimpio);
            $info['formato_whatsapp'] = $this->formatForWhatsApp($numeroLimpio);
            $info['es_movil'] = in_array($tipo, ['mobile', 'mobile_with_1']);
            $info['es_fijo'] = $tipo === 'landline';
        }
        
        return $info;
    }
    
    /**
     * Validar número específicamente para WhatsApp Business
     * 
     * @param string $telefono Número a validar
     * @return array Resultado de validación para WhatsApp
     */
    public function validateForWhatsApp(string $telefono): array 
    {
        $numeroLimpio = $this->cleanPhoneNumber($telefono);
        
        $resultado = [
            'valido' => false,
            'numero_limpio' => $numeroLimpio,
            'numero_whatsapp' => null,
            'recomendacion' => 'Número inválido',
            'advertencias' => []
        ];
        
        if (!$numeroLimpio) {
            $resultado['recomendacion'] = 'Proporcionar número en formato válido (ej: 5512345678)';
            return $resultado;
        }
        
        $tipo = $this->getPhoneNumberType($numeroLimpio);
        
        if ($tipo === 'landline') {
            $resultado['advertencias'][] = 'Número fijo - WhatsApp puede no estar disponible';
        }
        
        if (in_array($tipo, ['mobile', 'mobile_with_1', 'landline'])) {
            $resultado['valido'] = true;
            $resultado['numero_whatsapp'] = $this->formatForWhatsApp($numeroLimpio);
            $resultado['recomendacion'] = $tipo === 'mobile' ? 
                'Número válido para WhatsApp' : 
                'Válido, pero verificar disponibilidad de WhatsApp';
        }
        
        return $resultado;
    }
    
    /**
     * Obtener estadísticas de validación
     * 
     * @param array $numeros Lista de números para estadísticas
     * @return array Estadísticas de validación
     */
    public function getValidationStats(array $numeros): array 
    {
        $stats = [
            'total' => count($numeros),
            'validos' => 0,
            'invalidos' => 0,
            'moviles' => 0,
            'fijos' => 0,
            'tipos' => []
        ];
        
        foreach ($numeros as $numero) {
            $info = $this->getPhoneNumberInfo($numero);
            
            if ($info['valido']) {
                $stats['validos']++;
                
                if ($info['es_movil']) {
                    $stats['moviles']++;
                }
                
                if ($info['es_fijo']) {
                    $stats['fijos']++;
                }
                
                $tipo = $info['tipo'];
                $stats['tipos'][$tipo] = ($stats['tipos'][$tipo] ?? 0) + 1;
            } else {
                $stats['invalidos']++;
            }
        }
        
        $stats['porcentaje_validos'] = $stats['total'] > 0 ? 
            round(($stats['validos'] / $stats['total']) * 100, 2) : 0;
        
        return $stats;
    }
}