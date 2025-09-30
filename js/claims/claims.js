/**
 * Claims Module
 * v2.4 - Integrated SweetAlert for notifications and confirmations.
 * v2.3 - Corrected API endpoint filename for updateClaimStatus.
 * - Added fetch response debugging.
 * - Added Employee role handling for UI elements.
 * - Refined rendering functions for XSS protection.
 */
import { API_BASE_URL, populateEmployeeDropdown } from '../utils.js'; // Import shared functions/constants

// --- DOM Element References (Assumed to be obtained by the calling function/main script) ---
let pageTitleElement;
let mainContentArea;

/**
 * Initializes common elements used by the claims module.
 */
function initializeClaimElements() {
    pageTitleElement = document.getElementById('page-title');
    mainContentArea = document.getElementById('main-content-area');
    if (!pageTitleElement || !mainContentArea) {
        console.error("Claims Module: Core DOM elements (page-title or main-content-area) not found!");
        return false;
    }
    return true;
}

// ============================
// === Submit Claim Section ===
// ============================
export async function displaySubmitClaimSection() {
    console.log("[Display] Displaying Submit Claim Section...");
    if (!initializeClaimElements()) return;

    const user = window.currentUser;
    const isEmployeeRole = user?.role_name === 'Employee';
    const employeeId = user?.employee_id;

    pageTitleElement.textContent = 'Submit New Claim';
    mainContentArea.innerHTML = `
        <div class="bg-white p-6 rounded-lg shadow-md border border-[#F7E6CA] space-y-6">
            <h3 class="text-lg font-semibold text-[#4E3B2A] mb-3 font-header">Claim Details</h3>
            <form id="submit-claim-form" class="space-y-4" enctype="multipart/form-data">
                ${isEmployeeRole && employeeId ? `
                    <input type="hidden" name="employee_id" value="${employeeId}">
                    <p class="text-sm mb-2">Submitting claim for: <strong>${user.full_name || 'Yourself'}</strong></p>
                ` : `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="claim-employee-select" class="block text-sm font-medium text-gray-700 mb-1">Employee (Claimant):</label>
                        <select id="claim-employee-select" name="employee_id" required class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                            <option value="">Loading employees...</option>
                        </select>
                    </div>
                 </div>
                `}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                     <div>
                        <label for="claim-type-select" class="block text-sm font-medium text-gray-700 mb-1">Claim Type:</label>
                        <select id="claim-type-select" name="claim_type_id" required class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                            <option value="">Loading claim types...</option>
                        </select>
                    </div>
                    <div>
                        <label for="claim-date" class="block text-sm font-medium text-gray-700 mb-1">Date Expense Incurred:</label>
                        <input type="date" id="claim-date" name="claim_date" required class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                    </div>
                    <div>
                        <label for="claim-amount" class="block text-sm font-medium text-gray-700 mb-1">Amount (PHP):</label>
                        <input type="number" id="claim-amount" name="amount" required step="0.01" min="0.01" placeholder="e.g., 1500.50" class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                    </div>
                    <div class="md:col-span-2">
                        <label for="claim-description" class="block text-sm font-medium text-gray-700 mb-1">Description:</label>
                        <textarea id="claim-description" name="description" rows="3" placeholder="Describe the expense..." class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]"></textarea>
                    </div>
                    <div>
                        <label for="claim-receipt" class="block text-sm font-medium text-gray-700 mb-1">Upload Receipt (Optional):</label>
                        <input type="file" id="claim-receipt" name="receipt_file" class="w-full p-1.5 border border-gray-300 rounded-md shadow-sm text-sm text-gray-700 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-[#F7E6CA] file:text-[#4E3B2A] hover:file:bg-[#EADDCB]">
                        <p class="mt-1 text-xs text-gray-500">Allowed: JPG, PNG, PDF (Max 2MB)</p>
                    </div>
                </div>
                <div class="pt-2">
                    <button type="submit" class="px-4 py-2 bg-[#594423] text-white rounded-md hover:bg-[#4E3B2A] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#594423] transition duration-150 ease-in-out">
                        Submit Claim
                    </button>
                    </div>
            </form>
        </div>`;

    requestAnimationFrame(async () => {
        if (!isEmployeeRole) {
            await populateEmployeeDropdown('claim-employee-select');
        }
        await populateClaimTypeDropdown('claim-type-select');
        const submitForm = document.getElementById('submit-claim-form');
        if (submitForm) {
            if (!submitForm.hasAttribute('data-listener-attached')) {
                submitForm.addEventListener('submit', handleSubmitClaim);
                submitForm.setAttribute('data-listener-attached', 'true');
            }
        } else { console.error("Submit Claim form not found."); }
    });
}
async function populateClaimTypeDropdown(selectElementId) {
     const selectElement = document.getElementById(selectElementId);
    if (!selectElement) {
        console.error(`[populateClaimTypeDropdown] Element ID '${selectElementId}' not found.`);
        return;
    }
    selectElement.innerHTML = '<option value="" disabled selected>Loading claim types...</option>';

    try {
        const response = await fetch(`${API_BASE_URL}get_claim_types.php`);
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        const claimTypes = await response.json();

        if (claimTypes.error) throw new Error(claimTypes.error);

        selectElement.innerHTML = '<option value="">-- Select Claim Type --</option>';
        if (claimTypes.length > 0) {
            claimTypes.forEach(type => {
                const option = document.createElement('option');
                option.value = type.ClaimTypeID;
                option.textContent = type.TypeName;
                selectElement.appendChild(option);
            });
        } else {
            selectElement.innerHTML = '<option value="" disabled>No claim types found</option>';
        }
    } catch (error) {
        console.error('Error populating claim type dropdown:', error);
        selectElement.innerHTML = `<option value="" disabled>Error loading types</option>`;
    }
}
async function handleSubmitClaim(event) {
     event.preventDefault();
    const form = event.target;
    const submitButton = form.querySelector('button[type="submit"]');
    if (!submitButton) return;

    const formData = new FormData(form);

    Swal.fire({
        title: 'Processing...',
        text: 'Submitting your claim, please wait.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    submitButton.disabled = true;

    try {
        const response = await fetch(`${API_BASE_URL}submit_claim.php`, {
            method: 'POST',
            body: formData
        });

        const contentType = response.headers.get("content-type");
        let result;

        if (!response.ok) {
            let errorPayload = { error: `HTTP error! Status: ${response.status}` };
            if (contentType && contentType.includes("application/json")) {
                try {
                    result = await response.json();
                    errorPayload.error = result.error || errorPayload.error;
                    errorPayload.details = result.details;
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
            result = await response.json();
        } catch (jsonError) {
            console.error("Failed to parse successful JSON response:", jsonError);
            throw new Error("Received successful status, but failed to parse JSON response.");
        }

        Swal.fire({
            icon: 'success',
            title: 'Claim Submitted!',
            text: result.message || 'Your claim has been submitted successfully!',
            confirmButtonColor: '#4E3B2A',
            timer: 2500
        });
        form.reset();

        // If the current user is an employee, refresh their "My Claims" view.
        // This assumes that if they submit a claim, they are likely on the submit claim page,
        // and then might want to see their claims list.
        if (window.currentUser?.role_name === 'Employee') {
            // Check if the 'My Claims' section is currently active or if a function to load it exists
            // This is a simplified check; a more robust solution might involve a global navigation state.
            if (typeof displayMyClaimsSection === 'function' && mainContentArea.querySelector('#my-claims-list-container')) {
                 await loadMyClaims();
            }
        }


    } catch (error) {
        console.error('Error submitting claim:', error);
        let displayMessage = `Error: ${error.message}`;
        if (error.details) {
             displayMessage += ` Details: ${JSON.stringify(error.details)}`;
        }
        Swal.fire({
            icon: 'error',
            title: 'Submission Error',
            text: displayMessage,
            confirmButtonColor: '#4E3B2A'
        });
    } finally {
        submitButton.disabled = false;
        if (Swal.isLoading()) { // Ensure loading Swal is closed if it wasn't replaced
            Swal.close();
        }
    }
}

// =========================
// === My Claims Section ===
// =========================
export async function displayMyClaimsSection() {
     console.log("[Display] Displaying My Claims Section...");
    if (!initializeClaimElements()) return;

    const user = window.currentUser;
    const isEmployeeRole = user?.role_name === 'Employee';

    pageTitleElement.textContent = 'My Submitted Claims';
    mainContentArea.innerHTML = `
        <div class="bg-white p-6 rounded-lg shadow-md border border-[#F7E6CA] space-y-6">
            <h3 class="text-lg font-semibold text-[#4E3B2A] mb-3 font-header">Claim History</h3>
            ${isEmployeeRole ? '' : `
             <div class="flex flex-wrap gap-4 mb-4 items-end">
                 <div>
                    <label for="filter-myclaim-status" class="block text-sm font-medium text-gray-700 mb-1">Filter by Status:</label>
                    <select id="filter-myclaim-status" class="w-full sm:w-auto p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                        <option value="">All Statuses</option>
                        <option value="Submitted">Submitted</option>
                        <option value="Approved">Approved</option>
                        <option value="Rejected">Rejected</option>
                        <option value="Paid">Paid</option>
                        <option value="Queried">Queried</option>
                    </select>
                </div>
                 <div>
                    <button id="filter-myclaim-btn" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-150 ease-in-out">
                        Filter
                    </button>
                 </div>
            </div>
            `}
            <div id="my-claims-list-container" class="overflow-x-auto">
                <p>Loading claims...</p>
            </div>
        </div>`;

    requestAnimationFrame(async () => {
        if (!isEmployeeRole) {
            const filterBtn = document.getElementById('filter-myclaim-btn');
            if (filterBtn) {
                 if (!filterBtn.hasAttribute('data-listener-attached')) {
                    filterBtn.addEventListener('click', applyMyClaimsFilter);
                    filterBtn.setAttribute('data-listener-attached', 'true');
                 }
            } else { console.error("Filter My Claims button not found."); }
        }
        await loadMyClaims();
    });
}
function applyMyClaimsFilter() {
    const status = document.getElementById('filter-myclaim-status')?.value;
    loadMyClaims(status);
}
async function loadMyClaims(status = null) {
     console.log("[Load] Loading My Claims...");
    const container = document.getElementById('my-claims-list-container');
    if (!container) return;
    container.innerHTML = '<p class="text-center py-4">Loading claims...</p>';

    const user = window.currentUser;
    if (!user || !user.employee_id) {
        console.error("Cannot load 'My Claims': User not logged in or employee ID missing.");
        container.innerHTML = '<p class="text-center py-4 text-red-500">Could not load claims. User information missing.</p>';
        return;
    }
    const employeeId = user.employee_id;

    const params = new URLSearchParams();
    params.append('employee_id', employeeId);
     if (status) {
        params.append('status', status);
    }

    const url = `${API_BASE_URL}get_claims.php?${params.toString()}`;

    try {
        const response = await fetch(url);
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        const claims = await response.json();

        if (claims.error) {
            console.error("Error fetching claims:", claims.error);
            container.innerHTML = `<p class="text-red-500 text-center py-4">Error: ${claims.error}</p>`;
        } else {
            renderMyClaimsTable(claims);
        }
    } catch (error) {
        console.error('Error loading claims:', error);
        container.innerHTML = `<p class="text-red-500 text-center py-4">Could not load claims. ${error.message}</p>`;
    }
}
function renderMyClaimsTable(claims) {
     console.log("[Render] Rendering My Claims Table...");
    const container = document.getElementById('my-claims-list-container');
    if (!container) return;

    if (!claims || claims.length === 0) {
        container.innerHTML = '<p class="text-center py-4 text-gray-500">You have not submitted any claims yet.</p>';
        return;
    }

    const table = document.createElement('table');
    table.className = 'min-w-full divide-y divide-gray-200 border border-gray-300';

    const thead = table.createTHead();
    thead.className = 'bg-gray-50';
    const headerRow = thead.insertRow();
    const headers = ['Claim ID', 'Type', 'Claim Date', 'Amount (PHP)', 'Submitted', 'Status', 'Receipt'];
    headers.forEach(text => {
        const th = document.createElement('th');
        th.scope = 'col';
        th.className = 'px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider';
        th.textContent = text;
        headerRow.appendChild(th);
    });

    const tbody = table.createTBody();
    tbody.className = 'bg-white divide-y divide-gray-200';

    claims.forEach(claim => {
        const row = tbody.insertRow();
        row.id = `myclaim-row-${claim.ClaimID}`;

        const createCell = (text) => {
            const cell = row.insertCell();
            cell.className = 'px-4 py-3 whitespace-nowrap text-sm';
            cell.textContent = text ?? 'N/A';
            return cell;
        };

        createCell(claim.ClaimID).classList.add('text-gray-500');
        createCell(claim.ClaimTypeName).classList.add('text-gray-700');
        createCell(claim.ClaimDateFormatted ?? claim.ClaimDate).classList.add('text-gray-700');
        const amountCell = createCell(claim.AmountFormatted ?? claim.Amount);
        amountCell.classList.add('text-gray-700', 'text-right');
        createCell(claim.SubmissionDateFormatted ?? claim.SubmissionDate).classList.add('text-gray-500');

        const statusCell = createCell(claim.Status);
        statusCell.classList.add('font-semibold');
        let statusColor = 'text-gray-600';
        if (claim.Status === 'Approved' || claim.Status === 'Paid') statusColor = 'text-green-600';
        else if (claim.Status === 'Rejected') statusColor = 'text-red-600';
        else if (claim.Status === 'Submitted' || claim.Status === 'Queried') statusColor = 'text-yellow-600';
        statusCell.classList.add(statusColor);

        const receiptCell = row.insertCell();
        receiptCell.className = 'px-4 py-3 whitespace-nowrap text-sm text-center';
        const receiptPath = claim.ReceiptPath ? `/hr34/${claim.ReceiptPath}` : null;
        if (receiptPath) {
            const link = document.createElement('a');
            link.href = receiptPath;
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
            link.className = 'text-blue-600 hover:text-blue-800';
            link.title = 'View Receipt';
            const icon = document.createElement('i');
            icon.className = 'fas fa-receipt';
            link.appendChild(icon);
            receiptCell.appendChild(link);
        } else {
            receiptCell.textContent = '-';
        }
    });

    container.innerHTML = '';
    container.appendChild(table);
}

// ============================
// === Approvals Section ===
// ============================
export async function displayClaimsApprovalSection() {
     console.log("[Display] Displaying Claims Approval Section...");
    if (!initializeClaimElements()) return;

    const user = window.currentUser;
    if (user?.role_name === 'Employee') {
        pageTitleElement.textContent = 'Access Denied';
        mainContentArea.innerHTML = `
            <div class="bg-white p-6 rounded-lg shadow-md border border-red-300">
                <p class="text-red-600 font-semibold">Access Denied: You do not have permission to view the Claims Approval section.</p>
            </div>`;
        return;
    }

    pageTitleElement.textContent = 'Approve Claims';
    mainContentArea.innerHTML = `
        <div class="bg-white p-6 rounded-lg shadow-md border border-[#F7E6CA] space-y-6">
            <h3 class="text-lg font-semibold text-[#4E3B2A] mb-3 font-header">Claims Pending Approval</h3>
             <div class="flex flex-wrap gap-4 mb-4 items-end">
                 <div>
                    <label for="filter-approval-employee" class="block text-sm font-medium text-gray-700 mb-1">Filter by Employee:</label>
                    <select id="filter-approval-employee" class="w-full sm:w-auto p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                        <option value="">All Employees</option>
                        </select>
                </div>
                 <div>
                    <button id="filter-approval-btn" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-150 ease-in-out">
                        Filter
                    </button>
                 </div>
            </div>
            <div id="claims-approval-list-container" class="overflow-x-auto">
                <p>Loading claims for approval...</p>
            </div>
        </div>`;

    requestAnimationFrame(async () => {
        await populateEmployeeDropdown('filter-approval-employee', true);

        const filterBtn = document.getElementById('filter-approval-btn');
        if (filterBtn) {
             if (!filterBtn.hasAttribute('data-listener-attached')) {
                filterBtn.addEventListener('click', applyClaimsApprovalFilter);
                filterBtn.setAttribute('data-listener-attached', 'true');
             }
        } else { console.error("Filter Approvals button not found."); }

        await loadClaimsForApproval();
    });
}
function applyClaimsApprovalFilter() {
     const employeeId = document.getElementById('filter-approval-employee')?.value;
    loadClaimsForApproval(employeeId);
}
async function loadClaimsForApproval(employeeId = null) {
    console.log("[Load] Loading Claims for Approval...");
    const container = document.getElementById('claims-approval-list-container');
    if (!container) return;
    container.innerHTML = '<p class="text-center py-4">Loading claims for approval...</p>';

    const params = new URLSearchParams();
    params.append('status', 'Submitted'); 
    if (employeeId) {
        params.append('employee_id', employeeId);
    }

    const url = `${API_BASE_URL}get_claims.php?${params.toString()}`;
    
    try {
        const response = await fetch(url);
        if (!response.ok) {
            const errorText = await response.text();
            throw new Error(`HTTP error! status: ${response.status}, Response: ${errorText}`);
        }
        const claims = await response.json();
        if (claims.error) {
            throw new Error(claims.error);
        }
        renderClaimsApprovalTable(claims);
    } catch (error) {
        console.error('[JS DEBUG] Error in loadClaimsForApproval:', error);
        container.innerHTML = `<p class="text-red-500 text-center py-4">Could not load claims. ${error.message}</p>`;
    }
}
function renderClaimsApprovalTable(claims) {
    console.log("[Render] Rendering Claims Approval Table...");
    const container = document.getElementById('claims-approval-list-container');
    if (!container) return;

    if (!claims || !Array.isArray(claims) || claims.length === 0) { 
        container.innerHTML = '<p class="text-center py-4 text-gray-500">No claims currently pending approval.</p>';
        return;
    }

    const table = document.createElement('table');
    table.className = 'min-w-full divide-y divide-gray-200 border border-gray-300';

    const thead = table.createTHead();
    thead.className = 'bg-gray-50';
    const headerRow = thead.insertRow();
    const headers = ['ID', 'Employee', 'Type', 'Claim Date', 'Amount', 'Submitted', 'Receipt', 'Actions'];
    headers.forEach(text => {
        const th = document.createElement('th');
        th.scope = 'col';
        th.className = 'px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider';
        th.textContent = text;
        headerRow.appendChild(th);
    });

    const tbody = table.createTBody();
    tbody.className = 'bg-white divide-y divide-gray-200 claims-approval-action-container';

    claims.forEach(claim => {
        const row = tbody.insertRow();
        row.id = `approval-claim-row-${claim.ClaimID}`;

        const createCell = (text) => {
            const cell = row.insertCell();
            cell.className = 'px-4 py-3 whitespace-nowrap text-sm';
            cell.textContent = text ?? 'N/A';
            return cell;
        };

        createCell(claim.ClaimID).classList.add('text-gray-500');
        createCell(claim.EmployeeName).classList.add('font-medium', 'text-gray-900');
        createCell(claim.ClaimTypeName).classList.add('text-gray-700');
        createCell(claim.ClaimDateFormatted ?? claim.ClaimDate).classList.add('text-gray-700');
        const amountCell = createCell(claim.AmountFormatted ?? claim.Amount);
        amountCell.classList.add('text-gray-700', 'text-right');
        createCell(claim.SubmissionDateFormatted ?? claim.SubmissionDate).classList.add('text-gray-500');

        const receiptCell = row.insertCell();
        receiptCell.className = 'px-4 py-3 whitespace-nowrap text-sm text-center';
        const receiptPath = claim.ReceiptPath ? `/hr34/${claim.ReceiptPath}` : null;
        if (receiptPath) {
            const link = document.createElement('a');
            link.href = receiptPath;
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
            link.className = 'text-blue-600 hover:text-blue-800';
            link.title = 'View Receipt';
            const icon = document.createElement('i');
            icon.className = 'fas fa-receipt';
            link.appendChild(icon);
            receiptCell.appendChild(link);
        } else {
            receiptCell.textContent = '-';
        }

        const actionsCell = row.insertCell();
        actionsCell.className = 'px-4 py-3 whitespace-nowrap text-sm font-medium space-x-2';

        const approveBtn = document.createElement('button');
        approveBtn.className = 'text-green-600 hover:text-green-800 approve-claim-btn';
        approveBtn.dataset.claimId = claim.ClaimID;
        approveBtn.title = 'Approve Claim';
        approveBtn.innerHTML = '<i class="fas fa-check-circle"></i> Approve';
        actionsCell.appendChild(approveBtn);

        const rejectBtn = document.createElement('button');
        rejectBtn.className = 'text-red-600 hover:text-red-800 reject-claim-btn';
        rejectBtn.dataset.claimId = claim.ClaimID;
        rejectBtn.title = 'Reject Claim';
        rejectBtn.innerHTML = '<i class="fas fa-times-circle"></i> Reject';
        actionsCell.appendChild(rejectBtn);
    });

    container.innerHTML = '';
    container.appendChild(table);

    attachClaimApprovalActionListeners();
}
function attachClaimApprovalActionListeners() {
     const container = document.querySelector('.claims-approval-action-container');
    if (container) {
        container.removeEventListener('click', handleClaimApprovalAction);
        container.addEventListener('click', handleClaimApprovalAction);
    }
}
async function handleClaimApprovalAction(event) {
    const approveButton = event.target.closest('.approve-claim-btn');
    const rejectButton = event.target.closest('.reject-claim-btn');

    if (approveButton) {
        const claimId = approveButton.dataset.claimId;
        if (claimId) {
            const { value: comments } = await Swal.fire({
                title: `Approve Claim ID ${claimId}?`,
                input: 'textarea',
                inputLabel: 'Optional Comments',
                inputPlaceholder: 'Enter approval comments here...',
                showCancelButton: true,
                confirmButtonText: 'Approve',
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#aaa',
                inputValidator: (value) => {
                    // No validation needed for optional comments
                    return null;
                }
            });
            if (comments !== undefined) { // Check if not cancelled
                updateClaimStatus(claimId, 'Approved', comments || null);
            }
        }
    } else if (rejectButton) {
        const claimId = rejectButton.dataset.claimId;
        if (claimId) {
            const { value: reason } = await Swal.fire({
                title: `Reject Claim ID ${claimId}?`,
                input: 'textarea',
                inputLabel: 'Reason for Rejection (Optional)',
                inputPlaceholder: 'Enter rejection reason here...',
                showCancelButton: true,
                confirmButtonText: 'Reject',
                confirmButtonColor: '#d33',
                cancelButtonColor: '#aaa',
                // You can add custom validation for reason if needed
            });
            if (reason !== undefined) { // Check if not cancelled
                 updateClaimStatus(claimId, 'Rejected', reason || null);
            }
        }
    }
}
async function updateClaimStatus(claimId, newStatus, comments = null) {
    console.log(`Updating Claim ID ${claimId} to status: ${newStatus} with comments: ${comments}`);

    const user = window.currentUser;
    const approverId = user?.employee_id;
    if (!approverId) {
        Swal.fire('Error', 'Could not identify approver. Please log in again.', 'error');
        return;
    }
    
    Swal.fire({
        title: 'Processing...',
        text: `Updating claim to ${newStatus}...`,
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    try {
        const response = await fetch(`${API_BASE_URL}update_claims_status.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                claim_id: parseInt(claimId),
                new_status: newStatus,
                approver_id: approverId, 
                comments: comments
            })
        });

        const contentType = response.headers.get("content-type");
        let result;

        if (!response.ok) {
             let errorPayload = { error: `HTTP error! Status: ${response.status}` };
             if (contentType && contentType.includes("application/json")) {
                 try {
                     result = await response.json();
                     errorPayload.error = result.error || errorPayload.error;
                     errorPayload.details = result.details;
                 } catch (jsonError) {
                     errorPayload.error += " (Non-JSON error response received)";
                 }
             } else {
                 const errorText = await response.text();
                 errorPayload.error = `Server returned non-JSON response (Status: ${response.status}). Response: ${errorText.substring(0,100)}`;
             }
             const error = new Error(errorPayload.error);
             error.details = errorPayload.details;
             throw error;
        }
        try {
             result = await response.json();
        } catch (jsonError) {
             throw new Error("Received successful status, but failed to parse JSON response.");
        }

        Swal.fire({
            icon: 'success',
            title: 'Status Updated!',
            text: result.message || `Claim ${newStatus.toLowerCase()} successfully!`,
            confirmButtonColor: '#4E3B2A',
            timer: 2000
        });
        await loadClaimsForApproval(document.getElementById('filter-approval-employee')?.value);

    } catch (error) {
        console.error(`Error updating claim status for ID ${claimId}:`, error);
        let displayMessage = `Failed to update claim status: ${error.message}`;
        if (error.details) {
             displayMessage += ` Details: ${JSON.stringify(error.details)}`;
        }
        Swal.fire({
            icon: 'error',
            title: 'Update Failed',
            text: displayMessage,
            confirmButtonColor: '#4E3B2A'
        });
    } finally {
        if (Swal.isLoading()) {
            Swal.close();
        }
    }
}

// =================================
// === Claim Types Admin Section ===
// =================================
export async function displayClaimTypesAdminSection() {
      console.log("[Display] Displaying Claim Types Admin Section...");
    if (!initializeClaimElements()) return;

    const user = window.currentUser;
    if (user?.role_name === 'Employee' || user?.role_name === 'Manager') {
        pageTitleElement.textContent = 'Access Denied';
        mainContentArea.innerHTML = `
            <div class="bg-white p-6 rounded-lg shadow-md border border-red-300">
                <p class="text-red-600 font-semibold">Access Denied: You do not have permission to manage Claim Types.</p>
            </div>`;
        return;
    }

    pageTitleElement.textContent = 'Manage Claim Types';
    mainContentArea.innerHTML = `
        <div class="bg-white p-6 rounded-lg shadow-md border border-[#F7E6CA] space-y-6">
            <div class="border-b border-gray-200 pb-4">
                 <h3 class="text-lg font-semibold text-[#4E3B2A] mb-3 font-header"><span id="claim-type-form-title">Add New</span> Claim Type</h3>
                 <form id="add-edit-claim-type-form" class="space-y-4">
                    <input type="hidden" id="claim-type-id" name="claim_type_id" value="">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="claim-type-name" class="block text-sm font-medium text-gray-700 mb-1">Type Name:</label>
                            <input type="text" id="claim-type-name" name="type_name" required placeholder="e.g., Transportation" class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                        </div>
                         <div class="flex items-center pt-6">
                             <input id="claim-type-requires-receipt" name="requires_receipt" type="checkbox" value="1" class="h-4 w-4 text-[#594423] focus:ring-[#4E3B2A] border-gray-300 rounded">
                             <label for="claim-type-requires-receipt" class="ml-2 block text-sm text-gray-900">Requires Receipt?</label>
                         </div>
                         <div class="md:col-span-2">
                            <label for="claim-type-description" class="block text-sm font-medium text-gray-700 mb-1">Description:</label>
                            <textarea id="claim-type-description" name="description" rows="2" placeholder="Details about this claim type..." class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]"></textarea>
                        </div>
                    </div>
                     <div class="pt-2 space-x-3">
                        <button type="submit" class="px-4 py-2 bg-[#594423] text-white rounded-md hover:bg-[#4E3B2A] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#594423] transition duration-150 ease-in-out">
                            Save Claim Type
                        </button>
                         <button type="button" id="cancel-edit-claim-type-btn" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition duration-150 ease-in-out" style="display: none;">
                            Cancel Edit
                        </button>
                        </div>
                 </form>
            </div>

            <div>
                <h3 class="text-lg font-semibold text-[#4E3B2A] mb-3 font-header">Existing Claim Types</h3>
                <div id="claim-types-admin-list-container" class="overflow-x-auto">
                     <p>Loading claim types...</p>
                </div>
            </div>
        </div>`;

     requestAnimationFrame(async () => {
        const claimTypeForm = document.getElementById('add-edit-claim-type-form');
        if (claimTypeForm) {
            if (!claimTypeForm.hasAttribute('data-listener-attached')) {
                claimTypeForm.addEventListener('submit', handleSaveClaimType);
                claimTypeForm.setAttribute('data-listener-attached', 'true');
            }
        } else { console.error("Add/Edit Claim Type form not found."); }

         const cancelBtn = document.getElementById('cancel-edit-claim-type-btn');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', resetClaimTypeForm);
        }

        await loadClaimTypesForAdmin();
    });
}
async function loadClaimTypesForAdmin() {
     console.log("[Load] Loading Claim Types for Admin...");
    const container = document.getElementById('claim-types-admin-list-container');
    if (!container) return;
    container.innerHTML = '<p class="text-center py-4">Loading claim types...</p>';

    const url = `${API_BASE_URL}get_claim_types.php`;

    try {
        const response = await fetch(url);
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        const claimTypes = await response.json();

        if (claimTypes.error) {
            console.error("Error fetching claim types for admin:", claimTypes.error);
            container.innerHTML = `<p class="text-red-500 text-center py-4">Error: ${claimTypes.error}</p>`;
        } else {
            renderClaimTypesAdminTable(claimTypes);
        }
    } catch (error) {
        console.error('Error loading claim types for admin:', error);
        container.innerHTML = `<p class="text-red-500 text-center py-4">Could not load claim types. ${error.message}</p>`;
    }
}
function renderClaimTypesAdminTable(claimTypes) {
     console.log("[Render] Rendering Claim Types Admin Table...");
    const container = document.getElementById('claim-types-admin-list-container');
    if (!container) return;

    if (!claimTypes || claimTypes.length === 0) {
        container.innerHTML = '<p class="text-center py-4 text-gray-500">No claim types configured yet.</p>';
        return;
    }

    const table = document.createElement('table');
    table.className = 'min-w-full divide-y divide-gray-200 border border-gray-300';

    const thead = table.createTHead();
    thead.className = 'bg-gray-50';
    const headerRow = thead.insertRow();
    const headers = ['ID', 'Type Name', 'Description', 'Requires Receipt?', 'Actions'];
    headers.forEach(text => {
        const th = document.createElement('th');
        th.scope = 'col';
        th.className = 'px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider';
        th.textContent = text;
        headerRow.appendChild(th);
    });

    const tbody = table.createTBody();
    tbody.className = 'bg-white divide-y divide-gray-200 claim-types-admin-action-container';

    claimTypes.forEach(type => {
        const row = tbody.insertRow();
        row.id = `claim-type-row-${type.ClaimTypeID}`;

        const createCell = (text) => {
            const cell = row.insertCell();
            cell.className = 'px-4 py-3 whitespace-nowrap text-sm';
            cell.textContent = text ?? 'N/A';
            return cell;
        };

        createCell(type.ClaimTypeID).classList.add('text-gray-500');
        createCell(type.TypeName).classList.add('font-medium', 'text-gray-900');
        const descCell = createCell(type.Description || '-');
        descCell.classList.add('text-gray-700', 'whitespace-normal', 'break-words');
        createCell(type.RequiresReceipt == 1 ? 'Yes' : 'No').classList.add('text-gray-700');

        const actionsCell = row.insertCell();
        actionsCell.className = 'px-4 py-3 whitespace-nowrap text-sm font-medium space-x-2';

        const editBtn = document.createElement('button');
        editBtn.className = 'text-blue-600 hover:text-blue-800 edit-claim-type-btn';
        editBtn.dataset.id = type.ClaimTypeID;
        editBtn.dataset.name = type.TypeName;
        editBtn.dataset.description = type.Description || '';
        editBtn.dataset.requiresReceipt = type.RequiresReceipt ?? 0;
        editBtn.title = 'Edit Claim Type';
        editBtn.innerHTML = '<i class="fas fa-edit"></i> Edit';
        actionsCell.appendChild(editBtn);

        const deleteBtn = document.createElement('button');
        deleteBtn.className = 'text-red-600 hover:text-red-800 delete-claim-type-btn';
        deleteBtn.dataset.id = type.ClaimTypeID;
        deleteBtn.dataset.name = type.TypeName;
        deleteBtn.title = 'Delete Claim Type';
        deleteBtn.innerHTML = '<i class="fas fa-trash-alt"></i> Delete';
        actionsCell.appendChild(deleteBtn);
    });

    container.innerHTML = '';
    container.appendChild(table);

    attachClaimTypeAdminActionListeners();
}
function attachClaimTypeAdminActionListeners() {
     const container = document.querySelector('.claim-types-admin-action-container');
    if (container) {
        container.removeEventListener('click', handleClaimTypeAdminAction);
        container.addEventListener('click', handleClaimTypeAdminAction);
    }
}
async function handleClaimTypeAdminAction(event) {
     const editButton = event.target.closest('.edit-claim-type-btn');
    const deleteButton = event.target.closest('.delete-claim-type-btn');

    if (editButton) {
        const id = editButton.dataset.id;
        const name = editButton.dataset.name;
        const description = editButton.dataset.description;
        const requiresReceipt = editButton.dataset.requiresReceipt;
        populateClaimTypeFormForEdit(id, name, description, requiresReceipt);
    } else if (deleteButton) {
        const id = deleteButton.dataset.id;
        const name = deleteButton.dataset.name;
        
        const result = await Swal.fire({
            title: 'Delete Claim Type?',
            text: `Are you sure you want to delete the claim type "${name}" (ID: ${id})? This action cannot be undone.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!'
        });

        if (result.isConfirmed) {
            deleteClaimType(id);
        }
    }
}
function populateClaimTypeFormForEdit(id, name, description, requiresReceipt) {
     document.getElementById('claim-type-form-title').textContent = 'Edit';
    document.getElementById('claim-type-id').value = id;
    document.getElementById('claim-type-name').value = name;
    document.getElementById('claim-type-description').value = description;
    document.getElementById('claim-type-requires-receipt').checked = (requiresReceipt == 1);
    document.getElementById('cancel-edit-claim-type-btn').style.display = 'inline-block';
    document.getElementById('add-edit-claim-type-form').scrollIntoView({ behavior: 'smooth' });
}
function resetClaimTypeForm() {
      document.getElementById('claim-type-form-title').textContent = 'Add New';
     document.getElementById('add-edit-claim-type-form').reset();
     document.getElementById('claim-type-id').value = '';
     document.getElementById('cancel-edit-claim-type-btn').style.display = 'none';
     // document.getElementById('add-edit-claim-type-status').textContent = ''; // Not using this span anymore
}
async function handleSaveClaimType(event) {
     event.preventDefault();
    const form = event.target;
    const submitButton = form.querySelector('button[type="submit"]');
    if (!submitButton) return;

    const claimTypeId = form.elements['claim_type_id'].value;
    const typeName = form.elements['type_name'].value.trim();
    const description = form.elements['description'].value.trim();
    const requiresReceipt = form.elements['claim-type-requires-receipt'].checked ? 1 : 0;

    if (!typeName) {
        Swal.fire({
            icon: 'error',
            title: 'Validation Error',
            text: 'Claim Type Name is required.',
            confirmButtonColor: '#4E3B2A'
        });
        return;
    }

    const isEditing = !!claimTypeId;
    const url = isEditing ? `${API_BASE_URL}update_claim_type.php` : `${API_BASE_URL}add_claim_type.php`;
    const method = 'POST';

    const formData = {
        type_name: typeName,
        description: description,
        requires_receipt: requiresReceipt
    };
    if (isEditing) {
        formData.claim_type_id = parseInt(claimTypeId);
    }

    Swal.fire({
        title: 'Processing...',
        text: isEditing ? 'Updating claim type...' : 'Adding claim type...',
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

        const contentType = response.headers.get("content-type");
        let result;
        if (!response.ok) {
            let errorPayload = { error: `HTTP error! Status: ${response.status}` };
            if (contentType && contentType.includes("application/json")) {
                try { result = await response.json(); errorPayload.error = result.error || errorPayload.error; errorPayload.details = result.details; }
                catch (jsonError) { errorPayload.error += " (Non-JSON error response received)"; }
            } else {
                const errorText = await response.text(); 
                errorPayload.error = `Server returned non-JSON response (Status: ${response.status}). Response: ${errorText.substring(0,100)}`;
            }
            const error = new Error(errorPayload.error); error.details = errorPayload.details; throw error;
        }
        try { result = await response.json(); }
        catch (jsonError) { throw new Error("Received successful status, but failed to parse JSON response."); }

        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: result.message || `Claim type ${isEditing ? 'updated' : 'added'} successfully!`,
            confirmButtonColor: '#4E3B2A',
            timer: 2000
        });
        resetClaimTypeForm();
        await loadClaimTypesForAdmin();

    } catch (error) {
        console.error(`Error ${isEditing ? 'updating' : 'adding'} claim type:`, error);
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
        if (Swal.isLoading()) {
            Swal.close();
        }
    }
}
async function deleteClaimType(claimTypeId) {
     console.log(`[Delete] Attempting to delete Claim Type ID: ${claimTypeId}`);
    
    Swal.fire({
        title: 'Processing...',
        text: `Deleting claim type ${claimTypeId}...`,
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    try {
        const response = await fetch(`${API_BASE_URL}delete_claim_type.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ claim_type_id: parseInt(claimTypeId) })
        });

        const contentType = response.headers.get("content-type");
        let result;
        if (!response.ok) {
            let errorPayload = { error: `HTTP error! Status: ${response.status}` };
            if (contentType && contentType.includes("application/json")) {
                try { result = await response.json(); errorPayload.error = result.error || errorPayload.error; errorPayload.details = result.details; }
                catch (jsonError) { errorPayload.error += " (Non-JSON error response received)"; }
            } else {
                const errorText = await response.text(); 
                errorPayload.error = `Server returned non-JSON response (Status: ${response.status}). Response: ${errorText.substring(0,100)}`;
            }
            const error = new Error(errorPayload.error); error.details = errorPayload.details; throw error;
        }
        try { result = await response.json(); }
        catch (jsonError) { throw new Error("Received successful status, but failed to parse JSON response."); }

        Swal.fire({
            icon: 'success',
            title: 'Deleted!',
            text: result.message || 'Claim type deleted successfully!',
            confirmButtonColor: '#4E3B2A',
            timer: 2000
        });

        await loadClaimTypesForAdmin();
        resetClaimTypeForm(); // If the form was populated for editing this type

    } catch (error) {
        console.error('Error deleting claim type:', error);
        Swal.fire({
            icon: 'error',
            title: 'Deletion Failed',
            text: `Error deleting claim type: ${error.message}`,
            confirmButtonColor: '#4E3B2A'
        });
    } finally {
        if (Swal.isLoading()) {
            Swal.close();
        }
    }
}
