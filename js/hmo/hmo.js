// HMO & Benefits Management Module
// Handles HMO providers, plans, enrollments, and claims for both admin and employee views

import { API_BASE_URL } from '../utils.js';

// --- Admin HMO Functions ---

// Display HMO Providers & Plans section
export function displayHMOProvidersSection() {
    const mainContentArea = document.getElementById('main-content-area');
    const pageTitleElement = document.getElementById('page-title');
    const pageSubtitleElement = document.getElementById('page-subtitle');

    if (!mainContentArea || !pageTitleElement) {
        console.error("Main content area or page title element not found");
        return;
    }

    pageTitleElement.textContent = 'HMO Providers & Plans';
    pageSubtitleElement.textContent = 'Manage HMO providers and their benefit plans';

    mainContentArea.innerHTML = `
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-800">HMO Providers & Plans</h2>
                <div class="space-x-2">
                    <button id="add-provider-btn" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                        <i class="fas fa-plus mr-2"></i>Add Provider
                    </button>
                    <button id="add-plan-btn" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                        <i class="fas fa-plus mr-2"></i>Add Plan
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Providers List -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold mb-4">HMO Providers</h3>
                    <div id="providers-list" class="space-y-2">
                        <p class="text-gray-500">Loading providers...</p>
                    </div>
                </div>

                <!-- Plans List -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold mb-4">Benefit Plans</h3>
                    <div id="plans-list" class="space-y-2">
                        <p class="text-gray-500">Loading plans...</p>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Load data
    loadHMOProviders();
    loadHMOPlans();

    // Add event listeners
    document.getElementById('add-provider-btn').addEventListener('click', showAddProviderModal);
    document.getElementById('add-plan-btn').addEventListener('click', showAddPlanModal);
}

// Display HMO Enrollments section
export function displayHMOEnrollmentsSection() {
    const mainContentArea = document.getElementById('main-content-area');
    const pageTitleElement = document.getElementById('page-title');
    const pageSubtitleElement = document.getElementById('page-subtitle');

    if (!mainContentArea || !pageTitleElement) {
        console.error("Main content area or page title element not found");
        return;
    }

    pageTitleElement.textContent = 'HMO Enrollments';
    pageSubtitleElement.textContent = 'Manage employee HMO enrollments';

    mainContentArea.innerHTML = `
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-800">HMO Enrollments</h2>
                <button id="add-enrollment-btn" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                    <i class="fas fa-plus mr-2"></i>Add Enrollment
                </button>
            </div>

            <div class="bg-white rounded-lg shadow">
                <div class="p-6">
                    <div id="enrollments-table" class="overflow-x-auto">
                        <table class="min-w-full table-auto">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="px-4 py-2 text-left">Employee</th>
                                    <th class="px-4 py-2 text-left">Plan</th>
                                    <th class="px-4 py-2 text-left">Enrollment Date</th>
                                    <th class="px-4 py-2 text-left">Status</th>
                                    <th class="px-4 py-2 text-left">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="enrollments-tbody">
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                        Loading enrollments...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Load data
    loadHMOEnrollments();

    // Add event listeners
    document.getElementById('add-enrollment-btn').addEventListener('click', showAddEnrollmentModal);
}

