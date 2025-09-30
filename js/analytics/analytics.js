/**
 * Analytics Module
 * Handles display of HR analytics dashboards, reports, and metrics.
 * v2.6 - Implemented actual data display for some metrics.
 */
import { API_BASE_URL } from '../utils.js'; // Import base URL

// --- DOM Element References ---
let pageTitleElement;
let mainContentArea;
let headcountChartInstance = null; 
let leaveTypeChartInstance = null; 
let metricChartInstance = null;

/**
 * Initializes common elements used by the analytics module.
 */
function initializeAnalyticsElements() {
    pageTitleElement = document.getElementById('page-title');
    mainContentArea = document.getElementById('main-content-area');
    if (!pageTitleElement || !mainContentArea) {
        console.error("Analytics Module: Core DOM elements (page-title or main-content-area) not found!");
        return false;
    }
    return true;
}

/**
 * Displays the Analytics Dashboards section.
 */
export async function displayAnalyticsDashboardsSection() {
    console.log("[Display] Displaying Analytics Dashboards Section...");
    if (!initializeAnalyticsElements()) return;
    pageTitleElement.textContent = 'HR Analytics Dashboard';
    mainContentArea.innerHTML = `
        <div class="space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-gradient-to-br from-blue-400 to-blue-600 p-6 rounded-xl shadow-lg text-white"><div class="flex items-center justify-between"><div><p class="text-sm font-medium uppercase tracking-wider">Total Active Employees</p><p class="text-3xl font-bold" id="kpi-total-employees">Loading...</p></div><div class="bg-white/20 p-3 rounded-full"><i class="fas fa-users fa-lg text-white"></i></div></div></div>
                <div class="bg-gradient-to-br from-green-400 to-green-600 p-6 rounded-xl shadow-lg text-white"><div class="flex items-center justify-between"><div><p class="text-sm font-medium uppercase tracking-wider">Approved Leave Days (This Year)</p><p class="text-3xl font-bold" id="kpi-total-leave-days">Loading...</p></div><div class="bg-white/20 p-3 rounded-full"><i class="fas fa-calendar-check fa-lg text-white"></i></div></div></div>
                <div class="bg-gradient-to-br from-purple-400 to-purple-600 p-6 rounded-xl shadow-lg text-white"><div class="flex items-center justify-between"><div><p class="text-sm font-medium uppercase tracking-wider">Last Payroll Cost</p><p class="text-3xl font-bold" id="kpi-total-payroll-cost">Loading...</p><p class="text-xs" id="kpi-payroll-run-id">(Run ID: ...)</p></div><div class="bg-white/20 p-3 rounded-full"><i class="fas fa-money-bill-wave fa-lg text-white"></i></div></div></div>
                <div class="bg-gradient-to-br from-yellow-400 to-yellow-600 p-6 rounded-xl shadow-lg text-white"><div class="flex items-center justify-between"><div><p class="text-sm font-medium uppercase tracking-wider">Avg. Employee Tenure</p><p class="text-3xl font-bold" id="kpi-avg-tenure">Loading...</p></div><div class="bg-white/20 p-3 rounded-full"><i class="fas fa-user-clock fa-lg text-white"></i></div></div></div>
                <div class="bg-gradient-to-br from-red-400 to-red-600 p-6 rounded-xl shadow-lg text-white"><div class="flex items-center justify-between"><div><p class="text-sm font-medium uppercase tracking-wider">Total Leave Types</p><p class="text-3xl font-bold" id="kpi-total-leave-types">Loading...</p></div><div class="bg-white/20 p-3 rounded-full"><i class="fas fa-briefcase fa-lg text-white"></i></div></div></div>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white p-6 rounded-lg shadow-md border border-[#F7E6CA]"><h3 class="text-lg font-semibold text-[#4E3B2A] mb-4 font-header">Staff Distribution by Role</h3><div class="h-72 md:h-80"><canvas id="headcountByDepartmentChart"></canvas></div></div>
                <div class="bg-white p-6 rounded-lg shadow-md border border-[#F7E6CA]"><h3 class="text-lg font-semibold text-[#4E3B2A] mb-4 font-header">Approved Leave Days by Type (Current Year)</h3><div class="h-72 md:h-80"><canvas id="leaveDaysByTypeChart"></canvas></div></div>
            </div>
        </div>`;
    await loadAnalyticsData();
}

