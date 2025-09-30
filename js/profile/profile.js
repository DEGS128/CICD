/**
 * User Profile Module
 * Handles display and editing of the logged-in user's profile information.
 * v1.6 - Made address and emergency contact details editable by the user.
 * v1.5 - Added more fields to profile view (personal, address, emergency, photo)
 * - Made PersonalEmail editable.
 * - Display Direct Manager.
 * v1.4 - Standardized SweetAlert usage.
 * v1.3 - Added Change Password functionality with 2FA.
 * v1.2 - Added display for Performance Summary from HR 1-2 (simulated).
 * v1.1 - Added Edit Profile functionality.
 */
import { API_BASE_URL } from '../utils.js';

// --- DOM Element References ---
let pageTitleElement;
let mainContentArea;
let currentProfileData = null;

/**
 * Initializes common elements used by the profile module.
 */
function initializeProfileElements() {
    pageTitleElement = document.getElementById('page-title');
    mainContentArea = document.getElementById('main-content-area');
    if (!pageTitleElement || !mainContentArea) {
        console.error("User Profile Module: Core DOM elements (page-title or main-content-area) not found!");
        return false;
    }
    return true;
}

/**
 * Fetches and displays the logged-in user's profile.
 */
export async function displayUserProfileSection() {
    console.log("[Display] Displaying User Profile Section...");
    if (!initializeProfileElements()) return;

    pageTitleElement.textContent = 'My Profile';
    mainContentArea.innerHTML = '<p class="text-center py-4 text-gray-500">Loading profile information...</p>';

    if (!window.currentUser || !window.currentUser.employee_id) {
        mainContentArea.innerHTML = '<p class="text-red-500 p-4">Error: Could not load profile. User information is missing. Please log in again.</p>';
        console.error("User profile display error: window.currentUser or employee_id is missing.");
        return;
    }

    try {
        const response = await fetch(`${API_BASE_URL}get_user_profile.php`);
        if (!response.ok) {
            const errorResult = await response.json().catch(() => ({ error: `HTTP error! Status: ${response.status}` }));
            throw new Error(errorResult.error || `HTTP error! Status: ${response.status}`);
        }
        currentProfileData = await response.json();
        if (currentProfileData.error) {
            throw new Error(currentProfileData.error);
        }
        renderUserProfile(currentProfileData, false, false);

    } catch (error) {
        console.error('Error loading user profile:', error);
        mainContentArea.innerHTML = `<p class="text-red-500 p-4 text-center">Could not load profile information. ${error.message}</p>`;
        currentProfileData = null;
    }
}

/**
 * Renders the user profile data into the main content area.
 * @param {object} data - The profile data.
 * @param {boolean} [isEditMode=false] - Whether to render in edit mode.
 * @param {boolean} [isChangePasswordMode=false] - Whether to render the change password form.
 */
