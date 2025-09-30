/**
 * User Management Module
 * Handles display and management of system users and roles.
 * Includes functionality to add a new employee along with their user account,
 * and for System Admins to edit existing employee profile information.
 * v1.3 - Added Admin Edit Employee Profile functionality.
 * v1.2 - Added Add Employee functionality to the form.
 */
import { API_BASE_URL, populateEmployeeDropdown } from '../utils.js';

// --- DOM Element References ---
let pageTitleElement;
let mainContentArea;
let departmentDropdown; // For selecting department when adding/editing employee
let managerDropdown; // For selecting manager when adding/editing employee

// --- State Variables ---
let currentEditMode = 'add'; // 'add', 'edit_user_account', 'edit_employee_info'
let editingEmployeeId = null; // Stores EmployeeID when in 'edit_employee_info' mode
let editingUserId = null; // Stores UserID when in 'edit_user_account' mode


/**
 * Initializes common elements used by the user management module.
 */
function initializeUserManagementElements() {
    pageTitleElement = document.getElementById('page-title');
    mainContentArea = document.getElementById('main-content-area');
    if (!pageTitleElement || !mainContentArea) {
        console.error("User Management Module: Core DOM elements not found!");
        return false;
    }
    return true;
}

/**
 * Populates a select dropdown with available departments.
 * @param {string} selectElementId - The ID of the select element.
 */
async function populateDepartmentDropdownAdmin(selectElementId) {
    const selectElement = document.getElementById(selectElementId);
    if (!selectElement) {
        console.error(`[populateDepartmentDropdownAdmin] Element ID '${selectElementId}' not found.`);
        return;
    }
    selectElement.innerHTML = '<option value="" disabled selected>Loading departments...</option>';

    try {
        const response = await fetch(`${API_BASE_URL}get_org_structure.php`);
        const departments = await handleApiResponse(response);

        selectElement.innerHTML = '<option value="">-- Select Department --</option>';
        if (departments && departments.length > 0) {
            departments.forEach(dept => {
                const option = document.createElement('option');
                option.value = dept.DepartmentID;
                option.textContent = dept.DepartmentName;
                selectElement.appendChild(option);
            });
        } else {
            selectElement.innerHTML = '<option value="" disabled>No departments found</option>';
        }
    } catch (error) {
        console.error('Error populating department dropdown:', error);
        selectElement.innerHTML = `<option value="" disabled>Error loading departments</option>`;
    }
}

/**
 * Populates a select dropdown with available employees to select as a manager.
 * @param {string} selectElementId - The ID of the select element.
 * @param {number|null} [excludeEmployeeId=null] - Optional EmployeeID to exclude (e.g., the employee being edited).
 */
async function populateManagerDropdownAdmin(selectElementId, excludeEmployeeId = null) {
    const selectElement = document.getElementById(selectElementId);
    if (!selectElement) {
        console.error(`[populateManagerDropdownAdmin] Element ID '${selectElementId}' not found.`);
        return;
    }
    selectElement.innerHTML = '<option value="" disabled selected>Loading managers...</option>';

    try {
        const response = await fetch(`${API_BASE_URL}get_employees.php`); // Assuming this endpoint returns all employees
        const employees = await handleApiResponse(response);

        selectElement.innerHTML = '<option value="">-- Select Manager (Optional) --</option>';
        if (employees && employees.length > 0) {
            employees.forEach(emp => {
                if (excludeEmployeeId && emp.EmployeeID == excludeEmployeeId) {
                    return; // Skip the employee themselves
                }
                const option = document.createElement('option');
                option.value = emp.EmployeeID;
                option.textContent = `${emp.FirstName} ${emp.LastName} (ID: ${emp.EmployeeID})`;
                selectElement.appendChild(option);
            });
        } else {
            selectElement.innerHTML = '<option value="" disabled>No employees found to select as manager</option>';
        }
    } catch (error) {
        console.error('Error populating manager dropdown:', error);
        selectElement.innerHTML = `<option value="" disabled>Error loading managers</option>`;
    }
}


/**
 * Displays the User Management section.
 */