async function loadAnalyticsData() {
    const elements = {
        kpiTotalEmployees: document.getElementById('kpi-total-employees'),
        kpiTotalLeaveDays: document.getElementById('kpi-total-leave-days'),
        kpiTotalPayrollCost: document.getElementById('kpi-total-payroll-cost'),
        kpiPayrollRunId: document.getElementById('kpi-payroll-run-id'),
        kpiAvgTenure: document.getElementById('kpi-avg-tenure'),
        kpiTotalLeaveTypes: document.getElementById('kpi-total-leave-types'),
        headcountChartContainer: document.getElementById('headcountByDepartmentChart'),
        leaveTypeChartContainer: document.getElementById('leaveDaysByTypeChart')
    };
    if (Object.values(elements).some(el => !el)) {
        console.error("DOM elements missing for analytics dashboard.");
        if(mainContentArea) mainContentArea.innerHTML = `<p class="text-red-500 p-4">Error rendering dashboard elements.</p>`;
        return;
    }
    try {
        const response = await fetch(`${API_BASE_URL}get_hr_analytics_summary.php`, { credentials: 'include' });
        if (!response.ok) throw new Error(`HTTP error ${response.status}`);
        const data = await response.json();
        if (data.error) throw new Error(data.error);

        elements.kpiTotalEmployees.textContent = data.totalActiveEmployees || '0';
        elements.kpiTotalLeaveDays.textContent = data.totalLeaveDaysRequestedThisYear || '0';
        elements.kpiTotalPayrollCost.textContent = data.totalPayrollCostLastRun ? `₱${parseFloat(data.totalPayrollCostLastRun).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}` : '₱0.00';
        elements.kpiPayrollRunId.textContent = data.lastPayrollRunIdForCost ? `(Run ID: ${data.lastPayrollRunIdForCost})` : '(No completed run found)';
        elements.kpiAvgTenure.textContent = data.averageTenureYears ? `${data.averageTenureYears} Years` : 'N/A';
        elements.kpiTotalLeaveTypes.textContent = data.totalLeaveTypes || '0';

        if (data.headcountByDepartment?.length > 0) {
            renderHeadcountChart(data.headcountByDepartment.map(d => d.DepartmentName), data.headcountByDepartment.map(d => d.Headcount));
        } else {
            if(elements.headcountChartContainer?.getContext('2d')) elements.headcountChartContainer.parentElement.innerHTML = '<p class="text-center text-gray-500 py-4">No staff distribution data available.</p>';
        }
        if (data.leaveDaysByTypeThisYear?.length > 0) {
            renderLeaveDaysByTypeChart(data.leaveDaysByTypeThisYear.map(l => l.TypeName), data.leaveDaysByTypeThisYear.map(l => l.TotalDays));
        } else {
             if(elements.leaveTypeChartContainer?.getContext('2d')) elements.leaveTypeChartContainer.parentElement.innerHTML = '<p class="text-center text-gray-500 py-4">No approved leave data this year.</p>';
        }
    } catch (error) {
        console.error('Error loading HR analytics data:', error);
        Object.values(elements).forEach(el => { if(el && el.tagName !== 'CANVAS') el.textContent = 'Error'; });
        if(elements.headcountChartContainer?.parentElement) elements.headcountChartContainer.parentElement.innerHTML = `<p class="text-red-500">Error loading chart.</p>`;
        if(elements.leaveTypeChartContainer?.parentElement) elements.leaveTypeChartContainer.parentElement.innerHTML = `<p class="text-red-500">Error loading chart.</p>`;
    }
}

