/**
 * JavaScript for the report_customcajasan report
 *
 * @module     block_report_customcajasan/report
 * @copyright  2025 Cajasan
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/notification', 'core/str'], function($, Notification, Str) {
    // Store the current page number
    var currentPage = 0;

    /**
     * Load report data via AJAX
     */
    function loadReportData() {
        // Show loading indicator
        $('#report-results').addClass('loading');

        // Get filter values
        var formData = $('#report-form').serialize();
        formData += '&page=' + currentPage;
        formData += '&sesskey=' + M.cfg.sesskey;

        // Make AJAX request
        $.ajax({
            url: M.cfg.wwwroot + '/blocks/report_customcajasan/ajax/get_report_data.php',
            type: 'GET',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response && response.success) {
                    // Update report content
                    $('#report-results').html(response.html);

                    // Re-initialize pagination and other dynamic elements
                    initializeDynamicElements();

                    // Colorize status cells
                    colorizeStatusCells();
                } else {
                    // Show error using Moodle's notification API instead of console
                    Str.get_string('ajax_error', 'block_report_customcajasan')
                        .then(function(errorString) {
                            $('#report-results').html(
                                '<div class="alert alert-danger">' + errorString + '</div>'
                            );
                            return;
                        })
                        .catch(Notification.exception);
                }
            },
            error: function(xhr, status, error) {
                // Show error using Moodle's notification API instead of console
                Str.get_string('ajax_error_detail', 'block_report_customcajasan')
                    .then(function(errorString) {
                        $('#report-results').html(
                            '<div class="alert alert-danger">' + errorString + ': ' + status + '</div>'
                        );
                        return;
                    })
                    .catch(Notification.exception);
            },
            complete: function() {
                // Hide loading indicator
                $('#report-results').removeClass('loading');
            }
        });
    }

    /**
     * Initialize dynamic elements like pagination
     */
    function initializeDynamicElements() {
        // Handle pagination clicks
        $('.pagination .page-link').on('click', function(e) {
            e.preventDefault();
            var page = $(this).data('page');

            if (page !== undefined) {
                currentPage = page;
                loadReportData();
            }
        });
    }

    /**
     * Apply colors to status cells based on status values
     */
    function colorizeStatusCells() {
        // Status cells are at column index 9 (0-based)
        $('#enrollment-report-table tbody tr').each(function() {
            var statusCell = $(this).find('td:eq(9)'); // Estado is at column 9
            var statusText = statusCell.text().trim();

            if (statusText === 'COMPLETO') {
                statusCell.addClass('bg-success text-white');
            } else if (statusText === 'EN PROGRESO') {
                statusCell.addClass('bg-warning');
            } else if (statusText === 'FINALIZADO') {
                statusCell.addClass('bg-info text-white');
            } else if (statusText === 'CONSULTA') {
                statusCell.addClass('bg-secondary text-white');
            }
        });
    }

    /**
     * Initialize the module
     */
    function init() {
        // Handle category change - update courses
        $('#categoryid').on('change', function() {
            var categoryId = $(this).val();

            // Clear course selection
            $('#courseid').empty();
            
            // Obtener el texto de "Todos" directamente
            var allText = M.util.get_string('option_all', 'block_report_customcajasan');
            $('#courseid').append($('<option>', {
                value: '',
                text: allText
            }));

            if (categoryId) {
                // Get courses for the selected category
                $.ajax({
                    url: M.cfg.wwwroot + '/blocks/report_customcajasan/ajax/get_courses.php',
                    type: 'GET',
                    data: {
                        'categoryid': categoryId,
                        'sesskey': M.cfg.sesskey
                    },
                    dataType: 'json',
                    success: function(data) {
                        if (data.success && data.courses) {
                            $.each(data.courses, function(index, course) {
                                $('#courseid').append($('<option>', {
                                    value: course.id,
                                    text: course.fullname
                                }));
                            });
                        }
                        // Reset to page 0 and load data
                        currentPage = 0;
                        loadReportData();
                    },
                    error: function() {
                        // Show error using Moodle's notification instead of console
                        Notification.exception({message: 'Error loading courses'});
                        // Reset to page 0 and load data anyway
                        currentPage = 0;
                        loadReportData();
                    }
                });
            } else {
                // If no category selected, reset to page 0 and load data
                currentPage = 0;
                loadReportData();
            }
        });

        // Alphabet filter functionality
        $('.alphabet-filter a').on('click', function(e) {
            e.preventDefault();
            var letter = $(this).data('letter');
            var target = $(this).data('target');

            // Update the hidden input with the selected letter
            $('#' + target).val(letter);

            // Update the UI to show active letter
            $(this).closest('.alphabet-filter').find('a').removeClass('active');
            $(this).addClass('active');

            // Reset to page 0 and load data
            currentPage = 0;
            loadReportData();
        });

        // Handle filter form submission
        $('#report-form').on('submit', function(e) {
            e.preventDefault();
            currentPage = 0;
            loadReportData();
        });

        // Handle filter changes
        $('#estado, #courseid').on('change', function() {
            currentPage = 0;
            loadReportData();
        });

        // Handle date changes
        $('#startdate, #enddate').on('change', function() {
            currentPage = 0;
            loadReportData();
        });

        // Handle idnumber input with debounce
        var idnumberTimer;
        $('#idnumber').on('input', function() {
            clearTimeout(idnumberTimer);
            idnumberTimer = setTimeout(function() {
                currentPage = 0;
                loadReportData();
            }, 500); // 500ms delay
        });

        // Initial load if filters are set
        if ($('#report-results').length) {
            var hasFilters = $('#categoryid').val() || $('#courseid').val() || $('#estado').val() ||
                            $('#idnumber').val() || $('#firstname').val() || $('#lastname').val() ||
                            $('#startdate').val() || $('#enddate').val();

            if (hasFilters) {
                loadReportData();
            }
        }

        // Initialize any existing dynamic elements
        initializeDynamicElements();
    }

    return {
        init: init
    };
});