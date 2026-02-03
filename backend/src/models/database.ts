import fs from 'fs/promises';
import path from 'path';
import { fileURLToPath } from 'url';
import bcrypt from 'bcryptjs';
import { User, Orden } from '../types';
import pool from '../config/mysql';
import type { RowDataPacket, ResultSetHeader } from 'mysql2';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const DATA_DIR = path.join(__dirname, '../../data');
const USERS_FILE = path.join(DATA_DIR, 'users.json');
const ORDENES_FILE = path.join(DATA_DIR, 'ordenes.json');

// Detectar si MySQL está disponible
let useMySQLCache: boolean | null = null;

async function shouldUseMySQL(): Promise<boolean> {
  if (useMySQLCache !== null) return useMySQLCache;
  
  try {
    const connection = await pool.getConnection();
    connection.release();
    useMySQLCache = true;
    console.log('✅ MySQL conectado - usando base de datos');
    return true;
  } catch (error) {
    useMySQLCache = false;
    console.log('⚠️  MySQL no disponible - usando archivos JSON');
    return false;
  }
}

// Clase para manejar la base de datos (MySQL o JSON como fallback)
class Database {
  private initialized = false;

  async initialize() {
    if (this.initialized) return;

    const useMySQL = await shouldUseMySQL();
    
    if (!useMySQL) {
      // Inicializar archivos JSON como fallback
      try {
        await fs.access(DATA_DIR);
      } catch {
        await fs.mkdir(DATA_DIR, { recursive: true });
      }

      // Inicializar archivo de usuarios si no existe
      try {
        await fs.access(USERS_FILE);
      } catch {
        const defaultPassword = await bcrypt.hash('admin123', 10);
        const defaultUsers: User[] = [
          {
            id: '1',
            username: 'admin',
            password: defaultPassword,
            nombre: 'Administrador SAG',
            rol: 'admin',
            createdAt: new Date().toISOString()
          }
        ];
        await fs.writeFile(USERS_FILE, JSON.stringify(defaultUsers, null, 2));
      }

      // Inicializar archivo de órdenes si no existe
      try {
        await fs.access(ORDENES_FILE);
      } catch {
        await fs.writeFile(ORDENES_FILE, JSON.stringify([], null, 2));
      }
    }

    this.initialized = true;
  }

  // ==================== USUARIOS ====================

  async getUsers(): Promise<User[]> {
    await this.initialize();
    const useMySQL = await shouldUseMySQL();

    if (useMySQL) {
      const [rows] = await pool.execute<RowDataPacket[]>(
        'SELECT id, username, password_hash as password, nombre_completo as nombre, rol, fecha_creacion as createdAt FROM usuarios WHERE activo = TRUE'
      );
      return rows.map((row: any) => ({
        id: String(row.id),
        username: row.username,
        password: row.password,
        nombre: row.nombre,
        rol: row.rol,
        createdAt: row.createdAt
      }));
    } else {
      const data = await fs.readFile(USERS_FILE, 'utf-8');
      return JSON.parse(data);
    }
  }

  async getUserByUsername(username: string): Promise<User | undefined> {
    const useMySQL = await shouldUseMySQL();

    if (useMySQL) {
      const [rows] = await pool.execute<RowDataPacket[]>(
        'SELECT id, username, password_hash as password, nombre_completo as nombre, rol, fecha_creacion as createdAt FROM usuarios WHERE username = ? AND activo = TRUE LIMIT 1',
        [username]
      );
      if (rows.length === 0) return undefined;
      const row = rows[0];
      return {
        id: String(row.id),
        username: row.username,
        password: row.password,
        nombre: row.nombre,
        rol: row.rol,
        createdAt: row.createdAt
      };
    } else {
      const users = await this.getUsers();
      return users.find(u => u.username === username);
    }
  }

  async getUserById(id: string): Promise<User | undefined> {
    const useMySQL = await shouldUseMySQL();

    if (useMySQL) {
      const [rows] = await pool.execute<RowDataPacket[]>(
        'SELECT id, username, password_hash as password, nombre_completo as nombre, rol, fecha_creacion as createdAt FROM usuarios WHERE id = ? AND activo = TRUE LIMIT 1',
        [id]
      );
      if (rows.length === 0) return undefined;
      const row = rows[0];
      return {
        id: String(row.id),
        username: row.username,
        password: row.password,
        nombre: row.nombre,
        rol: row.rol,
        createdAt: row.createdAt
      };
    } else {
      const users = await this.getUsers();
      return users.find(u => u.id === id);
    }
  }

