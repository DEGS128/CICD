/**
 * Time & Attendance - Timesheets Module
 * v2.2 - Fixed View Details modal, improved error handling with SweetAlert.
 * v2.1 - Integrated SweetAlert for notifications in Create Timesheet modal.
 * v2.0 - Integrated modal for creating new timesheet periods.
 */
import { API_BASE_URL, populateEmployeeDropdown } from '../utils.js'; // Import shared functions/constants

// --- DOM Element References ---
let pageTitleElement;
let mainContentArea;

// Timesheet Detail Modal (existing)
let timesheetDetailModal;
let timesheetDetailModalOverlay;
let timesheetDetailModalCloseBtn;

// Create Timesheet Modal (new)
let createTimesheetModal;
let createTimesheetModalOverlay;
let createTimesheetModalForm;
let closeCreateTimesheetModalBtn;
let cancelCreateTimesheetModalBtn;
let createTimesheetModalStatus; 


/**
 * Initializes common elements used by the timesheets module.
 */
function initializeTimesheetElements() {
    pageTitleElement = document.getElementById('page-title');
    mainContentArea = document.getElementById('main-content-area');

    // Detail Modal
    timesheetDetailModal = document.getElementById('timesheet-detail-modal');
    timesheetDetailModalOverlay = document.getElementById('modal-overlay-ts'); 
    timesheetDetailModalCloseBtn = document.getElementById('modal-close-btn-ts'); 

    // Create Modal
    createTimesheetModal = document.getElementById('create-timesheet-modal');
    createTimesheetModalOverlay = document.getElementById('create-timesheet-modal-overlay');
    createTimesheetModalForm = document.getElementById('create-timesheet-modal-form');
    closeCreateTimesheetModalBtn = document.getElementById('close-create-timesheet-modal-btn');
    cancelCreateTimesheetModalBtn = document.getElementById('cancel-create-timesheet-modal-btn');
    createTimesheetModalStatus = document.getElementById('create-timesheet-modal-status');

    let allElementsPresent = true;
    if (!pageTitleElement || !mainContentArea) {
        console.error("Timesheets Module: Core DOM elements (page-title or main-content-area) not found!");
        allElementsPresent = false;
    }
    if (!timesheetDetailModal || !timesheetDetailModalOverlay || !timesheetDetailModalCloseBtn) {
        console.warn("Timesheets Module: Timesheet Detail Modal elements not fully found. Viewing details might not work.");
        // Not returning false, as the main section might still work.
    }
    if (!createTimesheetModal || !createTimesheetModalOverlay || !createTimesheetModalForm || !closeCreateTimesheetModalBtn || !cancelCreateTimesheetModalBtn || !createTimesheetModalStatus) {
        console.error("Timesheets Module: Create Timesheet Modal elements not found! Check IDs.");
        allElementsPresent = false; // This is critical for the create functionality
    }
    return allElementsPresent;
}

/**
 * Opens the "Create New Timesheet" modal.
 */
async function openCreateTimesheetModal() {
    if (!createTimesheetModal || !createTimesheetModalForm || !createTimesheetModalStatus) return;
    createTimesheetModalForm.reset();
    createTimesheetModalStatus.textContent = '';
    createTimesheetModalStatus.className = 'text-sm text-center h-4 mt-2';
    await populateEmployeeDropdown('modal-ts-employee-select'); 
    createTimesheetModal.classList.remove('hidden');
    createTimesheetModal.classList.add('flex');
}

/**
 * Closes the "Create New Timesheet" modal.
 */
function closeCreateTimesheetModal() {
    if (!createTimesheetModal) return;
    createTimesheetModal.classList.add('hidden');
    createTimesheetModal.classList.remove('flex');
}


/**
 * Displays the Timesheets section.
 */
