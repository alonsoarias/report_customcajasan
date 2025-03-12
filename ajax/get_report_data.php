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
 * AJAX endpoint to get report data
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

// Verify sesskey with better error handling
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
    // Get filter parameters with consistent pattern
    $filters = array(
        'category' => optional_param('categoryid', 0, PARAM_INT),
        'course' => optional_param('courseid', 0, PARAM_INT),
        'idnumber' => optional_param('idnumber', '', PARAM_TEXT),
        'firstname' => optional_param('firstname', '', PARAM_TEXT),
        'lastname' => optional_param('lastname', '', PARAM_TEXT),
        'estado' => optional_param('estado', '', PARAM_TEXT),
        'startdate' => optional_param('startdate', '', PARAM_TEXT),
        'enddate' => optional_param('enddate', '', PARAM_TEXT)
    );
    
    // Process date parameters
    if (!empty($filters['startdate'])) {
        $filters['startdate'] = strtotime($filters['startdate']);
    }
    
    if (!empty($filters['enddate'])) {
        $filters['enddate'] = strtotime($filters['enddate'] . ' 23:59:59');
    }
    
    // Store filters in session for download use
    $_SESSION['report_customcajasan_filters'] = $filters;
    
    // Pagination parameters
    $page = optional_param('page', 0, PARAM_INT);
    $perpage = optional_param('perpage', 100, PARAM_INT);
    
    // Get total count using optimized query
    $totalcount = report_customcajasan_count_data($filters);
    
    // Get only the records for the current page
    $limitfrom = $page * $perpage;
    $enrollments = report_customcajasan_get_data($filters, $limitfrom, $perpage);
    
    // Prepare HTML table
    $html = '';
    if (empty($enrollments)) {
        $html = html_writer::tag('div', 
            get_string('no_data', 'block_report_customcajasan'), 
            array('class' => 'alert alert-info'));
    } else {
        $html .= html_writer::tag('div', 
            get_string('total_records', 'block_report_customcajasan') . ': ' . $totalcount, 
            array('class' => 'font-weight-bold mb-2'));
        
        // Table with reorganized columns
        $table = new html_table();
        $table->head = array(
            get_string('column_identificacion', 'block_report_customcajasan'),
            get_string('column_nombres', 'block_report_customcajasan'),
            get_string('column_apellidos', 'block_report_customcajasan'),
            get_string('column_correo', 'block_report_customcajasan'),
            get_string('column_curso', 'block_report_customcajasan'),
            get_string('column_categoria', 'block_report_customcajasan'),
            get_string('column_unidad', 'block_report_customcajasan'),
            get_string('column_fecha_matricula', 'block_report_customcajasan'),
            get_string('column_ultimo_acceso', 'block_report_customcajasan'),
            get_string('column_fecha_certificado', 'block_report_customcajasan'),
            get_string('column_estado', 'block_report_customcajasan')
        );
        $table->data = array();
        
        // Prepare data with links to courses
        foreach ($enrollments as $enrollment) {
            // Create course link
            $curso_link = html_writer::link(
                new moodle_url('/course/view.php', array('id' => $enrollment->courseid)),
                $enrollment->curso,
                array('target' => '_blank')
            );
            
            // Add row with link and reorganized columns
            $table->data[] = array(
                $enrollment->identificacion,
                $enrollment->nombres,
                $enrollment->apellidos,
                $enrollment->correo,
                $curso_link,
                $enrollment->categoria,
                $enrollment->unidad,
                $enrollment->fecha_matricula,
                $enrollment->ultimo_acceso,
                $enrollment->fecha_certificado, 
                $enrollment->estado
            );
        }
        
        $table->id = 'enrollment-report-table';
        $table->attributes['class'] = 'table table-striped table-bordered table-hover';
        
        $html .= html_writer::table($table);
        
        // Add pagination HTML with data attributes for easier handling
        $baseurl = new moodle_url('/blocks/report_customcajasan/report.php', [
            'categoryid' => $filters['category'],
            'courseid' => $filters['course'],
            'idnumber' => $filters['idnumber'],
            'firstname' => $filters['firstname'],
            'lastname' => $filters['lastname'],
            'estado' => $filters['estado'],
            'startdate' => $filters['startdate'] ? date('Y-m-d', $filters['startdate']) : '',
            'enddate' => $filters['enddate'] ? date('Y-m-d', $filters['enddate']) : ''
        ]);
        
        $html .= custom_paging_bar($totalcount, $page, $perpage, $baseurl);
    }
    
    // JSON response
    $response = array(
        'success' => true,
        'html' => $html,
        'count' => $totalcount
    );
    
    // Send JSON response
    echo json_encode($response);
    
} catch (Exception $e) {
    $error = array(
        'success' => false,
        'error' => 'Error processing data: ' . $e->getMessage()
    );
    echo json_encode($error);
}