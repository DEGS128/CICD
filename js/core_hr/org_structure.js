/**
 * Core HR - Organizational Structure Module
 * v3.1 - Renamed exported function to displayOrgStructureSection for consistency.
 * v3.0 - Displays hierarchical structure from DB and allows adding new departments/modules.
 * v2.2 - Simplified Existing Departments table to show only Department Name.
 */
import { API_BASE_URL, populateEmployeeDropdown } from '../utils.js';

let allDepartmentsData = []; // To store fetched departments for parent dropdown

/**
 * Displays the Organizational Structure section.
 */
export async function displayOrgStructureSection() { // Renamed from displayOrgStructure
    console.log("[Display] Displaying Organizational Structure Section (Hierarchical)...");
    const pageTitleElement = document.getElementById('page-title');
    const mainContentArea = document.getElementById('main-content-area');

    if (!pageTitleElement || !mainContentArea) {
        console.error("displayOrgStructureSection: Core DOM elements not found.");
        return;
    }

    pageTitleElement.textContent = 'Manage Organizational Structure';
    mainContentArea.innerHTML = `
        <div class="bg-white p-6 rounded-lg shadow-md border border-[#F7E6CA] space-y-6">
            <div class="border-b border-gray-200 pb-4">
                 <h3 class="text-lg font-semibold text-[#4E3B2A] mb-3 font-header">Add New Department / Module</h3>
                 <form id="add-department-form" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div>
                            <label for="dept-name" class="block text-sm font-medium text-gray-700 mb-1">Name:</label>
                            <input type="text" id="dept-name" name="department_name" required placeholder="e.g., Marketing or Sub-Module" class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                        </div>
                        <div>
                            <label for="dept-parent-select" class="block text-sm font-medium text-gray-700 mb-1">Parent (Optional):</label>
                            <select id="dept-parent-select" name="parent_department_id" class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                                <option value="">-- No Parent (Top Level) --</option>
                            </select>
                        </div>
                         <div>
                            <label for="dept-manager-select" class="block text-sm font-medium text-gray-700 mb-1">Manager (Optional):</label>
                            <select id="dept-manager-select" name="manager_id" class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                                <option value="">-- Select Manager --</option>
                            </select>
                        </div>
                         <div>
                            <label for="dept-icon" class="block text-sm font-medium text-gray-700 mb-1">Icon Class (Optional):</label>
                            <input type="text" id="dept-icon" name="icon" placeholder="e.g., fa-solid fa-users" class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                        </div>
                         <div>
                            <label for="dept-sort-order" class="block text-sm font-medium text-gray-700 mb-1">Sort Order (Optional):</label>
                            <input type="number" id="dept-sort-order" name="sort_order" value="0" class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                        </div>
                        <div class="md:col-span-full">
                            <label for="dept-description" class="block text-sm font-medium text-gray-700 mb-1">Description (Optional):</label>
                            <textarea id="dept-description" name="description" rows="2" placeholder="Brief description..." class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]"></textarea>
                        </div>
                    </div>
                     <div class="pt-2 space-x-3">
                        <button type="submit" class="px-4 py-2 bg-[#4727ff] text-white rounded-md hover:bg-[#3a1fcc] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#4727ff] transition duration-150 ease-in-out">
                            Add Entry
                        </button>
                    </div>
                 </form>
            </div>

            <div>
                <h3 class="text-lg font-semibold text-[#4E3B2A] mb-3 font-header">Organizational Structure</h3>
                <div id="org-structure-display-container" class="space-y-4">
                     <p class="text-center py-4">Loading structure...</p>
                </div>
            </div>
        </div>
    `;
    
    requestAnimationFrame(async () => {
        await populateEmployeeDropdown('dept-manager-select', true);
        
        const addDepartmentForm = document.getElementById('add-department-form');
        if (addDepartmentForm) {
            if (!addDepartmentForm.hasAttribute('data-listener-attached')) {
                addDepartmentForm.addEventListener('submit', handleAddDepartment);
                addDepartmentForm.setAttribute('data-listener-attached', 'true');
            }
        } else {
            console.error("Add Department form not found.");
        }
        await loadAndRenderOrgStructure();
    });
}