export async function displayTimesheetsSection() {
    console.log("[Display] Displaying Timesheets Section...");
    if (!initializeTimesheetElements()) {
        if(mainContentArea) mainContentArea.innerHTML = `<p class="text-red-500 p-4">Error initializing timesheet section elements.</p>`;
        return;
    }
    pageTitleElement.textContent = 'Employee Timesheets';

    mainContentArea.innerHTML = `
        <div class="bg-white p-6 rounded-lg shadow-md border border-[#F7E6CA] space-y-6">
            <div class="flex justify-between items-center border-b border-gray-200 pb-4">
                <h3 class="text-lg font-semibold text-[#4E3B2A] font-header">Timesheet Management</h3>
                <button id="open-create-timesheet-modal-btn" class="px-4 py-2 bg-[#594423] text-white rounded-md hover:bg-[#4E3B2A] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#594423] transition duration-150 ease-in-out flex items-center space-x-2">
                    <i class="fa-solid fa-plus"></i>
                    <span>Create Timesheet Period</span>
                </button>
            </div>

            <div>
                <h3 class="text-lg font-semibold text-[#4E3B2A] mb-3 font-header">Submitted Timesheets</h3>
                <div class="flex flex-wrap gap-4 mb-4 items-end">
                    <div>
                        <label for="filter-ts-employee" class="block text-sm font-medium text-gray-700 mb-1">Filter by Employee:</label>
                        <select id="filter-ts-employee" class="w-full sm:w-auto p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                            <option value="">All Employees</option>
                        </select>
                    </div>
                     <div>
                        <label for="filter-ts-status" class="block text-sm font-medium text-gray-700 mb-1">Filter by Status:</label>
                        <select id="filter-ts-status" class="w-full sm:w-auto p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                            <option value="">All Statuses</option>
                            <option value="Pending">Pending</option>
                            <option value="Approved">Approved</option>
                            <option value="Rejected">Rejected</option>
                            <option value="Draft">Draft</option> 
                        </select>
                    </div>
                    <div>
                        <button id="filter-ts-btn" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-150 ease-in-out">
                            Filter
                        </button>
                    </div>
                </div>
                <div id="timesheets-list-container" class="overflow-x-auto">
                    <p class="text-center py-4">Loading timesheets...</p>
                </div>
            </div>
        </div>`;

    requestAnimationFrame(async () => {
        await populateEmployeeDropdown('filter-ts-employee', true); 

        const openModalBtn = document.getElementById('open-create-timesheet-modal-btn');
        if (openModalBtn && !openModalBtn.hasAttribute('data-listener-attached')) {
            openModalBtn.addEventListener('click', openCreateTimesheetModal);
            openModalBtn.setAttribute('data-listener-attached', 'true');
        }

        const filterBtn = document.getElementById('filter-ts-btn');
        if (filterBtn && !filterBtn.hasAttribute('data-listener-attached')) {
            filterBtn.addEventListener('click', applyTimesheetFilter);
            filterBtn.setAttribute('data-listener-attached', 'true');
        }
        
        if (closeCreateTimesheetModalBtn && !closeCreateTimesheetModalBtn.hasAttribute('data-listener-attached')) {
            closeCreateTimesheetModalBtn.addEventListener('click', closeCreateTimesheetModal);
            closeCreateTimesheetModalBtn.setAttribute('data-listener-attached', 'true');
        }
        if (cancelCreateTimesheetModalBtn && !cancelCreateTimesheetModalBtn.hasAttribute('data-listener-attached')) {
            cancelCreateTimesheetModalBtn.addEventListener('click', closeCreateTimesheetModal);
            cancelCreateTimesheetModalBtn.setAttribute('data-listener-attached', 'true');
        }
        if (createTimesheetModalOverlay && !createTimesheetModalOverlay.hasAttribute('data-listener-attached')) {
            createTimesheetModalOverlay.addEventListener('click', closeCreateTimesheetModal);
            createTimesheetModalOverlay.setAttribute('data-listener-attached', 'true');
        }
        if (createTimesheetModalForm && !createTimesheetModalForm.hasAttribute('data-listener-attached')) {
            createTimesheetModalForm.addEventListener('submit', handleAddTimesheet);
            createTimesheetModalForm.setAttribute('data-listener-attached', 'true');
        }
        
        if (timesheetDetailModalCloseBtn && !timesheetDetailModalCloseBtn.hasAttribute('data-listener-attached')) {
             timesheetDetailModalCloseBtn.addEventListener('click', closeTimesheetModal);
             timesheetDetailModalCloseBtn.setAttribute('data-listener-attached', 'true');
        }
        if (timesheetDetailModalOverlay && !timesheetDetailModalOverlay.hasAttribute('data-listener-attached')) {
             timesheetDetailModalOverlay.addEventListener('click', closeTimesheetModal);
             timesheetDetailModalOverlay.setAttribute('data-listener-attached', 'true');
        }

        await loadTimesheets(); 
    });
}

