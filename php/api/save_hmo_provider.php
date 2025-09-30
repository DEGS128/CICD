<?php
/**
 * API Endpoint: Save HMO Provider
 * Creates or updates an HMO provider
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../db_connect.php';

if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

try {
    $providerId = isset($_POST['providerId']) ? (int)$_POST['providerId'] : null;
    $providerName = trim($_POST['providerName'] ?? '');
    $contactPerson = trim($_POST['contactPerson'] ?? '');
    $phoneNumber = trim($_POST['phoneNumber'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $isActive = isset($_POST['isActive']) ? 1 : 0;

    // Validation
    if (empty($providerName)) {
        http_response_code(400);
        echo json_encode(['error' => 'Provider name is required.']);
        exit;
    }

    if ($providerId) {
        // Update existing provider
        $sql = "UPDATE HMOProviders SET
                    ProviderName = :providerName,
                    ContactPerson = :contactPerson,
                    PhoneNumber = :phoneNumber,
                    Email = :email,
                    Address = :address,
                    Website = :website,
                    IsActive = :isActive,
                    UpdatedAt = CURRENT_TIMESTAMP
                WHERE ProviderID = :providerId";

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':providerId', $providerId, PDO::PARAM_INT);
    } else {
        // Insert new provider
        $sql = "INSERT INTO HMOProviders
                (ProviderName, ContactPerson, PhoneNumber, Email, Address, Website, IsActive, CreatedAt)
                VALUES (:providerName, :contactPerson, :phoneNumber, :email, :address, :website, :isActive, CURRENT_TIMESTAMP)";

        $stmt = $pdo->prepare($sql);
    }

    $stmt->bindParam(':providerName', $providerName);
    $stmt->bindParam(':contactPerson', $contactPerson);
    $stmt->bindParam(':phoneNumber', $phoneNumber);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':address', $address);
    $stmt->bindParam(':website', $website);
    $stmt->bindParam(':isActive', $isActive, PDO::PARAM_INT);

    $stmt->execute();

    echo json_encode([
        'success' => true,
        'message' => $providerId ? 'Provider updated successfully.' : 'Provider created successfully.',
        'providerId' => $providerId ?: $pdo->lastInsertId()
    ]);

} catch (PDOException $e) {
    error_log("Save HMO Provider Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred while saving provider.']);
} catch (Exception $e) {
    error_log("Save HMO Provider Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An unexpected error occurred.']);
}
exit;
?>
