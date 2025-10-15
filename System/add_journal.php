<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../vendor/autoload.php';
include '../DB/config.php';

$response = ['success' => false, 'message' => 'Unknown error.'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $userId = $_POST['userId'] ?? null;
        $adminId = $_POST['adminId'] ?? null;
        $selectedMethod = $_POST['selectedMethod'] ?? null;

        // Validate adminId (id_login)
        $stmt = $pdo->prepare("SELECT id FROM login WHERE id = ?");
        $stmt->execute([$adminId]);
        $adminExists = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$adminExists) {
            throw new Exception("Invalid admin ID.");
        }

        // Fetch admin login type and count journal entries
        $stmt = $pdo->prepare("SELECT login_typ, (SELECT COUNT(*) FROM journal WHERE id_login = ?) AS journal_count FROM login WHERE id = ?");
        $stmt->execute([$adminId, $adminId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$result) {
            throw new Exception("Unable to fetch admin details.");
        }

        $loginTyp = $result['login_typ'];
        $journalCount = $result['journal_count'];
        $maxJournals = ($loginTyp === 'basic') ? 500 : (($loginTyp === 'gold') ? 500 : PHP_INT_MAX);

        if ($journalCount >= $maxJournals) {
            throw new Exception("Journal entry limit reached.");
        }

        // Handle data based on the selected method
        if ($selectedMethod === 'fileUpload') {
            // Handle file upload
            $document = isset($_FILES['document']) ? $_FILES['document'] : null;

            if (!$document || $document['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('No file uploaded or file upload error.');
            }

            // Process HTML file
            $fileTmpPath = $document['tmp_name'];
            $fileName = $document['name'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            if ($fileExtension === 'html') {
                $htmlContent = file_get_contents($fileTmpPath);
                $dom = new DOMDocument();
                @$dom->loadHTML($htmlContent);

                $tables = $dom->getElementsByTagName('table');
                $positionsTable = null;
                foreach ($tables as $table) {
                    $headers = $table->getElementsByTagName('th');
                    foreach ($headers as $header) {
                        if (trim($header->textContent) === 'Positions') {
                            $positionsTable = $table;
                            break 2;
                        }
                    }
                }

                if (!$positionsTable) {
                    throw new Exception("Positions table not found in the uploaded file.");
                }

                $rows = $positionsTable->getElementsByTagName('tr');
                $filteredData = [];

                foreach ($rows as $row) {
                    $cells = $row->getElementsByTagName('td');
                    if ($cells->length === 0) continue;

                    $rowData = [];
                    foreach ($cells as $cell) {
                        $rowData[] = trim($cell->textContent);
                    }

                    if (count($rowData) >= 13) {
                        $filteredRow = [
                            'Time Open' => $rowData[0] ?? '',
                            'Symbol' => $rowData[2] ?? '',
                            'Entry Price' => $rowData[6] ?? 0,
                            'S/L' => $rowData[7] ?? 0,
                            'T/P' => $rowData[10] ?? 0,
                            'Time Close' => $rowData[9] ?? '',
                            'Profit' => $rowData[13] ?? 0
                        ];

                        if (!empty($filteredRow['Time Open']) && !empty($filteredRow['Symbol']) && !empty($filteredRow['Time Close']) && is_numeric($filteredRow['Entry Price']) && is_numeric($filteredRow['S/L']) && is_numeric($filteredRow['T/P']) && is_numeric($filteredRow['Profit'])) {
                            $filteredData[] = $filteredRow;
                        }
                    }
                }

                foreach ($filteredData as $row) {
                    $stmt = $pdo->prepare("INSERT INTO journal (date_journal, pair, entry, sl, tp, close_journal, description, id_login, id_users, date_close, trade_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    if (!$stmt->execute([$row['Time Open'], $row['Symbol'], $row['Entry Price'], $row['S/L'], $row['T/P'], $row['Profit'], "I don't save it", $adminId, $userId, $row['Time Close'], 'fileUpload'])) {
                        throw new Exception('Error inserting journal entry from file.');
                    }
                }
            } else {
                throw new Exception('Invalid file type. Please upload an HTML file.');
            }
        } elseif ($selectedMethod === 'manualEntry') {
            // Handle manual entry
            $dateJournal = $_POST['date_journal'] ?? '';
            $dateJournalClose = $_POST['date_journal_close'] ?? '';
            $pair = $_POST['pair'] ?? '';
            $entry = $_POST['entry'] ?? '';
            $sl = $_POST['sl'] ?? '';
            $tp = $_POST['tp'] ?? '';
            $closeJournal = $_POST['close_journal'] ?? '';
            $description = $_POST['descriptionnamual'] ?? '';
            $image = isset($_FILES['image']) ? $_FILES['image']['name'] : '';
        
            // Check if the description contains a TradingView link
            $pattern = '/(https:\/\/www\.tradingview\.com\/x\/[a-zA-Z0-9]+)/';
            if (preg_match($pattern, $description, $matches)) {
                $description = preg_replace($pattern, '<a href="$1" target="_blank">$1</a>', $description);
            }
        
            // Handle image upload
            if (!empty($image)) {
                $targetDir = "../uploads/";
                $targetFile = $targetDir . basename($_FILES["image"]["name"]);
                if (!move_uploaded_file($_FILES["image"]["tmp_name"], $targetFile)) {
                    throw new Exception('Image upload failed.');
                }
            }
        
            // Insert into database
            $stmt = $pdo->prepare("INSERT INTO journal (date_journal, pair, entry, sl, tp, close_journal, description, id_login, id_users, date_close, image, trade_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt->execute([$dateJournal, $pair, $entry, $sl, $tp, $closeJournal, $description, $adminId, $userId, $dateJournalClose, $image, 'manualEntry'])) {
                throw new Exception('Error inserting manual journal entry.');
            }
        } elseif ($selectedMethod === 'payoutDeposit') { 
            $payoutDepositType = $_POST['payoutDepositType'] ?? '';
            $amount = $_POST['amount'] ?? 0;
            $payoutDate = $_POST['payoutDate'] ?? '';
            $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        
            if (empty($payoutDepositType) || empty($amount) || empty($payoutDate)) {
                throw new Exception('All fields are required for payout/deposit/commission.');
            }
        
            // Determine closeJournal and trade_type based on selected type
            if ($payoutDepositType === 'payout') {
                $closeJournal = -abs($amount); 
                $tradeType = 'payout';
            } elseif ($payoutDepositType === 'deposit') {
                $closeJournal = abs($amount); 
                $tradeType = 'deposit';
            } elseif ($payoutDepositType === 'commission') {
                $closeJournal = -abs($amount); // usually commission is a cost
                $tradeType = 'commission';
            } else {
                throw new Exception('Invalid payout/deposit type.');
            }
        
            // Insert into DB
            $stmt = $pdo->prepare("INSERT INTO journal (date_journal, close_journal, description, id_login, id_users, trade_type) VALUES (?, ?, ?, ?, ?, ?)");
            if (!$stmt->execute([$payoutDate, $closeJournal, $description, $adminId, $userId, $tradeType])) {
                throw new Exception('Error inserting payout/deposit/commission entry.');
            }
        }
         else {
            throw new Exception('Invalid method selected.');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Check if image is uploaded
            if (isset($_FILES['verification_image']) && $_FILES['verification_image']['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath = $_FILES['verification_image']['tmp_name'];
                $fileName = $_FILES['verification_image']['name'];
                $fileSize = $_FILES['verification_image']['size'];
                $fileType = $_FILES['verification_image']['type'];
                $fileNameCmps = explode(".", $fileName);
                $fileExtension = strtolower(end($fileNameCmps));
        
                // Sanitize file name
                $newFileName = uniqid() . '.' . $fileExtension;
        
                // Check allowed file types
                $allowedfileExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        
                if (in_array($fileExtension, $allowedfileExtensions)) {
                    // Directory to save images
                    $uploadFileDir = '../verificationimage/';
                    $dest_path = $uploadFileDir . $newFileName;
        
                    if (move_uploaded_file($fileTmpPath, $dest_path)) {
                        // Save path to database
                        $stmt = $pdo->prepare("INSERT INTO verification_images (user_id, image_path) VALUES (:user_id, :image_path)");
                        $stmt->execute([
                            ':user_id' => $_POST['user_id'], // Make sure to pass this in the form
                            ':image_path' => $newFileName
                        ]);
        
                        $response['success'] = true;
                        $response['message'] = 'Image uploaded successfully.';
                    } else {
                        $response['message'] = 'Error moving the file.';
                    }
                } else {
                    $response['message'] = 'Invalid file type.';
                }
            } else {
                $response['message'] = 'No image uploaded or upload error.';
            }
        } else {
            $response['message'] = 'Invalid request.';
        }
        
        $response['success'] = true;
        $response['message'] = 'Journal entry saved successfully.';
    } catch (Exception $e) {
        $response['success'] = false;
        $response['message'] = $e->getMessage();
    }
}

// Redirect with success or error parameters
if ($response['success']) {
    header("Location: ../Views/dashboard.php?success=1");
} else {
    header("Location: ../Views/dashboard.php?error=1");
}
exit();