// Display HMO Claims Approval section
export function displayHMOClaimsApprovalSection() {
    const mainContentArea = document.getElementById('main-content-area');
    const pageTitleElement = document.getElementById('page-title');
    const pageSubtitleElement = document.getElementById('page-subtitle');

    if (!mainContentArea || !pageTitleElement) {
        console.error("Main content area or page title element not found");
        return;
    }

    pageTitleElement.textContent = 'HMO Claims Approval';
    pageSubtitleElement.textContent = 'Review and approve HMO claims';

    mainContentArea.innerHTML = `
        <div class="p-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">HMO Claims Approval</h2>

            <div class="bg-white rounded-lg shadow">
                <div class="p-6">
                    <div id="claims-table" class="overflow-x-auto">
                        <table class="min-w-full table-auto">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="px-4 py-2 text-left">Employee</th>
                                    <th class="px-4 py-2 text-left">Claim Type</th>
                                    <th class="px-4 py-2 text-left">Amount</th>
                                    <th class="px-4 py-2 text-left">Date Submitted</th>
                                    <th class="px-4 py-2 text-left">Status</th>
                                    <th class="px-4 py-2 text-left">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="claims-tbody">
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                        Loading claims...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Load data
    loadHMOClaimsForApproval();
}

// --- Employee HMO Functions ---

// Display Employee HMO Benefits section
export function displayEmployeeHMOSection() {
    const mainContentArea = document.getElementById('main-content-area');
    const pageTitleElement = document.getElementById('page-title');
    const pageSubtitleElement = document.getElementById('page-subtitle');

    if (!mainContentArea || !pageTitleElement) {
        console.error("Main content area or page title element not found");
        return;
    }

    pageTitleElement.textContent = 'My HMO Benefits';
    pageSubtitleElement.textContent = 'View your HMO plan and benefits';

    mainContentArea.innerHTML = `
        <div class="p-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">My HMO Benefits</h2>

            <div id="hmo-benefits-content">
                <p class="text-gray-500">Loading your HMO benefits...</p>
            </div>
        </div>
    `;

    // Load data
    loadEmployeeHMOBenefits();
}

// Display Employee HMO Claims section
export function displayEmployeeHMOClaimsSection() {
    const mainContentArea = document.getElementById('main-content-area');
    const pageTitleElement = document.getElementById('page-title');
    const pageSubtitleElement = document.getElementById('page-subtitle');

    if (!mainContentArea || !pageTitleElement) {
        console.error("Main content area or page title element not found");
        return;
    }

    pageTitleElement.textContent = 'My HMO Claims';
    pageSubtitleElement.textContent = 'View your submitted HMO claims';

    mainContentArea.innerHTML = `
        <div class="p-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">My HMO Claims</h2>

            <div class="bg-white rounded-lg shadow">
                <div class="p-6">
                    <div id="employee-claims-table" class="overflow-x-auto">
                        <table class="min-w-full table-auto">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="px-4 py-2 text-left">Claim Type</th>
                                    <th class="px-4 py-2 text-left">Provider</th>
                                    <th class="px-4 py-2 text-left">Plan</th>
                                    <th class="px-4 py-2 text-left">Description</th>
                                    <th class="px-4 py-2 text-left">Amount</th>
                                    <th class="px-4 py-2 text-left">Date Submitted</th>
                                    <th class="px-4 py-2 text-left">Status</th>
                                    <th class="px-4 py-2 text-left">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="employee-claims-tbody">
                                <tr>
                                    <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                                        Loading your claims...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Load data
    loadEmployeeHMOClaims();
}