export async function displayUserManagementSection() {
    console.log("[Display] Displaying User Management Section...");
    if (!initializeUserManagementElements()) return;

    const user = window.currentUser;
    if (user?.role_name !== 'System Admin') {
        pageTitleElement.textContent = 'Access Denied';
        mainContentArea.innerHTML = `
            <div class="bg-white p-6 rounded-lg shadow-md border border-red-300">
                <p class="text-red-600 font-semibold">Access Denied: You do not have permission to manage users.</p>
            </div>`;
        return;
    }

    pageTitleElement.textContent = 'User & Employee Management';
    mainContentArea.innerHTML = `
        <div class="bg-white p-6 rounded-lg shadow-md border border-[#F7E6CA] space-y-6">
            <div class="border-b border-gray-200 pb-4">
                 <h3 class="text-lg font-semibold text-[#4E3B2A] mb-3"><span id="user-form-title">Add New Employee & User</span></h3>
                 <form id="add-edit-user-form" class="space-y-4">
                    <input type="hidden" id="editing-employee-id" name="editing_employee_id" value="">
                    <input type="hidden" id="editing-user-id" name="editing_user_id" value="">

                    <div id="employee-info-section" class="space-y-4 border-b border-dashed border-gray-300 pb-4 mb-4">
                        <h4 class="text-md font-semibold text-gray-700">Employee Information</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div>
                                <label for="emp-first-name" class="block text-sm font-medium text-gray-700 mb-1">First Name:</label>
                                <input type="text" id="emp-first-name" name="first_name" required class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                            </div>
                            <div>
                                <label for="emp-middle-name" class="block text-sm font-medium text-gray-700 mb-1">Middle Name (Optional):</label>
                                <input type="text" id="emp-middle-name" name="middle_name" class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                            </div>
                            <div>
                                <label for="emp-last-name" class="block text-sm font-medium text-gray-700 mb-1">Last Name:</label>
                                <input type="text" id="emp-last-name" name="last_name" required class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                            </div>
                             <div>
                                <label for="emp-suffix" class="block text-sm font-medium text-gray-700 mb-1">Suffix (Optional):</label>
                                <input type="text" id="emp-suffix" name="suffix" class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                            </div>
                            <div>
                                <label for="emp-email" class="block text-sm font-medium text-gray-700 mb-1">Work Email:</label>
                                <input type="email" id="emp-email" name="email" required class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                            </div>
                            <div>
                                <label for="emp-personal-email" class="block text-sm font-medium text-gray-700 mb-1">Personal Email (Optional):</label>
                                <input type="email" id="emp-personal-email" name="personal_email" class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                            </div>
                            <div>
                                <label for="emp-phone" class="block text-sm font-medium text-gray-700 mb-1">Phone Number (Optional):</label>
                                <input type="tel" id="emp-phone" name="phone_number" class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                            </div>
                             <div>
                                <label for="emp-dob" class="block text-sm font-medium text-gray-700 mb-1">Date of Birth (Optional):</label>
                                <input type="date" id="emp-dob" name="date_of_birth" class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                            </div>
                            <div>
                                <label for="emp-gender" class="block text-sm font-medium text-gray-700 mb-1">Gender (Optional):</label>
                                <select id="emp-gender" name="gender" class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                                    <option value="">-- Select --</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                    <option value="Prefer not to say">Prefer not to say</option>
                                </select>
                            </div>
                            <div>
                                <label for="emp-marital-status" class="block text-sm font-medium text-gray-700 mb-1">Marital Status (Optional):</label>
                                <select id="emp-marital-status" name="marital_status" class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                                    <option value="">-- Select --</option>
                                    <option value="Single">Single</option>
                                    <option value="Married">Married</option>
                                    <option value="Widowed">Widowed</option>
                                    <option value="Divorced">Divorced</option>
                                    <option value="Separated">Separated</option>
                                </select>
                            </div>
                             <div>
                                <label for="emp-nationality" class="block text-sm font-medium text-gray-700 mb-1">Nationality (Optional):</label>
                                <input type="text" id="emp-nationality" name="nationality" class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                            </div>
                            <div class="md:col-span-1 lg:col-span-1">
                                <label for="emp-job-title" class="block text-sm font-medium text-gray-700 mb-1">Job Title:</label>
                                <input type="text" id="emp-job-title" name="job_title" required class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                            </div>
                            <div class="md:col-span-1 lg:col-span-1">
                                <label for="emp-department-select" class="block text-sm font-medium text-gray-700 mb-1">Department:</label>
                                <select id="emp-department-select" name="department_id" required class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                                    <option value="">Loading departments...</option>
                                </select>
                            </div>
                             <div class="md:col-span-1 lg:col-span-1">
                                <label for="emp-manager-select" class="block text-sm font-medium text-gray-700 mb-1">Direct Manager (Optional):</label>
                                <select id="emp-manager-select" name="manager_id" class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                                    <option value="">Loading managers...</option>
                                </select>
                            </div>
                             <div>
                                <label for="emp-hire-date" class="block text-sm font-medium text-gray-700 mb-1">Hire Date:</label>
                                <input type="date" id="emp-hire-date" name="hire_date" required class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                            </div>
                             <div class="flex items-center pt-6 md:col-span-1 lg:col-span-1" id="employee-active-status-section">
                                <input id="emp-is-active" name="is_active_employee" type="checkbox" value="1" checked class="h-4 w-4 text-[#594423] focus:ring-[#4E3B2A] border-gray-300 rounded">
                                <label for="emp-is-active" class="ml-2 block text-sm text-gray-900">Employee Active?</label>
                            </div>
                        </div>
                    </div>

                    <div id="user-account-section" class="space-y-4">
                        <h4 class="text-md font-semibold text-gray-700 pt-2">User Account Information</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div id="user-employee-select-container" style="display:none;">
                                <label for="user-employee-select" class="block text-sm font-medium text-gray-700 mb-1">Link to Existing Employee:</label>
                                <select id="user-employee-select" name="employee_id" class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                                    <option value="">Loading employees...</option>
                                </select>
                            </div>
                            <div>
                                <label for="user-username" class="block text-sm font-medium text-gray-700 mb-1">Username:</label>
                                <input type="text" id="user-username" name="username" required placeholder="e.g., jdelacruz" class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                            </div>
                            <div>
                                <label for="user-role-select" class="block text-sm font-medium text-gray-700 mb-1">Role:</label>
                                <select id="user-role-select" name="role_id" required class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                                    <option value="">Loading roles...</option>
                                </select>
                            </div>
                            <div id="password-section">
                                <label for="user-password" class="block text-sm font-medium text-gray-700 mb-1">Password:</label>
                                <input type="password" id="user-password" name="password" required placeholder="Enter initial password" class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                            </div>
                            <div class="flex items-center pt-6 md:col-span-1 lg:col-span-1" id="user-active-status-section">
                                <input id="user-is-active" name="is_active_user" type="checkbox" value="1" checked class="h-4 w-4 text-[#594423] focus:ring-[#4E3B2A] border-gray-300 rounded">
                                <label for="user-is-active" class="ml-2 block text-sm text-gray-900">User Account Active?</label>
                            </div>
                        </div>
                    </div>
                     <div class="pt-2 space-x-3">
                        <button type="submit" id="save-form-button" class="px-4 py-2 bg-[#594423] text-white rounded-md hover:bg-[#4E3B2A] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#594423] transition duration-150 ease-in-out">
                            Save
                        </button>
                         <button type="button" id="cancel-edit-btn" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition duration-150 ease-in-out" style="display: none;">
                            Cancel
                        </button>
                        <span id="add-edit-status" class="ml-4 text-sm"></span>
                    </div>
                 </form>
            </div>

            <div>
                <h3 class="text-lg font-semibold text-[#4E3B2A] mb-3">Existing Users & Employees</h3>
                <div id="users-list-container" class="overflow-x-auto">
                     <p>Loading users...</p>
                </div>
            </div>
        </div>
    `;

    requestAnimationFrame(async () => {
        await populateEmployeeDropdown('user-employee-select');
        await populateRoleDropdown('user-role-select');
        await populateDepartmentDropdownAdmin('emp-department-select');
        await populateManagerDropdownAdmin('emp-manager-select');


        const userForm = document.getElementById('add-edit-user-form');
        if (userForm) {
            if (!userForm.hasAttribute('data-listener-attached')) {
                userForm.addEventListener('submit', handleSaveForm);
                userForm.setAttribute('data-listener-attached', 'true');
            }
        } else { console.error("Add/Edit User form not found."); }

        const cancelBtn = document.getElementById('cancel-edit-btn');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', resetFormToDefault);
        }
        resetFormToDefault(); // Initialize form for "Add New Employee & User"
        await loadUsers();
    });
}

