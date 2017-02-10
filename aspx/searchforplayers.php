<?php
/*
    Copyright (C) 2006-2013  BF2Statistics

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

// Namespace
namespace System;

use PDOStatement;

// No direct access
defined("BF2_ADMIN") or die("No Direct Access");

// Prepare output
$Response = new AspResponse();

// Fetch URL parameter values
$nick = (isset($_GET['nick'])) ? $_GET['nick'] : '';
$where = (isset($_GET['where'])) ? $_GET['where'] : 'a';
$sort = (isset($_GET['sort'])) ? $_GET['sort'] : 'a';

// Make sure we have a Nick to go by
if (empty($nick))
{
    $Response->responseError(true);
    $Response->writeHeaderLine("asof", "err");
    $Response->writeDataLine(time(), "Invalid Syntax!");
    $Response->send();
}
else
{
    // Connect to the database
    $connection = Database::GetConnection("stats");

    // Prepare Response
    $Response->writeHeaderLine("asof");
    $Response->writeDataLine(time());
    $Response->writeHeaderLine("n", "pid", "nick", "score");

    // Define sorting
    $sorting = ($sort == 'r') ? "DESC" : "ASC";

    // Fetch matching players, using a prepared statement for SQL injection immunity
    $query = "SELECT `id`, `name`, `score` FROM `player` WHERE `name` LIKE :search ORDER BY name {$sorting} LIMIT 20";
    $result = $connection->prepare($query);
    if ($result instanceof PDOStatement)
    {
        switch ($where)
        {
            default:
            case 'a': // any
                $result->bindValue(":search", "%{$nick}%", \PDO::PARAM_STR);
                break;
            case 'b': // begins with
                $result->bindValue(":search", "{$nick}%", \PDO::PARAM_STR);
                break;
            case 'e': // ends with
                $result->bindValue(":search", "%{$nick}", \PDO::PARAM_STR);
                break;
            case 'x': // exactly
                $result->bindValue(":search", "{$nick}", \PDO::PARAM_STR);
                break;
        }

        // Execute the query
        if ($result->execute())
        {
            $num = 1;
            while ($row = $result->fetch())
                $Response->writeDataLine($num++, $row['id'], $row['name'], $row['score']);
        }
    }

    $Response->send();
}