function renderUserProfile(data, isEditMode = false, isChangePasswordMode = false) {
    const S = (value, placeholder = 'N/A') => value || placeholder;
    const webRootPath = '/hr34/'; // Ensure this matches your web server's root for the app

    let performanceSummaryHtml = '';
    if (data.performance_summary_hr12) {
        const ps = data.performance_summary_hr12;
        performanceSummaryHtml = `
            <div class="mt-6 pt-6 border-t border-gray-200">
                <h4 class="font-semibold text-gray-700 mb-2 font-header">Performance Summary (from HR 1-2)</h4>
                <div class="space-y-1 text-sm">
                    <p><strong class="text-gray-600 w-36 inline-block">Last Review Date:</strong> ${S(ps.last_review_date)}</p>
                    <p><strong class="text-gray-600 w-36 inline-block">Last Review Period:</strong> ${S(ps.last_review_period)}</p>
                    <p><strong class="text-gray-600 w-36 inline-block">Overall Rating:</strong> ${S(ps.overall_rating)}</p>
                    <p><strong class="text-gray-600 w-36 inline-block">Summary Comment:</strong></p>
                    <p class="text-gray-700 pl-4 italic text-xs">${S(ps.summary_comment, 'No comments.')}</p>
                </div>
            </div>
        `;
    }

    let profilePhotoHtml = `
        <span class="inline-flex items-center justify-center h-24 w-24 rounded-full bg-[#594423] text-white text-4xl font-semibold">
            ${S(data.FirstName, '').charAt(0)}${S(data.LastName, '').charAt(0)}
        </span>`;
    if (data.EmployeePhotoPath) {
        const photoUrl = data.EmployeePhotoPath.startsWith('http') ? data.EmployeePhotoPath : `${webRootPath}${data.EmployeePhotoPath}`;
        profilePhotoHtml = `<img src="${S(photoUrl)}" alt="Profile Photo" class="h-24 w-24 rounded-full object-cover border-2 border-[#594423]">`;
    }

    // Helper to create input or text
    const createField = (label, id, name, value, type = 'text', placeholder = '', isEditable = isEditMode) => {
        const displayValue = S(value, placeholder);
        if (isEditable) {
            return `
                <div class="mb-2">
                    <label for="${id}" class="text-gray-600 w-32 inline-block font-medium">${label}:</label>
                    <input type="${type}" id="${id}" name="${name}" value="${S(value, '')}" placeholder="${placeholder}" class="form-input mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                </div>`;
        }
        if (type === 'email' && displayValue !== placeholder) {
             return `<p><strong class="text-gray-600 w-32 inline-block">${label}:</strong> <a href="mailto:${displayValue}" class="text-blue-600 hover:underline">${displayValue}</a></p>`;
        }
        return `<p><strong class="text-gray-600 w-32 inline-block">${label}:</strong> ${displayValue}</p>`;
    };


    let profileContentHtml = `
        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6 text-sm">
            <div>
                <h4 class="font-semibold text-gray-700 mb-2 font-header">Personal Information</h4>
                 <p><strong class="text-gray-600 w-32 inline-block">Date of Birth:</strong> ${S(data.DateOfBirthFormatted)}</p>
                 <p><strong class="text-gray-600 w-32 inline-block">Gender:</strong> ${S(data.Gender)}</p>
                 <p><strong class="text-gray-600 w-32 inline-block">Marital Status:</strong> ${S(data.MaritalStatus)}</p>
                 <p><strong class="text-gray-600 w-32 inline-block">Nationality:</strong> ${S(data.Nationality)}</p>
            </div>
            
            <div>
                <h4 class="font-semibold text-gray-700 mb-2 font-header">Contact Information</h4>
                ${createField('Work Email', 'profile-email', 'email', data.EmployeeEmail, 'email', 'your.work@example.com', isEditMode)}
                ${createField('Personal Email', 'profile-personal-email', 'personal_email', data.PersonalEmail, 'email', 'your.personal@example.com', isEditMode)}
                ${createField('Phone', 'profile-phone', 'phone_number', data.PhoneNumber, 'tel', 'e.g., 09123456789', isEditMode)}
            </div>

            <div>
                <h4 class="font-semibold text-gray-700 mb-2 font-header">Address</h4>
                ${createField('Address Line 1', 'profile-address1', 'address_line1', data.AddressLine1, 'text', 'Street, Building No.', isEditMode)}
                ${createField('Address Line 2', 'profile-address2', 'address_line2', data.AddressLine2, 'text', 'Village, Subdivision', isEditMode)}
                ${createField('City', 'profile-city', 'city', data.City, 'text', 'e.g., Quezon City', isEditMode)}
                ${createField('State/Province', 'profile-state', 'state_province', data.StateProvince, 'text', 'e.g., Metro Manila', isEditMode)}
                ${createField('Postal Code', 'profile-postal', 'postal_code', data.PostalCode, 'text', 'e.g., 1101', isEditMode)}
                ${createField('Country', 'profile-country', 'country', data.Country, 'text', 'e.g., Philippines', isEditMode)}
            </div>

            <div>
                <h4 class="font-semibold text-gray-700 mb-2 font-header">Emergency Contact</h4>
                ${createField('Contact Name', 'profile-emergency-name', 'emergency_contact_name', data.EmergencyContactName, 'text', 'Full Name', isEditMode)}
                ${createField('Relationship', 'profile-emergency-rel', 'emergency_contact_relationship', data.EmergencyContactRelationship, 'text', 'e.g., Spouse, Parent', isEditMode)}
                ${createField('Contact Phone', 'profile-emergency-phone', 'emergency_contact_phone', data.EmergencyContactPhone, 'tel', 'e.g., 09123456789', isEditMode)}
            </div>
            
            <div class="space-y-1">
                <h4 class="font-semibold text-gray-700 mb-2 font-header">Employment Details</h4>
                <p><strong class="text-gray-600 w-32 inline-block">Employee ID:</strong> ${S(data.EmployeeID)}</p>
                <p><strong class="text-gray-600 w-32 inline-block">Hire Date:</strong> ${S(data.HireDateFormatted)}</p>
                <p><strong class="text-gray-600 w-32 inline-block">Department Manager:</strong> ${S(data.DepartmentManagerName)}</p>
                <p><strong class="text-gray-600 w-32 inline-block">Direct Manager:</strong> ${S(data.DirectManagerName)}</p>
                <p><strong class="text-gray-600 w-32 inline-block">Status:</strong> <span class="${data.EmployeeIsActive == 1 ? 'text-green-600 font-semibold' : 'text-red-600 font-semibold'}">${data.EmployeeIsActive == 1 ? 'Active' : 'Inactive'}</span></p>
            </div>

            <div class="space-y-1">
                <h4 class="font-semibold text-gray-700 mb-2 font-header">Account Information</h4>
                <p><strong class="text-gray-600 w-32 inline-block">Username:</strong> ${S(data.Username)}</p>
                <p><strong class="text-gray-600 w-32 inline-block">Role:</strong> ${S(data.RoleName)}</p>
                <p><strong class="text-gray-600 w-32 inline-block">2FA Enabled:</strong> <span class="${data.IsTwoFactorEnabled == 1 ? 'text-green-600 font-semibold' : 'text-gray-600'}">${data.IsTwoFactorEnabled == 1 ? 'Yes' : 'No'}</span></p>
            </div>

            ${data.BaseSalary || data.BaseSalaryFormatted ? `
            <div class="space-y-1">
                <h4 class="font-semibold text-gray-700 mb-2 font-header">Compensation</h4>
                <p><strong class="text-gray-600 w-32 inline-block">Base Salary:</strong> ${S(data.BaseSalaryFormatted)} (${S(data.PayFrequency)})</p>
                ${data.PayFrequency === 'Hourly' && data.PayRateFormatted && data.PayRateFormatted !== '-' ? `<p><strong class="text-gray-600 w-32 inline-block">Hourly Rate:</strong> ${S(data.PayRateFormatted)}</p>` : ''}
                <p><strong class="text-gray-600 w-32 inline-block">Effective:</strong> ${S(data.SalaryEffectiveDateFormatted)}</p>
            </div>` : ''}

            <div class="md:col-span-2" id="performance-summary-section-container">
                ${performanceSummaryHtml}
            </div>
        </div>
    `;

    let changePasswordFormHtml = '';
    if (isChangePasswordMode) {
        profileContentHtml = '';
        changePasswordFormHtml = `
            <div id="change-password-section" class="mt-6 pt-6 border-t border-gray-200">
                <h3 class="text-xl font-semibold text-[#4E3B2A] mb-4 font-header">Change Password</h3>
                <form id="change-password-form" class="space-y-4">
                    <div>
                        <label for="current-password" class="block text-sm font-medium text-gray-700">Current Password:</label>
                        <input type="password" id="current-password" name="current_password" required class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                    </div>
                    <div>
                        <label for="new-password" class="block text-sm font-medium text-gray-700">New Password:</label>
                        <input type="password" id="new-password" name="new_password" required class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                        <p class="text-xs text-gray-500 mt-1">Minimum 8 characters.</p>
                    </div>
                    <div>
                        <label for="confirm-new-password" class="block text-sm font-medium text-gray-700">Confirm New Password:</label>
                        <input type="password" id="confirm-new-password" name="confirm_new_password" required class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                    </div>
                    
                    ${data.IsTwoFactorEnabled == 1 ? `
                    <div id="2fa-section-password-change" class="hidden mt-4 pt-4 border-t border-dashed">
                        <p class="text-sm text-blue-600 mb-2">A 2FA code is required to change your password.</p>
                        <div>
                            <label for="2fa-code-password-change" class="block text-sm font-medium text-gray-700">2FA Code:</label>
                            <div class="flex items-center space-x-2">
                                <input type="text" id="2fa-code-password-change" name="two_fa_code" inputmode="numeric" pattern="[0-9]*" maxlength="6" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                                <button type="button" id="request-2fa-code-btn" class="px-3 py-2 bg-blue-500 text-white text-sm rounded-md hover:bg-blue-600 whitespace-nowrap">Send Code</button>
                            </div>
                            <span id="2fa-code-status" class="text-xs text-gray-500 mt-1"></span>
                        </div>
                    </div>
                    ` : ''}

                    <div class="pt-2 space-x-3">
                        <button type="submit" id="save-new-password-button" class="px-6 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition duration-150 ease-in-out">
                            Change Password
                        </button>
                        <button type="button" id="cancel-change-password-button" class="px-6 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition duration-150 ease-in-out">
                            Cancel
                        </button>
                    </div>
                    </form>
            </div>
        `;
    }

    const fullName = `${S(data.FirstName)} ${S(data.MiddleName)} ${S(data.LastName)} ${S(data.Suffix)}`.replace(/\s+/g, ' ').trim();

    const content = `
        <div class="bg-white p-6 md:p-8 rounded-lg shadow-lg border border-[#F7E6CA] max-w-4xl mx-auto">
            <div class="flex flex-col md:flex-row items-center md:items-start md:space-x-8 mb-8 pb-6 border-b border-gray-200">
                <div class="flex-shrink-0 mb-4 md:mb-0">
                    ${profilePhotoHtml}
                </div>
                <div>
                    <h2 class="text-3xl font-bold text-[#4E3B2A] font-header">${fullName}</h2>
                    <p class="text-lg text-gray-600">${S(data.JobTitle)}</p>
                    <p class="text-sm text-gray-500">${S(data.DepartmentName)}</p>
                </div>
            </div>

            <form id="profile-edit-form" class="${isChangePasswordMode ? 'hidden' : ''}">
                ${profileContentHtml}
                <div class="mt-8 pt-6 border-t border-gray-200 text-center space-x-3">
                    ${isEditMode ? `
                        <button type="submit" id="save-profile-button" class="px-6 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition duration-150 ease-in-out">
                            Save Changes
                        </button>
                        <button type="button" id="cancel-edit-profile-button" class="px-6 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition duration-150 ease-in-out">
                            Cancel
                        </button>
                    ` : `
                        <button type="button" id="edit-profile-button" class="px-6 py-2 bg-[#594423] text-white rounded-md hover:bg-[#4E3B2A] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#594423] transition duration-150 ease-in-out">
                            Edit Profile
                        </button>
                        <button type="button" id="change-password-mode-button" class="px-6 py-2 bg-orange-500 text-white rounded-md hover:bg-orange-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 transition duration-150 ease-in-out">
                            Change Password
                        </button>
                    `}
                </div>
                </form>
            ${changePasswordFormHtml}
        </div>
    `;
    mainContentArea.innerHTML = content;

    if (isEditMode && !isChangePasswordMode) {
        document.getElementById('profile-edit-form')?.addEventListener('submit', handleUpdateProfile);
        document.getElementById('cancel-edit-profile-button')?.addEventListener('click', () => {
            if (currentProfileData) renderUserProfile(currentProfileData, false, false);
        });
    } else if (isChangePasswordMode) {
        document.getElementById('change-password-form')?.addEventListener('submit', handleChangePasswordSubmit);
        document.getElementById('cancel-change-password-button')?.addEventListener('click', () => {
            if (currentProfileData) renderUserProfile(currentProfileData, false, false);
        });
        if (data.IsTwoFactorEnabled == 1) {
            document.getElementById('request-2fa-code-btn')?.addEventListener('click', handleRequest2FACodeForPasswordChange);
        }
    } else {
        document.getElementById('edit-profile-button')?.addEventListener('click', () => {
            if (currentProfileData) renderUserProfile(currentProfileData, true, false);
        });
        document.getElementById('change-password-mode-button')?.addEventListener('click', () => {
            if (currentProfileData) renderUserProfile(currentProfileData, false, true);
        });
    }
}

