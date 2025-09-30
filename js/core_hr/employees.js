/**
 * Core HR - Employees Module
 * v2.2 - Added View Details modal, Add New button, and placeholder Edit/Deactivate buttons.
 * v2.1 - Updated to display more comprehensive employee details in the table.
 * v2.0 - Refined rendering functions for XSS protection.
 */
import { API_BASE_URL } from '../utils.js'; // Import base URL

// Store employee data globally in this module for modal use
let allEmployeesData = [];
let employeeDetailModal = null;
let employeeDetailModalOverlay = null;
let employeeDetailModalCloseBtn = null;
let employeeDetailModalCloseBtnFooter = null;

/**
 * Initializes modal elements if not already done.
 */
function initializeEmployeeModalElements() {
    if (!employeeDetailModal) {
        employeeDetailModal = document.getElementById('employee-detail-modal');
        employeeDetailModalOverlay = document.getElementById('modal-overlay-employee');
        employeeDetailModalCloseBtn = document.getElementById('modal-close-btn-employee');
        employeeDetailModalCloseBtnFooter = document.getElementById('modal-close-btn-employee-footer');

        if (employeeDetailModalCloseBtn) {
            employeeDetailModalCloseBtn.addEventListener('click', closeEmployeeDetailModal);
        }
        if (employeeDetailModalOverlay) {
            employeeDetailModalOverlay.addEventListener('click', closeEmployeeDetailModal);
        }
        if (employeeDetailModalCloseBtnFooter) {
            employeeDetailModalCloseBtnFooter.addEventListener('click', closeEmployeeDetailModal);
        }
    }
}


/**
 * Displays the Employee Section.
 * Fetches employee data and renders it in a table.
 */
export async function displayEmployeeSection() {
    console.log("[Display] Displaying Employee Section...");
    const pageTitleElement = document.getElementById('page-title');
    const mainContentArea = document.getElementById('main-content-area');
    if (!pageTitleElement || !mainContentArea) {
        console.error("displayEmployeeSection: Core DOM elements not found.");
        return;
    }
    pageTitleElement.textContent = 'Employee Master List';
    mainContentArea.innerHTML = `
        <div class="bg-white p-6 rounded-lg shadow-md border border-[#F7E6CA] space-y-6">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold text-[#4E3B2A]">All Employees</h3>
                <button id="add-new-employee-btn" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition duration-150 ease-in-out flex items-center space-x-2">
                    <i class="fa-solid fa-user-plus"></i>
                    <span>Add New Employee</span>
                </button>
            </div>
            <div id="employee-list-container" class="overflow-x-auto">
                <p class="text-center py-4">Loading employees...</p>
            </div>
        </div>`;
    
    requestAnimationFrame(async () => {
        initializeEmployeeModalElements(); // Ensure modal elements are ready
        const addNewBtn = document.getElementById('add-new-employee-btn');
        if (addNewBtn) {
            addNewBtn.addEventListener('click', () => {
                if (typeof window.navigateToSectionById === 'function') {
                    window.navigateToSectionById('user-management');
                } else {
                    alert("Please navigate to User & Employee Management to add a new employee.");
                }
            });
        }
        await loadEmployees();
    });
}

/**
 * Fetches employee data from the API.
 */
async function loadEmployees() {
    console.log("[Load] Loading Employees...");
    const container = document.getElementById('employee-list-container');
    if (!container) {
         console.error("Employee list container not found!");
         const mainContentArea = document.getElementById('main-content-area');
         if(mainContentArea) mainContentArea.innerHTML = '<p class="text-red-500">Error displaying employee list container.</p>';
         return;
    };
    try {
        const response = await fetch(`${API_BASE_URL}get_employees.php`);
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        const employees = await response.json();
        if (employees.error) {
             console.error("[loadEmployees] API returned error:", employees.error);
             container.innerHTML = `<p class="text-red-500 text-center py-4">Error: ${employees.error}</p>`;
        } else {
             allEmployeesData = employees; // Store for modal
             renderEmployeeTable(employees);
        }
    } catch (error) {
        console.error('[loadEmployees] Error loading employees:', error);
        container.innerHTML = `<p class="text-red-500 text-center py-4">Could not load employee data. ${error.message}</p>`;
    }
}

 /**
 * Renders the employee data into an HTML table.
 * @param {Array} employees - An array of employee objects.
 */
