-- ============================================================================
-- RESET DE CORRIDA - Flexcon Tracker
-- ============================================================================
-- Limpia toda la data transaccional (PO, WO, Sent Lists, Lotes, Kits,
-- Pesajes, Empaque, Packing Slips, Facturas) y reinicia los contadores a 0.
--
-- SE CONSERVA:
--   Usuarios, roles/permisos, firmas, máquinas, mesas, semiautomáticos,
--   áreas, departamentos, turnos, break times, holidays, piezas, precios,
--   estándares, catálogos (statuses_wo, invoice_charge_types),
--   horas extra (over_times / over_time_user) y auditoría (audit_trails).
--
-- ⚠️  IMPORTANTE: HAZ UN RESPALDO ANTES DE CORRER ESTO. Es irreversible.
--     En phpMyAdmin: pestaña "Exportar" de la base de datos.
--     O por consola:  mysqldump -u root flexcon_tracker > backup.sql
--
-- USO: correr el archivo completo en phpMyAdmin (pestaña SQL) o:
--     mysql -u root flexcon_tracker < database/sql/reset_corrida.sql
--
-- ARCHIVOS EN DISCO: los PDFs de Invoice, Packing Slip y Shipping List NO se
--     guardan en disco (se generan al vuelo al descargar), asi que al truncar
--     sus tablas ya quedan en 0. Lo unico que se acumula en storage son los
--     PDFs de las Purchase Orders -> limpiar con:  database/sql/reset_storage.ps1
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ---- Facturación --------------------------------------------------------
TRUNCATE TABLE `invoice_items`;
TRUNCATE TABLE `invoices`;

-- ---- Envío / Packing Slips ----------------------------------------------
TRUNCATE TABLE `packing_slip_items`;
TRUNCATE TABLE `packing_slips`;

-- ---- Empaque / Pesajes --------------------------------------------------
TRUNCATE TABLE `packaging_crimp_weighings`;
TRUNCATE TABLE `packaging_piece_weighings`;
TRUNCATE TABLE `packaging_records`;
TRUNCATE TABLE `quality_weighings`;
TRUNCATE TABLE `weighings`;

-- ---- Kits ---------------------------------------------------------------
TRUNCATE TABLE `kit_approval_cycles`;
TRUNCATE TABLE `kit_incidents`;
TRUNCATE TABLE `kit_lot`;
TRUNCATE TABLE `kits`;

-- ---- Lotes --------------------------------------------------------------
TRUNCATE TABLE `crimp_lots`;
TRUNCATE TABLE `lot_completion_logs`;
TRUNCATE TABLE `lots`;

-- ---- Sent Lists ---------------------------------------------------------
TRUNCATE TABLE `sent_list_rejections`;
TRUNCATE TABLE `sent_list_purchase_orders`;
TRUNCATE TABLE `sent_list_shift`;
TRUNCATE TABLE `sent_lists`;

-- ---- Work Orders --------------------------------------------------------
TRUNCATE TABLE `wo_status_logs`;
TRUNCATE TABLE `work_orders`;

-- ---- Purchase Orders ----------------------------------------------------
TRUNCATE TABLE `document_signatures`;
TRUNCATE TABLE `purchase_orders`;

-- ---- Contador de producción ---------------------------------------------
TRUNCATE TABLE `productions`;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- Fin. Todos los contadores (po_number, wo, lotes, etc.) arrancan de nuevo.
-- ============================================================================