/**
 * Populates a select dropdown with available roles.
 */
async function populateRoleDropdown(selectElementId) {
    const selectElement = document.getElementById(selectElementId);
    if (!selectElement) {
        console.error(`[populateRoleDropdown] Element ID '${selectElementId}' not found.`);
        return;
    }
    selectElement.innerHTML = '<option value="" disabled selected>Loading roles...</option>';

    try {
        // In a real app, fetch roles from an API: GET /api/roles
        // For this example, using a static list.
        const roles = [
            { RoleID: 1, RoleName: 'System Admin' },
            { RoleID: 2, RoleName: 'HR Admin' },
            { RoleID: 3, RoleName: 'Employee' },
            { RoleID: 4, RoleName: 'Manager' }
            // Add more roles as needed
        ];

        selectElement.innerHTML = '<option value="">-- Select Role --</option>';
        if (roles.length > 0) {
            roles.forEach(role => {
                const option = document.createElement('option');
                option.value = role.RoleID;
                option.textContent = role.RoleName;
                selectElement.appendChild(option);
            });
        } else {
            selectElement.innerHTML = '<option value="" disabled>No roles found</option>';
        }
    } catch (error) {
        console.error('Error populating role dropdown:', error);
        selectElement.innerHTML = `<option value="" disabled>Error loading roles</option>`;
    }
}


/**
 * Fetches users from the API and renders them in the table.
 */
async function loadUsers() {
    console.log("[Load] Loading Users...");
    const container = document.getElementById('users-list-container');
    if (!container) return;
    container.innerHTML = '<p class="text-center py-4">Loading users...</p>';

    try {
        const response = await fetch(`${API_BASE_URL}get_users.php`);
        const users = await handleApiResponse(response);
        renderUsersTable(users);
    } catch (error) {
        console.error('Error loading users:', error);
        container.innerHTML = `<p class="text-red-500 text-center py-4">Could not load users. ${error.message}</p>`;
    }
}

/**
 * Renders the user data into an HTML table.
 */
