<?php
// Start session and include config at the top
ini_set('display_errors', 1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check user authentication
if (!isset($_SESSION['UserAuthData'])) {
    header('Location: login.php');
    exit;
}
?>
<h2>Academic Management</h2>

<!-- Alert Messages -->
<div id="alert-container">
    <div class="alert alert-success alert-dismissible hidden" id="Update-Semester-Success">
        <button type="button" class="close" onclick="hideAlert(this)">&times;</button>
        <strong>Success!</strong> Semester details updated successfully.
    </div>
    <div class="alert alert-danger alert-dismissible hidden" id="Update-Semester-Failed">
        <button type="button" class="close" onclick="hideAlert(this)">&times;</button>
        <strong>Error!</strong> <span id="error-message">Something went wrong. Please contact support.</span>
    </div>
    <div class="alert alert-warning alert-dismissible hidden" id="Update-Semester-Warning">
        <button type="button" class="close" onclick="hideAlert(this)">&times;</button>
        <strong>Warning!</strong> Please enter a valid <strong><span id="Update-Semester-Error-Part"></span></strong>.
    </div>
</div>

<?php $x=2; include('spinner.php'); ?>

<!-- Update Semester Form -->
<div id="UpdateSemester" class="hidden card mt-4">
    <div class="card-header bg-primary text-white">
        <h4 class="mb-0">Update Semester Details</h4>
    </div>
    <div class="card-body">
        <form id="UpdateSemester-Form" class="form-horizontal">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="update-semester-id" id="update-semester-id">
            
            <div class="form-group row">
                <label for="Course" class="col-sm-3 col-form-label">Course</label>
                <div class="col-sm-9">
                    <input type="text" name="update-semester-course" id="update-semester-course" class="form-control" readonly>
                </div>
            </div>
            
            <div class="form-group row">
                <label for="Admission-Year" class="col-sm-3 col-form-label">Admission Year</label>
                <div class="col-sm-9">
                    <input type="text" name="update-semester-admission-year" id="update-semester-admission-year" class="form-control" readonly>
                </div>
            </div>
            
            <div class="form-group row">
                <label for="Current-Semester" class="col-sm-3 col-form-label">Current Semester</label>
                <div class="col-sm-9">
                    <select name="update-semester-current-semester" id="update-semester-current-semester" class="form-control" required>
                        <option value="">Select Semester</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group row">
                <label for="Start-Date" class="col-sm-3 col-form-label">Start Date</label>
                <div class="col-sm-9">
                    <input type="date" name="update-semester-start-date" id="update-semester-start-date" class="form-control" required>
                </div>
            </div>
            
            <div class="form-group row">
                <label for="End-Date" class="col-sm-3 col-form-label">End Date</label>
                <div class="col-sm-9">
                    <input type="date" name="update-semester-end-date" id="update-semester-end-date" class="form-control" required>
                </div>
            </div>
            
            <div class="form-group row">
                <div class="col-sm-9 offset-sm-3">
                    <button type="button" class="btn btn-secondary" onclick="UpdateSemesterClose();">Close</button>
                    <button type="button" class="btn btn-primary" id="UpdateSemester-FormButton" onclick="UpdateSemester();">
                        Update Semester
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Action Buttons -->
<div class="row mb-3">
    <div class="col-md-12">
        <button class="btn btn-success" data-toggle="modal" data-target="#RegisterNewBatch">
            <i class="glyphicon glyphicon-plus"></i> Register New Batch
        </button>
        <button class="btn btn-info" onclick="RealTimeData('fetch-academic-data')">
            <i class="glyphicon glyphicon-refresh"></i> Refresh
        </button>
    </div>
</div>

<!-- Academic Data Table -->
<div class="table-responsive">
    <table class="table table-bordered table-striped table-hover text-center" id="AcademicManagementFetch">
        <thead class="thead-dark">
            <tr>
                
            </tr>
        </thead>
        <tbody>
            <!-- Data loaded via AJAX -->
        </tbody>
    </table>
</div>

<!-- Register New Batch Modal -->
<div class="modal fade" id="RegisterNewBatch" tabindex="-1" role="dialog" aria-labelledby="registerBatchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h4 class="modal-title" id="registerBatchModalLabel">Register New Batch</h4>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <?php $x=1; include('spinner.php'); ?>
                
                <form id="RegisterNewBatch-Form">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="form-group">
                        <label for="register-new-batch-program">Program/Course</label>
                        <select name="register-new-batch-program" id="register-new-batch-program" class="form-control" required>
                            <option value="">Select a course to add new batch</option>
                            <?php
                            // Use PDO instead of deprecated mysql_*
                            try {
                                $stmt = $conn->query("SELECT * FROM `programs` ORDER BY program_name");
                                while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                    echo '<option value="' . htmlspecialchars($row['program_name']) . '">' . htmlspecialchars($row['program_name']) . '</option>';
                                }
                            } catch (PDOException $e) {
                                error_log("Database error: " . $e->getMessage());
                                echo '<option value="">Error loading programs</option>';
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="register-new-batch-year">Year of Admission</label>
                        <input type="number" name="register-new-batch-year" id="register-new-batch-year" class="form-control" 
                               placeholder="Enter Year of Admission" min="2000" max="2030" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="register-new-batch-scheme">Academic Scheme</label>
                        <input type="text" name="register-new-batch-scheme" id="register-new-batch-scheme" class="form-control" 
                               placeholder="Enter Academic Scheme" required>
                    </div>
                </form>
                
                <div class="alert alert-warning alert-dismissible hidden" id="RegisterNewBatch-warning">
                    <button type="button" class="close" onclick="hideAlert(this)">&times;</button>
                    Please enter a valid <span id="new-batch-error-part">Program Name</span>.
                </div>
                
                <div class="alert alert-success alert-dismissible hidden" id="RegisterNewBatch-success">
                    <button type="button" class="close" onclick="hideAlert(this)">&times;</button>
                    <strong>Success!</strong> Batch registered successfully.<br>
                    <strong>Don't forget to set semester duration.</strong>
                </div>
                
                <div class="alert alert-danger alert-dismissible hidden" id="RegisterNewBatch-error">
                    <button type="button" class="close" onclick="hideAlert(this)">&times;</button>
                    <strong>Error!</strong> <span id="register-error-message">Something went wrong. Please contact support.</span>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal" onclick="RegisterNewBatchClose();">Cancel</button>
                <button type="button" class="btn btn-primary" id="RegisterNewBatch-FormButton" onclick="RegisterNewBatch();">
                    <i class="glyphicon glyphicon-floppy-disk"></i> Register Batch
                </button>
            </div>
        </div>
    </div>
</div>

<!--------------------------------- Enhanced JavaScript ---------------------------------> 
<script>
// Global variables
let currentAcademicData = [];

// Utility Functions
function hideAlert(element) {
    $(element).closest('.alert').addClass('hidden');
}

function hideAllAlerts() {
    $('.alert').addClass('hidden');
}

function showLoading(button, text = 'Processing...') {
    const originalText = button.html();
    button.data('original-text', originalText);
    button.html('<i class="glyphicon glyphicon-refresh glyphicon-spin"></i> ' + text).prop('disabled', true);
}

function hideLoading(button) {
    const originalText = button.data('original-text');
    if (originalText) {
        button.html(originalText).prop('disabled', false);
    }
}

// Set Semester Functions
function SetSemester(id, course, admissionYear, currentSemester, startDate, endDate) {
    hideAllAlerts();
    
    // Populate form fields
    $("#update-semester-id").val(id);
    $("#update-semester-course").val(course);
    $("#update-semester-admission-year").val(admissionYear);
    
    // Determine semester limits
    const semesterLimits = {
        'B.Tech': 8,
        'M.Tech': 4,
        'Diploma': 6,
        'B.Sc': 6,
        'M.Sc': 4
    };
    
    const semesterLimit = semesterLimits[course] || 8;
    const semesterSelect = $("#update-semester-current-semester");
    
    // Populate semester dropdown
    semesterSelect.html('<option value="">Select Semester</option>');
    
    for (let i = 1; i <= semesterLimit; i++) {
        const selected = (i == currentSemester) ? 'selected' : '';
        semesterSelect.append(`<option value="${i}" ${selected}>Semester ${i}</option>`);
    }
    
    // Add passout option
    if (currentSemester != 0) {
        semesterSelect.append('<option value="0">Passout</option>');
    } else {
        semesterSelect.prepend('<option value="0" selected>Passout</option>');
    }
    
    // Set dates (handle MySQL default dates)
    $("#update-semester-start-date").val(startDate !== '0000-00-00' ? startDate : '');
    $("#update-semester-end-date").val(endDate !== '0000-00-00' ? endDate : '');
    
    // Show form and scroll to it
    $("#UpdateSemester").removeClass('hidden');
    $('html, body').animate({
        scrollTop: $("#UpdateSemester").offset().top - 20
    }, 500);
}

function UpdateSemesterClose() {
    $("#UpdateSemester").addClass('hidden');
    $("#UpdateSemester-Form")[0].reset();
    hideAllAlerts();
}

function UpdateSemester() {
    const id = $("#update-semester-id").val();
    const semester = $("#update-semester-current-semester").val();
    const startDate = $("#update-semester-start-date").val();
    const endDate = $("#update-semester-end-date").val();
    const csrfToken = $("input[name='csrf_token']").val();

    hideAllAlerts();

    // Validation
    if (!semester) {
        $("#Update-Semester-Warning").removeClass('hidden');
        $("#Update-Semester-Error-Part").text('Current Semester');
        $("#update-semester-current-semester").focus();
        return;
    }
    
    if (!startDate) {
        $("#Update-Semester-Warning").removeClass('hidden');
        $("#Update-Semester-Error-Part").text('Start Date');
        $("#update-semester-start-date").focus();
        return;
    }
    
    if (!endDate) {
        $("#Update-Semester-Warning").removeClass('hidden');
        $("#Update-Semester-Error-Part").text('End Date');
        $("#update-semester-end-date").focus();
        return;
    }
    
    // Date validation
    if (new Date(endDate) <= new Date(startDate)) {
        $("#Update-Semester-Warning").removeClass('hidden');
        $("#Update-Semester-Error-Part").text('End Date must be after Start Date');
        return;
    }

    const button = $("#UpdateSemester-FormButton");
    showLoading(button, 'Updating...');

    $.ajax({ 
        type: "POST",
        url: "dashboard_page/forms/academic_management_form.php",
        data: {
            "update_id": id,
            "update_semester": semester,
            "update_start_date": startDate,
            "update_end_date": endDate,
            "action": "update_semester",
            "csrf_token": csrfToken
        },
        dataType: "json",
        success: function (data) {
            hideLoading(button);
            
            if (data.response == "success") {
                $("#Update-Semester-Success").removeClass('hidden');
                UpdateSemesterClose();
                RealTimeData('fetch-academic-data');
                
                setTimeout(() => {
                    $("#Update-Semester-Success").addClass('hidden');
                }, 3000);
            } else {
                $("#UpdateSemester").removeClass('hidden');
                $("#Update-Semester-Failed").removeClass('hidden');
                $("#error-message").text(data.message || 'Operation failed');
            }
        },
        error: function(xhr, status, error) {
            hideLoading(button);
            $("#UpdateSemester").removeClass('hidden');
            $("#Update-Semester-Failed").removeClass('hidden');
            $("#error-message").text('Network error: ' + error);
            console.error("Update semester error:", error);
        }
    });
}

// Register New Batch Functions
function RegisterNewBatchClose() {
    $("#RegisterNewBatch-Form")[0].reset();
    $("#RegisterNewBatch-Form").removeClass("hidden");
    $("#RegisterNewBatch-FormButton").removeClass('hidden');
    hideAllAlerts();
}

function RegisterNewBatch() {
    const program = $("#register-new-batch-program").val();
    const year = $("#register-new-batch-year").val();
    const scheme = $("#register-new-batch-scheme").val();
    const csrfToken = $("input[name='csrf_token']").val();

    hideAllAlerts();

    // Validation
    if (!program) {
        $("#RegisterNewBatch-warning").removeClass('hidden');
        $("#new-batch-error-part").text('Program Name');
        $("#register-new-batch-program").focus();
        return;
    }
    
    if (!year || year < 2000 || year > 2030) {
        $("#RegisterNewBatch-warning").removeClass('hidden');
        $("#new-batch-error-part").text('Year of Admission (must be between 2000-2030)');
        $("#register-new-batch-year").focus();
        return;
    }
    
    if (!scheme || scheme.length < 2) {
        $("#RegisterNewBatch-warning").removeClass('hidden');
        $("#new-batch-error-part").text('Academic Scheme');
        $("#register-new-batch-scheme").focus();
        return;
    }

    const button = $("#RegisterNewBatch-FormButton");
    showLoading(button, 'Registering...');
    $('#Spinner1').removeClass("hidden");
    $("#RegisterNewBatch-Form").addClass("hidden");

    $.ajax({ 
        type: "POST",
        url: "dashboard_page/forms/academic_management_form.php",
        data: {
            "program_name": program,
            "year_of_admission": year,
            "acadamic_scheme": scheme,
            "action": "register-new-batch",
            "csrf_token": csrfToken
        },
        dataType: "json",
        success: function (data) {
            $('#Spinner1').addClass("hidden");
            hideLoading(button);
            
            if (data.response == "success") {
                $("#RegisterNewBatch-success").removeClass('hidden');
                RealTimeData('fetch-academic-data');
                
                setTimeout(() => {
                    $('#RegisterNewBatch').modal('hide');
                    RegisterNewBatchClose();
                }, 2000);
            } else {
                $("#RegisterNewBatch-Form").removeClass("hidden");
                $("#RegisterNewBatch-error").removeClass('hidden');
                $("#register-error-message").text(data.message || 'Registration failed');
            }
        },
        error: function(xhr, status, error) {
            $('#Spinner1').addClass("hidden");
            hideLoading(button);
            $("#RegisterNewBatch-Form").removeClass("hidden");
            $("#RegisterNewBatch-error").removeClass('hidden');
            $("#register-error-message").text('Network error: ' + error);
            console.error("Register batch error:", error);
        }
    });
}

// Real Time Data Functions
function RealTimeData(action) {
    $.ajax({
        type: "POST",
        url: "dashboard_page/realtime_data.php",
        data: { "action": action },
        dataType: "html",
        success: function (data) {
            $("#AcademicManagementFetch tbody").html(data);
            
            // Store current data for duplicate checking
            currentAcademicData = [];
            $("#AcademicManagementFetch tbody tr").each(function() {
                const course = $(this).find('td:eq(1)').text();
                const year = $(this).find('td:eq(2)').text();
                if(course && year) {
                    currentAcademicData.push({ course: course, admission_year: year });
                }
            });
        },
        error: function(xhr, status, error) {
            $("#AcademicManagementFetch tbody").html(
                '<tr><td colspan="8" class="text-danger">Error loading data: ' + error + '</td></tr>'
            );
        }
    });
}

// Initialize on page load
$(document).ready(function() {
    RealTimeData('fetch-academic-data');
    
    // Auto-hide alerts after 5 seconds
    setTimeout(() => {
        $('.alert').addClass('hidden');
    }, 5000);
    
    // Year input validation
    $("#register-new-batch-year").on('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
        if(this.value.length > 4) {
            this.value = this.value.slice(0,4);
        }
    });
    
    // Academic scheme input validation
    $("#register-new-batch-scheme").on('input', function() {
        this.value = this.value.replace(/[^a-zA-Z0-9\s\-]/g, '');
    });
});
</script>