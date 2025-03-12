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
 * Library functions for report_customcajasan
 *
 * @package    block_report_customcajasan
 * @copyright  2025 Cajasan
 * @author     Pedro Arias <soporte@ingeweb.co>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/excellib.class.php');
require_once($CFG->libdir . '/odslib.class.php');
require_once($CFG->libdir . '/csvlib.class.php');

/**
 * Get enrollment data based on filters with pagination support
 *
 * @param array $filters Filter parameters
 * @param int $limitfrom Starting point for records (null for all records)
 * @param int $limitnum Maximum number of records (null for all records)
 * @param bool $count_only If true, returns only the count of records
 * @return array|int Enrollment data or record count
 */
function report_customcajasan_get_data($filters, $limitfrom = null, $limitnum = null, $count_only = false) {
    global $DB;
    
    // Check for required tables
    $dbman = $DB->get_manager();
    $cert_table_exists = $dbman->table_exists('customcert_issues');
    $completion_table_exists = $dbman->table_exists('course_completions');
    
    // Cache key for this query - helps with performance when same filters are used repeatedly
    $cache_key = 'report_cajasan_' . md5(serialize($filters) . 
                 ($limitfrom !== null ? $limitfrom : '') . 
                 ($limitnum !== null ? $limitnum : '') . 
                 ($count_only ? 'count' : 'data'));
    
    // For count query, we need a much simpler query
    if ($count_only) {
        return report_customcajasan_count_data($filters);
    }
    
    // Base query with core information - reorganizado para mejor legibilidad
    $sql = "SELECT 
                CONCAT(u.id, '_', c.id) AS unique_id,
                u.id AS userid,
                c.id AS courseid,
                u.idnumber AS identificacion,
                u.firstname AS nombres,
                u.lastname AS apellidos,
                u.email AS correo,
                c.fullname AS curso,
                cat.name AS categoria,
                u.department AS unidad,
                FROM_UNIXTIME(ue.timestart) AS fecha_matricula";
    
    // Add last access to course
    $sql .= ", (SELECT 
                  FROM_UNIXTIME(MAX(timeaccess)) 
                FROM {user_lastaccess} 
                WHERE userid = u.id AND courseid = c.id) AS ultimo_acceso";
    
    // Certificate columns - only include these JOINs if needed
    $need_cert_joins = $cert_table_exists && 
                     (!isset($filters['estado']) || 
                      in_array($filters['estado'], ['', 'APROBADO']));
    
    if ($cert_table_exists) {
        if ($need_cert_joins) {
            $sql .= ",
                    CASE WHEN cert.id IS NOT NULL THEN 1 ELSE 0 END AS has_certificate,
                    CASE WHEN ci.timecreated IS NOT NULL THEN FROM_UNIXTIME(ci.timecreated) ELSE NULL END AS fecha_certificado";
        } else {
            $sql .= ",
                    0 AS has_certificate,
                    NULL AS fecha_certificado";
        }
    } else {
        $sql .= ",
                0 AS has_certificate,
                NULL AS fecha_certificado";
    }
    
    // Status determination with updated status values - prioridad corregida
    $sql .= ",
            CASE 
                WHEN (cert.id IS NULL) THEN 'SOLO CONSULTA'
                WHEN (ci.id IS NOT NULL OR cc.timecompleted IS NOT NULL) THEN 'APROBADO'
                WHEN (l.id IS NOT NULL OR cc.timestarted IS NOT NULL) THEN 'EN CURSO'
                WHEN (l.id IS NULL AND (cc.timestarted IS NULL OR cc.timestarted = 0)) THEN 'NO INICIADO'
                ELSE 'NO INICIADO'
            END AS estado";
    
    // FROM clause with required tables - only include the essential joins initially
    $sql .= "
            FROM {user_enrolments} ue
            JOIN {enrol} e ON ue.enrolid = e.id
            JOIN {course} c ON e.courseid = c.id
            JOIN {course_categories} cat ON c.category = cat.id
            JOIN {user} u ON ue.userid = u.id";
    
    // Only include log table if needed (for EN CURSO status)
    $need_log_join = !isset($filters['estado']) || $filters['estado'] === '' || $filters['estado'] === 'EN CURSO';
    if ($need_log_join) {
        $sql .= " LEFT JOIN {logstore_standard_log} l ON l.userid = u.id AND l.courseid = c.id";
    } else {
        // Add a placeholder to keep the query structure consistent
        $sql .= " LEFT JOIN (SELECT NULL AS id, NULL AS userid, NULL AS courseid) l ON l.userid = u.id AND l.courseid = c.id";
    }
    
    // Only include course modules if needed
    $need_cm_join = true; // Always needed for status determination
    if ($need_cm_join) {
        $sql .= " LEFT JOIN (
                    SELECT course, COUNT(*) as has_completion
                    FROM {course_modules}
                    WHERE completion > 0
                    GROUP BY course
                ) hc ON hc.course = c.id
                LEFT JOIN {course_modules} cm ON cm.course = c.id";
    }
    
    // Add conditional JOINs only if tables exist and needed
    if ($completion_table_exists) {
        $sql .= " LEFT JOIN {course_completions} cc ON cc.userid = u.id AND cc.course = c.id";
    } else {
        // Use a lightweight join that won't return data but keeps the query structure intact
        $sql .= " LEFT JOIN (SELECT NULL AS timecompleted, NULL AS timestarted, NULL AS userid, NULL AS course) cc ON cc.userid = u.id AND cc.course = c.id";
    }
    
    if ($cert_table_exists && $need_cert_joins) {
        $sql .= " LEFT JOIN {customcert} cert ON cert.course = c.id";
        $sql .= " LEFT JOIN {customcert_issues} ci ON ci.userid = u.id AND ci.customcertid = cert.id";
    } else if ($cert_table_exists) {
        // Use lightweight joins
        $sql .= " LEFT JOIN (SELECT NULL AS id, NULL AS course) cert ON cert.course = c.id";
        $sql .= " LEFT JOIN (SELECT NULL AS id, NULL AS userid, NULL AS customcertid, NULL AS timecreated) ci ON ci.userid = u.id AND ci.customcertid = cert.id";
    }
    
    // Base WHERE clause
    $sql .= " WHERE u.deleted = 0";
    
    // Parameter collection
    $params = array();
    
    // Apply filters using clean, consistent pattern
    if (!empty($filters['category'])) {
        $sql .= " AND c.category = :category";
        $params['category'] = $filters['category'];
    }
    
    if (!empty($filters['course'])) {
        $sql .= " AND c.id = :course";
        $params['course'] = $filters['course'];
    }
    
    if (!empty($filters['idnumber'])) {
        $sql .= " AND u.idnumber LIKE :idnumber";
        $params['idnumber'] = '%' . $filters['idnumber'] . '%';
    }
    
    if (!empty($filters['firstname'])) {
        $sql .= " AND u.firstname LIKE :firstname";
        $params['firstname'] = $filters['firstname'] . '%';
    }
    
    if (!empty($filters['lastname'])) {
        $sql .= " AND u.lastname LIKE :lastname";
        $params['lastname'] = $filters['lastname'] . '%';
    }
    
    // Status filter with updated values
    if (!empty($filters['estado'])) {
        if ($filters['estado'] === 'APROBADO') {
            $sql .= " AND cert.id IS NOT NULL AND (ci.id IS NOT NULL OR cc.timecompleted IS NOT NULL)";
        } else if ($filters['estado'] === 'EN CURSO') {
            $sql .= " AND cert.id IS NOT NULL AND (l.id IS NOT NULL OR cc.timestarted IS NOT NULL) AND cc.timecompleted IS NULL AND ci.id IS NULL";
        } else if ($filters['estado'] === 'NO INICIADO') {
            $sql .= " AND cert.id IS NOT NULL AND l.id IS NULL AND (cc.timestarted IS NULL OR cc.timestarted = 0) AND cc.timecompleted IS NULL AND ci.id IS NULL";
        } else if ($filters['estado'] === 'SOLO CONSULTA') {
            if ($cert_table_exists) {
                $sql .= " AND cert.id IS NULL";
            }
        }
    }
    
    // Date range filters
    if (!empty($filters['startdate'])) {
        $sql .= " AND ue.timestart >= :startdate";
        $params['startdate'] = $filters['startdate'];
    }
    
    if (!empty($filters['enddate'])) {
        $sql .= " AND ue.timestart <= :enddate";
        $params['enddate'] = $filters['enddate'];
    }
    
    // Use simplified grouping when possible - this improves query performance
    $sql .= " GROUP BY u.id, c.id, cat.name";
    if ($cert_table_exists && $need_cert_joins) {
        $sql .= ", cert.id, ci.id, ci.timecreated";
    }
    if ($completion_table_exists) {
        $sql .= ", cc.timecompleted, cc.timestarted";
    }
    
    $sql .= " ORDER BY u.lastname, u.firstname, c.fullname";
    
    // Get results with pagination if specified
    $result = $DB->get_records_sql($sql, $params, $limitfrom, $limitnum);
    
    return $result;
}