function renderEmployeeTable(employees) {
    console.log("[Render] Rendering Employee Table...");
    const container = document.getElementById('employee-list-container');
    if (!container) return;

    container.innerHTML = '';

    if (!employees || employees.length === 0) {
        const noDataMessage = document.createElement('p');
        noDataMessage.className = 'text-center py-4 text-gray-500';
        noDataMessage.textContent = 'No employees found.';
        container.appendChild(noDataMessage);
        return;
    }

    const table = document.createElement('table');
    table.className = 'min-w-full divide-y divide-gray-200 border border-gray-300';

    const thead = table.createTHead();
    thead.className = 'bg-gray-50';
    const headerRow = thead.insertRow();
    // Define table headers
    const headers = [
        'Emp. ID', 'Full Name', 'Job Title', 'Department', 'Work Email', 
        'Hire Date', 'Status', 'Actions'
    ];
    headers.forEach(text => {
        const th = document.createElement('th');
        th.scope = 'col';
        th.className = 'px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider';
        th.textContent = text;
        headerRow.appendChild(th);
    });

    const tbody = table.createTBody();
    tbody.className = 'bg-white divide-y divide-gray-200 employee-actions-container'; // Added class for event delegation

    employees.forEach(emp => {
        const row = tbody.insertRow();
        row.id = `emp-row-${emp.EmployeeID}`;

        const createCell = (text, allowWrap = false) => {
            const cell = row.insertCell();
            cell.className = 'px-3 py-2 text-sm text-gray-700';
            if (allowWrap) {
                cell.classList.add('whitespace-normal', 'break-words');
            } else {
                cell.classList.add('whitespace-nowrap');
            }
            cell.textContent = text ?? '-'; // Handle null/undefined
            return cell;
        };
        
        const fullName = `${emp.FirstName || ''} ${emp.MiddleName || ''} ${emp.LastName || ''} ${emp.Suffix || ''}`.replace(/\s+/g, ' ').trim();

        createCell(emp.EmployeeID).classList.add('text-gray-500');
        createCell(fullName).classList.add('font-medium', 'text-gray-900');
        createCell(emp.JobTitle);
        createCell(emp.DepartmentName);
        createCell(emp.Email);
        createCell(emp.HireDateFormatted || emp.HireDate);
        
        const statusCell = createCell(emp.Status);
        statusCell.classList.add('font-semibold', emp.IsActive == 1 ? 'text-green-600' : 'text-red-600');

        // Actions Cell
        const actionsCell = row.insertCell();
        actionsCell.className = 'px-3 py-2 whitespace-nowrap text-sm space-x-1';

        const viewBtn = document.createElement('button');
        viewBtn.className = 'text-blue-600 hover:text-blue-800 view-employee-btn p-1';
        viewBtn.innerHTML = '<i class="fas fa-eye"></i>';
        viewBtn.title = 'View Details';
        viewBtn.dataset.employeeId = emp.EmployeeID;
        actionsCell.appendChild(viewBtn);

        const editBtn = document.createElement('button');
        editBtn.className = 'text-purple-600 hover:text-purple-800 edit-employee-btn p-1';
        editBtn.innerHTML = '<i class="fas fa-edit"></i>';
        editBtn.title = 'Edit Employee (Admin)';
        editBtn.dataset.employeeId = emp.EmployeeID; // For linking to admin edit
        actionsCell.appendChild(editBtn);
        
        const toggleActiveBtn = document.createElement('button');
        toggleActiveBtn.className = `p-1 ${emp.IsActive == 1 ? 'text-red-600 hover:text-red-800 deactivate-employee-btn' : 'text-green-600 hover:text-green-800 activate-employee-btn'}`;
        toggleActiveBtn.innerHTML = emp.IsActive == 1 ? '<i class="fas fa-toggle-off"></i>' : '<i class="fas fa-toggle-on"></i>';
        toggleActiveBtn.title = emp.IsActive == 1 ? 'Deactivate Employee/User (Admin)' : 'Activate Employee/User (Admin)';
        toggleActiveBtn.dataset.userId = emp.UserID; // Assuming UserID is available
        toggleActiveBtn.dataset.employeeId = emp.EmployeeID;
        actionsCell.appendChild(toggleActiveBtn);

    });

    container.appendChild(table);
    attachEmployeeActionListeners(); // Attach listeners after table is rendered
}