/**
 * Applies the selected filters and reloads the timesheet list.
 */
function applyTimesheetFilter() {
    const employeeId = document.getElementById('filter-ts-employee')?.value;
    const status = document.getElementById('filter-ts-status')?.value;
    loadTimesheets(employeeId, status); 
}

/**
 * Fetches timesheets from the API based on optional filters.
 */
async function loadTimesheets(employeeId = null, status = null) {
    console.log(`[Load] Loading Timesheets... (Employee: ${employeeId || 'All'}, Status: ${status || 'All'})`);
    const container = document.getElementById('timesheets-list-container');
    if (!container) return;
    container.innerHTML = '<p class="text-center py-4">Loading timesheets...</p>'; 

    const params = new URLSearchParams();
    if (employeeId) params.append('employee_id', employeeId);
    if (status) params.append('status', status);
    
    const url = `${API_BASE_URL}get_timesheets.php?${params.toString()}`;

    try {
        const response = await fetch(url);
        const timesheets = await handleApiResponse(response); // Use centralized handler
        renderTimesheetsTable(timesheets); 
    } catch (error) {
        console.error('Error loading timesheets:', error);
        container.innerHTML = `<p class="text-red-500 text-center py-4">Could not load timesheets. ${error.message}</p>`;
    }
}

/**
 * Renders the list of timesheets into an HTML table.
 */
function renderTimesheetsTable(timesheets) {
    console.log("[Render] Rendering Timesheets Table...");
    const container = document.getElementById('timesheets-list-container');
    if (!container) return;

    if (!timesheets || timesheets.length === 0) {
        container.innerHTML = '<p class="text-center py-4 text-gray-500">No timesheets found for the selected criteria.</p>';
        return;
    }

    let tableHtml = `
        <table class="min-w-full divide-y divide-gray-200 border border-gray-300">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Period</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Hrs</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">OT Hrs</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Submitted</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200 timesheet-action-container">`; 

    timesheets.forEach(ts => {
        let statusColor = 'text-gray-600'; 
        if (ts.Status === 'Approved') statusColor = 'text-green-600';
        else if (ts.Status === 'Rejected') statusColor = 'text-red-600';
        else if (ts.Status === 'Pending') statusColor = 'text-yellow-600';

        tableHtml += `
            <tr id="ts-row-${ts.TimesheetID}">
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">${ts.TimesheetID}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">${ts.EmployeeName}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">${ts.PeriodStartDateFormatted || ts.PeriodStartDate} - ${ts.PeriodEndDateFormatted || ts.PeriodEndDate}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700 text-right">${ts.TotalHoursWorked || '-'}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700 text-right">${ts.OvertimeHours || '-'}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm font-semibold ${statusColor}">${ts.Status || '-'}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">${ts.SubmittedDateFormatted || '-'}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium space-x-2">
                    <button class="text-blue-600 hover:text-blue-800 view-ts-btn" data-ts-id="${ts.TimesheetID}" title="View Details">
                        <i class="fas fa-eye"></i>
                    </button>
                    ${ts.Status === 'Pending' ? `
                    <button class="text-green-600 hover:text-green-800 approve-ts-btn" data-ts-id="${ts.TimesheetID}" title="Approve">
                        <i class="fas fa-check-circle"></i>
                    </button>
                    <button class="text-red-600 hover:text-red-800 reject-ts-btn" data-ts-id="${ts.TimesheetID}" title="Reject">
                        <i class="fas fa-times-circle"></i>
                    </button>
                    ` : ''}
                     </td>
            </tr>`;
    });

    tableHtml += `</tbody></table>`;
    container.innerHTML = tableHtml;
    attachTimesheetActionListeners();
}

