<h2>Subject Management</h2>
<?php $x = 1; include('spinner.php'); ?>

<!-- Alert Messages -->
<div id="alert-container">
    <div class="alert alert-success alert-dismissible hidden" id="subject-success-alert">
        <button type="button" class="close" onclick="hideAlert(this)">&times;</button>
        <strong>Success!</strong> Subject operations completed successfully.
    </div>
    <div class="alert alert-danger alert-dismissible hidden" id="subject-error-alert">
        <button type="button" class="close" onclick="hideAlert(this)">&times;</button>
        <strong>Error!</strong> <span id="subject-error-message">Something went wrong.</span>
    </div>
    <div class="alert alert-warning alert-dismissible hidden" id="subject-warning-alert">
        <button type="button" class="close" onclick="hideAlert(this)">&times;</button>
        <strong>Warning!</strong> Please enter a valid <strong><span id="subject-warning-field"></span></strong>.
    </div>
</div>

<div id="Subject-Management-Main-Window">
    <div class="card">
        <div class="card-header bg-primary text-white">
            
        </div>
        <div class="card-body">
            <form id="SelectDepartment-Form">
                <div class="form-group">
                    <label for="select-department">Select Department</label>
                    <select name="select-department" id="select-department" class="form-control" required>
                        <option value="">Select a Department</option>
                        <?php
                        try {
                            $stmt = $conn->prepare("SELECT department_code, department_name FROM department ORDER BY department_name ASC");
                            $stmt->execute();
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo '<option value="' . htmlspecialchars($row['department_code']) . '">' . htmlspecialchars($row['department_name']) . '</option>';
                            }
                        } catch (PDOException $e) {
                            echo '<option value="">Error loading departments</option>';
                            error_log("Department load error: " . $e->getMessage());
                        }
                        ?>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <div id="Subject-Management-Department" class="hidden mt-4">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h4 class="mb-0">Manage Subject Details</h4>
            </div>
            <div class="card-body">
                <form id="Subject-Management-Department-Form" class="form-horizontal">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                    
                    <div class="form-group row">
                        <label for="subject-management-department-name" class="col-sm-3 col-form-label">Department</label>
                        <div class="col-sm-9">
                            <input type="text" name="subject-management-department-name" 
                                   id="subject-management-department-name" class="form-control" readonly>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="subject-management-department-program" class="col-sm-3 col-form-label">Program</label>
                        <div class="col-sm-9">
                            <select name="subject-management-department-program" 
                                    id="subject-management-department-program" class="form-control" required>
                                <option value="">Select a program</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="subject-management-department-course" class="col-sm-3 col-form-label">Course</label>
                        <div class="col-sm-9">
                            <select name="subject-management-department-course" 
                                    id="subject-management-department-course" class="form-control" required>
                                <option value="">Select a course</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="subject-management-department-scheme" class="col-sm-3 col-form-label">Scheme</label>
                        <div class="col-sm-9">
                            <select name="subject-management-department-scheme" 
                                    id="subject-management-department-scheme" class="form-control" required>
                                <option value="">Select a scheme</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="subject-management-department-semester" class="col-sm-3 col-form-label">Semester</label>
                        <div class="col-sm-9">
                            <select name="subject-management-department-semester" 
                                    id="subject-management-department-semester" class="form-control" required>
                                <option value="">Select a semester</option>
                            </select>
                        </div>
                    </div>
                </form>

                <div class="text-center mt-4">
                    <button type='button' class='btn btn-secondary' id="Subject-Management-Department-Close" 
                            onclick="SubjectManagementClose();">Close</button>
                    <button type='button' class='btn btn-success' id="Subject-Management-Department-Submit" 
                            onclick="SubjectManagementSubmit();">Load Subjects</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="Subject-Management-Edit-Window" class="hidden mt-4"></div>

<!-- Enhanced JavaScript -->
<script>
// Utility functions
function hideAlert(element) {
    $(element).closest('.alert').addClass('hidden');
}

function hideAllAlerts() {
    $('.alert').addClass('hidden');
}

function showAlert(type, message, field = '') {
    hideAllAlerts();
    
    if (type === 'warning') {
        $('#subject-warning-alert').removeClass('hidden');
        $('#subject-warning-field').text(field);
    } else if (type === 'error') {
        $('#subject-error-alert').removeClass('hidden');
        $('#subject-error-message').text(message);
    } else if (type === 'success') {
        $('#subject-success-alert').removeClass('hidden');
    }
}

