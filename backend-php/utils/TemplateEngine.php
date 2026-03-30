<?php
/**
 * TemplateEngine - SAG Garage
 * 
 * Motor de plantillas para mensajes de WhatsApp
 * Maneja variables dinámicas y personalización de mensajes
 * 
 * @author Marco Candiani
 * @version 1.0
 * @date 30/03/2026
 */

class TemplateEngine {
    private $variables = [];
    private $funciones = [];
    
    public function __construct() {
        $this->registrarFuncionesPorDefecto();
    }

    /**
     * Renderizar template con variables
     */
    public function renderTemplate($template, $variables = []) {
        if (empty($template)) {
            return '';
        }

        $this->variables = array_merge($this->variables, $variables);
        
        // Procesar variables simples primero {{variable}}
        $rendered = $this->procesarVariablesSimples($template);
        
        // Procesar funciones/filtros {{variable|funcion}}
        $rendered = $this->procesarFunciones($rendered);
        
        // Limpiar variables no encontradas (opcional)
        $rendered = $this->limpiarVariablesNoEncontradas($rendered);
        
        return trim($rendered);
    }

    /**
     * Procesar variables simples {{variable}}
     */
    private function procesarVariablesSimples($template) {
        return preg_replace_callback(
            '/\{\{([^|}]+)\}\}/',
            function($matches) {
                $variable = trim($matches[1]);
                return $this->obtenerValorVariable($variable);
            },
            $template
        );
    }

    /**
     * Procesar funciones {{variable|funcion:parametro}}
     */
    private function procesarFunciones($template) {
        return preg_replace_callback(
            '/\{\{([^}|]+)\|([^}:]+)(?::([^}]+))?\}\}/',
            function($matches) {
                $variable = trim($matches[1]);
                $funcion = trim($matches[2]);
                $parametro = isset($matches[3]) ? trim($matches[3]) : null;
                
                $valor = $this->obtenerValorVariable($variable);
                
                if (isset($this->funciones[$funcion])) {
                    return $this->funciones[$funcion]($valor, $parametro);
                }
                
                return $valor;
            },
            $template
        );
    }

    /**
     * Obtener valor de variable con soporte para dot notation
     */
    private function obtenerValorVariable($variable) {
        // Soporte para variables anidadas como cliente.nombre
        if (strpos($variable, '.') !== false) {
            $partes = explode('.', $variable);
            $valor = $this->variables;
            
            foreach ($partes as $parte) {
                if (isset($valor[$parte])) {
                    $valor = $valor[$parte];
                } else {
                    return "{{$variable}}"; // Mantener original si no se encuentra
                }
            }
            
            return $valor;
        }

        // Variable simple
        if (isset($this->variables[$variable])) {
            return $this->variables[$variable];
        }

        return "{{$variable}}"; // Mantener original si no se encuentra
    }

    /**
     * Limpiar variables no encontradas (opcional)
     */
    private function limpiarVariablesNoEncontradas($template) {
        // Por defecto, mantener las variables no encontradas
        // Esto se puede configurar según necesidades
        return $template;
    }

