/**
 * Time & Attendance - Shifts Module
 * v2.1 - Integrated SweetAlert for notifications in Add Shift modal.
 * v2.0 - Integrated modal for adding new shifts.
 */
import { API_BASE_URL } from '../utils.js'; // Import base URL

// --- DOM Element References ---
let pageTitleElement;
let mainContentArea;
let addShiftModal;
let addShiftModalOverlay;
let addShiftModalForm;
let closeAddShiftModalBtn;
let cancelAddShiftModalBtn;
let addShiftModalStatus; // Kept for potential non-blocking messages, though Swal is primary


/**
 * Initializes common elements used by the shifts module, including modal elements.
 */
function initializeShiftElements() {
    pageTitleElement = document.getElementById('page-title');
    mainContentArea = document.getElementById('main-content-area');
    
    // Modal specific elements
    addShiftModal = document.getElementById('add-shift-modal');
    addShiftModalOverlay = document.getElementById('add-shift-modal-overlay');
    addShiftModalForm = document.getElementById('add-shift-modal-form');
    closeAddShiftModalBtn = document.getElementById('close-add-shift-modal-btn');
    cancelAddShiftModalBtn = document.getElementById('cancel-add-shift-modal-btn');
    addShiftModalStatus = document.getElementById('add-shift-modal-status');

    if (!pageTitleElement || !mainContentArea) {
         console.error("Shifts Module: Core DOM elements (page-title or main-content-area) not found!");
         return false;
    }
    if (!addShiftModal || !addShiftModalOverlay || !addShiftModalForm || !closeAddShiftModalBtn || !cancelAddShiftModalBtn || !addShiftModalStatus) {
        console.error("Shifts Module: Add Shift Modal elements not found! Check IDs: add-shift-modal, add-shift-modal-overlay, add-shift-modal-form, close-add-shift-modal-btn, cancel-add-shift-modal-btn, add-shift-modal-status");
        return false;
    }
    return true;
}


/**
 * Opens the "Add New Shift" modal.
 */
function openAddShiftModal() {
    if (!addShiftModal || !addShiftModalForm || !addShiftModalStatus) return;
    addShiftModalForm.reset(); // Clear any previous form data
    addShiftModalStatus.textContent = ''; // Clear any previous status messages
    addShiftModalStatus.className = 'text-sm text-center h-4 mt-2'; // Reset status class
    addShiftModal.classList.remove('hidden');
    addShiftModal.classList.add('flex'); // Use flex to center it
}

/**
 * Closes the "Add New Shift" modal.
 */
function closeAddShiftModal() {
    if (!addShiftModal) return;
    addShiftModal.classList.add('hidden');
    addShiftModal.classList.remove('flex');
}


/**
 * Displays the Shifts Section.
 * Now includes a button to open the "Add New Shift" modal.
 */
export async function displayShiftsSection() {
    console.log("[Display] Displaying Shifts Section...");
    if (!initializeShiftElements()) { 
        if(mainContentArea) mainContentArea.innerHTML = `<p class="text-red-500 p-4">Error initializing shifts section elements.</p>`;
        return;
    }

    pageTitleElement.textContent = 'Manage Shifts';
    mainContentArea.innerHTML = `
        <div class="bg-white p-6 rounded-lg shadow-md border border-[#F7E6CA] space-y-6">
            <div class="flex justify-between items-center border-b border-gray-200 pb-4">
                <h3 class="text-lg font-semibold text-[#4E3B2A] font-header">Shift Management</h3>
                <button id="open-add-shift-modal-btn" class="px-4 py-2 bg-[#594423] text-white rounded-md hover:bg-[#4E3B2A] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#594423] transition duration-150 ease-in-out flex items-center space-x-2">
                    <i class="fa-solid fa-plus"></i>
                    <span>Add New Shift</span>
                </button>
            </div>
            <div>
                <h3 class="text-lg font-semibold text-[#4E3B2A] mb-3 font-header">Existing Shifts</h3>
                <div id="shifts-list-container" class="overflow-x-auto">
                    <p class="text-center py-4">Loading shifts...</p>
                </div>
            </div>
        </div>`;

    const openModalBtn = document.getElementById('open-add-shift-modal-btn');
    if (openModalBtn) {
        if (!openModalBtn.hasAttribute('data-listener-attached')) {
            openModalBtn.addEventListener('click', openAddShiftModal);
            openModalBtn.setAttribute('data-listener-attached', 'true');
        }
    } else {
        console.error("Open Add Shift Modal button not found.");
    }
    
    if (closeAddShiftModalBtn && !closeAddShiftModalBtn.hasAttribute('data-listener-attached')) {
        closeAddShiftModalBtn.addEventListener('click', closeAddShiftModal);
        closeAddShiftModalBtn.setAttribute('data-listener-attached', 'true');
    }
    if (cancelAddShiftModalBtn && !cancelAddShiftModalBtn.hasAttribute('data-listener-attached')) {
        cancelAddShiftModalBtn.addEventListener('click', closeAddShiftModal);
        cancelAddShiftModalBtn.setAttribute('data-listener-attached', 'true');
    }
    if (addShiftModalOverlay && !addShiftModalOverlay.hasAttribute('data-listener-attached')) {
        addShiftModalOverlay.addEventListener('click', closeAddShiftModal); 
        addShiftModalOverlay.setAttribute('data-listener-attached', 'true');
    }

    if (addShiftModalForm) {
         if (!addShiftModalForm.hasAttribute('data-listener-attached')) {
            addShiftModalForm.addEventListener('submit', handleAddShift);
            addShiftModalForm.setAttribute('data-listener-attached', 'true');
         }
    } else { console.error("Add Shift modal form not found for attaching listener."); }

    await loadShifts();
}