// Display Submit HMO Claim section
export function displaySubmitHMOClaimSection() {
    const mainContentArea = document.getElementById('main-content-area');
    const pageTitleElement = document.getElementById('page-title');
    const pageSubtitleElement = document.getElementById('page-subtitle');

    if (!mainContentArea || !pageTitleElement) {
        console.error("Main content area or page title element not found");
        return;
    }

    pageTitleElement.textContent = 'Submit HMO Claim';
    pageSubtitleElement.textContent = 'Submit a new HMO claim for reimbursement';

    mainContentArea.innerHTML = `
        <div class="p-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">Submit HMO Claim</h2>

            <div class="bg-white rounded-lg shadow p-6">
                <form id="submit-hmo-claim-form" class="space-y-4">
                    <div>
                        <label for="service-type" class="block text-sm font-medium text-gray-700 mb-1">Service Type</label>
                        <select id="service-type" name="serviceType" required class="w-full p-2 border border-gray-300 rounded-md">
                            <option value="">Select service type...</option>
                            <option value="consultation">Medical Consultation</option>
                            <option value="medication">Medication</option>
                            <option value="laboratory">Laboratory Tests</option>
                            <option value="hospitalization">Hospitalization</option>
                            <option value="dental">Dental</option>
                            <option value="optical">Optical</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div>
                        <label for="provider-name" class="block text-sm font-medium text-gray-700 mb-1">Healthcare Provider Name</label>
                        <input type="text" id="provider-name" name="providerName" required
                               class="w-full p-2 border border-gray-300 rounded-md" placeholder="Hospital/Clinic/Doctor name">
                    </div>

                    <div>
                        <label for="amount-claimed" class="block text-sm font-medium text-gray-700 mb-1">Amount Claimed (₱)</label>
                        <input type="number" id="amount-claimed" name="amountClaimed" step="0.01" min="0" required
                               class="w-full p-2 border border-gray-300 rounded-md" placeholder="0.00">
                    </div>

                    <div>
                        <label for="service-date" class="block text-sm font-medium text-gray-700 mb-1">Service Date</label>
                        <input type="date" id="service-date" name="serviceDate" required
                               class="w-full p-2 border border-gray-300 rounded-md">
                    </div>

                    <div>
                        <label for="diagnosis" class="block text-sm font-medium text-gray-700 mb-1">Diagnosis (Optional)</label>
                        <input type="text" id="diagnosis" name="diagnosis"
                               class="w-full p-2 border border-gray-300 rounded-md" placeholder="Medical diagnosis if known">
                    </div>

                    <div>
                        <label for="treatment-description" class="block text-sm font-medium text-gray-700 mb-1">Treatment/Service Description</label>
                        <textarea id="treatment-description" name="treatmentDescription" rows="4" required
                                  class="w-full p-2 border border-gray-300 rounded-md"
                                  placeholder="Describe the medical service, treatment, or expense..."></textarea>
                    </div>

                    <div>
                        <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Additional Notes (Optional)</label>
                        <textarea id="notes" name="notes" rows="2"
                                  class="w-full p-2 border border-gray-300 rounded-md"
                                  placeholder="Any additional information..."></textarea>
                    </div>

                    <div>
                        <label for="claim-receipt" class="block text-sm font-medium text-gray-700 mb-1">Receipt/Invoice (Optional)</label>
                        <input type="file" id="claim-receipt" name="receipt" accept="image/*,.pdf"
                               class="w-full p-2 border border-gray-300 rounded-md">
                    </div>

                    <div class="pt-4">
                        <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                            <i class="fas fa-paper-plane mr-2"></i>Submit Claim
                        </button>
                    </div>

                    <div id="submit-claim-status" class="text-sm mt-2"></div>
                </form>
            </div>
        </div>
    `;

    // Add form submission handler
    document.getElementById('submit-hmo-claim-form').addEventListener('submit', handleSubmitHMOClaim);
}

// --- Data Loading Functions ---

async function loadHMOProviders() {
    try {
        const response = await fetch(`${API_BASE_URL}get_hmo_providers.php`);
        const data = await response.json();

        const providersList = document.getElementById('providers-list');
        if (data.success && data.providers) {
            providersList.innerHTML = data.providers.map(provider => `
                <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                    <div>
                        <h4 class="font-medium">${provider.name}</h4>
                        <p class="text-sm text-gray-600">${provider.contact_info || 'No contact info'}</p>
                    </div>
                    <div class="space-x-2">
                        <button class="text-blue-600 hover:text-blue-800" onclick="editProvider(${provider.id})">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="text-red-600 hover:text-red-800" onclick="deleteProvider(${provider.id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `).join('');
        } else {
            providersList.innerHTML = '<p class="text-gray-500">No providers found.</p>';
        }
    } catch (error) {
        console.error('Error loading HMO providers:', error);
        document.getElementById('providers-list').innerHTML = '<p class="text-red-500">Error loading providers.</p>';
    }
}

