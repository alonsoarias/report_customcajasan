<?php
// This file is part of Moodle - http://moodle.org/
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

/**
 * AJAX endpoint to get courses for a category
 *
 * @package    block_report_customcajasan
 * @copyright  2025 Cajasan
 * @author     Pedro Arias <soporte@ingeweb.co>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/blocks/report_customcajasan/lib.php');

// Check user login
require_login(null, false);

// Verify AJAX parameters
$categoryid = optional_param('categoryid', 0, PARAM_INT);

// Verify sesskey
if (!confirm_sesskey()) {
    $error = array(
        'success' => false,
        'error' => get_string('invalidsesskey', 'error')
    );
    echo json_encode($error);
    die();
}

// Check capability
$systemcontext = context_system::instance();
try {
    require_capability('block/report_customcajasan:viewreport', $systemcontext);
} catch (Exception $e) {
    $error = array(
        'success' => false,
        'error' => get_string('nopermissions', 'error', 'block/report_customcajasan:viewreport')
    );
    echo json_encode($error);
    die();
}

try {
    // Use safer parameter binding with get_in_or_equal when possible
    $params = array();
    $sql = "SELECT id, fullname FROM {course} WHERE 1=1";
    
    if (!empty($categoryid)) {
        $sql .= " AND category = :categoryid";
        $params['categoryid'] = $categoryid;
    }
    
    $sql .= " ORDER BY fullname ASC";
    
    // Get courses
    $courses = $DB->get_records_sql($sql, $params);
    
    // Format response
    $response = array(
        'success' => true,
        'courses' => array_values($courses),
        'count' => count($courses)
    );
    
    // Send JSON response
    echo json_encode($response);
} catch (Exception $e) {
    $error = array(
        'success' => false,
        'error' => 'Error getting courses: ' . $e->getMessage()
    );
    echo json_encode($error);
}