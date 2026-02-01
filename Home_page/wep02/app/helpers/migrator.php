<?php
/**
 * Database Migration System
 * Handles automated database migrations with version tracking
 * 
 * Usage:
 *   $migrator = new Migrator($pdo);
 *   $migrator->migrate();
 */

class Migrator {
    private $pdo;
    private $migrationsTable = 'migrations';
    private $migrationsPath;
    
    /**
     * Constructor
     * 
     * @param PDO $pdo Database connection
     * @param string $migrationsPath Path to migrations directory
     */
    public function __construct($pdo, $migrationsPath = null) {
        $this->pdo = $pdo;
        $this->migrationsPath = $migrationsPath ?: __DIR__ . '/../../migrations';
        $this->ensureMigrationsTable();
    }
    
    /**
     * Create migrations table if it doesn't exist
     */
    private function ensureMigrationsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->migrationsTable} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL UNIQUE,
            batch INT NOT NULL,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_migration (migration),
            INDEX idx_batch (batch)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->pdo->exec($sql);
    }
    
    /**
     * Get all executed migrations
     * 
     * @return array List of executed migration names
     */
    private function getExecutedMigrations() {
        $stmt = $this->pdo->query("SELECT migration FROM {$this->migrationsTable} ORDER BY id");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    /**
     * Get next batch number
     * 
     * @return int Next batch number
     */
    private function getNextBatchNumber() {
        $stmt = $this->pdo->query("SELECT MAX(batch) as max_batch FROM {$this->migrationsTable}");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($result['max_batch'] ?? 0) + 1;
    }
    
    /**
     * Get all migration files from directory
     * 
     * @return array List of migration file paths
     */
    private function getMigrationFiles() {
        if (!is_dir($this->migrationsPath)) {
            mkdir($this->migrationsPath, 0755, true);
            return [];
        }
        
        $files = glob($this->migrationsPath . '/*.php');
        sort($files); // Ensure chronological order
        return $files;
    }
    
    /**
     * Get migration name from file path
     * 
     * @param string $filePath Full path to migration file
     * @return string Migration name
     */
    private function getMigrationName($filePath) {
        return basename($filePath);
    }
    
    /**
     * Run all pending migrations
     * 
     * @param bool $verbose Output progress messages
     * @return array Results of migration execution
     */
    public function migrate($verbose = false) {
        $executedMigrations = $this->getExecutedMigrations();
        $migrationFiles = $this->getMigrationFiles();
        $batch = $this->getNextBatchNumber();
        $results = [
            'executed' => [],
            'skipped' => [],
            'failed' => []
        ];
        
        foreach ($migrationFiles as $file) {
            $migrationName = $this->getMigrationName($file);
            
            // Skip already executed migrations
            if (in_array($migrationName, $executedMigrations)) {
                $results['skipped'][] = $migrationName;
                if ($verbose) {
                    echo "⊘ Skipped: {$migrationName} (already executed)\n";
                }
                continue;
            }
            
            // Execute migration
            try {
                if ($verbose) {
                    echo "→ Running: {$migrationName}...";
                }
                
                $this->executeMigrationFile($file);
                
                // Record migration
                $stmt = $this->pdo->prepare(
                    "INSERT INTO {$this->migrationsTable} (migration, batch) VALUES (?, ?)"
                );
                $stmt->execute([$migrationName, $batch]);
                
                $results['executed'][] = $migrationName;
                
                if ($verbose) {
                    echo " ✓ Success\n";
                }
            } catch (Exception $e) {
                $results['failed'][] = [
                    'migration' => $migrationName,
                    'error' => $e->getMessage()
                ];
                
                if ($verbose) {
                    echo " ✗ Failed\n";
                    echo "  Error: " . $e->getMessage() . "\n";
                }
                
                // Stop on first failure to maintain consistency
                break;
            }
        }
        
        return $results;
    }
    
    /**
     * Execute a single migration file
     * 
     * @param string $file Path to migration file
     */
    private function executeMigrationFile($file) {
        // Migration files should return a closure that receives $pdo
        $migration = require $file;
        
        if (!is_callable($migration)) {
            throw new Exception("Migration file must return a callable function");
        }
        
        // Begin transaction for safety
        $this->pdo->beginTransaction();
        
        try {
            // Execute the migration
            $migration($this->pdo);
            
            // Commit transaction
            $this->pdo->commit();
        } catch (Exception $e) {
            // Rollback on error
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    /**
     * Rollback last batch of migrations
     * 
     * @param bool $verbose Output progress messages
     * @return array Results of rollback
     */
    public function rollback($verbose = false) {
        // Get last batch number
        $stmt = $this->pdo->query("SELECT MAX(batch) as max_batch FROM {$this->migrationsTable}");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $lastBatch = $result['max_batch'] ?? 0;
        
        if ($lastBatch === 0) {
            if ($verbose) {
                echo "No migrations to rollback.\n";
            }
            return ['rolled_back' => []];
        }
        
        // Get migrations in last batch
        $stmt = $this->pdo->prepare(
            "SELECT migration FROM {$this->migrationsTable} WHERE batch = ? ORDER BY id DESC"
        );
        $stmt->execute([$lastBatch]);
        $migrations = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $results = ['rolled_back' => []];
        
        foreach ($migrations as $migrationName) {
            try {
                if ($verbose) {
                    echo "← Rolling back: {$migrationName}...";
                }
                
                // Delete from migrations table
                $stmt = $this->pdo->prepare(
                    "DELETE FROM {$this->migrationsTable} WHERE migration = ?"
                );
                $stmt->execute([$migrationName]);
                
                $results['rolled_back'][] = $migrationName;
                
                if ($verbose) {
                    echo " ✓ Success\n";
                }
            } catch (Exception $e) {
                if ($verbose) {
                    echo " ✗ Failed: " . $e->getMessage() . "\n";
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Get migration status
     * 
     * @return array Status information
     */
    public function status() {
        $executedMigrations = $this->getExecutedMigrations();
        $allMigrations = array_map(
            [$this, 'getMigrationName'],
            $this->getMigrationFiles()
        );
        
        $pending = array_diff($allMigrations, $executedMigrations);
        
        return [
            'executed' => $executedMigrations,
            'pending' => array_values($pending),
            'total' => count($allMigrations)
        ];
    }
    
    /**
     * Create a new migration file
     * 
     * @param string $name Migration name (e.g., "add_phone_to_customers")
     * @return string Path to created migration file
     */
    public function create($name) {
        // Generate timestamp-based filename
        $timestamp = date('Y_m_d_His');
        $filename = "{$timestamp}_{$name}.php";
        $filepath = $this->migrationsPath . '/' . $filename;
        
        // Ensure migrations directory exists
        if (!is_dir($this->migrationsPath)) {
            mkdir($this->migrationsPath, 0755, true);
        }
        
        // Create migration file with template
        $template = $this->getMigrationTemplate($name);
        file_put_contents($filepath, $template);
        
        return $filepath;
    }
    
    /**
     * Get migration file template
     * 
     * @param string $name Migration name
     * @return string Migration file content
     */
    private function getMigrationTemplate($name) {
        $className = $this->classNameFromMigrationName($name);
        
        return <<<PHP
<?php
/**
 * Migration: {$name}
 * Created: {$this->getCurrentDateTime()}
 */

return function(\$pdo) {
    // UP: Run the migration
    \$sql = "
        -- Add your SQL here
        -- Example:
        -- ALTER TABLE products ADD COLUMN new_field VARCHAR(255) DEFAULT NULL;
    ";
    
    \$pdo->exec(\$sql);
    
    // You can also run multiple statements:
    // \$pdo->exec("CREATE TABLE ...");
    // \$pdo->exec("ALTER TABLE ...");
};

PHP;
    }
    
    /**
     * Convert migration name to class name
     */
    private function classNameFromMigrationName($name) {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
    }
    
    /**
     * Get current datetime for documentation
     */
    private function getCurrentDateTime() {
        return date('Y-m-d H:i:s');
    }
}
