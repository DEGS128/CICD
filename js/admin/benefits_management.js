/**
 * Benefits Management Module
 * Handles benefits categories, benefits, and employee benefits
 */

class BenefitsManagement {
    constructor() {
        this.categories = [];
        this.benefits = [];
        this.employeeBenefits = [];
        this.init();
    }

    init() {
        this.loadCategories();
        this.loadBenefits();
        this.loadEmployeeBenefits();
        this.setupEventListeners();
    }

    setupEventListeners() {
        // Category management
        $(document).on('click', '.add-category-btn', () => this.showAddCategoryModal());
        $(document).on('click', '.edit-category-btn', (e) => this.editCategory($(e.target).data('id')));
        $(document).on('click', '.delete-category-btn', (e) => this.deleteCategory($(e.target).data('id')));

        // Benefit management
        $(document).on('click', '.add-benefit-btn', () => this.showAddBenefitModal());
        $(document).on('click', '.edit-benefit-btn', (e) => this.editBenefit($(e.target).data('id')));
        $(document).on('click', '.delete-benefit-btn', (e) => this.deleteBenefit($(e.target).data('id')));

        // Employee benefits management
        $(document).on('click', '.assign-benefit-btn', () => this.showAssignBenefitModal());
        $(document).on('click', '.remove-benefit-btn', (e) => this.removeEmployeeBenefit($(e.target).data('id')));
    }

    // Category Management
    loadCategories() {
        $.get('php/api/get_benefits_categories.php')
            .done((response) => {
                if (response.success) {
                    this.categories = response.categories;
                    this.renderCategoriesTable();
                } else {
                    this.showError('Failed to load benefits categories');
                }
            })
            .fail(() => this.showError('Error loading benefits categories'));
    }

    renderCategoriesTable() {
        const tbody = $('#benefits-categories-table tbody');
        tbody.empty();

        this.categories.forEach(category => {
            tbody.append(`
                <tr>
                    <td>${category.CategoryName}</td>
                    <td>${category.Description || 'N/A'}</td>
                    <td>${new Date(category.CreatedAt).toLocaleDateString()}</td>
                    <td>
                        <button class="btn btn-sm btn-primary edit-category-btn" data-id="${category.CategoryID}">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-danger delete-category-btn" data-id="${category.CategoryID}">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `);
        });
    }

    showAddCategoryModal() {
        const modal = `
            <div class="modal fade" id="addCategoryModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Add Benefits Category</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form id="addCategoryForm">
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Category Name *</label>
                                    <input type="text" class="form-control" name="categoryName" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea class="form-control" name="description" rows="3"></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Add Category</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;

        $('#modalContainer').html(modal);
        $('#addCategoryModal').modal('show');

        $('#addCategoryForm').on('submit', (e) => {
            e.preventDefault();
            this.addCategory(new FormData(e.target));
        });
    }

    addCategory(formData) {
        $.ajax({
            url: 'php/api/add_benefits_category.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false
        })
        .done((response) => {
            if (response.success) {
                this.showSuccess('Benefits Category added successfully');
                $('#addCategoryModal').modal('hide');
                this.loadCategories();
            } else {
                this.showError(response.error || 'Failed to add category');
            }
        })
        .fail(() => this.showError('Error adding category'));
    }

    // Benefits Management
    loadBenefits() {
        $.get('php/api/get_benefits.php')
            .done((response) => {
                if (response.success) {
                    this.benefits = response.benefits;
                    this.renderBenefitsTable();
                } else {
                    this.showError('Failed to load benefits');
                }
            })
            .fail(() => this.showError('Error loading benefits'));
    }

    renderBenefitsTable() {
        const tbody = $('#benefits-table tbody');
        tbody.empty();

        this.benefits.forEach(benefit => {
            const valueDisplay = this.formatBenefitValue(benefit);
            tbody.append(`
                <tr>
                    <td>${benefit.BenefitName}</td>
                    <td>${benefit.CategoryName}</td>
                    <td>${benefit.BenefitType}</td>
                    <td>${valueDisplay}</td>
                    <td>
                        <span class="badge ${benefit.IsTaxable ? 'badge-warning' : 'badge-info'}">
                            ${benefit.IsTaxable ? 'Taxable' : 'Non-taxable'}
                        </span>
                    </td>
                    <td>
                        <span class="badge ${benefit.IsActive ? 'badge-success' : 'badge-danger'}">
                            ${benefit.IsActive ? 'Active' : 'Inactive'}
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-primary edit-benefit-btn" data-id="${benefit.BenefitID}">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-danger delete-benefit-btn" data-id="${benefit.BenefitID}">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `);
        });
    }

    formatBenefitValue(benefit) {
        if (benefit.BenefitType === 'Monetary') {
            return `₱${parseFloat(benefit.Value).toLocaleString()} ${benefit.Currency}`;
        } else if (benefit.BenefitType === 'Percentage') {
            return `${benefit.Value}%`;
        } else {
            return benefit.Value;
        }
    }

    showAddBenefitModal() {
        // Load categories for dropdown
        const categoryOptions = this.categories.map(c =>
            `<option value="${c.CategoryID}">${c.CategoryName}</option>`
        ).join('');

        const modal = `
            <div class="modal fade" id="addBenefitModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Add Benefit</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form id="addBenefitForm">
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Category *</label>
                                    <select class="form-control" name="categoryId" required>
                                        <option value="">Select Category</option>
                                        ${categoryOptions}
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Benefit Name *</label>
                                    <input type="text" class="form-control" name="benefitName" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea class="form-control" name="description" rows="3"></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Benefit Type *</label>
                                    <select class="form-control" name="benefitType" required>
                                        <option value="Monetary">Monetary</option>
                                        <option value="Percentage">Percentage</option>
                                        <option value="Allowance">Allowance</option>
                                        <option value="Reimbursement">Reimbursement</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Value *</label>
                                    <input type="number" class="form-control" name="value" step="0.01" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Currency</label>
                                    <select class="form-control" name="currency">
                                        <option value="PHP">PHP</option>
                                        <option value="USD">USD</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="isTaxable" id="isTaxable">
                                        <label class="form-check-label" for="isTaxable">
                                            Is Taxable
                                        </label>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Effective Date *</label>
                                    <input type="date" class="form-control" name="effectiveDate" required>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Add Benefit</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;

        $('#modalContainer').html(modal);
        $('#addBenefitModal').modal('show');

        $('#addBenefitForm').on('submit', (e) => {
            e.preventDefault();
            this.addBenefit(new FormData(e.target));
        });
    }

