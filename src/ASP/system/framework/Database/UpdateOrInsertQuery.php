<?php
/**
 * BF2Statistics ASP Framework
 *
 * Author:       Steven Wilson
 * Copyright:    Copyright (c) 2006-2021, BF2statistics.com
 * License:      GNU GPL v3
 *
 */

namespace System\Database;

class UpdateOrInsertQuery
{
    /**
     * @var string The table we are modifying
     */
    public $table = '';

    /**
     * @var array An array of [Key => [operator => value]]
     */
    protected $columns = array();

    /**
     * @var array An array of [Key => [operator => value]]
     */
    protected $where = array();

    /**
     * @var string Specified the AND / OR where statement seperator
     */
    public $whereSeparator = "AND";

    /**
     * @var DbConnection
     */
    protected $connection;

    public function __construct(DbConnection $connection, $table)
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    /**
     * Sets a column and value
     *
     * @param string $column The column name
     * @param string $operator The comparison operator:
     * @param mixed $value The new value
     */
    public function set($column, $operator, $value)
    {
        // Correct bools
        if (is_bool($value))
            $value = ($value) ? 1 : 0;

        $this->columns[$column] = array($operator, $value);
    }

    /**
     * Sets an array of columns and values
     *
     * @param array $pairs A key value collections
     * @param string $operator The comparison operator
     */
    public function setArray($pairs, $operator)
    {
        foreach ($pairs as $column => $value)
        {
            $this->set($column, $operator, $value);
        }
    }

    /**
     * Sets a column and value to be used in the WHERE clause
     *
     * @param string $column The column name
     * @param string $operator The comparison operator
     * @param mixed $value The comparison value
     */
    public function where($column, $operator, $value)
    {
        // Correct bools
        if (is_bool($value))
            $value = ($value) ? 1 : 0;

        $this->where[$column] = array($operator, $value);
    }

    /**
     * Executes the current SQL statement
     *
     * @return int The number of rows affected
     */
    public function execute()
    {
        $result = $this->executeUpdate();
        if ($result == 0)
            return $this->executeInsert();

        return $result;
    }

    /**
     * @return int The number of rows affected
     */
    public function executeUpdate()
    {
        // Parse where clause
        $statements = array();
        foreach ($this->where as $col => $values)
        {
            list($operator, $value) = $values;
            $col = $this->connection->quoteIdentifier($col);
            $statements[] = "{$col}{$operator}" . $this->connection->quote($value);
        }

        $where = implode(" {$this->whereSeparator} ", $statements);

        // Do we have a where tp process?
        if ($where != '')
        {
            $where = ' WHERE ' . $where;
            /*
            $result = (int)$this->connection->query("SELECT COUNT(*) FROM `{$this->table}`". $where)->fetchColumn(0);
            if ($result == 0) return 0;
            */
        }

        // start creating the SQL string and enclose field names in `
        $statements = array();
        foreach ($this->columns as $col => $values)
        {
            list($operator, $value) = $values;
            $value = (is_int($value)) ? $value : $this->connection->quote($value);
            $col = $this->connection->quoteIdentifier($col);

            switch ($operator)
            {
                case "+":
                case "+=":
                    $statements[] = "{$col} = {$col} + {$value}";
                    break;
                case "-":
                case "-=":
                    $statements[] = "{$col} = {$col} - {$value}";
                    break;
                case "g":
                    $statements[] = "{$col} = CASE WHEN {$value} > {$col} THEN {$value} ELSE {$col} END";
                    break;
                case "l":
                    $statements[] = "{$col} = CASE WHEN {$value} < {$col} THEN {$value} ELSE {$col} END";
                    break;
                default:
                    $statements[] = "{$col}{$operator}{$value}";
            }
        }
        $cols = implode(", ", $statements);

        // Build our query
        $table = $this->connection->quoteIdentifier($this->table);
        return $this->connection->exec("UPDATE {$table} SET {$cols}{$where}");
    }

    /**
     * @return int The number of rows affected
     */
    public function executeInsert()
    {
        // Insert fields from the Where clause, since those are primary keys most likely!
        $statements = array();
        foreach ($this->where as $col => $values)
        {
            list($operator, $value) = $values;
            if (!isset($this->columns[$col]))
                $statements[$col] = (is_int($value)) ? $value : $this->connection->quote($value);
        }

        // start creating the SQL string and enclose field names in `
        foreach ($this->columns as $col => $values)
        {
            list($operator, $value) = $values;
            $statements[$col] = (is_int($value)) ? $value : $this->connection->quote($value);
        }

        $cols = implode("`, `", array_keys($statements));
        $values = implode(", ", array_values($statements));

        // Build our query
        return $this->connection->exec("INSERT INTO `{$this->table}`(`{$cols}`) VALUES({$values})");
    }
}