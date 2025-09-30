/**
 * Leave Management Module
 * v3.0 - Integrated SweetAlert for notifications and confirmations.
 * v2.9 - Added console logs to debug Leave Types loading.
 * - Removed invalid comment syntax from template literal.
 * - Added conditional display of Approver Comments column.
 * - Applied role-based UI controls similar to Claims module.
 * - Fixed duplicate function declaration error.
 * - Added Role-based UI control for Leave Request form.
 * - Added Leave Request Approval/Rejection functionality (Admin View)
 */
import { API_BASE_URL, populateEmployeeDropdown } from '../utils.js'; // Import shared functions/constants

// --- DOM Element References ---
let pageTitleElement;
let mainContentArea;
let currentlyLoadedEmployeeId = null; 

/**
 * Initializes common elements used by the leave module.
 */
function initializeLeaveElements() {
    pageTitleElement = document.getElementById('page-title');
    mainContentArea = document.getElementById('main-content-area');
    if (!pageTitleElement || !mainContentArea) {
        console.error("Leave Module: Core DOM elements (page-title or main-content-area) not found!");
        return false;
    }
    return true;
}

// ==================================
// === Leave Requests Section ===
// ==================================
export async function displayLeaveRequestsSection() {
    console.log("[Display] Displaying Leave Requests Section...");
    if (!initializeLeaveElements()) return;

    const user = window.currentUser;
    const userRole = user?.role_name;
    const employeeId = user?.employee_id;
    const isAdminType = ['System Admin', 'HR Admin', 'Manager'].includes(userRole);
    const isEmployeeRole = userRole === 'Employee';

    pageTitleElement.textContent = 'Leave Requests';

    let submitFormHtml = '';
    if (isEmployeeRole) {
        submitFormHtml = `
            <div class="border-b border-gray-200 pb-6">
                <h3 class="text-lg font-semibold text-[#4E3B2A] mb-3 font-header">Submit New Leave Request</h3>
                <form id="submit-leave-request-form" class="space-y-4">
                    <input type="hidden" name="employee_id" value="${employeeId || ''}">
                    <p class="text-sm mb-2">Submitting leave for: <strong>${user?.full_name || 'Yourself'}</strong></p>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div>
                            <label for="lr-leave-type-select" class="block text-sm font-medium text-gray-700 mb-1">Leave Type:</label>
                            <select id="lr-leave-type-select" name="leave_type_id" required class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                                <option value="">Loading leave types...</option>
                            </select>
                        </div>
                        <div>
                            <label for="lr-start-date" class="block text-sm font-medium text-gray-700 mb-1">Start Date:</label>
                            <input type="date" id="lr-start-date" name="start_date" required class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                        </div>
                        <div>
                            <label for="lr-end-date" class="block text-sm font-medium text-gray-700 mb-1">End Date:</label>
                            <input type="date" id="lr-end-date" name="end_date" required class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                        </div>
                        <div>
                            <label for="lr-num-days" class="block text-sm font-medium text-gray-700 mb-1">Number of Days:</label>
                            <input type="number" step="0.1" min="0.5" id="lr-num-days" name="number_of_days" required placeholder="e.g., 1 or 0.5" class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                         </div>
                         <div class="md:col-span-2 lg:col-span-3">
                            <label for="lr-reason" class="block text-sm font-medium text-gray-700 mb-1">Reason (Optional):</label>
                            <textarea id="lr-reason" name="reason" rows="2" placeholder="Reason for leave..." class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]"></textarea>
                        </div>
                    </div>
                     <div class="pt-2">
                        <button type="submit" class="px-4 py-2 bg-[#4727ff] text-white rounded-md hover:bg-[#3a1fcc] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#4727ff] transition duration-150 ease-in-out">
                            Submit Request
                        </button>
                        </div>
                </form>
            </div>`;
    }

    mainContentArea.innerHTML = `
        <div class="bg-white p-6 rounded-lg shadow-md border border-[#F7E6CA] space-y-6">
            ${submitFormHtml} <div>
                 <h3 class="text-lg font-semibold text-[#4E3B2A] mb-3 font-header" id="lr-list-title">Leave Request History</h3>
                 <div class="flex flex-wrap gap-4 mb-4 items-end" id="lr-admin-filters" style="display: none;"> <div>
                        <label for="filter-lr-employee" class="block text-sm font-medium text-gray-700 mb-1">Filter by Employee:</label>
                        <select id="filter-lr-employee" class="w-full sm:w-auto p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                            <option value="">All Employees</option>
                        </select>
                    </div>
                     <div>
                        <label for="filter-lr-status" class="block text-sm font-medium text-gray-700 mb-1">Filter by Status:</label>
                        <select id="filter-lr-status" class="w-full sm:w-auto p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                            <option value="">All Statuses</option>
                            <option value="Pending">Pending</option>
                            <option value="Approved">Approved</option>
                            <option value="Rejected">Rejected</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div>
                        <button id="filter-lr-btn" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-150 ease-in-out">
                            Filter Requests
                        </button>
                    </div>
                 </div>
                 <div id="leave-requests-list-container" class="overflow-x-auto">
                    <p>Loading leave requests...</p>
                 </div>
            </div>
        </div>`;

    requestAnimationFrame(async () => {
        const submitForm = document.getElementById('submit-leave-request-form');
        if (submitForm) {
            await populateLeaveTypeDropdown('lr-leave-type-select');
            if (!submitForm.hasAttribute('data-listener-attached')) {
                submitForm.addEventListener('submit', handleLeaveRequestSubmit);
                submitForm.setAttribute('data-listener-attached', 'true');
            }
        } else {
            console.log("Leave request submission form not rendered for this role.");
        }

        const listTitle = document.getElementById('lr-list-title');
        const adminFilters = document.getElementById('lr-admin-filters');
        if (isAdminType) {
            if(listTitle) listTitle.textContent = "All Leave Requests";
            if(adminFilters) adminFilters.style.display = 'flex';
            await populateEmployeeDropdown('filter-lr-employee', true);
            const filterBtn = document.getElementById('filter-lr-btn');
            if (filterBtn) {
                 if (!filterBtn.hasAttribute('data-listener-attached')) {
                    filterBtn.addEventListener('click', applyLeaveRequestFilter);
                    filterBtn.setAttribute('data-listener-attached', 'true');
                 }
            } else { console.error("Admin Filter button not found."); }
            await loadLeaveRequests();
            currentlyLoadedEmployeeId = null;
        } else {
            if(listTitle) listTitle.textContent = "My Leave Request History";
            if(adminFilters) adminFilters.style.display = 'none';
            if(employeeId) {
                currentlyLoadedEmployeeId = employeeId;
                await loadLeaveRequests(employeeId);
            } else {
                 console.error("Cannot load leave requests: Employee ID missing for current user.");
                 const container = document.getElementById('leave-requests-list-container');
                 if(container) container.innerHTML = '<p class="text-red-500 text-center py-4">Error: Could not load requests. User information missing.</p>';
            }
        }
    });
}
async function populateLeaveTypeDropdown(selectElementId) {
    const selectElement = document.getElementById(selectElementId);
    if (!selectElement) {
        console.error(`[populateLeaveTypeDropdown] Element ID '${selectElementId}' not found.`);
        return;
    }
    selectElement.innerHTML = '<option value="" disabled selected>Loading leave types...</option>';

    try {
        const response = await fetch(`${API_BASE_URL}get_leave_types.php`);
        const leaveTypes = await handleApiResponse(response);

        selectElement.innerHTML = '<option value="">-- Select Leave Type --</option>';
        if (leaveTypes.length > 0) {
            leaveTypes.forEach(type => {
                const option = document.createElement('option');
                option.value = type.LeaveTypeID;
                option.textContent = type.TypeName;
                selectElement.appendChild(option);
            });
        } else {
            selectElement.innerHTML = '<option value="" disabled>No leave types found</option>';
        }
    } catch (error) {
        console.error('Error populating leave type dropdown:', error);
        selectElement.innerHTML = `<option value="" disabled>Error loading types</option>`;
    }
}
async function handleLeaveRequestSubmit(event) {
     event.preventDefault();
    const form = event.target;
    const submitButton = form.querySelector('button[type="submit"]');
    if (!submitButton) return;

    const startDate = form.elements['start_date'].value;
    const endDate = form.elements['end_date'].value;
    const numDays = form.elements['number_of_days'].value;

    if (endDate < startDate) {
        Swal.fire({
            icon: 'error',
            title: 'Validation Error',
            text: 'End Date cannot be before Start Date.',
            confirmButtonColor: '#4E3B2A'
        });
        return;
    }
    if (parseFloat(numDays) <= 0) {
        Swal.fire({
            icon: 'error',
            title: 'Validation Error',
            text: 'Number of Days must be positive.',
            confirmButtonColor: '#4E3B2A'
        });
        return;
    }

    const formData = {
        employee_id: form.elements['employee_id'].value,
        leave_type_id: form.elements['leave_type_id'].value,
        start_date: startDate,
        end_date: endDate,
        number_of_days: parseFloat(numDays),
        reason: form.elements['reason'].value.trim() || null
    };

    Swal.fire({
        title: 'Processing...',
        text: 'Submitting your leave request, please wait.',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
    submitButton.disabled = true;

    try {
        const response = await fetch(`${API_BASE_URL}submit_leave_request.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        });

        const result = await handleApiResponse(response);

        Swal.fire({
            icon: 'success',
            title: 'Request Submitted!',
            text: result.message || 'Leave request submitted successfully!',
            confirmButtonColor: '#4E3B2A',
            timer: 2500
        });
        form.reset();

        console.log(`Leave request submitted. Refreshing list for employee ID: ${currentlyLoadedEmployeeId}.`);
        const statusFilter = document.getElementById('filter-lr-status')?.value || null;
        await loadLeaveRequests(currentlyLoadedEmployeeId, currentlyLoadedEmployeeId === null ? statusFilter : null);
        console.log(`Finished refreshing list for employee ID: ${currentlyLoadedEmployeeId}.`);

    } catch (error) {
        console.error('Error submitting leave request:', error);
        let displayMessage = `Error: ${error.message}`;
        if (error.details) { displayMessage += ` Details: ${JSON.stringify(error.details)}`; }
        Swal.fire({
            icon: 'error',
            title: 'Submission Error',
            text: displayMessage,
            confirmButtonColor: '#4E3B2A'
        });
    } finally {
        submitButton.disabled = false;
        if (Swal.isLoading()) { Swal.close(); }
    }
}
function applyLeaveRequestFilter() {
    const employeeId = document.getElementById('filter-lr-employee')?.value || null;
    const status = document.getElementById('filter-lr-status')?.value || null;
    currentlyLoadedEmployeeId = employeeId;
    loadLeaveRequests(employeeId, status);
}
async function loadLeaveRequests(employeeId = null, status = null) {
    console.log(`[Load] Loading Leave Requests... (Employee: ${employeeId || 'All'}, Status: ${status || 'All'})`);

    const container = document.getElementById('leave-requests-list-container');
    if (!container) return;
    container.innerHTML = '<p class="text-center py-4">Loading leave requests...</p>';

    const params = new URLSearchParams();
    if (employeeId) params.append('employee_id', employeeId);
    if (status) params.append('status', status);

    const url = `${API_BASE_URL}get_leave_requests.php?${params.toString()}`;

    try {
        const response = await fetch(url);
        const requests = await handleApiResponse(response);
        renderLeaveRequestsTable(requests);
    } catch (error) {
        console.error('Error loading leave requests:', error);
        container.innerHTML = `<p class="text-red-500 text-center py-4">Could not load leave requests. ${error.message}</p>`;
    }
}
function renderLeaveRequestsTable(requests) {
    console.log("[Render] Rendering Leave Requests Table...");
    const container = document.getElementById('leave-requests-list-container');
    if (!container) return;

    container.innerHTML = '';

    if (!requests || requests.length === 0) {
        const p = document.createElement('p');
        p.className = 'text-center py-4 text-gray-500';
        p.textContent = 'No leave requests found.';
        container.appendChild(p);
        return;
    }

    const user = window.currentUser;
    const canApproveReject = ['System Admin', 'HR Admin', 'Manager'].includes(user?.role_name);

    const table = document.createElement('table');
    table.className = 'min-w-full divide-y divide-gray-200 border border-gray-300';

    const thead = table.createTHead();
    thead.className = 'bg-gray-50';
    const headerRow = thead.insertRow();
    let headers = ['Req ID'];
    if (canApproveReject) headers.push('Employee');
    headers.push('Leave Type', 'Start Date', 'End Date', 'Days', 'Submitted', 'Status');
    if (canApproveReject) headers.push('Approver Comments');
    if (canApproveReject) headers.push('Actions');

    headers.forEach(text => {
        const th = document.createElement('th');
        th.scope = 'col';
        th.className = 'px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider';
        th.textContent = text;
        headerRow.appendChild(th);
    });

    const tbody = table.createTBody();
    tbody.className = 'bg-white divide-y divide-gray-200 leave-request-action-container';

    requests.forEach(req => {
        const row = tbody.insertRow();
        row.id = `lr-row-${req.RequestID}`;

        const createCell = (text, allowWrap = false) => {
            const cell = row.insertCell();
            cell.className = 'px-4 py-3 text-sm';
            if (allowWrap) {
                 cell.classList.add('whitespace-normal', 'break-words');
            } else {
                 cell.classList.add('whitespace-nowrap');
            }
            cell.textContent = text ?? 'N/A';
            return cell;
        };

        createCell(req.RequestID).classList.add('text-gray-500');
        if (canApproveReject) {
            createCell(req.EmployeeName).classList.add('font-medium', 'text-gray-900');
        }
        createCell(req.LeaveTypeName).classList.add('text-gray-700');
        createCell(req.StartDateFormatted ?? req.StartDate).classList.add('text-gray-700');
        createCell(req.EndDateFormatted ?? req.EndDate).classList.add('text-gray-700');
        createCell(req.NumberOfDays).classList.add('text-gray-700', 'text-center');
        createCell(req.RequestDateFormatted ?? req.RequestDate).classList.add('text-gray-500');

        const statusCell = createCell(req.Status);
        statusCell.classList.add('font-semibold');
        let statusColor = 'text-gray-600';
        if (req.Status === 'Approved') statusColor = 'text-green-600';
        else if (req.Status === 'Rejected' || req.Status === 'Cancelled') statusColor = 'text-red-600';
        else if (req.Status === 'Pending') statusColor = 'text-yellow-600';
        statusCell.classList.add(statusColor);

        if (canApproveReject) {
             const commentCell = createCell(req.ApproverComments || '-', true);
             commentCell.classList.add('text-gray-600');
        }

        if (canApproveReject) {
            const actionsCell = row.insertCell();
            actionsCell.className = 'px-4 py-3 whitespace-nowrap text-sm font-medium space-x-1';
            if (req.Status === 'Pending') {
                const approveBtn = document.createElement('button');
                approveBtn.className = 'text-green-600 hover:text-green-800 approve-lr-btn p-1';
                approveBtn.dataset.requestId = req.RequestID;
                approveBtn.title = 'Approve';
                approveBtn.innerHTML = '<i class="fas fa-check-circle fa-fw"></i>';
                actionsCell.appendChild(approveBtn);

                const rejectBtn = document.createElement('button');
                rejectBtn.className = 'text-red-600 hover:text-red-800 reject-lr-btn p-1';
                rejectBtn.dataset.requestId = req.RequestID;
                rejectBtn.title = 'Reject';
                rejectBtn.innerHTML = '<i class="fas fa-times-circle fa-fw"></i>';
                actionsCell.appendChild(rejectBtn);
            } else {
                const processedSpan = document.createElement('span');
                processedSpan.className = 'text-gray-400 text-xs italic';
                processedSpan.textContent = 'Processed';
                actionsCell.appendChild(processedSpan);
            }
        }
    });

    container.appendChild(table);

    if (canApproveReject) {
        attachLeaveRequestActionListeners();
    }
}
function attachLeaveRequestActionListeners() {
    const container = document.querySelector('.leave-request-action-container');
    if (container) {
        container.removeEventListener('click', handleLeaveRequestAction);
        container.addEventListener('click', handleLeaveRequestAction);
    } else {
        console.warn("Leave request action container not found for attaching listeners.");
    }
}
async function handleLeaveRequestAction(event) {
    const approveButton = event.target.closest('.approve-lr-btn');
    const rejectButton = event.target.closest('.reject-lr-btn');

    if (approveButton) {
        const requestId = approveButton.dataset.requestId;
        const { value: comments } = await Swal.fire({
            title: `Approve Leave Request ID ${requestId}?`,
            input: 'textarea',
            inputLabel: 'Optional Comments',
            inputPlaceholder: 'Enter approval comments here...',
            showCancelButton: true,
            confirmButtonText: 'Approve',
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#aaa'
        });
        if (comments !== undefined) {
            updateLeaveRequestStatus(requestId, 'Approved', comments || null);
        }
    } else if (rejectButton) {
        const requestId = rejectButton.dataset.requestId;
        const { value: reason } = await Swal.fire({
            title: `Reject Leave Request ID ${requestId}?`,
            input: 'textarea',
            inputLabel: 'Reason for Rejection (Optional)',
            inputPlaceholder: 'Enter rejection reason here...',
            showCancelButton: true,
            confirmButtonText: 'Reject',
            confirmButtonColor: '#d33',
            cancelButtonColor: '#aaa'
        });
        if (reason !== undefined) { 
            updateLeaveRequestStatus(requestId, 'Rejected', reason || null);
        }
    }
}
async function updateLeaveRequestStatus(requestId, newStatus, comments = null) {
    console.log(`Updating Leave Request ${requestId} to status: ${newStatus}`);
    
    Swal.fire({
        title: 'Processing...',
        text: `Updating leave request to ${newStatus}...`,
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    const user = window.currentUser;
    const approverId = user?.employee_id;
    if (!approverId) {
        console.error("Approver Employee ID not found in current user session.");
        Swal.fire({ icon: 'error', title: 'Error', text: 'Could not identify approver.', confirmButtonColor: '#4E3B2A' });
        return;
    }

    try {
        const response = await fetch(`${API_BASE_URL}update_leave_request_status.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                request_id: parseInt(requestId),
                new_status: newStatus,
                approver_id: approverId,
                comments: comments
            })
        });
        const result = await handleApiResponse(response);

        Swal.fire({
            icon: 'success',
            title: 'Status Updated!',
            text: result.message || `Leave request ${newStatus.toLowerCase()} successfully!`,
            timer: 2000,
            showConfirmButton: false,
            confirmButtonColor: '#4E3B2A'
        });

        const employeeFilter = document.getElementById('filter-lr-employee')?.value || currentlyLoadedEmployeeId || null;
        const statusFilter = document.getElementById('filter-lr-status')?.value || null;
        loadLeaveRequests(employeeFilter, statusFilter);

    } catch (error) {
        console.error(`Error ${newStatus.toLowerCase()}ing leave request:`, error);
        Swal.fire({
            icon: 'error',
            title: 'Update Failed',
            text: `Failed to ${newStatus.toLowerCase()} leave request: ${error.message}`,
            confirmButtonColor: '#4E3B2A'
        });
    } finally {
        if (Swal.isLoading()) { Swal.close(); }
    }
}