/**
 * Attaches event listeners for employee actions.
 */
function attachEmployeeActionListeners() {
    const container = document.querySelector('.employee-actions-container');
    if (container) {
        // Remove existing listener to prevent duplicates if re-rendering
        container.removeEventListener('click', handleEmployeeActionClick);
        container.addEventListener('click', handleEmployeeActionClick);
    }
}

/**
 * Handles clicks on action buttons in the employee table.
 * @param {Event} event
 */
function handleEmployeeActionClick(event) {
    const targetButton = event.target.closest('button');
    if (!targetButton) return;

    const employeeId = targetButton.dataset.employeeId;

    if (targetButton.classList.contains('view-employee-btn')) {
        const employee = allEmployeesData.find(emp => emp.EmployeeID == employeeId);
        if (employee) {
            openEmployeeDetailModal(employee);
        } else {
            Swal.fire('Error', 'Could not find employee details.', 'error');
        }
    } else if (targetButton.classList.contains('edit-employee-btn')) {
        // For now, guide user to admin section.
        // Later, this could directly trigger the edit mode in user_management.js
        Swal.fire({
            title: 'Edit Employee',
            text: `To edit Employee ID ${employeeId}, please go to the "User & Employee Management" section and use the "Edit Info" option there.`,
            icon: 'info',
            confirmButtonText: 'Go to User Management',
            showCancelButton: true,
            cancelButtonText: 'Dismiss'
        }).then((result) => {
            if (result.isConfirmed && typeof window.navigateToSectionById === 'function') {
                window.navigateToSectionById('user-management');
            }
        });
    } else if (targetButton.classList.contains('deactivate-employee-btn') || targetButton.classList.contains('activate-employee-btn')) {
        const userId = targetButton.dataset.userId;
        if (!userId) {
            Swal.fire('Error', 'User ID not found for this employee. Action cannot be performed here. Please use User Management.', 'error');
            return;
        }
        // For now, guide user to admin section.
        // Later, this could call a shared toggleUserActivation function.
        Swal.fire({
            title: 'Activate/Deactivate User',
            text: `To change the active status for Employee ID ${employeeId} (User ID: ${userId}), please go to the "User & Employee Management" section.`,
            icon: 'info',
             confirmButtonText: 'Go to User Management',
            showCancelButton: true,
            cancelButtonText: 'Dismiss'
        }).then((result) => {
            if (result.isConfirmed && typeof window.navigateToSectionById === 'function') {
                window.navigateToSectionById('user-management');
            }
        });
    }
}

/**
 * Populates and opens the employee detail modal.
 * @param {object} emp - The employee data object.
 */
