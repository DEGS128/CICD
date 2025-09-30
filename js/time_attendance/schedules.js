/**
 * Time & Attendance - Schedules Module
 * v2.0 - Integrated modal for adding new schedules.
 */
import { API_BASE_URL, populateEmployeeDropdown, populateShiftDropdown } from '../utils.js';

// --- DOM Element References ---
let pageTitleElement;
let mainContentArea;

// Add Schedule Modal elements
let addScheduleModal;
let addScheduleModalOverlay;
let addScheduleModalForm;
let closeAddScheduleModalBtn;
let cancelAddScheduleModalBtn;
let addScheduleModalStatus;

/**
 * Initializes common elements used by the schedules module, including modal elements.
 */
function initializeScheduleElements() {
    pageTitleElement = document.getElementById('page-title');
    mainContentArea = document.getElementById('main-content-area');

    addScheduleModal = document.getElementById('add-schedule-modal');
    addScheduleModalOverlay = document.getElementById('add-schedule-modal-overlay');
    addScheduleModalForm = document.getElementById('add-schedule-modal-form');
    closeAddScheduleModalBtn = document.getElementById('close-add-schedule-modal-btn');
    cancelAddScheduleModalBtn = document.getElementById('cancel-add-schedule-modal-btn');
    addScheduleModalStatus = document.getElementById('add-schedule-modal-status');

    if (!pageTitleElement || !mainContentArea) {
         console.error("Schedules Module: Core DOM elements (page-title or main-content-area) not found!");
         return false;
    }
    if (!addScheduleModal || !addScheduleModalOverlay || !addScheduleModalForm || !closeAddScheduleModalBtn || !cancelAddScheduleModalBtn || !addScheduleModalStatus) {
        console.error("Schedules Module: Add Schedule Modal elements not found! Check IDs.");
        return false;
    }
    return true;
}

/**
 * Opens the "Add New Schedule" modal.
 */
async function openAddScheduleModal() {
    if (!addScheduleModal || !addScheduleModalForm || !addScheduleModalStatus) return;
    addScheduleModalForm.reset();
    addScheduleModalStatus.textContent = '';
    addScheduleModalStatus.className = 'text-sm text-center h-4 mt-2';
    
    // Populate dropdowns inside the modal
    await populateEmployeeDropdown('modal-schedule-employee-select');
    await populateShiftDropdown('modal-schedule-shift-select');
    
    addScheduleModal.classList.remove('hidden');
    addScheduleModal.classList.add('flex');
}

/**
 * Closes the "Add New Schedule" modal.
 */
function closeAddScheduleModal() {
    if (!addScheduleModal) return;
    addScheduleModal.classList.add('hidden');
    addScheduleModal.classList.remove('flex');
}

/**
 * Displays the Schedules Section.
 */
export async function displaySchedulesSection() {
    console.log("[Display] Displaying Schedules Section...");
    if (!initializeScheduleElements()) {
        if(mainContentArea) mainContentArea.innerHTML = `<p class="text-red-500 p-4">Error initializing schedules section elements.</p>`;
        return;
    }
    pageTitleElement.textContent = 'Employee Schedules';
    mainContentArea.innerHTML = `
        <div class="bg-white p-6 rounded-lg shadow-md border border-[#F7E6CA] space-y-6">
            <div class="flex justify-between items-center border-b border-gray-200 pb-4">
                <h3 class="text-lg font-semibold text-[#4E3B2A] font-header">Schedule Management</h3>
                 <button id="open-add-schedule-modal-btn" class="px-4 py-2 bg-[#594423] text-white rounded-md hover:bg-[#4E3B2A] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#594423] transition duration-150 ease-in-out flex items-center space-x-2">
                    <i class="fa-solid fa-calendar-plus"></i>
                    <span>Assign New Schedule</span>
                </button>
            </div>
            <div>
                <h3 class="text-lg font-semibold text-[#4E3B2A] mb-3 font-header">Current & Past Schedules</h3>
                <div id="schedules-list-container" class="overflow-x-auto">
                    <p class="text-center py-4">Loading schedules...</p>
                </div>
            </div>
        </div>`;

     requestAnimationFrame(async () => {
        const openModalBtn = document.getElementById('open-add-schedule-modal-btn');
        if (openModalBtn && !openModalBtn.hasAttribute('data-listener-attached')) {
            openModalBtn.addEventListener('click', openAddScheduleModal);
            openModalBtn.setAttribute('data-listener-attached', 'true');
        }

        if (closeAddScheduleModalBtn && !closeAddScheduleModalBtn.hasAttribute('data-listener-attached')) {
            closeAddScheduleModalBtn.addEventListener('click', closeAddScheduleModal);
            closeAddScheduleModalBtn.setAttribute('data-listener-attached', 'true');
        }
        if (cancelAddScheduleModalBtn && !cancelAddScheduleModalBtn.hasAttribute('data-listener-attached')) {
            cancelAddScheduleModalBtn.addEventListener('click', closeAddScheduleModal);
            cancelAddScheduleModalBtn.setAttribute('data-listener-attached', 'true');
        }
        if (addScheduleModalOverlay && !addScheduleModalOverlay.hasAttribute('data-listener-attached')) {
            addScheduleModalOverlay.addEventListener('click', closeAddScheduleModal);
            addScheduleModalOverlay.setAttribute('data-listener-attached', 'true');
        }
        if (addScheduleModalForm && !addScheduleModalForm.hasAttribute('data-listener-attached')) {
            addScheduleModalForm.addEventListener('submit', handleAddSchedule);
            addScheduleModalForm.setAttribute('data-listener-attached', 'true');
        }
        await loadSchedules();
    });
}

