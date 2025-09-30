/**
 * Compensation Module
 * Handles display and management of Compensation Plans, Salary Adjustments, and Incentives.
 * v2.3 - Corrected payload keys for add_salary_adjustment.php
 * v2.2 - Integrated SweetAlert for notifications.
 * v2.1 - Reviewed for completeness with updated backend scripts.
 */
import { API_BASE_URL, populateEmployeeDropdown } from '../utils.js';

// --- DOM Element References ---
let pageTitleElement;
let mainContentArea;

/**
 * Initializes common elements used by the compensation module.
 */
function initializeCompensationElements() {
    pageTitleElement = document.getElementById('page-title');
    mainContentArea = document.getElementById('main-content-area');
    if (!pageTitleElement || !mainContentArea) {
        console.error("Compensation Module: Core DOM elements (page-title or main-content-area) not found!");
        return false;
    }
    return true;
}

// =================================
// === Compensation Plans Section ===
// =================================

/**
 * Displays the Compensation Plans section.
 */
export async function displayCompensationPlansSection() {
    console.log("[Display] Displaying Compensation Plans Section...");
    if (!initializeCompensationElements()) return;

    pageTitleElement.textContent = 'Manage Compensation Plans';
    mainContentArea.innerHTML = `
        <div class="bg-white p-6 rounded-lg shadow-md border border-[#F7E6CA] space-y-6">
            <div class="border-b border-gray-200 pb-4">
                 <h3 class="text-lg font-semibold text-[#4E3B2A] mb-3 font-header">Add New Compensation Plan</h3>
                 <form id="add-comp-plan-form" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div>
                            <label for="comp-plan-name" class="block text-sm font-medium text-gray-700 mb-1">Plan Name:</label>
                            <input type="text" id="comp-plan-name" name="plan_name" required placeholder="e.g., 2025 Salary Structure" class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                        </div>
                         <div>
                            <label for="comp-plan-type" class="block text-sm font-medium text-gray-700 mb-1">Plan Type:</label>
                            <input type="text" id="comp-plan-type" name="plan_type" placeholder="e.g., Salary, Bonus, Commission" class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                        </div>
                        <div>
                            <label for="comp-plan-effective-date" class="block text-sm font-medium text-gray-700 mb-1">Effective Date:</label>
                            <input type="date" id="comp-plan-effective-date" name="effective_date" required class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                        </div>
                        <div>
                            <label for="comp-plan-end-date" class="block text-sm font-medium text-gray-700 mb-1">End Date (Optional):</label>
                            <input type="date" id="comp-plan-end-date" name="end_date" class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                        </div>
                        <div class="md:col-span-2 lg:col-span-3">
                            <label for="comp-plan-description" class="block text-sm font-medium text-gray-700 mb-1">Description:</label>
                            <textarea id="comp-plan-description" name="description" rows="2" placeholder="Details about this compensation plan..." class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]"></textarea>
                        </div>
                    </div>
                     <div class="pt-2 space-x-3">
                        <button type="submit" class="px-4 py-2 bg-[#594423] text-white rounded-md hover:bg-[#4E3B2A] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#594423] transition duration-150 ease-in-out">
                            Save Compensation Plan
                        </button>
                        </div>
                 </form>
            </div>

            <div>
                <h3 class="text-lg font-semibold text-[#4E3B2A] mb-3 font-header">Existing Compensation Plans</h3>
                <div id="comp-plans-list-container" class="overflow-x-auto">
                     <p class="text-center py-4">Loading compensation plans...</p>
                </div>
            </div>
        </div>`;

    requestAnimationFrame(async () => {
        const compPlanForm = document.getElementById('add-comp-plan-form');
        if (compPlanForm) {
            if (!compPlanForm.hasAttribute('data-listener-attached')) {
                compPlanForm.addEventListener('submit', handleAddCompensationPlan);
                compPlanForm.setAttribute('data-listener-attached', 'true');
            }
        } else { console.error("Add Compensation Plan form not found."); }
        await loadCompensationPlans();
    });
}