function openEmployeeDetailModal(emp) {
    if (!employeeDetailModal) {
        console.error("Employee detail modal not initialized.");
        Swal.fire('UI Error', 'Cannot display employee details modal.', 'error');
        return;
    }
    const S = (value, placeholder = 'N/A') => value || placeholder;
    const webRootPath = '/hr34/';

    let photoHtml = `<div class="w-24 h-24 bg-gray-200 rounded-full flex items-center justify-center text-gray-500 text-3xl">
                        ${S(emp.FirstName, '?').charAt(0)}${S(emp.LastName, '?').charAt(0)}
                     </div>`;
    if (emp.EmployeePhotoPath) {
        const photoUrl = emp.EmployeePhotoPath.startsWith('http') ? emp.EmployeePhotoPath : `${webRootPath}${emp.EmployeePhotoPath}`;
        photoHtml = `<img src="${S(photoUrl)}" alt="Profile Photo" class="h-24 w-24 rounded-full object-cover border">`;
    }
    
    const fullName = `${S(emp.FirstName)} ${S(emp.MiddleName)} ${S(emp.LastName)} ${S(emp.Suffix)}`.replace(/\s+/g, ' ').trim();

    const contentDiv = document.getElementById('employee-detail-content');
    if (contentDiv) {
        contentDiv.innerHTML = `
            <div class="flex items-center space-x-4 mb-4">
                ${photoHtml}
                <div>
                    <h4 class="text-xl font-semibold text-[#4E3B2A]">${fullName}</h4>
                    <p class="text-gray-600">${S(emp.JobTitle)}</p>
                    <p class="text-sm text-gray-500">${S(emp.DepartmentName)}</p>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-3">
                <p><strong class="font-medium text-gray-600">Employee ID:</strong> ${S(emp.EmployeeID)}</p>
                <p><strong class="font-medium text-gray-600">Status:</strong> <span class="${emp.IsActive == 1 ? 'text-green-600' : 'text-red-600'} font-semibold">${S(emp.Status)}</span></p>
                
                <p><strong class="font-medium text-gray-600">Work Email:</strong> ${S(emp.Email)}</p>
                <p><strong class="font-medium text-gray-600">Personal Email:</strong> ${S(emp.PersonalEmail)}</p>
                <p><strong class="font-medium text-gray-600">Phone:</strong> ${S(emp.PhoneNumber)}</p>
                <p><strong class="font-medium text-gray-600">Hire Date:</strong> ${S(emp.HireDateFormatted || emp.HireDate)}</p>
                
                <p><strong class="font-medium text-gray-600">Date of Birth:</strong> ${S(emp.DateOfBirthFormatted || emp.DateOfBirth)}</p>
                <p><strong class="font-medium text-gray-600">Gender:</strong> ${S(emp.Gender)}</p>
                <p><strong class="font-medium text-gray-600">Marital Status:</strong> ${S(emp.MaritalStatus)}</p>
                <p><strong class="font-medium text-gray-600">Nationality:</strong> ${S(emp.Nationality)}</p>
                
                <p class="md:col-span-2"><strong class="font-medium text-gray-600">Address:</strong> ${S(emp.AddressLine1)} ${S(emp.AddressLine2, '')}, ${S(emp.City)}, ${S(emp.StateProvince)} ${S(emp.PostalCode)}, ${S(emp.Country)}</p>
                
                <p><strong class="font-medium text-gray-600">Manager:</strong> ${S(emp.ManagerName)}</p>
                <p><strong class="font-medium text-gray-600">User ID:</strong> ${S(emp.UserID)}</p>

                <h5 class="md:col-span-2 text-md font-semibold text-gray-700 mt-3 pt-2 border-t">Emergency Contact</h5>
                <p><strong class="font-medium text-gray-600">Name:</strong> ${S(emp.EmergencyContactName)}</p>
                <p><strong class="font-medium text-gray-600">Relationship:</strong> ${S(emp.EmergencyContactRelationship)}</p>
                <p><strong class="font-medium text-gray-600">Phone:</strong> ${S(emp.EmergencyContactPhone)}</p>
                
                ${emp.TerminationDate ? `
                    <h5 class="md:col-span-2 text-md font-semibold text-gray-700 mt-3 pt-2 border-t">Termination Info</h5>
                    <p><strong class="font-medium text-gray-600">Termination Date:</strong> ${S(emp.TerminationDateFormatted || emp.TerminationDate)}</p>
                    <p class="md:col-span-2"><strong class="font-medium text-gray-600">Reason:</strong> ${S(emp.TerminationReason)}</p>
                ` : ''}
            </div>
        `;
    }
    employeeDetailModal.classList.remove('hidden');
    employeeDetailModal.classList.add('flex');
}

/**
 * Closes the employee detail modal.
 */
function closeEmployeeDetailModal() {
    if (employeeDetailModal) {
        employeeDetailModal.classList.add('hidden');
        employeeDetailModal.classList.remove('flex');
    }
}