/**
 * Handles the submission of the profile update form.
 */
async function handleUpdateProfile(event) {
    event.preventDefault();
    const form = event.target;
    const submitButton = document.getElementById('save-profile-button');
    if (submitButton) submitButton.disabled = true;

    Swal.fire({
        title: 'Processing...',
        text: 'Saving your changes, please wait.',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    const formData = {
        email: form.elements['email'].value, // Work Email
        personal_email: form.elements['personal_email'].value,
        phone_number: form.elements['phone_number'].value,
        address_line1: form.elements['address_line1'].value,
        address_line2: form.elements['address_line2'].value,
        city: form.elements['city'].value,
        state_province: form.elements['state_province'].value,
        postal_code: form.elements['postal_code'].value,
        country: form.elements['country'].value,
        emergency_contact_name: form.elements['emergency_contact_name'].value,
        emergency_contact_relationship: form.elements['emergency_contact_relationship'].value,
        emergency_contact_phone: form.elements['emergency_contact_phone'].value
    };

    try {
        const response = await fetch(`${API_BASE_URL}update_user_profile.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        });

        const result = await handleApiResponse(response);

        if (result.error) { 
            throw new Error(result.error);
        }

        Swal.fire({
            icon: 'success',
            title: 'Profile Updated!',
            text: result.message || 'Your profile has been updated successfully!',
            confirmButtonColor: '#4E3B2A',
            timer: 2000
        });
        await displayUserProfileSection();

    } catch (error) {
        console.error('Error updating profile:', error);
        let displayMessage = `Error: ${error.message}`;
        if (error.details) { // Assuming error object might have a details property
             displayMessage += ` Details: ${JSON.stringify(error.details)}`;
        }
        Swal.fire({
            icon: 'error',
            title: 'Update Failed',
            text: displayMessage,
            confirmButtonColor: '#4E3B2A'
        });
    } finally {
        if (submitButton) submitButton.disabled = false;
        if (Swal.isLoading()) { Swal.close(); }
    }
}


/**
 * Handles the "Send Code" button click for 2FA during password change.
 */
async function handleRequest2FACodeForPasswordChange() {
    const statusSpan = document.getElementById('2fa-code-status');
    if (!statusSpan) return;

    statusSpan.textContent = 'Sending 2FA code...';
    statusSpan.className = 'text-xs text-blue-600 mt-1';
    const requestBtn = document.getElementById('request-2fa-code-btn');
    if (requestBtn) requestBtn.disabled = true;

    const swalLoading = Swal.fire({
        title: 'Sending Code...',
        text: 'Please wait.',
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => { Swal.showLoading(); }
    });

    try {
        const response = await fetch(`${API_BASE_URL}request_2fa_code.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ context: 'password_change' })
        });
        const result = await handleApiResponse(response);
        swalLoading.close();

        if (result.error) {
            throw new Error(result.error);
        }
        statusSpan.textContent = result.message || '2FA code sent to your email.';
        statusSpan.className = 'text-xs text-green-600 mt-1';
        Swal.fire({
            icon: 'success',
            title: 'Code Sent!',
            text: result.message || 'A 2FA code has been sent to your email.',
            confirmButtonColor: '#4E3B2A',
            timer: 3000
        });

    } catch (error) {
        swalLoading.close();
        console.error('Error requesting 2FA code:', error);
        statusSpan.textContent = `Error: ${error.message}`;
        statusSpan.className = 'text-xs text-red-600 mt-1';
        Swal.fire({
            icon: 'error',
            title: 'Failed to Send Code',
            text: `Error: ${error.message}`,
            confirmButtonColor: '#4E3B2A'
        });
    } finally {
        if (requestBtn) requestBtn.disabled = false;
    }
}


