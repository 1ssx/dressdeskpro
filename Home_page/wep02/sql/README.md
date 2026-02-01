# SQL Scripts - Multi-Tenant System

## 🎯 Purpose

This folder contains all SQL scripts for the multi-tenant system, including:
- Base schema for new stores
- Repair scripts for broken stores
- Verification and diagnostic tools

---

## 📁 Files Overview

### Core Schema
**`create_tables_v2.sql`** ✅
- Base schema for all new tenant stores
- Used by `signup.php` during store creation
- **Recently updated** to include all production features
- Contains: 10 tables, 3 views, complete indexes & foreign keys

### Repair & Maintenance
**`repair_tenant_schema.sql`** 🆕
- Fixes existing broken tenant stores
- Adds missing columns, tables, indexes
- Idempotent (safe to run multiple times)
- **Use this** if a store is showing SQL errors

**`repair_tenants.bat`** 🆕
- Interactive repair tool (Windows)
- Menu-driven interface
- Can repair one or all stores
- Includes automatic verification

**`verify_tenant_schemas.sql`** 🆕
- Diagnostic query
- Checks all tenant stores for missing schema
- Returns 1 = good, 0 = needs repair

### Migrations
**`migrate_existing_stores.sql`**
- Legacy migration for adding date fields
- **Note**: Now included in base schema

### Other Files
**`master_schema.sql`**
- Master database schema (wep_master)
- Stores metadata about all tenants

**`master_seed.sql`**
- Seed data for master database

**`master_enhancements_migration.sql`**
- Master database enhancements

**`performance_indexes.sql`**
- Additional performance indexes
- Optional optimization

---

## 🚀 Quick Actions

### Fix a Broken Store

**Option 1 - Easiest** (Windows):
```
Double-click: repair_tenants.bat
Follow the menu
```

**Option 2 - Command Line**:
```powershell
# Repair wep_store_35
d:\xampp\mysql\bin\mysql.exe -u root wep_store_35 < repair_tenant_schema.sql

# Verify
d:\xampp\mysql\bin\mysql.exe -u root < verify_tenant_schemas.sql
```

### Create a New Store
1. User signs up via the web interface
2. `signup.php` automatically uses `create_tables_v2.sql`
3. Store is created with complete schema
4. No manual intervention needed ✅

### Verify Store Health
```powershell
d:\xampp\mysql\bin\mysql.exe -u root < verify_tenant_schemas.sql
```

Look for stores with `0` values - those need repair.

---

## 🔧 What Each Script Does

### `create_tables_v2.sql`
Creates complete tenant database with:
- ✅ `users` - System users & authentication
- ✅ `customers` - Customer records
- ✅ `categories` - Product categories
- ✅ `suppliers` - Supplier information
- ✅ `products` - Inventory with `is_locked` field
- ✅ `invoices` - **With invoice_status and lifecycle fields**
- ✅ `invoice_items` - Line items
- ✅ `payments` - Multi-payment support
- ✅ `invoice_status_history` - Audit trail
- ✅ `store_logs` - Activity logging
- ✅ `bookings` - Booking management
- ✅ `expenses` - Expense tracking
- ✅ `inventory_movements` - Stock movements

Plus 3 views:
- `daily_bookings_view`
- `top_booking_types_view`
- `weekly_bookings_view`

### `repair_tenant_schema.sql`
For each missing component, this script:
1. Checks if it exists (via INFORMATION_SCHEMA)
2. If missing, adds it with proper defaults
3. If exists, skips (safe to re-run)

Adds:
- 8 columns to `invoices` table
- 3 new tables (`payments`, `invoice_status_history`, `store_logs`)
- 1 column to `products` table
- 11 indexes
- 4 foreign keys

### `verify_tenant_schemas.sql`
Checks 4 critical indicators:
1. `invoice_status` column exists
2. `payments` table exists
3. `invoice_status_history` table exists
4. `store_logs` table exists

Returns table with columns showing 1 (good) or 0 (bad).

---

## ⚠️ Important Notes

### Do NOT Run repair_tenant_schema.sql on:
- ❌ `wep_master` - Master database (different schema)
- ❌ `wep02_v2` - Reference store (already correct)
- ❌ `information_schema`, `mysql`, `performance_schema` - System databases

### Only Run It On:
- ✅ Tenant stores: `wep_store_XX`
- ✅ Only if showing SQL errors about missing columns

### Safety
- ✅ Idempotent - Can run multiple times safely
- ✅ Non-destructive - Only adds, never removes
- ✅ Preserves data - All existing records kept
- ✅ Automatic backups recommended but not required

---

## 📊 Workflow

### New Store Creation (Automatic)
```
User Signup → signup.php → create_tables_v2.sql → Complete Store ✅
```

### Fix Broken Store (Manual)
```
Identify Issue → verify_tenant_schemas.sql → repair_tenant_schema.sql → Fixed Store ✅
```

---

## 🎯 Success Indicators

After running repair, you should see:

**In SQL**:
```sql
USE wep_store_35;
SHOW COLUMNS FROM invoices LIKE 'invoice_status';
-- Should return 1 row

SHOW TABLES LIKE 'payments';
-- Should return 1 row
```

**In UI**:
- Dashboard loads without errors
- Invoices visible in list
- Statistics show actual numbers (not "Error")
- Can create new invoices

---

## 🆘 Troubleshooting

### Error: "Access denied"
- Check MySQL is running
- Verify root user credentials
- Try adding `-p` flag if password is set

### Error: "Database not found"
- Verify database name is correct
- Check `SHOW DATABASES;` for exact name
- Tenant DBs are named: `wep_store_XX`

### Script seems to do nothing
- Check MySQL error log: `d:\xampp\mysql\data\*.err`
- Run verification query to check actual state
- Try running individual ALTER statements manually

### Still seeing SQL errors after repair
- Verify repair actually ran (check columns exist)
- Clear browser cache
- Check exact error in browser console (F12)
- Consult `MULTI_TENANT_STABILIZATION_REPORT.md`

---

## 📚 Full Documentation

For complete documentation, see main project folder:
- `INDEX.md` - Documentation index
- `QUICK_REPAIR_GUIDE.md` - Step-by-step repair guide
- `MULTI_TENANT_STABILIZATION_REPORT.md` - Technical deep dive
- `ARCHITECTURE_DIAGRAM.md` - Visual architecture

---

## ✅ Status

All scripts tested and production-ready:
- ✅ Base schema complete
- ✅ Repair script working
- ✅ Verification script tested
- ✅ Automation script functional

**Last Updated**: 2025-12-12  
**Status**: Production Ready