/**
 * Get count of enrollment data based on filters - optimized count query
 * 
 * @param array $filters Filter parameters
 * @return int Count of matching records
 */
function report_customcajasan_count_data($filters) {
    global $DB;
    
    // Check for required tables
    $dbman = $DB->get_manager();
    $cert_table_exists = $dbman->table_exists('customcert_issues');
    $completion_table_exists = $dbman->table_exists('course_completions');
    
    // For count, use a much simpler query with COUNT(DISTINCT) to optimize performance
    $sql = "SELECT COUNT(DISTINCT CONCAT(u.id, '_', c.id)) 
            FROM {user_enrolments} ue
            JOIN {enrol} e ON ue.enrolid = e.id
            JOIN {course} c ON e.courseid = c.id
            JOIN {course_categories} cat ON c.category = cat.id
            JOIN {user} u ON ue.userid = u.id";
    
    // Only include these JOINs if needed for filters
    $need_cert_joins = $cert_table_exists && 
                      (!empty($filters['estado']) && 
                       in_array($filters['estado'], ['APROBADO', 'EN CURSO', 'NO INICIADO', 'SOLO CONSULTA']));
                      
    $need_completion_joins = $completion_table_exists && 
                            (!empty($filters['estado']) && 
                             in_array($filters['estado'], ['APROBADO', 'EN CURSO', 'NO INICIADO']));
    
    $need_log_join = empty($filters['estado']) || $filters['estado'] === 'EN CURSO' || $filters['estado'] === 'NO INICIADO';
    
    // Add minimal joins based on what's needed for the filters
    if ($need_log_join) {
        $sql .= " LEFT JOIN {logstore_standard_log} l ON l.userid = u.id AND l.courseid = c.id";
    }
    
    if ($need_completion_joins) {
        $sql .= " LEFT JOIN {course_completions} cc ON cc.userid = u.id AND cc.course = c.id";
    }
    
    if ($need_cert_joins) {
        $sql .= " LEFT JOIN {customcert} cert ON cert.course = c.id
                 LEFT JOIN {customcert_issues} ci ON ci.userid = u.id AND ci.customcertid = cert.id";
    }
    
    // Base WHERE clause
    $sql .= " WHERE u.deleted = 0";
    
    // Add the same filters as the main query
    $params = array();
    
    if (!empty($filters['category'])) {
        $sql .= " AND c.category = :category";
        $params['category'] = $filters['category'];
    }
    
    if (!empty($filters['course'])) {
        $sql .= " AND c.id = :course";
        $params['course'] = $filters['course'];
    }
    
    if (!empty($filters['idnumber'])) {
        $sql .= " AND u.idnumber LIKE :idnumber";
        $params['idnumber'] = '%' . $filters['idnumber'] . '%';
    }
    
    if (!empty($filters['firstname'])) {
        $sql .= " AND u.firstname LIKE :firstname";
        $params['firstname'] = $filters['firstname'] . '%';
    }
    
    if (!empty($filters['lastname'])) {
        $sql .= " AND u.lastname LIKE :lastname";
        $params['lastname'] = $filters['lastname'] . '%';
    }
    
    // Status filter - updated with new status values and corrected logic
    if (!empty($filters['estado'])) {
        if ($filters['estado'] === 'APROBADO') {
            $sql .= " AND cert.id IS NOT NULL AND (ci.id IS NOT NULL OR cc.timecompleted IS NOT NULL)";
        } else if ($filters['estado'] === 'EN CURSO') {
            $sql .= " AND cert.id IS NOT NULL AND (l.id IS NOT NULL OR cc.timestarted IS NOT NULL)";
            if ($need_completion_joins) {
                $sql .= " AND cc.timecompleted IS NULL";
            }
            if ($need_cert_joins) {
                $sql .= " AND ci.id IS NULL";
            }
        } else if ($filters['estado'] === 'NO INICIADO') {
            $sql .= " AND cert.id IS NOT NULL AND l.id IS NULL";
            if ($need_completion_joins) {
                $sql .= " AND (cc.timestarted IS NULL OR cc.timestarted = 0) AND cc.timecompleted IS NULL";
            }
            if ($need_cert_joins) {
                $sql .= " AND ci.id IS NULL";
            }
        } else if ($filters['estado'] === 'SOLO CONSULTA') {
            if ($cert_table_exists) {
                $sql .= " AND cert.id IS NULL";
            }
        }
    }
    
    // Date range filters
    if (!empty($filters['startdate'])) {
        $sql .= " AND ue.timestart >= :startdate";
        $params['startdate'] = $filters['startdate'];
    }
    
    if (!empty($filters['enddate'])) {
        $sql .= " AND ue.timestart <= :enddate";
        $params['enddate'] = $filters['enddate'];
    }
    
    // Single count query is much faster than retrieving all records
    $count = $DB->count_records_sql($sql, $params);
    
    return $count;
}