/**
 * Fetches shifts data from the API and renders it.
 */
async function loadShifts() {
    console.log("[Load] Loading Shifts...");
    const container = document.getElementById('shifts-list-container');
    if (!container) return;
    container.innerHTML = '<p class="text-center py-4">Loading shifts...</p>';
    const url = `${API_BASE_URL}get_shifts.php`;
    try {
        const response = await fetch(url);
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        const shifts = await response.json();
        if (shifts.error) {
            console.error("Error fetching shifts:", shifts.error);
            container.innerHTML = `<p class="text-red-500 text-center py-4">Error: ${shifts.error}</p>`;
        } else { renderShiftsTable(shifts); }
    } catch (error) {
        console.error('Error loading shifts:', error);
        container.innerHTML = `<p class="text-red-500 text-center py-4">Could not load shifts. ${error.message}</p>`;
    }
}

/**
 * Renders the shifts data into an HTML table.
 * @param {Array} shifts - An array of shift objects.
 */
function renderShiftsTable(shifts) {
    console.log("[Render] Rendering Shifts Table...");
    const container = document.getElementById('shifts-list-container');
    if (!container) return;
    if (!shifts || shifts.length === 0) {
        container.innerHTML = '<p class="text-center py-4 text-gray-500">No shifts defined yet.</p>';
        return;
    }
    let tableHtml = `
        <table class="min-w-full divide-y divide-gray-200 border border-gray-300">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Shift Name</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Start Time</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">End Time</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Break (mins)</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">`;
    shifts.forEach(shift => {
        tableHtml += `
            <tr id="shift-row-${shift.ShiftID}">
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">${shift.ShiftID}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">${shift.ShiftName}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">${shift.StartTimeFormatted || shift.StartTime}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">${shift.EndTimeFormatted || shift.EndTime}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">${shift.BreakDurationMinutes !== null ? shift.BreakDurationMinutes : '0'}</td>
            </tr>`;
    });
    tableHtml += `</tbody></table>`;
    container.innerHTML = tableHtml;
}

/**
 * Handles Add Shift form submission from the modal.
 * @param {Event} event - The form submission event.
 */
async function handleAddShift(event) {
    event.preventDefault();
    const form = document.getElementById('add-shift-modal-form'); 
    const submitButton = form.querySelector('button[type="submit"]');

    if (!form || !submitButton) { 
        console.error("Add Shift modal form or submit button missing."); 
        return; 
    }

    const shiftName = form.elements['shift_name'].value.trim();
    const startTime = form.elements['start_time'].value;
    const endTime = form.elements['end_time'].value;

    if (!shiftName || !startTime || !endTime) { 
        Swal.fire({
            icon: 'error',
            title: 'Validation Error',
            text: 'Shift Name, Start Time, and End Time are required.',
            confirmButtonColor: '#4E3B2A'
        });
        return; 
    }
    
    const formData = { 
        shift_name: shiftName, 
        start_time: startTime, 
        end_time: endTime, 
        break_duration: form.elements['break_duration'].value || 0 
    };

    Swal.fire({
        title: 'Processing...',
        text: 'Adding new shift, please wait.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    submitButton.disabled = true;

    try {
        const response = await fetch(`${API_BASE_URL}add_shift.php`, {
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
        
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: result.message || 'Shift added successfully!',
            confirmButtonColor: '#4E3B2A',
            timer: 2000 
        });
        
        await loadShifts(); 
        closeAddShiftModal();

    } catch (error) {
        console.error('Error adding shift:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error Adding Shift',
            text: `An error occurred: ${error.message}`,
            confirmButtonColor: '#4E3B2A'
        });
    } finally { 
        submitButton.disabled = false; 
        if (Swal.isLoading()) {
            Swal.close();
        }
    }
}
