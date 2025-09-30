/**
 * Payroll - Salaries Module
 */
import { API_BASE_URL, populateEmployeeDropdown } from '../utils.js'; // Import shared functions/constants

// --- Function to Display Salaries Section ---
export async function displaySalariesSection() {
    console.log("[Display] Displaying Salaries Section..."); // Debug Log
    const pageTitleElement = document.getElementById('page-title');
    const mainContentArea = document.getElementById('main-content-area');
    if (!pageTitleElement || !mainContentArea) {
         console.error("displaySalariesSection: Core DOM elements not found.");
         return;
    }
    pageTitleElement.textContent = 'Employee Salaries'; // Update page title

    // Inject HTML structure for the salaries section
    mainContentArea.innerHTML = `
        <div class="bg-white p-6 rounded-lg shadow-md border border-[#F7E6CA] space-y-6">
            <div class="border-b border-gray-200 pb-4">
                <h3 class="text-lg font-semibold text-[#4E3B2A] mb-3">Add / Update Salary</h3>
                <form id="add-update-salary-form" class="space-y-4">
                     <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div>
                            <label for="salary-employee-select" class="block text-sm font-medium text-gray-700 mb-1">Employee:</label>
                            <select id="salary-employee-select" name="employee_id" required class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                                <option value="">Loading employees...</option>
                            </select>
                        </div>
                        <div>
                            <label for="salary-base" class="block text-sm font-medium text-gray-700 mb-1">Base Salary:</label>
                            <input type="number" id="salary-base" name="base_salary" required step="0.01" min="0" placeholder="e.g., 50000.00" class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                        </div>
                         <div>
                            <label for="salary-frequency" class="block text-sm font-medium text-gray-700 mb-1">Pay Frequency:</label>
                            <select id="salary-frequency" name="pay_frequency" required class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                                <option value="">-- Select --</option>
                                <option value="Monthly">Monthly</option>
                                <option value="Bi-Weekly">Bi-Weekly</option>
                                <option value="Weekly">Weekly</option>
                                <option value="Hourly">Hourly</option>
                            </select>
                        </div>
                        <div>
                            <label for="salary-pay-rate" class="block text-sm font-medium text-gray-700 mb-1">Pay Rate (if Hourly):</label>
                            <input type="number" id="salary-pay-rate" name="pay_rate" step="0.01" min="0" placeholder="e.g., 250.00" class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                        </div>
                        <div>
                            <label for="salary-effective-date" class="block text-sm font-medium text-gray-700 mb-1">Effective Date:</label>
                            <input type="date" id="salary-effective-date" name="effective_date" required class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                        </div>
                    </div>
                    <div class="pt-2">
                        <button type="submit" class="px-4 py-2 bg-[#4727ff] text-white rounded-md hover:bg-[#3a1fcc] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#4727ff] transition duration-150 ease-in-out">
                            Save Salary Info
                        </button>
                        <span id="add-salary-status" class="ml-4 text-sm"></span> </div>
                </form>
            </div>

            <div>
                <h3 class="text-lg font-semibold text-[#4E3B2A] mb-3">Current Employee Salaries</h3>
                 <div id="salaries-list-container" class="overflow-x-auto">
                    <p>Loading salaries...</p>
                </div>
            </div>
        </div>`;

    // Add listener after HTML injection
    requestAnimationFrame(async () => {
        await populateEmployeeDropdown('salary-employee-select'); // Populate dropdown for the form
        const addSalaryForm = document.getElementById('add-update-salary-form');
        if (addSalaryForm) {
             if (!addSalaryForm.hasAttribute('data-listener-attached')) {
                addSalaryForm.addEventListener('submit', handleAddSalary);
                addSalaryForm.setAttribute('data-listener-attached', 'true');
             }
        } else { console.error("Add/Update Salary form not found after injecting HTML."); }
        await loadSalaries(); // Load initial list
    });
}

 // --- Function to Load Salaries Data ---
async function loadSalaries() {
    console.log("[Load] Loading Salaries..."); // Debug Log
    const container = document.getElementById('salaries-list-container');
    if (!container) return;
    container.innerHTML = '<p class="text-center py-4">Loading salaries...</p>'; // Loading indicator

    const url = `${API_BASE_URL}get_salaries.php`; // Add filters later if needed

    try {
        const response = await fetch(url);
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        const salaries = await response.json();

        if (salaries.error) {
            console.error("Error fetching salaries:", salaries.error);
            container.innerHTML = `<p class="text-red-500 text-center py-4">Error: ${salaries.error}</p>`;
        } else {
            renderSalariesTable(salaries); // Render the table
        }
    } catch (error) {
        console.error('Error loading salaries:', error);
        container.innerHTML = `<p class="text-red-500 text-center py-4">Could not load salaries. ${error.message}</p>`;
    }
}