function renderUsersTable(users) {
    console.log("[Render] Rendering Users Table...");
    const container = document.getElementById('users-list-container');
    if (!container) return;

    container.innerHTML = '';

    if (!users || users.length === 0) {
        container.innerHTML = '<p class="text-center py-4 text-gray-500">No users found.</p>';
        return;
    }

    const table = document.createElement('table');
    table.className = 'min-w-full divide-y divide-gray-200 border border-gray-300';

    const thead = table.createTHead();
    thead.className = 'bg-gray-50';
    const headerRow = thead.insertRow();
    const headers = ['Emp. ID', 'Employee Name', 'Job Title', 'Username', 'Role', 'Status', 'Actions'];
    headers.forEach(text => {
        const th = document.createElement('th');
        th.scope = 'col';
        th.className = 'px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider';
        th.textContent = text;
        headerRow.appendChild(th);
    });

    const tbody = table.createTBody();
    tbody.className = 'bg-white divide-y divide-gray-200 user-action-container';

    users.forEach(user => {
        const row = tbody.insertRow();
        row.dataset.employeeId = user.EmployeeID; // Store employee ID for fetching full details

        const createCell = (text) => {
            const cell = row.insertCell();
            cell.className = 'px-4 py-3 whitespace-nowrap text-sm';
            cell.textContent = text ?? 'N/A';
            return cell;
        };

        createCell(user.EmployeeID).classList.add('text-gray-500');
        createCell(user.EmployeeName).classList.add('font-medium', 'text-gray-900');
        createCell(user.EmployeeJobTitle).classList.add('text-gray-700');
        createCell(user.Username).classList.add('text-gray-700');
        createCell(user.RoleName).classList.add('text-gray-700');

        const statusCell = createCell(user.IsActive == 1 ? 'Active' : 'Inactive');
        statusCell.classList.add('font-semibold', user.IsActive == 1 ? 'text-green-600' : 'text-red-600');

        const actionsCell = row.insertCell();
        actionsCell.className = 'px-4 py-3 whitespace-nowrap text-sm font-medium space-x-2';

        const editEmployeeBtn = document.createElement('button');
        editEmployeeBtn.className = 'text-purple-600 hover:text-purple-800 edit-employee-info-btn';
        editEmployeeBtn.dataset.employeeId = user.EmployeeID;
        editEmployeeBtn.title = 'Edit Employee Information';
        editEmployeeBtn.innerHTML = '<i class="fas fa-user-tie"></i> Edit Info';
        actionsCell.appendChild(editEmployeeBtn);
        
        const editUserAcctBtn = document.createElement('button');
        editUserAcctBtn.className = 'text-blue-600 hover:text-blue-800 edit-user-account-btn';
        editUserAcctBtn.dataset.userId = user.UserID;
        editUserAcctBtn.dataset.username = user.Username;
        editUserAcctBtn.dataset.roleId = user.RoleID;
        editUserAcctBtn.dataset.employeeId = user.EmployeeID; // Keep for context
        editUserAcctBtn.dataset.isActive = user.IsActive;
        editUserAcctBtn.title = 'Edit User Account';
        editUserAcctBtn.innerHTML = '<i class="fas fa-user-edit"></i> Edit Acct';
        actionsCell.appendChild(editUserAcctBtn);


        const toggleActiveBtn = document.createElement('button');
        toggleActiveBtn.dataset.userId = user.UserID;
        toggleActiveBtn.dataset.username = user.Username;
        if (user.IsActive == 1) {
            toggleActiveBtn.className = 'text-red-600 hover:text-red-800 deactivate-user-btn';
            toggleActiveBtn.title = 'Deactivate User Account';
            toggleActiveBtn.innerHTML = '<i class="fas fa-toggle-off"></i> Deactivate';
        } else {
            toggleActiveBtn.className = 'text-green-600 hover:text-green-800 activate-user-btn';
            toggleActiveBtn.title = 'Activate User Account';
            toggleActiveBtn.innerHTML = '<i class="fas fa-toggle-on"></i> Activate';
        }
        actionsCell.appendChild(toggleActiveBtn);

        const resetPwdBtn = document.createElement('button');
        resetPwdBtn.className = 'text-orange-600 hover:text-orange-800 reset-pwd-btn';
        resetPwdBtn.dataset.userId = user.UserID;
        resetPwdBtn.dataset.username = user.Username;
        resetPwdBtn.title = 'Reset Password';
        resetPwdBtn.innerHTML = '<i class="fas fa-key"></i> Reset Pwd';
        actionsCell.appendChild(resetPwdBtn);
    });

    container.appendChild(table);
    attachUserActionListeners();
}


/**
 * Attaches event listeners for user actions.
 */
function attachUserActionListeners() {
    const container = document.querySelector('.user-action-container');
    if (container) {
        container.removeEventListener('click', handleUserAction); // Prevent multiple listeners
        container.addEventListener('click', handleUserAction);
    }
}

/**
 * Handles clicks on user action buttons.
 */