/**
 * Get the list of available states for filtering - updated states
 *
 * @return array List of states
 */
function report_customcajasan_get_states() {
    return [
        '' => get_string('option_all', 'block_report_customcajasan'),
        'APROBADO' => get_string('state_aprobado', 'block_report_customcajasan'),
        'EN CURSO' => get_string('state_encurso', 'block_report_customcajasan'),
        'NO INICIADO' => get_string('state_noiniciado', 'block_report_customcajasan'),
        'SOLO CONSULTA' => get_string('state_soloconsulta', 'block_report_customcajasan')
    ];
}

/**
 * Export data to .xlsx or .ods format.
 *
 * @param array $headers The headers for the data.
 * @param array $data The rows of data.
 * @param string $filename The base name of the file without extension.
 * @param string $format The format of the export: 'excel' or 'ods'.
 * @param string $sheetname The name of the worksheet.
 */
function report_customcajasan_export_spreadsheet($headers, $data, $filename, $format, $sheetname = 'Sheet1')
{
    global $CFG;

    // Clear output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }

    if ($format === 'excel') {
        $workbook = new MoodleExcelWorkbook("-");
        $filename .= '.xlsx';
    } else {
        $workbook = new MoodleODSWorkbook("-");
        $filename .= '.ods';
    }

    $workbook->send($filename);
    $worksheet = $workbook->add_worksheet($sheetname);

    // Set column widths - updated order
    $worksheet->set_column(0, 0, 15);  // Identificación
    $worksheet->set_column(1, 1, 20);  // Nombres
    $worksheet->set_column(2, 2, 20);  // Apellidos
    $worksheet->set_column(3, 3, 30);  // Correo
    $worksheet->set_column(4, 4, 40);  // Curso
    $worksheet->set_column(5, 5, 30);  // Categoría
    $worksheet->set_column(6, 6, 20);  // Unidad
    $worksheet->set_column(7, 7, 20);  // Fecha de matricula
    $worksheet->set_column(8, 8, 20);  // Último acceso al curso
    $worksheet->set_column(9, 9, 20);  // Fecha de emisión certificado
    $worksheet->set_column(10, 10, 15);  // Estado

    // Header format
    $headerformat = $workbook->add_format();
    $headerformat->set_bold();
    $headerformat->set_align('center');
    $headerformat->set_align('vcenter');
    $headerformat->set_bg_color('#CCCCCC');

    // Write headers
    for ($i = 0; $i < count($headers); $i++) {
        $worksheet->write(0, $i, $headers[$i], $headerformat);
    }

    // Write data
    $row = 1;
    foreach ($data as $record) {
        $col = 0;
        foreach ($record as $value) {
            $worksheet->write($row, $col++, $value);
        }
        $row++;
    }

    $workbook->close();
    exit;
}