async function loadCompensationPlans() {
    console.log("[Load] Loading Compensation Plans...");
    const container = document.getElementById('comp-plans-list-container');
    if (!container) return;
    container.innerHTML = '<p class="text-center py-4">Loading compensation plans...</p>';
    const url = `${API_BASE_URL}get_compensation_plans.php`;
    try {
        const response = await fetch(url);
        const plans = await handleApiResponse(response);
        renderCompensationPlansTable(plans);
    } catch (error) {
        console.error('Error loading compensation plans:', error);
        container.innerHTML = `<p class="text-red-500 text-center py-4">Could not load compensation plans. ${error.message}</p>`;
    }
}

function renderCompensationPlansTable(plans) {
    console.log("[Render] Rendering Compensation Plans Table...");
    const container = document.getElementById('comp-plans-list-container');
    if (!container) return;
    container.innerHTML = '';
    if (!plans || plans.length === 0) {
        container.innerHTML = '<p class="text-center py-4 text-gray-500">No compensation plans found.</p>';
        return;
    }
    const table = document.createElement('table');
    table.className = 'min-w-full divide-y divide-gray-200 border border-gray-300';
    const thead = table.createTHead();
    thead.className = 'bg-gray-50';
    const headerRow = thead.insertRow();
    const headers = ['ID', 'Plan Name', 'Type', 'Description', 'Effective Date', 'End Date'];
    headers.forEach(text => {
        const th = document.createElement('th');
        th.scope = 'col';
        th.className = 'px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider';
        th.textContent = text;
        headerRow.appendChild(th);
    });
    const tbody = table.createTBody();
    tbody.className = 'bg-white divide-y divide-gray-200';
    plans.forEach(plan => {
        const row = tbody.insertRow();
        row.id = `comp-plan-row-${plan.PlanID}`;
        const createCell = (text) => {
            const cell = row.insertCell();
            cell.className = 'px-4 py-3 whitespace-nowrap text-sm';
            cell.textContent = text ?? '-';
            return cell;
        };
        createCell(plan.PlanID).classList.add('text-gray-500');
        createCell(plan.PlanName).classList.add('font-medium', 'text-gray-900');
        createCell(plan.PlanType).classList.add('text-gray-700');
        const descCell = createCell(plan.Description);
        descCell.classList.remove('whitespace-nowrap');
        descCell.classList.add('whitespace-normal', 'break-words', 'text-gray-700');
        createCell(plan.EffectiveDateFormatted ?? plan.EffectiveDate).classList.add('text-gray-700');
        createCell(plan.EndDateFormatted ?? plan.EndDate).classList.add('text-gray-700');
    });
    container.appendChild(table);
}

