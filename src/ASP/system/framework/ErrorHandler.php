<?php
/**
 * BF2Statistics ASP Framework
 *
 * Author:       Steven Wilson
 * Copyright:    Copyright (c) 2006-2021, BF2statistics.com
 * License:      GNU GPL v3
 *
 */

namespace System;
use Exception;

/**
 * Responsible for handling all errors, and exceptions, and displaying
 * an error page
 *
 * @package System
 */
class ErrorHandler
{
    /**
     * @var bool
     */
    protected static $HandlingErrors = false;

    /**
     * @var bool
     */
    protected static $HandlingExceptions = false;

    /**
     * Registers this object as the error handler
     *
     * @param bool $handleErrors
     * @param bool $handleExceptions
     *
     * @return void
     */
    public static function Register($handleErrors = true, $handleExceptions = true)
    {
        // Errors
        if ($handleErrors && !self::$HandlingErrors)
        {
            self::$HandlingErrors = true;
            set_error_handler('System\ErrorHandler::HandlePHPError');
            error_reporting(E_ALL);
        }

        // Exceptions
        if ($handleExceptions && !self::$HandlingExceptions)
        {
            self::$HandlingExceptions = true;
            set_exception_handler('System\ErrorHandler::HandleException');
        }

        // Make sure to register output buffering!
        if (ob_get_level() == 0)
        {
            ini_set('output_buffering', 'On');
            ob_start();
        }
    }

    /**
     * UnRegisters this object as the error handler
     *
     * @return void
     */
    public static function UnRegister()
    {
        self::$HandlingErrors = false;
        self::$HandlingExceptions = false;
        restore_error_handler();
        restore_exception_handler();
    }

    /**
     * Main method for showing an error. Not guaranteed to display the error, just
     * depends on the users error reporting level.
     *
     * @param int $lvl Error level. the error levels share the php constants error levels
     * @param string $message The error message
     * @param string $file The filename in which the error was triggered from
     * @param int $line The line number in which the error was triggered from
     *
     * @return void
     */
    public static function TriggerError($lvl, $message, $file, $line)
    {
        self::DisplayError($lvl, $message, $file, $line);
    }

    /**
     * Same method as TriggerError, except this method is called by php internally
     *
     * @param int $lvl Error level. the error levels share the php constants error levels
     * @param string $message The error message
     * @param string $file The filename in which the error was triggered from
     * @param int $line The line number in which the error was triggered from
     *
     * @return bool
     */
    public static function HandlePHPError($lvl, $message, $file, $line)
    {
        // If the error_reporting level is 0, then this is a suppressed error ("@" preceding)
        if (!(error_reporting() & $lvl))
            return false;

        // Display error
        self::DisplayError($lvl, $message, $file, $line);

        // Don't execute PHP internal error handler
        return true;
    }

    /**
     * Main method for handling exceptions
     *
     * @param Exception $e The thrown exception
     *
     * @return void
     */
    public static function HandleException($e)
    {
        self::DisplayError($e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine(), $e);
    }

    /**
     * Logs a detailed and recursive exception to the asp_debug.log file
     *
     * @param Exception $e
     */
    public static function LogException(Exception $e)
    {
        $log = LogWriter::Instance('Asp');
        if ($log instanceof LogWriter)
        {
            $log->logError('A Handled Exception was logged');
            $log->writeLine("\tException Type: " . get_class($e));
            $log->writeLine("\tMessage: " . $e->getMessage());
            $log->writeLine("\tCode: " . $e->getCode());
            $log->writeLine("\tFile: " . $e->getFile());
            $log->writeLine("\tLine: " . $e->getLine());
            $log->writeLine("\tStack Trace: ");

            $i = 0;
            $trace = self::BuildStackTrace($e->getTrace(), false);
            foreach ($trace as $level)
            {
                $log->writeLine("\t\t[{$i}] => [");
                $log->writeLine("\t\t\t\"{$level['file']}\" @ line {$level['line']}");
                $log->writeLine("\t\t\t{$level['func']}({$level['args']})");
                $log->writeLine("\t\t]");
                $i++;
            }

            if ($ex = $e->getPrevious())
            {
                $i = 0;
                $log->writeLine("\tInner Exceptions: ");
                do
                {
                    $log->writeLine(
                        sprintf("\t\t[%d] => %s [%s] (%d) : %s",
                            $i++,
                            $ex->getMessage(),
                            $ex->getFile(),
                            $ex->getLine(),
                            get_class($ex)
                        )
                    );
                } while ($ex = $e->getPrevious());
            }
        }
    }