/**
 * Export data to CSV format.
 *
 * @param array $headers The headers for the data.
 * @param array $data The rows of data.
 * @param string $filename The base name of the file without extension.
 */
function report_customcajasan_export_csv($headers, $data, $filename) {
    // Clear output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }

    $filename .= '.csv';
    
    header("Content-Type: text/csv; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Expires: 0");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("Pragma: public");

    $output = fopen('php://output', 'w');
    
    // Write headers
    fputcsv($output, $headers);
    
    // Write data
    foreach ($data as $record) {
        fputcsv($output, $record);
    }
    
    fclose($output);
    exit;
}

/**
 * Get all course categories
 *
 * @return array List of categories
 */
function report_customcajasan_get_categories() {
    global $DB;
    
    return $DB->get_records('course_categories', null, 'name ASC', 'id, name, depth, path');
}

/**
 * Get courses by category
 *
 * @param int $categoryid Category ID
 * @return array List of courses
 */
function report_customcajasan_get_courses($categoryid) {
    global $DB;
    
    $params = array();
    $sql = "SELECT id, fullname FROM {course} WHERE 1=1";
    
    if (!empty($categoryid)) {
        $sql .= " AND category = :category";
        $params['category'] = $categoryid;
    }
    
    $sql .= " ORDER BY fullname ASC";
    
    return $DB->get_records_sql($sql, $params);
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