async function loadHMOPlans() {
    try {
        const response = await fetch(`${API_BASE_URL}get_hmo_plans.php`);
        const data = await response.json();

        const plansList = document.getElementById('plans-list');
        if (data.success && data.plans) {
            plansList.innerHTML = data.plans.map(plan => `
                <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                    <div>
                        <h4 class="font-medium">${plan.name}</h4>
                        <p class="text-sm text-gray-600">${plan.provider_name} - ₱${plan.premium_amount}/month</p>
                    </div>
                    <div class="space-x-2">
                        <button class="text-blue-600 hover:text-blue-800" onclick="editPlan(${plan.id})">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="text-red-600 hover:text-red-800" onclick="deletePlan(${plan.id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `).join('');
        } else {
            plansList.innerHTML = '<p class="text-gray-500">No plans found.</p>';
        }
    } catch (error) {
        console.error('Error loading HMO plans:', error);
        document.getElementById('plans-list').innerHTML = '<p class="text-red-500">Error loading plans.</p>';
    }
}

async function loadHMOEnrollments() {
    try {
        const response = await fetch(`${API_BASE_URL}get_hmo_enrollments.php`);
        const data = await response.json();

        const tbody = document.getElementById('enrollments-tbody');
        if (data.success && data.enrollments) {
            tbody.innerHTML = data.enrollments.map(enrollment => `
                <tr class="border-t">
                    <td class="px-4 py-2">${enrollment.employee_name}</td>
                    <td class="px-4 py-2">${enrollment.plan_name}</td>
                    <td class="px-4 py-2">${new Date(enrollment.enrollment_date).toLocaleDateString()}</td>
                    <td class="px-4 py-2">
                        <span class="px-2 py-1 rounded text-xs ${enrollment.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'}">
                            ${enrollment.status}
                        </span>
                    </td>
                    <td class="px-4 py-2 space-x-2">
                        <button class="text-blue-600 hover:text-blue-800" onclick="editEnrollment(${enrollment.id})">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="text-red-600 hover:text-red-800" onclick="terminateEnrollment(${enrollment.id})">
                            <i class="fas fa-times"></i>
                        </button>
                    </td>
                </tr>
            `).join('');
        } else {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                        No enrollments found.
                    </td>
                </tr>
            `;
        }
    } catch (error) {
        console.error('Error loading HMO enrollments:', error);
        document.getElementById('enrollments-tbody').innerHTML = `
            <tr>
                <td colspan="5" class="px-4 py-8 text-center text-red-500">
                    Error loading enrollments.
                </td>
            </tr>
        `;
    }
}

async function loadHMOClaimsForApproval() {
    try {
        const response = await fetch(`${API_BASE_URL}get_hmo_claims.php?status=pending`);
        const data = await response.json();

        const tbody = document.getElementById('claims-tbody');
        if (data.success && data.claims) {
            tbody.innerHTML = data.claims.map(claim => `
                <tr class="border-t">
                    <td class="px-4 py-2">${claim.employee_name}</td>
                    <td class="px-4 py-2">${claim.claim_type}</td>
                    <td class="px-4 py-2">₱${parseFloat(claim.amount).toFixed(2)}</td>
                    <td class="px-4 py-2">${new Date(claim.submission_date).toLocaleDateString()}</td>
                    <td class="px-4 py-2">
                        <span class="px-2 py-1 rounded text-xs bg-yellow-100 text-yellow-800">
                            ${claim.status}
                        </span>
                    </td>
                    <td class="px-4 py-2 space-x-2">
                        <button class="text-green-600 hover:text-green-800" onclick="approveClaim(${claim.id})">
                            <i class="fas fa-check"></i> Approve
                        </button>
                        <button class="text-red-600 hover:text-red-800" onclick="rejectClaim(${claim.id})">
                            <i class="fas fa-times"></i> Reject
                        </button>
                        <button class="text-blue-600 hover:text-blue-800" onclick="viewClaimDetails(${claim.id})">
                            <i class="fas fa-eye"></i> View
                        </button>
                    </td>
                </tr>
            `).join('');
        } else {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                        No pending claims found.
                    </td>
                </tr>
            `;
        }
    } catch (error) {
        console.error('Error loading HMO claims:', error);
        document.getElementById('claims-tbody').innerHTML = `
            <tr>
                <td colspan="6" class="px-4 py-8 text-center text-red-500">
                    Error loading claims.
                </td>
            </tr>
        `;
    }
}