function renderHeadcountChart(labels, data) {
    const ctx = document.getElementById('headcountByDepartmentChart')?.getContext('2d');
    if (!ctx) return;
    if (headcountChartInstance) headcountChartInstance.destroy();
    const bgColors = labels.map((_, i) => `hsl(${(i * 360 / (labels.length || 1))} , 70%, 60%)`);
    const borderColors = labels.map((_, i) => `hsl(${(i * 360 / (labels.length || 1))}, 70%, 50%)`);
    headcountChartInstance = new Chart(ctx, {
        type: 'bar', data: { labels, datasets: [{ label: 'Employees', data, backgroundColor: bgColors, borderColor: borderColors, borderWidth: 1 }] },
        options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { stepSize: Math.max(1, Math.ceil(Math.max(...data, 1) / 10)) } } }, plugins: { legend: { display: false }, title: { display: false } } }
    });
}

function renderLeaveDaysByTypeChart(labels, data) {
    const ctx = document.getElementById('leaveDaysByTypeChart')?.getContext('2d');
    if (!ctx) return;
    if (leaveTypeChartInstance) leaveTypeChartInstance.destroy();
    const bgColors = labels.map((_, i) => `hsl(${ (i * 360 / (labels.length || 1) + 45) % 360}, 75%, 65%)`);
    const borderColors = bgColors.map(c => c.replace('65%)', '55%)'));
    leaveTypeChartInstance = new Chart(ctx, {
        type: 'pie', data: { labels, datasets: [{ label: 'Approved Leave Days', data, backgroundColor: bgColors, borderColor: borderColors, borderWidth: 1 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'top' }, title: { display: false }, tooltip: { callbacks: { label: c => `${c.label || ''}: ${c.parsed || 0} days` } } } }
    });
}

export async function displayAnalyticsReportsSection() {
    if (!initializeAnalyticsElements()) return;
    pageTitleElement.textContent = 'Analytics Reports';

    // Generate automatic date ranges
    const currentDate = new Date();
    const currentYear = currentDate.getFullYear();
    const currentMonth = currentDate.getMonth();

    // Current month range
    const currentMonthStart = new Date(currentYear, currentMonth, 1);
    const currentMonthEnd = new Date(currentYear, currentMonth + 1, 0);

    // Previous month range
    const prevMonthStart = new Date(currentYear, currentMonth - 1, 1);
    const prevMonthEnd = new Date(currentYear, currentMonth, 0);

    // Current quarter range
    const currentQuarter = Math.floor(currentMonth / 3);
    const quarterStartMonth = currentQuarter * 3;
    const quarterEndMonth = quarterStartMonth + 3;
    const currentQuarterStart = new Date(currentYear, quarterStartMonth, 1);
    const currentQuarterEnd = new Date(currentYear, quarterEndMonth, 0);

    // Current year range
    const currentYearStart = new Date(currentYear, 0, 1);
    const currentYearEnd = new Date(currentYear, 11, 31);

    const formatDate = (date) => date.toISOString().split('T')[0];

    mainContentArea.innerHTML = `
        <div class="bg-white p-6 rounded-lg shadow-md border border-[#F7E6CA] space-y-6">
            <h3 class="text-lg font-semibold text-[#4E3B2A] mb-4 font-header">Generate & View Reports</h3>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6 pb-4 border-b border-gray-200 items-end">
                <div><label for="report-type-filter" class="block text-sm font-medium text-gray-700 mb-1">Report Type:</label><select id="report-type-filter" class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]"><option value="">-- Select Report --</option></select></div>
                <div><label for="report-date-range-filter" class="block text-sm font-medium text-gray-700 mb-1">Date Range:</label><select id="report-date-range-filter" class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]"><option value="${formatDate(currentMonthStart)}_${formatDate(currentMonthEnd)}">Current Month (${formatDate(currentMonthStart)} - ${formatDate(currentMonthEnd)})</option><option value="${formatDate(prevMonthStart)}_${formatDate(prevMonthEnd)}">Previous Month (${formatDate(prevMonthStart)} - ${formatDate(prevMonthEnd)})</option><option value="${formatDate(currentQuarterStart)}_${formatDate(currentQuarterEnd)}">Current Quarter (${formatDate(currentQuarterStart)} - ${formatDate(currentQuarterEnd)})</option><option value="${formatDate(currentYearStart)}_${formatDate(currentYearEnd)}">Current Year (${currentYear})</option><option value="custom">Custom Range...</option></select></div>
                <div><label for="custom-date-range" class="block text-sm font-medium text-gray-700 mb-1">Custom Range:</label><input type="text" id="custom-date-range" placeholder="YYYY-MM-DD_YYYY-MM-DD" class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]" style="display: none;"></div>
                <div><button id="generate-report-btn" class="w-full px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Generate Report</button></div>
            </div>
            <div id="reports-output-container" class="overflow-x-auto min-h-[200px] bg-gray-50 p-4 rounded-lg border"><p class="text-center py-4 text-gray-500">Select a report type and click "Generate Report".</p></div>
        </div>`;
    await loadAvailableReportsDropdown();
    const btn = document.getElementById('generate-report-btn');
    if (btn && !btn.hasAttribute('data-listener-attached')) {
        btn.addEventListener('click', handleGenerateReport);
        btn.setAttribute('data-listener-attached', 'true');
    }

    // Add event listener for date range dropdown
    const dateRangeSelect = document.getElementById('report-date-range-filter');
    const customDateInput = document.getElementById('custom-date-range');
    if (dateRangeSelect && customDateInput) {
        dateRangeSelect.addEventListener('change', function() {
            if (this.value === 'custom') {
                customDateInput.style.display = 'block';
                customDateInput.focus();
            } else {
                customDateInput.style.display = 'none';
            }
        });
    }
}