function showLoading(button, text = 'Loading...') {
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

// Main functions
function SubjectManagementClose() {
    $("#Subject-Management-Department").addClass('hidden');
    hideAllAlerts();
}

$("#select-department").change(function () {
    const selectedOption = $("#select-department option:selected");
    const departmentName = selectedOption.text();
    const departmentCode = selectedOption.val();
    
    if (!departmentCode) {
        $("#Subject-Management-Department").addClass('hidden');
        return;
    }
    
    $("#subject-management-department-name").val(departmentName);
    $("#Subject-Management-Department").removeClass('hidden');
    hideAllAlerts();
    
    // Reset dependent fields
    $("#subject-management-department-program").html('<option value="">Select a program</option>');
    $("#subject-management-department-course").html('<option value="">Select a course</option>');
    $("#subject-management-department-scheme").html('<option value="">Select a scheme</option>');
    $("#subject-management-department-semester").html('<option value="">Select a semester</option>');
    
    fetch_department_data();
});

$("#subject-management-department-program").change(function () {
    const program = $(this).val();
    if (!program) {
        $("#subject-management-department-course").html('<option value="">Select a course</option>');
        $("#subject-management-department-scheme").html('<option value="">Select a scheme</option>');
        $("#subject-management-department-semester").html('<option value="">Select a semester</option>');
        return;
    }
    
    hideAllAlerts();
    fetch_course_data();
});

// Fetching department data
function fetch_department_data() {
    const departmentCode = $("#select-department").val();
    
    if (!departmentCode) {
        showAlert('error', 'Please select a department');
        return;
    }

    showLoading($("#subject-management-department-program"), 'Loading programs...');

    $.ajax({
        type: "POST",
        url: "dashboard_page/forms/fetch.php",
        data: { 
            "department_code": departmentCode, 
            "action": "fetch_department_data",
            "csrf_token": "<?php echo $_SESSION['csrf_token'] ?? ''; ?>"
        },
        dataType: "json",
        success: function (data) {
            hideLoading($("#subject-management-department-program"));
            
            if (data && data.length > 0) {
                $("#subject-management-department-program").html('<option value="">Select a Program</option>');
                $.each(data, function (k, v) {
                    $("#subject-management-department-program").append('<option value="' + v + '">' + v + '</option>');
                });
            } else {
                showAlert('warning', 'No programs found for this department');
                $("#subject-management-department-program").html('<option value="">No programs available</option>');
            }
        },
        error: function(xhr, status, error) {
            hideLoading($("#subject-management-department-program"));
            showAlert('error', 'Failed to load programs: ' + error);
            console.error("Program load error:", error);
        }
    });
}

// Fetch courses
function fetch_course_data() {
    const departmentCode = $("#select-department").val();
    const program = $("#subject-management-department-program").val();
    
    if (!departmentCode || !program) {
        showAlert('error', 'Department and program are required');
        return;
    }

    showLoading($("#subject-management-department-course"), 'Loading courses...');

    $.ajax({
        type: "POST",
        url: "dashboard_page/forms/fetch.php",
        data: {
            "department_code": departmentCode,
            "department_program": program,
            "action": "fetch_course_data",
            "csrf_token": "<?php echo $_SESSION['csrf_token'] ?? ''; ?>"
        },
        dataType: "json",
        success: function (data) {
            hideLoading($("#subject-management-department-course"));
            
            if (data && data.courses) {
                // Populate courses
                $("#subject-management-department-course").html('<option value="">Select a course</option>');
                $.each(data.courses, function (k, v) {
                    $("#subject-management-department-course").append('<option value="' + v.code + '">' + v.name + '</option>');
                });

                // Populate schemes
                $("#subject-management-department-scheme").html('<option value="">Select a scheme</option>');
                if (data.scheme && data.scheme.length > 0) {
                    $.each(data.scheme, function (k, v) {
                        $("#subject-management-department-scheme").append('<option value="' + v + '">' + v + '</option>');
                    });
                }

                // Populate semesters
                $("#subject-management-department-semester").html('<option value="">Select a semester</option>');
                if (data.semester) {
                    // Add combined semester 1 & 2 option
                    $("#subject-management-department-semester").append('<option value="1">Semester 1 AND 2</option>');
                    
                    // Add individual semesters
                    for (let sem = 1; sem <= data.semester; sem++) {
                        $("#subject-management-department-semester").append('<option value="' + sem + '">Semester ' + sem + '</option>');
                    }
                }
            } else {
                showAlert('warning', 'No courses found for this program');
            }
        },
        error: function(xhr, status, error) {
            hideLoading($("#subject-management-department-course"));
            showAlert('error', 'Failed to load courses: ' + error);
            console.error("Course load error:", error);
        }
    });
}

// Submit and load subjects
function SubjectManagementSubmit() {
    const program = $("#subject-management-department-program").val();
    const course = $("#subject-management-department-course").val();
    const scheme = $("#subject-management-department-scheme").val();
    const semester = $("#subject-management-department-semester").val();
    const csrfToken = $("input[name='csrf_token']").val();

    hideAllAlerts();

    // Validation
    if (!program) {
        showAlert('warning', '', 'Program');
        $("#subject-management-department-program").focus();
        return;
    }
    if (!course) {
        showAlert('warning', '', 'Course');
        $("#subject-management-department-course").focus();
        return;
    }
    if (!scheme) {
        showAlert('warning', '', 'Scheme');
        $("#subject-management-department-scheme").focus();
        return;
    }
    if (!semester) {
        showAlert('warning', '', 'Semester');
        $("#subject-management-department-semester").focus();
        return;
    }

    const button = $("#Subject-Management-Department-Submit");
    showLoading(button, 'Loading Subjects...');

    $.ajax({
        type: "POST",
        url: "dashboard_page/forms/fetch.php",
        data: {
            "department": $("#select-department").val(),
            "department_name": $("#select-department option:selected").text(),
            "program": program,
            "course_code": course,
            "course": $("#subject-management-department-course option:selected").text(),
            "scheme": scheme,
            "semester": semester,
            "action": "fetch_subject_data",
            "csrf_token": csrfToken
        },
        dataType: "html",
        success: function (data) {
            hideLoading(button);
            $("#Subject-Management-Edit-Window").removeClass('hidden').html(data);
            
            // Scroll to the edit window
            $('html, body').animate({
                scrollTop: $("#Subject-Management-Edit-Window").offset().top - 20
            }, 500);
        },
        error: function(xhr, status, error) {
            hideLoading(button);
            showAlert('error', 'Failed to load subjects: ' + error);
            console.error("Subject load error:", error);
        }
    });
}

// Initialize on page load
$(document).ready(function() {
    // Auto-hide alerts after 5 seconds
    setTimeout(() => {
        $('.alert').addClass('hidden');
    }, 5000);
    
    // Close alerts when clicked
    $(document).on('click', '.alert .close', function() {
        $(this).closest('.alert').addClass('hidden');
    });
});
</script>