async function loadEmployeeHMOBenefits() {
    try {
        const response = await fetch(`${API_BASE_URL}get_employee_hmo_benefits.php`, {
            credentials: 'include'
        });
        const data = await response.json();

        const content = document.getElementById('hmo-benefits-content');
        if (data.success && data.benefits && data.benefits.length > 0) {
            // Get the first (most recent) active enrollment
            const benefits = data.benefits[0];
            content.innerHTML = `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold mb-4">Your HMO Plan</h3>
                        <div class="space-y-2">
                            <p><strong>Plan:</strong> ${benefits.PlanName}</p>
                            <p><strong>Provider:</strong> ${benefits.ProviderName}</p>
                            <p><strong>Enrollment Date:</strong> ${new Date(benefits.EnrollmentDate).toLocaleDateString()}</p>
                            <p><strong>Status:</strong>
                                <span class="px-2 py-1 rounded text-xs ${benefits.Status === 'Active' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'}">
                                    ${benefits.Status}
                                </span>
                            </p>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold mb-4">Coverage Summary</h3>
                        <div class="space-y-2">
                            <p><strong>Monthly Premium:</strong> ₱${parseFloat(benefits.MonthlyPremium).toFixed(2)}</p>
                            <p><strong>Coverage Type:</strong> ${benefits.CoverageType || 'Comprehensive'}</p>
                            <p><strong>Effective Date:</strong> ${new Date(benefits.EffectiveDate).toLocaleDateString()}</p>
                        </div>
                    </div>
                </div>

                <div class="mt-6 bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold mb-4">Covered Services</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="text-center">
                            <i class="fas fa-stethoscope text-2xl text-blue-600 mb-2"></i>
                            <p class="font-medium">Medical Consultations</p>
                        </div>
                        <div class="text-center">
                            <i class="fas fa-pills text-2xl text-green-600 mb-2"></i>
                            <p class="font-medium">Medications</p>
                        </div>
                        <div class="text-center">
                            <i class="fas fa-flask text-2xl text-purple-600 mb-2"></i>
                            <p class="font-medium">Laboratory Tests</p>
                        </div>
                    </div>
                </div>
            `;
        } else {
            content.innerHTML = `
                <div class="bg-yellow-50 border border-yellow-200 rounded p-4">
                    <p class="text-yellow-800">You are not currently enrolled in an HMO plan. Please contact HR for enrollment.</p>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error loading employee HMO benefits:', error);
        document.getElementById('hmo-benefits-content').innerHTML = `
            <div class="bg-red-50 border border-red-200 rounded p-4">
                <p class="text-red-800">Error loading your HMO benefits. Please try again later.</p>
            </div>
        `;
    }
}

async function loadEmployeeHMOClaims() {
    try {
        const response = await fetch(`${API_BASE_URL}get_employee_hmo_claims.php`, {
            credentials: 'include'
        });
        const data = await response.json();

        const tbody = document.getElementById('employee-claims-tbody');
        if (data.success && data.claims) {
            tbody.innerHTML = data.claims.map(claim => `
                <tr class="border-t">
                    <td class="px-4 py-2">${claim.ClaimType || 'N/A'}</td>
                    <td class="px-4 py-2">${claim.ProviderName || 'N/A'}</td>
                    <td class="px-4 py-2">${claim.PlanName || 'N/A'}</td>
                    <td class="px-4 py-2 max-w-xs truncate" title="${claim.Description || 'No description'}">${claim.Description ? (claim.Description.length > 50 ? claim.Description.substring(0, 50) + '...' : claim.Description) : 'N/A'}</td>
                    <td class="px-4 py-2">₱${parseFloat(claim.Amount || 0).toFixed(2)}</td>
                    <td class="px-4 py-2">${new Date(claim.SubmittedDate).toLocaleDateString()}</td>
                    <td class="px-4 py-2">
                        <span class="px-2 py-1 rounded text-xs ${
                            claim.Status === 'Approved' ? 'bg-green-100 text-green-800' :
                            claim.Status === 'Rejected' ? 'bg-red-100 text-red-800' :
                            'bg-yellow-100 text-yellow-800'
                        }">
                            ${claim.Status || 'Pending'}
                        </span>
                    </td>
                    <td class="px-4 py-2">
                        <button class="text-blue-600 hover:text-blue-800" onclick="viewEmployeeClaimDetails(${claim.ClaimID})">
                            <i class="fas fa-eye"></i> View Details
                        </button>
                    </td>
                </tr>
            `).join('');
        } else {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                        No claims found.
                    </td>
                </tr>
            `;
        }
    } catch (error) {
        console.error('Error loading employee HMO claims:', error);
        document.getElementById('employee-claims-tbody').innerHTML = `
            <tr>
                <td colspan="8" class="px-4 py-8 text-center text-red-500">
                    Error loading claims.
                </td>
            </tr>
        `;
    }
}