  async createUser(user: User): Promise<User> {
    const useMySQL = await shouldUseMySQL();

    if (useMySQL) {
      const [result] = await pool.execute<ResultSetHeader>(
        'INSERT INTO usuarios (username, password_hash, nombre_completo, rol) VALUES (?, ?, ?, ?)',
        [user.username, user.password, user.nombre, user.rol]
      );
      return {
        ...user,
        id: String(result.insertId)
      };
    } else {
      const users = await this.getUsers();
      users.push(user);
      await fs.writeFile(USERS_FILE, JSON.stringify(users, null, 2));
      return user;
    }
  }

  // ==================== ÓRDENES ====================

  async getOrdenes(): Promise<Orden[]> {
    await this.initialize();
    const useMySQL = await shouldUseMySQL();

    if (useMySQL) {
      // Obtener órdenes básicas
      const [ordenes] = await pool.execute<RowDataPacket[]>(`
        SELECT 
          o.id, o.numero_orden as folio, o.estado,
          o.fecha_ingreso as fechaCreacion,
          o.ultima_modificacion as fechaActualizacion,
          o.fecha_cierre as fechaCierre,
          o.problema_reportado, o.diagnostico, o.observaciones_generales,
          o.nivel_combustible, o.tiene_radio, o.tiene_encendedor, o.tiene_gato,
          o.tiene_llanta_refaccion, o.tiene_herramienta, o.tiene_antena,
          o.tiene_tapetes, o.tiene_extinguidor, o.tiene_documentos,
          o.objetos_valor, o.condicion_llantas,
          o.subtotal, o.iva, o.total,
          c.id as cliente_id, c.nombre as cliente_nombre, c.telefono as cliente_telefono, c.email as cliente_email,
          v.marca, v.modelo, v.anio, v.color, v.placas, v.numero_serie as vin, v.kilometraje,
          u.nombre_completo as creadoPor
        FROM ordenes_servicio o
        INNER JOIN clientes c ON o.cliente_id = c.id
        INNER JOIN vehiculos v ON o.vehiculo_id = v.id
        INNER JOIN usuarios u ON o.usuario_id = u.id
        ORDER BY o.id DESC
      `);

      // Convertir a formato Orden
      const result: Orden[] = [];
      for (const orden of ordenes) {
        // Obtener servicios
        const [servicios] = await pool.execute<RowDataPacket[]>(
          'SELECT id, descripcion, precio_unitario as precio FROM servicios_orden WHERE orden_id = ?',
          [orden.id]
        );

        // Obtener mano de obra (usando servicios_orden también)
        const [manoObra] = await pool.execute<RowDataPacket[]>(
          'SELECT id, descripcion, cantidad as horas, precio_unitario as precioPorHora, subtotal as total FROM servicios_orden WHERE orden_id = ? AND descripcion LIKE "%mano%"',
          [orden.id]
        );

        // Obtener refacciones
        const [refacciones] = await pool.execute<RowDataPacket[]>(
          'SELECT id, descripcion as nombre, cantidad, precio_unitario as precioCosto, subtotal as total FROM refacciones_orden WHERE orden_id = ?',
          [orden.id]
        );

        result.push({
          id: String(orden.id),
          folio: orden.folio,
          estado: orden.estado,
          fechaCreacion: orden.fechaCreacion,
          fechaActualizacion: orden.fechaActualizacion,
          fechaCierre: orden.fechaCierre,
          cliente: {
            nombre: orden.cliente_nombre,
            telefono: orden.cliente_telefono,
            email: orden.cliente_email
          },
          vehiculo: {
            marca: orden.marca,
            modelo: orden.modelo,
            año: String(orden.anio),
            color: orden.color,
            placas: orden.placas,
            vin: orden.vin,
            kilometraje: String(orden.kilometraje),
            nivelCombustible: orden.nivel_combustible
          },
          servicios: servicios.map((s: any) => ({
            id: String(s.id),
            descripcion: s.descripcion,
            precio: s.precio
          })),
          manoObra: manoObra.map((m: any) => ({
            id: String(m.id),
            descripcion: m.descripcion,
            horas: m.horas,
            precioPorHora: m.precioPorHora,
            total: m.total
          })),
          refacciones: refacciones.map((r: any) => ({
            id: String(r.id),
            nombre: r.nombre,
            cantidad: r.cantidad,
            precioCosto: r.precioCosto,
            precioVenta: r.precioCosto * 1.3, // 30% ganancia
            margenGanancia: 30,
            total: r.total
          })),
          totales: {
            subtotal: orden.subtotal,
            incluirIVA: orden.iva > 0,
            iva: orden.iva,
            total: orden.total
          },
          inspeccion: {
            antena: Boolean(orden.tiene_antena),
            espejosLaterales: true,
            espejoCentral: true,
            tapones: true,
            taponGasolina: true,
            radioEstereo: Boolean(orden.tiene_radio),
            encendedor: Boolean(orden.tiene_encendedor),
            gato: Boolean(orden.tiene_gato),
            llaveRuedas: Boolean(orden.tiene_herramienta),
            extintor: Boolean(orden.tiene_extinguidor),
            llantas: orden.condicion_llantas || 'buenas',
            tapetes: Boolean(orden.tiene_tapetes),
            herramientas: Boolean(orden.tiene_herramienta),
            llantaRefaccion: Boolean(orden.tiene_llanta_refaccion),
            limpiadores: true
          },
          diagnostico: {
            problema: orden.problema_reportado,
            diagnostico: orden.diagnostico || '',
            observaciones: orden.observaciones_generales || ''
          },
          creadoPor: orden.creadoPor
        });
      }

      return result;
    } else {
      const data = await fs.readFile(ORDENES_FILE, 'utf-8');
      const ordenes = JSON.parse(data);
      // Ordenar por ID descendente (más nuevas primero)
      return ordenes.sort((a: Orden, b: Orden) => {
        const idA = parseInt(a.id) || 0;
        const idB = parseInt(b.id) || 0;
        return idB - idA;
      });
    }
  }