/**
 * Attaches a single event listener to the table body for handling actions.
 */
function attachTimesheetActionListeners() {
    const container = document.querySelector('.timesheet-action-container'); 
    if (container) {
        container.removeEventListener('click', handleTimesheetAction);
        container.addEventListener('click', handleTimesheetAction);
    }
}


/**
 * Handles the submission of the add timesheet form (now from modal).
 */
async function handleAddTimesheet(event) {
    event.preventDefault(); 
    const form = document.getElementById('create-timesheet-modal-form'); 
    const submitButton = form.querySelector('button[type="submit"]');

    if (!form || !submitButton) {
         console.error("Create Timesheet modal form or submit button missing.");
         return;
    }

    const employeeId = form.elements['employee_id'].value;
    const startDate = form.elements['period_start_date'].value;
    const endDate = form.elements['period_end_date'].value;

    if (!employeeId || !startDate || !endDate) {
        Swal.fire({
            icon: 'error',
            title: 'Validation Error',
            text: 'Employee, Start Date, and End Date are required.',
            confirmButtonColor: '#4E3B2A'
        });
        return;
    }
     if (endDate < startDate) {
        Swal.fire({
            icon: 'error',
            title: 'Validation Error',
            text: 'End Date cannot be before Start Date.',
            confirmButtonColor: '#4E3B2A'
        });
        return;
     }

    const formData = {
        employee_id: employeeId,
        period_start_date: startDate,
        period_end_date: endDate,
    };

    Swal.fire({
        title: 'Processing...',
        text: 'Creating timesheet period, please wait.',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
    submitButton.disabled = true;

    try {
        const response = await fetch(`${API_BASE_URL}add_timesheet.php`, {
            method: 'POST',
             headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData) 
        });
        const result = await handleApiResponse(response); // Use centralized handler
        
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: result.message || 'Timesheet created successfully!',
            confirmButtonColor: '#4E3B2A',
            timer: 2000
        });
        
        await loadTimesheets(); 
        closeCreateTimesheetModal();

    } catch (error) {
        console.error('Error creating timesheet:', error);
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

/**
 * Handles clicks within the timesheet table body for actions like view, approve, reject.
 */
function handleTimesheetAction(event) {
    const target = event.target; 
    const viewButton = target.closest('.view-ts-btn');
    const approveButton = target.closest('.approve-ts-btn');
    const rejectButton = target.closest('.reject-ts-btn');

    if (viewButton) {
        const timesheetId = viewButton.dataset.tsId;
        handleViewTimesheet(timesheetId);
    } else if (approveButton) {
        const timesheetId = approveButton.dataset.tsId;
        handleApproveTimesheet(timesheetId);
    } else if (rejectButton) {
        const timesheetId = rejectButton.dataset.tsId;
        handleRejectTimesheet(timesheetId);
    }
}

/**
 * Fetches details for a specific timesheet and displays them in the modal.
 */
async function handleViewTimesheet(timesheetId) {
    console.log(`View button clicked for Timesheet ID: ${timesheetId}`);
    const modal = document.getElementById('timesheet-detail-modal'); 
    if (!modal) {
        console.error("Timesheet detail modal not found in HTML.");
        Swal.fire({ icon: 'error', title: 'UI Error', text: 'Cannot display timesheet details modal. Element not found.', confirmButtonColor: '#4E3B2A' });
        return;
    }

    // Clear previous details and show loading state
    document.getElementById('modal-timesheet-id').textContent = timesheetId;
    const fieldsToReset = ['modal-employee-name', 'modal-employee-job', 'modal-period-start', 'modal-period-end', 'modal-status', 'modal-total-hours', 'modal-overtime-hours', 'modal-submitted-date', 'modal-approver-name'];
    fieldsToReset.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.textContent = 'Loading...';
    });
    document.getElementById('modal-attendance-entries').innerHTML = '<p class="p-4 text-gray-500 text-center">Loading attendance...</p>';

    modal.classList.remove('hidden'); 
    modal.classList.add('flex'); 

    try {
        const response = await fetch(`${API_BASE_URL}get_timesheet_details.php?id=${timesheetId}`);
        const details = await handleApiResponse(response); // Use centralized handler
        console.log("Fetched Timesheet Details:", details); // Log the details

        if (!details || Object.keys(details).length === 0 && !details.error) { // Check if details is empty or just an error wrapper
             throw new Error("No data returned for this timesheet.");
        }
        displayTimesheetModal(details);

    } catch (error) {
        console.error('Error fetching timesheet details:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error Loading Details',
            text: `Could not load timesheet details: ${error.message}`,
            confirmButtonColor: '#4E3B2A'
        });
        // Optionally, clear the modal or close it
        document.getElementById('modal-attendance-entries').innerHTML = `<p class="p-4 text-red-500 text-center">Error loading details: ${error.message}</p>`;
        // closeTimesheetModal(); // Or keep it open with the error
    }
}

