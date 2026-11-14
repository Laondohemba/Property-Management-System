<?php

declare(strict_types=1);

namespace Tests\Architecture;

/**
 * Static checks for the Expand and Contract migration rules (spec §4.3, DBM-01, DBM-05, DBM-06).
 *
 * Each migration declares its stage in the class docblock:
 *
 *     /**
 *      * Adds guests.first_name and guests.last_name.
 *      *
 *      * @migration expand
 *      *\/
 *     return new class extends Migration { ... }
 *
 * An expand migration's up() must be additive: no drop, rename or column change, and no new
 * NOT NULL column without a default on an existing table. Contract migrations may remove
 * things, never from an append-only table. Every migration has a down() or documents why it
 * cannot be reversed with an @irreversible tag.
 */
final class MigrationInspector
{
    public const EXPAND = 'expand';

    public const CONTRACT = 'contract';

    /**
     * Append-only tables (spec §4.3): schema changes are strictly additive, so a contract
     * migration may never touch them. Financial tables join this list as they are created.
     */
    public const APPEND_ONLY_TABLES = ['activity_log'];

    /** Blueprint methods that add a column which is NOT NULL unless told otherwise. */
    private const COLUMN_TYPES = [
        'bigInteger', 'binary', 'boolean', 'char', 'date', 'dateTime', 'dateTimeTz', 'decimal',
        'double', 'enum', 'float', 'foreignId', 'foreignIdFor', 'foreignUlid', 'foreignUuid',
        'geography', 'geometry', 'integer', 'ipAddress', 'json', 'jsonb', 'longText', 'macAddress',
        'mediumInteger', 'mediumText', 'morphs', 'set', 'smallInteger', 'string', 'text', 'time',
        'timeTz', 'timestamp', 'timestampTz', 'tinyInteger', 'tinyText', 'ulid', 'ulidMorphs',
        'unsignedBigInteger', 'unsignedInteger', 'unsignedMediumInteger', 'unsignedSmallInteger',
        'unsignedTinyInteger', 'uuid', 'uuidMorphs', 'vector', 'year',
    ];

    /** Modifiers that make a new column safe for rows that already exist. */
    private const SAFE_MODIFIERS = ['nullable(', 'default(', 'useCurrent(', 'storedAs(', 'virtualAs(', 'autoIncrement('];

    /**
     * Problems found in one migration's source; an empty list means it follows the rules.
     *
     * @return list<string>
     */
    public function problems(string $source): array
    {
        $stage = $this->stage($source);

        if ($stage === null) {
            return ['is not labelled: add "@migration expand" or "@migration contract" to the class docblock'];
        }

        $problems = [];
        $up = $this->methodBody($source, 'up') ?? '';

        if ($stage === self::EXPAND) {
            foreach ($this->destructiveCalls($up) as $call) {
                $problems[] = "is labelled expand but up() calls {$call}(): destructive changes ship as a contract migration in a later release";
            }

            foreach ($this->destructiveSql($up) as $keyword) {
                $problems[] = "is labelled expand but up() runs raw {$keyword} SQL";
            }

            foreach ($this->unsafeNewColumns($up) as $column) {
                $problems[] = "is labelled expand but adds NOT NULL column {$column} to an existing table without a default: make it nullable or give it a default";
            }
        }

        if ($stage === self::CONTRACT) {
            foreach (self::APPEND_ONLY_TABLES as $table) {
                if (preg_match('/[\'"]'.preg_quote($table, '/').'[\'"]/', $up) === 1) {
                    $problems[] = "is a contract migration on append-only table {$table}: its schema changes must be additive";
                }
            }
        }

        if ($this->methodBody($source, 'down') === null && ! str_contains($this->classDocblock($source), '@irreversible')) {
            $problems[] = 'has no down(): add one, or document why it cannot be reversed with an @irreversible tag';
        }

        return $problems;
    }

    public function stage(string $source): ?string
    {
        if (preg_match('/@migration\s+(expand|contract)\b/', $this->classDocblock($source), $match) !== 1) {
            return null;
        }

        return $match[1];
    }

    private function classDocblock(string $source): string
    {
        return preg_match('~(/\*\*(?:(?!\*/).)*\*/)\s*return\s+new\s+class\b~s', $source, $match) === 1 ? $match[1] : '';
    }

    /** The body of a method, found by matching braces from its declaration. */
    private function methodBody(string $source, string $method): ?string
    {
        if (preg_match('/function\s+'.$method.'\s*\([^)]*\)[^{]*\{/', $source, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $start = $match[0][1] + strlen($match[0][0]);

        return $this->untilClosingBrace($source, $start);
    }

    private function untilClosingBrace(string $source, int $start): string
    {
        $depth = 1;

        for ($i = $start, $length = strlen($source); $i < $length; $i++) {
            $depth += match ($source[$i]) {
                '{' => 1,
                '}' => -1,
                default => 0,
            };

            if ($depth === 0) {
                return substr($source, $start, $i - $start);
            }
        }

        return substr($source, $start);
    }

    /** @return list<string> */
    private function destructiveCalls(string $code): array
    {
        preg_match_all('/(?:->|::)(drop\w*|rename\w*|change)\s*\(/', $code, $matches);

        return array_values(array_unique($matches[1]));
    }

    /** @return list<string> */
    private function destructiveSql(string $code): array
    {
        preg_match_all('/\b(DROP|TRUNCATE|RENAME|MODIFY)\s/', $code, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * New columns on existing tables (inside Schema::table) that are NOT NULL with no default.
     * Columns of a table created in the same migration are always safe.
     *
     * @return list<string>
     */
    private function unsafeNewColumns(string $code): array
    {
        $unsafe = [];
        $offset = 0;

        while (preg_match('/Schema::table\([^{]*\{/', $code, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $start = $match[0][1] + strlen($match[0][0]);
            $body = $this->untilClosingBrace($code, $start);
            $offset = $start + strlen($body);

            foreach (explode(';', $body) as $statement) {
                if (preg_match('/^\s*\$table->(\w+)\(\s*([\'"]?)([\w.]*)\2/', $statement, $column) !== 1
                    || ! in_array($column[1], self::COLUMN_TYPES, true)
                    || str_contains($statement, '->change(')) {
                    continue;
                }

                $safe = array_filter(self::SAFE_MODIFIERS, fn (string $modifier): bool => str_contains($statement, '->'.$modifier));

                if ($safe === []) {
                    $unsafe[] = $column[3] !== '' ? $column[3] : $column[1];
                }
            }
        }

        return $unsafe;
    }
}
