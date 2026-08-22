<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';

        if ($isSqlite) {
            // SQLite cannot ALTER away a column referenced by a foreign key, so
            // rebuild the table to mirror the MySQL schema used in production.
            Schema::dropIfExists('attendances');
            Schema::create('attendances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('qr_configuration_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->dateTime('scanned_at');
                $table->dateTime('synced_at')->nullable();
                $table->timestamps();
                $table->unique(['qr_configuration_id', 'user_id']);
            });

            return;
        }

        // Drops must be guarded by actual schema state: exceptions thrown while
        // executing statements happen after the Blueprint closure returns, so
        // try/catch inside the closure never fires. This also keeps the
        // migration re-runnable against partially migrated databases.
        $columns = Schema::getColumnListing('attendances');

        $droppable = array_values(array_filter([
            'event_id',
            'organization_id',
            'webauthn_credential_id',
            'qr_data',
            'gps_latitude',
            'gps_longitude',
            'gps_accuracy',
            'device_info',
            'ip_address',
            'is_offline',
        ], fn (string $column) => in_array($column, $columns, true)));

        foreach (['event_id', 'organization_id', 'webauthn_credential_id'] as $foreignKeyColumn) {
            if (! in_array($foreignKeyColumn, $columns, true)) {
                continue;
            }
            $this->dropForeignKeyIfExists('attendances', "attendances_{$foreignKeyColumn}_foreign");
        }

        $indexNames = collect(Schema::getIndexes('attendances'))->pluck('name')->all();
        foreach (['attendances_event_id_user_id_unique', 'attendances_event_id_organization_id_index'] as $index) {
            if (in_array($index, $indexNames, true)) {
                Schema::table('attendances', fn (Blueprint $table) => $table->dropIndex($index));
            }
        }

        if ($droppable !== []) {
            Schema::table('attendances', function (Blueprint $table) use ($droppable) {
                $table->dropColumn($droppable);
            });
        }

        if (! Schema::hasColumn('attendances', 'qr_configuration_id')) {
            Schema::table('attendances', function (Blueprint $table) {
                $table->foreignId('qr_configuration_id')->nullable();
                $table->foreign('qr_configuration_id')->references('id')->on('qr_configurations')->nullOnDelete();
                $table->unique(['qr_configuration_id', 'user_id']);
            });
        }
    }

    private function dropForeignKeyIfExists(string $table, string $foreignKey): void
    {
        $existing = collect(Schema::getForeignKeys($table))->pluck('name')->all();

        if (in_array($foreignKey, $existing, true)) {
            Schema::table($table, fn (Blueprint $t) => $t->dropForeign($foreignKey));
        }
    }

    public function down(): void
    {
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';

        if ($isSqlite) {
            Schema::dropIfExists('attendances');
            Schema::create('attendances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('event_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->text('qr_data')->nullable();
                $table->dateTime('scanned_at');
                $table->decimal('gps_latitude', 10, 7)->nullable();
                $table->decimal('gps_longitude', 10, 7)->nullable();
                $table->decimal('gps_accuracy', 10, 2)->nullable();
                $table->text('device_info')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->boolean('is_offline')->default(false);
                $table->dateTime('synced_at')->nullable();
                $table->timestamps();
                $table->unique(['event_id', 'user_id']);
                $table->index('scanned_at');
                $table->index(['event_id', 'organization_id']);
            });

            return;
        }

        Schema::table('attendances', function (Blueprint $table) {
            try {
                $table->dropForeign('attendances_qr_configuration_id_foreign');
            } catch (Throwable) {
            }
            try {
                $table->dropUnique('attendances_qr_configuration_id_user_id_unique');
            } catch (Throwable) {
            }
            $table->dropColumn('qr_configuration_id');
        });

        Schema::table('attendances', fn (Blueprint $table) => $table->foreignId('event_id')->nullable());

        Schema::table('attendances', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable();
            $table->text('qr_data')->nullable();
            $table->decimal('gps_latitude', 10, 7)->nullable();
            $table->decimal('gps_longitude', 10, 7)->nullable();
            $table->decimal('gps_accuracy', 10, 2)->nullable();
            $table->text('device_info')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->boolean('is_offline')->default(false);
            $table->foreignId('webauthn_credential_id')->nullable();
        });
    }
};