// --- Modal and Form Handlers ---

function showAddProviderModal() {
    // Implementation for add provider modal
    Swal.fire({
        title: 'Add HMO Provider',
        html: `
            <form id="add-provider-form" class="space-y-4">
                <div>
                    <label for="provider-name" class="block text-sm font-medium text-gray-700 mb-1">Provider Name</label>
                    <input type="text" id="provider-name" name="name" required class="w-full p-2 border border-gray-300 rounded-md">
                </div>
                <div>
                    <label for="provider-contact" class="block text-sm font-medium text-gray-700 mb-1">Contact Information</label>
                    <input type="text" id="provider-contact" name="contact_info" class="w-full p-2 border border-gray-300 rounded-md">
                </div>
            </form>
        `,
        showCancelButton: true,
        confirmButtonText: 'Add Provider',
        preConfirm: () => {
            const form = document.getElementById('add-provider-form');
            const formData = new FormData(form);
            return fetch(`${API_BASE_URL}save_hmo_provider.php`, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message || 'Failed to add provider');
                }
                return data;
            });
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire('Success!', 'HMO Provider added successfully.', 'success');
            loadHMOProviders();
        }
    });
}

function showAddPlanModal() {
    // Implementation for add plan modal
    Swal.fire({
        title: 'Add HMO Plan',
        html: `
            <form id="add-plan-form" class="space-y-4">
                <div>
                    <label for="plan-name" class="block text-sm font-medium text-gray-700 mb-1">Plan Name</label>
                    <input type="text" id="plan-name" name="name" required class="w-full p-2 border border-gray-300 rounded-md">
                </div>
                <div>
                    <label for="plan-provider" class="block text-sm font-medium text-gray-700 mb-1">Provider</label>
                    <select id="plan-provider" name="provider_id" required class="w-full p-2 border border-gray-300 rounded-md">
                        <option value="">Loading providers...</option>
                    </select>
                </div>
                <div>
                    <label for="plan-premium" class="block text-sm font-medium text-gray-700 mb-1">Monthly Premium (₱)</label>
                    <input type="number" id="plan-premium" name="premium_amount" step="0.01" min="0" required class="w-full p-2 border border-gray-300 rounded-md">
                </div>
                <div>
                    <label for="plan-description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea id="plan-description" name="description" rows="3" class="w-full p-2 border border-gray-300 rounded-md"></textarea>
                </div>
            </form>
        `,
        showCancelButton: true,
        confirmButtonText: 'Add Plan',
        didOpen: () => {
            // Load providers for dropdown
            fetch(`${API_BASE_URL}get_hmo_providers.php`)
                .then(response => response.json())
                .then(data => {
                    const select = document.getElementById('plan-provider');
                    if (data.success && data.providers) {
                        select.innerHTML = '<option value="">Select provider...</option>' +
                            data.providers.map(p => `<option value="${p.id}">${p.name}</option>`).join('');
                    }
                });
        },
        preConfirm: () => {
            const form = document.getElementById('add-plan-form');
            const formData = new FormData(form);
            return fetch(`${API_BASE_URL}save_hmo_plan.php`, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message || 'Failed to add plan');
                }
                return data;
            });
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire('Success!', 'HMO Plan added successfully.', 'success');
            loadHMOPlans();
        }
    });
}

