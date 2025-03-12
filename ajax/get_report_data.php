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
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/blocks/report_customcajasan/lib.php');

// Check user login
require_login(null, false);

// Verificar sesskey
if (!confirm_sesskey()) {
    $error = array(
        'success' => false,
        'error' => 'Invalid session key'
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
        'error' => 'Permission denied: ' . $e->getMessage()
    );
    echo json_encode($error);
    die();
}

// Get filter parameters
$categoryid = optional_param('categoryid', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$idnumber = optional_param('idnumber', '', PARAM_TEXT);
$firstname = optional_param('firstname', '', PARAM_TEXT);
$lastname = optional_param('lastname', '', PARAM_TEXT);
$estado = optional_param('estado', '', PARAM_TEXT);
$startdate = optional_param('startdate', '', PARAM_TEXT);
$enddate = optional_param('enddate', '', PARAM_TEXT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 100, PARAM_INT);

// Prepare filter parameters
$filters = array();
if (!empty($categoryid)) {
    $filters['category'] = $categoryid;
}
if (!empty($courseid)) {
    $filters['course'] = $courseid;
}
if (!empty($idnumber)) {
    $filters['idnumber'] = $idnumber;
}
if (!empty($firstname)) {
    $filters['firstname'] = $firstname;
}
if (!empty($lastname)) {
    $filters['lastname'] = $lastname;
}
if (!empty($estado)) {
    $filters['estado'] = $estado;
}
if (!empty($startdate)) {
    $filters['startdate'] = strtotime($startdate);
}
if (!empty($enddate)) {
    $filters['enddate'] = strtotime($enddate . ' 23:59:59');
}

try {
    // Get all data for counting
    $all_enrollments = report_customcajasan_get_data($filters);
    $totalcount = count($all_enrollments);
    
    // Apply pagination
    $start = $page * $perpage;
    $enrollments = array_slice($all_enrollments, $start, $perpage);
    
    // Prepare HTML table
    $html = '';
    if (empty($enrollments)) {
        $html = html_writer::tag('div', get_string('no_data', 'block_report_customcajasan'), array('class' => 'alert alert-info'));
    } else {
        $html .= html_writer::tag('div', get_string('total_records', 'block_report_customcajasan') . ': ' . $totalcount, array('class' => 'font-weight-bold mb-2'));
        
        // Table
        $table = new html_table();
        $table->head = array(
            get_string('column_identificacion', 'block_report_customcajasan'),
            get_string('column_nombres', 'block_report_customcajasan'),
            get_string('column_apellidos', 'block_report_customcajasan'),
            get_string('column_correo', 'block_report_customcajasan'),
            get_string('column_unidad', 'block_report_customcajasan'),
            get_string('column_curso', 'block_report_customcajasan'),
            get_string('column_fecha_matricula', 'block_report_customcajasan'),
            get_string('column_fecha_certificado', 'block_report_customcajasan'),
            get_string('column_fecha_finalizacion', 'block_report_customcajasan'),
            get_string('column_estado', 'block_report_customcajasan'),
            get_string('column_categoria', 'block_report_customcajasan')
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
            
            // Add row with link
            $table->data[] = array(
                $enrollment->identificacion,
                $enrollment->nombres,
                $enrollment->apellidos,
                $enrollment->correo,
                $enrollment->unidad,
                $curso_link, // Link to course instead of just name
                $enrollment->fecha_matricula,
                $enrollment->fecha_certificado, 
                $enrollment->fecha_finalizacion,
                $enrollment->estado,
                $enrollment->categoria
            );
        }
        
        $table->id = 'enrollment-report-table';
        $table->attributes['class'] = 'table table-striped table-bordered table-hover';
        
        $html .= html_writer::table($table);
        
        // Add pagination HTML
        $baseurl = new moodle_url('/blocks/report_customcajasan/report.php', [
            'categoryid' => $categoryid,
            'courseid' => $courseid,
            'idnumber' => $idnumber,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'estado' => $estado,
            'startdate' => $startdate,
            'enddate' => $enddate
        ]);
        
        // Using a custom function to create pagination HTML
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

/**
 * Custom function to generate pagination bar HTML
 * 
 * @param int $totalcount Total number of items
 * @param int $page Current page number
 * @param int $perpage Number of items per page
 * @param moodle_url $baseurl Base URL for pagination links
 * @return string HTML for pagination bar
 */
function custom_paging_bar($totalcount, $page, $perpage, $baseurl) {
    if ($totalcount <= $perpage) {
        return '';
    }
    
    $output = '';
    $totalpage = ceil($totalcount / $perpage);
    
    $output .= html_writer::start_tag('nav', ['aria-label' => 'Page navigation']);
    $output .= html_writer::start_tag('ul', ['class' => 'pagination']);
    
    // Previous page link
    if ($page > 0) {
        $output .= html_writer::start_tag('li', ['class' => 'page-item']);
        $output .= html_writer::link('#', '«', ['class' => 'page-link', 'aria-label' => 'Previous', 'data-page' => ($page - 1)]);
        $output .= html_writer::end_tag('li');
    } else {
        $output .= html_writer::start_tag('li', ['class' => 'page-item disabled']);
        $output .= html_writer::tag('span', '«', ['class' => 'page-link']);
        $output .= html_writer::end_tag('li');
    }
    
    // Page numbers
    $startpage = max(0, $page - 4);
    $endpage = min($totalpage - 1, $page + 4);
    
    if ($startpage > 0) {
        $output .= html_writer::start_tag('li', ['class' => 'page-item']);
        $output .= html_writer::link('#', '1', ['class' => 'page-link', 'data-page' => 0]);
        $output .= html_writer::end_tag('li');
        
        if ($startpage > 1) {
            $output .= html_writer::start_tag('li', ['class' => 'page-item disabled']);
            $output .= html_writer::tag('span', '...', ['class' => 'page-link']);
            $output .= html_writer::end_tag('li');
        }
    }
    
    for ($i = $startpage; $i <= $endpage; $i++) {
        if ($i == $page) {
            $output .= html_writer::start_tag('li', ['class' => 'page-item active']);
            $output .= html_writer::tag('span', $i + 1, ['class' => 'page-link']);
            $output .= html_writer::end_tag('li');
        } else {
            $output .= html_writer::start_tag('li', ['class' => 'page-item']);
            $output .= html_writer::link('#', $i + 1, ['class' => 'page-link', 'data-page' => $i]);
            $output .= html_writer::end_tag('li');
        }
    }
    
    if ($endpage < $totalpage - 1) {
        if ($endpage < $totalpage - 2) {
            $output .= html_writer::start_tag('li', ['class' => 'page-item disabled']);
            $output .= html_writer::tag('span', '...', ['class' => 'page-link']);
            $output .= html_writer::end_tag('li');
        }
        
        $output .= html_writer::start_tag('li', ['class' => 'page-item']);
        $output .= html_writer::link('#', $totalpage, ['class' => 'page-link', 'data-page' => $totalpage - 1]);
        $output .= html_writer::end_tag('li');
    }
    
    // Next page link
    if ($page < $totalpage - 1) {
        $output .= html_writer::start_tag('li', ['class' => 'page-item']);
        $output .= html_writer::link('#', '»', ['class' => 'page-link', 'aria-label' => 'Next', 'data-page' => ($page + 1)]);
        $output .= html_writer::end_tag('li');
    } else {
        $output .= html_writer::start_tag('li', ['class' => 'page-item disabled']);
        $output .= html_writer::tag('span', '»', ['class' => 'page-link']);
        $output .= html_writer::end_tag('li');
    }
    
    $output .= html_writer::end_tag('ul');
    $output .= html_writer::end_tag('nav');
    
    return $output;
}