/**
 * Fetches org structure data and populates the parent dropdown and renders the structure.
 */
async function loadAndRenderOrgStructure() {
    console.log("[Load] Loading Organizational Structure...");
    const displayContainer = document.getElementById('org-structure-display-container');
    const parentDeptSelect = document.getElementById('dept-parent-select');

    if (!displayContainer || !parentDeptSelect) {
         console.error("Org structure display container or parent select not found!");
         if(displayContainer) displayContainer.innerHTML = '<p class="text-red-500">Error: UI elements missing.</p>';
         return;
    }
    displayContainer.innerHTML = '<p class="text-center py-4">Loading structure...</p>';
    parentDeptSelect.innerHTML = '<option value="">-- No Parent (Top Level) --</option>'; 

    try {
        const response = await fetch(`${API_BASE_URL}get_org_structure.php`);
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        
        const text = await response.text();
        if (!text) {
            allDepartmentsData = [];
        } else {
            try {
                allDepartmentsData = JSON.parse(text);
            } catch (jsonError) {
                console.error("Failed to parse org structure JSON:", jsonError, "Response text:", text);
                throw new Error("Invalid JSON response from server for org structure.");
            }
        }

        if (allDepartmentsData.error) {
             console.error("[loadAndRenderOrgStructure] API returned error:", allDepartmentsData.error);
             displayContainer.innerHTML = `<p class="text-red-500 text-center py-4">Error: ${allDepartmentsData.error}</p>`;
        } else {
            renderHierarchicalStructure(displayContainer, allDepartmentsData);
            // Populate parent department dropdown
            if (allDepartmentsData && allDepartmentsData.length > 0) {
                allDepartmentsData.forEach(dept => {
                    const option = document.createElement('option');
                    option.value = dept.DepartmentID;
                    option.textContent = `${dept.DepartmentName} (ID: ${dept.DepartmentID})`;
                    parentDeptSelect.appendChild(option);
                });
            }
        }
    } catch (error) {
        console.error('[loadAndRenderOrgStructure] Error:', error);
        displayContainer.innerHTML = `<p class="text-red-500 text-center py-4">Could not load organizational structure. ${error.message}</p>`;
        parentDeptSelect.innerHTML = '<option value="" disabled>Error loading options</option>';
    }
}

/**
 * Builds a tree structure from the flat list of departments.
 * @param {Array} list - Flat list of department objects from API.
 * @returns {Array} Hierarchical list of department objects.
 */
function buildTree(list) {
    const map = {};
    const roots = [];
    list.forEach(item => {
        map[item.DepartmentID] = { ...item, children: [] };
    });

    list.forEach(item => {
        if (item.ParentDepartmentID && map[item.ParentDepartmentID]) {
            map[item.ParentDepartmentID].children.push(map[item.DepartmentID]);
        } else {
            roots.push(map[item.DepartmentID]);
        }
    });
    return roots;
}

/**
 * Renders the hierarchical organizational structure.
 * @param {HTMLElement} container - The HTML element to render into.
 * @param {Array} departments - The flat list of department data from API.
 */
