first :
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
        $description = "I don't save it"; // Set description to "I don't save it"
        $document = isset($_FILES['document']) ? $_FILES['document'] : null;

        // Validate adminId (id_login)
        $stmt = $pdo->prepare("SELECT id FROM login WHERE id = ?");
        $stmt->execute([$adminId]);
        $adminExists = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$adminExists) {
            throw new Exception("Invalid admin ID. The provided admin ID does not exist in the login table.");
        }

        // Fetch admin's login type and count current journal entries
        $stmt = $pdo->prepare("
            SELECT 
                login_typ,
                (SELECT COUNT(*) FROM journal WHERE id_login = ?) AS journal_count
            FROM login
            WHERE id = ?
        ");
        $stmt->execute([$adminId, $adminId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            throw new Exception("Unable to fetch admin details.");
        }

        $loginTyp = $result['login_typ'];
        $journalCount = $result['journal_count'];

        // Determine the maximum allowed journals based on login type
        $maxJournals = PHP_INT_MAX; // Default to unlimited
        switch ($loginTyp) {
            case 'basic':
                $maxJournals = 50;
                break;
            case 'gold':
                $maxJournals = 500;
                break;
            case 'diamond':
                $maxJournals = PHP_INT_MAX; // Unlimited
                break;
        }

        // Check if the limit has been reached
        if ($journalCount >= $maxJournals) {
            throw new Exception("You have reached the maximum number of journal entries allowed for your login type.");
        }

        // Handle document upload and process data
        if ($document && $document['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $document['tmp_name'];
            $fileName = $document['name'];
            $fileSize = $document['size'];
            $fileType = $document['type'];
            $fileNameCmps = explode(".", $fileName);
            $fileExtension = strtolower(end($fileNameCmps));

            // Check if the file is an HTML file
            if ($fileExtension === 'html') {
                // Load the HTML content
                $htmlContent = file_get_contents($fileTmpPath);

                // Use DOMDocument to parse the HTML
                $dom = new DOMDocument();
                @$dom->loadHTML($htmlContent); // Suppress warnings for invalid HTML

                // Find the "Positions" table
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

                // Extract rows from the "Positions" table
                $rows = $positionsTable->getElementsByTagName('tr');
                $filteredData = [];

                foreach ($rows as $row) {
                    $cells = $row->getElementsByTagName('td');
                    if ($cells->length === 0) {
                        continue; // Skip header rows
                    }

                    $rowData = [];
                    foreach ($cells as $cell) {
                        $rowData[] = trim($cell->textContent);
                    }

                    // Ensure the row has the expected number of columns
                    if (count($rowData) >= 13) {
                        $filteredRow = [
                            'Time Open' => $rowData[0] ?? '', // Time (Open)
                            'Symbol' => $rowData[2] ?? '', // Symbol
                            'Entry Price' => $rowData[6] ?? 0, // Price (Entry)
                            'S/L' => $rowData[7] ?? 0, // S/L
                            'T/P' => $rowData[8] ?? 0, // T/P
                            'Time Close' => $rowData[9] ?? '', // Time (Close)
                            'Profit' => $rowData[13] ?? 0 // Profit
                        ];

                        // Validate required fields
                        if (
                            empty($filteredRow['Time Open']) ||
                            empty($filteredRow['Symbol']) ||
                            empty($filteredRow['Time Close']) ||
                            !is_numeric($filteredRow['Entry Price']) ||
                            !is_numeric($filteredRow['S/L']) ||
                            !is_numeric($filteredRow['T/P']) ||
                            !is_numeric($filteredRow['Profit'])
                        ) {
                            continue; // Skip rows with missing or invalid data
                        }

                        $filteredData[] = $filteredRow;
                    }
                }

                // Insert filtered data into the database
                foreach ($filteredData as $row) {
                    $stmt = $pdo->prepare("
                        INSERT INTO journal (date_journal, pair, entry, sl, tp, close_journal, description, id_login, id_users, date_close) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    if (!$stmt->execute([
                        $row['Time Open'], // date_journal
                        $row['Symbol'], // pair
                        $row['Entry Price'], // entry
                        $row['S/L'], // sl
                        $row['T/P'], // tp
                        $row['Profit'], // close_journal
                        $description, // description ("I don't save it")
                        $adminId, // id_login
                        $userId, // id_users
                        $row['Time Close'] // date_close
                    ])) {
                        throw new Exception('Error inserting journal entry from file.');
                    }
                }

                $response['success'] = true;
                $response['message'] = 'Positions data saved successfully from HTML file.';
            } else {
                throw new Exception('Invalid file type. Please upload an HTML file.');
            }
        } else {
            throw new Exception('No file uploaded.');
        }
    } catch (Exception $e) {
        $response['success'] = false;
        $response['message'] = $e->getMessage();
    }
}

header("Location: ../Views/dashboard.php?success=" . ($response['success'] ? '1' : '0'));
exit();

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
        $dateJournal = $_POST['date_journal'] ?? '';
        $dateJournalClose = $_POST['date_journal_close'] ?? '';
        $pair = $_POST['pair'] ?? '';
        $entry = $_POST['entry'] ?? '';
        $sl = $_POST['sl'] ?? '';
        $tp = $_POST['tp'] ?? '';
        $closeJournal = $_POST['close_journal'] ?? '';
        $description = "I don't save it"; // Set description to "I don't save it"
        $image = isset($_FILES['image']) ? $_FILES['image']['name'] : '';
        $document = isset($_FILES['document']) ? $_FILES['document'] : null;

        // Validate adminId (id_login)
        $stmt = $pdo->prepare("SELECT id FROM login WHERE id = ?");
        $stmt->execute([$adminId]);
        $adminExists = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$adminExists) {
            throw new Exception("Invalid admin ID. The provided admin ID does not exist in the login table.");
        }

        // Fetch admin's login type and count current journal entries
        $stmt = $pdo->prepare("
            SELECT 
                login_typ,
                (SELECT COUNT(*) FROM journal WHERE id_login = ?) AS journal_count
            FROM login
            WHERE id = ?
        ");
        $stmt->execute([$adminId, $adminId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            throw new Exception("Unable to fetch admin details.");
        }

        $loginTyp = $result['login_typ'];
        $journalCount = $result['journal_count'];

        // Determine the maximum allowed journals based on login type
        $maxJournals = PHP_INT_MAX; // Default to unlimited
        switch ($loginTyp) {
            case 'basic':
                $maxJournals = 50;
                break;
            case 'gold':
                $maxJournals = 500;
                break;
            case 'diamond':
                $maxJournals = PHP_INT_MAX; // Unlimited
                break;
        }

        // Check if the limit has been reached
        if ($journalCount >= $maxJournals) {
            throw new Exception("You have reached the maximum number of journal entries allowed for your login type.");
        }

        // Handle image upload
        if (!empty($image)) {
            $targetDir = "../uploads/";
            $targetFile = $targetDir . basename($_FILES["image"]["name"]);
            if (!move_uploaded_file($_FILES["image"]["tmp_name"], $targetFile)) {
                throw new Exception('Image upload failed.');
            }
        }

        // Handle document upload and process data
        if ($document && $document['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $document['tmp_name'];
            $fileName = $document['name'];
            $fileSize = $document['size'];
            $fileType = $document['type'];
            $fileNameCmps = explode(".", $fileName);
            $fileExtension = strtolower(end($fileNameCmps));

            // Check if the file is a CSV or Excel file
            if (in_array($fileExtension, ['csv', 'xlsx', 'xls'])) {
                // Process the file based on its type
                if ($fileExtension === 'csv') {
                    // Process CSV file
                    $fileData = array_map('str_getcsv', file($fileTmpPath));
                } elseif ($fileExtension === 'xlsx' || $fileExtension === 'xls') {
                    // Process Excel file
                    $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
                    $spreadsheet = $reader->load($fileTmpPath);
                    $sheet = $spreadsheet->getActiveSheet();
                    $fileData = $sheet->toArray();
                }

                // Find the start of the "Positions" table
                $positionsStart = 0;
                foreach ($fileData as $index => $row) {
                    if (isset($row[0]) && $row[0] === 'Time' && isset($row[1]) && $row[1] === 'Position') {
                        $positionsStart = $index + 1; // Skip the header row
                        break;
                    }
                }

                // Extract the relevant columns and validate data
                $filteredData = [];
                for ($i = $positionsStart; $i < count($fileData); $i++) {
                    $row = $fileData[$i];
                    if (empty($row[0]) || $row[0] === '0000-00-00 00:00:00' || empty($row[2])) {
                        continue; // Skip rows with invalid or missing data
                    }

                    $filteredRow = [
                        'Time Open' => $row[0] ?? '', // Time (Open)
                        'Symbol' => $row[2] ?? '', // Symbol
                        'Entry Price' => $row[5] ?? 0, // Price (Entry)
                        'S/L' => $row[6] ?? 0, // S/L
                        'T/P' => $row[7] ?? 0, // T/P
                        'Time Close' => $row[8] ?? '', // Time (Close)
                        'Profit' => $row[12] ?? 0 // Profit
                    ];

                    // Validate required fields
                    if (empty($filteredRow['Symbol']) || empty($filteredRow['Time Open']) || empty($filteredRow['Time Close'])) {
                        continue; // Skip rows with missing required fields
                    }

                    $filteredData[] = $filteredRow;
                }

                // Insert filtered data into the database
                foreach ($filteredData as $row) {
                    $stmt = $pdo->prepare("
                        INSERT INTO journal (date_journal, pair, entry, sl, tp, close_journal, description, image, id_login, id_users, date_close) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    if (!$stmt->execute([
                        $row['Time Open'], // date_journal
                        $row['Symbol'], // pair
                        $row['Entry Price'], // entry
                        $row['S/L'], // sl
                        $row['T/P'], // tp
                        $row['Profit'], // close_journal
                        $description, // description ("I don't save it")
                        $image, // image
                        $adminId, // id_login
                        $userId, // id_users
                        $row['Time Close'] // date_close
                    ])) {
                        throw new Exception('Error inserting journal entry from file.');
                    }
                }

                $response['success'] = true;
                $response['message'] = 'Journal entries added successfully from file.';
            } else {
                throw new Exception('Invalid file type. Please upload a CSV or Excel file.');
            }
        } else {
            // Insert single journal entry if no file is uploaded
            $stmt = $pdo->prepare("
                INSERT INTO journal (date_journal, pair, entry, sl, tp, close_journal, description, image, id_login, id_users, date_close) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            if ($stmt->execute([$dateJournal, $pair, $entry, $sl, $tp, $closeJournal, $description, $image, $adminId, $userId, $dateJournalClose])) {
                $response['success'] = true;
                $response['message'] = 'Journal entry added successfully.';
            } else {
                throw new Exception('Error inserting journal entry.');
            }
        }
    } catch (Exception $e) {
        $response['success'] = false;
        $response['message'] = $e->getMessage();
    }
}

header("Location: ../Views/dashboard.php?success=" . ($response['success'] ? '1' : '0'));
exit();