function showAddEnrollmentModal() {
    // Implementation for add enrollment modal
    Swal.fire({
        title: 'Add HMO Enrollment',
        html: `
            <form id="add-enrollment-form" class="space-y-4">
                <div>
                    <label for="enrollment-employee" class="block text-sm font-medium text-gray-700 mb-1">Employee</label>
                    <select id="enrollment-employee" name="employee_id" required class="w-full p-2 border border-gray-300 rounded-md">
                        <option value="">Loading employees...</option>
                    </select>
                </div>
                <div>
                    <label for="enrollment-plan" class="block text-sm font-medium text-gray-700 mb-1">HMO Plan</label>
                    <select id="enrollment-plan" name="plan_id" required class="w-full p-2 border border-gray-300 rounded-md">
                        <option value="">Loading plans...</option>
                    </select>
                </div>
                <div>
                    <label for="enrollment-date" class="block text-sm font-medium text-gray-700 mb-1">Enrollment Date</label>
                    <input type="date" id="enrollment-date" name="enrollment_date" required class="w-full p-2 border border-gray-300 rounded-md">
                </div>
            </form>
        `,
        showCancelButton: true,
        confirmButtonText: 'Add Enrollment',
        didOpen: () => {
            // Load employees and plans
            fetch(`${API_BASE_URL}get_users.php`)
                .then(response => response.json())
                .then(data => {
                    const select = document.getElementById('enrollment-employee');
                    if (data.success && data.users) {
                        select.innerHTML = '<option value="">Select employee...</option>' +
                            data.users.map(u => `<option value="${u.id}">${u.full_name}</option>`).join('');
                    }
                });

            fetch(`${API_BASE_URL}get_hmo_plans.php`)
                .then(response => response.json())
                .then(data => {
                    const select = document.getElementById('enrollment-plan');
                    if (data.success && data.plans) {
                        select.innerHTML = '<option value="">Select plan...</option>' +
                            data.plans.map(p => `<option value="${p.id}">${p.name} (${p.provider_name})</option>`).join('');
                    }
                });
        },
        preConfirm: () => {
            const form = document.getElementById('add-enrollment-form');
            const formData = new FormData(form);
            return fetch(`${API_BASE_URL}save_hmo_enrollment.php`, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message || 'Failed to add enrollment');
                }
                return data;
            });
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire('Success!', 'HMO Enrollment added successfully.', 'success');
            loadHMOEnrollments();
        }
    });
}

