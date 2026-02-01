-- ============================================================================
-- TENANT SCHEMA VERIFICATION SCRIPT
-- Checks all tenant databases for missing columns and tables
-- Run this to identify which stores need repair
-- ============================================================================

SELECT 
    'wep_store_16' AS store_name,
    (SELECT COUNT(*) FROM information_schema.COLUMNS 
     WHERE table_schema = 'wep_store_16' AND table_name = 'invoices' AND column_name = 'invoice_status') AS has_invoice_status,
    (SELECT COUNT(*) FROM information_schema.TABLES 
     WHERE table_schema = 'wep_store_16' AND table_name = 'payments') AS has_payments_table,
    (SELECT COUNT(*) FROM information_schema.TABLES 
     WHERE table_schema = 'wep_store_16' AND table_name = 'invoice_status_history') AS has_history_table,
    (SELECT COUNT(*) FROM information_schema.TABLES 
     WHERE table_schema = 'wep_store_16' AND table_name = 'store_logs') AS has_logs_table

UNION ALL

SELECT 
    'wep_store_20' AS store_name,
    (SELECT COUNT(*) FROM information_schema.COLUMNS 
     WHERE table_schema = 'wep_store_20' AND table_name = 'invoices' AND column_name = 'invoice_status') AS has_invoice_status,
    (SELECT COUNT(*) FROM information_schema.TABLES 
     WHERE table_schema = 'wep_store_20' AND table_name = 'payments') AS has_payments_table,
    (SELECT COUNT(*) FROM information_schema.TABLES 
     WHERE table_schema = 'wep_store_20' AND table_name = 'invoice_status_history') AS has_history_table,
    (SELECT COUNT(*) FROM information_schema.TABLES 
     WHERE table_schema = 'wep_store_20' AND table_name = 'store_logs') AS has_logs_table

UNION ALL

SELECT 
    'wep_store_29' AS store_name,
    (SELECT COUNT(*) FROM information_schema.COLUMNS 
     WHERE table_schema = 'wep_store_29' AND table_name = 'invoices' AND column_name = 'invoice_status') AS has_invoice_status,
    (SELECT COUNT(*) FROM information_schema.TABLES 
     WHERE table_schema = 'wep_store_29' AND table_name = 'payments') AS has_payments_table,
    (SELECT COUNT(*) FROM information_schema.TABLES 
     WHERE table_schema = 'wep_store_29' AND table_name = 'invoice_status_history') AS has_history_table,
    (SELECT COUNT(*) FROM information_schema.TABLES 
     WHERE table_schema = 'wep_store_29' AND table_name = 'store_logs') AS has_logs_table

UNION ALL

SELECT 
    'wep_store_35' AS store_name,
    (SELECT COUNT(*) FROM information_schema.COLUMNS 
     WHERE table_schema = 'wep_store_35' AND table_name = 'invoices' AND column_name = 'invoice_status') AS has_invoice_status,
    (SELECT COUNT(*) FROM information_schema.TABLES 
     WHERE table_schema = 'wep_store_35' AND table_name = 'payments') AS has_payments_table,
    (SELECT COUNT(*) FROM information_schema.TABLES 
     WHERE table_schema = 'wep_store_35' AND table_name = 'invoice_status_history') AS has_history_table,
    (SELECT COUNT(*) FROM information_schema.TABLES 
     WHERE table_schema = 'wep_store_35' AND table_name = 'store_logs') AS has_logs_table

UNION ALL

SELECT 
    'wep02_v2 (REFERENCE)' AS store_name,
    (SELECT COUNT(*) FROM information_schema.COLUMNS 
     WHERE table_schema = 'wep02_v2' AND table_name = 'invoices' AND column_name = 'invoice_status') AS has_invoice_status,
    (SELECT COUNT(*) FROM information_schema.TABLES 
     WHERE table_schema = 'wep02_v2' AND table_name = 'payments') AS has_payments_table,
    (SELECT COUNT(*) FROM information_schema.TABLES 
     WHERE table_schema = 'wep02_v2' AND table_name = 'invoice_status_history') AS has_history_table,
    (SELECT COUNT(*) FROM information_schema.TABLES 
     WHERE table_schema = 'wep02_v2' AND table_name = 'store_logs') AS has_logs_table;

-- ============================================================================
-- HOW TO INTERPRET RESULTS:
-- has_invoice_status = 1 means GOOD (column exists)
-- has_invoice_status = 0 means BROKEN (needs repair)
-- 
-- Same for tables: 1 = exists, 0 = missing
-- ============================================================================