/**
 * Fetches schedules data from the API and renders it.
 */
async function loadSchedules() {
    console.log("[Load] Loading Schedules...");
    const container = document.getElementById('schedules-list-container');
    if (!container) return;
    container.innerHTML = '<p class="text-center py-4">Loading schedules...</p>';
    const url = `${API_BASE_URL}get_schedules.php`;
    try {
        const response = await fetch(url);
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        const schedules = await response.json();
        if (schedules.error) {
            console.error("Error fetching schedules:", schedules.error);
            container.innerHTML = `<p class="text-red-500 text-center py-4">Error: ${schedules.error}</p>`;
        } else { renderSchedulesTable(schedules); }
    } catch (error) {
        console.error('Error loading schedules:', error);
        container.innerHTML = `<p class="text-red-500 text-center py-4">Could not load schedules. ${error.message}</p>`;
    }
}

/**
 * Renders the schedules data into an HTML table.
 * @param {Array} schedules - An array of schedule objects.
 */
function renderSchedulesTable(schedules) {
    console.log("[Render] Rendering Schedules Table...");
    const container = document.getElementById('schedules-list-container');
    if (!container) return;
    if (!schedules || schedules.length === 0) {
        container.innerHTML = '<p class="text-center py-4 text-gray-500">No schedules found.</p>';
        return;
    }
    let tableHtml = `
        <table class="min-w-full divide-y divide-gray-200 border border-gray-300">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Shift Assigned</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Work Days</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Start Date</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">End Date</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">`;
    schedules.forEach(sched => {
        const shiftInfo = sched.ShiftName ? `${sched.ShiftName} (${sched.StartTimeFormatted || sched.StartTime} - ${sched.EndTimeFormatted || sched.EndTime})` : '<span class="text-gray-400 italic">None</span>';
        tableHtml += `
            <tr id="sched-row-${sched.ScheduleID}">
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">${sched.ScheduleID}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">${sched.EmployeeName}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">${shiftInfo}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">${sched.Workdays || '<span class="text-gray-400 italic">N/A</span>'}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">${sched.StartDateFormatted || sched.StartDate}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">${sched.EndDateFormatted}</td>
            </tr>`;
    });
    tableHtml += `</tbody></table>`;
    container.innerHTML = tableHtml;
}

/**
 * Handles Add Schedule form submission from the modal.
 * @param {Event} event - The form submission event.
 */
async function handleAddSchedule(event) {
    event.preventDefault();
    const form = document.getElementById('add-schedule-modal-form');
    const statusSpan = document.getElementById('add-schedule-modal-status');
    const submitButton = form.querySelector('button[type="submit"]');

    if (!form || !statusSpan || !submitButton) { 
        console.error("Add Schedule modal form or status elements missing."); 
        return; 
    }
    
    const employeeId = form.elements['employee_id'].value;
    const startDate = form.elements['start_date'].value;
    if (!employeeId || !startDate) { 
        statusSpan.textContent = 'Employee and Start Date are required.'; 
        statusSpan.className = 'text-sm text-red-600 h-4 mt-2'; 
        return; 
    }
    const endDate = form.elements['end_date'].value;
    if (endDate && endDate < startDate) { 
        statusSpan.textContent = 'End Date cannot be before Start Date.'; 
        statusSpan.className = 'text-sm text-red-600 h-4 mt-2'; 
        return; 
    }
    
    const formData = { 
        employee_id: employeeId, 
        shift_id: form.elements['shift_id'].value || null, 
        start_date: startDate, 
        end_date: endDate || null, 
        workdays: form.elements['workdays'].value.trim() || null 
    };
    
    statusSpan.textContent = 'Adding schedule...'; 
    statusSpan.className = 'text-sm text-blue-600 h-4 mt-2'; 
    submitButton.disabled = true;

    try {
        const response = await fetch(`${API_BASE_URL}add_schedule.php`, {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(formData)
        });
        const result = await response.json();
        if (!response.ok) {
            if (response.status === 400 && result.details) { 
                const errorMessages = Object.values(result.details).join(' '); 
                throw new Error(errorMessages || result.error || `HTTP error! status: ${response.status}`); 
            }
            throw new Error(result.error || `HTTP error! status: ${response.status}`);
        }
        statusSpan.textContent = result.message || 'Schedule added successfully!'; 
        statusSpan.className = 'text-sm text-green-600 h-4 mt-2';
        
        await loadSchedules(); 
        
        setTimeout(() => { 
            closeAddScheduleModal(); 
        }, 1500);
    } catch (error) {
        console.error('Error adding schedule:', error);
        statusSpan.textContent = `Error: ${error.message}`; 
        statusSpan.className = 'text-sm text-red-600 h-4 mt-2';
    } finally { 
        submitButton.disabled = false; 
    }
}
