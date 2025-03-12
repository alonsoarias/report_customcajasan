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
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/excellib.class.php');
require_once($CFG->libdir . '/odslib.class.php');
require_once($CFG->libdir . '/csvlib.class.php');

/**
 * Get enrollment data based on filters
 *
 * @param array $filters Filter parameters
 * @return array Enrollment data
 */
function report_customcajasan_get_data($filters) {
    global $DB;

    // Primero verificamos si la tabla customcert_issues existe
    $dbman = $DB->get_manager();
    $table_exists = $dbman->table_exists('customcert_issues');
    
    if ($table_exists) {
        // Verificamos la estructura de la tabla para saber qué columna usar
        $columns = $DB->get_columns('customcert_issues');
        $course_column = '';
        
        // Buscamos la columna que representa el curso
        if (isset($columns['course'])) {
            $course_column = 'course';
        } else if (isset($columns['courseid'])) {
            $course_column = 'courseid';
        } else {
            // Si no existe ninguna columna para el curso, no usaremos JOIN con customcert_issues
            $table_exists = false;
        }
    }

    // IMPORTANTE: Crear un ID único combinando usuario y curso
    $sql = "SELECT 
                CONCAT(u.id, '_', c.id) AS unique_id,  -- ID único compuesto
                u.id AS userid,
                u.idnumber AS identificacion,
                CONCAT(u.firstname, ' ', u.lastname) AS nombre,
                u.email AS correo,
                u.department AS unidad,
                c.fullname AS curso,
                FROM_UNIXTIME(ue.timestart) AS fecha_matricula,";
    
    if ($table_exists && !empty($course_column)) {
        // Si la tabla existe y encontramos la columna para el curso, utilizamos JOIN
        $sql .= "CASE
                    WHEN ci.timecreated IS NOT NULL THEN FROM_UNIXTIME(ci.timecreated)
                    ELSE 'No emitido'
                END AS fecha_finalizacion,";
    } else {
        // Si no existe la tabla o no encontramos la columna, usamos un valor fijo
        $sql .= "'No emitido' AS fecha_finalizacion,";
    }
    
    $sql .= "CASE 
                    WHEN ue.status = 0 THEN 'Activo'
                    WHEN ue.status = 1 THEN 'Suspendido'
                    ELSE 'Desconocido'
                END AS estado,
                cc.name AS categoria
            FROM {user_enrolments} ue
            JOIN {enrol} e ON ue.enrolid = e.id
            JOIN {course} c ON e.courseid = c.id
            JOIN {course_categories} cc ON c.category = cc.id
            JOIN {user} u ON ue.userid = u.id";
    
    if ($table_exists && !empty($course_column)) {
        // Si la tabla existe y encontramos la columna, hacemos el LEFT JOIN
        $sql .= " LEFT JOIN {customcert_issues} ci ON ci.userid = u.id AND ci.{$course_column} = c.id";
    }
    
    $sql .= " WHERE u.deleted = 0";
    
    $params = array();
    
    // Apply category filter
    if (!empty($filters['category'])) {
        $sql .= " AND c.category = :category";
        $params['category'] = $filters['category'];
    }
    
    // Apply course filter
    if (!empty($filters['course'])) {
        $sql .= " AND c.id = :course";
        $params['course'] = $filters['course'];
    }
    
    // Apply identification filter
    if (!empty($filters['idnumber'])) {
        $sql .= " AND u.idnumber LIKE :idnumber";
        $params['idnumber'] = '%' . $filters['idnumber'] . '%';
    }
    
    // Apply date filters
    if (!empty($filters['startdate'])) {
        $sql .= " AND ue.timestart >= :startdate";
        $params['startdate'] = $filters['startdate'];
    }
    
    if (!empty($filters['enddate'])) {
        $sql .= " AND ue.timestart <= :enddate";
        $params['enddate'] = $filters['enddate'];
    }
    
    // Order by
    $sql .= " ORDER BY u.lastname, u.firstname, c.fullname";
    
    return $DB->get_records_sql($sql, $params);
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

    // Set column widths
    $worksheet->set_column(0, 0, 15);  // Identificación
    $worksheet->set_column(1, 1, 30);  // Nombre
    $worksheet->set_column(2, 2, 30);  // Correo
    $worksheet->set_column(3, 3, 20);  // Unidad
    $worksheet->set_column(4, 4, 40);  // Curso
    $worksheet->set_column(5, 5, 20);  // Fecha de matricula
    $worksheet->set_column(6, 6, 20);  // Fecha de emisión del certificado
    $worksheet->set_column(7, 7, 15);  // Estado
    $worksheet->set_column(8, 8, 30);  // Categoría

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