    /**
     * Registrar funciones por defecto para templates
     */
    private function registrarFuncionesPorDefecto() {
        // Función para convertir a mayúsculas
        $this->funciones['upper'] = function($valor) {
            return mb_strtoupper($valor, 'UTF-8');
        };

        // Función para convertir a minúsculas
        $this->funciones['lower'] = function($valor) {
            return mb_strtolower($valor, 'UTF-8');
        };

        // Función para capitalizar primera letra
        $this->funciones['capitalize'] = function($valor) {
            return mb_convert_case($valor, MB_CASE_TITLE, 'UTF-8');
        };

        // Función para truncar texto
        $this->funciones['truncate'] = function($valor, $limite = 50) {
            $limite = (int)$limite;
            if (mb_strlen($valor, 'UTF-8') <= $limite) {
                return $valor;
            }
            return mb_substr($valor, 0, $limite - 3, 'UTF-8') . '...';
        };

        // Función para formatear fechas
        $this->funciones['date'] = function($valor, $formato = 'd/m/Y') {
            if (is_string($valor)) {
                $timestamp = strtotime($valor);
                if ($timestamp === false) {
                    return $valor;
                }
            } elseif (is_numeric($valor)) {
                $timestamp = $valor;
            } else {
                return $valor;
            }
            
            return date($formato, $timestamp);
        };

        // Función para pluralizar
        $this->funciones['plural'] = function($valor, $parametro = null) {
            $numero = (int)$valor;
            if ($parametro) {
                $formas = explode(',', $parametro);
                if ($numero === 1 && isset($formas[0])) {
                    return $formas[0];
                } elseif (isset($formas[1])) {
                    return $formas[1];
                }
            }
            
            return $numero === 1 ? '' : 's';
        };

        // Función para formatear números
        $this->funciones['number'] = function($valor, $decimales = 0) {
            return number_format((float)$valor, (int)$decimales, '.', ',');
        };

        // Función para reemplazar texto
        $this->funciones['replace'] = function($valor, $parametro = null) {
            if ($parametro) {
                $partes = explode(',', $parametro, 2);
                if (count($partes) === 2) {
                    return str_replace(trim($partes[0]), trim($partes[1]), $valor);
                }
            }
            return $valor;
        };

        // Función para primera palabra
        $this->funciones['first_word'] = function($valor) {
            $palabras = explode(' ', trim($valor));
            return $palabras[0] ?? '';
        };

        // Función para tiempo transcurrido en español
        $this->funciones['tiempo_es'] = function($valor) {
            $dias = (int)$valor;
            
            if ($dias < 30) {
                return $dias . ($dias === 1 ? ' día' : ' días');
            } elseif ($dias < 365) {
                $meses = floor($dias / 30);
                return $meses . ($meses === 1 ? ' mes' : ' meses');
            } else {
                $años = floor($dias / 365);
                return $años . ($años === 1 ? ' año' : ' años');
            }
        };

        // Función para formatear teléfono
        $this->funciones['phone'] = function($valor) {
            $limpio = preg_replace('/[^0-9]/', '', $valor);
            
            if (strlen($limpio) === 10) {
                return preg_replace('/(\d{3})(\d{3})(\d{4})/', '($1) $2-$3', $limpio);
            } elseif (strlen($limpio) === 12 && substr($limpio, 0, 2) === '52') {
                $nacional = substr($limpio, 2);
                return preg_replace('/(\d{3})(\d{3})(\d{4})/', '($1) $2-$3', $nacional);
            }
            
            return $valor;
        };

        // Función para saludo según hora
        $this->funciones['saludo'] = function($valor) {
            $hora = (int)date('H');
            
            if ($hora >= 6 && $hora < 12) {
                return 'Buenos días';
            } elseif ($hora >= 12 && $hora < 18) {
                return 'Buenas tardes';
            } else {
                return 'Buenas noches';
            }
        };

        // Función para emoji de vehículo según marca
        $this->funciones['emoji_vehiculo'] = function($valor) {
            $marca = strtolower($valor);
            
            $emojis = [
                'toyota' => '🚗',
                'honda' => '🚙',
                'nissan' => '🚐',
                'chevrolet' => '🚑',
                'ford' => '🚒',
                'volkswagen' => '🚓',
                'bmw' => '🏎️',
                'mercedes' => '🚘',
                'audi' => '🚔'
            ];
            
            foreach ($emojis as $marcaBuscar => $emoji) {
                if (strpos($marca, $marcaBuscar) !== false) {
                    return $emoji;
                }
            }
            
            return '🚗'; // Por defecto
        };
    }

    /**
     * Registrar función personalizada
     */
    public function registrarFuncion($nombre, $callback) {
        $this->funciones[$nombre] = $callback;
    }

    /**
     * Establecer variables globales
     */
    public function setVariables($variables) {
        $this->variables = array_merge($this->variables, $variables);
    }

    /**
     * Obtener variables actuales
     */
    public function getVariables() {
        return $this->variables;
    }