async function loadAvailableReportsDropdown() {
    const filter = document.getElementById('report-type-filter');
    if (!filter) return;
    try {
        const response = await fetch(`${API_BASE_URL}get_hr_reports_list.php`, { credentials: 'include' });
        if (!response.ok) throw new Error('Failed to fetch report list');
        const reports = await response.json();
        if (reports.error) throw new Error(reports.error);
        reports.forEach(r => {
            const opt = document.createElement('option');
            opt.value = r.reportId; opt.textContent = r.reportName;
            filter.appendChild(opt);
        });
    } catch (e) {
        console.error("Error populating report types:", e);
        filter.innerHTML = '<option value="" disabled selected>Error loading types</option>';
    }
}

async function handleGenerateReport() {
    const typeSelect = document.getElementById('report-type-filter');
    const type = typeSelect?.value;
    const name = typeSelect?.options[typeSelect.selectedIndex]?.textContent || type;
    const dateRangeSelect = document.getElementById('report-date-range-filter');
    const customDateInput = document.getElementById('custom-date-range');
    const output = document.getElementById('reports-output-container');

    if (!output || !type) {
        if(output) output.innerHTML = '<p class="text-red-500">Please select a report type.</p>';
        return;
    }

    // Get the actual date range value
    let range = dateRangeSelect?.value;
    if (range === 'custom' && customDateInput?.value) {
        range = customDateInput.value;
    }

    let endpoint = '';
    if (type === 'employee_master_list') endpoint = `${API_BASE_URL}generate_employee_master_report.php`;
    else if (type === 'leave_summary_report') endpoint = `${API_BASE_URL}generate_leave_summary_report.php`;
    else if (type === 'payroll_summary_report') endpoint = `${API_BASE_URL}generate_payroll_summary_report.php`;
    else { output.innerHTML = `<p class="text-red-500">Report '${name}' not configured.</p>`; return; }

    output.innerHTML = `<p class="text-blue-600">Generating <strong>${name}</strong>...</p>`;
    try {
        const params = new URLSearchParams();
        if (range && /^\d{4}-\d{2}-\d{2}_\d{4}-\d{2}-\d{2}$/.test(range)) params.append('date_range', range);
        else if (range) console.warn("Invalid date range format:", range);

        const response = await fetch(`${endpoint}?${params.toString()}`, { credentials: 'include' });
        if (!response.ok) throw new Error(`HTTP error ${response.status}`);
        const reportData = await response.json();
        if (reportData.error) throw new Error(reportData.error);
        renderReportTable(reportData, output);
    } catch (e) {
        console.error(`Error generating report '${type}':`, e);
        output.innerHTML = `<p class="text-red-500">Could not generate report: ${e.message}</p>`;
    }
}

