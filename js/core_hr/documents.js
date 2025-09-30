/**
 * Core HR - Documents Module
 * v2.1 - Integrated SweetAlert for notifications and confirmations.
 * v2.0 - Refined rendering functions for XSS protection.
 */
import { API_BASE_URL, populateEmployeeDropdown } from '../utils.js'; // Import shared functions/constants

/**
 * Displays the Employee Documents section.
 * Sets up the UI for uploading and viewing documents.
 */
export async function displayDocumentsSection() { 
    console.log("[Display] Displaying Documents Section...");
    const pageTitleElement = document.getElementById('page-title');
    const mainContentArea = document.getElementById('main-content-area');

    if (!pageTitleElement || !mainContentArea) {
        console.error("displayDocumentsSection: Core DOM elements not found.");
        if(mainContentArea) mainContentArea.innerHTML = `<p class="text-red-500 p-4">Error initializing document section elements.</p>`;
        return;
    }

    pageTitleElement.textContent = 'Employee Documents';
    mainContentArea.innerHTML = `
        <div class="bg-white p-6 rounded-lg shadow-md border border-[#F7E6CA] space-y-6">
            <div class="border-b border-gray-200 pb-4">
                <h3 class="text-lg font-semibold text-[#4E3B2A] mb-3 font-header">Upload New Document</h3>
                <form id="upload-document-form" class="space-y-4" enctype="multipart/form-data">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="doc-employee-select" class="block text-sm font-medium text-gray-700 mb-1">Employee:</label>
                            <select id="doc-employee-select" name="employee_id" required class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                                <option value="">Loading employees...</option>
                            </select>
                        </div>
                        <div>
                            <label for="doc-type" class="block text-sm font-medium text-gray-700 mb-1">Document Type:</label>
                            <input type="text" id="doc-type" name="document_type" required placeholder="e.g., Contract, ID, Certificate" class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                        </div>
                        <div>
                            <label for="doc-file" class="block text-sm font-medium text-gray-700 mb-1">File:</label>
                            <input type="file" id="doc-file" name="document_file" required class="w-full p-1.5 border border-gray-300 rounded-md shadow-sm text-sm text-gray-700 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-[#F7E6CA] file:text-[#4E3B2A] hover:file:bg-[#EADDCB]">
                            <p class="mt-1 text-xs text-gray-500">Allowed: PDF, DOC, DOCX, JPG, PNG (Max 5MB)</p>
                        </div>
                    </div>
                    <div class="pt-2">
                            <button type="submit" class="px-4 py-2 bg-[#4727ff] text-white rounded-md hover:bg-[#3a1fcc] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#4727ff] transition duration-150 ease-in-out">
                                Upload Document
                            </button>
                        </div>
                </form>
            </div>

            <div>
                <h3 class="text-lg font-semibold text-[#4E3B2A] mb-3 font-header">Existing Documents</h3>
                <div class="flex flex-wrap gap-4 mb-4 items-end">
                     <div>
                       <label for="filter-doc-employee" class="block text-sm font-medium text-gray-700 mb-1">Filter by Employee:</label>
                       <select id="filter-doc-employee" class="w-full sm:w-auto p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]">
                           <option value="">All Employees</option>
                           </select>
                    </div>
                    <div>
                       <button id="filter-doc-btn" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-150 ease-in-out">
                           Filter
                       </button>
                    </div>
                </div>
                <div id="documents-list-container" class="overflow-x-auto">
                    <p class="text-center py-4">Loading documents...</p> 
                </div>
            </div>
        </div>`;

    requestAnimationFrame(async () => {
        await populateEmployeeDropdown('doc-employee-select'); 
        await populateEmployeeDropdown('filter-doc-employee', true); 

        const uploadForm = document.getElementById('upload-document-form');
        if (uploadForm) {
            if (!uploadForm.hasAttribute('data-listener-attached')) {
                uploadForm.addEventListener('submit', handleUploadDocument);
                uploadForm.setAttribute('data-listener-attached', 'true');
            }
        } else {
            console.error("Upload Document form not found after injecting HTML.");
        }

        const filterBtn = document.getElementById('filter-doc-btn');
        if (filterBtn) {
            if (!filterBtn.hasAttribute('data-listener-attached')) {
                filterBtn.addEventListener('click', applyDocumentFilter);
                filterBtn.setAttribute('data-listener-attached', 'true');
            }
        } else {
            console.error("Filter Document button not found after injecting HTML.");
        }
        await loadDocuments();
    });
}