    addBenefit(formData) {
        $.ajax({
            url: 'php/api/add_benefit.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false
        })
        .done((response) => {
            if (response.success) {
                this.showSuccess('Benefit added successfully');
                $('#addBenefitModal').modal('hide');
                this.loadBenefits();
            } else {
                this.showError(response.error || 'Failed to add benefit');
            }
        })
        .fail(() => this.showError('Error adding benefit'));
    }

    // Employee Benefits Management
    loadEmployeeBenefits() {
        // This would load employee benefits data
        // For now, just render an empty table
        this.renderEmployeeBenefitsTable();
    }

    renderEmployeeBenefitsTable() {
        const tbody = $('#employee-benefits-table tbody');
        tbody.empty();

        // Sample data - in real implementation, this would come from API
        tbody.append(`
            <tr>
                <td>John Doe</td>
                <td>Health Insurance</td>
                <td>₱15,000.00</td>
                <td>Active</td>
                <td>
                    <button class="btn btn-sm btn-warning remove-benefit-btn" data-id="1">
                        <i class="fas fa-times"></i>
                    </button>
                </td>
            </tr>
            <tr>
                <td>Jane Smith</td>
                <td>Dental Coverage</td>
                <td>₱5,000.00</td>
                <td>Active</td>
                <td>
                    <button class="btn btn-sm btn-warning remove-benefit-btn" data-id="2">
                        <i class="fas fa-times"></i>
                    </button>
                </td>
            </tr>
        `);
    }

    showAssignBenefitModal() {
        this.showSuccess('Assign benefit modal would be implemented here');
    }

    // Utility Methods
    showSuccess(message) {
        // Show success toast or alert
        alert('Success: ' + message);
    }

    showError(message) {
        // Show error toast or alert
        alert('Error: ' + message);
    }

    editCategory(id) {
        const category = this.categories.find(c => c.CategoryID == id);
        if (category) {
            this.showSuccess(`Edit category: ${category.CategoryName}`);
            // Implement edit modal
        }
    }

    deleteCategory(id) {
        if (confirm('Are you sure you want to delete this benefits category?')) {
            // Implement delete API call
            this.showSuccess('Category deleted successfully');
            this.loadCategories();
        }
    }

    editBenefit(id) {
        const benefit = this.benefits.find(b => b.BenefitID == id);
        if (benefit) {
            this.showSuccess(`Edit benefit: ${benefit.BenefitName}`);
            // Implement edit modal
        }
    }

    deleteBenefit(id) {
        if (confirm('Are you sure you want to delete this benefit?')) {
            // Implement delete API call
            this.showSuccess('Benefit deleted successfully');
            this.loadBenefits();
        }
    }

    removeEmployeeBenefit(id) {
        if (confirm('Are you sure you want to remove this benefit from the employee?')) {
            // Implement remove API call
            this.showSuccess('Benefit removed successfully');
            this.loadEmployeeBenefits();
        }
    }
}

// Initialize Benefits Management when document is ready
$(document).ready(() => {
    if ($('#benefits-management-section').length) {
        new BenefitsManagement();
    }
});