function renderReportTable(reportData, container) {
    if (!reportData || !reportData.columns || !reportData.rows) {
        container.innerHTML = '<p class="text-gray-500">No data for this report.</p>'; return;
    }
    let html = `<div class="mb-2"><h4 class="font-semibold">${reportData.reportName}</h4><p class="text-xs text-gray-500">Generated: ${new Date(reportData.generatedAt).toLocaleString()}</p></div>
        <table class="min-w-full divide-y divide-gray-200 border"><thead class="bg-gray-100"><tr>`;
    reportData.columns.forEach(c => { html += `<th class="px-3 py-2 text-left text-xs font-medium uppercase">${c.label}</th>`; });
    html += `</tr></thead><tbody class="bg-white divide-y divide-gray-200">`;
    if (reportData.rows.length === 0) {
        html += `<tr><td colspan="${reportData.columns.length}" class="px-3 py-3 text-center text-sm">No data.</td></tr>`;
    } else {
        reportData.rows.forEach(row => {
            html += `<tr>`;
            reportData.columns.forEach(c => {
                const val = row[c.key] !== null && row[c.key] !== undefined ? String(row[c.key]) : '-';
                html += `<td class="px-3 py-2 whitespace-nowrap text-sm">${val.replace(/</g, "&lt;")}</td>`;
            });
            html += `</tr>`;
        });
    }
    html += `</tbody></table>`;
    if(reportData.summary?.length > 0){
        html += `<div class="mt-4 pt-2 border-t">`;
        reportData.summary.forEach(item => { html += `<p class="text-sm"><strong>${item.label}:</strong> ${item.value}</p>`; });
        html += `</div>`;
    }
    html += `<div class="mt-4 text-right"><button onclick="exportReportToCSV('${reportData.reportName.replace(/\s+/g, '_')}')" class="px-3 py-1 bg-green-500 text-white text-xs rounded hover:bg-green-600">Export CSV</button></div>`;
    container.innerHTML = html;
}

