/**
 * Payroll - Payslips Module
 * Handles viewing of generated payslips by opening a printable HTML page.
 * v1.7 - Changed view action to open printable HTML payslip instead of modal.
 */
import { API_BASE_URL, populateEmployeeDropdown } from '../utils.js';

// --- DOM Element References ---
let pageTitleElement;
let mainContentArea;
// Modal elements are no longer needed for viewing, but keep if other modals use similar IDs
// let payslipModal;
// let payslipModalOverlay;
// let payslipModalCloseBtn;

/**
 * Initializes common elements used by the payslips module.
 * Modal related initializations are removed.
 */
function initializePayslipElements() {
    pageTitleElement = document.getElementById('page-title');
    mainContentArea = document.getElementById('main-content-area');

    if (!pageTitleElement || !mainContentArea) {
        console.error("Payslips Module: Core DOM elements not found!");
        return false;
    }
    return true;
}

/**
 * Displays the Payslips Viewing Section.
 */
export async function displayPayslipsSection() {
    if (!initializePayslipElements()) {
         const mainContent = document.getElementById('main-content-area');
         if (mainContent) {
             mainContent.innerHTML = `<p class="text-red-500 p-4">Error initializing payslip section elements.</p>`;
         }
         return;
    }

    const user = window.currentUser;
    const isAdminType = ['System Admin', 'HR Admin'].includes(user?.role_name);
    const employeeId = user?.employee_id;

    pageTitleElement.textContent = 'View Payslips';

    const employeeFilterHtml = isAdminType ? `
        <div>
            <label for="filter-payslip-employee" class="block text-sm font-medium text-gray-700 mb-1">Filter by Employee:</label>
            <select id="filter-payslip-employee" class="w-full sm:w-auto p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                <option value="">All Employees</option>
            </select>
        </div>` : '';

    mainContentArea.innerHTML = `
        <div class="bg-white p-6 rounded-lg shadow-md border border-[#F7E6CA] space-y-6">
            <h3 class="text-lg font-semibold text-[#4E3B2A] mb-3">Payslip History</h3>
            <div class="flex flex-wrap gap-4 mb-4 items-end">
                ${employeeFilterHtml}
                <div>
                    <label for="filter-payslip-payroll-id" class="block text-sm font-medium text-gray-700 mb-1">Filter by Payroll Run ID:</label>
                    <input type="number" id="filter-payslip-payroll-id" placeholder="Enter Payroll ID" class="w-full sm:w-auto p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                </div>
                <div>
                    <button id="filter-payslip-btn" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-150 ease-in-out">
                        Filter Payslips
                    </button>
                </div>
            </div>
            <div id="payslips-list-container" class="overflow-x-auto">
                <p class="text-center py-4">Loading payslips...</p>
            </div>
        </div>
        `; // Modal HTML removed from here

    requestAnimationFrame(async () => {
        if (isAdminType) {
            await populateEmployeeDropdown('filter-payslip-employee', true);
        }

        const filterBtn = document.getElementById('filter-payslip-btn');
        if (filterBtn) {
            if (!filterBtn.hasAttribute('data-listener-attached')) {
                filterBtn.addEventListener('click', applyPayslipFilter);
                filterBtn.setAttribute('data-listener-attached', 'true');
            }
        } else { console.error("Filter Payslip button not found."); }

        await loadPayslips(isAdminType ? null : employeeId);
    });
}

/**
 * Applies filters and reloads the payslips list.
 */
function applyPayslipFilter() {
    const user = window.currentUser;
    const isAdminType = ['System Admin', 'HR Admin'].includes(user?.role_name);
    let employeeId = null;

    if (isAdminType) {
        employeeId = document.getElementById('filter-payslip-employee')?.value || null;
    } else {
        employeeId = user?.employee_id;
    }

    const payrollId = document.getElementById('filter-payslip-payroll-id')?.value || null;
    loadPayslips(employeeId, payrollId);
}

/**
 * Fetches payslip records from the API.
 * @param {string|null} [employeeId=null] - Employee ID to filter by.
 * @param {string|null} [payrollId=null] - Payroll Run ID to filter by.
 */
async function loadPayslips(employeeId = null, payrollId = null) {
    const container = document.getElementById('payslips-list-container');
    if (!container) {
        console.error("[Load] Payslips list container not found.");
        return;
    }
    container.innerHTML = '<p class="text-center py-4">Loading payslips...</p>';

    const params = new URLSearchParams();
    if (employeeId) params.append('employee_id', employeeId);
    if (payrollId) params.append('payroll_id', payrollId);

    const url = `${API_BASE_URL}get_payslips.php?${params.toString()}`;

    try {
        const response = await fetch(url);
        const payslips = await handleApiResponse(response);
        renderPayslipsTable(payslips);
    } catch (error) {
        console.error('[Load] Error loading payslips:', error);
        container.innerHTML = `<p class="text-red-500 text-center py-4">Could not load payslips. ${error.message}</p>`;
    }
}