  async getOrdenById(id: string): Promise<Orden | undefined> {
    const useMySQL = await shouldUseMySQL();

    if (useMySQL) {
      const ordenes = await this.getOrdenes();
      return ordenes.find(o => o.id === id);
    } else {
      const ordenes = await this.getOrdenes();
      return ordenes.find(o => o.id === id);
    }
  }

  async getOrdenByFolio(folio: string): Promise<Orden | undefined> {
    const useMySQL = await shouldUseMySQL();

    if (useMySQL) {
      const ordenes = await this.getOrdenes();
      return ordenes.find(o => o.folio === folio);
    } else {
      const ordenes = await this.getOrdenes();
      return ordenes.find(o => o.folio === folio);
    }
  }

  async createOrden(orden: Orden): Promise<Orden> {
    const useMySQL = await shouldUseMySQL();

    if (useMySQL) {
      const connection = await pool.getConnection();
      
      try {
        await connection.beginTransaction();

        // 1. Crear o obtener cliente
        let clienteId: number;
        const [clienteRows] = await connection.execute<RowDataPacket[]>(
          'SELECT id FROM clientes WHERE telefono = ? LIMIT 1',
          [orden.cliente.telefono]
        );

        if (clienteRows.length > 0) {
          clienteId = clienteRows[0].id;
          // Actualizar info del cliente
          await connection.execute(
            'UPDATE clientes SET nombre = ?, email = ? WHERE id = ?',
            [orden.cliente.nombre, orden.cliente.email, clienteId]
          );
        } else {
          const [result] = await connection.execute<ResultSetHeader>(
            'INSERT INTO clientes (nombre, telefono, email) VALUES (?, ?, ?)',
            [orden.cliente.nombre, orden.cliente.telefono, orden.cliente.email]
          );
          clienteId = result.insertId;
        }

        // 2. Crear o obtener vehículo
        let vehiculoId: number;
        const [vehiculoRows] = await connection.execute<RowDataPacket[]>(
          'SELECT id FROM vehiculos WHERE cliente_id = ? AND placas = ? LIMIT 1',
          [clienteId, orden.vehiculo.placas]
        );

        if (vehiculoRows.length > 0) {
          vehiculoId = vehiculoRows[0].id;
          // Actualizar info del vehículo
          await connection.execute(
            'UPDATE vehiculos SET marca = ?, modelo = ?, anio = ?, color = ?, numero_serie = ?, kilometraje = ? WHERE id = ?',
            [orden.vehiculo.marca, orden.vehiculo.modelo, orden.vehiculo.año, orden.vehiculo.color, orden.vehiculo.vin, parseInt(orden.vehiculo.kilometraje) || 0, vehiculoId]
          );
        } else {
          const [result] = await connection.execute<ResultSetHeader>(
            'INSERT INTO vehiculos (cliente_id, marca, modelo, anio, color, placas, numero_serie, kilometraje) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [clienteId, orden.vehiculo.marca, orden.vehiculo.modelo, orden.vehiculo.año, orden.vehiculo.color, orden.vehiculo.placas, orden.vehiculo.vin, parseInt(orden.vehiculo.kilometraje) || 0]
          );
          vehiculoId = result.insertId;
        }

        // 3. Crear orden de servicio
        const [ordenResult] = await connection.execute<ResultSetHeader>(
          `INSERT INTO ordenes_servicio (
            numero_orden, cliente_id, vehiculo_id, usuario_id,
            problema_reportado, diagnostico, observaciones_generales,
            nivel_combustible, tiene_radio, tiene_encendedor, tiene_gato,
            tiene_llanta_refaccion, tiene_herramienta, tiene_antena,
            tiene_tapetes, tiene_extinguidor, tiene_documentos,
            condicion_llantas, estado, subtotal, iva, total
          ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
          [
            orden.folio, clienteId, vehiculoId, 1, // usuario_id = 1 (admin)
            orden.diagnostico.problema, orden.diagnostico.diagnostico, orden.diagnostico.observaciones,
            orden.vehiculo.nivelCombustible,
            orden.inspeccion.radioEstereo, orden.inspeccion.encendedor, orden.inspeccion.gato,
            orden.inspeccion.llantaRefaccion, orden.inspeccion.herramientas, orden.inspeccion.antena,
            orden.inspeccion.tapetes, orden.inspeccion.extintor, false,
            orden.inspeccion.llantas, orden.estado,
            orden.totales.subtotal, orden.totales.iva, orden.totales.total
          ]
        );

        const ordenId = ordenResult.insertId;

        // 4. Insertar servicios
        for (const servicio of orden.servicios) {
          await connection.execute(
            'INSERT INTO servicios_orden (orden_id, descripcion, precio_unitario, cantidad, subtotal) VALUES (?, ?, ?, ?, ?)',
            [ordenId, servicio.descripcion, servicio.precio, 1, servicio.precio]
          );
        }

        // 5. Insertar mano de obra
        for (const mo of orden.manoObra) {
          await connection.execute(
            'INSERT INTO servicios_orden (orden_id, descripcion, precio_unitario, cantidad, subtotal) VALUES (?, ?, ?, ?, ?)',
            [ordenId, mo.descripcion, mo.precioPorHora, mo.horas, mo.total]
          );
        }

        // 6. Insertar refacciones
        for (const ref of orden.refacciones) {
          await connection.execute(
            'INSERT INTO refacciones_orden (orden_id, descripcion, cantidad, precio_unitario, subtotal) VALUES (?, ?, ?, ?, ?)',
            [ordenId, ref.nombre, ref.cantidad, ref.precioCosto, ref.total]
          );
        }

        await connection.commit();
        
        return {
          ...orden,
          id: String(ordenId)
        };
      } catch (error) {
        await connection.rollback();
        throw error;
      } finally {
        connection.release();
      }
    } else {
      const ordenes = await this.getOrdenes();
      ordenes.push(orden);
      await fs.writeFile(ORDENES_FILE, JSON.stringify(ordenes, null, 2));
      return orden;
    }
  }

  async updateOrden(id: string, ordenData: Partial<Orden>): Promise<Orden | null> {
    const useMySQL = await shouldUseMySQL();

    if (useMySQL) {
      const connection = await pool.getConnection();
      
      try {
        await connection.beginTransaction();

        // Actualizar campos básicos de la orden
        if (ordenData.estado) {
          await connection.execute(
            'UPDATE ordenes_servicio SET estado = ?, ultima_modificacion = CURRENT_TIMESTAMP WHERE id = ?',
            [ordenData.estado, id]
          );
        }

        if (ordenData.diagnostico) {
          await connection.execute(
            'UPDATE ordenes_servicio SET diagnostico = ?, observaciones_generales = ? WHERE id = ?',
            [ordenData.diagnostico.diagnostico, ordenData.diagnostico.observaciones, id]
          );
        }

        if (ordenData.totales) {
          await connection.execute(
            'UPDATE ordenes_servicio SET subtotal = ?, iva = ?, total = ? WHERE id = ?',
            [ordenData.totales.subtotal, ordenData.totales.iva, ordenData.totales.total, id]
          );
        }

        await connection.commit();
        
        // Obtener orden actualizada
        const orden = await this.getOrdenById(id);
        return orden || null;
      } catch (error) {
        await connection.rollback();
        throw error;
      } finally {
        connection.release();
      }
    } else {
      const ordenes = await this.getOrdenes();
      const index = ordenes.findIndex(o => o.id === id);
      
      if (index === -1) return null;

      ordenes[index] = {
        ...ordenes[index],
        ...ordenData,
        fechaActualizacion: new Date().toISOString()
      };

      await fs.writeFile(ORDENES_FILE, JSON.stringify(ordenes, null, 2));
      return ordenes[index];
    }
  }

  async deleteOrden(id: string): Promise<boolean> {
    const useMySQL = await shouldUseMySQL();

    if (useMySQL) {
      const [result] = await pool.execute<ResultSetHeader>(
        'DELETE FROM ordenes_servicio WHERE id = ?',
        [id]
      );
      return result.affectedRows > 0;
    } else {
      const ordenes = await this.getOrdenes();
      const filtered = ordenes.filter(o => o.id !== id);
      
      if (filtered.length === ordenes.length) return false;

      await fs.writeFile(ORDENES_FILE, JSON.stringify(filtered, null, 2));
      return true;
    }
  }

  async searchOrdenes(filters: {
    folio?: string;
    cliente?: string;
    placa?: string;
    estado?: string;
    fechaDesde?: string;
    fechaHasta?: string;
  }): Promise<Orden[]> {
    let ordenes = await this.getOrdenes();

    if (filters.folio) {
      ordenes = ordenes.filter(o => 
        o.folio.toLowerCase().includes(filters.folio!.toLowerCase())
      );
    }

    if (filters.cliente) {
      ordenes = ordenes.filter(o =>
        o.cliente.nombre.toLowerCase().includes(filters.cliente!.toLowerCase())
      );
    }

    if (filters.placa) {
      ordenes = ordenes.filter(o =>
        o.vehiculo.placas.toLowerCase().includes(filters.placa!.toLowerCase())
      );
    }

    if (filters.estado) {
      ordenes = ordenes.filter(o => o.estado === filters.estado);
    }

    if (filters.fechaDesde) {
      ordenes = ordenes.filter(o => o.fechaCreacion >= filters.fechaDesde!);
    }

    if (filters.fechaHasta) {
      ordenes = ordenes.filter(o => o.fechaCreacion <= filters.fechaHasta!);
    }

    return ordenes;
  }

  async getStats(): Promise<{
    ordenesAbiertas: number;
    ordenesDelDia: number;
    ordenesDeLaSemana: number;
    ordenesDelMes: number;
    ingresosDelMes: number;
  }> {
    const ordenes = await this.getOrdenes();
    const now = new Date();
    const startOfDay = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const startOfWeek = new Date(now);
    startOfWeek.setDate(now.getDate() - now.getDay());
    const startOfMonth = new Date(now.getFullYear(), now.getMonth(), 1);

    return {
      ordenesAbiertas: ordenes.filter(o => o.estado === 'abierta').length,
      ordenesDelDia: ordenes.filter(o => new Date(o.fechaCreacion) >= startOfDay).length,
      ordenesDeLaSemana: ordenes.filter(o => new Date(o.fechaCreacion) >= startOfWeek).length,
      ordenesDelMes: ordenes.filter(o => new Date(o.fechaCreacion) >= startOfMonth).length,
      ingresosDelMes: ordenes
        .filter(o => new Date(o.fechaCreacion) >= startOfMonth && o.estado === 'cerrada')
        .reduce((sum, o) => sum + o.totales.total, 0)
    };
  }

  async getNextFolio(): Promise<string> {
    const ordenes = await this.getOrdenes();
    const year = new Date().getFullYear();
    const ordenesDelAño = ordenes.filter(o => o.folio.startsWith(`SAG-${year}`));
    const nextNumber = ordenesDelAño.length + 1;
    return `SAG-${year}-${String(nextNumber).padStart(4, '0')}`;
  }
}

export const db = new Database();
