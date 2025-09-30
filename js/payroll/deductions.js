/**
 * Payroll - Deductions Module
 */
import { API_BASE_URL, populateEmployeeDropdown } from '../utils.js'; // Import shared functions/constants

// TODO: Need a function to populate Payroll Run dropdown if adding deductions linked to specific runs.
// async function populatePayrollRunDropdown(selectElementId) { ... }

/**
 * Displays the Deductions section.
 */
export async function displayDeductionsSection() {
    console.log("[Display] Displaying Deductions Section...");
    const pageTitleElement = document.getElementById('page-title');
    const mainContentArea = document.getElementById('main-content-area');
    if (!pageTitleElement || !mainContentArea) {
        console.error("displayDeductionsSection: Core DOM elements not found.");
        return;
    }
    pageTitleElement.textContent = 'Employee Deductions';

    // Inject HTML structure
    mainContentArea.innerHTML = `
        <div class="bg-white p-6 rounded-lg shadow-md border border-[#F7E6CA] space-y-6">
            <div class="border-b border-gray-200 pb-4">
                <h3 class="text-lg font-semibold text-[#4E3B2A] mb-3">Add New Deduction Record</h3>
                <form id="add-deduction-form" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div>
                            <label for="deduction-employee-select" class="block text-sm font-medium text-gray-700 mb-1">Employee:</label>
                            <select id="deduction-employee-select" name="employee_id" required class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                                <option value="">Loading employees...</option>
                            </select>
                        </div>
                        <div>
                            <label for="deduction-payroll-select" class="block text-sm font-medium text-gray-700 mb-1">Payroll Run ID:</label>
                            <input type="number" id="deduction-payroll-select" name="payroll_id" required placeholder="Enter Payroll Run ID" class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                             </div>
                        <div>
                            <label for="deduction-type" class="block text-sm font-medium text-gray-700 mb-1">Deduction Type:</label>
                            <input type="text" id="deduction-type" name="deduction_type" required placeholder="e.g., SSS, PhilHealth, Pag-IBIG, Loan" class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                        </div>
                        <div>
                            <label for="deduction-amount" class="block text-sm font-medium text-gray-700 mb-1">Amount:</label>
                            <input type="number" id="deduction-amount" name="deduction_amount" required step="0.01" min="0.01" placeholder="e.g., 500.00" class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                        </div>
                        <div>
                            <label for="deduction-provider" class="block text-sm font-medium text-gray-700 mb-1">Provider (Optional):</label>
                            <input type="text" id="deduction-provider" name="provider" placeholder="e.g., Insurance Co." class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                        </div>
                    </div>
                    <div class="pt-2">
                        <button type="submit" class="px-4 py-2 bg-[#594423] text-white rounded-md hover:bg-[#4E3B2A] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#594423] transition duration-150 ease-in-out">
                            Add Deduction
                        </button>
                        <span id="add-deduction-status" class="ml-4 text-sm"></span>
                    </div>
                </form>
            </div>

            <div>
                <h3 class="text-lg font-semibold text-[#4E3B2A] mb-3">Deduction Records</h3>
                 <div class="flex flex-wrap gap-4 mb-4 items-end">
                      <div>
                        <label for="filter-deduction-employee" class="block text-sm font-medium text-gray-700 mb-1">Filter by Employee:</label>
                        <select id="filter-deduction-employee" class="w-full sm:w-auto p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                            <option value="">All Employees</option>
                            </select>
                     </div>
                     <div>
                        <label for="filter-deduction-payroll" class="block text-sm font-medium text-gray-700 mb-1">Filter by Payroll Run ID:</label>
                        <input type="number" id="filter-deduction-payroll" placeholder="Enter Payroll ID" class="w-full sm:w-auto p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                     </div>
                     <div>
                        <button id="filter-deduction-btn" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-150 ease-in-out">
                            Filter
                        </button>
                     </div>
                 </div>
                <div id="deductions-list-container" class="overflow-x-auto">
                    <p>Loading deductions...</p>
                </div>
            </div>
        </div>`;

    // Add listeners and populate dropdowns after HTML injection
    requestAnimationFrame(async () => {
        await populateEmployeeDropdown('deduction-employee-select'); // For add form
        await populateEmployeeDropdown('filter-deduction-employee', true); // For filter
        // TODO: await populatePayrollRunDropdown('deduction-payroll-select'); // Need this function and endpoint

        const addDeductionForm = document.getElementById('add-deduction-form');
        if (addDeductionForm) {
            if (!addDeductionForm.hasAttribute('data-listener-attached')) {
                addDeductionForm.addEventListener('submit', handleAddDeduction);
                addDeductionForm.setAttribute('data-listener-attached', 'true');
            }
        } else { console.error("Add Deduction form not found."); }

        const filterBtn = document.getElementById('filter-deduction-btn');
        if (filterBtn) {
             if (!filterBtn.hasAttribute('data-listener-attached')) {
                filterBtn.addEventListener('click', applyDeductionFilter);
                filterBtn.setAttribute('data-listener-attached', 'true');
             }
        } else { console.error("Filter Deduction button not found."); }

        await loadDeductions(); // Load initial list
    });
}

/**
 * Applies filters and reloads the deductions list.
 */
