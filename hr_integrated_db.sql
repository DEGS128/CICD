-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 30, 2025 at 06:10 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `hr_integrated_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendancerecords`
--

CREATE TABLE `attendancerecords` (
  `RecordID` int(11) NOT NULL,
  `EmployeeID` int(11) NOT NULL,
  `AttendanceDate` date NOT NULL,
  `ClockInTime` time DEFAULT NULL,
  `ClockOutTime` time DEFAULT NULL,
  `Status` varchar(50) DEFAULT NULL,
  `Notes` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bonuses`
--

CREATE TABLE `bonuses` (
  `BonusID` int(11) NOT NULL,
  `EmployeeID` int(11) NOT NULL,
  `PayrollID` int(11) DEFAULT NULL,
  `BonusAmount` decimal(12,2) NOT NULL,
  `BonusType` varchar(100) DEFAULT NULL,
  `AwardDate` date NOT NULL,
  `PaymentDate` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `claimapprovals`
--

CREATE TABLE `claimapprovals` (
  `ApprovalID` int(11) NOT NULL,
  `ClaimID` int(11) NOT NULL,
  `ApproverID` int(11) NOT NULL,
  `ApprovalDate` datetime NOT NULL DEFAULT current_timestamp(),
  `Status` varchar(50) NOT NULL,
  `Comments` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `claimapprovals`
--

INSERT INTO `claimapprovals` (`ApprovalID`, `ClaimID`, `ApproverID`, `ApprovalDate`, `Status`, `Comments`) VALUES
(1, 1, 1, '2025-09-25 02:39:46', 'Approved', 'yourwelocm');

-- --------------------------------------------------------

--
-- Table structure for table `claims`
--

CREATE TABLE `claims` (
  `ClaimID` int(11) NOT NULL,
  `EmployeeID` int(11) NOT NULL,
  `ClaimTypeID` int(11) NOT NULL,
  `SubmissionDate` datetime NOT NULL DEFAULT current_timestamp(),
  `ClaimDate` date DEFAULT NULL,
  `Amount` decimal(12,2) NOT NULL,
  `Currency` varchar(10) NOT NULL DEFAULT 'PHP',
  `Description` text DEFAULT NULL,
  `ReceiptPath` varchar(255) DEFAULT NULL,
  `Status` varchar(50) NOT NULL DEFAULT 'Submitted',
  `PayrollID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `claims`
--

INSERT INTO `claims` (`ClaimID`, `EmployeeID`, `ClaimTypeID`, `SubmissionDate`, `ClaimDate`, `Amount`, `Currency`, `Description`, `ReceiptPath`, `Status`, `PayrollID`) VALUES
(1, 3, 5, '2025-09-25 02:38:55', '2025-09-25', 1500.00, 'PHP', 'medical', NULL, 'Approved', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `claimtypes`
--

CREATE TABLE `claimtypes` (
  `ClaimTypeID` int(11) NOT NULL,
  `TypeName` varchar(150) NOT NULL,
  `Description` varchar(255) DEFAULT NULL,
  `RequiresReceipt` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `claimtypes`
--

INSERT INTO `claimtypes` (`ClaimTypeID`, `TypeName`, `Description`, `RequiresReceipt`) VALUES
(1, 'Travel Expenses', 'Business travel related expenses', 1),
(2, 'Meal Allowance', 'Business meal expenses', 1),
(3, 'Office Supplies', 'Office equipment and supplies', 1),
(4, 'Training Costs', 'Professional development and training', 1),
(5, 'Medical Reimbursement', 'Medical expenses not covered by insurance', 1);

-- --------------------------------------------------------

--
-- Table structure for table `compensationplans`
--

CREATE TABLE `compensationplans` (
  `PlanID` int(11) NOT NULL,
  `PlanName` varchar(150) NOT NULL,
  `Description` varchar(255) DEFAULT NULL,
  `EffectiveDate` date NOT NULL,
  `EndDate` date DEFAULT NULL,
  `PlanType` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `compensationplans`
--

INSERT INTO `compensationplans` (`PlanID`, `PlanName`, `Description`, `EffectiveDate`, `EndDate`, `PlanType`) VALUES
(1, 'Standard Compensation', 'Standard employee compensation package', '2025-09-09', NULL, 'Base Salary'),
(2, 'Performance Bonus', 'Performance-based bonus program', '2025-09-09', NULL, 'Variable Pay');

-- --------------------------------------------------------

--
-- Table structure for table `deductions`
--

CREATE TABLE `deductions` (
  `DeductionID` int(11) NOT NULL,
  `EmployeeID` int(11) NOT NULL,
  `PayrollID` int(11) NOT NULL,
  `DeductionType` varchar(100) NOT NULL,
  `DeductionAmount` decimal(12,2) NOT NULL,
  `Provider` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `DepartmentID` int(11) NOT NULL,
  `DepartmentName` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`DepartmentID`, `DepartmentName`) VALUES
(1, 'Administration'),
(2, 'Human Resources'),
(3, 'Information Technology'),
(4, 'Finance'),
(5, 'Operations'),
(6, 'Marketing');

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `DocumentID` int(11) NOT NULL,
  `EmployeeID` int(11) NOT NULL,
  `DocumentName` varchar(255) NOT NULL,
  `DocumentType` varchar(100) NOT NULL,
  `FilePath` varchar(500) DEFAULT NULL,
  `UploadDate` datetime DEFAULT current_timestamp(),
  `Status` varchar(20) DEFAULT 'Active',
  `Description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `documents`
--

INSERT INTO `documents` (`DocumentID`, `EmployeeID`, `DocumentName`, `DocumentType`, `FilePath`, `UploadDate`, `Status`, `Description`) VALUES
(1, 1, 'Employment Contract - John Doe.pdf', 'Contract', '/documents/contracts/emp_001.pdf', '2025-09-14 03:26:15', 'Active', 'Initial employment contract'),
(2, 1, 'ID Copy - John Doe.pdf', 'Identification', '/documents/id/id_001.pdf', '2025-09-14 03:26:15', 'Active', 'Government issued ID'),
(3, 2, 'Employment Contract - Jane Smith.pdf', 'Contract', '/documents/contracts/emp_002.pdf', '2025-09-14 03:26:15', 'Active', 'Initial employment contract'),
(4, 2, 'Resume - Jane Smith.pdf', 'Resume', '/documents/resumes/res_002.pdf', '2025-09-14 03:26:15', 'Active', 'Updated resume'),
(5, 3, 'Employment Contract - Bob Johnson.pdf', 'Contract', '/documents/contracts/emp_003.pdf', '2025-09-14 03:26:15', 'Active', 'Initial employment contract'),
(6, 3, 'Degree Certificate - Bob Johnson.pdf', 'Education', '/documents/education/deg_003.pdf', '2025-09-14 03:26:15', 'Active', 'Bachelor degree certificate'),
(7, 4, 'Employment Contract - Alice Brown.pdf', 'Contract', '/documents/contracts/emp_004.pdf', '2025-09-14 03:26:15', 'Active', 'Initial employment contract'),
(8, 5, 'Employment Contract - Charlie Wilson.pdf', 'Contract', '/documents/contracts/emp_005.pdf', '2025-09-14 03:26:15', 'Active', 'Initial employment contract');

-- --------------------------------------------------------

--
-- Table structure for table `employeedocuments`
--

CREATE TABLE `employeedocuments` (
  `DocumentID` int(11) NOT NULL,
  `EmployeeID` int(11) NOT NULL,
  `DocumentType` varchar(100) DEFAULT NULL,
  `DocumentName` varchar(200) NOT NULL,
  `FilePath` varchar(255) NOT NULL,
  `UploadedAt` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `EmployeeID` int(11) NOT NULL,
  `FirstName` varchar(100) NOT NULL,
  `MiddleName` varchar(100) DEFAULT NULL,
  `LastName` varchar(100) NOT NULL,
  `Suffix` varchar(20) DEFAULT NULL,
  `Email` varchar(190) DEFAULT NULL,
  `PersonalEmail` varchar(190) DEFAULT NULL,
  `PhoneNumber` varchar(50) DEFAULT NULL,
  `DateOfBirth` date DEFAULT NULL,
  `Gender` varchar(20) DEFAULT NULL,
  `MaritalStatus` varchar(50) DEFAULT NULL,
  `Nationality` varchar(100) DEFAULT NULL,
  `AddressLine1` varchar(200) DEFAULT NULL,
  `AddressLine2` varchar(200) DEFAULT NULL,
  `City` varchar(100) DEFAULT NULL,
  `StateProvince` varchar(100) DEFAULT NULL,
  `PostalCode` varchar(30) DEFAULT NULL,
  `Country` varchar(100) DEFAULT NULL,
  `EmergencyContactName` varchar(150) DEFAULT NULL,
  `EmergencyContactRelationship` varchar(100) DEFAULT NULL,
  `EmergencyContactPhone` varchar(50) DEFAULT NULL,
  `HireDate` date DEFAULT NULL,
  `JobTitle` varchar(150) DEFAULT NULL,
  `DepartmentID` int(11) DEFAULT NULL,
  `ManagerID` int(11) DEFAULT NULL,
  `IsActive` tinyint(1) NOT NULL DEFAULT 1,
  `TerminationDate` date DEFAULT NULL,
  `TerminationReason` varchar(255) DEFAULT NULL,
  `EmployeePhotoPath` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`EmployeeID`, `FirstName`, `MiddleName`, `LastName`, `Suffix`, `Email`, `PersonalEmail`, `PhoneNumber`, `DateOfBirth`, `Gender`, `MaritalStatus`, `Nationality`, `AddressLine1`, `AddressLine2`, `City`, `StateProvince`, `PostalCode`, `Country`, `EmergencyContactName`, `EmergencyContactRelationship`, `EmergencyContactPhone`, `HireDate`, `JobTitle`, `DepartmentID`, `ManagerID`, `IsActive`, `TerminationDate`, `TerminationReason`, `EmployeePhotoPath`) VALUES
(1, 'System', NULL, 'Admin', NULL, 'admin@hr4.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-09', 'System Administrator', 1, NULL, 1, NULL, NULL, NULL),
(2, 'John', NULL, 'Doe', NULL, 'john.doe@hr4.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-09', 'Software Developer', 7, NULL, 1, NULL, NULL, NULL),
(3, 'john paul', NULL, 'austria', NULL, 'johnpaulaustria321@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-25', 'nursing', 14, NULL, 1, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `employeesalaries`
--

CREATE TABLE `employeesalaries` (
  `SalaryID` int(11) NOT NULL,
  `EmployeeID` int(11) NOT NULL,
  `BaseSalary` decimal(12,2) NOT NULL,
  `PayFrequency` varchar(30) NOT NULL,
  `PayRate` decimal(12,2) DEFAULT NULL,
  `EffectiveDate` date NOT NULL,
  `EndDate` date DEFAULT NULL,
  `IsCurrent` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employeesalaries`
--

INSERT INTO `employeesalaries` (`SalaryID`, `EmployeeID`, `BaseSalary`, `PayFrequency`, `PayRate`, `EffectiveDate`, `EndDate`, `IsCurrent`) VALUES
(1, 1, 80000.00, 'Monthly', NULL, '2025-09-09', '0323-02-22', 0),
(2, 2, 60000.00, 'Monthly', NULL, '2025-09-09', '0232-02-22', 0),
(3, 2, 2323.00, 'Monthly', 2323232.00, '0232-02-23', NULL, 1),
(4, 1, 22323.00, 'Monthly', 2322333.00, '0323-02-23', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `incentives`
--

CREATE TABLE `incentives` (
  `IncentiveID` int(11) NOT NULL,
  `EmployeeID` int(11) NOT NULL,
  `PlanID` int(11) DEFAULT NULL,
  `IncentiveType` varchar(100) DEFAULT NULL,
  `Amount` decimal(12,2) NOT NULL,
  `AwardDate` date NOT NULL,
  `PayoutDate` date DEFAULT NULL,
  `PayrollID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_roles`
--

CREATE TABLE `job_roles` (
  `JobRoleID` int(11) NOT NULL,
  `JobRoleName` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `job_roles`
--

INSERT INTO `job_roles` (`JobRoleID`, `JobRoleName`) VALUES
(1, 'Manager'),
(2, 'Staff'),
(3, 'Senior Staff'),
(4, 'Director'),
(5, 'Coordinator');

-- --------------------------------------------------------

--
-- Table structure for table `leavebalances`
--

CREATE TABLE `leavebalances` (
  `LeaveBalanceID` int(11) NOT NULL,
  `EmployeeID` int(11) NOT NULL,
  `LeaveTypeID` int(11) NOT NULL,
  `BalanceYear` int(11) NOT NULL,
  `AvailableDays` decimal(6,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leaverequests`
--

CREATE TABLE `leaverequests` (
  `RequestID` int(11) NOT NULL,
  `EmployeeID` int(11) NOT NULL,
  `LeaveTypeID` int(11) NOT NULL,
  `StartDate` date NOT NULL,
  `EndDate` date NOT NULL,
  `NumberOfDays` decimal(6,2) NOT NULL,
  `Reason` varchar(255) DEFAULT NULL,
  `Status` varchar(50) NOT NULL DEFAULT 'Pending',
  `RequestDate` datetime NOT NULL DEFAULT current_timestamp(),
  `ApproverID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leavetypes`
--

CREATE TABLE `leavetypes` (
  `LeaveTypeID` int(11) NOT NULL,
  `TypeName` varchar(150) NOT NULL,
  `Description` varchar(255) DEFAULT NULL,
  `RequiresApproval` tinyint(1) NOT NULL DEFAULT 1,
  `AccrualRate` decimal(8,2) DEFAULT NULL,
  `MaxCarryForwardDays` decimal(8,2) DEFAULT NULL,
  `IsActive` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leavetypes`
--

INSERT INTO `leavetypes` (`LeaveTypeID`, `TypeName`, `Description`, `RequiresApproval`, `AccrualRate`, `MaxCarryForwardDays`, `IsActive`) VALUES
(1, 'Annual Leave', 'Regular vacation leave', 1, 1.25, 5.00, 1),
(2, 'Sick Leave', 'Medical and health related leave', 1, 1.00, 10.00, 1),
(3, 'Personal Leave', 'Personal time off', 1, 0.50, 2.00, 1),
(4, 'Maternity Leave', 'Maternity and childbirth leave', 1, 0.00, 0.00, 1),
(5, 'Paternity Leave', 'Paternity leave for new fathers', 1, 0.00, 0.00, 1);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `NotificationID` int(11) NOT NULL,
  `UserID` int(11) NOT NULL,
  `SenderUserID` int(11) DEFAULT NULL,
  `NotificationType` varchar(100) DEFAULT NULL,
  `Message` varchar(255) NOT NULL,
  `Link` varchar(255) DEFAULT NULL,
  `IsRead` tinyint(1) NOT NULL DEFAULT 0,
  `CreatedAt` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`NotificationID`, `UserID`, `SenderUserID`, `NotificationType`, `Message`, `Link`, `IsRead`, `CreatedAt`) VALUES
(1, 1, 3, 'NEW_CLAIM_SUBMITTED', 'New claim (#1) submitted by john paul austria for medical (Amount: 1500 PHP).', '#claims-approval', 1, '2025-09-25 02:38:55'),
(2, 3, 3, 'NEW_CLAIM_SUBMITTED', 'New claim (#1) submitted by john paul austria for medical (Amount: 1500 PHP).', '#claims-approval', 0, '2025-09-25 02:38:55'),
(3, 3, 1, 'CLAIM_APPROVED', 'Your claim (#1) has been Approved. Comments: yourwelocm', '#my-claims', 0, '2025-09-25 02:39:46');

-- --------------------------------------------------------

--
-- Table structure for table `organizationalstructure`
--

CREATE TABLE `organizationalstructure` (
  `DepartmentID` int(11) NOT NULL,
  `DepartmentName` varchar(150) NOT NULL,
  `ParentDepartmentID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `organizationalstructure`
--

INSERT INTO `organizationalstructure` (`DepartmentID`, `DepartmentName`, `ParentDepartmentID`) VALUES
(1, 'Human Resources', NULL),
(2, 'Information Technology', NULL),
(3, 'Finance', NULL),
(4, 'Operations', NULL),
(5, 'Recruitment', 1),
(6, 'Payroll', 1),
(7, 'Software Development', 2),
(8, 'IT Support', 2),
(9, 'Accounting', 3),
(10, 'Audit', 3),
(11, 'Human Resources', NULL),
(12, 'Information Technology', NULL),
(13, 'Finance', NULL),
(14, 'Operations', NULL),
(15, 'Recruitment', 1),
(16, 'Payroll', 1),
(17, 'Software Development', 2),
(18, 'IT Support', 2),
(19, 'Accounting', 3),
(20, 'Audit', 3);

-- --------------------------------------------------------

--
-- Table structure for table `payrollruns`
--

CREATE TABLE `payrollruns` (
  `PayrollID` int(11) NOT NULL,
  `PayPeriodStartDate` date NOT NULL,
  `PayPeriodEndDate` date NOT NULL,
  `PaymentDate` date NOT NULL,
  `Status` varchar(50) NOT NULL DEFAULT 'Pending',
  `ProcessedDate` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payrollruns`
--

INSERT INTO `payrollruns` (`PayrollID`, `PayPeriodStartDate`, `PayPeriodEndDate`, `PaymentDate`, `Status`, `ProcessedDate`) VALUES
(1, '2233-02-23', '2234-03-23', '2234-12-22', 'Pending', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `payroll_runs`
--

CREATE TABLE `payroll_runs` (
  `PayrollRunID` int(11) NOT NULL,
  `RunName` varchar(255) NOT NULL,
  `PayPeriodStart` date NOT NULL,
  `PayPeriodEnd` date NOT NULL,
  `RunDate` datetime DEFAULT current_timestamp(),
  `Status` varchar(20) DEFAULT 'Draft',
  `TotalEmployees` int(11) DEFAULT 0,
  `TotalGrossPay` decimal(15,2) DEFAULT 0.00,
  `TotalDeductions` decimal(15,2) DEFAULT 0.00,
  `TotalNetPay` decimal(15,2) DEFAULT 0.00,
  `CreatedBy` int(11) DEFAULT NULL,
  `ProcessedDate` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payroll_runs`
--

INSERT INTO `payroll_runs` (`PayrollRunID`, `RunName`, `PayPeriodStart`, `PayPeriodEnd`, `RunDate`, `Status`, `TotalEmployees`, `TotalGrossPay`, `TotalDeductions`, `TotalNetPay`, `CreatedBy`, `ProcessedDate`) VALUES
(1, 'January 2024 - First Half', '2024-01-01', '2024-01-15', '2025-09-14 03:26:16', 'Processed', 8, 45000.00, 9000.00, 36000.00, NULL, '2024-01-16 10:30:00'),
(2, 'January 2024 - Second Half', '2024-01-16', '2024-01-31', '2025-09-14 03:26:16', 'Processed', 8, 45000.00, 9000.00, 36000.00, NULL, '2024-02-01 10:30:00'),
(3, 'February 2024 - First Half', '2024-02-01', '2024-02-15', '2025-09-14 03:26:16', 'Processed', 8, 45000.00, 9000.00, 36000.00, NULL, '2024-02-16 10:30:00'),
(4, 'February 2024 - Second Half', '2024-02-16', '2024-02-29', '2025-09-14 03:26:16', 'Draft', 8, 45000.00, 9000.00, 36000.00, NULL, NULL),
(5, 'March 2024 - First Half', '2024-03-01', '2024-03-15', '2025-09-14 03:26:16', 'Draft', 8, 45000.00, 9000.00, 36000.00, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `payslips`
--

CREATE TABLE `payslips` (
  `PayslipID` int(11) NOT NULL,
  `PayrollID` int(11) NOT NULL,
  `EmployeeID` int(11) NOT NULL,
  `PayPeriodStartDate` date NOT NULL,
  `PayPeriodEndDate` date NOT NULL,
  `PaymentDate` date NOT NULL,
  `BasicSalary` decimal(12,2) NOT NULL DEFAULT 0.00,
  `HourlyRate` decimal(12,2) DEFAULT NULL,
  `HoursWorked` decimal(10,2) NOT NULL DEFAULT 0.00,
  `OvertimeHours` decimal(10,2) NOT NULL DEFAULT 0.00,
  `RegularPay` decimal(12,2) NOT NULL DEFAULT 0.00,
  `OvertimePay` decimal(12,2) NOT NULL DEFAULT 0.00,
  `HolidayPay` decimal(12,2) NOT NULL DEFAULT 0.00,
  `NightDifferentialPay` decimal(12,2) NOT NULL DEFAULT 0.00,
  `BonusesTotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `OtherEarnings` decimal(12,2) NOT NULL DEFAULT 0.00,
  `GrossIncome` decimal(12,2) NOT NULL DEFAULT 0.00,
  `SSS_Contribution` decimal(12,2) NOT NULL DEFAULT 0.00,
  `PhilHealth_Contribution` decimal(12,2) NOT NULL DEFAULT 0.00,
  `PagIBIG_Contribution` decimal(12,2) NOT NULL DEFAULT 0.00,
  `WithholdingTax` decimal(12,2) NOT NULL DEFAULT 0.00,
  `OtherDeductionsTotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `TotalDeductions` decimal(12,2) NOT NULL DEFAULT 0.00,
  `NetIncome` decimal(12,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `RoleID` int(11) NOT NULL,
  `RoleName` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`RoleID`, `RoleName`) VALUES
(4, 'Employee'),
(2, 'HR Admin'),
(3, 'Manager'),
(1, 'System Admin');

-- --------------------------------------------------------

--
-- Table structure for table `salaryadjustments`
--

CREATE TABLE `salaryadjustments` (
  `AdjustmentID` int(11) NOT NULL,
  `EmployeeID` int(11) NOT NULL,
  `PreviousSalaryID` int(11) DEFAULT NULL,
  `NewSalaryID` int(11) DEFAULT NULL,
  `AdjustmentDate` date NOT NULL,
  `Reason` varchar(255) DEFAULT NULL,
  `ApprovedBy` int(11) DEFAULT NULL,
  `ApprovalDate` date DEFAULT NULL,
  `PercentageIncrease` decimal(6,3) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `schedules`
--

CREATE TABLE `schedules` (
  `ScheduleID` int(11) NOT NULL,
  `EmployeeID` int(11) NOT NULL,
  `ShiftID` int(11) DEFAULT NULL,
  `StartDate` date NOT NULL,
  `EndDate` date DEFAULT NULL,
  `Workdays` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shifts`
--

CREATE TABLE `shifts` (
  `ShiftID` int(11) NOT NULL,
  `ShiftName` varchar(100) NOT NULL,
  `StartTime` time NOT NULL,
  `EndTime` time NOT NULL,
  `BreakDurationMinutes` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shifts`
--

INSERT INTO `shifts` (`ShiftID`, `ShiftName`, `StartTime`, `EndTime`, `BreakDurationMinutes`) VALUES
(1, 'Day Shift', '08:00:00', '17:00:00', 60),
(2, 'Night Shift', '22:00:00', '07:00:00', 60),
(3, 'Morning Shift', '06:00:00', '15:00:00', 45),
(4, 'Evening Shift', '14:00:00', '23:00:00', 45),
(5, 'Flexible Hours', '09:00:00', '18:00:00', 60);

-- --------------------------------------------------------

--
-- Table structure for table `timesheets`
--

CREATE TABLE `timesheets` (
  `TimesheetID` int(11) NOT NULL,
  `EmployeeID` int(11) NOT NULL,
  `ScheduleID` int(11) DEFAULT NULL,
  `PeriodStartDate` date NOT NULL,
  `PeriodEndDate` date NOT NULL,
  `TotalHoursWorked` decimal(10,2) NOT NULL DEFAULT 0.00,
  `OvertimeHours` decimal(10,2) NOT NULL DEFAULT 0.00,
  `Status` varchar(50) NOT NULL DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `timesheets`
--

INSERT INTO `timesheets` (`TimesheetID`, `EmployeeID`, `ScheduleID`, `PeriodStartDate`, `PeriodEndDate`, `TotalHoursWorked`, `OvertimeHours`, `Status`) VALUES
(1, 1, NULL, '0003-02-23', '0323-12-23', 0.00, 0.00, 'Pending');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `UserID` int(11) NOT NULL,
  `EmployeeID` int(11) NOT NULL,
  `Username` varchar(100) NOT NULL,
  `PasswordHash` varchar(255) NOT NULL,
  `RoleID` int(11) NOT NULL,
  `IsActive` tinyint(1) NOT NULL DEFAULT 1,
  `IsTwoFactorEnabled` tinyint(1) NOT NULL DEFAULT 0,
  `TwoFactorEmailCode` varchar(20) DEFAULT NULL,
  `TwoFactorCodeExpiry` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`UserID`, `EmployeeID`, `Username`, `PasswordHash`, `RoleID`, `IsActive`, `IsTwoFactorEnabled`, `TwoFactorEmailCode`, `TwoFactorCodeExpiry`) VALUES
(1, 1, 'admin@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, 0, NULL, NULL),
(2, 2, 'hr_chief@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 1, 0, NULL, NULL),
(3, 3, 'johnpaulaustria321@gmail.com', '$2y$10$SIzLwql6ST6EYAEfaksQhOc59wHZOjb4MfnqvkFbZYVnJ0zhMsTTK', 1, 1, 0, NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendancerecords`
--
ALTER TABLE `attendancerecords`
  ADD PRIMARY KEY (`RecordID`),
  ADD KEY `EmployeeID` (`EmployeeID`,`AttendanceDate`);

--
-- Indexes for table `bonuses`
--
ALTER TABLE `bonuses`
  ADD PRIMARY KEY (`BonusID`),
  ADD KEY `EmployeeID` (`EmployeeID`),
  ADD KEY `PayrollID` (`PayrollID`);

--
-- Indexes for table `claimapprovals`
--
ALTER TABLE `claimapprovals`
  ADD PRIMARY KEY (`ApprovalID`),
  ADD KEY `ClaimID` (`ClaimID`),
  ADD KEY `ApproverID` (`ApproverID`);

--
-- Indexes for table `claims`
--
ALTER TABLE `claims`
  ADD PRIMARY KEY (`ClaimID`),
  ADD KEY `EmployeeID` (`EmployeeID`),
  ADD KEY `ClaimTypeID` (`ClaimTypeID`),
  ADD KEY `PayrollID` (`PayrollID`),
  ADD KEY `idx_claims_status` (`Status`),
  ADD KEY `idx_claims_employee_status` (`EmployeeID`,`Status`);

--
-- Indexes for table `claimtypes`
--
ALTER TABLE `claimtypes`
  ADD PRIMARY KEY (`ClaimTypeID`),
  ADD UNIQUE KEY `TypeName` (`TypeName`);

--
-- Indexes for table `compensationplans`
--
ALTER TABLE `compensationplans`
  ADD PRIMARY KEY (`PlanID`);

--
-- Indexes for table `deductions`
--
ALTER TABLE `deductions`
  ADD PRIMARY KEY (`DeductionID`),
  ADD KEY `EmployeeID` (`EmployeeID`),
  ADD KEY `PayrollID` (`PayrollID`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`DepartmentID`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`DocumentID`);

--
-- Indexes for table `employeedocuments`
--
ALTER TABLE `employeedocuments`
  ADD PRIMARY KEY (`DocumentID`),
  ADD KEY `EmployeeID` (`EmployeeID`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`EmployeeID`),
  ADD UNIQUE KEY `uq_employees_email` (`Email`),
  ADD UNIQUE KEY `uq_employees_personal_email` (`PersonalEmail`),
  ADD KEY `DepartmentID` (`DepartmentID`),
  ADD KEY `ManagerID` (`ManagerID`),
  ADD KEY `idx_employees_active` (`IsActive`);

--
-- Indexes for table `employeesalaries`
--
ALTER TABLE `employeesalaries`
  ADD PRIMARY KEY (`SalaryID`),
  ADD KEY `EmployeeID` (`EmployeeID`),
  ADD KEY `idx_empsal_iscurrent` (`IsCurrent`);

--
-- Indexes for table `incentives`
--
ALTER TABLE `incentives`
  ADD PRIMARY KEY (`IncentiveID`),
  ADD KEY `EmployeeID` (`EmployeeID`),
  ADD KEY `PlanID` (`PlanID`),
  ADD KEY `PayrollID` (`PayrollID`);

--
-- Indexes for table `job_roles`
--
ALTER TABLE `job_roles`
  ADD PRIMARY KEY (`JobRoleID`);

--
-- Indexes for table `leavebalances`
--
ALTER TABLE `leavebalances`
  ADD PRIMARY KEY (`LeaveBalanceID`),
  ADD UNIQUE KEY `uq_lb_emp_type_year` (`EmployeeID`,`LeaveTypeID`,`BalanceYear`),
  ADD KEY `fk_lb_type` (`LeaveTypeID`);

--
-- Indexes for table `leaverequests`
--
ALTER TABLE `leaverequests`
  ADD PRIMARY KEY (`RequestID`),
  ADD KEY `EmployeeID` (`EmployeeID`),
  ADD KEY `LeaveTypeID` (`LeaveTypeID`),
  ADD KEY `ApproverID` (`ApproverID`),
  ADD KEY `idx_leaverequests_status` (`Status`),
  ADD KEY `idx_leave_requests_employee_status` (`EmployeeID`,`Status`);

--
-- Indexes for table `leavetypes`
--
ALTER TABLE `leavetypes`
  ADD PRIMARY KEY (`LeaveTypeID`),
  ADD UNIQUE KEY `TypeName` (`TypeName`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`NotificationID`),
  ADD KEY `UserID` (`UserID`),
  ADD KEY `SenderUserID` (`SenderUserID`),
  ADD KEY `idx_notifications_isread` (`IsRead`);

--
-- Indexes for table `organizationalstructure`
--
ALTER TABLE `organizationalstructure`
  ADD PRIMARY KEY (`DepartmentID`),
  ADD KEY `ParentDepartmentID` (`ParentDepartmentID`);

--
-- Indexes for table `payrollruns`
--
ALTER TABLE `payrollruns`
  ADD PRIMARY KEY (`PayrollID`),
  ADD KEY `idx_payrollruns_paymentdate` (`PaymentDate`),
  ADD KEY `idx_payrollruns_status` (`Status`);

--
-- Indexes for table `payroll_runs`
--
ALTER TABLE `payroll_runs`
  ADD PRIMARY KEY (`PayrollRunID`);

--
-- Indexes for table `payslips`
--
ALTER TABLE `payslips`
  ADD PRIMARY KEY (`PayslipID`),
  ADD KEY `PayrollID` (`PayrollID`),
  ADD KEY `EmployeeID` (`EmployeeID`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`RoleID`),
  ADD UNIQUE KEY `RoleName` (`RoleName`);

--
-- Indexes for table `salaryadjustments`
--
ALTER TABLE `salaryadjustments`
  ADD PRIMARY KEY (`AdjustmentID`),
  ADD KEY `EmployeeID` (`EmployeeID`),
  ADD KEY `PreviousSalaryID` (`PreviousSalaryID`),
  ADD KEY `NewSalaryID` (`NewSalaryID`);

--
-- Indexes for table `schedules`
--
ALTER TABLE `schedules`
  ADD PRIMARY KEY (`ScheduleID`),
  ADD KEY `EmployeeID` (`EmployeeID`),
  ADD KEY `ShiftID` (`ShiftID`);

--
-- Indexes for table `shifts`
--
ALTER TABLE `shifts`
  ADD PRIMARY KEY (`ShiftID`);

--
-- Indexes for table `timesheets`
--
ALTER TABLE `timesheets`
  ADD PRIMARY KEY (`TimesheetID`),
  ADD KEY `EmployeeID` (`EmployeeID`),
  ADD KEY `ScheduleID` (`ScheduleID`),
  ADD KEY `idx_timesheets_status` (`Status`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`UserID`),
  ADD UNIQUE KEY `Username` (`Username`),
  ADD KEY `EmployeeID` (`EmployeeID`),
  ADD KEY `RoleID` (`RoleID`),
  ADD KEY `idx_users_active` (`IsActive`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendancerecords`
--
ALTER TABLE `attendancerecords`
  MODIFY `RecordID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bonuses`
--
ALTER TABLE `bonuses`
  MODIFY `BonusID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `claimapprovals`
--
ALTER TABLE `claimapprovals`
  MODIFY `ApprovalID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `claims`
--
ALTER TABLE `claims`
  MODIFY `ClaimID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `claimtypes`
--
ALTER TABLE `claimtypes`
  MODIFY `ClaimTypeID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `compensationplans`
--
ALTER TABLE `compensationplans`
  MODIFY `PlanID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `deductions`
--
ALTER TABLE `deductions`
  MODIFY `DeductionID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `DepartmentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `DocumentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `employeedocuments`
--
ALTER TABLE `employeedocuments`
  MODIFY `DocumentID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `EmployeeID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `employeesalaries`
--
ALTER TABLE `employeesalaries`
  MODIFY `SalaryID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `incentives`
--
ALTER TABLE `incentives`
  MODIFY `IncentiveID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `job_roles`
--
ALTER TABLE `job_roles`
  MODIFY `JobRoleID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `leavebalances`
--
ALTER TABLE `leavebalances`
  MODIFY `LeaveBalanceID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leaverequests`
--
ALTER TABLE `leaverequests`
  MODIFY `RequestID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leavetypes`
--
ALTER TABLE `leavetypes`
  MODIFY `LeaveTypeID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `NotificationID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `organizationalstructure`
--
ALTER TABLE `organizationalstructure`
  MODIFY `DepartmentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `payrollruns`
--
ALTER TABLE `payrollruns`
  MODIFY `PayrollID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `payroll_runs`
--
ALTER TABLE `payroll_runs`
  MODIFY `PayrollRunID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `payslips`
--
ALTER TABLE `payslips`
  MODIFY `PayslipID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `RoleID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `salaryadjustments`
--
ALTER TABLE `salaryadjustments`
  MODIFY `AdjustmentID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `schedules`
--
ALTER TABLE `schedules`
  MODIFY `ScheduleID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `shifts`
--
ALTER TABLE `shifts`
  MODIFY `ShiftID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `timesheets`
--
ALTER TABLE `timesheets`
  MODIFY `TimesheetID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `UserID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendancerecords`
--
ALTER TABLE `attendancerecords`
  ADD CONSTRAINT `fk_attendance_employee` FOREIGN KEY (`EmployeeID`) REFERENCES `employees` (`EmployeeID`) ON DELETE CASCADE;

--
-- Constraints for table `bonuses`
--
ALTER TABLE `bonuses`
  ADD CONSTRAINT `fk_bonus_employee` FOREIGN KEY (`EmployeeID`) REFERENCES `employees` (`EmployeeID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_bonus_payroll` FOREIGN KEY (`PayrollID`) REFERENCES `payrollruns` (`PayrollID`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_bonuses_employee` FOREIGN KEY (`EmployeeID`) REFERENCES `employees` (`EmployeeID`);

--
-- Constraints for table `claimapprovals`
--
ALTER TABLE `claimapprovals`
  ADD CONSTRAINT `fk_claim_approvals_approver` FOREIGN KEY (`ApproverID`) REFERENCES `employees` (`EmployeeID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_claim_approvals_claim` FOREIGN KEY (`ClaimID`) REFERENCES `claims` (`ClaimID`) ON DELETE CASCADE;

--
-- Constraints for table `claims`
--
ALTER TABLE `claims`
  ADD CONSTRAINT `fk_claims_employee` FOREIGN KEY (`EmployeeID`) REFERENCES `employees` (`EmployeeID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_claims_payroll` FOREIGN KEY (`PayrollID`) REFERENCES `payrollruns` (`PayrollID`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_claims_type` FOREIGN KEY (`ClaimTypeID`) REFERENCES `claimtypes` (`ClaimTypeID`);

--
-- Constraints for table `deductions`
--
ALTER TABLE `deductions`
  ADD CONSTRAINT `fk_deductions_employee` FOREIGN KEY (`EmployeeID`) REFERENCES `employees` (`EmployeeID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_deductions_payroll` FOREIGN KEY (`PayrollID`) REFERENCES `payrollruns` (`PayrollID`) ON DELETE CASCADE;

--
-- Constraints for table `employeedocuments`
--
ALTER TABLE `employeedocuments`
  ADD CONSTRAINT `fk_docs_employee` FOREIGN KEY (`EmployeeID`) REFERENCES `employees` (`EmployeeID`) ON DELETE CASCADE;

--
-- Constraints for table `employees`
--
ALTER TABLE `employees`
  ADD CONSTRAINT `fk_emp_dept` FOREIGN KEY (`DepartmentID`) REFERENCES `organizationalstructure` (`DepartmentID`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_emp_manager` FOREIGN KEY (`ManagerID`) REFERENCES `employees` (`EmployeeID`) ON DELETE SET NULL;

--
-- Constraints for table `employeesalaries`
--
ALTER TABLE `employeesalaries`
  ADD CONSTRAINT `fk_empsal_employee` FOREIGN KEY (`EmployeeID`) REFERENCES `employees` (`EmployeeID`) ON DELETE CASCADE;

--
-- Constraints for table `incentives`
--
ALTER TABLE `incentives`
  ADD CONSTRAINT `fk_incentive_employee` FOREIGN KEY (`EmployeeID`) REFERENCES `employees` (`EmployeeID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_incentive_payroll` FOREIGN KEY (`PayrollID`) REFERENCES `payrollruns` (`PayrollID`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_incentive_plan` FOREIGN KEY (`PlanID`) REFERENCES `compensationplans` (`PlanID`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_incentives_employee` FOREIGN KEY (`EmployeeID`) REFERENCES `employees` (`EmployeeID`);

--
-- Constraints for table `leavebalances`
--
ALTER TABLE `leavebalances`
  ADD CONSTRAINT `fk_lb_employee` FOREIGN KEY (`EmployeeID`) REFERENCES `employees` (`EmployeeID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_lb_type` FOREIGN KEY (`LeaveTypeID`) REFERENCES `leavetypes` (`LeaveTypeID`);

--
-- Constraints for table `leaverequests`
--
ALTER TABLE `leaverequests`
  ADD CONSTRAINT `fk_lr_approver` FOREIGN KEY (`ApproverID`) REFERENCES `employees` (`EmployeeID`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_lr_employee` FOREIGN KEY (`EmployeeID`) REFERENCES `employees` (`EmployeeID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_lr_type` FOREIGN KEY (`LeaveTypeID`) REFERENCES `leavetypes` (`LeaveTypeID`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notif_sender` FOREIGN KEY (`SenderUserID`) REFERENCES `users` (`UserID`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_notif_user` FOREIGN KEY (`UserID`) REFERENCES `users` (`UserID`) ON DELETE CASCADE;

--
-- Constraints for table `organizationalstructure`
--
ALTER TABLE `organizationalstructure`
  ADD CONSTRAINT `fk_org_parent` FOREIGN KEY (`ParentDepartmentID`) REFERENCES `organizationalstructure` (`DepartmentID`) ON DELETE SET NULL;

--
-- Constraints for table `payslips`
--
ALTER TABLE `payslips`
  ADD CONSTRAINT `fk_payslips_employee` FOREIGN KEY (`EmployeeID`) REFERENCES `employees` (`EmployeeID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_payslips_payroll` FOREIGN KEY (`PayrollID`) REFERENCES `payrollruns` (`PayrollID`) ON DELETE CASCADE;

--
-- Constraints for table `salaryadjustments`
--
ALTER TABLE `salaryadjustments`
  ADD CONSTRAINT `fk_saladj_employee` FOREIGN KEY (`EmployeeID`) REFERENCES `employees` (`EmployeeID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_saladj_new` FOREIGN KEY (`NewSalaryID`) REFERENCES `employeesalaries` (`SalaryID`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_saladj_prev` FOREIGN KEY (`PreviousSalaryID`) REFERENCES `employeesalaries` (`SalaryID`) ON DELETE SET NULL;

--
-- Constraints for table `schedules`
--
ALTER TABLE `schedules`
  ADD CONSTRAINT `fk_schedule_employee` FOREIGN KEY (`EmployeeID`) REFERENCES `employees` (`EmployeeID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_schedule_shift` FOREIGN KEY (`ShiftID`) REFERENCES `shifts` (`ShiftID`) ON DELETE SET NULL;

--
-- Constraints for table `timesheets`
--
ALTER TABLE `timesheets`
  ADD CONSTRAINT `fk_timesheet_employee` FOREIGN KEY (`EmployeeID`) REFERENCES `employees` (`EmployeeID`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_employee` FOREIGN KEY (`EmployeeID`) REFERENCES `employees` (`EmployeeID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_users_role` FOREIGN KEY (`RoleID`) REFERENCES `roles` (`RoleID`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
