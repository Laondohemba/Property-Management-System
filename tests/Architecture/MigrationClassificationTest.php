<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * CI classifies every migration as expand or contract and fails when an expand migration is
 * destructive (spec §4.3, DBM-06). See MigrationInspector for the rules.
 */
class MigrationClassificationTest extends TestCase
{
    public function test_every_migration_is_classified_and_follows_the_expand_contract_rules(): void
    {
        $files = glob(dirname(__DIR__, 2).'/database/migrations/*.php') ?: [];
        $this->assertNotEmpty($files);

        $inspector = new MigrationInspector;
        $violations = [];

        foreach ($files as $file) {
            foreach ($inspector->problems((string) file_get_contents($file)) as $problem) {
                $violations[] = basename($file).' '.$problem;
            }
        }

        $this->assertSame([], $violations, "Migration rules (spec §4.3):\n".implode("\n", $violations));
    }

    public function test_a_safe_expand_migration_passes(): void
    {
        $this->assertSame([], (new MigrationInspector)->problems($this->migration('expand', <<<'PHP'
            Schema::create('guest_notes', function (Blueprint $table) {
                $table->id();
                $table->string('body');
            });

            Schema::table('guests', function (Blueprint $table) {
                $table->string('first_name', 100)->nullable()->after('name');
                $table->boolean('vip')->default(false);
                $table->timestamp('verified_at')->useCurrent();
                $table->index('first_name');
            });
            PHP)));
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function unsafeMigrations(): iterable
    {
        yield 'drops a column' => ['expand', "Schema::table('guests', function (Blueprint \$table) {\n    \$table->dropColumn('name');\n});", 'calls dropColumn()'];
        yield 'renames a column' => ['expand', "Schema::table('guests', function (Blueprint \$table) {\n    \$table->renameColumn('name', 'full_name');\n});", 'calls renameColumn()'];
        yield 'changes a column type' => ['expand', "Schema::table('guests', function (Blueprint \$table) {\n    \$table->string('name', 50)->change();\n});", 'calls change()'];
        yield 'drops a table' => ['expand', "Schema::dropIfExists('guests');", 'calls dropIfExists()'];
        yield 'drops an index' => ['expand', "Schema::table('guests', function (Blueprint \$table) {\n    \$table->dropIndex(['email']);\n});", 'calls dropIndex()'];
        yield 'runs raw DROP' => ['expand', "DB::statement('ALTER TABLE guests DROP COLUMN name');", 'raw DROP SQL'];
        yield 'adds NOT NULL without default' => ['expand', "Schema::table('guests', function (Blueprint \$table) {\n    \$table->string('first_name');\n});", 'NOT NULL column first_name'];
        yield 'adds a required foreign key' => ['expand', "Schema::table('guests', function (Blueprint \$table) {\n    \$table->foreignId('company_id')->constrained();\n});", 'NOT NULL column company_id'];
        yield 'contracts the audit trail' => ['contract', "Schema::table('activity_log', function (Blueprint \$table) {\n    \$table->dropColumn('user_agent');\n});", 'append-only table activity_log'];
    }

    #[DataProvider('unsafeMigrations')]
    public function test_unsafe_migrations_are_rejected(string $stage, string $up, string $expected): void
    {
        $problems = (new MigrationInspector)->problems($this->migration($stage, $up));

        $this->assertCount(1, $problems);
        $this->assertStringContainsString($expected, $problems[0]);
    }

    public function test_a_contract_migration_may_drop_legacy_columns(): void
    {
        $this->assertSame([], (new MigrationInspector)->problems($this->migration('contract', <<<'PHP'
            Schema::table('guests', function (Blueprint $table) {
                $table->dropColumn('name');
            });
            PHP)));
    }

    public function test_unlabelled_migrations_are_rejected(): void
    {
        $source = str_replace(' * @migration expand', '', $this->migration('expand', "Schema::create('a', fn () => null);"));

        $this->assertStringContainsString('is not labelled', (new MigrationInspector)->problems($source)[0]);
    }

    public function test_a_migration_without_down_must_say_why(): void
    {
        $withoutDown = preg_replace('/\s*public function down\(\): void\s*\{[^}]*\}/', '', $this->migration('expand', "Schema::create('a', fn () => null);"));
        $this->assertIsString($withoutDown);

        $this->assertStringContainsString('has no down()', (new MigrationInspector)->problems($withoutDown)[0]);

        $documented = str_replace(' * @migration expand', " * @migration expand\n * @irreversible Backfilled values cannot be told apart from typed ones.", $withoutDown);
        $this->assertSame([], (new MigrationInspector)->problems($documented));
    }

    private function migration(string $stage, string $up): string
    {
        return <<<PHP
            <?php

            use Illuminate\\Database\\Migrations\\Migration;

            /**
             * Fixture.
             *
             * @migration {$stage}
             */
            return new class extends Migration
            {
                public function up(): void
                {
                    {$up}
                }

                public function down(): void
                {
                    Schema::dropIfExists('a');
                }
            };
            PHP;
    }
}