    /**
     * Validar template (verificar que las variables existen)
     */
    public function validarTemplate($template, $variablesRequeridas = []) {
        $variablesEncontradas = [];
        $variablesFaltantes = [];

        // Extraer variables del template
        preg_match_all('/\{\{([^|}]+)(?:\|[^}]*)?\}\}/', $template, $matches);
        
        foreach ($matches[1] as $variable) {
            $variable = trim($variable);
            $variablesEncontradas[] = $variable;
            
            if (in_array($variable, $variablesRequeridas) && !isset($this->variables[$variable])) {
                $variablesFaltantes[] = $variable;
            }
        }

        return [
            'valido' => empty($variablesFaltantes),
            'variables_encontradas' => array_unique($variablesEncontradas),
            'variables_faltantes' => $variablesFaltantes
        ];
    }

    /**
     * Vista previa del template con datos de ejemplo
     */
    public function vistaPrevia($template, $datosEjemplo = []) {
        $ejemploPorDefecto = [
            'cliente' => 'Juan Pérez',
            'vehiculo' => 'Toyota Corolla',
            'servicio' => 'Full Service',
            'tiempo' => '6 meses',
            'telefono_sag' => '(55) 1234-5678',
            'direccion_sag' => 'Av. Principal #123, Ciudad',
            'horarios_sag' => 'Lun-Vie 8:00-18:00',
            'nombre_sag' => 'SAG Garage'
        ];

        $datos = array_merge($ejemploPorDefecto, $datosEjemplo);
        
        return $this->renderTemplate($template, $datos);
    }

    /**
     * Obtener lista de variables disponibles en un template
     */
    public function extraerVariables($template) {
        $variables = [];
        
        // Variables simples {{variable}}
        preg_match_all('/\{\{([^|}]+)\}\}/', $template, $matches);
        foreach ($matches[1] as $variable) {
            $variables[] = trim($variable);
        }

        // Variables con funciones {{variable|funcion}}
        preg_match_all('/\{\{([^}|]+)\|[^}]+\}\}/', $template, $matches);
        foreach ($matches[1] as $variable) {
            $variables[] = trim($variable);
        }

        return array_unique($variables);
    }

    /**
     * Obtener lista de funciones disponibles
     */
    public function getFunciones() {
        return array_keys($this->funciones);
    }

    /**
     * Limpiar todas las variables
     */
    public function limpiarVariables() {
        $this->variables = [];
    }

    /**
     * Escapar caracteres especiales para WhatsApp
     */
    public function escaparWhatsApp($texto) {
        // WhatsApp soporta texto plano con algunos caracteres especiales
        // Principalmente necesitamos manejar emojis correctamente
        return $texto;
    }

    /**
     * Convertir texto con formato markdown básico
     */
    public function procesarMarkdown($texto) {
        // WhatsApp soporta *texto* para negrita
        $texto = preg_replace('/\*\*(.*?)\*\*/', '*$1*', $texto);
        
        // WhatsApp soporta _texto_ para cursiva (limitado)
        // Mejor usar asteriscos para énfasis
        $texto = preg_replace('/__(.*?)__/', '*$1*', $texto);
        
        return $texto;
    }

    /**
     * Validar longitud del mensaje para WhatsApp
     */
    public function validarLongitud($mensaje) {
        $longitud = mb_strlen($mensaje, 'UTF-8');
        $limite = 4096; // Límite de WhatsApp
        
        return [
            'valido' => $longitud <= $limite,
            'longitud' => $longitud,
            'limite' => $limite,
            'exceso' => max(0, $longitud - $limite)
        ];
    }

    /**
     * Generar mensaje de error amigable
     */
    public function mensajeError($tipo = 'general') {
        $mensajes = [
            'general' => 'Lo sentimos, hubo un problema generando tu mensaje personalizado.',
            'variables' => 'Algunos datos no están disponibles para personalizar tu mensaje.',
            'template' => 'El template de mensaje tiene errores de formato.',
            'longitud' => 'El mensaje es demasiado largo para WhatsApp.'
        ];

        return $mensajes[$tipo] ?? $mensajes['general'];
    }

    /**
     * Debug: mostrar información del procesamiento
     */
    public function debug($template, $variables = []) {
        return [
            'template_original' => $template,
            'variables_disponibles' => array_merge($this->variables, $variables),
            'variables_en_template' => $this->extraerVariables($template),
            'funciones_disponibles' => $this->getFunciones(),
            'template_procesado' => $this->renderTemplate($template, $variables),
            'validacion_longitud' => $this->validarLongitud($this->renderTemplate($template, $variables))
        ];
    }
}