// ==================================
// === Leave Balances Section ===
// ==================================
export async function displayLeaveBalancesSection() {
    console.log("[Display] Displaying Leave Balances Section...");
    if (!initializeLeaveElements()) return;

    const user = window.currentUser;
    const userRole = user?.role_name;
    const employeeId = user?.employee_id;
    const isAdminType = ['System Admin', 'HR Admin', 'Manager'].includes(userRole);
    const currentYear = new Date().getFullYear();

    pageTitleElement.textContent = 'Leave Balances';

    let filterHtml = '';
    if (isAdminType) {
        filterHtml = `
            <div>
                <label for="filter-lb-employee" class="block text-sm font-medium text-gray-700 mb-1">Select Employee:</label>
                <select id="filter-lb-employee" class="w-full sm:w-auto p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                    <option value="">Loading employees...</option>
                </select>
            </div>`;
    }

    mainContentArea.innerHTML = `
        <div class="bg-white p-6 rounded-lg shadow-md border border-[#F7E6CA] space-y-6">
            <h3 class="text-lg font-semibold text-[#4E3B2A] mb-3 font-header">Employee Leave Balances</h3>
            <div class="flex flex-wrap gap-4 mb-4 items-end">
                 ${filterHtml}
                <div>
                    <label for="filter-lb-year" class="block text-sm font-medium text-gray-700 mb-1">Year:</label>
                    <input type="number" id="filter-lb-year" value="${currentYear}" min="2020" max="2099" class="w-full sm:w-auto p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                </div>
                <div>
                    <button id="filter-lb-btn" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-150 ease-in-out">
                        View Balances
                    </button>
                </div>
            </div>
             <div id="leave-balances-container" class="overflow-x-auto">
                 <p class="text-center py-4 text-gray-500">Select filters and click "View Balances".</p>
             </div>
        </div>`;

    requestAnimationFrame(async () => {
        if (isAdminType) {
            await populateEmployeeDropdown('filter-lb-employee');
        }

        const filterBtn = document.getElementById('filter-lb-btn');
        if (filterBtn) {
             if (!filterBtn.hasAttribute('data-listener-attached')) {
                filterBtn.addEventListener('click', applyLeaveBalanceFilter);
                filterBtn.setAttribute('data-listener-attached', 'true');
             }
        } else { console.error("Filter Leave Balance button not found."); }

        if (!isAdminType && employeeId) {
            currentlyLoadedEmployeeId = employeeId;
            await loadLeaveBalances(employeeId, currentYear);
        }
    });
}
async function waitForElementOptions(selectElement, timeout = 3000) {
     return new Promise((resolve, reject) => {
        const startTime = Date.now();
        const interval = setInterval(() => {
            if (selectElement.options.length > 1 || (selectElement.options.length === 1 && selectElement.options[0].value !== "")) {
                clearInterval(interval);
                resolve();
            } else if (Date.now() - startTime > timeout) {
                clearInterval(interval);
                console.warn(`Timeout waiting for options in ${selectElement.id}`);
                resolve();
            }
        }, 100);
    });
}
function applyLeaveBalanceFilter() {
    const user = window.currentUser;
    const isAdminType = ['System Admin', 'HR Admin', 'Manager'].includes(user?.role_name);
    let employeeId = null;

    if (isAdminType) {
        employeeId = document.getElementById('filter-lb-employee')?.value;
    } else {
        employeeId = user?.employee_id;
    }

    const year = document.getElementById('filter-lb-year')?.value;

    if (!employeeId) {
        const container = document.getElementById('leave-balances-container');
        if (container) container.innerHTML = '<p class="text-center py-4 text-red-500">Please select an employee (or user ID missing).</p>';
        return;
    }
    if (!year || year < 2000) {
         const container = document.getElementById('leave-balances-container');
         if (container) container.innerHTML = '<p class="text-center py-4 text-red-500">Please enter a valid year.</p>';
         return;
    }

    currentlyLoadedEmployeeId = employeeId;
    loadLeaveBalances(employeeId, year);
}
async function loadLeaveBalances(employeeId, year) {
    console.log(`[Load] Loading Leave Balances for Employee: ${employeeId}, Year: ${year}`);
    const container = document.getElementById('leave-balances-container');
    if (!container) return;
    container.innerHTML = '<p class="text-center py-4">Loading leave balances...</p>';

    const params = new URLSearchParams();
    params.append('employee_id', employeeId);
    params.append('year', year);

    const url = `${API_BASE_URL}get_leave_balances.php?${params.toString()}`;

    try {
        const response = await fetch(url);
        const balances = await handleApiResponse(response);
        renderLeaveBalances(balances, year);
    } catch (error) {
        console.error('Error loading leave balances:', error);
        container.innerHTML = `<p class="text-red-500 text-center py-4">Could not load leave balances. ${error.message}</p>`;
    }
}
function renderLeaveBalances(balances, year) {
     console.log("[Render] Rendering Leave Balances...");
    const container = document.getElementById('leave-balances-container');
    if (!container) return;

    container.innerHTML = '';

    if (!balances || balances.length === 0) {
        const p = document.createElement('p');
        p.className = 'text-center py-4 text-gray-500';
        p.textContent = `No leave balance records found for ${year}.`;
        container.appendChild(p);
        return;
    }

    const heading = document.createElement('h4');
    heading.className = 'text-md font-medium text-gray-700 mb-2 font-header';
    heading.textContent = `Balances for ${year}`;
    container.appendChild(heading);

    const table = document.createElement('table');
    table.className = 'min-w-full divide-y divide-gray-200 border border-gray-300';

    const thead = table.createTHead();
    thead.className = 'bg-gray-50';
    const headerRow = thead.insertRow();
    const headers = ['Leave Type', 'Entitled', 'Accrued', 'Used', 'Available'];
    headers.forEach(text => {
        const th = document.createElement('th');
        th.scope = 'col';
        th.className = 'px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider';
        if (['Entitled', 'Accrued', 'Used', 'Available'].includes(text)) {
            th.classList.add('text-right');
        }
        th.textContent = text;
        headerRow.appendChild(th);
    });

    const tbody = table.createTBody();
    tbody.className = 'bg-white divide-y divide-gray-200';

    balances.forEach(bal => {
        const row = tbody.insertRow();
        row.id = `lb-row-${bal.BalanceID}`;

        const createCell = (text) => {
            const cell = row.insertCell();
            cell.className = 'px-4 py-3 whitespace-nowrap text-sm';
            cell.textContent = text ?? '0.0';
            return cell;
        };

        createCell(bal.LeaveTypeName).classList.add('font-medium', 'text-gray-900');
        createCell(bal.EntitledDays).classList.add('text-gray-700', 'text-right');
        createCell(bal.AccruedDays).classList.add('text-gray-700', 'text-right');
        createCell(bal.UsedDays).classList.add('text-gray-700', 'text-right');
        createCell(bal.AvailableDays).classList.add('text-gray-900', 'text-right', 'font-semibold');
    });

    container.appendChild(table);
}

