/**
 * Payroll - Bonuses Module
 */
import { API_BASE_URL, populateEmployeeDropdown } from '../utils.js'; // Import shared functions/constants

// --- Function to Display Bonuses Section ---
export async function displayBonusesSection() {
    console.log("[Display] Displaying Bonuses Section..."); // Debug Log
    const pageTitleElement = document.getElementById('page-title');
    const mainContentArea = document.getElementById('main-content-area');
    if (!pageTitleElement || !mainContentArea) {
         console.error("displayBonusesSection: Core DOM elements not found.");
         return;
    }
    pageTitleElement.textContent = 'Employee Bonuses'; // Update page title

    // Inject HTML structure for the bonuses section
    mainContentArea.innerHTML = `
        <div class="bg-white p-6 rounded-lg shadow-md border border-[#F7E6CA] space-y-6">
            <div class="border-b border-gray-200 pb-4">
                <h3 class="text-lg font-semibold text-[#4E3B2A] mb-3">Add New Bonus</h3>
                <form id="add-bonus-form" class="space-y-4">
                     <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div>
                            <label for="bonus-employee-select" class="block text-sm font-medium text-gray-700 mb-1">Employee:</label>
                            <select id="bonus-employee-select" name="employee_id" required class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                                <option value="">Loading employees...</option>
                            </select>
                        </div>
                        <div>
                            <label for="bonus-amount" class="block text-sm font-medium text-gray-700 mb-1">Bonus Amount:</label>
                            <input type="number" id="bonus-amount" name="bonus_amount" required step="0.01" min="0.01" placeholder="e.g., 5000.00" class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                        </div>
                         <div>
                            <label for="bonus-type" class="block text-sm font-medium text-gray-700 mb-1">Bonus Type (Optional):</label>
                            <input type="text" id="bonus-type" name="bonus_type" placeholder="e.g., Performance, Holiday" class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                        </div>
                        <div>
                            <label for="bonus-award-date" class="block text-sm font-medium text-gray-700 mb-1">Award Date:</label>
                            <input type="date" id="bonus-award-date" name="award_date" required class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                        </div>
                        <div>
                            <label for="bonus-payment-date" class="block text-sm font-medium text-gray-700 mb-1">Payment Date (Optional):</label>
                            <input type="date" id="bonus-payment-date" name="payment_date" class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                        </div>
                         </div>
                    <div class="pt-2">
                        <button type="submit" class="px-4 py-2 bg-[#594423] text-white rounded-md hover:bg-[#4E3B2A] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#594423] transition duration-150 ease-in-out">
                            Add Bonus
                        </button>
                        <span id="add-bonus-status" class="ml-4 text-sm"></span> </div>
                </form>
            </div>

            <div>
                <h3 class="text-lg font-semibold text-[#4E3B2A] mb-3">Bonus Records</h3>
                 <div class="flex flex-wrap gap-4 mb-4 items-end">
                      <div>
                        <label for="filter-bonus-employee" class="block text-sm font-medium text-gray-700 mb-1">Filter by Employee:</label>
                        <select id="filter-bonus-employee" class="w-full sm:w-auto p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                            <option value="">All Employees</option>
                            </select>
                     </div>
                     <div>
                        <button id="filter-bonus-btn" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-150 ease-in-out">
                            Filter
                        </button>
                     </div>
                 </div>
                <div id="bonuses-list-container" class="overflow-x-auto">
                    <p>Loading bonuses...</p>
                </div>
            </div>
        </div>`;

    // Add listener after HTML injection
    requestAnimationFrame(async () => {
        await populateEmployeeDropdown('bonus-employee-select'); // Populate dropdown for the form
        await populateEmployeeDropdown('filter-bonus-employee', true); // Populate filter dropdown
        // TODO: Populate Payroll Run dropdown if added to form

        const addBonusForm = document.getElementById('add-bonus-form');
        if (addBonusForm) {
             if (!addBonusForm.hasAttribute('data-listener-attached')) {
                addBonusForm.addEventListener('submit', handleAddBonus);
                addBonusForm.setAttribute('data-listener-attached', 'true');
             }
        } else { console.error("Add Bonus form not found after injecting HTML."); }

        const filterBtn = document.getElementById('filter-bonus-btn');
        if (filterBtn) {
             if (!filterBtn.hasAttribute('data-listener-attached')) {
                filterBtn.addEventListener('click', applyBonusFilter);
                filterBtn.setAttribute('data-listener-attached', 'true');
             }
        } else { console.error("Filter Bonus button not found after injecting HTML."); }

        await loadBonuses(); // Load initial list
    });
}

 // --- Function to Apply Bonus Filter ---
function applyBonusFilter() {
    const employeeId = document.getElementById('filter-bonus-employee')?.value;
    loadBonuses(employeeId); // Reload records with filter
}

