<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        match ($driver) {
            'mysql' => DB::statement(
                "ALTER TABLE tuition_invoices MODIFY COLUMN generation_source ENUM('manual', 'scheduled', 'annual_prepayment') NOT NULL DEFAULT 'manual'"
            ),
            'pgsql' => DB::statement(
                'ALTER TABLE tuition_invoices DROP CONSTRAINT IF EXISTS tuition_invoices_generation_source_check'
            ),
            // SQLite: recreate table to update CHECK constraint (SQLite doesn't support ALTER CONSTRAINT).
            'sqlite' => $this->recreateTableForSqlite(['manual', 'scheduled', 'annual_prepayment']),
            default => null,
        };

        // PostgreSQL: add new CHECK constraint with expanded values (if column was CHECK-constrained).
        if ($driver === 'pgsql') {
            DB::statement(
                'ALTER TABLE tuition_invoices ADD CONSTRAINT tuition_invoices_generation_source_check '
                ."CHECK (generation_source IN ('manual', 'scheduled', 'annual_prepayment'))"
            );
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        match ($driver) {
            'mysql' => DB::statement(
                "ALTER TABLE tuition_invoices MODIFY COLUMN generation_source ENUM('manual', 'scheduled') NOT NULL DEFAULT 'manual'"
            ),
            'pgsql' => DB::statement(
                'ALTER TABLE tuition_invoices DROP CONSTRAINT IF EXISTS tuition_invoices_generation_source_check'
            ),
            'sqlite' => $this->recreateTableForSqlite(['manual', 'scheduled']),
            default => null,
        };

        // PostgreSQL: re-add CHECK constraint without 'annual_prepayment'.
        if ($driver === 'pgsql') {
            DB::statement(
                'ALTER TABLE tuition_invoices ADD CONSTRAINT tuition_invoices_generation_source_check '
                ."CHECK (generation_source IN ('manual', 'scheduled'))"
            );
        }
    }

    /**
     * SQLite: recreate the table to update a CHECK constraint (SQLite lacks ALTER CONSTRAINT).
     */
    private function recreateTableForSqlite(array $allowedValues): void
    {
        $check = implode(', ', array_map(fn ($v) => "'{$v}'", $allowedValues));

        DB::transaction(function () use ($check) {
            DB::unprepared('PRAGMA foreign_keys = OFF');

            DB::unprepared("
                CREATE TABLE tuition_invoices_new (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    student_id INTEGER NOT NULL REFERENCES students(id) ON DELETE CASCADE,
                    period VARCHAR(7) NOT NULL,
                    fee_type VARCHAR(20) NOT NULL CHECK (fee_type IN ('enrollment', 'spp', 'other')),
                    description TEXT,
                    amount INTEGER NOT NULL,
                    due_date DATE NOT NULL,
                    status VARCHAR(20) NOT NULL DEFAULT 'draft' CHECK (status IN ('draft', 'pending_payment', 'paid', 'expired', 'cancelled')),
                    paid_at TIMESTAMP NULL,
                    generation_source VARCHAR(20) NOT NULL CHECK (generation_source IN ({$check})),
                    created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
                    created_at TIMESTAMP NULL,
                    updated_at TIMESTAMP NULL,
                    UNIQUE(student_id, period, fee_type)
                )
            ");

            DB::unprepared('
                INSERT INTO tuition_invoices_new (id, student_id, period, fee_type, description, amount, due_date, status, paid_at, generation_source, created_by, created_at, updated_at)
                SELECT id, student_id, period, fee_type, description, amount, due_date, status, paid_at, generation_source, created_by, created_at, updated_at
                FROM tuition_invoices
            ');

            DB::unprepared('DROP TABLE tuition_invoices');
            DB::unprepared('ALTER TABLE tuition_invoices_new RENAME TO tuition_invoices');

            DB::unprepared('PRAGMA foreign_keys = ON');
        });
    }
};
