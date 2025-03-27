/**
 * JavaScript for the report_customcajasan report
 *
 * @module     block_report_customcajasan/report
 * @copyright  2025 Cajasan
 * @author     Pedro Arias <soporte@ingeweb.co>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/notification', 'core/str'], function($, Notification, Str) {
    // State management for current page and filters
    var state = {
        currentPage: 0,
        perPage: 100, // Default value
        filter: {}
    };

    /**
     * Update hidden fields in the download form with current filter values
     */
    function updateDownloadForm() {
        // Update each hidden field in the download form
        $('#downloadForm input[name="categoryid"]').val($('#categoryid').val());
        $('#downloadForm input[name="courseid"]').val($('#courseid').val());
        $('#downloadForm input[name="idnumber"]').val($('#idnumber').val());
        $('#downloadForm input[name="firstname"]').val($('#firstname').val());
        $('#downloadForm input[name="lastname"]').val($('#lastname').val());
        $('#downloadForm input[name="estado"]').val($('#estado').val());
        $('#downloadForm input[name="startdate"]').val($('#startdate').val());
        $('#downloadForm input[name="enddate"]').val($('#enddate').val());
    }

    /**
     * Load report data via AJAX with improved handling
     */
    function loadReportData() {
        // Show loading indicator
        $('#report-results').addClass('loading');

        // Get filter values
        var formData = $('#report-form').serialize();
        formData += '&page=' + state.currentPage;
        
        // Add perpage parameter if set
        if (state.perPage !== undefined) {
            formData += '&perpage=' + state.perPage;
        }
        
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
                    
                    // Store total count in state
                    state.totalCount = response.count;
                    
                    // Re-initialize pagination and other dynamic elements
                    initializeDynamicElements();
                    
                    // Apply visual enhancements
                    colorizeStatusCells();
                    
                    // Update download form with current filters
                    updateDownloadForm();
                } else {
                    // Show error with better message handling
                    var errorMsg = (response && response.error) ? response.error : 
                        M.util.get_string('ajax_error', 'block_report_customcajasan');
                    
                    $('#report-results').html(
                        '<div class="alert alert-danger">' + errorMsg + '</div>'
                    );
                }
            },
            error: function(xhr, status) {
                // Show detailed error information
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
        // Handle pagination with improved event delegation
        $(document).off('click', '.pagination .page-link').on('click', '.pagination .page-link', function(e) {
            e.preventDefault();
            var page = $(this).data('page');

            if (page !== undefined) {
                state.currentPage = page;
                loadReportData();
                
                // Scroll to top of results for better UX
                $('html, body').animate({
                    scrollTop: $('#report-results').offset().top - 20
                }, 200);
            }
        });
    }

    /**
     * Apply colors to status cells based on status values - updated for new status
     */
    function colorizeStatusCells() {
        // Status cells are at column index 10 (0-based) - updated index after removing fecha_finalizacion
        $('#enrollment-report-table tbody tr').each(function() {
            var statusCell = $(this).find('td:eq(10)'); // Estado is at column 10 now
            var statusText = statusCell.text().trim();

            // Clear previous classes
            statusCell.removeClass('bg-success bg-warning bg-info bg-secondary bg-danger text-white');

            // Add appropriate class based on status - updated status values
            if (statusText === 'APROBADO') {
                statusCell.addClass('bg-success text-white');
            } else if (statusText === 'EN CURSO') {
                statusCell.addClass('bg-warning');
            } else if (statusText === 'NO INICIADO') {
                statusCell.addClass('bg-danger text-white');
            } else if (statusText === 'SOLO CONSULTA') {
                statusCell.addClass('bg-secondary text-white');
            }
        });
    }

    /**
     * Initialize the module with improved filter handling
     */
    function init() {
        // Handle category change - update courses pero NO cargar datos automáticamente
        $('#categoryid').on('change', function() {
            var categoryId = $(this).val();
            state.filter.category = categoryId;
            state.currentPage = 0;

            // Clear course selection
            $('#courseid').empty();
            
            // Add default "All" option
            Str.get_string('option_all', 'block_report_customcajasan')
                .then(function(allText) {
                    $('#courseid').append($('<option>', {
                        value: '',
                        text: allText
                    }));
                })
                .catch(function() {
                    // Fallback si falla la carga del string
                    $('#courseid').append($('<option>', {
                        value: '',
                        text: 'All'
                    }));
                });

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
                        // Solo actualizar formulario de descarga, NO cargar datos
                        updateDownloadForm();
                    },
                    error: function(xhr, status) {
                        // Show error using Moodle's notification API
                        Notification.exception({message: 'Error loading courses: ' + status});
                        // Solo actualizar formulario de descarga, NO cargar datos
                        updateDownloadForm();
                    }
                });
            } else {
                // Solo actualizar formulario de descarga, NO cargar datos
                updateDownloadForm();
            }
        });

        // Alphabet filter functionality with improved handling (mantener autoload)
        $('.alphabet-filter a').on('click', function(e) {
            e.preventDefault();
            var letter = $(this).data('letter');
            var target = $(this).data('target');

            // Store in state
            state.filter[target] = letter;
            
            // Update the hidden input with the selected letter
            $('#' + target).val(letter);

            // Update the UI to show active letter
            $(this).closest('.alphabet-filter').find('a').removeClass('active');
            $(this).addClass('active');

            // Reset to page 0 and load data (mantener autoload)
            state.currentPage = 0;
            loadReportData();
            updateDownloadForm();
        });

        // Handle filter form submission - siempre cargar al enviar formulario
        $('#report-form').on('submit', function(e) {
            e.preventDefault();
            state.currentPage = 0;
            loadReportData();
            updateDownloadForm();
        });

        // Handle selectbox changes - solo actualizar estado, NO cargar datos
        $('#estado, #courseid').on('change', function() {
            var id = $(this).attr('id');
            state.filter[id] = $(this).val();
            state.currentPage = 0;
            // Solo actualizar formulario de descarga, NO cargar datos
            updateDownloadForm();
        });

        // Handle date changes (mantener autoload)
        $('#startdate, #enddate').on('change', function() {
            var id = $(this).attr('id');
            state.filter[id] = $(this).val();
            state.currentPage = 0;
            loadReportData();
            updateDownloadForm();
        });

        // Handle idnumber input with debounce (mantener autoload)
        var idnumberTimer;
        $('#idnumber').on('input', function() {
            clearTimeout(idnumberTimer);
            idnumberTimer = setTimeout(function() {
                state.filter.idnumber = $('#idnumber').val();
                state.currentPage = 0;
                loadReportData();
                updateDownloadForm();
            }, 500); // 500ms debounce delay
        });
        
        // Handle per page change
        $('#perpage').on('change', function() {
            var perpage = parseInt($(this).val());
            state.perPage = perpage; // Store in state
            state.currentPage = 0; // Reset to first page when changing page size
            loadReportData();
        });

        // Initialize download button to update form fields before submission
        $('#downloadForm').on('submit', function() {
            // Update form with current filter values right before submission
            updateDownloadForm();
            
            // Opcional: Verificar que al menos un filtro esté aplicado
            var hasDownloadFilters = $('#downloadForm input[name="categoryid"]').val() || 
                             $('#downloadForm input[name="courseid"]').val() || 
                             $('#downloadForm input[name="estado"]').val() ||
                             $('#downloadForm input[name="idnumber"]').val() || 
                             $('#downloadForm input[name="firstname"]').val() || 
                             $('#downloadForm input[name="lastname"]').val() ||
                             $('#downloadForm input[name="startdate"]').val() || 
                             $('#downloadForm input[name="enddate"]').val();
            
            if (!hasDownloadFilters) {
                // Mostrar una notificación si no hay filtros seleccionados
                Notification.alert(
                    '',
                    M.util.get_string('filters_required', 'block_report_customcajasan')
                );
                return false; // Evitar la descarga sin filtros
            }
            
            return true; // Permitir que continúe el envío del formulario
        });

        // Initial load if filters are set
        if ($('#report-results').length) {
            // Initialize state from current form values
            state.filter = {
                category: $('#categoryid').val(),
                course: $('#courseid').val(),
                estado: $('#estado').val(),
                idnumber: $('#idnumber').val(),
                firstname: $('#firstname').val(),
                lastname: $('#lastname').val(),
                startdate: $('#startdate').val(),
                enddate: $('#enddate').val()
            };
            
            // Initialize perPage
            state.perPage = $('#perpage').val() ? parseInt($('#perpage').val()) : 100;

            // Verificar si hay filtros de selectbox o de otros tipos
            var hasSelectboxFilter = $('#categoryid').val() || $('#courseid').val() || $('#estado').val();
            var hasOtherFilter = $('#idnumber').val() || $('#firstname').val() || $('#lastname').val() ||
                                $('#startdate').val() || $('#enddate').val();

            if (hasOtherFilter) {
                // Si hay filtros de otro tipo, cargar datos
                loadReportData();
                updateDownloadForm();
            } else if (hasSelectboxFilter) {
                // Si solo hay filtros de selectbox, NO cargar datos automáticamente
                updateDownloadForm();
            }
        }

        // Initialize any existing dynamic elements
        initializeDynamicElements();
    }

    return {
        init: init
    };
});