<?php
ini_set('display_errors', 1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database configuration
include_once(__DIR__ . '/../config.php');// Adjust path if needed

// ------------------- USER AUTHENTICATION -------------------
if (!isset($_SESSION['UserAuthData'])) {
    header('Location: ../../logout.php');
    exit;
}

$UserAuthData = $_SESSION['UserAuthData'];

if (is_string($UserAuthData)) {
    $UserAuthData = unserialize($UserAuthData);
}

if (empty($UserAuthData) || $UserAuthData['role'] !== 'admin') {
    echo "<p>Access denied. Admins only.</p>";
    exit;
}
// ------------------------------------------------------------

// Fetch departments
try {
    $stmt = $conn->query("SELECT department_code, department_name FROM department ORDER BY department_name ASC");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $departments = [];
}
?>

<h2>Staff Management</h2>
<div class="form-group">
  <form id="SelectDepartment-Form">
    <select name="select-department" id="select-department" class="form-control">
      <option value="null">Select a Department</option>
      <option value="all">All Staffs</option>
      <?php
      foreach ($departments as $row) {
          echo '<option value="' . htmlspecialchars($row['department_code']) . '">' 
               . htmlspecialchars($row['department_name']) . '</option>';
      }
      ?>
    </select>
  </form>
</div>

<div id="Staff-Data-Window" class="hidden">
  <h4>Staffs - <span id="Department-Name"></span></h4>
  <table class="table table-bordered text-center" id="StaffData-Fetch"></table>
</div>

<!-- Pop-Up Box for Adding Staff -->
<div class="container-fluid">
  <div class="row-fluid">
    <div class="col-lg-12">
      <div class="modal fade" id="AddNewStaff" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header">
              <h4 class="modal-title" id="myModalLabel">Add New Staff</h4>
            </div>
            <div class="modal-body">
              <?php $x=1; include('./spinner.php'); ?>
              <form id="AddNewStaff-Form" class="form-horizontal">
                <input type="hidden" name="add-new-staff-department" id="add-new-staff-department" readonly>
                
                <!-- Name -->
                <div class="form-group">
                  <label class="col-sm-3 control-label">Name</label>
                  <div class="col-sm-9">
                    <input type="text" id="add-new-staff-name" class="form-control" placeholder="Firstname Lastname" required>
                  </div>
                </div>

                <!-- Date of Birth -->
                <div class="form-group">
                  <label class="col-sm-3 control-label">Date of Birth</label>
                  <div class="col-sm-9">
                    <input type="date" id="add-new-staff-dob" class="form-control" required>
                  </div>
                </div>

                <!-- Email -->
                <div class="form-group">
                  <label class="col-sm-3 control-label">E-Mail</label>
                  <div class="col-sm-9">
                    <input type="email" id="add-new-staff-email" class="form-control" placeholder="sample@domain.com" required>
                  </div>
                </div>

                <!-- Phone -->
                <div class="form-group">
                  <label class="col-sm-3 control-label">Phone Number</label>
                  <div class="col-sm-9">
                    <input type="text" id="add-new-staff-phone" class="form-control" placeholder="9876543210" required>
                  </div>
                </div>

                <!-- Join Date -->
                <div class="form-group">
                  <label class="col-sm-3 control-label">Join Date</label>
                  <div class="col-sm-9">
                    <input type="date" id="add-new-staff-joindate" class="form-control" required>
                  </div>
                </div>

                <!-- Designation -->
                <div class="form-group">
                  <label class="col-sm-3 control-label">Designation</label>
                  <div class="col-sm-9">
                    <select id="add-new-staff-designation" class="form-control" required>
                      <option value="null" selected>Select Designation</option>
                      <option value="Professor">Professor</option>
                      <option value="Assistant Professor">Assistant Professor</option>
                      <option value="Guest Lecturer">Guest Lecturer</option>
                      <option value="Lab Assistant">Lab Assistant</option>
                    </select>
                  </div>
                </div>

                <!-- Type -->
                <div class="form-group">
                  <label class="col-sm-3 control-label">Type</label>
                  <div class="col-sm-9">
                    <select id="add-new-staff-type" class="form-control" required>
                      <option value="null" selected>Select Type</option>
                      <option value="technical">Technical</option>
                      <option value="non-technical">Non-Technical</option>
                    </select>
                  </div>
                </div>

              </form>
              
              <div class="alert alert-warning hidden" id="AddNewStaff-warning">Please enter a valid <span id="AddNewStaff-Error-Part"></span>.</div>
              <div class="alert alert-success hidden" id="AddNewStaff-success">Staff added successfully. Temporary password: <strong><span id="AddNewStaff-password"></span></strong>.</div>
              <div class="alert alert-danger hidden" id="AddNewStaff-error">Something went wrong. Contact support.</div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-default" data-dismiss="modal" onclick="CloseAddNewStaff();">Close</button>
              <button type="button" class="btn btn-primary" onclick="AddNewStaff();">Register</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
// ---------- Department Selection ----------
$("#select-department").change(function() {
    var selectedText = $("#select-department option:selected").text();
    var selectedVal = $("#select-department").val();

    $("#Department-Name").html(selectedText);
    $("#add-new-staff-department").val(selectedVal);

    if(selectedVal != 'null') {
        $("#Staff-Data-Window").removeClass('hidden');
        fetchStaffData(selectedVal);
    } else {
        $("#Staff-Data-Window").addClass('hidden');
    }
});

// ---------- Fetch Staff Data ----------
function fetchStaffData(department) {
    $("#StaffData-Fetch").html('');
    $.ajax({
        type: "POST",
        url: "dashboard_page/forms/fetch_staff.php",
        data: {
            department_code: department,
            action: "fetch_staff_data"
        },
        dataType: "html",
        success: function(data) {
            $("#StaffData-Fetch").html(data);
        }
    });
}

// ---------- Add New Staff ----------
function AddNewStaff() {
    var department = $("#add-new-staff-department").val();
    var name = $("#add-new-staff-name").val();
    var dob = $("#add-new-staff-dob").val();
    var email = $("#add-new-staff-email").val();
    var phone = $("#add-new-staff-phone").val();
    var joindate = $("#add-new-staff-joindate").val();
    var designation = $("#add-new-staff-designation").val();
    var type = $("#add-new-staff-type").val();

    // Basic validations
    if(name.length < 2) { showWarning("Name"); return; }
    if(!dob) { showWarning("Date of Birth"); return; }
    if(!email.match(/^[^@]+@[^@]+\.[^@]+$/)) { showWarning("E-Mail"); return; }
    if(!phone.match(/^[0-9]{10}$/)) { showWarning("Phone Number"); return; }
    if(!joindate) { showWarning("Join Date"); return; }
    if(designation == "null") { showWarning("Designation"); return; }
    if(type == "null") { showWarning("Type"); return; }

    $("#AddNewStaff-warning").addClass('hidden');

    $.ajax({
        type: "POST",
        url: "dashboard_page/forms/staff_management_form.php",
        data: {
            department: department,
            name: name,
            dob: dob,
            email: email,
            phone: phone,
            joindate: joindate,
            designation: designation,
            type: type,
            action: "add_new_staff"
        },
        dataType: "json",
        beforeSend: function() {
            $('#Spinner1').removeClass('hidden');
        },
        success: function(data) {
            $('#Spinner1').addClass('hidden');
            if(data.response === 'success') {
                $('#AddNewStaff-success').removeClass('hidden');
                $("#AddNewStaff-password").html(data.pass);
                fetchStaffData(department);
            } else {
                $('#AddNewStaff-error').removeClass('hidden');
            }
        }
    });
}

function showWarning(field) {
    $("#AddNewStaff-warning").removeClass('hidden');
    $("#AddNewStaff-Error-Part").html(field);
}

// ---------- Close Form ----------
function CloseAddNewStaff() {
    $("#AddNewStaff-Form").trigger("reset");
    $('#AddNewStaff-warning, #AddNewStaff-error, #AddNewStaff-success').addClass('hidden');
    $('#Spinner1').addClass('hidden');
}
</script>
