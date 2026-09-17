<?php
// This file is part of the Accredible Certificate module for Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_accredible\local;

/**
 * An Accredible API failure, carrying the error it was raised from.
 *
 * moodle_exception cannot chain: its constructor takes no $previous, and \Exception::$previous
 * is private, so a wrapped error's class would otherwise be lost and every failure would be
 * logged as moodle_exception. Keeping the original here lets the issuance handlers log the class
 * that actually failed, which is what separates an API rejection from a PHP fault in the plugin.
 *
 * @package    mod_accredible
 * @subpackage accredible
 * @copyright  Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api_exception extends \moodle_exception {
    /**
     * The error this exception was raised from, if any.
     * @var \Throwable|null
     */
    public $cause;

    /**
     * Constructor method.
     *
     * @param string $errorcode a lang string identifier in mod_accredible.
     * @param string $link a URL to direct the user to.
     * @param mixed $a the lang string's placeholder values.
     * @param string|null $debuginfo developer-only detail; must be a string or scalar.
     * @param \Throwable|null $cause the error being wrapped.
     */
    public function __construct($errorcode, $link = '', $a = null, $debuginfo = null, ?\Throwable $cause = null) {
        $this->cause = $cause;
        parent::__construct($errorcode, 'accredible', $link, $a, $debuginfo);
    }

    /**
     * Describe an error for the {$a->cause} placeholder of a lang string.
     *
     * A bare \Exception is the plugin's own way of relaying an already-normalised API message,
     * so its class name adds nothing; anything else is a real PHP fault worth naming.
     *
     * @param \Throwable $e the error being wrapped.
     * @return string
     */
    public static function cause_text(\Throwable $e) {
        if (get_class($e) === \Exception::class) {
            return $e->getMessage();
        }
        return get_class($e) . ': ' . $e->getMessage();
    }

    /**
     * The class of the error that actually failed, for logging.
     *
     * @param \Throwable $e the exception a handler caught.
     * @return string
     */
    public static function origin_class(\Throwable $e) {
        if ($e instanceof self && $e->cause !== null) {
            return get_class($e->cause);
        }
        return get_class($e);
    }

    /**
     * Developer detail worth logging beside the message, or null when there is none.
     *
     * Core exceptions hide their real diagnosis here: a dml_write_exception's message is the
     * translated "Error writing to database" while $debuginfo holds the actual SQL error, and
     * moodle_exception only folds $debuginfo into the message under PHPUnit or DEBUG_DEVELOPER.
     * This plugin's own exceptions carry their cause in $a instead, so they return null rather
     * than repeat it on every row.
     *
     * @param \Throwable $e the exception a handler caught.
     * @return string|null
     */
    public static function debug_detail(\Throwable $e) {
        if ($e instanceof self || !($e instanceof \moodle_exception) || empty($e->debuginfo)) {
            return null;
        }
        return (string) $e->debuginfo;
    }
}