// --- Function to Load Bonuses Data ---
async function loadBonuses(employeeId = null) {
    console.log("[Load] Loading Bonuses..."); // Debug Log
    const container = document.getElementById('bonuses-list-container');
    if (!container) return;
    container.innerHTML = '<p class="text-center py-4">Loading bonuses...</p>'; // Loading indicator

    const params = new URLSearchParams();
    if (employeeId) params.append('employee_id', employeeId);
    const url = `${API_BASE_URL}get_bonuses.php?${params.toString()}`;

    try {
        const response = await fetch(url);
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        const bonuses = await response.json();

        if (bonuses.error) {
            console.error("Error fetching bonuses:", bonuses.error);
            container.innerHTML = `<p class="text-red-500 text-center py-4">Error: ${bonuses.error}</p>`;
        } else {
            renderBonusesTable(bonuses); // Render the table
        }
    } catch (error) {
        console.error('Error loading bonuses:', error);
        container.innerHTML = `<p class="text-red-500 text-center py-4">Could not load bonuses. ${error.message}</p>`;
    }
}

// --- Function to Render Bonuses Table ---
function renderBonusesTable(bonuses) {
    console.log("[Render] Rendering Bonuses Table..."); // Debug Log
    const container = document.getElementById('bonuses-list-container');
    if (!container) return;

    if (!bonuses || bonuses.length === 0) {
        container.innerHTML = '<p class="text-center py-4 text-gray-500">No bonus records found for the selected criteria.</p>';
        return;
    }

    // Create table structure
    let tableHtml = `
        <table class="min-w-full divide-y divide-gray-200 border border-gray-300">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Award Date</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Date</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payroll Run ID</th>
                    </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">`;

    // Add rows for each bonus record
    bonuses.forEach(bonus => {
        tableHtml += `
            <tr id="bonus-row-${bonus.BonusID}">
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">${bonus.BonusID}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">${bonus.EmployeeName}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">${bonus.BonusType || '-'}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700 text-right">${bonus.BonusAmountFormatted || '-'}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">${bonus.AwardDateFormatted || bonus.AwardDate}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">${bonus.PaymentDateFormatted}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">${bonus.PayrollID || '-'}</td>
                </tr>`;
    });

    tableHtml += `
            </tbody>
        </table>`;

    container.innerHTML = tableHtml;
    // Add event listeners for edit/delete buttons here if they exist
}

// --- Function to Handle Add Bonus Form Submission ---
async function handleAddBonus(event) {
    event.preventDefault(); // Prevent default form submission
    const form = event.target;
    const statusSpan = document.getElementById('add-bonus-status');
    const submitButton = form.querySelector('button[type="submit"]');

    if (!form || !statusSpan || !submitButton) {
         console.error("Add Bonus form elements missing.");
         return;
    }

    // Basic Client-side validation
    const employeeId = form.elements['employee_id'].value;
    const bonusAmount = form.elements['bonus_amount'].value;
    const awardDate = form.elements['award_date'].value;
    const paymentDate = form.elements['payment_date'].value;


    if (!employeeId || !bonusAmount || !awardDate) {
        statusSpan.textContent = 'Employee, Bonus Amount, and Award Date are required.';
        statusSpan.className = 'ml-4 text-sm text-red-600';
        return;
    }
     if (paymentDate && paymentDate < awardDate) {
        statusSpan.textContent = 'Payment Date cannot be before Award Date.';
        statusSpan.className = 'ml-4 text-sm text-red-600';
        return;
     }


    const formData = {
        employee_id: employeeId,
        bonus_amount: parseFloat(bonusAmount), // Ensure it's a number
        bonus_type: form.elements['bonus_type'].value.trim() || null,
        award_date: awardDate,
        payment_date: paymentDate || null, // Send null if empty
        // payroll_id: form.elements['payroll_id']?.value || null // Include if payroll select exists
    };

    statusSpan.textContent = 'Adding bonus...';
    statusSpan.className = 'ml-4 text-sm text-blue-600';
    submitButton.disabled = true;

    try {
        const response = await fetch(`${API_BASE_URL}add_bonus.php`, {
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

        statusSpan.textContent = result.message || 'Bonus added successfully!';
        statusSpan.className = 'ml-4 text-sm text-green-600';
        form.reset(); // Clear the form
        await loadBonuses(); // Refresh the bonuses list

        // Optionally clear message after a few seconds
        setTimeout(() => { if(statusSpan.textContent === (result.message || 'Bonus added successfully!')) statusSpan.textContent = ''; }, 5000);


    } catch (error) {
        console.error('Error adding bonus:', error);
        statusSpan.textContent = `Error: ${error.message}`;
        statusSpan.className = 'ml-4 text-sm text-red-600';
    } finally {
        submitButton.disabled = false; // Re-enable button
    }
}