async function handleSubmitHMOClaim(event) {
    event.preventDefault();

    const form = event.target;
    const formData = new FormData(form);

    Swal.fire({
        title: 'Submitting Claim...',
        text: 'Please wait while we process your HMO claim.',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    try {
        // First, get the employee's HMO enrollment ID
        const enrollmentResponse = await fetch(`${API_BASE_URL}get_employee_hmo_benefits.php`, {
            credentials: 'include'
        });
        const enrollmentData = await enrollmentResponse.json();

        if (!enrollmentData.success || !enrollmentData.benefits || enrollmentData.benefits.length === 0) {
            throw new Error('You are not enrolled in an active HMO plan. Please contact HR for enrollment.');
        }

        const activeEnrollment = enrollmentData.benefits.find(benefit => benefit.Status === 'Active');
        if (!activeEnrollment) {
            throw new Error('You do not have an active HMO enrollment. Please contact HR.');
        }

        // Add enrollment ID to form data
        formData.append('enrollmentId', activeEnrollment.EnrollmentID);

        // Submit the claim
        const response = await fetch(`${API_BASE_URL}submit_hmo_claim.php`, {
            method: 'POST',
            body: formData,
            credentials: 'include'
        });

        const data = await response.json();

        if (data.success) {
            Swal.fire({
                title: 'Success!',
                text: `Claim submitted successfully! Claim Number: ${data.claimNumber}`,
                icon: 'success',
                confirmButtonText: 'OK'
            });
            form.reset();
            // Optionally reload the claims list if on the claims page
            if (typeof loadEmployeeHMOClaims === 'function') {
                loadEmployeeHMOClaims();
            }
        } else {
            throw new Error(data.message || 'Failed to submit claim');
        }
    } catch (error) {
        console.error('Error submitting HMO claim:', error);
        Swal.fire({
            title: 'Error!',
            text: error.message,
            icon: 'error',
            confirmButtonText: 'OK'
        });
    }
}

// --- Global Functions for Inline Event Handlers ---
window.editProvider = (id) => {
    // Implementation for editing provider
    console.log('Edit provider:', id);
};

window.deleteProvider = (id) => {
    // Implementation for deleting provider
    console.log('Delete provider:', id);
};

window.editPlan = (id) => {
    // Implementation for editing plan
    console.log('Edit plan:', id);
};

window.deletePlan = (id) => {
    // Implementation for deleting plan
    console.log('Delete plan:', id);
};

window.editEnrollment = (id) => {
    // Implementation for editing enrollment
    console.log('Edit enrollment:', id);
};

window.terminateEnrollment = (id) => {
    // Implementation for terminating enrollment
    console.log('Terminate enrollment:', id);
};

window.approveClaim = (id) => {
    // Implementation for approving claim
    console.log('Approve claim:', id);
};

window.rejectClaim = (id) => {
    // Implementation for rejecting claim
    console.log('Reject claim:', id);
};

window.viewClaimDetails = (id) => {
    // Implementation for viewing claim details
    console.log('View claim details:', id);
};

window.viewEmployeeClaimDetails = async (claimId) => {
    try {
        const response = await fetch(`${API_BASE_URL}get_employee_hmo_claims.php?claim_id=${claimId}`, {
            credentials: 'include'
        });
        const data = await response.json();

        if (data.success && data.claims && data.claims.length > 0) {
            const claim = data.claims[0];
            Swal.fire({
                title: 'Claim Details',
                html: `
                    <div class="space-y-4 text-left">
                        <div><strong>Claim Type:</strong> ${claim.ClaimType || 'N/A'}</div>
                        <div><strong>Provider:</strong> ${claim.ProviderName || 'N/A'}</div>
                        <div><strong>Plan:</strong> ${claim.PlanName || 'N/A'}</div>
                        <div><strong>Description:</strong> ${claim.Description || 'No description'}</div>
                        <div><strong>Amount:</strong> ₱${parseFloat(claim.Amount || 0).toFixed(2)}</div>
                        <div><strong>Claim Date:</strong> ${new Date(claim.ClaimDate).toLocaleDateString()}</div>
                        <div><strong>Submitted Date:</strong> ${new Date(claim.SubmittedDate).toLocaleDateString()}</div>
                        ${claim.ApprovedDate ? `<div><strong>Approved Date:</strong> ${new Date(claim.ApprovedDate).toLocaleDateString()}</div>` : ''}
                        <div><strong>Status:</strong> 
                            <span class="px-2 py-1 rounded text-xs ${
                                claim.Status === 'Approved' ? 'bg-green-100 text-green-800' :
                                claim.Status === 'Rejected' ? 'bg-red-100 text-red-800' :
                                'bg-yellow-100 text-yellow-800'
                            }">
                                ${claim.Status || 'Pending'}
                            </span>
                        </div>
                        ${claim.Comments ? `<div><strong>Comments:</strong> ${claim.Comments}</div>` : ''}
                    </div>
                `,
                icon: 'info',
                confirmButtonText: 'Close'
            });
        } else {
            Swal.fire('Error', 'Claim details not found.', 'error');
        }
    } catch (error) {
        console.error('Error loading claim details:', error);
        Swal.fire('Error', 'Failed to load claim details.', 'error');
    }
};