function renderHierarchicalStructure(container, departments) {
    container.innerHTML = ''; // Clear loading or previous content

    if (!departments || departments.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-center">No organizational structure defined.</p>';
        return;
    }

    const tree = buildTree(departments);

    const renderNode = (node, level = 0) => {
        const div = document.createElement('div');
        div.className = `p-3 rounded-md border ${level === 0 ? 'bg-gray-100 border-gray-300' : 'bg-white border-gray-200 ml-4 mt-2'}`;
        
        let iconHtml = node.Icon ? `<i class="${node.Icon} text-lg text-[#594423] mr-2"></i>` : '<i class="fa-solid fa-sitemap text-lg text-gray-400 mr-2"></i>';
        
        let managerHtml = node.ManagerName ? `<span class="text-xs text-gray-500 block sm:inline sm:ml-2">(Manager: ${node.ManagerName})</span>` : '';
        let parentHtml = node.ParentDepartmentName ? `<span class="text-xs text-gray-400 block sm:inline sm:ml-2">(Parent: ${node.ParentDepartmentName})</span>` : '';
        let employeeCountHtml = node.EmployeeCount !== undefined ? `<span class="text-xs bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded-full ml-2">${node.EmployeeCount} emp.</span>` : '';


        div.innerHTML = `
            <div class="flex justify-between items-center">
                <h5 class="text-md font-semibold text-[#4E3B2A] flex items-center font-header">
                    ${iconHtml}
                    <span>${node.DepartmentName}</span>
                    ${employeeCountHtml}
                </h5>
                </div>
            ${node.Description ? `<p class="text-xs text-gray-600 mt-1">${node.Description}</p>` : ''}
            <div class="text-xs text-gray-500 mt-1">
                ID: ${node.DepartmentID} ${parentHtml} ${managerHtml} (Sort: ${node.SortOrder || 0})
            </div>
        `;
        
        if (node.children && node.children.length > 0) {
            const childrenContainer = document.createElement('div');
            childrenContainer.className = 'mt-2 pl-4 border-l-2 border-gray-200';
            node.children.sort((a, b) => (a.SortOrder || 0) - (b.SortOrder || 0) || a.DepartmentName.localeCompare(b.DepartmentName)); // Sort children
            node.children.forEach(child => childrenContainer.appendChild(renderNode(child, level + 1)));
            div.appendChild(childrenContainer);
        }
        return div;
    };

    tree.sort((a, b) => (a.SortOrder || 0) - (b.SortOrder || 0) || a.DepartmentName.localeCompare(b.DepartmentName)); // Sort root nodes
    tree.forEach(node => container.appendChild(renderNode(node)));
}


/**
 * Handles the submission of the add department form.
 */
async function handleAddDepartment(event) {
    event.preventDefault();
    const form = event.target;
    const submitButton = form.querySelector('button[type="submit"]');
    if (!submitButton) return;

    const formData = {
        department_name: form.elements['department_name'].value.trim(),
        parent_department_id: form.elements['parent_department_id'].value ? parseInt(form.elements['parent_department_id'].value) : null,
        manager_id: form.elements['manager_id'].value ? parseInt(form.elements['manager_id'].value) : null,
        description: form.elements['description'].value.trim() || null,
        icon: form.elements['icon'].value.trim() || null,
        sort_order: form.elements['sort_order'].value ? parseInt(form.elements['sort_order'].value) : 0
    };

    if (!formData.department_name) {
        Swal.fire({ icon: 'error', title: 'Validation Error', text: 'Department/Module Name is required.', confirmButtonColor: '#4E3B2A' });
        return;
    }

    Swal.fire({
        title: 'Processing...',
        text: 'Adding entry, please wait.',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
    submitButton.disabled = true;

    try {
        const response = await fetch(`${API_BASE_URL}add_department.php`, { // Ensure add_department.php handles these fields
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        });
        
        const resultText = await response.text();
        let result;
        try { result = JSON.parse(resultText); } 
        catch (e) { 
            console.error("Failed to parse add_department response:", resultText);
            throw new Error("Invalid response from server after adding entry.");
        }

        if (!response.ok) {
            throw new Error(result.error || `HTTP error! Status: ${response.status}`);
        }
        
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: result.message || 'Organizational entry added successfully!',
            confirmButtonColor: '#4E3B2A',
            timer: 2000
        });
        form.reset();
        document.getElementById('dept-sort-order').value = 0; // Reset sort order to default
        await loadAndRenderOrgStructure(); // Refresh the list and structure
    } catch (error) {
        console.error('Error adding organizational entry:', error);
        Swal.fire({
            icon: 'error',
            title: 'Operation Failed',
            text: `Error: ${error.message}`,
            confirmButtonColor: '#4E3B2A'
        });
    } finally {
        submitButton.disabled = false;
        if (Swal.isLoading()) { Swal.close(); }
    }
}