async function handleAddCompensationPlan(event) {
    event.preventDefault();
    const form = event.target;
    const submitButton = form.querySelector('button[type="submit"]');
    if (!submitButton) return;

    const planName = form.elements['plan_name'].value.trim();
    const effectiveDate = form.elements['effective_date'].value;
    const endDate = form.elements['end_date'].value;

    if (!planName || !effectiveDate) {
        Swal.fire({
            icon: 'error',
            title: 'Validation Error',
            text: 'Plan Name and Effective Date are required.',
            confirmButtonColor: '#4E3B2A'
        });
        return;
    }
    if (endDate && endDate < effectiveDate) {
        Swal.fire({
            icon: 'error',
            title: 'Validation Error',
            text: 'End Date cannot be before Effective Date.',
            confirmButtonColor: '#4E3B2A'
        });
        return;
    }

    const formData = {
        plan_name: planName,
        description: form.elements['description'].value.trim() || null,
        effective_date: effectiveDate,
        end_date: endDate || null,
        plan_type: form.elements['plan_type'].value.trim() || null
    };

    Swal.fire({
        title: 'Processing...',
        text: 'Adding compensation plan, please wait.',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
    submitButton.disabled = true;

    try {
        const response = await fetch(`${API_BASE_URL}add_compensation_plan.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        });
        const result = await handleApiResponse(response);

        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: result.message || 'Compensation plan added successfully!',
            confirmButtonColor: '#4E3B2A',
            timer: 2000
        });
        form.reset();
        await loadCompensationPlans();
    } catch (error) {
        console.error('Error adding compensation plan:', error);
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

// =================================
// === Salary Adjustments Section ===
// =================================

export async function displaySalaryAdjustmentsSection() {
    console.log("[Display] Displaying Salary Adjustments Section...");
    if (!initializeCompensationElements()) return;

    pageTitleElement.textContent = 'Salary Adjustments';
    mainContentArea.innerHTML = `
        <div class="bg-white p-6 rounded-lg shadow-md border border-[#F7E6CA] space-y-6">
            <div class="border-b border-gray-200 pb-4">
                <h3 class="text-lg font-semibold text-[#4E3B2A] mb-3 font-header">Record New Salary Adjustment</h3>
                <form id="add-salary-adjustment-form" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div>
                            <label for="adj-employee-select" class="block text-sm font-medium text-gray-700 mb-1">Employee:</label>
                            <select id="adj-employee-select" name="employee_id" required class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                                <option value="">Loading employees...</option>
                            </select>
                        </div>
                        <div>
                            <label for="adj-base-salary" class="block text-sm font-medium text-gray-700 mb-1">New Base Salary:</label>
                            <input type="number" id="adj-base-salary" name="base_salary" required step="0.01" min="0" placeholder="e.g., 55000.00" class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                        </div>
                        <div>
                            <label for="adj-pay-frequency" class="block text-sm font-medium text-gray-700 mb-1">New Pay Frequency:</label>
                            <select id="adj-pay-frequency" name="pay_frequency" required class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                                <option value="">-- Select --</option>
                                <option value="Monthly">Monthly</option>
                                <option value="Bi-Weekly">Bi-Weekly</option>
                                <option value="Weekly">Weekly</option>
                                <option value="Hourly">Hourly</option>
                            </select>
                        </div>
                        <div>
                            <label for="adj-pay-rate" class="block text-sm font-medium text-gray-700 mb-1">New Pay Rate (if Hourly):</label>
                            <input type="number" id="adj-pay-rate" name="pay_rate" step="0.01" min="0" placeholder="e.g., 275.00" class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                        </div>
                        <div>
                            <label for="adj-effective-date" class="block text-sm font-medium text-gray-700 mb-1">Effective Date:</label>
                            <input type="date" id="adj-effective-date" name="effective_date" required class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                        </div>
                        <div>
                            <label for="adj-reason" class="block text-sm font-medium text-gray-700 mb-1">Reason for Adjustment:</label>
                            <input type="text" id="adj-reason" name="reason" required placeholder="e.g., Annual Increment, Promotion" class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                        </div>
                        <div class="md:col-span-2 lg:col-span-3">
                            <label for="adj-notes" class="block text-sm font-medium text-gray-700 mb-1">Notes (Optional):</label>
                            <textarea id="adj-notes" name="notes" rows="2" placeholder="Additional details..." class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]"></textarea>
                        </div>
                    </div>
                    <div class="pt-2">
                        <button type="submit" class="px-4 py-2 bg-[#594423] text-white rounded-md hover:bg-[#4E3B2A] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#594423] transition duration-150 ease-in-out">
                            Save Salary Adjustment
                        </button>
                        </div>
                </form>
            </div>

            <div>
                <h3 class="text-lg font-semibold text-[#4E3B2A] mb-3 font-header">Adjustment History</h3>
                <div id="salary-adjustments-list-container" class="overflow-x-auto">
                     <p class="text-center py-4">Loading salary adjustments...</p>
                </div>
            </div>
        </div>`;

    requestAnimationFrame(async () => {
        await populateEmployeeDropdown('adj-employee-select');
        const adjForm = document.getElementById('add-salary-adjustment-form');
        if (adjForm) {
            if (!adjForm.hasAttribute('data-listener-attached')) {
                adjForm.addEventListener('submit', handleAddSalaryAdjustment);
                adjForm.setAttribute('data-listener-attached', 'true');
            }
        } else { console.error("Add Salary Adjustment form not found."); }
        await loadSalaryAdjustments();
    });
}

async function loadSalaryAdjustments() {
    console.log("[Load] Loading Salary Adjustments...");
    const container = document.getElementById('salary-adjustments-list-container');
    if (!container) return;
    container.innerHTML = '<p class="text-center py-4">Loading salary adjustments...</p>';
    const url = `${API_BASE_URL}get_salary_adjustments.php`;
    try {
        const response = await fetch(url);
        const adjustments = await handleApiResponse(response);
        renderSalaryAdjustmentsTable(adjustments);
    } catch (error) {
        console.error('Error loading salary adjustments:', error);
        container.innerHTML = `<p class="text-red-500 text-center py-4">Could not load salary adjustments. ${error.message}</p>`;
    }
}

function renderSalaryAdjustmentsTable(adjustments) {
    console.log("[Render] Rendering Salary Adjustments Table...");
    const container = document.getElementById('salary-adjustments-list-container');
    if (!container) return;
    container.innerHTML = '';
    if (!adjustments || adjustments.length === 0) {
        container.innerHTML = '<p class="text-center py-4 text-gray-500">No salary adjustment records found.</p>';
        return;
    }
    const table = document.createElement('table');
    table.className = 'min-w-full divide-y divide-gray-200 border border-gray-300';
    const thead = table.createTHead();
    thead.className = 'bg-gray-50';
    const headerRow = thead.insertRow();
    const headers = ['Adj. ID', 'Employee', 'Effective Date', 'Reason', 'Prev. Salary', 'New Salary', 'Prev. Freq.', 'New Freq.', 'Approved By'];
    headers.forEach(text => {
        const th = document.createElement('th');
        th.scope = 'col';
        th.className = 'px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider';
        th.textContent = text;
        headerRow.appendChild(th);
    });
    const tbody = table.createTBody();
    tbody.className = 'bg-white divide-y divide-gray-200';
    adjustments.forEach(adj => {
        const row = tbody.insertRow();
        row.id = `adj-row-${adj.AdjustmentID}`;
        const createCell = (text, isNumeric = false) => {
            const cell = row.insertCell();
            cell.className = 'px-4 py-3 whitespace-nowrap text-sm';
            if (isNumeric) cell.classList.add('text-right');
            cell.textContent = text ?? '-';
            return cell;
        };
        createCell(adj.AdjustmentID).classList.add('text-gray-500');
        createCell(adj.EmployeeName).classList.add('font-medium', 'text-gray-900');
        createCell(adj.AdjustmentDateFormatted ?? adj.EffectiveDate).classList.add('text-gray-700'); // Use EffectiveDate from DB if AdjustmentDateFormatted is not there
        const reasonCell = createCell(adj.Reason);
        reasonCell.classList.remove('whitespace-nowrap');
        reasonCell.classList.add('whitespace-normal', 'break-words', 'text-gray-700');
        createCell(adj.PreviousBaseSalary, true).classList.add('text-gray-600');
        createCell(adj.NewBaseSalary, true).classList.add('text-green-600', 'font-semibold');
        createCell(adj.PreviousPayFrequency).classList.add('text-gray-600');
        createCell(adj.NewPayFrequency).classList.add('text-green-600');
        createCell(adj.ApproverName).classList.add('text-gray-700');
    });
    container.appendChild(table);
}

async function handleAddSalaryAdjustment(event) {
    event.preventDefault();
    const form = event.target;
    const submitButton = form.querySelector('button[type="submit"]');
    if (!submitButton) return;

    // Corrected keys for the payload to match add_salary_adjustment.php
    const formData = {
        employee_id: form.elements['employee_id'].value,
        new_base_salary: parseFloat(form.elements['base_salary'].value), // Corrected key
        new_pay_frequency: form.elements['pay_frequency'].value,    // Corrected key
        new_pay_rate: form.elements['pay_rate'].value ? parseFloat(form.elements['pay_rate'].value) : null, // Corrected key (if your PHP expects it as new_pay_rate)
        effective_date: form.elements['effective_date'].value,
        reason: form.elements['reason'].value.trim(),
        notes: form.elements['notes'].value.trim() || null, // Added notes
        // percentage_increase is not directly used by add_salary_adjustment.php,
        // but can be calculated or stored in notes if needed.
    };


    if (!formData.employee_id || isNaN(formData.new_base_salary) || !formData.new_pay_frequency || !formData.effective_date || !formData.reason) {
        Swal.fire({ icon: 'error', title: 'Validation Error', text: 'Employee, New Base Salary, New Pay Frequency, Effective Date, and Reason are required.', confirmButtonColor: '#4E3B2A' });
        return;
    }
    if (formData.new_base_salary < 0) {
        Swal.fire({ icon: 'error', title: 'Validation Error', text: 'New Base Salary cannot be negative.', confirmButtonColor: '#4E3B2A' });
        return;
    }
    if (formData.new_pay_rate !== null && formData.new_pay_rate < 0) {
        Swal.fire({ icon: 'error', title: 'Validation Error', text: 'New Pay Rate cannot be negative.', confirmButtonColor: '#4E3B2A' });
        return;
    }

    Swal.fire({
        title: 'Processing...',
        text: 'Saving salary adjustment, please wait.',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
    submitButton.disabled = true;

    try {
        const response = await fetch(`${API_BASE_URL}add_salary_adjustment.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        });
        const result = await handleApiResponse(response);

        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: result.message || 'Salary adjustment recorded successfully!',
            confirmButtonColor: '#4E3B2A',
            timer: 2000
        });
        form.reset();
        await loadSalaryAdjustments();
    } catch (error) {
        console.error('Error adding salary adjustment:', error);
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


// ===========================
// === Incentives Section ===
// ===========================

export async function displayIncentivesSection() {
    console.log("[Display] Displaying Incentives Section...");
    if (!initializeCompensationElements()) return;

    pageTitleElement.textContent = 'Manage Incentives';
    mainContentArea.innerHTML = `
        <div class="bg-white p-6 rounded-lg shadow-md border border-[#F7E6CA] space-y-6">
            <div class="border-b border-gray-200 pb-4">
                 <h3 class="text-lg font-semibold text-[#4E3B2A] mb-3 font-header">Add New Incentive</h3>
                 <form id="add-incentive-form" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div>
                            <label for="incentive-employee-select" class="block text-sm font-medium text-gray-700 mb-1">Employee:</label>
                            <select id="incentive-employee-select" name="employee_id" required class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                                <option value="">Loading employees...</option>
                            </select>
                        </div>
                        <div>
                            <label for="incentive-amount" class="block text-sm font-medium text-gray-700 mb-1">Amount:</label>
                            <input type="number" id="incentive-amount" name="amount" required step="0.01" min="0.01" placeholder="e.g., 1000.00" class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                        </div>
                        <div>
                            <label for="incentive-type" class="block text-sm font-medium text-gray-700 mb-1">Incentive Type:</label>
                            <input type="text" id="incentive-type" name="incentive_type" placeholder="e.g., Sales Bonus, Spot Award" class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                        </div>
                        <div>
                            <label for="incentive-award-date" class="block text-sm font-medium text-gray-700 mb-1">Award Date:</label>
                            <input type="date" id="incentive-award-date" name="award_date" required class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                        </div>
                        <div>
                            <label for="incentive-payout-date" class="block text-sm font-medium text-gray-700 mb-1">Payout Date (Optional):</label>
                            <input type="date" id="incentive-payout-date" name="payout_date" class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                        </div>
                         <div>
                            <label for="incentive-plan-id" class="block text-sm font-medium text-gray-700 mb-1">Comp Plan ID (Optional):</label>
                            <input type="number" id="incentive-plan-id" name="plan_id" placeholder="Link to Plan ID" class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                        </div>
                         <div>
                            <label for="incentive-payroll-id" class="block text-sm font-medium text-gray-700 mb-1">Payroll Run ID (Optional):</label>
                            <input type="number" id="incentive-payroll-id" name="payroll_id" placeholder="Link to Payroll ID" class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                        </div>
                    </div>
                     <div class="pt-2 space-x-3">
                        <button type="submit" class="px-4 py-2 bg-[#594423] text-white rounded-md hover:bg-[#4E3B2A] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#594423] transition duration-150 ease-in-out">
                            Add Incentive
                        </button>
                        </div>
                 </form>
            </div>

            <div>
                <h3 class="text-lg font-semibold text-[#4E3B2A] mb-3 font-header">Incentive Records</h3>
                 <div class="flex flex-wrap gap-4 mb-4 items-end">
                      <div>
                        <label for="filter-incentive-employee" class="block text-sm font-medium text-gray-700 mb-1">Filter by Employee:</label>
                        <select id="filter-incentive-employee" class="w-full sm:w-auto p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                            <option value="">All Employees</option>
                            </select>
                     </div>
                     <div>
                        <button id="filter-incentive-btn" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-150 ease-in-out">
                            Filter
                        </button>
                     </div>
                 </div>
                <div id="incentives-list-container" class="overflow-x-auto">
                     <p class="text-center py-4">Loading incentives...</p>
                </div>
            </div>
        </div>`;

    requestAnimationFrame(async () => {
        await populateEmployeeDropdown('incentive-employee-select');
        await populateEmployeeDropdown('filter-incentive-employee', true);
        const incentiveForm = document.getElementById('add-incentive-form');
        if (incentiveForm) {
            if (!incentiveForm.hasAttribute('data-listener-attached')) {
                incentiveForm.addEventListener('submit', handleAddIncentive);
                incentiveForm.setAttribute('data-listener-attached', 'true');
            }
        } else { console.error("Add Incentive form not found."); }
        const filterBtn = document.getElementById('filter-incentive-btn');
        if (filterBtn) {
             if (!filterBtn.hasAttribute('data-listener-attached')) {
                filterBtn.addEventListener('click', applyIncentiveFilter);
                filterBtn.setAttribute('data-listener-attached', 'true');
             }
        } else { console.error("Filter Incentive button not found."); }
        await loadIncentives();
    });
}

function applyIncentiveFilter() {
    const employeeId = document.getElementById('filter-incentive-employee')?.value;
    loadIncentives(employeeId);
}

async function loadIncentives(employeeId = null) {
    console.log(`[Load] Loading Incentives... (Employee: ${employeeId || 'All'})`);
    const container = document.getElementById('incentives-list-container');
    if (!container) return;
    container.innerHTML = '<p class="text-center py-4">Loading incentives...</p>';
    const params = new URLSearchParams();
    if (employeeId) params.append('employee_id', employeeId);
    const url = `${API_BASE_URL}get_incentives.php?${params.toString()}`;
    try {
        const response = await fetch(url);
        const incentives = await handleApiResponse(response);
        renderIncentivesTable(incentives);
    } catch (error) {
        console.error('Error loading incentives:', error);
        container.innerHTML = `<p class="text-red-500 text-center py-4">Could not load incentives. ${error.message}</p>`;
    }
}

function renderIncentivesTable(incentives) {
    console.log("[Render] Rendering Incentives Table...");
    const container = document.getElementById('incentives-list-container');
    if (!container) return;
    container.innerHTML = '';
    if (!incentives || incentives.length === 0) {
        container.innerHTML = '<p class="text-center py-4 text-gray-500">No incentive records found.</p>';
        return;
    }
    const table = document.createElement('table');
    table.className = 'min-w-full divide-y divide-gray-200 border border-gray-300';
    const thead = table.createTHead();
    thead.className = 'bg-gray-50';
    const headerRow = thead.insertRow();
    const headers = ['Inc. ID', 'Employee', 'Type', 'Comp. Plan', 'Amount', 'Award Date', 'Payout Date', 'Payroll ID'];
    headers.forEach(text => {
        const th = document.createElement('th');
        th.scope = 'col';
        th.className = 'px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider';
        th.textContent = text;
        headerRow.appendChild(th);
    });
    const tbody = table.createTBody();
    tbody.className = 'bg-white divide-y divide-gray-200';
    incentives.forEach(inc => {
        const row = tbody.insertRow();
        row.id = `incentive-row-${inc.IncentiveID}`;
        const createCell = (text, isNumeric = false) => {
            const cell = row.insertCell();
            cell.className = 'px-4 py-3 whitespace-nowrap text-sm';
            if (isNumeric) cell.classList.add('text-right');
            cell.textContent = text ?? '-';
            return cell;
        };
        createCell(inc.IncentiveID).classList.add('text-gray-500');
        createCell(inc.EmployeeName).classList.add('font-medium', 'text-gray-900');
        createCell(inc.IncentiveType).classList.add('text-gray-700');
        createCell(inc.CompensationPlanName).classList.add('text-gray-700');
        createCell(inc.AmountFormatted ?? inc.Amount, true).classList.add('text-gray-700');
        createCell(inc.AwardDateFormatted ?? inc.AwardDate).classList.add('text-gray-700');
        createCell(inc.PayoutDateFormatted ?? inc.PayoutDate).classList.add('text-gray-500');
        createCell(inc.PayrollID).classList.add('text-gray-500');
    });
    container.appendChild(table);
}

async function handleAddIncentive(event) {
    event.preventDefault();
    const form = event.target;
    const submitButton = form.querySelector('button[type="submit"]');
    if (!submitButton) return;

    const employeeId = form.elements['employee_id'].value;
    const amount = form.elements['amount'].value;
    const awardDate = form.elements['award_date'].value;
    const payoutDate = form.elements['payout_date'].value;

    if (!employeeId || !amount || !awardDate) {
        Swal.fire({ icon: 'error', title: 'Validation Error', text: 'Employee, Amount, and Award Date are required.', confirmButtonColor: '#4E3B2A' });
        return;
    }
     if (parseFloat(amount) <= 0) {
        Swal.fire({ icon: 'error', title: 'Validation Error', text: 'Amount must be positive.', confirmButtonColor: '#4E3B2A' });
        return;
    }
    if (payoutDate && payoutDate < awardDate) {
        Swal.fire({ icon: 'error', title: 'Validation Error', text: 'Payout Date cannot be before Award Date.', confirmButtonColor: '#4E3B2A' });
        return;
    }

    const formData = {
        employee_id: parseInt(employeeId),
        amount: parseFloat(amount),
        incentive_type: form.elements['incentive_type'].value.trim() || null,
        award_date: awardDate,
        payout_date: payoutDate || null,
        plan_id: form.elements['plan_id'].value ? parseInt(form.elements['plan_id'].value) : null,
        payroll_id: form.elements['payroll_id'].value ? parseInt(form.elements['payroll_id'].value) : null
    };

    Swal.fire({
        title: 'Processing...',
        text: 'Adding incentive, please wait.',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
    submitButton.disabled = true;

    try {
        const response = await fetch(`${API_BASE_URL}add_incentive.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        });
        const result = await handleApiResponse(response);

        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: result.message || 'Incentive added successfully!',
            confirmButtonColor: '#4E3B2A',
            timer: 2000
        });
        form.reset();
        await loadIncentives();
    } catch (error) {
        console.error('Error adding incentive:', error);
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
        if (response.status === 204) { // No Content
             return { message: "Operation completed successfully (No Content)." };
        }
        const text = await response.text();
        if (!text || !text.trim()) { // If body is empty or whitespace only
             return []; // For list endpoints, an empty array is a valid response
        }
        try {
            data = JSON.parse(text);
            return data;
        } catch (jsonError) {
            console.error("Failed to parse successful response as JSON:", jsonError);
            console.error("Response text was:", text.substring(0, 500)); // Log more of the problematic text
            throw new Error("Received successful status, but failed to parse response as JSON.");
        }
    } catch (e) {
        console.error("Error processing successful response body:", e);
        throw new Error("Error processing response from server.");
    }
}