function applyDeductionFilter() {
    const employeeId = document.getElementById('filter-deduction-employee')?.value;
    const payrollId = document.getElementById('filter-deduction-payroll')?.value;
    loadDeductions(employeeId, payrollId);
}

/**
 * Fetches deduction records from the API.
 * @param {string|null} [employeeId=null] - Employee ID to filter by.
 * @param {string|null} [payrollId=null] - Payroll Run ID to filter by.
 */
async function loadDeductions(employeeId = null, payrollId = null) {
    console.log(`[Load] Loading Deductions... (Employee: ${employeeId || 'All'}, Payroll: ${payrollId || 'All'})`);
    const container = document.getElementById('deductions-list-container');
    if (!container) return;
    container.innerHTML = '<p class="text-center py-4">Loading deductions...</p>';

    const params = new URLSearchParams();
    if (employeeId) params.append('employee_id', employeeId);
    if (payrollId) params.append('payroll_id', payrollId);

    const url = `${API_BASE_URL}get_deductions.php?${params.toString()}`;

    try {
        const response = await fetch(url);
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        const deductions = await response.json();

        if (deductions.error) {
            console.error("Error fetching deductions:", deductions.error);
            container.innerHTML = `<p class="text-red-500 text-center py-4">Error: ${deductions.error}</p>`;
        } else {
            renderDeductionsTable(deductions);
        }
    } catch (error) {
        console.error('Error loading deductions:', error);
        container.innerHTML = `<p class="text-red-500 text-center py-4">Could not load deductions. ${error.message}</p>`;
    }
}

/**
 * Renders the deductions data into an HTML table.
 * @param {Array} deductions - An array of deduction objects.
 */
function renderDeductionsTable(deductions) {
    console.log("[Render] Rendering Deductions Table...");
    const container = document.getElementById('deductions-list-container');
    if (!container) return;

    if (!deductions || deductions.length === 0) {
        container.innerHTML = '<p class="text-center py-4 text-gray-500">No deduction records found.</p>';
        return;
    }

    let tableHtml = `
        <table class="min-w-full divide-y divide-gray-200 border border-gray-300">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payroll Run ID</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Provider</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">`;

    deductions.forEach(ded => {
        tableHtml += `
            <tr id="deduction-row-${ded.DeductionID}">
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">${ded.DeductionID}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">${ded.EmployeeName || 'N/A'}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">${ded.PayrollID}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">${ded.DeductionType}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700 text-right">${ded.DeductionAmountFormatted || ded.DeductionAmount}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">${ded.Provider || '-'}</td>
                </tr>`;
    });

    tableHtml += `</tbody></table>`;
    container.innerHTML = tableHtml;
}

/**
 * Handles the submission of the add deduction form.
 */
async function handleAddDeduction(event) {
    event.preventDefault();
    const form = event.target;
    const statusSpan = document.getElementById('add-deduction-status');
    const submitButton = form.querySelector('button[type="submit"]');
    if (!form || !statusSpan || !submitButton) {
        console.error("Add Deduction form elements missing.");
        return;
    }

    // Client-side validation
    const employeeId = form.elements['employee_id'].value;
    const payrollId = form.elements['payroll_id'].value;
    const deductionType = form.elements['deduction_type'].value.trim();
    const deductionAmount = form.elements['deduction_amount'].value;

    if (!employeeId || !payrollId || !deductionType || !deductionAmount) {
        statusSpan.textContent = 'Employee, Payroll Run ID, Type, and Amount are required.';
        statusSpan.className = 'ml-4 text-sm text-red-600';
        return;
    }
    if (parseFloat(deductionAmount) <= 0) {
         statusSpan.textContent = 'Deduction Amount must be positive.';
         statusSpan.className = 'ml-4 text-sm text-red-600';
         return;
    }


    const formData = {
        employee_id: parseInt(employeeId),
        payroll_id: parseInt(payrollId),
        deduction_type: deductionType,
        deduction_amount: parseFloat(deductionAmount),
        provider: form.elements['provider'].value.trim() || null
    };

    statusSpan.textContent = 'Adding deduction...';
    statusSpan.className = 'ml-4 text-sm text-blue-600';
    submitButton.disabled = true;

    try {
        const response = await fetch(`${API_BASE_URL}add_deduction.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        });

        const result = await response.json();

        if (!response.ok) {
            if (response.status === 400 && result.details) {
                const errorMessages = Object.values(result.details).join(' ');
                throw new Error(errorMessages || result.error || `HTTP error! status: ${response.status}`);
            }
            throw new Error(result.error || `HTTP error! status: ${response.status}`);
        }

        statusSpan.textContent = result.message || 'Deduction added successfully!';
        statusSpan.className = 'ml-4 text-sm text-green-600';
        form.reset(); // Clear the form
        await loadDeductions(); // Refresh the list

        setTimeout(() => { if(statusSpan.textContent === (result.message || 'Deduction added successfully!')) statusSpan.textContent = ''; }, 5000);

    } catch (error) {
        console.error('Error adding deduction:', error);
        statusSpan.textContent = `Error: ${error.message}`;
        statusSpan.className = 'ml-4 text-sm text-red-600';
    } finally {
        submitButton.disabled = false;
    }
}
