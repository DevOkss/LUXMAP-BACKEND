<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function hasIndex(string $table, string $index): bool
    {
        $driver = DB::getDriverName();
        try {
            if ($driver === 'sqlite') {
                $rows = DB::select("SELECT name FROM sqlite_master WHERE type='index' AND tbl_name = ? AND name = ?", [$table, $index]);
                return count($rows) > 0;
            }
            $rows = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]);
            return count($rows) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function up(): void
    {
        // Add column if not already present (idempotent for re-run after previous failure)
        if (!Schema::hasColumn('organization_user', 'academic_term_id')) {
            Schema::table('organization_user', function (Blueprint $table) {
                $table->foreignId('academic_term_id')->nullable()->after('organization_id')->constrained('academic_terms')->nullOnDelete();
                $table->index('academic_term_id');
            });
        } else {
            // Ensure index exists if column was added but migration failed before completing
            if (!$this->hasIndex('organization_user', 'organization_user_academic_term_id_index')) {
                try {
                    Schema::table('organization_user', function (Blueprint $table) {
                        $table->index('academic_term_id');
                    });
                } catch (\Throwable $e) {
                }
            }
            // Ensure foreign key exists (MySQL only, SQLite may not enforce)
            if (DB::getDriverName() === 'mysql') {
                try {
                    $fkExists = collect(DB::select("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'organization_user' AND COLUMN_NAME = 'academic_term_id' AND REFERENCED_TABLE_NAME = 'academic_terms'"))->isNotEmpty();
                    if (!$fkExists) {
                        Schema::table('organization_user', function (Blueprint $table) {
                            $table->foreign('academic_term_id')->references('id')->on('academic_terms')->nullOnDelete();
                        });
                    }
                } catch (\Throwable $e) {
                }
            }
        }

        // Ensure user_id has its own index before dropping the composite unique (which is currently the only index for user_id FK on MySQL)
        if (!$this->hasIndex('organization_user', 'organization_user_user_id_index')) {
            try {
                Schema::table('organization_user', function (Blueprint $table) {
                    $table->index('user_id', 'organization_user_user_id_index');
                });
            } catch (\Throwable $e) {
            }
        }

        // Backfill existing officer assignments to the current active term if one exists
        $activeTermId = DB::table('academic_terms')->where('is_active', true)->value('id');
        if ($activeTermId) {
            $officerRoles = ['ssc_officer', 'isc_officer', 'sro_officer'];
            DB::table('organization_user')
                ->whereIn('role', $officerRoles)
                ->whereNull('academic_term_id')
                ->update(['academic_term_id' => $activeTermId]);
        }

        // Replace unique (user_id, organization_id) with (user_id, organization_id, academic_term_id)
        if ($this->hasIndex('organization_user', 'organization_user_user_id_organization_id_unique')) {
            Schema::table('organization_user', function (Blueprint $table) {
                try {
                    $table->dropUnique(['user_id', 'organization_id']);
                } catch (\Throwable $e) {
                    try {
                        DB::statement('ALTER TABLE organization_user DROP INDEX organization_user_user_id_organization_id_unique');
                    } catch (\Throwable $e2) {
                    }
                }
            });
        }

        if (!$this->hasIndex('organization_user', 'org_user_term_unique')) {
            Schema::table('organization_user', function (Blueprint $table) {
                $table->unique(['user_id', 'organization_id', 'academic_term_id'], 'org_user_term_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::table('organization_user', function (Blueprint $table) {
            try {
                $table->dropUnique('org_user_term_unique');
            } catch (\Throwable $e) {
            }
            try {
                $table->dropIndex('organization_user_user_id_index');
            } catch (\Throwable $e) {
            }
            try {
                $table->dropForeign(['academic_term_id']);
            } catch (\Throwable $e) {
            }
            try {
                $table->dropIndex(['academic_term_id']);
            } catch (\Throwable $e) {
            }
            if (Schema::hasColumn('organization_user', 'academic_term_id')) {
                $table->dropColumn('academic_term_id');
            }
            if (!$this->hasIndex('organization_user', 'organization_user_user_id_organization_id_unique')) {
                try {
                    $table->unique(['user_id', 'organization_id']);
                } catch (\Throwable $e) {
                }
            }
        });
    }

    private function hasIndexLegacy(string $table, string $index): bool
    {
        return $this->hasIndex($table, $index);
    }
};