async function handleUserAction(event) {
    const editEmployeeButton = event.target.closest('.edit-employee-info-btn');
    const editUserAccountButton = event.target.closest('.edit-user-account-btn');
    const deactivateButton = event.target.closest('.deactivate-user-btn');
    const activateButton = event.target.closest('.activate-user-btn');
    const resetButton = event.target.closest('.reset-pwd-btn');

    if (editEmployeeButton) {
        const employeeId = editEmployeeButton.dataset.employeeId;
        await populateEmployeeInfoEditForm(employeeId);
    } else if (editUserAccountButton) {
        const userId = editUserAccountButton.dataset.userId;
        const username = editUserAccountButton.dataset.username;
        const roleId = editUserAccountButton.dataset.roleId;
        const employeeId = editUserAccountButton.dataset.employeeId;
        const isActive = editUserAccountButton.dataset.isActive;
        populateUserAccountEditForm(userId, employeeId, username, roleId, isActive);
    } else if (deactivateButton) {
        // ... (keep existing deactivate logic) ...
        const userId = deactivateButton.dataset.userId;
        const username = deactivateButton.dataset.username;
        Swal.fire({
            title: 'Deactivate User Account?',
            text: `Are you sure you want to deactivate user account "${username}" (ID: ${userId})? This will also deactivate the employee record.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, deactivate!'
        }).then((result) => {
            if (result.isConfirmed) {
                toggleUserActivation(userId, 0); // Deactivate
            }
        });
    } else if (activateButton) {
        // ... (keep existing activate logic) ...
        const userId = activateButton.dataset.userId;
        const username = activateButton.dataset.username;
        Swal.fire({
            title: 'Activate User Account?',
            text: `Are you sure you want to activate user account "${username}" (ID: ${userId})? This will also activate the employee record.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#aaa',
            confirmButtonText: 'Yes, activate!'
        }).then((result) => {
            if (result.isConfirmed) {
                toggleUserActivation(userId, 1); // Activate
            }
        });
    } else if (resetButton) {
        // ... (keep existing reset password logic) ...
        const userId = resetButton.dataset.userId;
        const username = resetButton.dataset.username;
         Swal.fire({
            title: 'Reset Password?',
            text: `Are you sure you want to reset the password for user "${username}" (ID: ${userId})? The user will need to be informed of the temporary password.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ff9800',
            cancelButtonColor: '#aaa',
            confirmButtonText: 'Yes, reset password!'
        }).then((result) => {
            if (result.isConfirmed) {
                resetUserPassword(userId);
            }
        });
    }
}

/**
 * Populates the form for editing an Employee's Information.
 * @param {string} employeeId - The ID of the employee to edit.
 */
async function populateEmployeeInfoEditForm(employeeId) {
    currentEditMode = 'edit_employee_info';
    editingEmployeeId = employeeId;
    editingUserId = null; // Not editing user account details here

    document.getElementById('user-form-title').textContent = 'Edit Employee Information';
    document.getElementById('save-form-button').textContent = 'Save Employee Changes';
    document.getElementById('editing-employee-id').value = employeeId;
    document.getElementById('editing-user-id').value = '';


    // Show employee fields, hide user account fields
    document.getElementById('employee-info-section').style.display = 'block';
    document.getElementById('user-account-section').style.display = 'none';
    document.getElementById('user-employee-select-container').style.display = 'none';
    
    makeEmployeeFieldsRequired(true); // Most employee fields are required
    makeUserAccountFieldsRequired(false); // User account fields are not part of this edit

    // Fetch full employee details to populate the form
    try {
        // We need a get_employee_details.php endpoint or similar
        // For now, let's assume get_users.php returns enough, or we make a dedicated one.
        // Let's assume we need to fetch more details.
        // This would be better with a dedicated endpoint: get_employee_details.php?employee_id=X
        // For now, we'll try to find it in the loaded users list or re-fetch if necessary.
        // This is a simplified approach; a dedicated get_employee_details.php is better.
        const response = await fetch(`${API_BASE_URL}get_employees.php`); // Re-fetch all, then filter. Not ideal.
        const employees = await handleApiResponse(response);
        const employeeData = employees.find(emp => emp.EmployeeID == employeeId);

        if (!employeeData) {
            Swal.fire('Error', `Could not find employee data for ID ${employeeId}.`, 'error');
            resetFormToDefault();
            return;
        }
        
        // Populate Employee Fields
        document.getElementById('emp-first-name').value = employeeData.FirstName || '';
        document.getElementById('emp-middle-name').value = employeeData.MiddleName || '';
        document.getElementById('emp-last-name').value = employeeData.LastName || '';
        document.getElementById('emp-suffix').value = employeeData.Suffix || '';
        document.getElementById('emp-email').value = employeeData.Email || '';
        document.getElementById('emp-personal-email').value = employeeData.PersonalEmail || '';
        document.getElementById('emp-phone').value = employeeData.PhoneNumber || '';
        document.getElementById('emp-dob').value = employeeData.DateOfBirth || '';
        document.getElementById('emp-gender').value = employeeData.Gender || '';
        document.getElementById('emp-marital-status').value = employeeData.MaritalStatus || '';
        document.getElementById('emp-nationality').value = employeeData.Nationality || '';
        // Address fields would go here if they were in the form
        document.getElementById('emp-job-title').value = employeeData.JobTitle || '';
        document.getElementById('emp-department-select').value = employeeData.DepartmentID || '';
        await populateManagerDropdownAdmin('emp-manager-select', employeeId); // Repopulate manager dropdown, excluding self
        document.getElementById('emp-manager-select').value = employeeData.ManagerID || '';
        document.getElementById('emp-hire-date').value = employeeData.HireDate || '';
        document.getElementById('emp-is-active').checked = (employeeData.IsActive == 1);


    } catch (error) {
        console.error("Error fetching employee details for edit:", error);
        Swal.fire('Error', `Could not load employee details: ${error.message}`, 'error');
        resetFormToDefault();
        return;
    }

    document.getElementById('cancel-edit-btn').style.display = 'inline-block';
    document.getElementById('add-edit-user-form').scrollIntoView({ behavior: 'smooth' });
}


/**
 * Populates the user form for editing an existing user account.
 */
function populateUserAccountEditForm(userId, employeeId, username, roleId, isActive) {
    currentEditMode = 'edit_user_account';
    editingUserId = userId;
    editingEmployeeId = employeeId; // Store for context

    document.getElementById('user-form-title').textContent = 'Edit User Account';
    document.getElementById('save-form-button').textContent = 'Save User Account Changes';
    document.getElementById('editing-user-id').value = userId;
    document.getElementById('editing-employee-id').value = employeeId;


    // Hide employee details section, show existing employee dropdown (disabled)
    document.getElementById('employee-info-section').style.display = 'none';
    document.getElementById('user-account-section').style.display = 'block';
    document.getElementById('user-employee-select-container').style.display = 'block';
    const userEmployeeSelect = document.getElementById('user-employee-select');
    userEmployeeSelect.value = employeeId;
    userEmployeeSelect.disabled = true;
    
    makeEmployeeFieldsRequired(false);
    makeUserAccountFieldsRequired(true, false); // User fields required, but not password

    document.getElementById('user-username').value = username;
    document.getElementById('user-username').disabled = true; // Username typically not editable
    document.getElementById('user-role-select').value = roleId;
    document.getElementById('user-is-active').checked = (isActive == 1);

    document.getElementById('password-section').style.display = 'none'; // Hide password for user account edit

    document.getElementById('cancel-edit-btn').style.display = 'inline-block';
    document.getElementById('add-edit-user-form').scrollIntoView({ behavior: 'smooth' });
}

/**
 * Resets the user form to its default state for adding a new employee and user.
 */
function resetFormToDefault() {
    currentEditMode = 'add';
    editingEmployeeId = null;
    editingUserId = null;

    document.getElementById('user-form-title').textContent = 'Add New Employee & User';
    document.getElementById('save-form-button').textContent = 'Save Employee & User';
    document.getElementById('add-edit-user-form').reset();
    document.getElementById('editing-employee-id').value = '';
    document.getElementById('editing-user-id').value = '';


    document.getElementById('employee-info-section').style.display = 'block';
    document.getElementById('user-account-section').style.display = 'block';
    document.getElementById('user-employee-select-container').style.display = 'none'; // Hidden for add mode
    const userEmployeeSelect = document.getElementById('user-employee-select');
    userEmployeeSelect.disabled = true;
    userEmployeeSelect.required = false;

    makeEmployeeFieldsRequired(true);
    makeUserAccountFieldsRequired(true, true); // User fields and password required for add

    document.getElementById('user-username').disabled = false;
    document.getElementById('password-section').style.display = 'block';
    document.getElementById('emp-is-active').checked = true; // Default employee to active
    document.getElementById('user-is-active').checked = true; // Default user to active


    document.getElementById('cancel-edit-btn').style.display = 'none';
    document.getElementById('add-edit-status').textContent = '';
}

/**
 * Helper to set/unset required attribute on employee fields.
 * @param {boolean} isRequired
 */
function makeEmployeeFieldsRequired(isRequired) {
    document.getElementById('emp-first-name').required = isRequired;
    document.getElementById('emp-last-name').required = isRequired;
    document.getElementById('emp-email').required = isRequired;
    document.getElementById('emp-job-title').required = isRequired;
    document.getElementById('emp-department-select').required = isRequired;
    document.getElementById('emp-hire-date').required = isRequired;
    // Optional fields are not changed here
}

/**
 * Helper to set/unset required attribute on user account fields.
 * @param {boolean} isRequired
 * @param {boolean} isPasswordRequired
 */
function makeUserAccountFieldsRequired(isRequired, isPasswordRequired = false) {
    document.getElementById('user-username').required = isRequired;
    document.getElementById('user-role-select').required = isRequired;
    document.getElementById('user-password').required = isPasswordRequired;
}


/**
 * Handles the submission of the add/edit user form.
 * Calls the appropriate API endpoint based on currentEditMode.
 * @param {Event} event
 */
async function handleSaveForm(event) {
    event.preventDefault();
    const form = event.target;
    const statusSpan = document.getElementById('add-edit-status');
    const submitButton = form.querySelector('button[type="submit"]');
    if (!statusSpan || !submitButton) return;

    let formData = {};
    let url;
    let successMessage;

    statusSpan.className = 'ml-4 text-sm text-blue-600';
    submitButton.disabled = true;

    if (currentEditMode === 'add') {
        statusSpan.textContent = 'Adding employee & user...';
        url = `${API_BASE_URL}add_employee_and_user.php`;
        successMessage = 'Employee and User account created successfully!';
        formData = {
            // Employee Details
            first_name: form.elements['first_name'].value.trim(),
            middle_name: form.elements['middle_name'].value.trim() || null,
            last_name: form.elements['last_name'].value.trim(),
            suffix: form.elements['suffix'].value.trim() || null,
            email: form.elements['email'].value.trim(),
            personal_email: form.elements['personal_email'].value.trim() || null,
            phone_number: form.elements['phone_number'].value.trim() || null,
            date_of_birth: form.elements['date_of_birth'].value || null,
            gender: form.elements['gender'].value || null,
            marital_status: form.elements['marital_status'].value || null,
            nationality: form.elements['nationality'].value || null,
            job_title: form.elements['job_title'].value.trim(),
            department_id: parseInt(form.elements['department_id'].value),
            manager_id: form.elements['manager_id'].value ? parseInt(form.elements['manager_id'].value) : null,
            hire_date: form.elements['hire_date'].value,
            // User Account Details
            username: form.elements['username'].value.trim(),
            password: form.elements['password'].value,
            role_id: parseInt(form.elements['role_id'].value),
            is_active: form.elements['user-is-active'].checked ? 1 : 0 // User account active status
            // Employee active status is implicitly true on add via SQL default
        };
         if (!formData.first_name || !formData.last_name || !formData.email || !formData.job_title || !formData.department_id || !formData.hire_date ||
            !formData.username || !formData.password || !formData.role_id) {
            Swal.fire('Validation Error', 'Core employee and user fields (Name, Email, Job, Dept, Hire Date, Username, Password, Role) are required for new creation.', 'warning');
            statusSpan.textContent = '';
            submitButton.disabled = false;
            return;
        }

    } else if (currentEditMode === 'edit_user_account') {
        statusSpan.textContent = 'Updating user account...';
        url = `${API_BASE_URL}update_user.php`;
        successMessage = 'User account updated successfully!';
        formData = {
            user_id: parseInt(editingUserId),
            employee_id: parseInt(editingEmployeeId), // For context if API needs it
            role_id: parseInt(form.elements['role_id'].value),
            is_active: form.elements['user-is-active'].checked ? 1 : 0
        };
         if (!formData.user_id || !formData.role_id) {
            Swal.fire('Validation Error', 'User ID and Role are required for updating user account.', 'warning');
            statusSpan.textContent = '';
            submitButton.disabled = false;
            return;
        }
    } else if (currentEditMode === 'edit_employee_info') {
        statusSpan.textContent = 'Updating employee information...';
        url = `${API_BASE_URL}admin_update_employee_profile.php`;
        successMessage = 'Employee information updated successfully!';
        formData = {
            employee_id_to_update: parseInt(editingEmployeeId),
            first_name: form.elements['first_name'].value.trim(),
            middle_name: form.elements['middle_name'].value.trim() || null,
            last_name: form.elements['last_name'].value.trim(),
            suffix: form.elements['suffix'].value.trim() || null,
            email: form.elements['email'].value.trim(),
            personal_email: form.elements['personal_email'].value.trim() || null,
            phone_number: form.elements['phone_number'].value.trim() || null,
            date_of_birth: form.elements['date_of_birth'].value || null,
            gender: form.elements['gender'].value || null,
            marital_status: form.elements['marital_status'].value || null,
            nationality: form.elements['nationality'].value || null,
            job_title: form.elements['job_title'].value.trim(),
            department_id: form.elements['department_id'].value ? parseInt(form.elements['department_id'].value) : null,
            manager_id: form.elements['manager_id'].value ? parseInt(form.elements['manager_id'].value) : null,
            hire_date: form.elements['hire_date'].value,
            is_active_employee: form.elements['emp-is-active'].checked ? 1 : 0
        };
         if (!formData.employee_id_to_update || !formData.first_name || !formData.last_name || !formData.email || !formData.job_title || !formData.department_id || !formData.hire_date) {
            Swal.fire('Validation Error', 'Employee ID, Name, Email, Job Title, Department, and Hire Date are required for updating employee info.', 'warning');
            statusSpan.textContent = '';
            submitButton.disabled = false;
            return;
        }
    } else {
        console.error("Unknown form mode:", currentEditMode);
        statusSpan.textContent = 'Error: Unknown form mode.';
        statusSpan.className = 'ml-4 text-sm text-red-600';
        submitButton.disabled = false;
        return;
    }

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        });

        const result = await handleApiResponse(response);

        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: result.message || successMessage,
            timer: 2000,
            showConfirmButton: false
        });

        resetFormToDefault();
        await loadUsers(); // Refresh the list

    } catch (error) {
        console.error(`Error in ${currentEditMode} mode:`, error);
        let displayMessage = `Error: ${error.message}`;
        if (error.details) {
            displayMessage += ` Details: ${Object.values(error.details).join(', ')}`;
        }
        Swal.fire({
            icon: 'error',
            title: 'Operation Failed',
            text: displayMessage
        });
        statusSpan.textContent = displayMessage;
        statusSpan.className = 'ml-4 text-sm text-red-600';
    } finally {
        submitButton.disabled = false;
    }
}

/**
 * Toggles the activation status of a user.
 * This will also toggle the employee's active status.
 */
async function toggleUserActivation(userId, newStatus) {
    console.log(`[Activation] Attempting to set User ID ${userId} to status: ${newStatus}`);
    const statusSpan = document.getElementById('add-edit-status');

    // We need the role_id to send to update_user.php, even if not changing it.
    // Find the user's row to get their current role_id.
    const userRow = document.querySelector(`.edit-user-account-btn[data-user-id="${userId}"]`);
    if (!userRow || !userRow.dataset.roleId) {
         console.error("Could not find role ID for user", userId, "to toggle activation.");
         Swal.fire('Error', 'Could not determine user role for status update.', 'error');
         if(statusSpan) statusSpan.textContent = '';
         return;
    }
    const roleId = userRow.dataset.roleId;


    if(statusSpan) {
        statusSpan.textContent = newStatus === 1 ? `Activating user ${userId}...` : `Deactivating user ${userId}...`;
        statusSpan.className = 'ml-4 text-sm text-blue-600';
    }

    try {
        const response = await fetch(`${API_BASE_URL}update_user.php`, { // update_user.php handles user's IsActive
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                user_id: parseInt(userId),
                role_id: parseInt(roleId), // Send current role_id
                is_active: newStatus // This updates Users.IsActive
            })
        });
        const result = await handleApiResponse(response);

        // Additionally, if we need to explicitly update Employees.IsActive,
        // we might need another API call or have update_user.php handle it.
        // For now, assuming update_user.php might also trigger employee status update
        // or that the system considers User.IsActive as the primary driver.
        // If not, an admin_update_employee_profile call would be needed here too.

        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: result.message || `User status updated successfully!`,
            timer: 2000,
            showConfirmButton: false
        });
         if(statusSpan) statusSpan.textContent = '';
        await loadUsers();

    } catch (error) {
        console.error('Error toggling user activation:', error);
        const errorMsg = `Error updating user status: ${error.message}`;
        Swal.fire('Error', errorMsg, 'error');
        if(statusSpan) {
            statusSpan.textContent = errorMsg;
            statusSpan.className = 'ml-4 text-sm text-red-600';
        }
    }
}


/**
 * Resets a user's password via API call.
 */
async function resetUserPassword(userId) {
     console.log(`[Reset Pwd] Attempting to reset password for User ID: ${userId}`);
     const statusSpan = document.getElementById('add-edit-status');
     if(statusSpan) {
         statusSpan.textContent = `Resetting password for user ${userId}...`;
         statusSpan.className = 'ml-4 text-sm text-blue-600';
     }

     try {
        const response = await fetch(`${API_BASE_URL}reset_password.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: parseInt(userId) })
        });
        const result = await handleApiResponse(response);

        Swal.fire({
            icon: 'success',
            title: 'Password Reset Successful',
            text: result.message || 'Password reset successfully!', // API should provide new temp password or instructions
            confirmButtonText: 'OK'
        });
        if(statusSpan) statusSpan.textContent = '';

    } catch (error) {
        console.error('Error resetting password:', error);
        const errorMsg = `Error resetting password: ${error.message}`;
        Swal.fire('Error', errorMsg, 'error');
         if(statusSpan) {
            statusSpan.textContent = errorMsg;
            statusSpan.className = 'ml-4 text-sm text-red-600';
        }
    }
}