window.exportReportToCSV = function(filenamePrefix) {
    const table = document.querySelector('#reports-output-container table');
    if (!table) { alert("No report table to export."); return; }
    let csv = [];
    table.querySelectorAll("tr").forEach(row => {
        const rowData = [];
        row.querySelectorAll("td, th").forEach(col => {
            let data = col.innerText.replace(/"/g, '""');
            if (/[",\n]/.test(data)) data = `"${data}"`;
            rowData.push(data);
        });
        csv.push(rowData.join(","));
    });
    const blob = new Blob([csv.join("\n")], {type: "text/csv;charset=utf-8;"});
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = `${filenamePrefix}_${new Date().toISOString().slice(0,10)}.csv`;
    link.style.display = "none";
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

export async function displayAnalyticsMetricsSection() {
    if (!initializeAnalyticsElements()) return;
    pageTitleElement.textContent = 'Key HR Metrics Tracking';

    // Auto-select appropriate time periods based on current date
    const currentDate = new Date();
    const currentMonth = currentDate.getMonth();
    const currentQuarter = Math.floor(currentMonth / 3);

    // Determine best default period based on current date
    let defaultPeriod = 'current';
    if (currentMonth === 0) { // January - show annual trend
        defaultPeriod = 'annual';
    } else if (currentMonth === 2 || currentMonth === 5 || currentMonth === 8 || currentMonth === 11) { // End of quarter months
        defaultPeriod = 'quarterly';
    }

    mainContentArea.innerHTML = `
        <div class="bg-white p-6 rounded-lg shadow-md border border-[#F7E6CA] space-y-6">
            <h3 class="text-lg font-semibold text-[#4E3B2A] mb-4 font-header">Track Key Performance Indicators</h3>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6 pb-4 border-b border-gray-200 items-end">
                <div><label for="metric-name-filter" class="block text-sm font-medium text-gray-700 mb-1">Metric:</label><select id="metric-name-filter" class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]"><option value="">-- Select Metric --</option><option value="headcount_by_department">Staff Distribution by Role</option><option value="turnover_rate">Staff Turnover Rate</option><option value="avg_time_to_hire">Average Time to Hire</option><option value="training_completion_rate">Training Completion Rate</option></select></div>
                <div><label for="metric-period-filter" class="block text-sm font-medium text-gray-700 mb-1">Time Period:</label><select id="metric-period-filter" class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-[#4E3B2A] focus:border-[#4E3B2A]"><option value="current" ${defaultPeriod === 'current' ? 'selected' : ''}>Current Snapshot</option><option value="monthly" ${defaultPeriod === 'monthly' ? 'selected' : ''}>Monthly Trend</option><option value="quarterly" ${defaultPeriod === 'quarterly' ? 'selected' : ''}>Quarterly Trend</option><option value="annual" ${defaultPeriod === 'annual' ? 'selected' : ''}>Annual Trend</option></select></div>
                <div><button id="view-metric-btn" class="w-full px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">View Metric</button></div>
                <div><button id="auto-refresh-btn" class="w-full px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700" title="Auto-refresh with current data"><i class="fas fa-sync-alt"></i> Auto Refresh</button></div>
            </div>
            <div id="metric-display-area" class="min-h-[300px] bg-gray-50 p-4 rounded-lg border"><p class="text-center py-4 text-gray-500">Select a metric and period to view data.</p></div>
        </div>`;
    const btn = document.getElementById('view-metric-btn');
    if (btn && !btn.hasAttribute('data-listener-attached')) {
        btn.addEventListener('click', handleViewMetric);
        btn.setAttribute('data-listener-attached', 'true');
    }

    // Add auto-refresh functionality
    const autoRefreshBtn = document.getElementById('auto-refresh-btn');
    if (autoRefreshBtn && !autoRefreshBtn.hasAttribute('data-listener-attached')) {
        autoRefreshBtn.addEventListener('click', handleAutoRefreshMetrics);
        autoRefreshBtn.setAttribute('data-listener-attached', 'true');
    }

    // Auto-load first metric if available
    setTimeout(() => {
        const metricSelect = document.getElementById('metric-name-filter');
        if (metricSelect && metricSelect.value === '') {
            metricSelect.value = 'headcount_by_department';
            metricSelect.dispatchEvent(new Event('change'));
        }
    }, 500);
}

async function handleViewMetric() {
    const name = document.getElementById('metric-name-filter')?.value;
    const period = document.getElementById('metric-period-filter')?.value;
    const displayArea = document.getElementById('metric-display-area');

    if (!displayArea || !name) {
        if(displayArea) displayArea.innerHTML = '<p class="text-red-500">Please select a metric.</p>';
        return;
    }
    const displayName = name.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    displayArea.innerHTML = `<p class="text-blue-600">Loading data for <strong>${displayName}</strong> (${period})...</p>`;
    
    try {
        const response = await fetch(`${API_BASE_URL}get_key_metrics.php?metric_name=${encodeURIComponent(name)}&metric_period=${encodeURIComponent(period)}`, { credentials: 'include' });
        if (!response.ok) throw new Error(`HTTP error ${response.status}`);
        const data = await response.json();
        if (data.error) throw new Error(data.error);
        
        if (metricChartInstance) metricChartInstance.destroy();
        
        if (data.dataPoints?.length > 0 && data.labels?.length > 0) {
            displayArea.innerHTML = `<canvas id="metricDetailChart" class="max-h-[400px]"></canvas>`;
            renderMetricDetailChart(data.labels, data.dataPoints, data.metricNameDisplay || displayName, data.unit);
        } else if (data.value !== null) {
            displayArea.innerHTML = `<div class="p-4 text-center"><h4 class="text-xl font-semibold">${data.metricNameDisplay || displayName}</h4><p class="text-4xl text-blue-600 font-bold my-3">${data.value} ${data.unit || ''}</p><p class="text-sm text-gray-500">Period: ${data.metricPeriod.charAt(0).toUpperCase() + data.metricPeriod.slice(1)}</p>${data.notes ? `<p class="text-xs text-gray-400 mt-2"><em>Note: ${data.notes}</em></p>` : ''}</div>`;
        } else {
            displayArea.innerHTML = `<p class="text-gray-500">No data for ${displayName} for this period.</p>`;
        }
    } catch (e) {
        console.error("Error viewing metric:", e);
        displayArea.innerHTML = `<p class="text-red-500">Could not load metric: ${e.message}</p>`;
    }
}

function renderMetricDetailChart(labels, dataPoints, metricTitle, unit) {
    const ctx = document.getElementById('metricDetailChart')?.getContext('2d');
    if (!ctx) return;
    const bgColors = labels.map((_, i) => `hsl(${(i * 360 / (labels.length || 1) + 120) % 360}, 65%, 55%)`);
    const borderColors = bgColors.map(c => c.replace('55%)', '45%)'));
    metricChartInstance = new Chart(ctx, {
        type: 'bar',
        data: { labels, datasets: [{ label: `${metricTitle}${unit ? ` (${unit})` : ''}`, data: dataPoints, backgroundColor: bgColors, borderColor: borderColors, borderWidth: 1 }] },
        options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { stepSize: (Math.max(...dataPoints, 1) < 10) ? 1 : undefined } } }, plugins: { legend: { display: dataPoints.length > 1 }, title: { display: true, text: metricTitle } } }
    });
}

async function handleAutoRefreshMetrics() {
    const displayArea = document.getElementById('metric-display-area');
    const metricSelect = document.getElementById('metric-name-filter');
    const periodSelect = document.getElementById('metric-period-filter');

    if (!displayArea) return;

    // Get current selections or use defaults
    const name = metricSelect?.value || 'headcount_by_department';
    const period = periodSelect?.value || 'current';

    if (!name) {
        displayArea.innerHTML = '<p class="text-red-500">Please select a metric to refresh.</p>';
        return;
    }

    const displayName = name.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    displayArea.innerHTML = `<p class="text-blue-600">Auto-refreshing <strong>${displayName}</strong> with latest data...</p>`;

    try {
        const response = await fetch(`${API_BASE_URL}get_key_metrics.php?metric_name=${encodeURIComponent(name)}&metric_period=${encodeURIComponent(period)}&auto_refresh=true`, { credentials: 'include' });
        if (!response.ok) throw new Error(`HTTP error ${response.status}`);
        const data = await response.json();
        if (data.error) throw new Error(data.error);

        if (metricChartInstance) metricChartInstance.destroy();

        if (data.dataPoints?.length > 0 && data.labels?.length > 0) {
            displayArea.innerHTML = `<canvas id="metricDetailChart" class="max-h-[400px]"></canvas><p class="text-xs text-gray-500 mt-2">✓ Refreshed at ${new Date().toLocaleTimeString()}</p>`;
            renderMetricDetailChart(data.labels, data.dataPoints, data.metricNameDisplay || displayName, data.unit);
        } else if (data.value !== null) {
            displayArea.innerHTML = `<div class="p-4 text-center"><h4 class="text-xl font-semibold">${data.metricNameDisplay || displayName}</h4><p class="text-4xl text-blue-600 font-bold my-3">${data.value} ${data.unit || ''}</p><p class="text-sm text-gray-500">Period: ${data.metricPeriod.charAt(0).toUpperCase() + data.metricPeriod.slice(1)}</p>${data.notes ? `<p class="text-xs text-gray-400 mt-2"><em>Note: ${data.notes}</em></p>` : ''}<p class="text-xs text-green-600 mt-2">✓ Refreshed at ${new Date().toLocaleTimeString()}</p></div>`;
        } else {
            displayArea.innerHTML = `<p class="text-gray-500">No data for ${displayName} for this period.</p><p class="text-xs text-gray-400 mt-2">Last refresh: ${new Date().toLocaleTimeString()}</p>`;
        }
    } catch (e) {
        console.error("Error auto-refreshing metric:", e);
        displayArea.innerHTML = `<p class="text-red-500">Could not refresh metric: ${e.message}</p>`;
    }
}