/**
 * Handles the confirmation and API call for approving a timesheet.
 */
async function handleApproveTimesheet(timesheetId) {
    console.log(`Approve button clicked for Timesheet ID: ${timesheetId}`);
    const result = await Swal.fire({
        title: 'Approve Timesheet?',
        text: `Are you sure you want to APPROVE Timesheet ID ${timesheetId}?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, approve it!'
    });

    if (result.isConfirmed) {
        await updateTimesheetStatus(timesheetId, 'Approved');
    }
}

/**
 * Handles the confirmation and API call for rejecting a timesheet.
 */
async function handleRejectTimesheet(timesheetId) {
    console.log(`Reject button clicked for Timesheet ID: ${timesheetId}`);
     const result = await Swal.fire({
        title: 'Reject Timesheet?',
        text: `Are you sure you want to REJECT Timesheet ID ${timesheetId}?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, reject it!'
    });
    if (result.isConfirmed) {
        await updateTimesheetStatus(timesheetId, 'Rejected');
    }
}

/**
 * Updates the status of a timesheet via API call.
 */
async function updateTimesheetStatus(timesheetId, status) {
    console.log(`Updating timesheet ${timesheetId} to status: ${status}`);
    Swal.fire({
        title: 'Processing...',
        text: `Updating timesheet to ${status}...`,
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
    try {
        const response = await fetch(`${API_BASE_URL}update_timesheet_status.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ timesheet_id: parseInt(timesheetId), status: status }) 
        });
        const result = await handleApiResponse(response); // Use centralized handler
        
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: result.message || `Timesheet ${status.toLowerCase()} successfully!`,
            confirmButtonColor: '#4E3B2A'
        });
        await loadTimesheets(
            document.getElementById('filter-ts-employee')?.value,
            document.getElementById('filter-ts-status')?.value
        );
    } catch (error) {
        console.error(`Error ${status.toLowerCase()}ing timesheet:`, error);
        Swal.fire({
            icon: 'error',
            title: `Error ${status}ing Timesheet`,
            text: `Failed to ${status.toLowerCase()} timesheet: ${error.message}`,
            confirmButtonColor: '#4E3B2A'
        });
    }
}


/**
 * Populates the timesheet details modal with data.
 */
function displayTimesheetModal(details) {
    if (!details) {
        console.warn("displayTimesheetModal called with null or undefined details.");
        // Clear modal fields or show 'Data not available'
        const fieldsToClear = ['modal-timesheet-id', 'modal-employee-name', 'modal-employee-job', 'modal-period-start', 'modal-period-end', 'modal-status', 'modal-total-hours', 'modal-overtime-hours', 'modal-submitted-date', 'modal-approver-name'];
        fieldsToClear.forEach(id => {
            const el = document.getElementById(id);
            if(el) el.textContent = 'N/A';
        });
        document.getElementById('modal-attendance-entries').innerHTML = '<p class="p-4 text-gray-500 text-center">Timesheet data not available.</p>';
        return;
    }

    document.getElementById('modal-timesheet-id').textContent = details.TimesheetID || 'N/A';
    document.getElementById('modal-employee-name').textContent = details.EmployeeName || 'N/A';
    document.getElementById('modal-employee-job').textContent = details.EmployeeJobTitle || 'N/A';
    document.getElementById('modal-period-start').textContent = details.PeriodStartDateFormatted || details.PeriodStartDate || 'N/A';
    document.getElementById('modal-period-end').textContent = details.PeriodEndDateFormatted || details.PeriodEndDate || 'N/A';
    document.getElementById('modal-status').textContent = details.Status || 'N/A';
    document.getElementById('modal-total-hours').textContent = details.TotalHoursWorkedFormatted || details.TotalHoursWorked || '-';
    document.getElementById('modal-overtime-hours').textContent = details.OvertimeHoursFormatted || details.OvertimeHours || '-';
    document.getElementById('modal-submitted-date').textContent = details.SubmittedDateFormatted || details.SubmittedDate || 'N/A';
    document.getElementById('modal-approver-name').textContent = details.ApproverName || (details.ApprovalDate ? 'Approved' : 'N/A');

    const entriesContainer = document.getElementById('modal-attendance-entries');
    if (details.attendance_entries && details.attendance_entries.length > 0) {
        let entriesHtml = `
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-3 py-2 text-left font-medium text-gray-600 uppercase tracking-wider">Date</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-600 uppercase tracking-wider">Clock In</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-600 uppercase tracking-wider">Clock Out</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-600 uppercase tracking-wider">Hours</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-600 uppercase tracking-wider">Status</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-600 uppercase tracking-wider">Notes</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">`;
        details.attendance_entries.forEach(entry => {
            entriesHtml += `
                <tr>
                    <td class="px-3 py-2 whitespace-nowrap">${entry.AttendanceDateFormatted || entry.AttendanceDate || 'N/A'}</td>
                    <td class="px-3 py-2 whitespace-nowrap">${entry.ClockInTimeFormatted || '-'}</td>
                    <td class="px-3 py-2 whitespace-nowrap">${entry.ClockOutTimeFormatted || '-'}</td>
                    <td class="px-3 py-2 whitespace-nowrap text-right">${entry.HoursWorkedCalcFormatted || '-'}</td>
                    <td class="px-3 py-2 whitespace-nowrap">${entry.Status || '-'}</td>
                    <td class="px-3 py-2 whitespace-normal break-words">${entry.Notes || '-'}</td>
                </tr>`;
        });
        entriesHtml += `</tbody></table>`;
        entriesContainer.innerHTML = entriesHtml;
    } else {
        entriesContainer.innerHTML = '<p class="p-4 text-gray-500 text-center">No attendance records found for this period.</p>';
    }
}

/**
 * Closes the Timesheet Detail Modal.
 */
export function closeTimesheetModal() { 
    const modal = document.getElementById('timesheet-detail-modal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

/**
 * Handles API response, checking status and parsing JSON.
 * Throws an error for non-OK responses or JSON parsing issues.
 * @param {Response} response - The Fetch API Response object.
 * @returns {Promise<object>} - The parsed JSON data.
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
            if (errorText.toLowerCase().includes('notice') || errorText.toLowerCase().includes('warning')) {
                 errorPayload.error = `Server returned non-JSON response (Status: ${response.status}). Check PHP logs for notices/warnings.`;
            } else {
                errorPayload.error = `Server returned non-JSON response (Status: ${response.status}). Response snippet: ${errorText.substring(0,100)}... Check PHP logs.`;
            }
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
             // For get_timesheet_details, an empty body for a successful status should be treated as an issue
             // if we expect an object.
             console.warn("handleApiResponse: Received successful status, but response body was empty or whitespace.");
             return {}; // Return empty object, let the caller (handleViewTimesheet) decide if it's an error
        }
        try {
            data = JSON.parse(text);
            return data;
        } catch (jsonError) {
            console.error("handleApiResponse: Failed to parse successful response as JSON:", jsonError);
            console.error("handleApiResponse: Response text was:", text.substring(0, 500)); 
            throw new Error("Received successful status, but failed to parse response as JSON.");
        }
    } catch (e) {
        console.error("handleApiResponse: Error processing successful response body:", e);
        throw new Error("Error processing response from server.");
    }
}