// =================================
// === Leave Types Admin Section ===
// =================================
export async function displayLeaveTypesAdminSection() {
     console.log("[Display] Displaying Leave Types Admin Section...");
    if (!initializeLeaveElements()) return;

    const user = window.currentUser;
    if (!['System Admin', 'HR Admin'].includes(user?.role_name)) {
        pageTitleElement.textContent = 'Access Denied';
        mainContentArea.innerHTML = `
            <div class="bg-white p-6 rounded-lg shadow-md border border-red-300">
                <p class="text-red-600 font-semibold">Access Denied: You do not have permission to manage Leave Types.</p>
            </div>`;
        return; 
    }

    pageTitleElement.textContent = 'Manage Leave Types';
    mainContentArea.innerHTML = `
        <div class="bg-white p-6 rounded-lg shadow-md border border-[#F7E6CA] space-y-6">
            <div class="border-b border-gray-200 pb-4">
                 <h3 class="text-lg font-semibold text-[#4E3B2A] mb-3 font-header"><span id="leave-type-form-title">Add New</span> Leave Type</h3>
                 <form id="add-edit-leave-type-form" class="space-y-4">
                    <input type="hidden" id="leave-type-id" name="leave_type_id" value="">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div>
                            <label for="leave-type-name" class="block text-sm font-medium text-gray-700 mb-1">Type Name:</label>
                            <input type="text" id="leave-type-name" name="type_name" required placeholder="e.g., Vacation Leave, Sick Leave" class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                        </div>
                        <div>
                            <label for="leave-type-accrual" class="block text-sm font-medium text-gray-700 mb-1">Accrual Rate (Days/Month):</label>
                            <input type="number" step="0.1" min="0" id="leave-type-accrual" name="accrual_rate" placeholder="e.g., 1.25 (Optional)" class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                        </div>
                         <div>
                            <label for="leave-type-carry-forward" class="block text-sm font-medium text-gray-700 mb-1">Max Carry Forward Days:</label>
                            <input type="number" step="0.1" min="0" id="leave-type-carry-forward" name="max_carry_forward_days" placeholder="e.g., 5 (Optional)" class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                        </div>
                         <div class="flex items-center pt-6 md:col-span-1 lg:col-span-1">
                             <input id="leave-type-requires-approval" name="requires_approval" type="checkbox" value="1" checked class="h-4 w-4 text-[#594423] focus:ring-[#4E3B2A] border-gray-300 rounded">
                             <label for="leave-type-requires-approval" class="ml-2 block text-sm text-gray-900">Requires Approval?</label>
                         </div>
                         <div class="md:col-span-2 lg:col-span-3">
                            <label for="leave-type-description" class="block text-sm font-medium text-gray-700 mb-1">Description:</label>
                            <textarea id="leave-type-description" name="description" rows="2" placeholder="Details about this leave type policy..." class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]"></textarea>
                        </div>
                    </div>
                     <div class="pt-2 space-x-3">
                        <button type="submit" class="px-4 py-2 bg-[#4727ff] text-white rounded-md hover:bg-[#3a1fcc] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#4727ff] transition duration-150 ease-in-out">
                            Save Leave Type
                        </button>
                         <button type="button" id="cancel-edit-leave-type-btn" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition duration-150 ease-in-out" style="display: none;">
                            Cancel Edit
                        </button>
                        </div>
                 </form>
            </div>

            <div>
                <h3 class="text-lg font-semibold text-[#4E3B2A] mb-3 font-header">Existing Leave Types</h3>
                <div id="leave-types-admin-list-container" class="overflow-x-auto">
                     <p>Loading leave types...</p>
                </div>
            </div>
        </div>`;

    requestAnimationFrame(async () => {
        const leaveTypeForm = document.getElementById('add-edit-leave-type-form');
        if (leaveTypeForm) {
            if (!leaveTypeForm.hasAttribute('data-listener-attached')) {
                leaveTypeForm.addEventListener('submit', handleSaveLeaveType);
                leaveTypeForm.setAttribute('data-listener-attached', 'true');
            }
        } else { console.error("Add/Edit Leave Type form not found."); }

         const cancelBtn = document.getElementById('cancel-edit-leave-type-btn');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', resetLeaveTypeForm);
        }
        await loadLeaveTypesForAdmin(); 
    });
}
async function loadLeaveTypesForAdmin() {
    console.log("[Load] Loading Leave Types for Admin...");
    const container = document.getElementById('leave-types-admin-list-container');
    if (!container) {
        console.error("[Load] Container 'leave-types-admin-list-container' not found.");
        return;
    }
    container.innerHTML = '<p class="text-center py-4">Loading leave types...</p>';
    const url = `${API_BASE_URL}get_leave_types.php`; 

    try {
        const response = await fetch(url);
        console.log(`[loadLeaveTypesForAdmin] Fetch status for ${url}: ${response.status}`);
        if (!response.ok) {
             const errorText = await response.text();
             console.error(`[loadLeaveTypesForAdmin] Fetch error response text: ${errorText}`);
             throw new Error(`HTTP error! status: ${response.status}`);
        }
        const responseText = await response.text();
        console.log(`[loadLeaveTypesForAdmin] Raw response text: ${responseText}`);
        let leaveTypes;
        try {
            leaveTypes = JSON.parse(responseText);
            console.log(`[loadLeaveTypesForAdmin] Parsed leave types:`, leaveTypes);
        } catch (jsonError) {
            console.error(`[loadLeaveTypesForAdmin] Failed to parse JSON: ${jsonError}`);
            throw new Error("Failed to parse server response as JSON.");
        }

        if (leaveTypes.error) {
            console.error("Error fetching leave types for admin:", leaveTypes.error);
            throw new Error(leaveTypes.error);
        }
        renderLeaveTypesAdminTable(leaveTypes);
    } catch (error) {
        console.error('Error loading leave types for admin:', error);
        container.innerHTML = `<p class="text-red-500 text-center py-4">Could not load leave types. ${error.message}</p>`;
    }
 }