/**
 * Renders the payslips data into an HTML table.
 * @param {Array} payslips - An array of payslip objects.
 */
function renderPayslipsTable(payslips) {
    const container = document.getElementById('payslips-list-container');
    if (!container) {
        console.error("[Render] Payslips list container not found.");
        return;
    }
    container.innerHTML = '';

    if (!payslips || payslips.length === 0) {
        container.innerHTML = '<p class="text-center py-4 text-gray-500">No payslips found for the selected criteria.</p>';
        return;
    }

    const table = document.createElement('table');
    table.className = 'min-w-full divide-y divide-gray-200 border border-gray-300';

    const thead = table.createTHead();
    thead.className = 'bg-gray-50';
    const headerRow = thead.insertRow();
    const headers = ['Payslip ID', 'Payroll ID', 'Employee', 'Pay Period', 'Payment Date', 'Gross Income', 'Total Deductions', 'Net Income', 'Actions'];
    headers.forEach(text => {
        const th = document.createElement('th');
        th.scope = 'col';
        th.className = 'px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider';
        th.textContent = text;
        headerRow.appendChild(th);
    });

    const tbody = table.createTBody();
    tbody.className = 'bg-white divide-y divide-gray-200 payslip-action-container';

    payslips.forEach(ps => {
        const row = tbody.insertRow();
        row.id = `payslip-row-${ps.PayslipID}`;

        const createCell = (text) => {
            const cell = row.insertCell();
            cell.className = 'px-4 py-3 whitespace-nowrap text-sm';
            cell.textContent = text ?? '-';
            return cell;
        };

        createCell(ps.PayslipID).classList.add('text-gray-500');
        createCell(ps.PayrollID).classList.add('text-gray-700');
        createCell(ps.EmployeeName).classList.add('font-medium', 'text-gray-900');
        createCell(`${ps.PayPeriodStartDateFormatted} - ${ps.PayPeriodEndDateFormatted}`).classList.add('text-gray-700');
        createCell(ps.PaymentDateFormatted).classList.add('text-gray-700');
        createCell(ps.GrossIncomeFormatted).classList.add('text-gray-700', 'text-right');
        createCell(ps.TotalDeductionsFormatted).classList.add('text-red-600', 'text-right');
        createCell(ps.NetIncomeFormatted).classList.add('text-green-600', 'font-semibold', 'text-right');

        const actionsCell = row.insertCell();
        actionsCell.className = 'px-4 py-3 whitespace-nowrap text-sm font-medium space-x-2';
        const viewBtn = document.createElement('button');
        viewBtn.className = 'text-blue-600 hover:text-blue-800 view-payslip-btn';
        viewBtn.dataset.payslipId = ps.PayslipID;
        viewBtn.title = 'View/Print Payslip';
        viewBtn.innerHTML = '<i class="fas fa-print"></i> View/Print'; // Changed icon and text
        actionsCell.appendChild(viewBtn);
    });

    container.appendChild(table);
    attachPayslipActionListeners();
}

/**
 * Attaches event listeners for payslip actions (View/Print).
 */
function attachPayslipActionListeners() {
    const container = document.querySelector('.payslip-action-container');
    if (container) {
        container.removeEventListener('click', handlePayslipAction);
        container.addEventListener('click', handlePayslipAction);
    } else {
         console.warn("[Listeners] Payslip action container not found.");
    }
}

/**
 * Handles clicks within the payslip table body (delegated).
 * @param {Event} event
 */
function handlePayslipAction(event) {
    const viewButton = event.target.closest('.view-payslip-btn');
    if (viewButton) {
        const payslipId = viewButton.dataset.payslipId;
        viewOrPrintPayslip(payslipId);
    }
}

/**
 * Opens the printable HTML version of the payslip in a new tab.
 * @param {string} payslipId
 */
function viewOrPrintPayslip(payslipId) {
    console.log(`[View/Print Payslip] Action for Payslip ID: ${payslipId}`);
    if (!payslipId) {
        console.error("Invalid Payslip ID for viewing/printing.");
        alert("Error: Cannot generate payslip. Invalid ID.");
        return;
    }
    // Construct the URL to the new PHP script
    const payslipUrl = `${API_BASE_URL}generate_payslip_html.php?id=${payslipId}`;
    // Open the URL in a new tab
    window.open(payslipUrl, '_blank');
}


// --- Helper Functions ---
/**
 * Handles API response, checking status and parsing JSON.
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
                errorPayload.error += " (Non-JSON error response received)";
            }
        } else {
            const errorText = await response.text();
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
                 return [];
             }
             try {
                 data = JSON.parse(text);
                 return data;
             } catch (jsonError) {
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