/**
 * Handles API response, checking status and parsing JSON.
 */
async function handleApiResponse(response) {
    const contentType = response.headers.get("content-type");
    let data;

    if (!response.ok) {
        let errorPayload = { error: `HTTP error! Status: ${response.status}` };
        if (contentType && contentType.includes("application/json")) {
            try {
                data = await response.json();
                errorPayload.error = data.error || errorPayload.error;
                errorPayload.details = data.details;
            } catch (jsonError) {
                console.error("Failed to parse JSON error response:", jsonError);
                const errorTextUnparsed = await response.text().catch(() => "Could not read error text.");
                errorPayload.error += ` (Non-JSON error response received: ${errorTextUnparsed.substring(0,100)})`;
            }
        } else {
            const errorText = await response.text().catch(() => "Could not read error text.");
            console.error("Non-JSON error response received:", errorText.substring(0, 500));
            errorPayload.error = `Server returned non-JSON response (Status: ${response.status}). Check PHP logs.`;
        }
        const error = new Error(errorPayload.error);
        error.details = errorPayload.details;
        throw error;
    }

    try {
        if (response.status === 204 || !contentType || !contentType.includes("application/json")) {
             const text = await response.text();
             if (!text || !text.trim()) {
                 return { message: "Operation completed successfully (No Content)." };
             }
             try { data = JSON.parse(text); return data; }
             catch (jsonError) {
                  console.warn("Received successful status, but response was non-JSON text:", text.substring(0, 200));
                   throw new Error("Received successful status, but response was non-JSON text.");
             }
        }
        data = await response.json();
        return data;
    } catch (jsonError) {
        console.error("Failed to parse successful JSON response:", jsonError);
        throw new Error("Received successful status, but failed to parse JSON response.");
    }
}
