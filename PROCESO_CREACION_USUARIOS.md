# 📋 Proceso para Crear Usuarios en SAG Garage

## 🔐 Sistema de Autenticación
El sistema usa **BCRYPT** con las siguientes características:
- **Tabla:** `usuarios`
- **Campo password:** `password_hash` (VARCHAR 255)
- **Algoritmo:** bcrypt con cost=10
- **Funciones PHP:** `password_hash()` y `password_verify()`

## 📝 Proceso Paso a Paso

### 1. Instalar Dependencias
```bash
npm install bcryptjs
```

### 2. Crear Script de Generación de Hash
**Archivo:** `generate_hash.cjs`
```javascript
const bcrypt = require('bcryptjs');

const password = 'TU_PASSWORD_AQUI';
const saltRounds = 10;

const hash = bcrypt.hashSync(password, saltRounds);

console.log('=== GENERADOR DE HASH ===');
console.log(`Password: ${password}`);
console.log(`Hash: ${hash}`);

// Verificar
const isValid = bcrypt.compareSync(password, hash);
console.log(`Verificación: ${isValid ? 'EXITOSA' : 'FALLÓ'}`);

console.log('\n=== SQL PARA INSERTAR ===');
console.log(`INSERT INTO usuarios (username, password_hash, nombre_completo, email, rol, activo) VALUES`);
console.log(`('tu_usuario', '${hash}', 'Nombre Completo', 'email@ejemplo.com', 'admin', 1);`);
```

### 3. Ejecutar Script
```bash
node generate_hash.cjs
```

### 4. Usar el Hash en SQL
```sql
INSERT INTO usuarios (
    username, 
    password_hash, 
    nombre_completo, 
    email, 
    rol, 
    activo
) VALUES (
    'nuevo_usuario',
    'HASH_GENERADO_AQUI',
    'Nombre del Usuario',
    'email@ejemplo.com',
    'admin',  -- o 'tecnico', 'recepcionista'
    1
);
```

### 5. Verificar Login
- Usar las credenciales en la interfaz de login
- Si falla, verificar el hash con el script de verificación

## 🛡️ Seguridad

### Archivos Ignorados por Git
Los siguientes archivos NO se suben al repositorio por seguridad:
- `generate_hash.*`
- `*_hash.*`
- `insert_usuario_*`
- `fix_usuario_*`
- `*.cjs`

### Roles Disponibles
- **`admin`**: Acceso completo al sistema
- **`tecnico`**: Acceso a órdenes y servicios
- **`recepcionista`**: Acceso limitado a recepción

### 🚨 Permisos Especiales de Alertas
El sistema de alertas tiene permisos restrictivos. Solo usuarios específicos pueden acceder:

**Usuarios autorizados para alertas:**
- `markiiak` (usuario principal)
- `temporaldemo` (usuario demo)

**Para agregar nuevos usuarios con acceso a alertas:**
1. Editar archivo: `src/utils/alertsAuth.ts`
2. Agregar username al array `authorizedUsers`
3. El usuario DEBE tener rol `admin`

```javascript
const authorizedUsers = ['markiiak', 'temporaldemo', 'nuevo_usuario'];
```

## ⚠️ Importante
1. **NUNCA** commitear archivos con contraseñas o hashes
2. Usar el .gitignore para proteger archivos sensibles
3. El hash generado es único cada vez (por el salt)
4. Verificar siempre que el hash funcione antes de usar
5. Eliminar usuarios demo después de las pruebas

## 🔧 Troubleshooting

### Error "Credenciales inválidas"
1. Verificar que el hash fue generado correctamente
2. Confirmar que se usó bcryptjs con cost=10
3. Verificar que el usuario existe en la base de datos
4. Comprobar que el campo `activo` sea 1

### Error "require is not defined"
- Cambiar extensión de `.js` a `.cjs`
- O cambiar `require` por `import` si usas ES modules

## 📚 Ejemplo Completo
```bash
# 1. Crear archivo generate_hash.cjs con el código de arriba
# 2. Instalar dependencia
npm install bcryptjs

# 3. Ejecutar script
node generate_hash.cjs

# 4. Copiar el hash generado y usarlo en SQL
# 5. Ejecutar SQL en la base de datos
# 6. Probar login en la aplicación
```

---
*Proceso documentado: Marzo 2026*
*Sistema: SAG Garage - Gestión de Presupuestos*