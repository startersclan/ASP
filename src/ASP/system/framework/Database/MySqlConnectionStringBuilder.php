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

/**
 * Generates connection strings used to connect to MySQL databases.
 *
 * @package System\Database
 */
class MySqlConnectionStringBuilder extends DbConnectionStringBuilder
{
    /**
     * @var string Gets or sets the character set to use.
     */
    public $charset = "UTF8";

    /**
     * MySqlConnectionStringBuilder constructor.
     */
    public function __construct()
    {
        $this->identifierEscapeChar = '`';
    }

    /**
     * @inheritdoc
     */
    public function getConnectionString()
    {
        return "mysql:host={$this->host};port={$this->port};dbname={$this->database};charset={$this->charset}";
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getConnectionString();
    }
}