    /**
     * Displays the error screen
     *
     * @param int $code The PHP error level or Exception code.
     * @param string $message The error message
     * @param string $file The filename in which the error was triggered from
     * @param int $line The line number in which the error was triggered from
     * @param bool|Exception $exception The Exception object, or false if a PHP error
     *
     * @return void
     */
    protected static function DisplayError($code, $message, $file, $line, $exception = false)
    {
        // Clear out the current output buffer
        if (ob_get_length() != 0) ob_clean();

        // Define variables
        $isAjax = Request::IsAjax();
        $isException = ($exception instanceof Exception);
        $level = ($isException) ? $code : self::ErrorLevelToText($code);

        // If ajax, then we create a json encoded error
        if ($isAjax)
        {
            $mode = ($exception == true) ? "Exception" : $level;
            $data = array(
                'success' => false,
                'message' => "A PHP {$mode} was thrown during this request",
                'error' => "A PHP {$mode} was thrown during this request",
                'errorData' => array(
                    'exception' => $isException,
                    'level' => $level,
                    'message' => $message,
                    'file' => $file,
                    'line' => $line
                )
            );
            $page = json_encode($data);
        }
        else
        {
            try
            {
                // Create headline message
                $headline = ($isException) ? "An Unhandled Exception has occurred" : "A PHP {$level} has occurred";

                // Create view, and set variables
                $view = new View('error');
                $view->set('message', $message);
                $view->set('isException', $isException);
                $view->set('type', ($isException) ? get_class($exception) : "PHP {$level}");
                $view->set('headline', $headline);
                $view->set('code', $code);
                $view->set('file', $file);
                $view->set('line', $line);

                // Set stack trace
                if ($isException)
                {
                    /** @var Exception $exception */
                    $view->set('stacktrace', self::BuildStackTrace($exception->getTrace()));
                }
                else
                {
                    $view->set('stacktrace', self::BuildStackTrace(debug_backtrace()));
                }

                // Store rendered view in the page variable for later
                $page = $view->render(false, true);
            }
            catch (Exception $e)
            {
                $page = $message;
            }
        }

        // Set error header if the headers have yet to be sent
        if (!headers_sent())
            header("HTTP/1.1 500 Internal Server Error");

        // Spit out the error message
        die($page);
    }

    /**
     * Formalizes a stack trace array
     *
     * @param array $stack The stack trace
     *
     * @param bool $htmlEntities
     *
     * @return array
     */
    private static function BuildStackTrace(array $stack, $htmlEntities = true)
    {
        $return = [];

        foreach ($stack as $level)
        {
            // File
            $file = '(unknown file)';
            if (isset($level['file']))
            {
                $file = str_replace([ROOT, DS], ['', '/'], $level['file']);
                if ($htmlEntities)
                    $file = htmlspecialchars($file);
            }

            // Check info
            $function = isset($level['function']) ? $level['function'] : '(unknown function)';
            if (isset($level['class']) and strlen($level['class']) > 0)
            {
                // Ignore the flow of this class
                if ($level['class'] == 'System\ErrorHandler')
                    continue;

                // Build function string
                $type = (isset($level['type'])) ? $level['type'] : '::';
                $function = $level['class']. $type .$function;
            }

            // Arguments
            $args = array();
            if (isset($level['args']))
            {
                foreach ($level['args'] as $arg)
                {
                    $args[] = self::DescribeVar($arg);
                }
            }

            // Append return
            $return[] = [
                'file' => $file,
                'line' => isset($level['line']) ? $level['line'] : 0,
                'func' => $function,
                'args' => implode(', ', $args)
            ];
        }

        return $return;
    }

    /**
     * Return a description string of a variable
     *
     * @var mixed $var the var
     *
     * @return string the description
     */
    private static function DescribeVar($var)
    {
        if (is_array($var))
        {
            return 'array('.count($var).')';
        }
        elseif (is_object($var))
        {
            $id = method_exists($var, 'getId') ? $var->getId() : (property_exists($var, 'id') ? $var->id : '');
            return get_class($var).'('.$id.')';
        }
        elseif (is_bool($var))
        {
            return ($var ? 'true' : 'false');
        }
        elseif (is_string($var))
        {
            return '\''.$var.'\'';
        }
        else
        {
            return $var;
        }
    }

    /**
     * Converts a php error constant level to it's string name
     *
     * @param int $lvl The error constant
     *
     * @return string
     */
    protected static function ErrorLevelToText($lvl)
    {
        switch ($lvl)
        {
            default:
            case E_ERROR:
                return 'Error';
            case E_WARNING:
                return 'Warning';
            case E_NOTICE:
                return 'Notice';
            case E_USER_ERROR:
                return 'User Error';
            case E_USER_WARNING:
                return 'User Warning';
            case E_USER_NOTICE:
                return 'User Notice';
            case E_PARSE:
                return 'Parse Error';
            case E_STRICT:
                return 'Strict';
            case E_CORE_ERROR:
                return 'PHP Core Error';
        }
    }
}