function renderLeaveTypesAdminTable(leaveTypes) {
    console.log("[Render] Rendering Leave Types Admin Table...");
    const container = document.getElementById('leave-types-admin-list-container');
    if (!container) return;
    container.innerHTML = ''; 

    if (!leaveTypes || !Array.isArray(leaveTypes) || leaveTypes.length === 0) { 
        console.warn("[Render] No leave types data or invalid format received.", leaveTypes);
        container.innerHTML = '<p class="text-center py-4 text-gray-500">No leave types configured yet.</p>';
        return;
    }

    const table = document.createElement('table');
    table.className = 'min-w-full divide-y divide-gray-200 border border-gray-300';

    const thead = table.createTHead();
    thead.className = 'bg-gray-50';
    const headerRow = thead.insertRow();
    const headers = ['ID', 'Type Name', 'Description', 'Accrual Rate', 'Max Carry Fwd', 'Needs Approval?', 'Actions'];
    headers.forEach(text => {
        const th = document.createElement('th');
        th.scope = 'col';
        th.className = 'px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider';
        th.textContent = text;
        headerRow.appendChild(th);
    });

    const tbody = table.createTBody();
    tbody.className = 'bg-white divide-y divide-gray-200 leave-types-admin-action-container'; 

    leaveTypes.forEach(type => {
        const row = tbody.insertRow();
        row.id = `leave-type-row-${type.LeaveTypeID}`;

         const createCell = (text, allowWrap = false) => {
            const cell = row.insertCell();
            cell.className = 'px-4 py-3 text-sm';
            if (allowWrap) {
                 cell.classList.add('whitespace-normal', 'break-words');
            } else {
                 cell.classList.add('whitespace-nowrap');
            }
            cell.textContent = text ?? '-'; 
            return cell;
        };

        createCell(type.LeaveTypeID).classList.add('text-gray-500');
        createCell(type.TypeName).classList.add('font-medium', 'text-gray-900');
        createCell(type.Description, true).classList.add('text-gray-700'); 
        createCell(type.AccrualRate).classList.add('text-gray-700');
        createCell(type.MaxCarryForwardDays).classList.add('text-gray-700');
        createCell(type.RequiresApproval == 1 ? 'Yes' : 'No').classList.add('text-gray-700');

        const actionsCell = row.insertCell();
        actionsCell.className = 'px-4 py-3 whitespace-nowrap text-sm font-medium space-x-2';

        const editBtn = document.createElement('button');
        editBtn.className = 'text-blue-600 hover:text-blue-800 edit-leave-type-btn';
        editBtn.dataset.id = type.LeaveTypeID;
        editBtn.dataset.name = type.TypeName;
        editBtn.dataset.description = type.Description || '';
        editBtn.dataset.accrual = type.AccrualRate ?? '';
        editBtn.dataset.carry = type.MaxCarryForwardDays ?? '';
        editBtn.dataset.approval = type.RequiresApproval ?? 1;
        editBtn.title = 'Edit Leave Type';
        editBtn.innerHTML = '<i class="fas fa-edit"></i> Edit';
        actionsCell.appendChild(editBtn);

        const deleteBtn = document.createElement('button');
        deleteBtn.className = 'text-red-600 hover:text-red-800 delete-leave-type-btn';
        deleteBtn.dataset.id = type.LeaveTypeID;
        deleteBtn.dataset.name = type.TypeName;
        deleteBtn.title = 'Delete Leave Type';
        deleteBtn.innerHTML = '<i class="fas fa-trash-alt"></i> Delete';
        actionsCell.appendChild(deleteBtn);
    });

    container.appendChild(table);
    attachLeaveTypeAdminActionListeners();
}
function attachLeaveTypeAdminActionListeners() {
     const container = document.querySelector('.leave-types-admin-action-container');
    if (container) {
        container.removeEventListener('click', handleLeaveTypeAdminAction);
        container.addEventListener('click', handleLeaveTypeAdminAction);
    }
}
async function handleLeaveTypeAdminAction(event) {
     const editButton = event.target.closest('.edit-leave-type-btn');
    const deleteButton = event.target.closest('.delete-leave-type-btn');

    if (editButton) {
        const id = editButton.dataset.id;
        const name = editButton.dataset.name;
        const description = editButton.dataset.description;
        const accrual = editButton.dataset.accrual;
        const carry = editButton.dataset.carry;
        const approval = editButton.dataset.approval;
        populateLeaveTypeFormForEdit(id, name, description, accrual, carry, approval);
    } else if (deleteButton) {
        const id = deleteButton.dataset.id;
        const name = deleteButton.dataset.name;
        
        const result = await Swal.fire({
            title: 'Delete Leave Type?',
            text: `Are you sure you want to delete leave type "${name}" (ID: ${id})? This cannot be undone.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!'
        });
        if (result.isConfirmed) {
            deleteLeaveType(id);
        }
    }
}
function populateLeaveTypeFormForEdit(id, name, description, accrual, carry, approval) {
     document.getElementById('leave-type-form-title').textContent = 'Edit';
    document.getElementById('leave-type-id').value = id;
    document.getElementById('leave-type-name').value = name;
    document.getElementById('leave-type-description').value = description;
    document.getElementById('leave-type-accrual').value = accrual;
    document.getElementById('leave-type-carry-forward').value = carry;
    document.getElementById('leave-type-requires-approval').checked = (approval == 1);
    document.getElementById('cancel-edit-leave-type-btn').style.display = 'inline-block';
    document.getElementById('add-edit-leave-type-form').scrollIntoView({ behavior: 'smooth' });
}
function resetLeaveTypeForm() {
      document.getElementById('leave-type-form-title').textContent = 'Add New';
     document.getElementById('add-edit-leave-type-form').reset();
     document.getElementById('leave-type-id').value = '';
     document.getElementById('cancel-edit-leave-type-btn').style.display = 'none';
}
async function handleSaveLeaveType(event) {
     event.preventDefault();
    const form = event.target;
    const submitButton = form.querySelector('button[type="submit"]');
    if (!submitButton) return;

    const leaveTypeId = form.elements['leave_type_id'].value;
    const typeName = form.elements['type_name'].value.trim();
    const description = form.elements['description'].value.trim();
    const accrualRate = form.elements['accrual_rate'].value.trim();
    const maxCarry = form.elements['max_carry_forward_days'].value.trim();
    const requiresApproval = form.elements['leave-type-requires-approval'].checked ? 1 : 0;

    if (!typeName) {
        Swal.fire({
            icon: 'error',
            title: 'Validation Error',
            text: 'Leave Type Name is required.',
            confirmButtonColor: '#4E3B2A'
        });
        return;
    }

    const isEditing = !!leaveTypeId;
    const url = isEditing ? `${API_BASE_URL}update_leave_type.php` : `${API_BASE_URL}add_leave_type.php`;
    const method = 'POST';

    const formData = {
        type_name: typeName,
        description: description || null,
        accrual_rate: accrualRate ? parseFloat(accrualRate) : null,
        max_carry_forward_days: maxCarry ? parseFloat(maxCarry) : null,
        requires_approval: requiresApproval
    };
    if (isEditing) {
        formData.leave_type_id = parseInt(leaveTypeId);
    }

    Swal.fire({
        title: 'Processing...',
        text: isEditing ? 'Updating leave type...' : 'Adding leave type...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
    submitButton.disabled = true;

    try {
        const response = await fetch(url, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        });

        const result = await handleApiResponse(response);

        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: result.message || `Leave type ${isEditing ? 'updated' : 'added'} successfully!`,
            confirmButtonColor: '#4E3B2A',
            timer: 2000
        });
        resetLeaveTypeForm();
        await loadLeaveTypesForAdmin(); 

    } catch (error) {
        console.error(`Error ${isEditing ? 'updating' : 'adding'} leave type:`, error);
        let displayMessage = `Error: ${error.message}`;
        if (error.details) { displayMessage += ` Details: ${JSON.stringify(error.details)}`; }
        Swal.fire({
            icon: 'error',
            title: 'Operation Failed',
            text: displayMessage,
            confirmButtonColor: '#4E3B2A'
        });
    } finally {
        submitButton.disabled = false;
        if (Swal.isLoading()) { Swal.close(); }
    }
}
async function deleteLeaveType(leaveTypeId) {
     console.log(`[Delete] Attempting to delete Leave Type ID: ${leaveTypeId}`);
    
    Swal.fire({
        title: 'Processing...',
        text: `Deleting leave type ${leaveTypeId}...`,
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    try {
        const response = await fetch(`${API_BASE_URL}delete_leave_type.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ leave_type_id: parseInt(leaveTypeId) })
        });

        const result = await handleApiResponse(response);

        Swal.fire({
            icon: 'success',
            title: 'Deleted!',
            text: result.message || 'Leave type deleted successfully!',
            confirmButtonColor: '#4E3B2A',
            timer: 2000
        });

        await loadLeaveTypesForAdmin();
        resetLeaveTypeForm();

    } catch (error) {
        console.error('Error deleting leave type:', error);
        Swal.fire({
            icon: 'error',
            title: 'Deletion Failed',
            text: `Error deleting leave type: ${error.message}`,
            confirmButtonColor: '#4E3B2A'
        });
    } finally {
        if (Swal.isLoading()) { Swal.close(); }
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
            errorPayload.error = `Server returned non-JSON response (Status: ${response.status}). Response: ${errorText.substring(0,100)}`;
        }
        const error = new Error(errorPayload.error);
        error.details = errorPayload.details;
        throw error;
    }

    try {
        if (response.status === 204) { 
             return { message: "Operation completed successfully (No Content)." };
        }
        const text = await response.text();
        if (!text || !text.trim()) {
             return []; 
        }
        try {
            data = JSON.parse(text);
            return data;
        } catch (jsonError) {
            console.warn("Received successful status, but response was non-JSON text:", text.substring(0, 200));
            throw new Error("Received successful status, but response was non-JSON text.");
        }
    } catch (e) {
        console.error("Error processing successful response body:", e);
        throw new Error("Error processing response from server.");
    }
}