/**
 * Applies the selected employee filter and reloads the document list.
 */
function applyDocumentFilter() {
    const employeeId = document.getElementById('filter-doc-employee')?.value;
    loadDocuments(employeeId); 
}

/**
 * Fetches documents from the API based on the optional employee filter.
 */
async function loadDocuments(employeeId = null) {
    console.log(`[Load] Loading Documents... (Employee ID: ${employeeId || 'All'})`);
    const container = document.getElementById('documents-list-container');
    if (!container) return;
    container.innerHTML = '<p class="text-center py-4">Loading documents...</p>'; 

    let url = `${API_BASE_URL}get_documents.php`;
    if (employeeId) {
        url += `?employee_id=${encodeURIComponent(employeeId)}`;
    }

    try {
        const response = await fetch(url);
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        const documents = await response.json();

        if (documents.error) {
            console.error("Error fetching documents:", documents.error);
            container.innerHTML = `<p class="text-red-500 text-center py-4">Error: ${documents.error}</p>`;
        } else {
            renderDocumentsTable(documents); 
        }
    } catch (error) {
        console.error('Error loading documents:', error);
        container.innerHTML = `<p class="text-red-500 text-center py-4">Could not load documents. ${error.message}</p>`;
    }
}

/**
 * Renders the list of documents into an HTML table.
 */
function renderDocumentsTable(documents) {
    console.log("[Render] Rendering Documents Table...");
    const container = document.getElementById('documents-list-container');
    if (!container) return;
    container.innerHTML = '';

    if (!documents || documents.length === 0) {
        const noDataMessage = document.createElement('p');
        noDataMessage.className = 'text-center py-4 text-gray-500';
        noDataMessage.textContent = 'No documents found for the selected criteria.';
        container.appendChild(noDataMessage);
        return;
    }

    const table = document.createElement('table');
    table.className = 'min-w-full divide-y divide-gray-200 border border-gray-300';

    const thead = table.createTHead();
    thead.className = 'bg-gray-50';
    const headerRow = thead.insertRow();
    const headers = ['Employee', 'Type', 'Filename', 'Uploaded', 'Actions'];
    headers.forEach(text => {
        const th = document.createElement('th');
        th.scope = 'col';
        th.className = 'px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider';
        th.textContent = text; 
        headerRow.appendChild(th);
    });

    const tbody = table.createTBody();
    tbody.className = 'bg-white divide-y divide-gray-200 document-action-container';

    documents.forEach(doc => {
        const row = tbody.insertRow();
        row.id = `doc-row-${doc.DocumentID}`;

        const createCell = (text) => {
            const cell = row.insertCell();
            cell.className = 'px-4 py-3 whitespace-nowrap text-sm';
            cell.textContent = text ?? ''; 
            return cell;
        };

        createCell(doc.EmployeeName).classList.add('font-medium', 'text-gray-900');

        const typeCell = createCell(doc.DocumentType);
        if (!doc.DocumentType) {
            typeCell.innerHTML = '<span class="text-gray-400 italic">N/A</span>'; 
        } else {
            typeCell.classList.add('text-gray-700');
        }

        const filenameCell = row.insertCell();
        filenameCell.className = 'px-4 py-3 whitespace-nowrap text-sm text-gray-700';
        const webRootPath = '/hr34/'; 
        const filePath = doc.FilePath ? `${webRootPath}${doc.FilePath}` : '#';
        const link = document.createElement('a');
        link.href = filePath;
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
        link.className = 'text-blue-600 hover:underline';
        link.title = 'View Document';
        link.textContent = doc.DocumentName || ''; 
        if (!doc.DocumentName) {
            link.innerHTML = '<span class="text-gray-400 italic">N/A</span>'; 
        }
        filenameCell.appendChild(link);

        const uploadDate = doc.UploadDate ? new Date(doc.UploadDate).toLocaleDateString() : 'N/A';
        createCell(uploadDate).classList.add('text-gray-500');

        const actionsCell = row.insertCell();
        actionsCell.className = 'px-4 py-3 whitespace-nowrap text-sm font-medium';
        const deleteButton = document.createElement('button');
        deleteButton.className = 'text-red-600 hover:text-red-800 delete-doc-btn';
        deleteButton.dataset.docId = doc.DocumentID;
        deleteButton.title = 'Delete Document';
        deleteButton.innerHTML = '<i class="fas fa-trash-alt"></i> Delete';
        actionsCell.appendChild(deleteButton);
    });
    container.appendChild(table);
    attachDeleteListeners();
}

