<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * The live foreign key graph of the database, read from information_schema
 * rather than written by hand. A delete planner that carries its own list of
 * relationships goes stale the day a migration adds one, and the failure mode
 * is silent: a new child table either blocks with an unhelpful error or is
 * dropped without anyone deciding that it should be.
 */
class ForeignKeyMap
{
    /** @var array<string, list<array{table: string, column: string, referenced: string, rule: string}>>|null */
    private ?array $children = null;

    /**
     * Every table that holds a foreign key pointing at $table.
     *
     * @return list<array{table: string, column: string, referenced: string, rule: string}>
     */
    public function childrenOf(string $table): array
    {
        return $this->load()[$table] ?? [];
    }

    /**
     * The column of $table that other tables point at, which is its primary key
     * everywhere in this schema. Defaults to id for a table nobody references.
     */
    public function keyOf(string $table): string
    {
        foreach ($this->childrenOf($table) as $child) {
            return $child['referenced'];
        }

        return 'id';
    }

    /**
     * @return array<string, list<array{table: string, column: string, referenced: string, rule: string}>>
     */
    private function load(): array
    {
        if ($this->children !== null) {
            return $this->children;
        }

        $rows = DB::select(
            'select k.TABLE_NAME as child_table, k.COLUMN_NAME as child_column, '
            .'k.REFERENCED_TABLE_NAME as parent_table, k.REFERENCED_COLUMN_NAME as parent_column, '
            .'r.DELETE_RULE as delete_rule '
            .'from information_schema.KEY_COLUMN_USAGE k '
            .'join information_schema.REFERENTIAL_CONSTRAINTS r '
            .'on r.CONSTRAINT_NAME = k.CONSTRAINT_NAME and r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA '
            .'where k.REFERENCED_TABLE_NAME is not null and k.TABLE_SCHEMA = DATABASE()'
        );

        $map = [];
        foreach ($rows as $row) {
            $map[$row->parent_table][] = [
                'table' => $row->child_table,
                'column' => $row->child_column,
                'referenced' => $row->parent_column,
                'rule' => strtoupper((string) $row->delete_rule),
            ];
        }

        return $this->children = $map;
    }
}