// --- Function to Render Salaries Table ---
function renderSalariesTable(salaries) {
    console.log("[Render] Rendering Salaries Table..."); // Debug Log
    const container = document.getElementById('salaries-list-container');
    if (!container) return;

    if (!salaries || salaries.length === 0) {
        container.innerHTML = '<p class="text-center py-4 text-gray-500">No current salary records found.</p>';
        return;
    }

    // Create table structure
    let tableHtml = `
        <table class="min-w-full divide-y divide-gray-200 border border-gray-300">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Emp. ID</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee Name</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Job Title</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Base Salary</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Frequency</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pay Rate (Hourly)</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Effective Date</th>
                    </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">`;

    // Add rows for each salary record
    salaries.forEach(salary => {
        tableHtml += `
            <tr id="salary-row-${salary.SalaryID}">
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">${salary.EmployeeID}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">${salary.EmployeeName}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">${salary.JobTitle || '-'}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700 text-right">${salary.BaseSalaryFormatted || '-'}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">${salary.PayFrequency || '-'}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700 text-right">${salary.PayRateFormatted}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">${salary.EffectiveDateFormatted || salary.EffectiveDate}</td>
                </tr>`;
    });

    tableHtml += `
            </tbody>
        </table>`;

    container.innerHTML = tableHtml;
    // Add event listeners for edit/delete buttons here if they exist
}

// --- Function to Handle Add/Update Salary Form Submission ---
async function handleAddSalary(event) {
    event.preventDefault(); // Prevent default form submission
    const form = event.target;
    const statusSpan = document.getElementById('add-salary-status');
    const submitButton = form.querySelector('button[type="submit"]');

    if (!form || !statusSpan || !submitButton) {
         console.error("Add/Update Salary form elements missing.");
         return;
    }

    // Basic Client-side validation
    const employeeId = form.elements['employee_id'].value;
    const baseSalary = form.elements['base_salary'].value;
    const payFrequency = form.elements['pay_frequency'].value;
    const effectiveDate = form.elements['effective_date'].value;

    if (!employeeId || !baseSalary || !payFrequency || !effectiveDate) {
        statusSpan.textContent = 'Employee, Base Salary, Pay Frequency, and Effective Date are required.';
        statusSpan.className = 'ml-4 text-sm text-red-600';
        return;
    }

    const formData = {
        employee_id: employeeId,
        base_salary: parseFloat(baseSalary), // Ensure it's a number
        pay_frequency: payFrequency,
        pay_rate: form.elements['pay_rate'].value ? parseFloat(form.elements['pay_rate'].value) : null, // Send null if empty
        effective_date: effectiveDate
    };

    statusSpan.textContent = 'Saving salary info...';
    statusSpan.className = 'ml-4 text-sm text-blue-600';
    submitButton.disabled = true;

    try {
        const response = await fetch(`${API_BASE_URL}add_update_salary.php`, {
            method: 'POST',
             headers: {
                'Content-Type': 'application/json' // Sending JSON
            },
            body: JSON.stringify(formData) // Convert JS object to JSON string
        });

        const result = await response.json();

        if (!response.ok) {
            // Handle validation errors specifically if provided
            if (response.status === 400 && result.details) {
                 const errorMessages = Object.values(result.details).join(' ');
                 throw new Error(errorMessages || result.error || `HTTP error! status: ${response.status}`);
            }
            throw new Error(result.error || `HTTP error! status: ${response.status}`);
        }

        statusSpan.textContent = result.message || 'Salary updated successfully!';
        statusSpan.className = 'ml-4 text-sm text-green-600';
        form.reset(); // Clear the form
        await loadSalaries(); // Refresh the salaries list

        // Optionally clear message after a few seconds
        setTimeout(() => { if(statusSpan.textContent === (result.message || 'Salary updated successfully!')) statusSpan.textContent = ''; }, 5000);


    } catch (error) {
        console.error('Error updating salary:', error);
        statusSpan.textContent = `Error: ${error.message}`;
        statusSpan.className = 'ml-4 text-sm text-red-600';
    } finally {
        submitButton.disabled = false; // Re-enable button
    }
}