/**
 * Attaches click event listeners to all delete document buttons using event delegation.
 */
function attachDeleteListeners() {
    const container = document.querySelector('.document-action-container'); 
    if (container) {
        container.removeEventListener('click', handleDeleteDocumentClick);
        container.addEventListener('click', handleDeleteDocumentClick);
    }
}

/**
 * Handles the click event for a delete document button.
 * Prompts for confirmation using SweetAlert.
 */
async function handleDeleteDocumentClick(event) {
    const button = event.target.closest('.delete-doc-btn'); 
    if (!button) return; 

    const documentId = button.dataset.docId;
    if (!documentId) {
        console.error("Could not find document ID on delete button.");
        Swal.fire('Error', 'Could not identify the document to delete.', 'error');
        return;
    }

    const result = await Swal.fire({
        title: 'Are you sure?',
        text: `Do you want to delete document ID ${documentId}? This action cannot be undone.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!'
    });

    if (result.isConfirmed) {
        deleteDocument(documentId);
    }
}


/**
 * Sends a request to the API to delete a specific document.
 * Uses SweetAlert for feedback.
 */
async function deleteDocument(documentId) {
    console.log(`[Delete] Attempting to delete document ID: ${documentId}`);
    
    Swal.fire({
        title: 'Deleting...',
        text: `Deleting document ${documentId}, please wait.`,
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    try {
        const response = await fetch(`${API_BASE_URL}delete_document.php`, {
            method: 'POST', 
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ document_id: parseInt(documentId) }) 
        });

        const result = await response.json();

        if (!response.ok) {
            throw new Error(result.error || `HTTP error! status: ${response.status}`);
        }

        Swal.fire({
            icon: 'success',
            title: 'Deleted!',
            text: result.message || 'Document deleted successfully!',
            timer: 2000,
            confirmButtonColor: '#4E3B2A'
        });
        await loadDocuments(document.getElementById('filter-doc-employee')?.value);

    } catch (error) {
        console.error('Error deleting document:', error);
        Swal.fire({
            icon: 'error',
            title: 'Deletion Failed',
            text: `Error deleting document: ${error.message}`,
            confirmButtonColor: '#4E3B2A'
        });
    }
}


/**
 * Handles the submission of the document upload form.
 * Uses SweetAlert for feedback.
 */
async function handleUploadDocument(event) {
    event.preventDefault(); 
    const form = event.target;
    const submitButton = form.querySelector('button[type="submit"]');
    const fileInput = document.getElementById('doc-file');

    if (!form || !submitButton || !fileInput) {
         console.error("Upload Document form elements missing.");
         return;
    }

    if (!fileInput.files || fileInput.files.length === 0) {
        Swal.fire('Validation Error', 'Please select a file to upload.', 'warning');
        return;
    }
    const file = fileInput.files[0];
    const maxSize = 5 * 1024 * 1024; // 5MB
    if (file.size > maxSize) {
        Swal.fire('File Too Large', 'File size exceeds the 5MB limit.', 'warning');
        return;
    }
    
    const formData = new FormData(form);

    Swal.fire({
        title: 'Uploading...',
        text: 'Uploading document, please wait.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    submitButton.disabled = true;

    try {
        const response = await fetch(`${API_BASE_URL}upload_document.php`, {
            method: 'POST',
            body: formData 
        });

        const result = await response.json();

        if (!response.ok) {
            if (response.status === 400 && result.details) {
                 const errorMessages = Object.values(result.details).join(' ');
                 throw new Error(errorMessages || result.error || `HTTP error! status: ${response.status}`);
            }
             if ((response.status === 400 || response.status === 500) && result.error && (result.error.includes('upload') || result.error.includes('save'))) {
                 throw new Error(result.error);
             }
            throw new Error(result.error || `HTTP error! status: ${response.status}`);
        }

        Swal.fire({
            icon: 'success',
            title: 'Uploaded!',
            text: result.message || 'Document uploaded successfully!',
            timer: 2000,
            confirmButtonColor: '#4E3B2A'
        });
        form.reset(); 
        await loadDocuments(document.getElementById('filter-doc-employee')?.value);

    } catch (error) {
        console.error('Error uploading document:', error);
        Swal.fire({
            icon: 'error',
            title: 'Upload Failed',
            text: `Upload Error: ${error.message}`,
            confirmButtonColor: '#4E3B2A'
        });
    } finally {
        submitButton.disabled = false; 
        if (Swal.isLoading()) {
            Swal.close();
        }
    }
}