/**
 * Handles the submission of the change password form.
 */
async function handleChangePasswordSubmit(event) {
    event.preventDefault();
    const form = event.target;
    const savePasswordBtn = document.getElementById('save-new-password-button');

    const currentPassword = form.elements['current-password'].value;
    const newPassword = form.elements['new-password'].value;
    const confirmNewPassword = form.elements['confirm-new-password'].value;
    const twoFaCodeInput = form.elements['2fa-code-password-change'];
    const twoFaCode = twoFaCodeInput ? twoFaCodeInput.value : null;

    if (newPassword.length < 8) {
        Swal.fire('Validation Error', 'New password must be at least 8 characters long.', 'warning');
        return;
    }
    if (newPassword !== confirmNewPassword) {
        Swal.fire('Validation Error', 'New passwords do not match.', 'warning');
        return;
    }

    Swal.fire({
        title: 'Processing...',
        text: 'Changing your password, please wait.',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
    if (savePasswordBtn) savePasswordBtn.disabled = true;

    const payload = {
        current_password: currentPassword,
        new_password: newPassword
    };

    if (currentProfileData && currentProfileData.IsTwoFactorEnabled == 1) {
        const twoFaSection = document.getElementById('2fa-section-password-change');
        if (twoFaSection) twoFaSection.classList.remove('hidden');

        if (!twoFaCode) {
            Swal.fire({
                icon: 'info',
                title: '2FA Required',
                text: 'Please enter the 2FA code sent to your email to proceed.',
                confirmButtonColor: '#4E3B2A'
            });
            if (savePasswordBtn) savePasswordBtn.disabled = false;
            return;
        }
        payload.two_fa_code = twoFaCode;
    }

    try {
        const response = await fetch(`${API_BASE_URL}change_password.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const result = await handleApiResponse(response);

        if (result.error) {
            throw new Error(result.error);
        }

        Swal.fire({
            icon: 'success',
            title: 'Password Changed!',
            text: result.message || 'Your password has been changed successfully!',
            confirmButtonColor: '#4E3B2A',
            timer: 2500
        });
        form.reset();
        setTimeout(async () => {
            await displayUserProfileSection();
        }, 2500);


    } catch (error) {
        console.error('Error changing password:', error);
        Swal.fire({
            icon: 'error',
            title: 'Change Password Failed',
            text: `Error: ${error.message}`,
            confirmButtonColor: '#4E3B2A'
        });
    } finally {
        if (savePasswordBtn) savePasswordBtn.disabled = false;
        if (Swal.isLoading()) { Swal.close(); }
    }
}


/**
 * Handles API response, checking status and parsing JSON.
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
                errorPayload.error += ` (Non-JSON error response received: ${errorTextUnparsed.substring(0, 100)})`;
            }
        } else {
            const errorText = await response.text().catch(() => "Could not read error text.");
            console.error("Non-JSON error response received:", errorText.substring(0, 500));
            errorPayload.error = `Server returned non-JSON response (Status: ${response.status}). Response: ${errorText.substring(0, 100)}`;
        }
        const error = new Error(errorPayload.error);
        error.details = errorPayload.details;
        throw error;
    }

    try {
        if (response.status === 204) {
            return { message: "Operation completed successfully (No Content)." };
        }
        const text = await response.text();
        if (!text || !text.trim()) {
            return { message: "Operation completed successfully (Empty Response Body)." };
        }
        try {
            data = JSON.parse(text);
            return data;
        } catch (jsonError) {
            console.warn("Received successful status, but response was non-JSON text:", text.substring(0, 200));
            return { message: "Operation completed, but response format was unexpected.", raw_response: text };
        }
    } catch (e) {
        console.error("Error processing successful response body:", e);
        throw new Error("Error processing response from server.");
    }
}
