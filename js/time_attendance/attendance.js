// hr34/js/time_attendance/attendance.js
/**
 * Time & Attendance - Attendance Module
 */
import { API_BASE_URL, populateEmployeeDropdown } from '../utils.js'; // Import shared functions/constants

// --- Function to Display Attendance Section ---
export async function displayAttendanceSection() {
    console.log("[Display] Displaying Attendance Section...");
    const pageTitleElement = document.getElementById('page-title');
    const mainContentArea = document.getElementById('main-content-area');
    if (!pageTitleElement || !mainContentArea) {
        console.error("displayAttendanceSection: Core DOM elements not found.");
        return;
    }
    pageTitleElement.textContent = 'Attendance Records';
    mainContentArea.innerHTML = `
        <div class="bg-white p-6 rounded-lg shadow-md border border-[#F7E6CA] space-y-6">
            <div class="flex flex-wrap gap-4 mb-4 items-end">
                <div>
                    <label for="filter-attendance-employee" class="block text-sm font-medium text-gray-700 mb-1">Filter by Employee:</label>
                    <select id="filter-attendance-employee" class="w-full sm:w-auto p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                        <option value="">All Employees</option>
                    </select>
                </div>
                <div>
                    <label for="filter-attendance-start-date" class="block text-sm font-medium text-gray-700 mb-1">Start Date:</label>
                    <input type="date" id="filter-attendance-start-date" class="w-full sm:w-auto p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                </div>
                <div>
                    <label for="filter-attendance-end-date" class="block text-sm font-medium text-gray-700 mb-1">End Date:</label>
                    <input type="date" id="filter-attendance-end-date" class="w-full sm:w-auto p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                </div>
                <div>
                    <button id="filter-attendance-btn" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-150 ease-in-out">
                        Filter
                    </button>
                </div>
            </div>
            <div id="attendance-list-container" class="overflow-x-auto">
                <p>Loading attendance records...</p>
            </div>
        </div>
    `;

    // Add event listeners and load initial data
    requestAnimationFrame(async () => {
        await populateEmployeeDropdown('filter-attendance-employee', true);
        const filterBtn = document.getElementById('filter-attendance-btn');
        if (filterBtn) {
            filterBtn.addEventListener('click', applyAttendanceFilter);
        }
        await loadAttendanceRecords();
    });
}

// --- Function to Apply Attendance Filter ---
function applyAttendanceFilter() {
    const employeeId = document.getElementById('filter-attendance-employee')?.value;
    const startDate = document.getElementById('filter-attendance-start-date')?.value;
    const endDate = document.getElementById('filter-attendance-end-date')?.value;
    loadAttendanceRecords(employeeId, startDate, endDate);
}

// --- Function to Load Attendance Records ---
async function loadAttendanceRecords(employeeId = null, startDate = null, endDate = null) {
    console.log("[Load] Loading Attendance Records...");
    const container = document.getElementById('attendance-list-container');
    if (!container) return;
    container.innerHTML = '<p class="text-center py-4">Loading attendance records...</p>';

    const params = new URLSearchParams();
    if (employeeId) params.append('employee_id', employeeId);
    if (startDate) params.append('start_date', startDate);
    if (endDate) params.append('end_date', endDate);

    const url = `${API_BASE_URL}get_attendance.php?${params.toString()}`;

    try {
        const response = await fetch(url);
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        const records = await response.json();
        if (records.error) {
            console.error("Error fetching attendance records:", records.error);
            container.innerHTML = `<p class="text-red-500 text-center py-4">Error: ${records.error}</p>`;
        } else {
            renderAttendanceTable(records);
        }
    } catch (error) {
        console.error('Error loading attendance records:', error);
        container.innerHTML = `<p class="text-red-500 text-center py-4">Could not load attendance records. ${error.message}</p>`;
    }
}

// --- Function to Render Attendance Table ---
function renderAttendanceTable(records) {
    console.log("[Render] Rendering Attendance Table...");
    const container = document.getElementById('attendance-list-container');
    if (!container) return;
    if (!records || records.length === 0) {
        container.innerHTML = '<p class="text-center py-4 text-gray-500">No attendance records found.</p>';
        return;
    }

    let tableHtml = `
        <table class="min-w-full divide-y divide-gray-200 border border-gray-300">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Clock In</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Clock Out</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">`;

    records.forEach(record => {
        tableHtml += `
            <tr id="attendance-row-${record.RecordID}">
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">${record.RecordID}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">${record.EmployeeName}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">${record.AttendanceDateFormatted || record.AttendanceDate}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">${record.ClockInTimeFormatted || '-'}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">${record.ClockOutTimeFormatted || '-'}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">${record.Status || '-'}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">${record.Notes || '-'}</td>
            </tr>`;
    });

    tableHtml += `</tbody></table>`;
    container.innerHTML = tableHtml;
}