<?php
    session_start();

    // Check if the user is logged in
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../System/signin.php");
        exit();
    }

    include '../DB/config.php'; // Ensure this file contains your PDO connection setup

    // Initialize error reporting for debugging purposes (Remove in production)
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    // Use null coalescing to avoid passing null to htmlspecialchars
    $user_email = $_SESSION['email'] ?? '';
    $user_phone = $_SESSION['phone'] ?? '';
    $user_address = $_SESSION['address'] ?? '';

    try {
        // Fetch user details from the database
        $userId = $_SESSION['user_id'];
        $stmt = $pdo->prepare("SELECT fullname, email, Address, phone, login_typ, created_at FROM login WHERE id = :id");
        $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $userDetails = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($userDetails) {
            $user_fullname = $userDetails['fullname'];
            $user_email = $userDetails['email'];
            $user_address = $userDetails['Address'];
            $user_phone = $userDetails['phone'];
            $user_login_typ = $userDetails['login_typ'];
            $_SESSION['created_at'] = $userDetails['created_at']; // store it in session

        } else {
            $user_fullname = '';
            $user_email = '';
            $user_address = '';
            $user_phone = '';
            $user_login_typ = '';
            $_SESSION['created_at'] = '';
            echo "Error: User details not found.";
            exit();
        }
    } catch (PDOException $e) {
        echo "Error fetching user details: " . htmlspecialchars($e->getMessage());
        exit();
    }

    // Handle form submission for adding a new user
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $fullname = trim($_POST['fullname']);
        $capital = trim($_POST['capital']);
        $admin_id = $_SESSION['user_id']; // Get the logged-in user's ID

        if (!empty($fullname) && !empty($capital)) {
            // Fetch the user's login type and current account count
            $stmt = $pdo->prepare("
                SELECT 
                    login_typ, 
                    (SELECT COUNT(*) FROM users WHERE login_id = ?) AS account_count 
                FROM login 
                WHERE id = ?
            ");
            $stmt->execute([$admin_id, $admin_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                $login_typ = $result['login_typ'];
                $account_count = $result['account_count'];

                // Determine the maximum number of accounts based on the login type
                $max_accounts = 0;
                switch ($login_typ) {
                    case 'basic':
                        $max_accounts = 2;
                        break;
                    case 'gold':
                        $max_accounts = 5;
                        break;
                    case 'diamond':
                        $max_accounts = 10; // Assuming diamond allows 10
                        break;
                }

                // Check if the user can add more accounts
                if ($account_count < $max_accounts) {
                    // Insert the new account
                    $stmt = $pdo->prepare("INSERT INTO users (fullname, capital, login_id) VALUES (?, ?, ?)");
                    if ($stmt->execute([$fullname, $capital, $admin_id])) {
                        header("Location: dashboard.php?success=1");
                        exit();
                    } else {
                        $_SESSION['message'] = "You can only have tow accounts.";
                        header("Location: dashboard.php?error=1");
                    }
                } else {
                    $_SESSION['message'] = "You can only have tow accounts.";
                    header("Location: dashboard.php?error=1");
                }
            } else {
                $_SESSION['message'] = "You can only have tow accounts.";
                header("Location: dashboard.php?error=1");
            }
        } else {
            $_SESSION['message'] = "You can only have tow accounts.";
            header("Location: dashboard.php?error=1");
        }
    }
    // After fetching $user_login_typ from the DB
    if (in_array(strtolower($user_login_typ), ['gold', 'diamond'])) {
        echo "<!-- User has {$user_login_typ} plan → showing calendar & overview -->";
        echo "<style>
            .Comprehensivecalender { display: block !important; }
            .overviewtow { display: grid !important; }
        </style>";
    } else {
        echo "<!-- User has {$user_login_typ} plan → hiding calendar & overview -->";
        echo "<style>
            .Comprehensivecalender { display: none !important; }
            .overviewtow { display: none !important; }
            #OthersLink { display: none !important; }
            #Provecontact { display: none !important; }
        </style>";
    }

    // Handle user deletion
    if (isset($_GET['delete'])) {
        // Check if the 'delete' parameter is set in the query string
        $user_id = intval($_GET['delete']);

        if ($user_id > 0) {
            try {
                // Start transaction
                $pdo->beginTransaction();

                // Delete related journal records first
                $stmt = $pdo->prepare("DELETE FROM journal WHERE id_users = ?");
                $stmt->execute([$user_id]);

                // Now delete the user
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                if ($stmt->execute([$user_id])) {
                    // Commit transaction
                    $pdo->commit();
                    echo "<script>
                            Swal.fire({
                                title: 'Deleted!',
                                text: 'User has been deleted.',
                                icon: 'success'
                            }).then(function() {
                                window.location = '../System/users_list.php';
                            });
                        </script>";
                        header("Location: dashboard.php?success=1");
                    exit(); // Prevent further execution
                } else {
                    // Rollback transaction in case of failure
                    $pdo->rollBack();
                    header("Location: dashboard.php?error=1");
                    exit();
                }
            } catch (PDOException $e) {
                // Handle any errors
                $pdo->rollBack();
                error_log("Error deleting user: " . $e->getMessage());
                header("Location: dashboard.php?error=1");
                exit();
            }
        }
    }
    
    // Fetch all journal entries
    try {
        $stmt = $pdo->query("SELECT * FROM journal");
        $journals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        echo "<p>Error fetching journal entries: " . htmlspecialchars($e->getMessage()) . "</p>";
    }

    // Fetch users that belong to the logged-in admin
    $adminId = $_SESSION['user_id'];

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE login_id = :adminId");
        $stmt->execute(['adminId' => $adminId]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }

    // Fetch notifications
    try {
        $stmt = $pdo->prepare("SELECT message, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$_SESSION['user_id']]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        echo "<p>Error fetching notifications: " . htmlspecialchars($e->getMessage()) . "</p>";
    }

    // Fetch the user's messages
    try {
        $stmt = $pdo->prepare("SELECT * FROM messages WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$_SESSION['user_id']]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        echo "Error fetching messages: " . htmlspecialchars($e->getMessage());
    }

    // Fetch the user's community messages with sender's full name and timestamp
    $chatboxTable = $_SESSION['chatbox_table'] ?? 'community_1111'; // Default to 'community'

    try {
        $stmt = $pdo->query("
            SELECT c.*, l.fullname 
            FROM $chatboxTable c
            JOIN login l ON c.sender_id = l.id
            ORDER BY c.timestamp ASC
        ");
        $community_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        echo "Error fetching chatbox data: " . htmlspecialchars($e->getMessage());
    }
    function makeLinksClickable($text) {
        $pattern = '/(https?:\/\/[^\s]+|www\.[^\s]+)/i';
    
        return preg_replace_callback($pattern, function ($matches) {
            $url = $matches[0];
            $href = (stripos($url, 'http') === 0) ? $url : "http://$url";
            return "<a href='" . htmlspecialchars($href, ENT_QUOTES) . "' target='_blank' style='color:blue; text-decoration:underline;'>" 
                    . htmlspecialchars($matches[0], ENT_QUOTES) . "</a>";
        }, htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE));
    }
    
    // Function to calculate the date signal
    function getDateSignal($timestamp) {
        $currentDate = new DateTime();
        $messageDate = new DateTime($timestamp);

        $interval = $currentDate->diff($messageDate)->days;

        if ($interval == 0) {
            return "Today";
        } elseif ($interval == 1) {
            return "Yesterday";
        } else {
            return $messageDate->format('F j, Y'); // e.g., "October 5, 2024"
        }
    }
    // Assume $_SESSION['created_at'] kay7mil datetime dyal creation account f format MySQL 'Y-m-d H:i:s'
    if (isset($_SESSION['created_at'])) {
        $created_at = $_SESSION['created_at'];

        // Convert to DateTime object
        $createdDateTime = new DateTime($created_at);
        $now = new DateTime(); // datetime dyal had nhar

        // Calculate difference
        $interval = $createdDateTime->diff($now);

        // Prepare readable format (days, hours, minutes, seconds)
        $timer_subscribe = $interval->format('%y years, %m months, %d days, %h hours, %i minutes, %s seconds');
    } else {
        $timer_subscribe = 'Unknown';
    }
    // Function to get max accounts based on login type
    function displayPlan($user_login_typ) {

        switch (strtolower($user_login_typ)) {
            case 'basic':
                ?>
                <h4>Basic Plan</h4>
                <h3>0$</h3>
                <p><i class="ri-checkbox-circle-line"></i> 2 Trading Journal Account</p>
                <p><i class="ri-checkbox-circle-line"></i> 50 Track Record data</p>
                <p><i class="ri-checkbox-circle-line"></i> Simple analysis chart</p>
                <br>
                <h4>We advise you to pass to the </h4>
                <h3>Gold Plan</h3>
                <p><i class="ri-checkbox-circle-line"></i> 5 Trading Journal Account</p>
                <p><i class="ri-checkbox-circle-line"></i> 500 Track Record data</p>
                <p><i class="ri-checkbox-circle-line"></i> Advanced analysis chart</p>
                <p><i class="ri-checkbox-circle-line"></i> Support Access</p>
                <p><i class="fa fa-gift"></i> only for <span style="color:#fff;">15$</span> a month</p>
                <br>
                <a style="cursor: pointer;text-decoration:underline;" href="#" onclick="switchTabuser(2)">update from here...</a>
                <p> subscribe start on: <br><span id="accountCreated"> <?php echo htmlspecialchars($_SESSION['created_at']); ?></span> <br> Timer subscribe : <br> <span id="timerSubscribe"></span></p>
                
                <script>
                    const createdAtStr = document.getElementById('accountCreated').innerText;
                    const createdAt = new Date(createdAtStr);

                    function updateTimer() {
                        const now = new Date();
                        let diff = now - createdAt; // milliseconds

                        const seconds = Math.floor(diff / 1000 % 60);
                        const minutes = Math.floor(diff / (1000 * 60) % 60);
                        const hours = Math.floor(diff / (1000 * 60 * 60) % 24);
                        const days = Math.floor(diff / (1000 * 60 * 60 * 24));

                        // Update text
                        const timerElement = document.getElementById('timerSubscribe');
                        timerElement.innerText = `${days}days, ${hours}h, ${minutes}min, ${seconds}s`;

                        // Change color
                        if (days < 20) {
                            timerElement.style.color = 'orange';
                        } else if (days < 27) {
                            timerElement.style.color = 'red';
                        } else if (days > 29) {
                            timerElement.style.color = 'black';
                        }
                    }

                    // Update every second
                    setInterval(updateTimer, 1000);
                    updateTimer();
                </script>
                <?php
                break;
    
            case 'gold':
                ?>
                <h4>Gold Plan</h4>
                <h3>15$</h3>
                <p><i class="ri-checkbox-circle-line"></i> 5 Trading Journal Account</p>
                <p><i class="ri-checkbox-circle-line"></i> 500 Track Record data</p>
                <p><i class="ri-checkbox-circle-line"></i> Advanced analysis chart</p>
                <p><i class="ri-checkbox-circle-line"></i> Support Access</p>
                <br>
                <h4>Diamond Plan</h4>
                <h3>30$</h3>
                <p><i class="ri-checkbox-circle-line"></i> 10 Trading Journal Account</p>
                <p><i class="ri-checkbox-circle-line"></i> Unlimited Track Record</p>
                <p><i class="ri-checkbox-circle-line"></i> Support Access</p>
                <p><i class="ri-checkbox-circle-line"></i> Private Coaching</p>
                <p><i class="ri-checkbox-circle-line"></i> ACCESS ForexPeak Community</p>
                <p><i class="fa fa-gift"></i> only for <span style="color:#fff;">30$</span> a month</p>
                <br>
                <a style="cursor: pointer;text-decoration:underline;" href="#" onclick="switchTabuser(2)">update from here...</a>
                <br>
                <p> subscribe start on: <br><span id="accountCreated"> <?php echo htmlspecialchars($_SESSION['created_at']); ?></span> <br> Timer subscribe : <br> <span id="timerSubscribe"></span></p>
                <script>
                    const createdAtStr = document.getElementById('accountCreated').innerText;
                    const createdAt = new Date(createdAtStr);

                    function updateTimer() {
                        const now = new Date();
                        let diff = now - createdAt; // milliseconds

                        const seconds = Math.floor(diff / 1000 % 60);
                        const minutes = Math.floor(diff / (1000 * 60) % 60);
                        const hours = Math.floor(diff / (1000 * 60 * 60) % 24);
                        const days = Math.floor(diff / (1000 * 60 * 60 * 24));

                        // Update text
                        const timerElement = document.getElementById('timerSubscribe');
                        timerElement.innerText = `${days}days, ${hours}h, ${minutes}min, ${seconds}s`;

                        // Change color
                        if (days > 28) {
                            timerElement.style.color = 'red';
                        } else if (days > 20) {
                            timerElement.style.color = 'orange';
                        } else if (days > 30) {
                            timerElement.style.color = 'black';
                        }
                    }

                    // Update every second
                    setInterval(updateTimer, 1000);
                    updateTimer();
                </script>
                <?php
                break;
    
            case 'diamond':
                ?>
                <h4>Diamond Plan</h4>
                <h3>30$</h3>
                <p><i class="ri-checkbox-circle-line"></i> 10 Trading Journal Account</p>
                <p><i class="ri-checkbox-circle-line"></i> Unlimited Track Record</p>
                <p><i class="ri-checkbox-circle-line"></i> Support Access</p>
                <p><i class="ri-checkbox-circle-line"></i> Private Coaching</p>
                <p><i class="ri-checkbox-circle-line"></i> ACCESS ForexPeak Community</p>
                <br>
                <p> subscribe start on: <br><span id="accountCreated"> <?php echo htmlspecialchars($_SESSION['created_at']); ?></span> <br> Timer subscribe : <br> <span id="timerSubscribe"></span></p>
                <script>
                    const createdAtStr = document.getElementById('accountCreated').innerText;
                    const createdAt = new Date(createdAtStr);

                    function updateTimer() {
                        const now = new Date();
                        let diff = now - createdAt; // milliseconds

                        const seconds = Math.floor(diff / 1000 % 60);
                        const minutes = Math.floor(diff / (1000 * 60) % 60);
                        const hours = Math.floor(diff / (1000 * 60 * 60) % 24);
                        const days = Math.floor(diff / (1000 * 60 * 60 * 24));

                        // Update text
                        const timerElement = document.getElementById('timerSubscribe');
                        timerElement.innerText = `${days}days, ${hours}h, ${minutes}min, ${seconds}s`;

                        // Change color
                        if (days > 28) {
                            timerElement.style.color = 'red';
                        } else if (days > 20) {
                            timerElement.style.color = 'orange';
                        } else if (days > 30) {
                            timerElement.style.color = 'black';
                        }
                    }

                    // Update every second
                    setInterval(updateTimer, 1000);
                    updateTimer();
                </script>
                <?php
                break;
    
            default:
                echo "<p>No valid plan found.</p>";
        }
    }
    // Handle form submission for sending a new community message
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['community_image'])) {
        $community_text = trim($_POST['community']);
        $receiver_id = $_POST['receiver_id']; // Set receiver ID as needed
        $reply_to_message_id = $_POST['reply_to_message_id'] ?? null;
        $original_message = $_POST['original_message'] ?? null;
        $name_sender = null; // Initialize name_sender
        $currentTableName = $_POST['currentTableName'] ?? 'community_1111'; // Get the current table name from the form

        // Sanitize the table name to prevent SQL injection
        $currentTableName = preg_replace('/[^a-zA-Z0-9_]/', '', $currentTableName);

        // If this is a reply, fetch the name_sender of the original message
        if (!empty($reply_to_message_id)) {
            // Prepare the query to fetch the sender's full name from the correct table
            $stmt = $pdo->prepare("
                SELECT l.fullname 
                FROM $currentTableName c
                JOIN login l ON c.sender_id = l.id 
                WHERE c.id = ?
            ");
            $stmt->execute([$reply_to_message_id]);
            $original_message_data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($original_message_data) {
                $name_sender = $original_message_data['fullname']; // Get the name_sender
            }
        }

        $image_path = null;

        // Handle image upload
        if (!empty($_FILES['community_image']['name'])) {
            $target_dir = "../images/"; // Directory to store uploaded images
            $image_extension = strtolower(pathinfo($_FILES['community_image']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

            if (in_array($image_extension, $allowed_extensions)) {
                $new_file_name = $target_dir . uniqid("img_", true) . "." . $image_extension; // Unique file name

                // Move the uploaded file
                if (move_uploaded_file($_FILES['community_image']['tmp_name'], $new_file_name)) {
                    $image_path = $new_file_name; // Store the relative path to the image
                } else {
                    echo "Error uploading the image.";
                }
            } else {
                echo "Invalid file type. Only JPG, JPEG, PNG, and GIF files are allowed.";
            }
        }

        if (!empty($community_text) || !empty($image_path)) {
            // Use the currentTableName from the form submission
            $stmt = $pdo->prepare("
                INSERT INTO $currentTableName (sender_id, receiver_id, community_message, reply_to_message_id, original_message, name_sender, image_path) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            if ($stmt->execute([$_SESSION['user_id'], $receiver_id, $community_text, $reply_to_message_id, $original_message, $name_sender, $image_path])) {
                header("Location: dashboard.php"); // Redirect to refresh the page and show the new message
                exit();
            } else {
                echo "Error sending community message.";
            }
        }
    }
    if (isset($_FILES['virificateimage']) && $_FILES['virificateimage']['error'] === UPLOAD_ERR_OK) {
        $imageTmpPath = $_FILES['virificateimage']['tmp_name'];
        $imageExtension = strtolower(pathinfo($_FILES['virificateimage']['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($imageExtension, $allowedExtensions)) {
            $imageName = uniqid('proof_', true) . '.' . $imageExtension;
            $uploadDir = "../vireficationimage/";
            $targetPath = $uploadDir . $imageName;
    
            if (!move_uploaded_file($imageTmpPath, $targetPath)) {
                die("Failed to upload image.");
            }
        } else {
            die("Invalid file format.");
        }
    }
    $adminId = $_SESSION['user_id'];
    // Fetch users
    $stmtl = $pdo->prepare("SELECT * FROM users WHERE public = 'public'");
    $stmtl->execute();
    $userss = $stmtl->fetchAll(PDO::FETCH_ASSOC);

    $js_traders = [];

    foreach ($userss as $user) {
        // Fetch journal trades for each user
        $stmtl = $pdo->prepare("SELECT * FROM journal WHERE id_users = :userId ORDER BY date_journal ASC");
        $stmtl->execute(['userId' => $user['id']]);
        $trades = $stmtl->fetchAll(PDO::FETCH_ASSOC);

        $track = [];
        $chartData = [];
        $winCount = 0;
        $totalTrades = 0; // will count only valid trades
        $totalProfit = 0;
        $initialEquity = $user['capital'] ?? 0;

        foreach ($trades as $trade) {
            // Skip trades with null entry completely
            if (is_null($trade['entry'])) {
                continue;
            }

            // Add only valid trades
            $track[] = [
                'time' => date('H:i', strtotime($trade['date_journal'])),
                'pair' => $trade['pair'],
                'entry' => $trade['entry'],
                'sl' => $trade['sl'],
                'tp' => $trade['tp'],
                'close' => $trade['close_journal']
            ];

            $chartData[] = $trade['close_journal'];

            $totalTrades++; // count only valid trades

            // Win rate calculation
            if ($trade['close_journal'] > 0) {
                $winCount++;
            }

            // Profit calculation
            $totalProfit += $trade['close_journal']; // already in money
        }

        $winRate = $totalTrades > 0 ? round(($winCount / $totalTrades) * 100) . '%' : 'N/A';
        $profitPercent = $initialEquity > 0 ? round(($totalProfit / $initialEquity) * 100, 2) . '%' : '0%';

        $js_traders[] = [
            'id' => $user['id'],
            'name' => $user['fullname'],
            'winRate' => $winRate,
            'equity' => '$' . $user['capital'],
            'profit' => $profitPercent,
            'profitColor' => $totalProfit >= 0 ? 'green' : 'red',
            'track' => $track,
            'chartData' => $chartData
        ];
    }


    // Fetch specific user details
    $userId = isset($_GET['id_users']) ? intval($_GET['id_users']) : 0;
    $query = $pdo->prepare("SELECT capital FROM users WHERE id = :id");
    $query->bindParam(':id', $userId, PDO::PARAM_INT);
    $query->execute();
    $user = $query->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Sharp" rel="stylesheet">
    <link rel="stylesheet"href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Sharp" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="../Css/dashboarde.css">
    <title>ForexPeak</title>
</head>
<body>
    <div class="container">
        
        <aside>
            <div class="toggle">
                <br>
                <div class="logo">
                    <!-- <img src="images/logo.png"> -->
                    <h1>Forex<span class="danger">Peak</span></h1>
                </div>
                <div class="close" id="close-btn">
                    <span class="material-icons-sharp">close</span>
                </div>
            </div>

            <div class="sidebar">
                <a id="AnalyticsLink" class="nav-link active" href="#" >
                    <span class="material-icons-sharp">insights</span>
                    <h3 data-arabe="تحليلات" data-france="Analytique" data-english="Analytics">Analytics</h3>
                </a>
                <a id="HistoryLink" class="nav-link" href="#" >
                    <span class="material-icons-sharp">receipt_long</span>
                    <h3 data-arabe="تاريخ" data-france="Historique" data-english="History">History</h3>
                </a>
                <a id="UsersLink" class="nav-link" href="#" >
                    <span class="material-symbols-outlined">function</span>
                    <h3 data-arabe="وظيفة" data-france="Fonction" data-english="Function">Function</h3>
                </a>
                <a id="InventoryLink" class="nav-link" href="#" >
                    <span class="material-icons-sharp">candlestick_chart</span>
                    <h3 data-arabe="تريدينغ فيو" data-france="TradingView" data-english="TradingView">TradingView</h3>
                </a>
                <a id="BacktestingLink" class="nav-link" href="#" >
                    <span class="material-symbols-outlined">chart_data</span>        
                    <h3 data-arabe="اختبر معاملاتك" data-france="Backtest" data-english="Backtest">Backtest</h3>
                </a>
                <a id="TicketsLink" class="nav-link" href="#" >
                    <span class="material-icons-sharp">newspaper</span>
                    <h3 data-arabe="أخبار" data-france="Actualités" data-english="News">News</h3>
                </a>
                <a id="CommunityLink" class="nav-link" href="#" >
                    <span class="material-icons-sharp">forum</span>
                    <h3 data-arabe="مجتمع" data-france="communauté" data-english="community">community</h3>
                </a>
                <a id="OthersLink" class="nav-link" href="#" >
                    <span class="material-icons-sharp">verified_user</span>
                    <h3 data-arabe="خبرة" data-france="expertse" data-english="expers">expers </h3>
                </a>
                <a id="SettingsLink" class="nav-link" href="#" >
                    <span class="material-icons-sharp">contact_support</span>
                    <h3 data-arabe="الدعم" data-france="Support" data-english="Support">Support</h3>
                </a>
                <a id="ReportsLink" class="nav-link" href="#" >
                    <span class="material-icons-sharp">settings</span>
                    <h3 data-arabe="الإعدادات" data-france="Paramètres" data-english="Settings">Settings</h3>
                </a>
                <a id="#" onclick="window.location.href='../System/logout.php'" class="nav-link2">
                    <span class="material-icons-sharp">logout</span>
                    <h3  data-arabe="تسجيل الخروج" data-france="Déconnexion" data-english="Logout">Logout</h3>
                </a>
            </div>
        </aside>
        <main id="AnalyticsMain" style="display: block;">
            <h1 data-arabe="تحليلات" data-france="Analytique" data-english="Analytics">Analytics</h1>
            
            <!-- Analyses -->
            <div class="analyse">
                
                <div class="searches">
                    <div class="status">
                        <div class="info">
                            <h3 data-arabe="نسبة النجاح" data-france="Taux de réussite" data-english="Win Rate">Win Rate</h3>
                            <h1 id="positiveClosePercentage">0%</h1>
                        </div>
                        <div class="skill">
                            <div class="outer">
                                <div class="inner">
                                    <div id="number1">0%</div>
                                </div>
                            </div>

                            <svg xmlns="http://www.w3.org/2000/svg" version="1.1" width="160px" height="160px">
                                <defs>
                                    <linearGradient id="GradientColor1">
                                        <stop offset="0%" stop-color="#bfff47" />
                                        <stop offset="100%" stop-color="#00ff2a" />
                                    </linearGradient>
                                </defs>
                                <circle id="circle1" cx="80" cy="80" r="70" stroke-linecap="round" />
                            </svg>
                        </div>
                    </div>
                </div>
                <div class="sales">
                    <div class="status">
                        <div class="info">
                            <h3 data-arabe="الربح" data-france="bénéfice" data-english="Benefits"> Benefits</h3>
                            <h1 id="totalCloseDisplay">$00,00</h1>
                            <h3 data-arabe="رأس المال" data-france="Balance" data-english="Balance">Balance</h3>
                            <h1 id="totalEquityDisplay">$00,00</h1>
                        </div>
                        <div class="skill">
                            <div class="outer">
                                <div class="inner">
                                    <div id="number3">0%</div>
                                </div>
                            </div>

                            <svg xmlns="http://www.w3.org/2000/svg" version="1.1" width="160px" height="160px">
                                <defs>
                                    <linearGradient class="GradientColor3" id="GradientColor1">
                                        <stop offset="0%" stop-color="#bfff47" />
                                        <stop offset="100%" stop-color="#00ff2a" />
                                    </linearGradient>
                                </defs>
                                <circle id="circle3" cx="80" cy="80" r="70" stroke-linecap="round" />
                            </svg>
                        </div>
                    </div>
                </div>
                <div class="visits">
                    <div class="status">
                        <div class="info">
                            <h3 data-arabe="نسبة الربح" data-france="Taux de profit" data-english="Profit rate">Profit rate</h3>
                            <h1 id="closeCapitalPercentage">0%</h1>
                        </div>
                        <div class="skill">
                            <div class="outer">
                                <div class="inner">
                                    <div id="number2">0%</div>
                                </div>
                            </div>

                            <svg xmlns="http://www.w3.org/2000/svg" version="1.1" width="160px" height="160px">
                                <defs>
                                    <linearGradient class="GradientColor2" id="GradientColor1">
                                        <stop offset="0%" stop-color="#bfff47" />
                                        <stop offset="100%" stop-color="#00ff2a" />
                                    </linearGradient>
                                </defs>
                                <circle id="circle2" cx="80" cy="80" r="70" stroke-linecap="round" />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Big Calendar Section -->
            <div class="new-users">
                <h2 data-arabe="ملف التداول" data-france="Journal de trading" data-english="Trades Journal">Trades Journal</h2>
                <br>
                <div class="data-header">
                    
                    <button onclick="showMoreRows()" data-arabe="حذف صفقة" data-france="Supprimer un trade" data-english="DELETE TRADE">DELETE TRADE</button>
                    <button id="prevMonth">&lt;</button>
                    <select id="monthSelect"></select>
                    <select id="yearSelect"></select>
                    <button id="nextMonth">&gt;</button>
                    <button id="todayButton" data-arabe="اليوم" data-france="Aujourd'hui" data-english="Today">Today</button>
                </div>
                <div class="dash">
                    <div class="calendar">
                        <div class="calendar-header">
                            <div>Sun</div>
                            <div>Mon</div>
                            <div>Tue</div>
                            <div>Wed</div>
                            <div>Thu</div>
                            <div>Fri</div>
                            <div>Sat</div>
                        </div>
                        <div class="calendar-grid" id="calendarGrid"></div>
                    </div>
                    <div class="table-container">
                        <table class="journal-table" id="journalTable">
                            <thead>
                                <tr>
                                    <th class="table-header" data-arabe="التاريخ" data-france="Date" data-english="Date">Date</th>
                                    <th class="table-header" data-arabe="زوج الفوركس" data-france="Paire de Forex" data-english="Forex Pair">Forex Pair</th>
                                    <th class="table-header" data-arabe="الربح ($)" data-france="Profit ($)" data-english="Profit ($)">Profit ($)</th>
                                </tr>
                            </thead>
                            <tbody class="minitable">
                                <!-- Data rows will be inserted here dynamically -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <h2 data-arabe="آخر 3 أشهر" data-france="les 3 derniers mois" data-english="last 3 month">last 3 month</h2>
            <!-- Mini Calendar -->
            <div id="miniCalendars"></div>
            <!-- Recent Orders Table -->
            <div class="recent-orders">
                <h2 data-arabe="الطلبات" data-france="Les meilleures commandes" data-english="The Best Orders">The Best Orders</h2>
                <table>
                    <thead>
                        <tr>
                            <th data-arabe="التاريخ" data-france="Date" data-english="Date">Date</th>
                            <th data-arabe="زوج الفوركس" data-france="Paire de Forex" data-english="Forex Pair">Forex Pair</th>
                            <th data-arabe="الربح" data-france="Benefices" data-english="Benefits">Benefits</th>
                            <th data-arabe="النسبة المئوية" data-france="Pourcentage" data-english="Percentage">Percentage</th>
                        </tr>
                    </thead>
                    <tbody class="thebesttable">
                        <!-- Data will be appended here via JavaScript -->
                    </tbody>
                </table>
                <br>
                <h2 data-arabe="إجمالي الربح في زوج واحد" data-france="Profit total dans une paire" data-english="Total Profit In One Pair">Total Profit In One Pair</h2>
                <table>
                    <thead>
                        <tr>
                            <th data-arabe="زوج الفوركس" data-france="Paire de Forex" data-english="Forex Pair">Forex Pair</th>
                            <th data-arabe=" الربح" data-france="le profit" data-english="Profit">Profit</th>
                            <th data-arabe="النسبة المئوية" data-france="Pourcentage" data-english="Percentage">Percentage</th>
                        </tr>
                    </thead>
                    <tbody class="AllProfit">
                        <!-- Data will be appended here via JavaScript -->
                    </tbody>
                </table>
                <br>
                <h2 data-arabe="إجمالي الربح لسنة وشهر" data-france="Profit total par an et par mois" data-english="Total year&month Profit">Total year&month Profit</h2>
                <select class="custom-select" style="width: 20%;" id="yearSelector" onchange="switchView()">
                    <option value="all" data-arabe="جميع السنوات" data-france="Toutes les années" data-english="All Years">All Years</option>
                </select>
                <br><br>
                <!-- HTML for Total Close Table -->
                <table id="totalCloseTable">
                    <thead>
                        <tr>
                            <th data-arabe="شهر/سنة" data-france="Mois/Année" data-english="Month/Year">Month/Year</th>
                            <th data-arabe="الربح الإجمالي" data-france="Benefice totale" data-english="Total Benefits">Total Benefits</th>
                            <th data-arabe="النسبة المئوية" data-france="Pourcentage" data-english="Percentage">Percentage</th>
                        </tr>
                    </thead>
                    <tbody class="AllProfit"></tbody>
                </table>
                <br>
                <a href="#"><span class="material-icons-sharp">arrow_upward</span></a>
                <br>
            </div>
        </main>
        <main id="HistoryMain" style="display: none;">
            <h1 data-arabe="تاريخ" data-france="Histoire" data-english="History">History</h1>
            <div class="custom-select" style="display:none;width:200px;">
                <select>
                    <option value="english" selected data-arabe="اختر" data-france="Choisir" data-english="Select">Select</option>
                    <option value="option1" data-arabe="خيار 1" data-france="Option 1" data-english="Option 1">Option 1</option>
                    <option value="option2" data-arabe="خيار 2" data-france="Option 2" data-english="Option 2">Option 2</option>
                    <option value="option3" data-arabe="خيار 3" data-france="Option 3" data-english="Option 3">Option 3</option>
                </select>
            </div>
            <br>
            <div class="analyse1">
                <div id="chart-container">
                    <canvas id="area-chart" class="chartt"></canvas>
                </div>
                <div id="chart-container">
                    <canvas id="bar-chart" class="chartt"></canvas>
                </div>
                <div id="chart-container">
                    <canvas id="range-spline-chart" class="chartt"></canvas>
                </div>
                <div id="chart-container">
                    <canvas id="radar-chart" class="chartt"></canvas>
                </div>
            </div>
            <br>
            <br>            
            <h3 data-arabe="نظرة عامة" data-france="Aperçu" data-english="Overview">Overview</h3>
            <br>
            <div style="align-items: flex-start; flex-direction: column;">
                <ul class="Overview">
                    <div id="average-time-card">
                        <h4 
                            data-arabe="متوسط الوقت: <?php echo abs($averageTimeInHours ?? '0'); ?> ساعة" 
                            data-france="Temps moyen: <?php echo abs($averageTimeInHours ?? '0'); ?> heures" 
                            data-english="Average Time: <?php echo abs($averageTimeInHours ?? '0'); ?> hours">
                            Average Time: <?php echo abs($averageTimeInHours ?? '0'); ?> hours
                        </h4>
                    </div>

                    <!-- Total Trades Card -->
                    <div id="total-trades-card">
                        <h4 id="totalTread" 
                            data-arabe="إجمالي التداول: <?php echo $totalTrades ?? '0'; ?>" 
                            data-france="Total des échanges: <?php echo $totalTrades ?? '0'; ?>" 
                            data-english="Total Trades: <?php echo $totalTrades ?? '0'; ?>">
                            Total Trades: <?php echo $totalTrades ?? '0'; ?>
                        </h4>
                        <div id="progress-bar-total-trades">
                            <div id="progress-total-trades"></div>
                        </div>
                    </div>

                    <!-- Missed Trades Card -->
                    <div id="missed-trades-card">
                        <h4 id="MissedTread" 
                            data-arabe="التداولات المفقودة: <?php echo $missedTrades ?? '0'; ?>" 
                            data-france="Echanges manqués: <?php echo $missedTrades ?? '0'; ?>" 
                            data-english="Missed Trades: <?php echo $missedTrades ?? '0'; ?>">
                            Missed Trades: <?php echo $missedTrades ?? '0'; ?>
                        </h4>
                    </div>

                    <!-- Average RR Card -->
                    <div id="average-rr-card">
                        <h4 id="average-rr-value" 
                            data-arabe="متوسط قيمة RR: <?php echo $averageRR ?? '0'; ?>" 
                            data-france="Valeur moyenne RR: <?php echo $averageRR ?? '0'; ?>" 
                            data-english="Average RR Value: <?php echo $averageRR ?? '0'; ?>">
                            Average RR Value: <?php echo $averageRR ?? '0'; ?>
                        </h4>
                    </div>
                </ul>
            </div>
            <br>
            <div class="overviewtow">
                <!-- New Overview Section -->
                <div id="risk-reward-overview">
                    <h3>Risk & Reward Overview</h3>
                    <ul>
                        <li>
                            <h4 id="biggest-win" 
                                data-arabe="أكبر ربح: $0.00" 
                                data-france="Plus grand gain: $0.00" 
                                data-english="Biggest Win: $0.00">
                                Biggest Win: $0.00
                            </h4>
                        </li>
                        <li>
                            <h4 id="biggest-loss" 
                                data-arabe="أكبر خسارة: $0.00" 
                                data-france="Plus grande perte: $0.00" 
                                data-english="Biggest Loss: $0.00">
                                Biggest Loss: $0.00
                            </h4>
                        </li>
                        <li>
                            <h4 id="highest-rr" 
                                data-arabe="أعلى نسبة RR: 0.00" 
                                data-france="Ratio RR le plus élevé: 0.00" 
                                data-english="Highest RR: 0.00">
                                Highest RR: 0.00
                            </h4>
                        </li>
                        <li>
                            <h4 id="rr-in-biggest-win" 
                                data-arabe="نسبة RR في أكبر ربح: 0.00" 
                                data-france="Ratio RR dans le plus grand gain: 0.00" 
                                data-english="RR in Biggest Win: 0.00">
                                RR in Biggest Win: 0.00
                            </h4>
                        </li>
                        <li>
                            <h4 id="rr-in-biggest-loss" 
                                data-arabe="نسبة RR في أكبر خسارة: 0.00" 
                                data-france="Ratio RR dans la plus grande perte: 0.00" 
                                data-english="RR in Biggest Loss: 0.00">
                                RR in Biggest Loss: 0.00
                            </h4>
                        </li>
                    </ul>
                </div>
                <!-- Drawdown Stats Section -->
                <div id="drawdown-stats">
                    <h3>Drawdown Stats</h3>
                    <ul>
                        <li>
                            <h4 id="average-drawdown" 
                                data-arabe="متوسط السحب: 0.00%" 
                                data-france="Drawdown moyen: 0.00%" 
                                data-english="Average Drawdown: 0.00%">
                                Average Drawdown: 0.00%
                            </h4>
                        </li>
                    </ul>
                    <h3>Profitable RR Stats</h3>
                    <ul>
                        <li>
                            <h4 id="average-profitable-rr" 
                                data-arabe="متوسط RR المربح: 0.00" 
                                data-france="RR moyen rentable: 0.00" 
                                data-english="Average Profitable RR: 0.00">
                                Average Profitable RR: 0.00
                            </h4>
                        </li>
                    </ul>
                    <h3>Max Potential Profit Stats</h3>
                    <ul>
                        <li>
                            <h4 id="average-max-potential-profit" 
                                data-arabe="متوسط أقصى ربح محتمل: 0.00%" 
                                data-france="Profit potentiel maximum moyen: 0.00%" 
                                data-english="Average Max Potential Profit: 0.00%">
                                Average Max Potential Profit: 0.00%
                            </h4>
                        </li>
                    </ul>
                </div>

                <!-- Best/Worst Trade Times Section -->
                <div id="trade-times-stats">
                    <h3>Trade Times</h3>
                    <ul>
                        <li>
                            <h4 id="best-trade-time" 
                                data-arabe="أفضل وقت للتداول: 00:00" 
                                data-france="Meilleur moment pour trader: 00:00" 
                                data-english="Best Trade Time: 00:00">
                                Best Trade Time: 00:00
                            </h4>
                        </li>
                        <li>
                            <h4 id="worst-trade-time" 
                                data-arabe="أسوأ وقت للتداول: 00:00" 
                                data-france="Pire moment pour trader: 00:00" 
                                data-english="Worst Trade Time: 00:00">
                                Worst Trade Time: 00:00
                            </h4>
                        </li>
                        <li>
                            <h4 id="average-best-trade-time" 
                                data-arabe="متوسط أفضل وقت للتداول: 00:00" 
                                data-france="Moyenne du meilleur moment pour trader: 00:00" 
                                data-english="Average Best Trade Time: 00:00">
                                Average Best Trade Time: 00:00
                            </h4>
                        </li>
                        <li>
                            <h4 id="average-worst-trade-time" 
                                data-arabe="متوسط أسوأ وقت للتداول: 00:00" 
                                data-france="Moyenne du pire moment pour trader: 00:00" 
                                data-english="Average Worst Trade Time: 00:00">
                                Average Worst Trade Time: 00:00
                            </h4>
                        </li>
                    </ul>
                </div>

                <!-- Add this section inside the <div id="profitable-rr-stats"> -->
                <div id="streak-probability-stats">
                    <h3>Streak Probability Stats (%)</h3>
                    <ul>
                        <li>
                            <h4 id="total-losing-trades" 
                                data-arabe="إجمالي الصفقات الخاسرة: 0" 
                                data-france="Total des transactions perdantes: 0" 
                                data-english="Total Losing Trades: 0">
                                Total Losing Trades: 0
                            </h4>
                        </li>
                        <li>
                            <h4 id="total-winning-trades" 
                                data-arabe="إجمالي الصفقات الرابحة: 0" 
                                data-france="Total des transactions gagnantes: 0" 
                                data-english="Total Winning Trades: 0">
                                Total Winning Trades: 0
                            </h4>
                        </li>
                        <li>
                            <h4 id="best-probability-losing-streak" 
                                data-arabe="أفضل احتمالية للخسارة المتتالية: 0.00%" 
                                data-france="Meilleure probabilité de perte consécutive: 0.00%" 
                                data-english="Best Probability of Losing Streak: 0.00%">
                                Best Probability of Losing Streak: 0.00%
                            </h4>
                        </li>
                        <li>
                            <h4 id="worst-probability-losing-streak" 
                                data-arabe="أسوأ احتمالية للخسارة المتتالية: 0.00%" 
                                data-france="Pire probabilité de perte consécutive: 0.00%" 
                                data-english="Worst Probability of Losing Streak: 0.00%">
                                Worst Probability of Losing Streak: 0.00%
                            </h4>
                        </li>
                        <li>
                            <h4 id="best-probability-winning-streak" 
                                data-arabe="أفضل احتمالية للربح المتتالي: 0.00%" 
                                data-france="Meilleure probabilité de gain consécutif: 0.00%" 
                                data-english="Best Probability of Winning Streak: 0.00%">
                                Best Probability of Winning Streak: 0.00%
                            </h4>
                        </li>
                        <li>
                            <h4 id="worst-probability-winning-streak" 
                                data-arabe="أسوأ احتمالية للربح المتتالي: 0.00%" 
                                data-france="Pire probabilité de gain consécutif: 0.00%" 
                                data-english="Worst Probability of Winning Streak: 0.00%">
                                Worst Probability of Winning Streak: 0.00%
                            </h4>
                        </li>
                    </ul>
                </div>

                <div id="payout-trades">
                    <h3>payout deposit commission</h3>
                    <ul>
                        <li>
                            <h4 id="total-payout-trades" 
                                data-arabe="إجمالي الدفع: 0" 
                                data-france="Total des transactions paiement: 0" 
                                data-english="Total payout Trades: 0">
                                Total payout Trades: 0
                            </h4>
                        </li>
                        <li>
                            <h4 id="total-deposit-trades" 
                                data-arabe="إجمالي إيداع: 0" 
                                data-france="Total des transactions dépôt: 0" 
                                data-english="Total deposit Trades: 0">
                                Total deposit Trades: 0
                            </h4>
                        </li>
                        <li>
                            <h4 id="total-commission-trades" 
                                data-arabe="إجمالي عمولة: 0" 
                                data-france="Total des transactions commission: 0" 
                                data-english="Total commission Trades: 0">
                                Total commission Trades: 0
                            </h4>
                        </li>
                        <li>
                            <h4 id="best-payout-trade" 
                                data-arabe="أفضل دفع: 0" 
                                data-france="Meilleur paiement: 0" 
                                data-english="Best payout Trade: 0">
                                Best payout Trade: 0
                            </h4>
                        </li>
                        <li>
                            <h4 id="worst-payout-trade" 
                                data-arabe="أسوأ دفع: 0" 
                                data-france="Pire paiement: 0" 
                                data-english="Worst payout Trade: 0">
                                Worst payout Trade: 0
                            </h4>
                        </li>
                        <li>
                            <h4 id="biggest-commission-trade" 
                                data-arabe="أكبر عمولة: 0" 
                                data-france="Plus grande commission: 0" 
                                data-english="Biggest commission Trade: 0">
                                Biggest commission Trade: 0
                            </h4>
                        </li>
                    </ul>
                </div>

                <!-- AI-genereater-stats -->
                <div id="AI-genereater-stats">
                    <h3 data-arabe="مُولِّد الذكاء الاصطناعي" 
                        data-france="Générateur d'IA" 
                        data-english="AI Generator">
                        AI Generator
                    </h3>
                    <p id="AI-genereater" 
                    data-arabe="هنا سيتم عرض المشورة والبيانات المُولدة بواسطة الذكاء الاصطناعي." 
                    data-france="Ici, des conseils et des données générées par l'IA seront affichés." 
                    data-english="Here, advice and data generated by AI will be displayed.">
                    Here, advice and data generated by AI will be displayed.
                    </p>
                </div>

            </div>
            <br>
            <h3>payout table</h3>
            <table id="payout-trade-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Payout</th>
                                <th>Payout (%)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Payout trades will be dynamically added here -->
                        </tbody>
            </table>
            <br>
            <h3>payout image</h3>
            <table id="payout-image-table">
                        <tbody class="slidetbody">
                            <!-- Payout trades will be dynamically added here -->
                        </tbody>
            </table>
            <br>
            <h3 data-arabe="الإحصائيات" data-france="Statistiques" data-english="Statistics">Statistc</h3>
            <br>
            <canvas id="chart"></canvas>
            <br>
            <h3 data-arabe="المخاطرة مقابل المكافأة في جميع الصفقات" data-france="rapport risque/récompense de toutes les transactions" data-english="risk to reward of all trades">risk to reward of all trades</h3>
            <br>
            <canvas id="rr-chart" width="400" height="200"></canvas>
            <br>
            <h3 data-arabe="الإحصائيات الشهرية" data-france="Statistiques mensuelles" data-english="Monthly statistics">Monthly statistics</h3>
            <br>
            <canvas id="monthlyBarChart" width="400" height="200"></canvas>
            <br>
            <div class="Comprehensivecalender">
                <h3 data-arabe="تقويم شامل" data-france="Calendrier complet" data-english="Comprehensive calendar">Comprehensive calendar</h3>
                <br>
                <div class="controlsbtn">
                    <button class="buttonn" id="prevYearbtn"><</button>
                    <span id="yearValue"></span>
                    <button class="buttonn" id="nextYearbtn">></button>
                </div>
                <div id="miniCalendarsYears"></div>
            </div>
            <div class="recent-orders">
                <h3 data-arabe="جميع الطلبات" data-france="toutes les commandes" data-english="all Orders">all Orders</h3>
                <table>
                    <thead>
                        <tr>
                            <th class="table-header sortable" data-sort="date_journal">Date</th>
                            <th class="table-header sortable" data-sort="date_close">Date Close</th>
                            <th class="table-header sortable" data-sort="pair">Forex Pair</th>
                            <th class="table-header sortable" data-sort="trade_type">Trade Type</th>
                            <th class="table-header sortable" data-sort="entry">Entry</th>
                            <th class="table-header sortable" data-sort="tp">Take Profit</th>
                            <th class="table-header sortable" data-sort="sl">SL Profit ($)</th>
                            <th class="table-header sortable" data-sort="close_journal">Short/Long</th>
                            <th class="table-header">Delete</th>
                        </tr>
                    </thead>
                    <tbody id="trackrecordall" class="hestoritable">
                        <!-- Data rows will be inserted here dynamically -->
                    </tbody>
                </table>
                <br>
                <a href="#"><span class="material-icons-sharp">arrow_upward</span></a>
                <br>
            </div>
        </main>
        <main id="UsersMain" style="display: none;">
            <h1 data-arabe="وظيفة" data-france="Fonction" data-english="Function">Function</h1>
            <div class="tab-container" style="height:auto;">
                <div class="tabs">
                    <button class="tab-button active" onclick="switchTab(0)" data-arabe="حساب النقاط" data-france="Calculer Pips" data-english="Calculate Pips">
                        <i class="ri-apps-2-line"></i> Calculate Pips
                    </button>
                    <button class="tab-button" onclick="switchTab(1)" data-arabe="مكافأة المخاطرة" data-france="Risque Récompense" data-english="Risk Reward">
                        <i class="ri-apps-2-line"></i> Risk Reward
                    </button>
                    <button class="tab-button" onclick="switchTab(2)" data-arabe="حجم اللوت" data-france="Taille du Lot" data-english="Lot Size">
                        <i class="ri-apps-2-line"></i> Lot Size
                    </button>
                    <button class="tab-button" onclick="switchTab(3)" data-arabe="آلة حاسبة تراكمية" data-france="Calculateur de Compounding" data-english="Compounding Calculator">
                        <i class="ri-apps-2-line"></i> Compounding Calculator
                    </button>
                    <button class="tab-button" onclick="switchTab(4)" data-arabe="آلة حاسبة مارتينجال" data-france="Calculateur de Martingale" data-english="Martingale Calculator">
                        <i class="ri-apps-2-line"></i> Martingale Calculator
                    </button>
                </div>
                <div>
                    <div class="tab-content" data-tab-content="calc1" style="display: block;">
                        <!-- Content for Calculate Pips -->
                        <form action="" class="containe">
                            <h5 data-arabe="حساب النقاط " data-france="Calculer les Pips" data-english="Calculate Pips">Calculate Pips</h5>
                            <div class="switch-container">
                                <span id="mode" data-arabe="شراء" data-france="Achat" data-english="Buy">Buy</span>
                                <label class="switch">
                                    <input type="checkbox" id="buySellSwitch">
                                    <div class="slider"></div>
                                    <div class="slider-card">
                                        <div class="slider-card-face slider-card-front"></div>
                                        <div class="slider-card-face slider-card-back"></div>
                                    </div>
                                </label>
                                <span id="mode" data-arabe="بيع" data-france="Vente" data-english="Sell">Sell</span>
                            </div>
                            <div class="split">
                                <div class="label">
                                    <label for="EntrePips" data-arabe="أدخل السعر" data-france="Entrez le prix" data-english="Enter price">Enter price</label>
                                    <input class="input-field" type="number" id="EntrePips" placeholder="Enter your price" required>
                                </div>
                                <div class="label">
                                    <label for="TargetPips" data-arabe="أدخل السعر المستهدف" data-france="Entrez le prix cible" data-english="Target price">Target price</label>
                                    <input class="input-field" type="number" id="TargetPips" placeholder="Enter target price" required>
                                </div>
                            </div>
                            <div class="label">
                                <label for="stopLossPips" data-arabe="أدخل سعر وقف الخسارة" data-france="Entrez le prix Stop-Loss" data-english="Stop Loss price">Stop Loss price</label>
                                <input class="input-field" type="number" id="stopLossPips" placeholder="Enter stop loss price" required>
                            </div>
                            <h5 id="Targetresult" data-arabe="نقاط الهدف: 00.00" data-france="Pips Cible: 00.00" data-english="Target Pips: 00.00">Target Pips: 00.00</h5>
                            <h5 id="Stoplossresult" data-arabe="نقاط وقف الخسارة: 00.00" data-france="Pips Stop-Loss: 00.00" data-english="Stop Loss Pips: 00.00">Stop Loss Pips: 00.00</h5>
                            <button type="button" class="checkout-btn" data-arabe="حساب النقاط" data-france="Calculer les Pips" data-english="Calculate Pips" onclick="calculatePips()">Calculate Pips</button>
                        </form>
                    </div>
                    <div class="tab-content" data-tab-content="calc2" style="display: none;">
                        <form class="containe" id="riskRewardForm">
                            <h1 data-arabe="أداة نسبة المخاطر إلى المكافأة" data-france="Outil de ratio de risque à récompense" data-english="Risk to Reward Ratio Tool">Risk to Reward Ratio Tool</h1>
                            <h5 data-arabe="سعر الدخول:" data-france="Prix d'entrée:" data-english="Entry Price:">Entry Price:</h5>
                            <input type="number" class="input-field" id="entryriskReward" step="0.01" required>
                            <h5 data-arabe="سعر وقف الخسارة:" data-france="Prix de stop-loss:" data-english="Stop-Loss Price:">Stop-Loss Price:</h5>
                            <input type="number" class="input-field" id="stopLossriskReward" step="0.01" required>
                            <h5 data-arabe="سعر الهدف:" data-france="Prix cible:" data-english="Target Price:">Target Price:</h5>
                            <input type="number" class="input-field" id="targetriskReward" step="0.1" required>
                            <br><br>
                            <button type="button" class="checkout-btn" onclick="updateVisualization()" 
                                    data-arabe="تحديث التصور" data-france="Mettre à jour la visualisation" data-english="Update Visualization">
                                Update Visualization
                            </button>
                            <br>
                            <h1 id="riskRewardRatio" 
                                data-arabe="نسبة المخاطر إلى المكافأة: غير متاحة" 
                                data-france="Ratio de risque à récompense: N/A" 
                                data-english="Risk to Reward Ratio: N/A">
                                Risk to Reward Ratio: N/A
                            </h1>
                            <div id="visualizationContainer">
                                <div id="stopLossDiv"></div>
                                <div id="targetDiv"></div>
                            </div>
                        </form>
                    </div>
                    <div class="tab-content" data-tab-content="calc3" style="display: none;">
                        <form action="" class="containe">
                            <h5 data-arabe="احسب حجم العقد الخاص بك" data-france="Calculez votre taille de lot" data-english="Calculate your lot size">Calculate your lot size</h5>
                            <div class="split">
                                <div class="label">
                                    <label for="accountBalance" 
                                        data-arabe="رصيد الحساب ($)" 
                                        data-france="Solde du compte ($)" 
                                        data-english="Account Balance ($)">Account Balance ($)</label>
                                    <input class="input-field" type="number" id="accountBalance"
                                        placeholder="Enter your account balance" required>
                                </div>
                                <div class="label">
                                    <label for="riskPercentage" 
                                        data-arabe="نسبة المخاطر (%)" 
                                        data-france="Pourcentage de risque (%)" 
                                        data-english="Risk Percentage (%)">Risk Percentage (%)</label>
                                    <input class="input-field" type="number" id="riskPercentage"
                                        placeholder="Enter your risk percentage" required>
                                </div>
                            </div>
                            <div class="label">
                                <label for="stopLoss" 
                                    data-arabe="وقف الخسارة (نقاط)" 
                                    data-france="Stop Loss (Pips)" 
                                    data-english="Stop Loss (Pips)">Stop Loss (Pips)</label>
                                <input class="input-field" type="number" id="stopLoss"
                                    placeholder="Enter your stop loss in pips" required>
                            </div>
                            <h5 id="resultlot" 
                                data-arabe="حجم العقد: 0.00 لوت قياسي" 
                                data-france="Taille de lot: 0.00 lots standard" 
                                data-english="Lot Size: 0.00 standard lots">Lot Size: 0.00 standard lots</h5>
                            <button type="button" class="checkout-btn" onclick="calculateLotSize()" 
                                    data-arabe="احسب حجم العقد" data-france="Calculer la taille du lot" data-english="Calculate Lot Size">
                                Calculate Lot Size
                            </button>
                        </form>
                    </div>
                    <div class="tab-content" data-tab-content="calc4" style="display: none;">
                        <form action="" class="containe">
                            <h5 data-arabe="آلة حاسبة للتضخيم" data-france="Calculateur de capitalisation" data-english="Compounding Calculator">Compounding Calculator</h5>
                            <div class="split">
                                <div class="label">
                                    <label for="principal" 
                                        data-arabe="المبلغ الرئيسي ($):" 
                                        data-france="Montant principal ($):" 
                                        data-english="Principal Amount ($):">Principal Amount ($):</label>
                                    <input class="input-field" type="number" id="principal" placeholder="Enter your account balance" required>
                                </div>
                                <div class="label">
                                    <label for="rate" 
                                        data-arabe="معدل الفائدة السنوي (%)" 
                                        data-france="Taux d'intérêt annuel (%)" 
                                        data-english="Annual Interest Rate (%):">Annual Interest Rate (%):</label>
                                    <input class="input-field" type="number" id="rate" placeholder="Enter annual interest rate" required>
                                </div>
                            </div>
                            <div class="label">
                                <label for="time" 
                                    data-arabe="مدة الوقت (شهر):" 
                                    data-france="Période (mois):" 
                                    data-english="Time Period (month):">Time Period (month):</label>
                                <input class="input-field" type="number" id="time" placeholder="Enter time period in month" required>
                            </div>
                            <h5 id="resulte" data-arabe="" data-france="" data-english=""> <!-- Result will show here dynamically --> </h5>
                            <button type="button" class="checkout-btn" onclick="calculateCompounding()" 
                                    data-arabe="احسب" data-france="Calculer" data-english="Calculate">
                                Calculate
                            </button>
                            <br><br>
                            <div class="instructions">
                                <h4 data-arabe="كيف يعمل؟" data-france="Comment ça marche?" data-english="How does it work?">How does it work?</h4>
                                <p data-arabe="الخطوة 1: أدخل رأس مال التداول الأولي الخاص بك، وهو المبلغ الذي تخطط لبدء التداول به." data-france="Étape 1 : saisissez votre capital de trading initial, qui correspond au montant avec lequel vous prévoyez de commencer à trader." data-english="Step 1: Enter your initial trading capital, which is the amount you plan to start trading with.">Step 1: Enter your initial trading capital, which is the amount you plan to start trading with.</p>
                                <p data-arabe="الخطوة 2: أدخل متوسط ​​معدل العائد الشهري المتوقع. على سبيل المثال، إذا حققت 5% من صفقاتك كل شهر، فأدخل 5." data-france="Étape 2 : saisissez votre taux de rendement mensuel moyen attendu. Par exemple, si vous réalisez 5 % de vos transactions chaque mois, saisissez 5." data-english="Step 2: Enter your expected average monthly return rate. For example, if you make 5% on your trades each month, enter 5.">Step 2: Enter your expected average monthly return rate. For example, if you make 5% on your trades each month, enter 5.</p>
                                <p data-arabe="الخطوة 3: أدخل عدد الأشهر التي تخطط للتداول فيها أو حساب العائدات المركبة. على سبيل المثال، إذا كنت تريد رؤية النتائج على مدار عام، فأدخل 12 شهرًا." data-france="Étape 3 : saisissez le nombre de mois pendant lesquels vous prévoyez de négocier ou de capitaliser vos rendements. Par exemple, si vous souhaitez voir les résultats sur une année, saisissez 12 mois." data-english="Step 3: Input the number of months you plan to trade or compound your returns for. For instance, if you want to see the results over a year, enter 12 months.">Step 3: Input the number of months you plan to trade or compound your returns for. For instance, if you want to see the results over a year, enter 12 months.</p>
                                <p data-arabe="الخطوة 4: انقر فوق (حساب) لمعرفة مقدار نمو رأس المال الخاص بك خلال الفترة الزمنية المحددة، بما في ذلك التأثيرات المركبة." data-france="Étape 4 : Cliquez sur « Calculer » pour voir dans quelle mesure votre capital augmentera sur la période sélectionnée, y compris les effets de composition." data-english="Step 4: Click 'Calculate' to see how much your capital will grow over the selected time period, including compounding effects.">Step 4: Click 'Calculate' to see how much your capital will grow over the selected time period, including compounding effects.</p>
                            </div>
                        </form>
                    </div>
                    <div class="tab-content" data-tab-content="calc5" style="display: none;">
                        <form action="" class="containe">
                            <h5 data-arabe="آلة حاسبة مارتينجال" data-france="Calculateur de Martingale" data-english="Martingale Calculator">Martingale Calculator</h5>
                            <div class="split">
                                <div class="label">
                                    <label for="martingaleprincipal" 
                                        data-arabe="المبلغ الرئيسي ($):" 
                                        data-france="Montant principal ($):" 
                                        data-english="Principal Amount ($):">Principal Amount ($):</label>
                                    <input class="input-field" type="number" id="martingaleprincipal"
                                        placeholder="Enter your account balance" required>
                                </div>
                                <div class="label">
                                    <label for="martingalerate" 
                                        data-arabe="نسبة الصفقة (%):" 
                                        data-france="Pourcentage de l'affaire (%):" 
                                        data-english="Percentage of the deal (%):">Percentage of the deal (%):</label>
                                    <input class="input-field" type="number" id="martingalerate"
                                        placeholder="Enter percentage of the deal" required>
                                </div>
                            </div>
                            <div class="label">
                                <label for="martingalelosses" 
                                    data-arabe="عدد الخسائر:" 
                                    data-france="Nombre de pertes:" 
                                    data-english="Number of losses:">Number of losses:</label>
                                <input class="input-field" type="number" id="martingalelosses"
                                    placeholder="Enter number of losses" required>
                            </div>
                            <h5 id="resultmartingale" 
                                data-arabe="المبلغ المطلوب الكلي: 0.00" 
                                data-france="Montant total requis: 0.00" 
                                data-english="Total Required Amount: 0.00">Total Required Amount: 0.00</h5>
                            <button type="button" class="checkout-btn" onclick="calculateMartingale()" 
                                    data-arabe="احسب" data-france="Calculer" data-english="Calculate">
                                Calculate
                            </button>
                            <br><br>
                            <div class="description">
                                <p data-arabe="استراتيجية <strong>مارتينجال</strong> تُستخدم عادةً من قبل المتداولين لاسترداد الخسائر عن طريق مضاعفة حجم الصفقة بعد كل خسارة. الفكرة هي أنه عندما تفوز في النهاية، فإن الربح من الصفقة الرابحة سيغطي جميع الخسائر السابقة بالإضافة إلى تحقيق ربح يساوي حجم الصفقة الأولية."
                                data-france="La stratégie de <strong>Martingale</strong> est couramment utilisée par les traders pour récupérer des pertes en doublant la taille du trade après chaque perte. L'idée est que lorsque vous gagnez finalement, le profit du trade gagnant couvrira toutes les pertes précédentes, et en plus fera un profit égal à votre taille de trade initiale."
                                data-english="The <strong>Martingale Strategy</strong> is commonly used by traders to recover losses by doubling the size of the trade after each loss. The idea is that when you eventually win, the profit from the winning trade will cover all the previous losses, plus make a profit equal to your initial trade size.">
                                    The <strong>Martingale Strategy</strong> is commonly used by traders to recover losses by doubling the size of the trade after each loss. The idea is that when you eventually win, the profit from the winning trade will cover all the previous losses, plus make a profit equal to your initial trade size.
                                </p>
                                
                                <p data-arabe="إليك كيف يعمل:"
                                data-france="Voici comment cela fonctionne :"
                                data-english="Here’s how it works:">
                                    Here’s how it works:
                                </p>
                                
                                <ul>
                                    <li data-arabe="ابدأ بحجم صفقة أساسي (على سبيل المثال، 10 دولارات)." 
                                        data-france="Commencez avec une taille de trade de base (par exemple, 10 $)."
                                        data-english="Start with a base trade size (e.g., $10).">
                                        Start with a base trade size (e.g., $10).
                                    </li>
                                    <li data-arabe="إذا خسرت، ضاعف حجم الصفقة (على سبيل المثال، إلى 20 دولارًا)." 
                                        data-france="Si vous perdez, doublez la taille du trade (par exemple, à 20 $)."
                                        data-english="If you lose, double your trade size (e.g., to $20).">
                                        If you lose, double your trade size (e.g., to $20).
                                    </li>
                                    <li data-arabe="إذا خسرت مرة أخرى، ضاعف حجم الصفقة مرة أخرى (على سبيل المثال، إلى 40 دولارًا)، وهكذا." 
                                        data-france="Si vous perdez à nouveau, doublez à nouveau la taille du trade (par exemple, à 40 $), et ainsi de suite."
                                        data-english="If you lose again, double the trade size again (e.g., to $40), and so on.">
                                        If you lose again, double the trade size again (e.g., to $40), and so on.
                                    </li>
                                    <li data-arabe="عندما تفوز في النهاية، سيغطي ربحك جميع الخسائر السابقة بالإضافة إلى تحقيق الربح المستهدف."
                                        data-france="Lorsque vous gagnez enfin, votre profit couvrira toutes les pertes précédentes, plus votre profit cible."
                                        data-english="When you eventually win, your profit will recover all previous losses, plus your target profit.">
                                        When you eventually win, your profit will recover all previous losses, plus your target profit.
                                    </li>
                                </ul>
                                
                                <p data-arabe="يساعدك هذا الحاسبة في تحديد حجم الصفقة المطلوب بعد الخسائر المتتالية ويظهر لك مقدار ما تحتاجه للتداول لتحقيق ربحك المستهدف."
                                data-france="Ce calculateur vous aide à déterminer la taille du trade nécessaire après des pertes consécutives et vous montre combien vous devez trader pour atteindre votre profit cible."
                                data-english="This calculator helps you determine the trade size needed after consecutive losses and shows how much you need to trade to hit your target profit.">
                                    This calculator helps you determine the trade size needed after consecutive losses and shows how much you need to trade to hit your target profit.
                                </p>
                            </div>

                        </form>
                    </div>
                </div> 
            </div>
        </main>
        <main id="CommunityMain" style="display: none;">
            <h1 data-arabe="مجتمع ForexPeak" 
                data-france="Communauté ForexPeak" 
                data-english="Community ForexPeak">
                Community ForexPeak
            </h1>
            <br><br>

            <div class="tab-container">            
                <!-- Party Controls (General and Create Party Buttons) -->

                <div class="controlsbtn">
                    <!-- Container for Dynamically Created Parties -->
                    <div id="partyList"></div>

                    <!-- Create Party Button -->
                    <div class="party-div" id="createPartyButton">
                        <h3 data-arabe="+" data-france="+" data-english="+">+</h3>
                    </div>

                    <form id="JoinParty" class="party-form">
                        <input type="number" 
                            name="partyNumber" 
                            placeholder="Enter last 4 digits of the code" 
                            data-arabe="أدخل آخر 4 أرقام من الرمز" 
                            data-france="Entrez les 4 derniers chiffres du code" 
                            data-english="Enter last 4 digits of the code"
                            required min="1000" max="9999">
                        <button type="submit" class="send-btn" 
                                data-arabe="انضم إلى المجموعة" 
                                data-france="Rejoindre le groupe" 
                                data-english="Join Party">
                            Join Party
                        </button>
                    </form>
                </div>
                        
                <!-- Create Party Form (Hidden by Default) -->
                <div id="createPartyFormContainer" style="display: none;">
                    <form action="../System/create_parties.php" method="POST" style="margin-bottom: 1rem;" class="party-form">
                        <input type="text" name="partyName" 
                            placeholder="Party Name" 
                            data-arabe="اسم المجموعة" 
                            data-france="Nom du groupe" 
                            data-english="Party Name" required>
                        <input type="hidden" name="partyCode" id="partyCode">
                        <button type="submit" class="send-btn" onclick="generatePartyCode()" 
                                data-arabe="إنشاء مجموعة" 
                                data-france="Créer un groupe" 
                                data-english="Create Party">
                            Create Party
                        </button>
                    </form>
                </div>

                <div class="chat-header">
                    <h1 data-arabe="دردشة المجتمع" 
                        data-france="Chat de la communauté" 
                        data-english="Community Chat">
                        Community Chat
                    </h1>
                </div>
                
                <div class="chat-box" id="chatBox">
                    <?php if (empty($community_data)): ?>
                        <!-- Display this message if there are no messages -->
                        <div class="no-messages">
                            <span data-arabe="لا توجد رسائل للعرض. ابدأ محادثة!" 
                                data-france="Aucun message à afficher. Commencez une conversation !" 
                                data-english="No messages to display. Start a conversation!">
                                No messages to display. Start a conversation!
                            </span>
                        </div>
                    <?php else: ?>
                        <?php 
                        $previousDateSignal = null; // To track if we've already displayed a date signal for a given day

                        foreach ($community_data as $community): 
                            $dateSignal = getDateSignal($community['timestamp']);
                        ?>
                            <!-- Display date signal only if it's different from the previous one -->
                            <?php if ($dateSignal !== $previousDateSignal): ?>
                                <div class="date-signal">
                                    <span><?= htmlspecialchars($dateSignal) ?></span>
                                </div>
                                <?php $previousDateSignal = $dateSignal; ?>
                            <?php endif; ?>

                            <div class="message <?= ($community['sender_id'] == $_SESSION['user_id']) ? 'sent' : 'received' ?>" 
                                style="background-color: <?= ($community['sender_id'] == $_SESSION['user_id']) ? 'var(--color-dark)' : 'var(--color-white)'; ?>; color: <?= ($community['sender_id'] == $_SESSION['user_id']) ? 'var(--color-white)' : 'var(--color-dark)'; ?>; width: 20%;"
                                data-message-id="<?= htmlspecialchars($community['id']) ?>" id="sender_<?= htmlspecialchars($community['id']) ?>">
                                
                                <!-- Display sender's full name and message -->
                                <strong><?= htmlspecialchars($community['fullname']) ?></strong><br>
                                <span class="message-text"><?= makeLinksClickable($community['community_message']) ?></span><br>
                                
                                <!-- Display uploaded image -->
                                <?php if (!empty($community['image_path'])): ?>
                                    <img src="<?= htmlspecialchars($community['image_path']) ?>" alt="Uploaded Image" class="chat-image" onclick="showFullScreenImage(this)">
                                <?php endif; ?>
                                
                                <!-- Display replay section if this is a reply -->
                                <?php if (!empty($community['reply_to_message_id'])): ?>
                                    <div class="replay" id="replay_<?= htmlspecialchars($community['reply_to_message_id']) ?>">
                                        <input type="hidden" class="senderId" value="<?= htmlspecialchars($community['reply_to_message_id']) ?>">
                                        <span class="name-replay"><?= htmlspecialchars($community['name_sender']) ?></span>
                                        <span class="message-replay"><?= htmlspecialchars($community['original_message']) ?></span>
                                    </div>
                                <?php endif; ?>
                                <br>
                                <button class="replay-btn" id="replay" 
                                        data-arabe="رد" 
                                        data-france="Répondre" 
                                        data-english="Reply">
                                    reply
                                </button>
                                <div style="display: flex; align-items: center; justify-content: space-between; flex-direction: row-reverse;">
                                    <span style="font-size: 12px; color: gray;"><?= htmlspecialchars(date('Y-m-d H:i:s', strtotime($community['timestamp']))) ?></span><br>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Full-Screen Image Overlay -->
                <div id="imageOverlay" class="image-overlay" onclick="closeFullScreenImage()">
                    <img id="fullScreenImage" class="full-screen-image" src="" alt="Full-Screen View">
                </div>

                <div class="replay-div" id="replaydiv">
                    <span class="name-replay"></span>
                    <span class="message-replay"></span>
                    <button id="cancelReplyBtn" class="cancel-reply-btn" 
                            data-arabe="إلغاء" 
                            data-france="Annuler" 
                            data-english="Cancel">
                        X
                    </button>
                </div>

                <div class="community-input-container">
                    <button id="scrollToBottomBtn" class="scroll-btn" onclick="scrollToBottom()" 
                            data-arabe="⬇️" 
                            data-france="⬇️" 
                            data-english="⬇️">
                        ⬇️
                    </button>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="text" class="community-input" name="community" id="communityInput" 
                            placeholder="Type a community message..." 
                            data-arabe="اكتب رسالة للمجتمع..." 
                            data-france="Tapez un message communautaire..." 
                            data-english="Type a community message...">
                        <input type="hidden" name="reply_to_message_id" id="replyToMessageId">
                        <input type="hidden" name="original_message" id="originalMessage">
                        <input type="hidden" name="receiver_id" value="2">
                        <input type="hidden" name="currentTableName" id="currentTableName">
                        <input id="photo-input" type="file" name="community_image" accept="image/*" style="display: none;">
                        <button class="send-btn" type="submit" 
                                data-arabe="إرسال" 
                                data-france="Envoyer" 
                                data-english="Send">
                            Send
                        </button>
                        <label class="send-btn" for="photo-input" id="photo-label" 
                            data-arabe="📷" 
                            data-france="📷" 
                            data-english="📷">
                            📷
                        </label>
                    </form>
                </div>
            </div>
        </main>
        <main id="TicketsMain" style="display: none;">
            <h1>News</h1>
            <br>
            <div class="form-control2">
            <!-- TradingView Widget BEGIN -->
            <div style="display: none;" class="tradingview-widget-container" id="newsdark">
                <div class="tradingview-widget-container__widget"></div>
                <script type="text/javascript"
                    src="https://s3.tradingview.com/external-embedding/embed-widget-events.js" data-theme="dark" async>
                    {
                        "width": "100%",
                        "height": "100%",
                        "colorTheme": "dark",
                        "isTransparent": false,
                        "locale": "en",
                        "importanceFilter": "-1,0,1",
                        "countryFilter": "ar,au,br,ca,cn,fr,de,in,id,it,jp,kr,mx,ru,sa,za,tr,gb,us,eu"
                    }
                </script>
            </div>
            <div class="tradingview-widget-container" id="newslight">
                <div class="tradingview-widget-container__widget"></div>
                <script type="text/javascript"
                    src="https://s3.tradingview.com/external-embedding/embed-widget-events.js" data-theme="dark" async>
                    {
                        "width": "100%",
                        "height": "100%",
                        "colorTheme": "light",
                        "isTransparent": false,
                        "locale": "en",
                        "importanceFilter": "-1,0,1",
                        "countryFilter": "ar,au,br,ca,cn,fr,de,in,id,it,jp,kr,mx,ru,sa,za,tr,gb,us,eu"
                    }
                </script>
            </div>
            </div>
            <!-- TradingView Widget END -->
        </main>
        <main id="InventoryMain" style="display: none;">
            <h1>TradingView</h1>
                <br>
                <div style="display: none;" class="form-control2" id="blacktradingview">
                        <!-- TradingView Widget BEGIN -->
                        <div class="tradingview-widget-container" style="height:100%;width:100%">
                            <div class="tradingview-widget-container__widget" style="height:calc(100% - 32px);width:100%"></div>
                            <div class="tradingview-widget-copyright"><a href="https://www.tradingview.com/" rel="noopener nofollow"
                                    target="_blank"><span class="blue-text"></span></a></div>
                            <script type="text/javascript"
                                src="https://s3.tradingview.com/external-embedding/embed-widget-advanced-chart.js" data-theme="dark"
                                async>
                                {
                                    "autosize": true,
                                    "symbol": "OANDA:XAUUSD",
                                    "timezone": "Etc/UTC",
                                    "theme": "dark",
                                    "style": "1",
                                    "locale": "en",
                                    "withdateranges": true,
                                    "range": "YTD",
                                    "hide_side_toolbar": false,
                                    "allow_symbol_change": true,
                                    "calendar": false,
                                    "show_popup_button": true,
                                    "popup_width": "1000",
                                    "popup_height": "650",
                                    "support_host": "https://www.tradingview.com"
                                }
                            </script>
                        </div>
                        <!-- TradingView Widget END -->
                </div>
                <div class="form-control2" id="lighttradingview">
                        <div class="tradingview-widget-container" style="height:100%;width:100%">
                            <div class="tradingview-widget-container__widget" style="height:calc(100% - 32px);width:100%"></div>
                            <div class="tradingview-widget-copyright"><a href="https://www.tradingview.com/" rel="noopener nofollow"
                                    target="_blank"><span class="blue-text"></span></a></div>
                            <script type="text/javascript"
                                src="https://s3.tradingview.com/external-embedding/embed-widget-advanced-chart.js" data-theme="dark"
                                async>
                                {
                                    "autosize": true,
                                    "symbol": "OANDA:XAUUSD",
                                    "timezone": "Etc/UTC",
                                    "theme": "light",
                                    "style": "1",
                                    "locale": "en",
                                    "withdateranges": true,
                                    "range": "YTD",
                                    "hide_side_toolbar": false,
                                    "allow_symbol_change": true,
                                    "calendar": false,
                                    "show_popup_button": true,
                                    "popup_width": "1000",
                                    "popup_height": "650",
                                    "support_host": "https://www.tradingview.com"
                                }
                            </script>
                        </div>
                </div>
        </main>
        <main id="BacktestMain" style="display: none;">
            <h1 data-arabe="اختبر معاملاتك" data-france="back test" data-english="back test">back test</h1>
            <br><br>
            <div class="container-Backtes">
                <div class="controls">
                    <input type="number" id="initial" placeholder="Initial Amount" data-arabe="المبلغ الأولي" data-france="Montant initial" data-english="Initial Amount">
                    <button id="setInitial" data-arabe="تحديد المبلغ الأولي" data-france="Définir initial" data-english="Set Initial">Set Initial</button>
                    <input type="number" id="profit" placeholder="Profit Amount" data-arabe="مبلغ الربح" data-france="Montant du profit" data-english="Profit Amount">
                    <button id="addProfit" data-arabe="+ إضافة الربح" data-france="+ Ajouter profit" data-english="+ Add Profit">+ Add Profit</button>
                    <input type="number" id="loss" placeholder="Loss Amount" data-arabe="مبلغ الخسارة" data-france="Montant de la perte" data-english="Loss Amount">
                    <button id="addLoss" data-arabe="- إضافة الخسارة" data-france="- Ajouter perte" data-english="- Add Loss">- Add Loss</button>
                </div>

                <div class="stats">
                    <div class="text-backtest">
                        <h2 data-arabe="نسبة الفوز: " data-france="Taux de réussite: " data-english="Winrate: ">Winrate: </h2>
                        <span id="winrate">0%</span>
                    </div>
                    <div class="text-backtest">
                        <h2 data-arabe="صافي الأرباح والخسائر: " data-france="Pertes et profits nets: " data-english="PnL: ">PnL: </h2>
                        <span id="pnl">0%</span>
                    </div>
                    <div class="text-backtest">
                        <h2 data-arabe="إجمالي المعاملات: " data-france="Total des transactions: " data-english="Total Trades: ">Total Trades: </h2>
                        <span id="totalTrades">0</span>
                    </div>
                    <div class="text-backtest">
                        <h2 data-arabe="الفوز / الخسائر: " data-france="Gains / Pertes: " data-english="Wins / Losses: ">Wins / Losses: </h2>
                        <span id="winLoss">0W / 0L</span>
                    </div>
                </div>

                <canvas id="chart-backtest" width="800" height="400"></canvas>
            </div>
            <br><br>
            <h1 data-arabe="المخاطرة | المكافأة" data-france="Risque | Récompense" data-english="Risk | Reward">Risk | Reward</h1>
            <br><br>
            <div style="padding-bottom: 3rem;">
                <table>
                    <thead>
                        <tr>
                            <th data-arabe="المخاطرة | المكافأة" data-france="Risque | Récompense" data-english="Risk | Reward">Risk | Reward</th>
                            <th data-arabe="20%" data-france="20%" data-english="20%">20%</th>
                            <th data-arabe="30%" data-france="30%" data-english="30%">30%</th>
                            <th data-arabe="40%" data-france="40%" data-english="40%">40%</th>
                            <th data-arabe="50%" data-france="50%" data-english="50%">50%</th>
                            <th data-arabe="60%" data-france="60%" data-english="60%">60%</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td data-arabe="1:1" data-france="1:1" data-english="1:1">1:1</td>
                            <td data-arabe="غير مربح" data-france="Pas rentable" data-english="Not Profitable" class="not-profitable">Not Profitable</td>
                            <td data-arabe="غير مربح" data-france="Pas rentable" data-english="Not Profitable" class="not-profitable">Not Profitable</td>
                            <td data-arabe="غير مربح" data-france="Pas rentable" data-english="Not Profitable" class="not-profitable">Not Profitable</td>
                            <td data-arabe="نقطة التعادل" data-france="Seuil de rentabilité" data-english="Break Even" class="break-even">Break Even</td>
                            <td data-arabe="مربح" data-france="Rentable" data-english="Profitable" class="profitable">Profitable</td>
                        </tr>
                        <tr>
                            <td data-arabe="2:1" data-france="2:1" data-english="2:1">2:1</td>
                            <td data-arabe="غير مربح" data-france="Pas rentable" data-english="Not Profitable" class="not-profitable">Not Profitable</td>
                            <td data-arabe="غير مربح" data-france="Pas rentable" data-english="Not Profitable" class="not-profitable">Not Profitable</td>
                            <td data-arabe="مربح" data-france="Rentable" data-english="Profitable" class="profitable">Profitable</td>
                            <td data-arabe="مربح" data-france="Rentable" data-english="Profitable" class="profitable">Profitable</td>
                            <td data-arabe="مربح" data-france="Rentable" data-english="Profitable" class="profitable">Profitable</td>
                        </tr>
                        <tr>
                            <td data-arabe="3:1" data-france="3:1" data-english="3:1">3:1</td>
                            <td data-arabe="غير مربح" data-france="Pas rentable" data-english="Not Profitable" class="not-profitable">Not Profitable</td>
                            <td data-arabe="مربح" data-france="Rentable" data-english="Profitable" class="profitable">Profitable</td>
                            <td data-arabe="مربح" data-france="Rentable" data-english="Profitable" class="profitable">Profitable</td>
                            <td data-arabe="مربح" data-france="Rentable" data-english="Profitable" class="profitable">Profitable</td>
                            <td data-arabe="مربح" data-france="Rentable" data-english="Profitable" class="profitable">Profitable</td>
                        </tr>
                        <tr>
                            <td data-arabe="4:1" data-france="4:1" data-english="4:1">4:1</td>
                            <td data-arabe="نقطة التعادل" data-france="Seuil de rentabilité" data-english="Break Even" class="break-even">Break Even</td>
                            <td data-arabe="مربح" data-france="Rentable" data-english="Profitable" class="profitable">Profitable</td>
                            <td data-arabe="مربح" data-france="Rentable" data-english="Profitable" class="profitable">Profitable</td>
                            <td data-arabe="مربح" data-france="Rentable" data-english="Profitable" class="profitable">Profitable</td>
                            <td data-arabe="مربح" data-france="Rentable" data-english="Profitable" class="profitable">Profitable</td>
                        </tr>
                        <tr>
                            <td data-arabe="5:1" data-france="5:1" data-english="5:1">5:1</td>
                            <td data-arabe="مربح" data-france="Rentable" data-english="Profitable" class="profitable">Profitable</td>
                            <td data-arabe="مربح" data-france="Rentable" data-english="Profitable" class="profitable">Profitable</td>
                            <td data-arabe="مربح" data-france="Rentable" data-english="Profitable" class="profitable">Profitable</td>
                            <td data-arabe="مربح" data-france="Rentable" data-english="Profitable" class="profitable">Profitable</td>
                            <td data-arabe="مربح" data-france="Rentable" data-english="Profitable" class="profitable">Profitable</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div style="padding-bottom: 3rem;">                    
                <h1 class="com_title" data-arabe="حاسبة النمو المركب" data-france="Calculateur de croissance composée" data-english="Compounding Growth Calculator">Compounding Growth Calculator</h1>
                <div class="com_containe">
                    <div class="com_input-group">
                        <label data-arabe="رأس المال الأولي:" data-france="Capital initial :" data-english="Initial Capital:">Initial Capital: </label><input type="number" id="capital" value="1000">
                    </div>
                    <div class="com_input-group">
                        <label data-arabe="النمو اليومي (%):" data-france="Croissance quotidienne (%):" data-english="Daily Growth (%):">Daily Growth (%): </label><input type="number" id="percentage" value="2">
                    </div>
                    <div class="com_input-group">
                        <label data-arabe="الأيام:" data-france="Jours :" data-english="Days:">Days: </label><input type="number" id="days" value="30">
                    </div>
                    <button data-arabe="احسب" data-france="Calculer" data-english="Calculate" onclick="generateChart()">Calculate</button>
                </div>
                
                <div class="com_chart-container">
                    <canvas id="compoundingChart"></canvas>
                </div>
            </div>
            <script>
                function generateChart() {
                    let capital = parseFloat(document.getElementById('capital').value);
                    let percentage = parseFloat(document.getElementById('percentage').value) / 100;
                    let days = parseInt(document.getElementById('days').value);
                    
                    let labels = [];
                    let data = [];
                    let currentCapital = capital;
                    
                    for (let i = 1; i <= days; i++) {
                        currentCapital *= (1 + percentage);
                        labels.push("Day " + i);
                        data.push(currentCapital.toFixed(2));
                    }
                    
                    let ctx = document.getElementById('compoundingChart').getContext('2d');
                    if (window.myChart) window.myChart.destroy();
                    
                    window.myChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: 'Capital Growth',
                                data: data,
                                borderColor: '#bfff47',
                                backgroundColor: '#bfff47',
                                fill: false
                            }]
                        },
                        options: {
                            responsive: true,
                            scales: {
                                y: { beginAtZero: false }
                            }
                        }
                    });
                }

                // Wait until DOM is fully loaded before generating the chart
                document.addEventListener("DOMContentLoaded", function () {
                    generateChart();
                });
            </script>
        </main>
        <main id="OthersMain" style="display: none;">
            <h1 data-arabe="التجار الصالحين" data-france="Traders validés" data-english="Valide Traders">
                Valide Traders
            </h1>

            <!-- Best 3 Traders -->
            <div class="usersCards">
                <h3>Best 3 Traders</h3>
                <input type="text" id="best-trader-search" class="search-bar" placeholder="Search traders...">
                <div id="best-trader-list"></div>
            </div>

            <!-- Recommended Traders -->
            <div class="usersCards">
                <h3>Recommended Traders</h3>
                <input type="text" id="trader-search" class="search-bar" placeholder="Search traders...">
                <div id="trader-list"></div>
            </div>
        </main>
        <main class="PortfolioTraders" id="PortfolioTraders" style="display: none;">
            <button id="backToOthers"><span class="material-icons-sharp">arrow_back</span> Back</button>
            <div id="portfolio-content"></div>
        </main>
        <script>
            const traders = <?php echo json_encode($js_traders); ?>;
            const traderList = document.getElementById("trader-list");
            const bestTraderList = document.getElementById("best-trader-list");
            const portfolioMain = document.getElementById("PortfolioTraders");
            const othersMain = document.getElementById("OthersMain");
            const portfolioContent = document.getElementById("portfolio-content");

            // Function to render trader card
            function renderTrader(trader, container) {
                const wrapper = document.createElement("div");

                const userDiv = document.createElement("div");
                userDiv.classList.add("user");
                userDiv.innerHTML = `
                    <div class="icon" id="Portfolio-Traders-${trader.id}">
                        <span class="material-icons-sharp">person</span>
                    </div>
                    <div class="details">
                        <div class="name">${trader.name}</div>
                        <div class="stat-line">Win Rate: <span>${trader.winRate}</span></div>
                        <div class="stat-line">Equity: <span>${trader.equity}</span></div>
                        <div class="stat-line"> Profit: <span class="${trader.profitColor}">${trader.profit}</span></div>
                    </div>
                    <div class="chart"><canvas id="chart-user-${trader.id}"></canvas></div>
                    <div class="track-button">
                        <button id="toggle-record-${trader.id}">Show Track Record</button>
                    </div>
                `;
                wrapper.appendChild(userDiv);

                const recordDiv = document.createElement("div");
                recordDiv.classList.add("track-record");
                recordDiv.id = `track-record-${trader.id}`;
                recordDiv.style.display = "none";
                recordDiv.innerHTML = `
                    <table>
                        <thead>
                        <tr><th>Time</th><th>Pair</th><th>Entry</th><th>SL</th><th>TP</th><th>Close</th></tr>
                        </thead>
                        <tbody>
                        ${trader.track.map(t => `
                            <tr>
                                <td>${t.time}</td><td>${t.pair}</td><td>${t.entry}</td>
                                <td>${t.sl}</td><td>${t.tp}</td><td>${t.close}</td>
                            </tr>`).join("")}
                        </tbody>
                    </table>
                `;
                wrapper.appendChild(recordDiv);

                // toggle button
                userDiv.querySelector(`#toggle-record-${trader.id}`).addEventListener("click", function() {
                    recordDiv.style.display = recordDiv.style.display === "block" ? "none" : "block";
                    this.textContent = recordDiv.style.display === "block" ? "Hide Track Record" : "Show Track Record";
                });

                // ---- Portfolio click ----
                userDiv.querySelector(`#Portfolio-Traders-${trader.id}`).addEventListener("click", function() {
                    othersMain.style.display = "none";
                    portfolioMain.style.display = "block";
                    portfolioContent.innerHTML = `
                        <h2>${trader.name} Portfolio</h2>
                        <div class="portfolio-trader-header">
                            <div>
                                <span class="material-icons-sharp">person</span>
                            </div>
                            <div>
                                <p><strong>Win Rate:</strong> ${trader.winRate}</p>
                                <p><strong>Equity:</strong> ${trader.equity}</p>
                                <p><strong>Profit:</strong> <span class="${trader.profitColor}">${trader.profit}</span></p>
                            </div>
                        </div>
                        <div class="analyse2">
                            <h3>Performance Charts</h3>
                            <div id="chart-container">
                                <canvas class="chartt" id="rrChart-${trader.id}"></canvas>
                            </div>
                            <div id="chart-container">
                                <canvas class="chartt" id="equityChart-${trader.id}"></canvas>
                            </div>
                            <div id="chart-container">
                                <canvas class="chartt" id="mixedChart-${trader.id}"></canvas>
                            </div>
                            <div id="chart-container">
                                <canvas class="chartt" id="winrateChart-${trader.id}"></canvas>
                            </div>
                        </div>

                        <h3>Track Record</h3>
                        <table>
                            <thead>
                                <tr><th>Time</th><th>Pair</th><th>Entry</th><th>SL</th><th>TP</th><th>Close</th></tr>
                            </thead>
                            <tbody>
                                ${trader.track.map(t => `
                                    <tr>
                                        <td>${t.time}</td><td>${t.pair}</td><td>${t.entry}</td>
                                        <td>${t.sl}</td><td>${t.tp}</td><td>${t.close}</td>
                                    </tr>`).join("")}
                            </tbody>
                        </table>
                    `;

                    // ---- Charts ----
                    setTimeout(() => {
                        // 1. Win Rate Gauge
                        const winRateValue = parseFloat(trader.winRate.replace('%',''));
                        new Chart(document.getElementById(`winrateChart-${trader.id}`), {
                            type: 'doughnut',
                            data: {
                                labels: ['Win', 'Loss'],
                                datasets: [{
                                    data: [winRateValue, 100 - winRateValue],
                                    backgroundColor: ['#4caf50', '#f44336']
                                }]
                            },
                            options: { plugins: { legend: { position: 'bottom' } } }
                        });

                        // 2. RR لكل صفقة (bar chart)
                        const rrValues = trader.track.map(t => {
                            const risk = Math.abs(t.entry - t.sl);
                            const reward = Math.abs(t.tp - t.entry);
                            return reward && risk ? (reward / risk).toFixed(2) : null;
                        }).filter(v => v !== null);

                        new Chart(document.getElementById(`rrChart-${trader.id}`), {
                            type: 'bar',
                            data: {
                                labels: rrValues.map((_, i) => `${i+1}`),
                                datasets: [{
                                    label: 'R:R per Trade',
                                    data: rrValues,
                                    backgroundColor: '#2196f3'
                                }]
                            },
                            options: {
                                responsive: true,
                                plugins: { legend: { display: false } },
                                scales: { y: { beginAtZero: true } }
                            }
                        });

                        // 3. Equity Curve
                        new Chart(document.getElementById(`equityChart-${trader.id}`), {
                            type: 'line',
                            data: {
                                labels: trader.chartData.map((_, i) => i+1),
                                datasets: [{
                                    label: 'Equity',
                                    data: trader.chartData,
                                    borderColor: '#8bcf6c',
                                    fill: false,
                                    tension: 0.3
                                }]
                            },
                            options: { plugins: { legend: { display: false } } }
                        });

                        // 4. Mixed Chart (Equity + RR)
                        new Chart(document.getElementById(`mixedChart-${trader.id}`), {
                            data: {
                                labels: trader.track.map((_, i) => `${i+1}`),
                                datasets: [
                                    {
                                        type: 'line',
                                        label: 'Equity',
                                        data: trader.chartData,
                                        borderColor: '#4caf50',
                                        yAxisID: 'y1',
                                        fill: false,
                                        tension: 0.3
                                    },
                                    {
                                        type: 'bar',
                                        label: 'R:R',
                                        data: rrValues,
                                        backgroundColor: '#2196f3',
                                        yAxisID: 'y2'
                                    }
                                ]
                            },
                            options: {
                                responsive: true,
                                interaction: { mode: 'index', intersect: false },
                                stacked: false,
                                scales: {
                                    y1: {
                                        type: 'linear',
                                        position: 'left',
                                        title: { display: true, text: 'Equity' }
                                    },
                                    y2: {
                                        type: 'linear',
                                        position: 'right',
                                        title: { display: true, text: 'R:R' },
                                        grid: { drawOnChartArea: false }
                                    }
                                }
                            }
                        });

                    }, 100);
                });

                container.appendChild(wrapper);

                // small inline chart on card
                function splitIntoFourParts(data) {
                    const len = data.length;
                    const chunkSize = Math.ceil(len / 4);
                    const result = [];
                    for (let i = 0; i < 4; i++) {
                        const chunk = data.slice(i * chunkSize, (i + 1) * chunkSize);
                        if (chunk.length > 0) {
                            const avg = chunk.reduce((sum, val) => sum + val, 0) / chunk.length;
                            result.push(avg.toFixed(2));
                        }
                    }
                    return result;
                }

                const ctx = document.getElementById(`chart-user-${trader.id}`).getContext("2d");
                const chartDataSplit = splitIntoFourParts(trader.chartData);

                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: ['','','',''],
                        datasets: [{
                            label: 'Profit',
                            data: chartDataSplit,
                            borderColor: '#8bcf6c',
                            borderWidth: 2,
                            fill: false,
                            tension: 0.3
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: { legend: { display: false } },
                        scales: { x: { display: false }, y: { display: false } }
                    }
                });
            }

            // Sort traders by Win Rate
            function sortByWinRate(data) {
                return [...data].sort((a, b) =>
                    parseFloat(b.winRate.toString().replace('%','')) -
                    parseFloat(a.winRate.toString().replace('%',''))
                );
            }

            // Best 3 Traders
            const topTraders = sortByWinRate(traders);
            topTraders.forEach(t => renderTrader(t, traderList));

            // Recommended Traders (all sorted)
            const allTraders = sortByWinRate(traders).slice(0, 3);
            allTraders.forEach(t => renderTrader(t, bestTraderList));

            // Back button
            document.getElementById("backToOthers").addEventListener("click", function() {
                portfolioMain.style.display = "none";
                othersMain.style.display = "block";
            });
        </script>
        <main id="SettingsMain" style="display: none;">
            <h1 data-arabe="الدعم" data-france="Support" data-english="Support">Support</h1>
            <div class="center-wrapper">
                <div class="contact">
                    <h1 data-arabe="اتصل بنا" data-france="Contactez-nous" data-english="Contact Us">Contact Us</h1>
                    <form method="POST" action="../System/send_message.php">
                        <input type="hidden" name="user_id" value="<?php echo $_SESSION['user_id']; ?>">

                        <div class="form-group">
                            <input type="text" class="input-field" name="name" placeholder="Your Name" required>
                        </div>
                        <div class="form-group">
                            <input type="email" class="input-field" name="email" placeholder="Your Email" required>
                        </div>
                        <div class="form-group">
                            <input type="text" class="input-field" name="mobile" placeholder="Your Mobile" required>
                        </div>
                        <div class="form-group">
                            <input type="text" class="input-field" name="subject" placeholder="Subject" required>
                        </div>
                        <div class="form-group">
                            <textarea class="input-field" name="message" placeholder="Message" required></textarea>
                        </div>
                        <button type="submit" name="send_message" class="submit-btn">Submit Now</button>
                    </form>
                </div>
                <script>
                                function showUserInfo(fullname, id) {
                                    document.getElementById("accountId").value = id;
                                    document.getElementById("accountName").value = fullname;

                                    console.log("Account ID:", id);
                                    console.log("Account Name:", fullname);
                                }
                                // ملي يلود الصفحة يبان أول واحد checked
                                window.addEventListener("DOMContentLoaded", function() {
                                    const checkedInput = document.querySelector('input[name="radio"]:checked');
                                    if (checkedInput) {
                                        checkedInput.click(); // باش ينفذ showUserInfo مباشرة
                                    }
                                });
                </script>          
                <div class="contact" id="Provecontact">
                    <h1 data-arabe="اثبت حسابك الحقيقي" data-france="Prouvez votre vrai compte-nous" data-english="Prove your real account ">Prove your real account </h1>
                    <form method="POST" action="../System/send_request.php" enctype="multipart/form-data">
                        <input type="hidden" name="user_id" value="<?php echo $_SESSION['user_id']; ?>">
                        <input type="hidden" id="accountId" name="account_id" value="">
                        <input type="hidden" id="accountName" name="account_name" value="">


                        <div class="form-group">
                            <input type="text" class="input-field" name="name" placeholder="Your Name" required>
                        </div>
                        <div class="form-group">
                            <input type="email" class="input-field" name="email" placeholder="Your Email" required>
                        </div>
                        <div class="form-group">
                            <input type="text" class="input-field" name="subject" placeholder="Your Broker Or funded name" required>
                        </div>
                        <div class="form-group">
                            <input type="file" class="input-field" name="prove" accept="image/*,.pdf" placeholder="Your Prove account" required>
                        </div>
                        <div class="form-group">
                            <textarea class="input-field" name="message" placeholder="Message" required></textarea>
                        </div>
                        <button type="submit" name="send_message" class="submit-btn">request public account</button>
                    </form>
                </div>
            </div>
        </main>
        <main id="ReportsMain" style="display: none;">
            <h1 data-arabe="الإعدادات" data-france="Paramètres" data-english="Settings">Settings</h1>
            <br>
            <div class="form-control2"style="height:auto;">
                <div class="tabs">
                    <button class="tab-button-user active" onclick="switchTabuser(0)">
                        <i class="ri-user-line"></i> 
                        <span data-arabe="معلومات الحساب" data-france="Informations du compte" data-english="Account Info">Account Info</span>
                    </button>
                    <button class="tab-button-user" onclick="switchTabuser(1)">
                        <i class="ri-chat-2-line"></i> 
                        <span data-arabe="رسائل الدعم" data-france="Messages de support" data-english="Support Messages">Support Messages</span>
                    </button>
                    <button class="tab-button-user" onclick="switchTabuser(2)">
                        <i class="fa-solid fa-arrow-up-wide-short"></i> 
                        <span data-arabe="ترقية بريميوم" data-france="Passer Premium" data-english="Pass Premium">Pass Premium</span>
                    </button>
                    <button class="tab-button-user" onclick="switchTabuser(3)">
                        <i class="ri-settings-2-line"></i> 
                        <span data-arabe="إعدادات النظام" data-france="Paramètres du système" data-english="System Settings">System Settings</span>
                    </button>
                    <button class="tab-button-user" onclick="switchTabuser(4)">
                        <i class="ri-information-line"></i> 
                        <span data-arabe="معلومات عنا" data-france="À propos de nous" data-english="About Us">About Us</span>
                    </button>
                </div> 
                <div class="flex-container">
                    <div class="welcome-container" >
                        <div>
                            <h1 class="welcome-title" data-arabe="أهلا بك، <?php echo htmlspecialchars($_SESSION['fullname']); ?>" data-france="Bienvenue, <?php echo htmlspecialchars($_SESSION['fullname']); ?>" data-english="Welcome, <?php echo htmlspecialchars($_SESSION['fullname']); ?>">Welcome, <?php echo htmlspecialchars($_SESSION['fullname']); ?></h1>
                            <p class="welcome-message" data-arabe="مرحبا بك في منصة تداول العملات المشفرة الرائدة..." data-france="Bienvenue sur ForexPeak, une plateforme de ForexPeak leader..." data-english="Welcome to ForexPeak, a leading Forex trading platform...">Welcome to ForexPeak, a leading Forex trading platform...</p>
                            
                            <h2 class="account-info-title" data-arabe="معلومات الحساب" data-france="Informations du compte" data-english="Account Information">Account Information</h2>
                            
                            <form class="account-info-form" action="../System/update.php" method="post" onsubmit="event.preventDefault(); openUpdateModal();">

                                <label class="input-label" for="updatename" data-arabe="الاسم:" data-france="Nom:" data-english="Name:">Name:</label>
                                <input class="input-field" type="text" id="updatename" value="<?php echo htmlspecialchars($_SESSION['fullname'] ?? ''); ?>" name="updatename" required>

                                <label class="input-label" for="updateemail" data-arabe="البريد الإلكتروني:" data-france="Email:" data-english="Email:">Email:</label>
                                <input class="input-field" type="email" id="updateemail" value="<?php echo htmlspecialchars($user_email ?? ''); ?>" name="updateemail" required>

                                <label class="input-label" for="updatephone" data-arabe="الهاتف:" data-france="Téléphone:" data-english="Phone:">Phone:</label>
                                <input class="input-field" type="text" id="updatephone" value="<?php echo htmlspecialchars($user_phone ?? ''); ?>" name="updatephone" required>

                                <label class="input-label" for="updateaddress" data-arabe="العنوان:" data-france="Adresse:" data-english="Address:">Address:</label>
                                <input class="input-field" type="text" id="updateaddress" value="<?php echo htmlspecialchars($user_address ?? ''); ?>" name="updateaddress" required>

                                <label class="input-label" for="updatepassword" data-arabe="كلمة المرور:" data-france="Mot de passe:" data-english="Password:">Password:</label>
                                <input class="input-field" type="password" id="updatepassword" placeholder="********" name="updatepassword" required>

                                <input type="submit" class="submit-btn" value="Update Information" data-arabe="تحديث المعلومات" data-france="Mettre à jour les informations" data-english="Update Information">
                            </form>

                        </div>
                        <div style="background-color: #fff;width: 1px;"></div>
                        <div>
                            <h1 class="welcome-title" data-arabe="اشتراك الحساب" data-france="Votre abonnement à votre compte" data-english="Your Account Subscription">Your Account Subscription</h1>
                            <h2 class="account-info-title" data-arabe="نوع الحساب" data-france="type de compte" data-english="Account Type">Account Type</h2>
                            <p><?php echo htmlspecialchars($user_login_typ); ?></p>
                            <div class="content">
                                <?php displayPlan($user_login_typ); ?>
                            </div>
                        </div>
                    </div>
                    <div class="support-container" style="display: none;flex-direction: column;">
                        <h2 class="support-messages-title" data-arabe="ليس لديك رسالة!" data-france="Vous n'avez pas de message !" data-english="You have no message!">You have no message!</h2>
                        <br><br>
                        <div class="message_suppor">
                            <div class="message_suppor__icon">
                                <span class="material-symbols-outlined">
                                    support_agent
                                </span>
                            </div>
                            <div class="message_suppor__title">support message ! support@gmail.com </div>
                            <div class="message_suppor__close">
                                <span class="material-symbols-outlined">
                                    contact_support
                                </span>
                            </div>
                        </div>
                        <h1>Conversation</h1>
                        <div class="Conversation-div" id="conversationBox">
                            <?php if (!empty($notifications) || !empty($messages)): ?>
                                <?php 
                                $conversation = [];

                                foreach ($notifications as $index => $notification) {
                                    $conversation[] = [
                                        'type' => 'notification',
                                        'content' => $notification['message'],
                                        'created_at' => $notification['created_at'],
                                        'index' => $index,
                                    ];
                                }

                                foreach ($messages as $index => $message) {
                                    $conversation[] = [
                                        'type' => 'message',
                                        'content' => $message['message'],
                                        'created_at' => $message['created_at'],
                                        'index' => $index,
                                    ];
                                }

                                usort($conversation, function ($a, $b) {
                                    return strtotime($a['created_at']) - strtotime($b['created_at']);
                                });
                                ?>

                                <?php foreach ($conversation as $item): ?>
                                    <?php if ($item['type'] === 'notification'): ?>
                                        <div class="message <?php echo (time() - strtotime($item['created_at']) < 86400) ? 'recent' : 'old'; ?>">
                                            <div class="message-content">
                                                <p><?= makeLinksClickable($item['content']) ?></p>
                                            </div>
                                            <div class="message-footer">
                                                <small class="message-time"><?= htmlspecialchars($item['created_at']) ?></small>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="your-message <?php echo (time() - strtotime($item['created_at']) < 86400) ? 'recent' : 'old'; ?>">
                                            <div class="your-message-content">
                                                <p><strong>You: </strong></p>
                                                <p><?= makeLinksClickable($item['content']) ?></p>
                                            </div>
                                            <div class="your-message-footer">
                                                <small class="your-message-time"><?= htmlspecialchars($item['created_at']) ?></small>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p>No messages or notifications yet.</p>
                            <?php endif; ?>
                            <div id="scroll-sentinel"></div>
                        </div>
                        <!-- Message form -->
                        <form style="display: flex;justify-content: space-around;" action="../System/send_message.php" method="POST">
                            <input type="hidden" name="send_message" value="1">
                            <input type="hidden" name="name" value="<?php echo htmlspecialchars($_SESSION['fullname']); ?>" required>
                            <input type="hidden" name="email" value="<?php echo htmlspecialchars($user_email); ?>" required>
                            <input type="hidden" name="mobile" value="<?php echo htmlspecialchars($user_phone); ?>" required>
                            <input type="hidden" name="subject" value="help:">

                            <input style="width: 50%;margin: 0;" class="input-field" type="text" name="message" placeholder="Type your message..." required>
                            <input  class="submit-btn" type="submit" value="Send">
                        </form>
                        <script>
                            (function () {
                            const box = document.getElementById('conversationBox');
                            if (!box) return;

                            const sentinel = document.getElementById('scroll-sentinel') || (() => {
                                const d = document.createElement('div');
                                d.id = 'scroll-sentinel';
                                box.appendChild(d);
                                return d;
                            })();

                            function scrollToBottom() {
                                // Belt & suspenders: do both to cover all cases
                                box.scrollTop = box.scrollHeight;
                                sentinel.scrollIntoView({ block: 'end' });
                            }

                            // 1) After DOM laid out
                            requestAnimationFrame(scrollToBottom);
                            // 2) After all assets (fonts/images) load
                            window.addEventListener('load', scrollToBottom);
                            // 3) Whenever messages change (AJAX, PHP partials, etc.)
                            new MutationObserver(scrollToBottom).observe(box, { childList: true });

                            // 4) If this panel is revealed later (tabs/modals), re-run on size change
                            if ('ResizeObserver' in window) {
                                new ResizeObserver(scrollToBottom).observe(box);
                            }
                            })();
                        </script>
                    </div>
                    <div class="premium-container" id="selectdarkmode" style="display: none;flex-direction: column;">
                        <h2 class="premium-title" data-arabe="الترقية إلى بريميوم" data-france="Passer à Premium" data-english="Upgrade to Premium">Upgrade to Premium</h2>
                        <p class="premium-message" data-arabe="افتح الوصول إلى ميزات حصرية، أدوات، ودعم..." data-france="Débloquez l'accès à des fonctionnalités exclusives, des outils et un support..." data-english="Unlock access to exclusive features, tools, and support...">Unlock access to exclusive features, tools, and support...</p>
                        <br><br>
                        <div class="pricing">
                            <div class="card-pricing">
                                <div class="content">
                                    <h4>Basic Plan</h4>
                                    <h3>0$</h3>
                                    <p>
                                        <i class="ri-checkbox-circle-line"></i>
                                        2 Trading Journal Account
                                    </p>
                                    <p>
                                        <i class="ri-checkbox-circle-line"></i>
                                        50 Track Record data
                                    </p>
                                    <p>
                                        <i class="ri-checkbox-circle-line"></i>
                                        Simple analysis chart
                                    </p>
                                </div>
                                <button class="submit-btn" onclick="location.href='index.html';">Join Now</button>
                            </div>
                            <div class="card-pricing">
                                <div class="content">
                                    <h4>Gold Plan</h4>
                                    <h3>15$</h3>
                                    <p>
                                        <i class="ri-checkbox-circle-line"></i>
                                        5 Trading Journal Account
                                    </p>
                                    <p>
                                        <i class="ri-checkbox-circle-line"></i>
                                        500 Track Record data
                                    </p>
                                    <p>
                                        <i class="ri-checkbox-circle-line"></i>
                                        Advanced analysis chart
                                    </p>
                                    <p>
                                        <i class="ri-checkbox-circle-line"></i>
                                        Support Access
                                    </p>
                                </div>
                                <button class="submit-btn" onclick="location.href='index.html';">Join Now</button>
                            </div>
                            <div class="card-pricing">
                                <div class="content">
                                    <h4>Diamond Plan</h4>
                                    <h3>30$</h3>
                                    <p>
                                        <i class="ri-checkbox-circle-line"></i>
                                        10 Trading Journal Account
                                    </p>
                                    <p>
                                        <i class="ri-checkbox-circle-line"></i>
                                        Unlimited Track Record
                                    </p>
                                    <p>
                                        <i class="ri-checkbox-circle-line"></i>
                                        Support Access
                                    </p>
                                    <p>
                                        <i class="ri-checkbox-circle-line"></i>
                                        Private Coaching
                                    </p>
                                    <p>
                                        <i class="ri-checkbox-circle-line"></i>
                                        ACCESS ForexPeak Community
                                    </p>
                                </div>
                                <button class="submit-btn" onclick="location.href='index.html';">Join Now</button>
                            </div>
                        </div>
                    </div>
                    <div class="System-container" style="display: none;flex-direction: column;">
                        <h2 class="system-settings-title" data-arabe="إعدادات النظام" data-france="Paramètres du système" data-english="System Settings">System Settings</h2>
                        <p class="system-settings-message" data-arabe="قم بتعديل تفضيلات التداول الخاصة بك..." data-france="Ajustez vos préférences de trading..." data-english="Adjust your trading preferences...">Adjust your trading preferences...</p>
                        <select name="" id="theme-select" class="custom-select">
                            <option value="dark"  data-arabe="فاتح" data-france="Clair" data-english="Light">Light</option>
                            <option value="light" data-arabe="داكن" data-france="Sombre" data-english="Dark">Dark</option>
                        </select>
                        <br><br>
                        <select name="" id="language-select" class="custom-select">
                            <option value="default" data-arabe="افتراضي" data-france="Défaut" data-english="Default" disabled selected>Default</option>
                            <option value="Arabe" data-arabe="العربية" data-france="Arabe" data-english="Arabic">Arabe</option>
                            <option value="English" data-arabe="الإنجليزية" data-france="Anglais" data-english="English">English</option>
                            <option value="French" data-arabe="الفرنسية" data-france="Français" data-english="French">French</option>
                        </select>
                        <br><br>
                        <button id="settings-button" class="submit-btn" data-arabe="الإعدادات" data-france="Paramètres" data-english="Settings">Settings</button>
                    </div>
                    <div class="About-container" style="display: none;flex-direction: column;">
                        <h2 class="about-us-title" data-arabe="كيفية استخدام الموقع" data-france="Comment utiliser ce site" data-english="How to Use This Website">How to Use This Website</h2>

                        <!-- Step 1: Sign Up or Log In -->
                        <div class="how-to-step">
                            <h3 data-arabe="الخطوة 1: التسجيل أو تسجيل الدخول" data-france="Étape 1 : S'inscrire ou Se Connecter" data-english="Step 1: Sign Up or Log In">Step 1: Sign Up or Log In</h3>
                            <p data-arabe="قم بإنشاء حساب جديد أو سجل الدخول باستخدام بياناتك الحالية." data-france="Créez un nouveau compte ou connectez-vous avec vos informations existantes." data-english="Create a new account or log in using your existing credentials.">
                                Create a new account or log in using your existing credentials.
                            </p>
                            <video autoplay muted loop class="how-to-img" data-arabe="صورة توضح كيفية التسجيل أو تسجيل الدخول" data-france="Image montrant comment s'inscrire ou se connecter" data-english="Image showing how to sign up or log in">
                                <source src="../images/how-to-signup.mp4" type="video/mp4">
                                Your browser does not support the video tag.
                            </video>
                        </div>

                        <!-- Step 2: Explore the Dashboard -->
                        <div class="how-to-step">
                            <h3 data-arabe="الخطوة 2: استكشف لوحة التحكم" data-france="Étape 2 : Explorer le Tableau de Bord" data-english="Step 2: Explore the Dashboard">Step 2: Explore the Dashboard</h3>
                            <p data-arabe="استخدم لوحة التحكم للوصول إلى الأدوات والتحليلات الخاصة بك." data-france="Utilisez le tableau de bord pour accéder à vos outils et analyses." data-english="Use the dashboard to access your tools and analytics.">
                                Use the dashboard to access your tools and analytics.
                            </p>
                            <img src="../images/how-to-dashboard.jpg" alt="Explore the Dashboard" class="how-to-img" data-arabe="صورة توضح كيفية استكشاف لوحة التحكم" data-france="Image montrant comment explorer le tableau de bord" data-english="Image showing how to explore the dashboard">
                        </div>

                        <!-- Step 3: Add a Trading Account -->
                        <div class="how-to-step">
                            <h3 data-arabe="الخطوة 3: إضافة حساب تداول" data-france="Étape 3 : Ajouter un Compte de Trading" data-english="Step 3: Add a Trading Account">Step 3: Add a Trading Account</h3>
                            <p data-arabe="أضف حساب تداول جديد لتتبع صفقاتك وأدائك." data-france="Ajoutez un nouveau compte de trading pour suivre vos transactions et performances." data-english="Add a new trading account to track your trades and performance.">
                                Add a new trading account to track your trades and performance.
                            </p>
                            <img src="../images/how-to-add-account.jpg" alt="Add a Trading Account" class="how-to-img" data-arabe="صورة توضح كيفية إضافة حساب تداول" data-france="Image montrant comment ajouter un compte de trading" data-english="Image showing how to add a trading account">
                        </div>

                        <!-- Step 4: Analyze Your Trades -->
                        <div class="how-to-step">
                            <h3 data-arabe="الخطوة 4: تحليل صفقاتك" data-france="Étape 4 : Analyser Vos Transactions" data-english="Step 4: Analyze Your Trades">Step 4: Analyze Your Trades</h3>
                            <p data-arabe="استخدم أدوات التحليل لمراجعة أداء صفقاتك وتحسين استراتيجياتك." data-france="Utilisez les outils d'analyse pour examiner vos performances et améliorer vos stratégies." data-english="Use the analytics tools to review your trade performance and improve your strategies.">
                                Use the analytics tools to review your trade performance and improve your strategies.
                            </p>
                            <img src="../images/how-to-analyze.jpg" alt="Analyze Your Trades" class="how-to-img" data-arabe="صورة توضح كيفية تحليل الصفقات" data-france="Image montrant comment analyser les transactions" data-english="Image showing how to analyze trades">
                        </div>

                        <!-- Step 5: Join the Community -->
                        <div class="how-to-step">
                            <h3 data-arabe="الخطوة 5: انضم إلى المجتمع" data-france="Étape 5 : Rejoindre la Communauté" data-english="Step 5: Join the Community">Step 5: Join the Community</h3>
                            <p data-arabe="تفاعل مع المتداولين الآخرين وشارك النصائح والاستراتيجيات." data-france="Interagissez avec d'autres traders et partagez des conseils et stratégies." data-english="Interact with other traders and share tips and strategies.">
                                Interact with other traders and share tips and strategies.
                            </p>
                            <video autoplay muted loop class="how-to-img" data-arabe="صورة توضح كيفية التسجيل أو تسجيل الدخول" data-france="Image montrant comment s'inscrire ou se connecter" data-english="Image showing how to sign up or log in">
                                <source src="../images/how-to-community.mp4" type="video/mp4">
                                Your browser does not support the video tag.
                            </video>
                        </div>

                        <!-- Call to Action -->
                        <div class="how-to-step">
                            <h3 data-arabe="ابدأ الآن!" data-france="Commencez Maintenant!" data-english="Get Started Now!">Get Started Now!</h3>
                            <p data-arabe="انضم إلى ForexPeak وابدأ رحلتك نحو التداول الناجح." data-france="Rejoignez ForexPeak et commencez votre voyage vers le trading réussi." data-english="Join ForexPeak and start your journey towards successful trading.">
                                Join ForexPeak and start your journey towards successful trading.
                            </p>
                            <button class="submit-btn" onclick="location.href='../index.html#OURPLANS';" data-arabe="انضم إلى المجتمع" data-france="Rejoindre la Communauté" data-english="Join the Community">Join the Community</button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
        <div id="rightSection" class="right-section">
            <div class="nav">
                <label id="menu-btn">
                    <span class="material-icons-sharp">
                        menu
                    </span>
                    <input type="checkbox" style="display: none;" id="menu-btncheckbox">
                </label>
                <div class="dark-mode">
                    <span class="material-icons-sharp active">
                        dark_mode
                    </span>
                    <span class="material-icons-sharp">
                        light_mode
                    </span>
                </div>
                <div class="profile">
                    <div class="info">
                        <p data-arabe="مرحبًا ، <?php echo htmlspecialchars($_SESSION['fullname']); ?>" data-france="Salut , <?php echo htmlspecialchars($_SESSION['fullname']); ?>" data-english="Hi , <?php echo htmlspecialchars($_SESSION['fullname']); ?>">Hi , <?php echo htmlspecialchars($_SESSION['fullname']); ?></p>
                        <small class="text-muted" id="current-time"></small>
                    </div>
                    <div onclick="profile()" class="profile-photo">
                        <img src="../images/default-avatar-profile-icon-of-social-media-user-vector-removebg-preview.png">
                    </div>
                </div>
            </div>
            <!-- End of Nav -->
            <div class="user-profile active-user-profile">
                <div class="analyse">
                    <div class="sales">
                        <div class="status">
                            <div class="info">
                                <h1 data-arabe="أضف حسابًا" data-france="Ajouter un compte" data-english="Add Account">Add Account</h1>
                            </div>
                        </div>

                        <div class="reminders">
                            <div class="header">
                                <span class="material-icons-sharp">
                                    person
                                </span>
                                <h2 data-arabe="الحسابات" data-france="Comptes" data-english="Accounts">Accounts</h2>
                            </div>
                            <!--style user -->
                            <?php foreach ($users as $user): ?>
                                <div class="notification">
                                    <div onclick="toggleRadio(<?php echo $user['id']; ?>)" class="icon">
                                        <span class="material-icons-sharp">person</span>
                                    </div>

                                    <div class="info" style="max-width:60px;" onclick="toggleRadio(<?php echo $user['id']; ?>)">
                                        <h3><?php echo htmlspecialchars($user['fullname']); ?></h3>
                                        <small class="text_muted"><?php echo htmlspecialchars($user['capital']); ?>$</small>
                                    </div>

                                    <span onclick="confirmDelete(<?php echo $user['id']; ?>)" class="material-icons-sharp">delete</span>
                                    <input 
                                        onclick="showUserInfo('<?php echo htmlspecialchars($user['fullname']); ?>','<?php echo htmlspecialchars($user['id']); ?>')" 
                                        name="radio" 
                                        type="radio" 
                                        class="input" 
                                        id="radio-<?php echo $user['id']; ?>" 
                                        <?php if ($user === reset($users)) echo "checked"; ?> 
                                    />
                                </div>
                            <?php endforeach; ?>
                            
                            <input type="hidden" style="background-color:transparent;" id="userCapital" name="Capital">
                            <div id="AccountButoninfo" class="notification add-reminder">
                                <div>
                                    <span class="material-icons-sharp">
                                        add
                                    </span>
                                    <h3 data-arabe="أضف حسابًا" data-france="Ajouter un compte" data-english="Add Account">Add Account</h3>
                                </div>
                            </div>
                            <div id="newnote" class="notification add-reminder">
                                <div>
                                    <span class="material-icons-sharp">
                                        add
                                    </span>
                                    <h3 data-arabe="تجارة جديدة" data-france="Nouvelle Trade" data-english="New Trade">New Trade</h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="user-profile">
                <div class="analyse" style="width:100%;">
                    <img src="../images/ADS.png" alt="Add a Trading Account" class="how-to-img" data-arabe="صورة توضح كيفية إضافة حساب تداول" data-france="Image montrant comment ajouter un compte de trading" data-english="Image showing how to add a trading account">
                </div>
            </div>
        </div>
    </div>
    <div id="AddAccountDiv" style="display: none;" class="modal">
        <div class="modal-content">
            <span class="close" id="closeAddAccountDiv">&times;</span>
            <h1 data-arabe="إضافة حساب جديد" data-france="Ajouter un nouveau compte" data-english="Add New Account">Add New Account</h1>
            <form action="" class="Formeno" method="POST">
                <label for="fullname" data-arabe="الاسم الكامل:" data-france="NOM COMPLET:" data-english="FULL NAME:">FULL NAME :</label>
                <input type="text" class="form-control" id="fullname" name="fullname" maxlength="8" required><br>
                <label for="capital" data-arabe="رأس المال:" data-france="CAPITAL:" data-english="CAPITAL:">CAPITAL :</label>
                <input type="text" class="form-control" id="capital" name="capital" required><br>
                <br>
                
                <button type="submit" class="AddUser" data-arabe="إضافة مستخدم" data-france="Ajouter un utilisateur" data-english="Add User">Add User</button>
            </form>
        </div>
    </div>
    <!-- Modal Structure for Update Confirmation -->
    <div id="updateConfirmationModal" style="display:none;">
        <div class="modal-content2">
            <p>Are you sure you want to update your information?</p>
            <button id="confirmUpdateYes" class="modal-button">Yes</button>
            <button id="confirmUpdateNo" class="modal-button">No</button>
        </div>
    </div>
    <div>
        <?php foreach ($users as $user): ?>
            <div id="FormenoteModal" class="modal">
                <div class="modal-content">
                    <form id="Formeno" class="Formeno" action="../System/add_journal.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" style="background-color:transparent;" id="userId" name="userId" value="<?php echo $user['id']; ?>">
                        <input type="hidden" style="background-color:transparent;" id="adminId" name="adminId" value="<?php echo $_SESSION['user_id']; ?>">
                        <span class="close" id="closeNewNote">&times;</span>
                        <h2 id="modalDateHeading" data-arabe="تاريخ الملاحظة" data-france="Date de la note" data-english="Note Date"></h2>

                        <!-- Hidden input to track the selected method -->
                        <input type="hidden" id="selectedMethod" name="selectedMethod" value="">

                        <!-- Select Method of Saving Data -->
                        <label class="form-label" for="saveMethod" data-arabe="طريقة الحفظ" data-france="Méthode de sauvegarde" data-english="Save Method"></label>
                        <select id="saveMethod" name="saveMethod" class="form-control" onchange="toggleSaveMethod()" required>
                            <option value="fileUpload" data-arabe="تحميل الملف" data-france="Télécharger le fichier" data-english="File Upload">File Upload</option>
                            <option value="manualEntry" data-arabe="إدخال يدوي" data-france="Entrée manuelle" data-english="Manual Entry">Manual Entry</option>
                            <option value="payoutDeposit" data-arabe="دفع/إيداع" data-france="Paiement/Dépôt" data-english="Payout/Deposit">Payout/Deposit/commitiom</option>
                        </select>

                        <!-- File upload for document (hidden by default) -->
                        <div id="fileUploadSection" style="display: none;">
                            <label class="form-label" for="document" data-arabe="تحميل المستند" data-france="Télécharger le document" data-english="Upload Document"></label>
                            <input type="file" id="fileUpload" name="document" class="form-control" accept=".html">
                        </div>

                        <!-- Manual entry fields (hidden by default) -->
                        <div id="manualEntrySection" style="display: none;">
                            <label class="form-label" for="noteDate" data-arabe="تاريخ الفتح :" data-france="DATE OUVERT :" data-english="DATE OPEN :"></label>
                            <input type="datetime-local" id="noteDate" name="date_journal" class="form-control">

                            <label class="form-label" for="date_journal_close" data-arabe="تاريخ الإغلاق :" data-france="DATE FERMÉ :" data-english="DATE CLOSE :"></label>
                            <input type="datetime-local" id="date_journal_close" name="date_journal_close" class="form-control">

                            <label for="forexPair" class="form-label" data-arabe="الزوج :" data-france="PAIRE :" data-english="PAIR :">PAIR :</label>
                            <select id="forexPair" name="pair" class="forex-select">
                                <option value="EURUSD" data-arabe="يورو/دولار" data-france="EUR/USD" data-english="EUR/USD">EUR/USD</option>
                                <option value="EURJPY" data-arabe="يورو/ ين" data-france="EUR/JPY" data-english="EUR/JPY">EUR/JPY</option>
                                <option value="GBPUSD" data-arabe="جنيه إسترليني/دولار" data-france="GBP/USD" data-english="GBP/USD">GBP/USD</option>
                                <option value="GBPJPY" data-arabe="جنيه إسترليني/ين" data-france="GBP/JPY" data-english="GBP/JPY">GBP/JPY</option>
                                <option value="GBPAUD" data-arabe="الجنيه الاسترليني / الدولار الاسترالي" data-france="GBP/AUD" data-english="GBP/AUD">GBP/AUD</option>
                                <option value="AUDJPY" data-arabe="دولار أسترالي/ين" data-france="AUD/JPY" data-english="AUD/JPY">AUD/JPY</option>
                                <option value="AUDUSD" data-arabe="دولار أسترالي/دولار" data-france="AUD/USD" data-english="AUD/USD">AUD/USD</option>
                                <option value="USDJPY" data-arabe="دولار/ين" data-france="USD/JPY" data-english="USD/JPY">USD/JPY</option>
                                <option value="USDCAD" data-arabe="دولار/دولار كندي" data-france="USD/CAD" data-english="USD/CAD">USD/CAD</option>
                                <option value="USDCHF" data-arabe="دولار/فرنك سويسري" data-france="USD/CHF" data-english="USD/CHF">USD/CHF</option>
                                <option value="NZDUSD" data-arabe="دولار نيوزيلندي/دولار" data-france="NZD/USD" data-english="NZD/USD">NZD/USD</option>
                                <option value="XAUUSD" data-arabe="ذهب/دولار" data-france="XAU/USD" data-english="XAU/USD">XAU/USD</option>
                                <option value="XAGUSD" data-arabe="فضة/دولار" data-france="XAG/USD" data-english="XAG/USD">XAG/USD</option>
                                <option value="US30" data-arabe="US30 (داو جونز)" data-france="US30 (Dow Jones)" data-english="US30 (Dow Jones)">US30 (Dow Jones)</option>
                                <option value="SPX500" data-arabe="SP500" data-france="S&P 500" data-english="S&P 500">S&P 500</option>
                                <option value="NAS100" data-arabe="ناسداك 100" data-france="Nasdaq 100" data-english="Nasdaq 100">Nasdaq 100</option>
                                <option value="custom" id="customNewSymbol">Add New Symbol</option>
                            </select>
                            
                            <label class="form-label" id="labelcustomSymbol" for="customSymbol" data-arabe="الزوج :" data-france="PAIRE :" data-english="PAIR :" style="display: none;"></label>
                            <input type="text" id="customSymbol" class="form-control" style="display: none;" placeholder="Enter new symbol (XXXXXX)" />

                            <script>
                                document.getElementById("forexPair").addEventListener("change", function () {
                                    let selectedValue = this.value;
                                    let customSymbolInput = document.getElementById("customSymbol");
                                    let labelcustomSymbol = document.getElementById("labelcustomSymbol");

                                    if (selectedValue === "custom") {
                                        customSymbolInput.style.display = "block";
                                        labelcustomSymbol.style.display = "block";
                                        customSymbolInput.disabled = false; // Enable input when visible
                                        customSymbolInput.value = ""; // Reset input field
                                    } else {
                                        customSymbolInput.style.display = "none";
                                        labelcustomSymbol.style.display = "none";
                                        customSymbolInput.disabled = true; // Disable input when hidden
                                        customSymbolInput.value = ""; // Clear the value to prevent submission
                                    }
                                });
                                document.getElementById("customSymbol").addEventListener("input", function () {
                                    let regex = /^[A-Za-z]{6}$/; // Format: XXXXXX (six letters only)
                                    let forexPairSelect = document.querySelector(".forex-select");
                                    let newSymbol = this.value.toUpperCase();

                                    if (!regex.test(newSymbol)) {
                                        this.style.border = "2px solid red"; // Show red border for invalid input
                                    } else {
                                        this.style.border = "2px solid green"; // Green border for valid input

                                        // Check if the option already exists
                                        let existingOption = Array.from(forexPairSelect.options).find(option => option.value === newSymbol);
                                        
                                        if (!existingOption) {
                                            let newOption = document.createElement("option");
                                            newOption.value = newSymbol;
                                            newOption.textContent = newSymbol;
                                            forexPairSelect.appendChild(newOption);
                                        }

                                        forexPairSelect.value = newSymbol;
                                    }
                                });
                            </script> 
                            <label class="form-label" for="entry" data-arabe="المدخل :" data-france="ENTRÉE :" data-english="ENTRY :"></label>
                            <input type="number" id="entry" name="entry" class="form-control" placeholder="Enter your entry here..." step="0.00001" min="0">

                            <label class="form-label" for="sl" data-arabe="وقف الخسارة :" data-france="STOP LOSS :" data-english="STOP LOSS :"></label>
                            <input type="number" id="sl" name="sl" class="form-control" placeholder="Enter your SL here..." step="0.00001" min="0">

                            <label class="form-label" for="tp" data-arabe="جني الأرباح :" data-france="TAKE PROFIT :" data-english="TAKE PROFIT :"></label>
                            <input type="number" id="tp" name="tp" class="form-control" placeholder="Enter your TP here..." step="0.00001" min="0">

                            <label class="form-label" for="close" data-arabe="الربح: ($)" data-france="PROFIT : ($)" data-english="PROFIT: ($)"></label>
                            <input type="number" id="close" name="close_journal" class="form-control" placeholder="Enter your close here..." step="0.00001">

                            <label for="description" data-arabe="الملاحظة :" data-france="NOTE :" data-english="NOTE :"></label>
                            <textarea id="description" name="descriptionnamual"></textarea><br>
                        </div>

                        <!-- Payout/Deposit Section (hidden by default) -->
                        <div id="payoutDepositSection" style="display: none;">
                            <label class="form-label" for="payoutDepositType" data-arabe="النوع" data-france="Type" data-english="Type"></label>

                            <label for="payout" data-arabe="دفع" data-france="Paiement" data-english="Payout">Payout</label>
                            <input type="radio" id="payout" name="payoutDepositType" value="payout" required>

                            <label for="deposit" data-arabe="إيداع" data-france="Dépôt" data-english="Deposit">Deposit</label>
                            <input type="radio" id="deposit" name="payoutDepositType" value="deposit" required>

                            <label for="commission" data-arabe="عمولة" data-france="Commission" data-english="Commission">Commission</label>
                            <input type="radio" id="commission" name="payoutDepositType" value="payout" required>

                            <label class="form-label" for="amount" data-arabe="المبلغ" data-france="Montant" data-english="Amount"></label>
                            <input type="number" id="amount" name="amount" class="form-control" placeholder="Enter amount here..." step="0.01" min="0" required>

                            <label class="form-label" for="payoutDate" data-arabe="تاريخ الدفع/الإيداع" data-france="Date de paiement/dépôt" data-english="Payout/Deposit/Commission Date"></label>
                            <input type="datetime-local" id="payoutDate" name="payoutDate" class="form-control" required>

                            <label class="form-label" for="virificateimage" data-arabe="دليل على التحويل" data-france="Preuve de conversion" data-english="Evidence of transformation"></label>
                            <input type="file" id="virificateimage" name="virificateimage" class="form-control" accept="image/*" >

                            <label for="descriptionPayoutDeposit" data-arabe="الملاحظة :" data-france="NOTE :" data-english="NOTE :"></label>
                            <textarea id="descriptionPayoutDeposit" name="description"></textarea><br>
                        </div>

                        <button type="submit" id="saveNoteButton" data-arabe="حفظ الملاحظة" data-france="Enregistrer la note" data-english="Save Note">Save Note</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
        <div id="shownote" class="modal">
            <div class="modal-content">
                <span class="close" id="closeShowNote">&times;</span>
                <h2 id="modalDateHeadingShowNote"></h2>
                <div id="shownoteContent">
                    <!-- User data will be displayed here -->
                </div>
            </div>
        </div>
    </div>
    <div class="successful">
        <div class="successful__icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" viewBox="0 0 24 24" height="24" fill="none">
                <circle cx="12" cy="12" r="10" fill="#d4edda" />
                <path fill="#28a745" d="M9 12.5l2 2 4-4-1.5-1.5-2.5 2.5-1-1z" />
            </svg>
        </div>
        <div class="successful__title">The operation was completed successfully.</div>
        <div class="successful__close" onclick="closeNotification(this)">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" viewBox="0 0 20 20" height="20">
                <path fill="#393a37" d="m15.8333 5.34166-1.175-1.175-4.6583 4.65834-4.65833-4.65834-1.175 1.175 4.65833 4.65834-4.65833 4.6583 1.175 1.175 4.65833-4.6583 4.6583 4.6583 1.175-1.175-4.6583-4.6583z"></path>
            </svg>
        </div>
        <audio style="display: none;" id="successful_notificationSound" src="../images/level-up-191997.mp3" preload="auto"></audio>
    </div>
    <div class="error">
        <div class="error__icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" viewBox="0 0 24 24" height="24" fill="none">
                <path fill="#393a37" d="m13 13h-2v-6h2zm0 4h-2v-2h2zm-1-15c-1.3132 0-2.61358.25866-3.82683.7612-1.21326.50255-2.31565 1.23915-3.24424 2.16773-1.87536 1.87537-2.92893 4.41891-2.92893 7.07107 0 2.6522 1.05357 5.1957 2.92893 7.0711.92859.9286 2.03098 1.6651 3.24424 2.1677 1.21325.5025 2.51363.7612 3.82683.7612 2.6522 0 5.1957-1.0536 7.0711-2.9289 1.8753-1.8754 2.9289-4.4189 2.9289-7.0711 0-1.3132-.2587-2.61358-.7612-3.82683-.5026-1.21326-1.2391-2.31565-2.1677-3.24424-.9286-.92858-2.031-1.66518-3.2443-2.16773-1.2132-.50254-2.5136-.7612-3.8268-.7612z"></path>
            </svg>
        </div>
        <?php if (isset($_SESSION['message'])): ?>
            <?php echo htmlspecialchars($_SESSION['message']); ?>
            <?php unset($_SESSION['message']); // Clear the message after displaying it ?>
        <?php endif; ?>
        <div class="error__close" onclick="closeNotification(this)">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" viewBox="0 0 20 20" height="20">
                <path fill="#393a37" d="m15.8333 5.34166-1.175-1.175-4.6583 4.65834-4.65833-4.65834-1.175 1.175 4.65833 4.65834-4.65833 4.6583 1.175 1.175 4.65833-4.6583 4.6583 4.6583 1.175-1.175-4.6583-4.6583z"></path>
            </svg>
        </div>
        <audio style="display: none;" id="error_notificationSound" src="../images/error-8-206492.mp3" preload="auto"></audio>
    </div>
    <div id="load" class="load">
        <div class="spinner"></div>
    </div>
</div>
</body>
</html>
<script>
    function toggleSaveMethod() {
        let method = document.getElementById("saveMethod").value;
        document.getElementById("selectedMethod").value = method; 

        // Sections
        let fileUploadSection = document.getElementById("fileUploadSection");
        let manualEntrySection = document.getElementById("manualEntrySection");
        let payoutDepositSection = document.getElementById("payoutDepositSection");

        // Inputs
        let fileUploadInput = document.getElementById("fileUpload");
        let manualEntryInputs = manualEntrySection.querySelectorAll("input, textarea, select");
        let payoutDepositInputs = payoutDepositSection.querySelectorAll("input, textarea");

        // Hide all sections by default
        fileUploadSection.style.display = "none";
        manualEntrySection.style.display = "none";
        payoutDepositSection.style.display = "none";

        // Disable all inputs
        fileUploadInput.required = false;
        manualEntryInputs.forEach(input => input.required = false);
        payoutDepositInputs.forEach(input => input.required = false);

        // Show and enable only the selected section
        if (method === "fileUpload") {
            fileUploadSection.style.display = "block";
            fileUploadInput.required = true;
        } else if (method === "manualEntry") {
            manualEntrySection.style.display = "block";
            manualEntryInputs.forEach(input => input.required = true);
        } else if (method === "payoutDeposit") {
            payoutDepositSection.style.display = "block";
            payoutDepositInputs.forEach(input => input.required = true);
        }
    }

    // Initialize function on page load
    document.addEventListener("DOMContentLoaded", toggleSaveMethod);

    function successful() {
        // Find the successful notification container and show it
        const notification = document.querySelector('.successful');
        if (notification) {
            notification.style.display = 'flex';
            // Play the notification sound
            const audio = document.getElementById('successful_notificationSound');
            if (audio) {
                audio.play();
            }
            setTimeout(() => {
                notification.style.display = 'none';
            }, 2000);
        }
    }

    function error() {
        // Find the error notification container and show it
        const notification = document.querySelector('.error');
        if (notification) {
            notification.style.display = 'flex';
            // Play the notification sound
            const audio = document.getElementById('error_notificationSound');
            if (audio) {
                audio.play();
            }
            setTimeout(() => {
                notification.style.display = 'none';
            }, 2000);
        }
    }

    // Check if the URL has a "success" or "error" parameter
    const urlParams = new URLSearchParams(window.location.search);

    if (urlParams.get('success') === '1') {
        successful();
        removeQueryParameter('success');
    }

    if (urlParams.get('error') === '1') {
        error();
        removeQueryParameter('error');
    }

    function removeQueryParameter(param) {
        // Remove the specified parameter from the URL without reloading the page
        const newUrl = new URL(window.location.href);
        newUrl.searchParams.delete(param);
        window.history.replaceState({}, document.title, newUrl.pathname + newUrl.search);
    }

    function closeNotification(element) {
        // Find the parent notification container and hide it
        const notification = element.closest('.successful') || element.closest('.error');
        if (notification) {
            notification.style.display = 'none';
        }
    }

    function openUpdateModal() {
        document.getElementById('updateConfirmationModal').style.display = 'flex';
    }

    document.getElementById('confirmUpdateYes').onclick = function () {
        // Submit the form when the user confirms
        const form = document.querySelector('.account-info-form');
        if (form) {
            form.submit();
        }
        closeUpdateModal(); // Close the modal
    };

    document.getElementById('confirmUpdateNo').onclick = function () {
        closeUpdateModal(); // Close the modal without updating
    };

    function closeUpdateModal() {
        document.getElementById('updateConfirmationModal').style.display = 'none';
    }
</script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Initialize the input field value on page load if needed
        const savedUserId = localStorage.getItem('selectedUserId');
        const savedCapital = localStorage.getItem('capital');

        // Set the capital value if it exists in localStorage
        if (savedCapital) {
            document.getElementById('userCapital').value = savedCapital;
        }

        if (savedUserId) {
            const savedRadio = document.getElementById('radio-' + savedUserId);
            if (savedRadio) {
                savedRadio.checked = true;
                updateUserId(savedRadio);
            }
        }

        // Attach form submit event listener
        document.getElementById('Formeno').addEventListener('submit', function (event) {
            const userId = document.getElementById('userId').value;
            if (userId == 0) {
                event.preventDefault(); // Prevent the form from submitting
                alert('Please select an Account before tracking your record.');
            }
        });
    });
    function toggleRadio(userId) {
        // Get the radio button by its ID and check if it exists
        const radio = document.getElementById('radio-' + userId);

        if (radio) {
            fetch('../System/get_user_capital.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        userId: userId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Save capital value to localStorage
                        localStorage.setItem('capital', data.capital);
                        const capital = localStorage.getItem('capital');

                        document.getElementById('userCapital').value = data.capital;
                        showUserId(userId);
                        radio.checked = true;
                        updateUserId(radio);
                        localStorage.setItem('selectedUserId', userId);

                        location.reload();
                    } else {
                        document.getElementById('Formeno').submit();
                        document.getElementById('userCapital').value = 0;
                        localStorage.setItem('capital', 0);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
        }
    }
    function updateUserId(radioButton) {
        var userId = radioButton.id.split('-')[1]; // Extract the user ID from the radio button's id
        document.getElementById('userId').value = userId;
        showUserId(userId);
    }
    function openTab(event, tabName) {
        const tabContainer = event.currentTarget.closest('.tab-container');
        const tabContent = tabContainer.querySelectorAll(".tab-content");
        const tabButtons = tabContainer.querySelectorAll(".tab-button");

        // Hide all tab content and remove 'active' class from all buttons
        tabContent.forEach(content => content.style.display = "none");
        tabButtons.forEach(button => button.classList.remove("active"));

        // Display the selected tab's content and add 'active' class to the clicked button
        const selectedTabContent = tabContainer.querySelector(`.tab-content[data-tab-content='${tabName}']`);
        if (selectedTabContent) {
            selectedTabContent.style.display = "block";
        }
        event.currentTarget.classList.add("active");
    }
    function showUserId(userId) {
        document.getElementById('userId').value = userId;
        if (userId) {
            // Initialize all variables to 0
            let totalClose = 0;
            let totalPositiveClose = 0;
            let positiveTrades = 0;
            let totalTrades = 0;
            let missedTrades = 0;
            let averageRR = 0;
            let averageTimeInHours = 0;
            let totalRR = 0;
            let rrCount = 0;
            let totalTimeDifference = 0;
            let totalTradesWithCloseDate = 0;

            // New variables for Risk & Reward Overview
            let biggestWin = 0;
            let biggestLoss = 0;
            let highestRR = 0;
            let rrInBiggestWin = 0;
            let rrInBiggestLoss = 0;

            // New variables for Drawdown, Profitable RR, and Max Potential Profit
            let totalDrawdown = 0;
            let totalProfitableRR = 0;
            let totalMaxPotentialProfit = 0;
            let drawdownCount = 0;
            let profitableRRCount = 0;
            let maxPotentialProfitCount = 0;

            // New variables for Best/Worst Trade Times
            let bestTradeTime = null;
            let worstTradeTime = null;
            let totalBestTradeTime = 0;
            let totalWorstTradeTime = 0;
            let bestTradeTimeCount = 0;
            let worstTradeTimeCount = 0;

            const totalCloseDisplay = document.getElementById('totalCloseDisplay');
            const closeCapitalPercentage = document.getElementById('closeCapitalPercentage');
            const positiveClosePercentage = document.getElementById('positiveClosePercentage');
            let userCapital = parseFloat(document.getElementById('userCapital').value) || 0;

            const totalCloseByMonth = {};
            const totalCloseByYear = {};
            const yearSelector = document.getElementById('yearSelector');
            const tableBody = document.querySelector('#totalCloseTable tbody');
            let allYears = new Set();

            fetch(`../System/fetch_journal_data.php?id_users=${userId}`)
                .then(response => response.json())
                .then(data => {
                    // Replace 'null' with null for consistency
                    data.forEach(item => {
                        Object.keys(item).forEach(key => {
                            if (item[key] === 'null') {
                                item[key] = null;
                            }
                        });
                    });

                    // Separate valid and invalid data
                    const validData = data.filter(item => {
                        return (
                            item.close_journal !== null && item.close_journal !== '' && // Ensure close_journal is not empty
                            item.entry !== null && item.entry !== '' && // Ensure entry is not empty
                            item.tp !== null && item.tp !== '' && // Ensure tp is not empty
                            item.sl !== null && item.sl !== '' // Ensure sl is not empty
                        );
                    });

                    const invalidData = data.filter(item => {
                        return (
                            item.close_journal === null || item.close_journal === '' || // Include rows with empty close_journal
                            item.entry === null || item.entry === '' || // Include rows with empty entry
                            item.tp === null || item.tp === '' || // Include rows with empty tp
                            item.sl === null || item.sl === '' // Include rows with empty sl
                        );
                    });
                    function formatTimeWithAMPM(hour) {
                        if (hour === 0) {
                            return '12 AM';
                        } else if (hour < 12) {
                            return `${hour} AM`;
                        } else if (hour === 12) {
                            return '12 PM';
                        } else {
                            return `${hour - 12} PM`;
                        }
                    }
                    // If no valid data, update UI with default values
                    if (validData.length === 0) {
                        totalCloseDisplay.textContent = `$ 0.00`;
                        closeCapitalPercentage.textContent = `0.00%`;
                        positiveClosePercentage.textContent = `0.00%`;

                        totalCloseDisplay.style.color = 'gray';
                        closeCapitalPercentage.style.color = 'gray';
                        positiveClosePercentage.style.color = 'gray';

                        document.getElementById('totalTread').innerHTML = 'Total Trades: 0';
                        document.getElementById('MissedTread').innerHTML = 'Missed Trades: 0';
                        document.getElementById('average-rr-value').textContent = 'Average RR Value: 0';
                        document.getElementById('average-time-card').querySelector('h4').textContent = 'Average Time: 0 hours';

                        // Update Risk & Reward Overview with default values
                        document.getElementById('biggest-win').textContent = 'Biggest Win: $0.00';
                        document.getElementById('biggest-loss').textContent = 'Biggest Loss: $0.00';
                        document.getElementById('highest-rr').textContent = 'Highest RR: 0.00';
                        document.getElementById('rr-in-biggest-win').textContent = 'RR in Biggest Win: 0.00';
                        document.getElementById('rr-in-biggest-loss').textContent = 'RR in Biggest Loss: 0.00';

                        // Update new sections with default values
                        document.getElementById('average-drawdown').textContent = 'Average Drawdown: 0.00%';
                        document.getElementById('average-profitable-rr').textContent = 'Average Profitable RR: 0.00';
                        document.getElementById('average-max-potential-profit').textContent = 'Average Max Potential Profit: 0.00%';
                        document.getElementById('best-trade-time').textContent = 'Best Trade Time: 00:00';
                        document.getElementById('worst-trade-time').textContent = 'Worst Trade Time: 00:00';
                        document.getElementById('average-best-trade-time').textContent = 'Average Best Trade Time: 00:00';
                        document.getElementById('average-worst-trade-time').textContent = 'Average Worst Trade Time: 00:00';
                    }

                    // Process valid data for calculations and most UI updates
                    validData.sort((a, b) => new Date(a.date_journal) - new Date(b.date_journal));

                    const hestoriTableBody = document.querySelector('.hestoritable');
                    hestoriTableBody.innerHTML = '';
                    const miniTableBody = document.querySelector('.minitable');
                    miniTableBody.innerHTML = '';
                    const bestTableBody = document.querySelector('.thebesttable');
                    bestTableBody.innerHTML = '';
                    const TableBodyAllProfit = document.querySelector('.AllProfit');
                    TableBodyAllProfit.innerHTML = '';

                    const bestCloseByPair = {};
                    totalTrades = validData.length;
                    let totalclosenegative = 0;

                    const labels = [];
                    const closeValues = [];
                    const pairs = new Set();
                    const closeByPair = {};
                    const minValues = [];
                    const maxValues = [];
                    const totalCloseByPair = {};
                    let negativeCloseCount = 0;

                    const totalTradesProgress = document.querySelector('#progress-total-trades');

                    if (totalTradesProgress) {
                        setTimeout(() => {
                            const progressPercentage = (totalTrades / 100) * 100;
                            totalTradesProgress.style.width = `${progressPercentage}%`;
                        }, 1000);
                    }
                    /*data or validData */
                    validData.forEach(item => {
                        const date = new Date(item.date_journal);
                        const monthYear = `${date.getFullYear()}-${(date.getMonth() + 1).toString().padStart(2, '0')}`;
                        const year = date.getFullYear();
                        const closeJournalValue = parseFloat(item.close_journal) || 0;

                        // Track biggest win and loss
                        if (closeJournalValue > biggestWin) {
                            biggestWin = closeJournalValue;
                        }
                        if (closeJournalValue < biggestLoss) {
                            biggestLoss = closeJournalValue;
                        }

                        // Update total close by month, year, and overall total
                        totalCloseByMonth[monthYear] = (totalCloseByMonth[monthYear] || 0) + closeJournalValue;
                        totalCloseByYear[year] = (totalCloseByYear[year] || 0) + closeJournalValue;
                        totalClose += closeJournalValue;

                        // Track positive and negative closes
                        if (closeJournalValue > 0) {
                            totalPositiveClose += closeJournalValue;
                            positiveTrades++;
                        } else if (closeJournalValue < 0) {
                            negativeCloseCount++;
                            totalclosenegative += closeJournalValue;  // Add to the total negative close value
                        }

                        // Calculate risk to reward
                        const entry = parseFloat(item.entry);
                        const tp = parseFloat(item.tp);
                        const sl = parseFloat(item.sl);
                        const rrRatio = calculateRiskToReward(entry, tp, sl);

                        if (rrRatio !== 'Undefined') {
                            totalRR += parseFloat(rrRatio);
                            rrCount++;

                            if (closeJournalValue > 0) {
                                highestRR = Math.max(highestRR, parseFloat(rrRatio));
                                if (closeJournalValue === biggestWin) {
                                    rrInBiggestWin = parseFloat(rrRatio);
                                }
                                totalProfitableRR += parseFloat(rrRatio);
                                profitableRRCount++;
                            } else if (closeJournalValue === biggestLoss) {
                                rrInBiggestLoss = parseFloat(rrRatio);
                            }
                        }

                        // Track drawdown, potential profit, and best/worst trade time
                        const drawdown = (closeJournalValue / userCapital) * 100;
                        totalDrawdown += drawdown;
                        drawdownCount++;

                        const maxPotentialProfit = ((tp - entry) / entry) * 100;
                        totalMaxPotentialProfit += maxPotentialProfit;
                        maxPotentialProfitCount++;

                        const tradeTime = date.getHours();
                        if (closeJournalValue > 0) {
                            totalBestTradeTime += tradeTime;
                            bestTradeTimeCount++;
                        } else if (closeJournalValue < 0) {
                            totalWorstTradeTime += tradeTime;
                            worstTradeTimeCount++;
                        }

                        // Update close by pair and track best close by pair
                        totalCloseByPair[item.pair] = (totalCloseByPair[item.pair] || 0) + closeJournalValue;
                        
                        if (!bestCloseByPair[item.pair] || parseFloat(bestCloseByPair[item.pair].close_journal) < closeJournalValue) {
                            bestCloseByPair[item.pair] = item;
                        }

                        // Collect labels, close values, and other data
                        labels.push(item.date_journal);
                        closeValues.push(closeJournalValue);
                        minValues.push(Math.min(0, closeJournalValue - 10));
                        maxValues.push(Math.max(closeJournalValue + 10));
                        
                        closeByPair[item.pair] = (closeByPair[item.pair] || 0) + closeJournalValue;
                        pairs.add(item.pair);
                        allYears.add(year);

                        // Calculate time difference if available
                        if (item.date_close) {
                            const dateClose = new Date(item.date_close);
                            totalTimeDifference += dateClose - date;
                            totalTradesWithCloseDate++;
                        }
                    });

                    function formatTimeWithAMPM(time) {
                        let [hours] = time.split(':');
                        hours = parseInt(hours, 10);
                        const ampm = hours >= 12 ? 'PM' : 'AM';
                        hours = hours % 12;
                        hours = hours ? hours : 12; // the hour '0' should be '12'
                        return `${hours} ${ampm}`;
                    }

                    function generateAdvice(stats) {
                        let advice = '';

                        if (stats.risque_to_reward < 2) {
                            advice += 'Consider improving your risk/reward ratio for better potential returns. Aim for at least a 2:1 ratio.\n';
                        } else if (stats.risque_to_reward >= 2 && stats.risque_to_reward < 3) {
                            advice += 'Your risk/reward ratio is acceptable but could be improved for more consistent returns.\n';
                        } else {
                            advice += 'Great job on maintaining a solid risk/reward ratio. Keep it up!\n';
                        }

                        if (stats.averageDrawdown < 15) {
                            advice += 'Your average drawdown is higher than ideal. Consider reducing your position sizes or reviewing your stop-loss strategy.\n';
                        } else if (stats.averageDrawdown >= 15 && stats.averageDrawdown < 25) {
                            advice += 'Your drawdown is moderate. Be mindful of your risk management strategies.\n';
                        } else {
                            advice += 'Your drawdown is within acceptable limits. Ensure you maintain strong risk management practices.\n';
                        }

                        if (stats.bestTradeTime === '00:00') {
                            advice += 'You haven’t identified the best trade time yet. Review your trades and consider optimizing for specific time windows.\n';
                        } else if (stats.bestTradeTime !== '00:00' && stats.bestTradeTime !== 'undefined') {
                            advice += `Your best trade time is identified as ${formatTimeWithAMPM(stats.bestTradeTime)}. Try to focus your trades during this period.\n`;
                        } else {
                            advice += 'You might want to revisit your trade times for better optimization.\n';
                        }

                        if (stats.streakProbability.loss > 50) {
                            advice += 'There’s a high probability of a losing streak. Consider reducing trade size or taking a break if you encounter a loss streak.\n';
                        } else if (stats.streakProbability.loss >= 30 && stats.streakProbability.loss <= 50) {
                            advice += 'There is a moderate risk of a losing streak. Keep your position sizes conservative.\n';
                        } else {
                            advice += 'Your streak probability is low, but remain cautious and mindful of market conditions.\n';
                        }

                        // Add more conditions as needed
                        if (stats.winRate < 40) {
                            advice += 'Your win rate is below average. Consider reviewing your strategy and improving entry/exit points.\n';
                        } else if (stats.winRate >= 40 && stats.winRate < 60) {
                            advice += 'Your win rate is acceptable, but there’s room for improvement. Focus on optimizing your strategy.\n';
                        } else {
                            advice += 'Excellent win rate! Keep refining your strategy for continued success.\n';
                        }

                        document.getElementById('AI-genereater').innerText = advice;
                    }

                    let averageRR = rrCount > 0 ? (totalRR / rrCount).toFixed(2) : 'N/A';
                    const bestTradeTime = bestTradeTimeCount > 0 ? (totalBestTradeTime / bestTradeTimeCount).toFixed(0) : 0;
                    const totalTrCount = validData.length;
                    let userStats = {
                        risque_to_reward: averageRR,
                        averageDrawdown: totalclosenegative / drawdownCount,
                        bestTradeTime: bestTradeTime,
                        streakProbability: {
                            wins: validData.length - negativeCloseCount,
                            losses: negativeCloseCount
                        }
                    };
                    generateAdvice(userStats);
                    function calculateStreakProbabilities(data) {
                        let losingStreaks = [], winningStreaks = [];
                        let currentLosingStreak = 0, currentWinningStreak = 0;

                        data.forEach(item => {
                            const closeJournalValue = parseFloat(item.close_journal) || 0;

                            if (closeJournalValue < 0) {
                                currentLosingStreak++;
                                if (currentWinningStreak > 0) {
                                    winningStreaks.push(currentWinningStreak);
                                    currentWinningStreak = 0;
                                }
                            } else if (closeJournalValue > 0) {
                                currentWinningStreak++;
                                if (currentLosingStreak > 0) {
                                    losingStreaks.push(currentLosingStreak);
                                    currentLosingStreak = 0;
                                }
                            }
                        });

                        if (currentLosingStreak > 0) losingStreaks.push(currentLosingStreak);
                        if (currentWinningStreak > 0) winningStreaks.push(currentWinningStreak);

                        return {
                            bestLosingStreak: Math.max(0, ...losingStreaks),
                            bestWinningStreak: Math.max(0, ...winningStreaks)
                        };
                    }

                    const streakProbabilities = calculateStreakProbabilities(validData);
                    document.getElementById('total-losing-trades').textContent = `Losing Streak: ${streakProbabilities.bestLosingStreak}`;
                    document.getElementById('total-winning-trades').textContent = `Winning Streak: ${streakProbabilities.bestWinningStreak}`;
                    let losingStreakProbability = ((streakProbabilities.bestLosingStreak / totalTrCount) * 100).toFixed(2);
                    let winningStreakProbability = ((streakProbabilities.bestWinningStreak / totalTrCount) * 100).toFixed(2);

                    // Update HTML elements worst
                    document.getElementById('best-probability-losing-streak').textContent = `Best Probability Losing Streak: ${losingStreakProbability/2}%`;
                    document.getElementById('best-probability-winning-streak').textContent = `Best Probability Winning Streak: ${winningStreakProbability*2}%`;
                    document.getElementById('worst-probability-losing-streak').textContent = `worst Probability Losing Streak: ${losingStreakProbability}%`;
                    document.getElementById('worst-probability-winning-streak').textContent = `worst Probability Winning Streak: ${winningStreakProbability}%`;

                    // Process invalid data without rowHestori
                    invalidData.forEach(item => {
                        const closeJournalValue = parseFloat(item.close_journal) || 0;
                        totalClose += closeJournalValue; // Add payout (negative value) to total equity
                    });

                    // Merge valid and invalid data
                    const allData = [...validData, ...invalidData];

                    // Sort the data by date_journal (oldest to newest)
                    allData.sort((a, b) => new Date(a.date_journal) - new Date(b.date_journal));

                    // Initialize totals for each trade type
                    let totalPayout = 0;
                    let totalDeposit = 0;
                    let totalCommission = 0;
                    let bestPayout = 0; // Initially set to a very low value
                    let worstPayout = 0; // Initially set to a very high value
                    let biggestCommission = 0; // Initially set to a very low value

                    // Process sorted data with rowHestori
                    allData.forEach(item => {
                        const closeJournalValue = parseFloat(item.close_journal) || 0;
                        const color = (item.date_close === null) ? 'gray' : (closeJournalValue > 0 ? 'green' : closeJournalValue < 0 ? 'red' : 'gray');
                        const tradeType = (item.tp > item.entry) ? "Buy" : (item.tp < item.entry) ? "Sell" : "null";
                        const payoutTableBody = document.getElementById('payout-trade-table').querySelector('tbody');

                        // Update total values based on trade_type
                        if (item.trade_type === 'payout') {
                            totalPayout += closeJournalValue;

                            // Update the best and worst payout
                            if (closeJournalValue > bestPayout) bestPayout = closeJournalValue;
                            if (closeJournalValue < worstPayout) worstPayout = closeJournalValue;

                            // Create a row for the payout table
                            const row = document.createElement('tr');
                            row.innerHTML = `
                                <td>${item.date_journal}</td>
                                <td style="color: green;">$ ${closeJournalValue.toFixed(2) * -1}</td>
                                <td style="color: green;">${( (parseFloat(closeJournalValue.toFixed(2)) / userCapital) * -100 ).toFixed(3)}%</td>                                
                            `;
                            payoutTableBody.appendChild(row);
                        } else if (item.trade_type === 'deposit') {
                            totalDeposit += closeJournalValue;
                        } else if (item.trade_type === 'commission') {
                            totalCommission += closeJournalValue;

                            // Track biggest commission
                            if (closeJournalValue > biggestCommission) biggestCommission = closeJournalValue;
                        }
                        const payoutImageTable = document.getElementById('payout-image-table').querySelector('tbody');

                        // Vérifie si l'image existe et n'est pas vide
                        if (item.image && item.image.trim() !== '' && item.image.toLowerCase() !== 'null') {
                            const rowPayoutImage = document.createElement('tr');
                            rowPayoutImage.innerHTML = `
                                <td>
                                    <img class="slide" src="../verificationimage/${item.image}" alt="Payout Image">
                                </td>
                            `;

                            // Rendre l'image cliquable
                            const img = rowPayoutImage.querySelector('img');
                            img.addEventListener('click', function () {
                                openFullscreenImage(this.src);
                            });

                            payoutImageTable.appendChild(rowPayoutImage);
                        }

                        // Fonction pour ouvrir l'image en fullscreen dans un modal
                        function openFullscreenImage(src) {
                            // Crée un conteneur de modal
                            const modal = document.createElement('div');
                            modal.classList.add('image-modal');
                            modal.innerHTML = `
                                <div class="image-modal-content">
                                    <span class="close-btn">&times;</span>
                                    <img src="${src}" alt="Fullscreen Image">
                                </div>
                            `;

                            document.body.appendChild(modal);

                            // Fermer le modal au clic sur la croix ou à l'extérieur
                            modal.querySelector('.close-btn').addEventListener('click', () => modal.remove());
                            modal.addEventListener('click', e => {
                                if (e.target === modal) modal.remove();
                            });
                        }


                        // Add to history table if you want % <td class="table-cell" style="color: ${color};"> ${((parseFloat(closeJournalValue.toFixed(2)) / userCapital) * 100).toFixed(3)}%</td>
                        const rowHestori = document.createElement('tr');
                        rowHestori.innerHTML = `
                            <td class="table-cell">${item.date_journal}</td>
                            <td class="table-cell">${item.date_close || 'N/A'}</td>
                            <td class="table-cell">${item.pair}</td>
                            <td class="table-cell">${tradeType}</td>
                            <td class="table-cell">${item.entry}</td>
                            <td class="table-cell">${item.tp}</td>
                            <td class="table-cell">${item.sl}</td>
                            <td class="table-cell" style="color: ${color};">$ ${closeJournalValue.toFixed(2)}</td>
                            <td class="table-cell">
                                <button class="deletbtn" data-arabe="حذف" data-france="Supprimer" data-english="Delet" data-id="${item.id}">Delete</button>
                            </td>
                        `;
                        hestoriTableBody.appendChild(rowHestori);

                        const totalTrCount = validData.length;
                        document.getElementById('totalTread').innerHTML = 'Total Trades: ' + totalTrCount;
                        document.getElementById('MissedTread').innerHTML = 'Missed Trades: ' + negativeCloseCount;

                        const currentRowCount = miniTableBody.getElementsByTagName('tr').length;

                        if (currentRowCount < 10) {
                            const rowMini = document.createElement('tr');
                            rowMini.innerHTML = `
                                <td class="table-cell">${item.date_journal}</td>
                                <td class="table-cell">${item.pair}</td>
                                <td class="table-cell" style="color: ${color};">$ ${closeJournalValue.toFixed(2)}</td>
                            `;
                            miniTableBody.appendChild(rowMini);
                        } else if (currentRowCount === 10) {
                            const showMoreRow = miniTableBody.querySelector('.show-more-row');
                            if (!showMoreRow) {
                                const rowShowMore = document.createElement('tr');
                                rowShowMore.className = 'show-more-row';
                                rowShowMore.innerHTML = `
                                    <td colspan="3" style="text-align: center; cursor: pointer;" onclick="showMoreRows()">Show More...</td>
                                `;
                                miniTableBody.appendChild(rowShowMore);
                            }
                        }
                    });

                    // Update the totals in the DOM after processing all data
                    document.getElementById('total-payout-trades').innerText = `Total payout Trades: $${totalPayout.toFixed(2) * -1}`;
                    document.getElementById('total-deposit-trades').innerText = `Total deposit Trades: $${totalDeposit.toFixed(2)}`;
                    document.getElementById('total-commission-trades').innerText = `Total commission Trades: $${totalCommission.toFixed(2)}`;

                    // Add best and worst payout and biggest commission
                    document.getElementById('best-payout-trade').innerText = `Best payout Trade: $${worstPayout.toFixed(2) * -1}`;
                    document.getElementById('worst-payout-trade').innerText = `Worst payout Trade: $${bestPayout.toFixed(2) * -1}`;
                    document.getElementById('biggest-commission-trade').innerText = `Biggest commission Trade: $${biggestCommission.toFixed(2)}`;


                    // Calculate average time difference
                    averageTimeInHours = totalTradesWithCloseDate > 0 ? totalTimeDifference / (totalTradesWithCloseDate * 1000 * 60 * 60) : 0;

                    // Update the average time display
                    const averageTimeDisplay = document.getElementById('average-time-card').querySelector('h4');
                    averageTimeDisplay.textContent = `Average Time: ${averageTimeInHours.toFixed(2)} hours`;
                    averageTimeDisplay.setAttribute('data-arabe', `متوسط الوقت: ${averageTimeInHours.toFixed(2)} ساعة`);
                    averageTimeDisplay.setAttribute('data-france', `Temps moyen: ${averageTimeInHours.toFixed(2)} heures`);
                    averageTimeDisplay.setAttribute('data-english', `Average Time: ${averageTimeInHours.toFixed(2)} hours`);

                    // Update Risk & Reward Overview
                    document.getElementById('biggest-win').textContent = `Biggest Win: $${biggestWin.toFixed(2)}`;
                    document.getElementById('biggest-loss').textContent = `Biggest Loss: $${biggestLoss.toFixed(2)}`;
                    document.getElementById('highest-rr').textContent = `Highest RR: ${highestRR.toFixed(2)}`;
                    document.getElementById('rr-in-biggest-win').textContent = `RR in Biggest Win: ${rrInBiggestWin.toFixed(2)}`;
                    document.getElementById('rr-in-biggest-loss').textContent = `RR in Biggest Loss: ${rrInBiggestLoss.toFixed(2)}`;

                    // Calculate and update Drawdown Stats
                    const averageDrawdown = drawdownCount > 0 ? (totalDrawdown / drawdownCount).toFixed(2) : 0;
                    document.getElementById('average-drawdown').textContent = `Average Drawdown: ${averageDrawdown}%`;

                    // Calculate and update Profitable RR Stats
                    const averageProfitableRR = profitableRRCount > 0 ? (totalProfitableRR / profitableRRCount).toFixed(2) : 0;
                    document.getElementById('average-profitable-rr').textContent = `Average Profitable RR: ${averageProfitableRR}`;

                    // Calculate and update Max Potential Profit Stats
                    const averageMaxPotentialProfit = maxPotentialProfitCount > 0 ? (totalMaxPotentialProfit / maxPotentialProfitCount).toFixed(2) : 0;
                    document.getElementById('average-max-potential-profit').textContent = `Average Max Potential Profit: ${averageMaxPotentialProfit}%`;

                    // Calculate and update Best/Worst Trade Times
                    const averageBestTradeTime = bestTradeTimeCount > 0 ? (totalBestTradeTime / bestTradeTimeCount).toFixed(0) : 0;
                    const averageWorstTradeTime = worstTradeTimeCount > 0 ? (totalWorstTradeTime / worstTradeTimeCount).toFixed(0) : 0;

                    const formattedBestTradeTime = formatTimeWithAMPM(averageBestTradeTime);
                    const formattedWorstTradeTime = formatTimeWithAMPM(averageWorstTradeTime);

                    document.getElementById('best-trade-time').textContent = `Best Trade Time: ${formattedBestTradeTime}`;
                    document.getElementById('worst-trade-time').textContent = `Worst Trade Time: ${formattedWorstTradeTime}`;
                    document.getElementById('average-best-trade-time').textContent = `Average Best Trade Time: ${formattedBestTradeTime}`;
                    document.getElementById('average-worst-trade-time').textContent = `Average Worst Trade Time: ${formattedWorstTradeTime}`;
                    allYears.forEach(year => {
                            const option = document.createElement('option');
                            option.value = year;
                            option.text = year;
                            yearSelector.appendChild(option);
                    });

                    window.switchView = function () {
                            const selectedYear = yearSelector.value;
                            if (selectedYear === 'all') {
                                displayMonthlyData(totalCloseByMonth);
                            } else {
                                displayYearlyData(selectedYear, totalCloseByMonth);
                            }
                    };

                    displayMonthlyData(totalCloseByMonth);

                    function calculateRiskToReward(entry, tp, sl) {
                            const risk = entry - sl;
                            const reward = tp - entry;
                            if (risk === 0) return 'Undefined';
                            return (reward / risk).toFixed(2);
                    }

                    averageRR = rrCount > 0 ? (totalRR / rrCount).toFixed(2) : 'N/A';
                    const averageRRDisplay = document.getElementById('average-rr-value');
                    if (averageRRDisplay) {
                            averageRRDisplay.textContent = `Average RR: 1/${averageRR}`;
                    } 

                    for (const [pair, totalCloseValue] of Object.entries(totalCloseByPair)) {
                            const color = totalCloseValue > 0 ? 'green' : totalCloseValue < 0 ? 'red' : 'gray';
                            const rowProfit = document.createElement('tr');
                            rowProfit.innerHTML = `
                                <td class="table-cell">${pair}</td>
                                <td class="table-cell" style="color: ${color};">$ ${totalCloseValue.toFixed(2)}</td>
                                <td class="table-cell" style="color: ${color};">${((totalCloseValue / userCapital) * 100).toFixed(2)}%</td>
                            `;

                            if (TableBodyAllProfit) {
                                TableBodyAllProfit.appendChild(rowProfit);
                            }
                    }

                    document.querySelectorAll('.deletbtn').forEach(button => {
                            button.addEventListener('click', function () {
                                const journalId = this.getAttribute('data-id');
                                const modal = document.createElement('div');
                                modal.style.position = 'fixed';
                                modal.style.top = '0';
                                modal.style.left = '0';
                                modal.style.width = '100vw';
                                modal.style.height = '100vh';
                                modal.style.backgroundColor = 'var(--color-background-load)';
                                modal.style.display = 'flex';
                                modal.style.justifyContent = 'center';
                                modal.style.alignItems = 'center';
                                modal.style.zIndex = '1000';

                                const modalContent = document.createElement('div');
                                modalContent.style.backgroundColor = 'var(--color-white)';
                                modalContent.style.padding = 'var(--card-padding)';
                                modalContent.style.borderRadius = 'var(--card-border-radius)';
                                modalContent.style.boxShadow = 'var(--box-shadow)';
                                modalContent.style.textAlign = 'center';
                                modalContent.style.color = 'var(--color-dark)';

                                modalContent.innerHTML = `
                                    <p style="margin-bottom: var(--padding-1); font-size: 1.2rem;">
                                        Are you sure you want to delete this journal entry?
                                    </p>
                                    <button id="confirmDelete" style="
                                        background-color: #cf6c6c;
                                        color: var(--color-dark);
                                        padding: var(--padding-1);
                                        border-radius: var(--border-radius-1);
                                        border: none;
                                        cursor: pointer;
                                        margin-right: var(--padding-1);
                                    ">
                                        Yes
                                    </button>
                                    <button id="cancelDelete" style="
                                        background-color: var(--color-primary);
                                        color: var(--color-dark);
                                        padding: var(--padding-1);
                                        border-radius: var(--border-radius-1);
                                        border: none;
                                        cursor: pointer;
                                    ">
                                        No
                                    </button>
                                `;

                                modal.appendChild(modalContent);
                                document.body.appendChild(modal);

                                document.getElementById('confirmDelete').addEventListener('click', () => {
                                    fetch('../System/delete_journal.php', {
                                            method: 'POST',
                                            headers: {
                                                'Content-Type': 'application/json'
                                            },
                                            body: JSON.stringify({
                                                id: journalId
                                            })
                                        })
                                        .then(response => response.json())
                                        .then(data => {
                                            if (data.success) {
                                                window.location.href = 'dashboard.php?success=1#trackrecordall';
                                            } else {
                                                alert('Error deleting journal entry: ' + data.message);
                                            }
                                        })
                                        .catch(error => console.error('Error:', error))
                                        .finally(() => document.body.removeChild(modal));
                                });

                                document.getElementById('cancelDelete').addEventListener('click', () => {
                                    document.body.removeChild(modal);
                                });
                            });
                    });

                        totalCloseDisplay.textContent = `${totalClose.toFixed(2)}$`;
                        totalCloseDisplay.style.color = 'gray';
                        document.getElementById("totalEquityDisplay").textContent = `${(userCapital + totalClose).toFixed(2)}$`;
                        document.getElementById("totalEquityDisplay").style.color = 'gray';

                        const closePercentage = totalClose * 100 / userCapital;
                        closeCapitalPercentage.textContent = `${closePercentage.toFixed(2)}%`;
                        closeCapitalPercentage.style.color = 'gray';

                        const positiveClosePercentageValue = (positiveTrades * 100) / totalTrades;
                        positiveClosePercentage.textContent = `${positiveClosePercentageValue.toFixed(2)}%`;
                        positiveClosePercentage.style.color = 'gray';

                        Object.values(bestCloseByPair).forEach(item => {
                            const stop1 = document.querySelector("#GradientColor1 stop[offset='0%']");
                            const stop2 = document.querySelector("#GradientColor1 stop[offset='100%']");
                            const numberElement1 = document.getElementById("number1");
                            const circle1 = document.getElementById("circle1");

                            let percentage1 = positiveClosePercentageValue;
                            numberElement1.innerHTML = percentage1.toFixed(2) + "%";
                            if (positiveClosePercentageValue < 50) {
                                stop1.setAttribute("stop-color", "#ff6f6f");
                                stop2.setAttribute("stop-color", "#ff0000");
                            } else {
                                stop1.setAttribute("stop-color", "#bfff47");
                                stop2.setAttribute("stop-color", "#00ff2a");
                            }
                            let offset1 = 472 - (472 * percentage1) / 100;
                            circle1.style.strokeDashoffset = offset1;

                            const stop3 = document.querySelector("#GradientColor2 stop[offset='0%']");
                            const stop4 = document.querySelector("#GradientColor2 stop[offset='100%']");
                            const numberElement2 = document.getElementById("number2");
                            const circle2 = document.getElementById("circle2");

                            let percentage2 = closePercentage;
                            numberElement2.innerHTML = percentage2.toFixed(2) + "%";
                            if (closePercentage < 0) {
                                stop1.setAttribute("stop-color", "#ff6f6f");
                                stop2.setAttribute("stop-color", "#ff0000");
                            } else {
                                stop1.setAttribute("stop-color", "#bfff47");
                                stop2.setAttribute("stop-color", "#00ff2a");
                            }
                            let offset2 = 472 - (472 * percentage2) / 100;
                            circle2.style.strokeDashoffset = offset2;

                            const stop5 = document.querySelector("#GradientColor3 stop[offset='0%']");
                            const stop6 = document.querySelector("#GradientColor3 stop[offset='100%']");
                            const numberElement3 = document.getElementById("number3");
                            const circle3 = document.getElementById("circle3");

                            let percentage3 = totalClose;

                            numberElement3.innerHTML = percentage3.toFixed(2) + "$";
                            if (totalClose < 0) {
                                stop1.setAttribute("stop-color", "#ff6f6f");
                                stop2.setAttribute("stop-color", "#ff0000");
                            } else {
                                stop1.setAttribute("stop-color", "#bfff47");
                                stop2.setAttribute("stop-color", "#00ff2a");
                            }
                            let offset3 = 472 - (472 * percentage3) / 100;
                            circle3.style.strokeDashoffset = offset3;

                            const closeJournalValue = parseFloat(item.close_journal) || 0;
                            const color = closeJournalValue > 0 ? 'green' : closeJournalValue < 0 ? 'red' : 'gray';
                            const rowBest = document.createElement('tr');
                            rowBest.innerHTML = `
                                <td class="table-cell">${item.date_journal}</td>
                                <td class="table-cell">${item.pair}</td>
                                <td class="table-cell" style="color: ${color};">$ ${closeJournalValue.toFixed(2)}</td>
                                <td class="table-cell" style="color: ${color};">${((closeJournalValue / userCapital) * 100).toFixed(2)}%</td>
                            `;
                            bestTableBody.appendChild(rowBest);
                        });

                        let cumulativeCloseValues = [];
                        let cumulativeSum = 0;

                        closeValues.forEach(value => {
                            cumulativeSum += value;
                            cumulativeCloseValues.push(cumulativeSum);
                        });

                        const ctxArea = document.getElementById('area-chart').getContext('2d');
                        new Chart(ctxArea, {
                            type: 'line',
                            data: {
                                labels: labels.map(() => ''),
                                datasets: [{
                                    label: 'Close Value',
                                    data: cumulativeCloseValues,
                                    fill: true,
                                    backgroundColor: 'rgba(153, 255, 102, 0.5)',
                                    borderColor: 'rgb(153, 255, 102)',
                                    borderWidth: 1,
                                    tension: 0.4
                                }]
                            },
                            options: {
                                plugins: {
                                    title: {
                                        display: true,
                                    },
                                    legend: {
                                        display: true,
                                        position: 'top'
                                    }
                                },
                                scales: {
                                    x: {
                                        beginAtZero: true,
                                        ticks: {
                                            font: {
                                                size: 10
                                            }
                                        }
                                    },
                                    y: {
                                        beginAtZero: true,
                                        ticks: {
                                            font: {
                                                size: 10
                                            }
                                        }
                                    }
                                }
                            }
                        });

                        const ctxBar = document.getElementById('bar-chart').getContext('2d');
                        new Chart(ctxBar, {
                            type: 'bar',
                            data: {
                                labels: labels.map(() => ''),
                                datasets: [{
                                    label: 'Close Value',
                                    data: cumulativeCloseValues,
                                    backgroundColor: 'rgba(153, 102, 255, 0.2)',
                                    borderColor: 'rgba(153, 102, 255, 1)',
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                plugins: {
                                    title: {
                                        display: true,
                                        text: 'Your Chart Title'
                                    }
                                },
                                scales: {
                                    x: {
                                        beginAtZero: true,
                                        ticks: {
                                            font: {
                                                size: 10
                                            }
                                        }
                                    },
                                    y: {
                                        beginAtZero: true,
                                        ticks: {
                                            font: {
                                                size: 10
                                            }
                                        }
                                    }
                                }
                            }
                        });

                        let winValues = [];
                        let lossValues = [];

                        closeValues.forEach(value => {
                            if (value > 0) {
                                winValues.push(value);
                                lossValues.push(null);
                            } else {
                                lossValues.push(value);
                                winValues.push(null);
                            }
                        });

                        function calculateMovingAverage(data, windowSize) {
                            const movingAverage = [];
                            for (let i = 0; i < data.length; i++) {
                                const window = data.slice(Math.max(0, i - windowSize + 1), i + 1);
                                const average = window.reduce((sum, value) => sum + value, 0) / window.length;
                                movingAverage.push(average);
                            }
                            return movingAverage;
                        }

                        const movingAverageMax = calculateMovingAverage(maxValues, 5);
                        const movingAverageMin = calculateMovingAverage(minValues, 5);

                        const ctxRangeSpline = document.getElementById('range-spline-chart').getContext('2d');
                        new Chart(ctxRangeSpline, {
                            type: 'line',
                            data: {
                                labels: labels.map(() => ''),
                                datasets: [
                                    {
                                        label: 'Max',
                                        data: maxValues,
                                        fill: '+1',
                                        pointRadius: 1,
                                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                                        borderColor: 'rgba(75, 192, 192, 1)',
                                        borderWidth: 1,
                                        tension: 0.4
                                    },
                                    {
                                        label: 'Min',
                                        data: minValues,
                                        fill: '-1',
                                        pointRadius: 1,
                                        backgroundColor: 'rgba(255, 99, 132, 0.2)',
                                        borderColor: 'rgba(255, 99, 132, 1)',
                                        borderWidth: 1,
                                        tension: 0.4
                                    },
                                    {
                                        label: 'Moving Avg (Max)',
                                        data: movingAverageMax,
                                        fill: false,
                                        pointRadius: 1,
                                        borderColor: 'rgba(54, 162, 235, 1)',
                                        borderDash: [5, 5],
                                        borderWidth: 2,
                                        tension: 0.4
                                    },
                                    {
                                        label: 'Moving Avg (Min)',
                                        data: movingAverageMin,
                                        fill: false,
                                        pointRadius: 1,
                                        borderColor: 'rgba(255, 206, 86, 1)',
                                        borderDash: [5, 5],
                                        borderWidth: 2,
                                        tension: 0.4
                                    }
                                ]
                            },
                            options: {
                                plugins: {
                                    title: {
                                        display: true,
                                        text: 'Moving Average Chart'
                                    }
                                },
                                scales: {
                                    x: {
                                        beginAtZero: true,
                                        ticks: {
                                            font: {
                                                size: 10
                                            }
                                        }
                                    },
                                    y: {
                                        beginAtZero: false,
                                        suggestedMin: 100,
                                        ticks: {
                                            font: {
                                                size: 10
                                            }
                                        }
                                    }
                                }
                            }
                        });
                        // Extract RR values and close_journal values
                        const rrValues = validData.map(item => {
                            const entry = parseFloat(item.entry);
                            const tp = parseFloat(item.tp);
                            const sl = parseFloat(item.sl);
                            const closevalue = parseFloat(item.close_journal);
                            const rrRatio = calculateRiskToReward(entry, tp, sl);
                            return {
                                rr: rrRatio !== 'Undefined' ? parseFloat(rrRatio) : null,
                                close: closevalue
                            };
                        });

                        // Filter out null values
                        const filteredData = rrValues.filter(item => item.rr !== null);

                        // Separate RR values and colors based on close value
                        const filteredRRValues = filteredData.map(item => item.rr);
                        const backgroundColors = filteredData.map(item =>
                            item.close < 0 ? 'rgba(255, 99, 132, 0.5)' : 'rgba(75, 192, 192, 0.5)' // Red for negative, Green for positive
                        );
                        const borderColors = filteredData.map(item =>
                            item.close < 0 ? 'rgba(255, 99, 132, 1)' : 'rgb(75, 135, 192)' // Darker shades for borders
                        );

                        // Create the RR chart
                        const ctxRR = document.getElementById('rr-chart').getContext('2d');
                        new Chart(ctxRR, {
                            type: 'bar', 
                            data: {
                                labels: Array.from({ length: filteredRRValues.length }, (_, i) => `Trade ${i + 1}`),
                                datasets: [{
                                    label: 'Risk-to-Reward Ratio',
                                    data: filteredRRValues,
                                    backgroundColor: backgroundColors, // Dynamic colors
                                    borderColor: borderColors,
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                responsive: true,
                                plugins: {
                                    legend: {
                                        display: true,
                                        position: 'top'
                                    },
                                    tooltip: {
                                        callbacks: {
                                            label: function (context) {
                                                return `RR: ${context.raw.toFixed(2)}`;
                                            }
                                        }
                                    }
                                },
                                scales: {
                                    x: {
                                        title: {
                                            display: true,
                                            text: 'Trades'
                                        }
                                    },
                                    y: {
                                        title: {
                                            display: true,
                                            text: 'Risk-to-Reward Ratio'
                                        },
                                        beginAtZero: true
                                    }
                                }
                            }
                        });

                        const radarLabels = ['Losses', 'Wins'];
                        const radarData = [totalTrades - positiveTrades, positiveTrades];

                        const ctxDoughnut = document.getElementById('radar-chart').getContext('2d');
                        new Chart(ctxDoughnut, {
                            type: 'doughnut',
                            data: {
                                labels: radarLabels,
                                datasets: [{
                                    label: 'Trade Performance',
                                    data: radarData,
                                    backgroundColor: [
                                        'rgba(255, 99, 132, 0.2)',
                                        'rgba(54, 162, 235, 0.2)'
                                    ],
                                    borderColor: [
                                        'rgba(255, 99, 132, 1)',
                                        'rgba(54, 162, 235, 1)'
                                    ],
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                responsive: true,
                                plugins: {
                                    legend: {
                                        position: 'top',
                                    },
                                    title: {
                                        display: true,
                                        text: 'Trade Performance'
                                    }
                                }
                            }
                        });

                        const chart = document.getElementById('chart').getContext('2d');
                        new Chart(chart, {
                            type: 'bar',
                            data: {
                                labels: Array.from({ length: cumulativeCloseValues.length }, (_, i) => i + 1),
                                datasets: [{
                                        label: 'Cumulative Close Values',
                                        data: cumulativeCloseValues,
                                        backgroundColor: 'rgba(255, 206, 86, 0.5)',
                                        borderColor: 'rgba(255, 206, 86, 1)',
                                        borderWidth: 2,
                                        fill: false
                                    },
                                    {
                                        label: 'Winning Trades',
                                        data: winValues,
                                        backgroundColor: 'rgba(75, 192, 192, 0.5)',
                                        borderColor: 'rgba(75, 192, 192, 1)',
                                        borderWidth: 1
                                    },
                                    {
                                        label: 'Losing Trades',
                                        data: lossValues,
                                        backgroundColor: 'rgba(255, 99, 132, 0.5)',
                                        borderColor: 'rgba(255, 99, 132, 1)',
                                        borderWidth: 1
                                    }
                                ]
                            },
                            options: {
                                responsive: true,
                                plugins: {
                                    legend: {
                                        display: true
                                    },
                                    tooltip: {
                                        callbacks: {
                                            label: function (context) {
                                                return context.dataset.label + ': $' + context.raw;
                                            }
                                        }
                                    }
                                },
                                scales: {
                                    x: {
                                        stacked: false,
                                    },
                                    y: {
                                        beginAtZero: true
                                    }
                                }
                            }
                        });

                        

                    })
                    .catch(error => console.error('Error fetching journal data:', error));

            }
    }
    // Display data by year
    function displayYearlyData(year, monthlyData) {
        const tableBody = document.querySelector('#totalCloseTable tbody');
        tableBody.innerHTML = ''; // Clear previous data
        let userCapital = parseFloat(document.getElementById('userCapital').value) || 0;

        Object.entries(monthlyData)
            .filter(([monthYear]) => monthYear.startsWith(year)) // Filter data by selected year
            .forEach(([monthYear, totalClose]) => {
                const row = document.createElement('tr');

                // Dynamically set the color based on the totalClose value
                const color = totalClose > 0 ? 'green' : totalClose < 0 ? 'red' : 'gray';

                row.innerHTML = `
                    <td>${monthYear}</td>
                    <td style="color: ${color};">$ ${totalClose.toFixed(2)}</td>
                    <td style="color: ${color};">${((totalClose / userCapital) * 100).toFixed(2)}%</td>
                `;
                tableBody.appendChild(row);
            });
    }
    function drawMonthlyBarChart(monthlyData) {
        const labels = Object.keys(monthlyData); // Extract month-year labels
        const data = Object.values(monthlyData); // Extract total close values

        const ctx = document.getElementById('monthlyBarChart').getContext('2d');

        // Create the bar chart
        const monthlyBarChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Total Close Amount ($)',
                    data: data,
                    backgroundColor: 'rgba(75, 192, 192, 0.5)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Total Close Amount ($)'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Month-Year'
                        }
                    }
                },
                responsive: true,
                plugins: {
                    legend: {
                        display: true
                    },
                    title: {
                        display: true,
                        text: 'Monthly Total Close Chart'
                    }
                }
            }
        });
    }
    function displayMonthlyData(monthlyData) {
        const tableBody = document.querySelector('#totalCloseTable tbody');
        tableBody.innerHTML = ''; // Clear previous data
        let userCapital = parseFloat(document.getElementById('userCapital').value) || 0;

        Object.entries(monthlyData).forEach(([monthYear, totalClose]) => {
            const row = document.createElement('tr');
            const color = totalClose > 0 ? 'green' : totalClose < 0 ? 'red' : 'gray';

            row.innerHTML = `
                <td>${monthYear}</td>
                <td style="color: ${color};">$ ${totalClose.toFixed(2)}</td>
                <td style="color: ${color};">${((totalClose / userCapital) * 100).toFixed(2)}%</td>
            `;
            tableBody.appendChild(row);
        });

        // Call the function to draw the bar chart
        drawMonthlyBarChart(monthlyData);
    }
    function confirmDelete(userId) {
        // Create and display a confirmation modal
        const modal = document.createElement('div');
        modal.style.position = 'fixed';
        modal.style.top = '0';
        modal.style.left = '0';
        modal.style.width = '100vw';
        modal.style.height = '100vh';
        modal.style.backgroundColor = 'var(--color-background-load)';
        modal.style.display = 'flex';
        modal.style.justifyContent = 'center';
        modal.style.alignItems = 'center';
        modal.style.zIndex = '1000';

        const modalContent = document.createElement('div');
        modalContent.style.backgroundColor = 'var(--color-white)';
        modalContent.style.padding = 'var(--card-padding)';
        modalContent.style.borderRadius = 'var(--card-border-radius)';
        modalContent.style.boxShadow = 'var(--box-shadow)';
        modalContent.style.textAlign = 'center';
        modalContent.style.color = 'var(--color-dark)';

        modalContent.innerHTML = `
            <p style="margin-bottom: var(--padding-1); font-size: 1.2rem;">
                Are you sure you want to delete this user? This action cannot be undone.
            </p>
            <button id="confirmDelete" style="
                background-color: #cf6c6c;
                color: var(--color-dark);
                padding: var(--padding-1);
                border-radius: var(--border-radius-1);
                border: none;
                cursor: pointer;
                margin-right: var(--padding-1);
            ">
                Yes, delete it!
            </button>
            <button id="cancelDelete" style="
                background-color: var(--color-primary);
                color: var(--color-dark);
                padding: var(--padding-1);
                border-radius: var(--border-radius-1);
                border: none;
                cursor: pointer;
            ">
                Cancel
            </button>
        `;

        modal.appendChild(modalContent);
        document.body.appendChild(modal);

        // Handle confirmation and cancellation
        document.getElementById('confirmDelete').addEventListener('click', () => {
            window.location.href = 'dashboard.php?delete=' + userId;
            document.body.removeChild(modal);
        });

        document.getElementById('cancelDelete').addEventListener('click', () => {
            document.body.removeChild(modal);
        });
    }
    document.addEventListener('DOMContentLoaded', () => {
        // DOM Elements
        const monthSelect = document.getElementById('monthSelect');
        const yearSelect = document.getElementById('yearSelect');
        const calendarGrid = document.getElementById('calendarGrid');
        const shownote = document.getElementById('shownote');
        const FormenoteModal = document.getElementById('FormenoteModal');
        const closeNewNote = document.getElementById('closeNewNote');
        const closeShowNote = document.getElementById('closeShowNote');
        const modalDateHeading = document.getElementById('modalDateHeading');
        const modalDateHeadingShowNote = document.getElementById('modalDateHeadingShowNote');
        const noteDate = document.getElementById('noteDate');
        const modifyNoteContent = document.getElementById('modifyNoteContent');
        const saveNoteButton = document.getElementById('saveNoteButton');
        const saveModifyNoteButton = document.getElementById('savemodifyNoteButton');
        const prevMonthButton = document.getElementById('prevMonth');
        const nextMonthButton = document.getElementById('nextMonth');
        const todayButton = document.getElementById('todayButton');
        const newnote = document.getElementById('newnote');
        // Date management
        const today = new Date();
        let currentMonth = today.getMonth();
        let currentYear = today.getFullYear();
        let selectedDate;

        // Array of month names
        const months = [
            "January", "February", "March", "April", "May", "June",
            "July", "August", "September", "October", "November", "December"
        ];
        // Populate month select dropdown
        function populateMonthSelect() {
            months.forEach((month, index) => {
                const option = document.createElement('option');
                option.value = index;
                option.text = month;
                monthSelect.appendChild(option);
            });
        }

        // Populate year select dropdown (50 years in the past and future)
        function populateYearSelect() {
            const startYear = today.getFullYear() - 50;
            const endYear = today.getFullYear() + 50;
            for (let year = startYear; year <= endYear; year++) {
                const option = document.createElement('option');
                option.value = year;
                option.text = year;
                yearSelect.appendChild(option);
            }
        }

        // Clear the calendar grid
        function clearCalendar() {
            calendarGrid.innerHTML = '';
        }

        // Calculate total close (sum of the 'close' field in notes)
        function calculateTotalClose(data) {
            return data.reduce((total, item) => {
                // Ensure that close_journal is treated as a number, and handle empty/null values
                const closeValue = parseFloat(item.close_journal) || 0;
                return total + closeValue;
            }, 0);
        }

        // Generate the calendar based on the selected month and year
        function generateCalendar(month, year) {
            clearCalendar();
            const firstDay = new Date(year, month, 1).getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();

            const prevMonthDays = firstDay;
            const totalCells = prevMonthDays + daysInMonth;
            const nextMonthDays = totalCells > 35 ? (42 - totalCells) : (35 - totalCells);

            const prevMonth = month === 0 ? 11 : month - 1;
            const prevYear = month === 0 ? year - 1 : year;
            const daysInPrevMonth = new Date(prevYear, prevMonth + 1, 0).getDate();

            const calendarGrid = document.getElementById('calendarGrid'); // Assuming you have a container for the calendar
            const today = new Date();

            // Add days from the previous month
            for (let i = daysInPrevMonth - prevMonthDays + 1; i <= daysInPrevMonth; i++) {
                const day = document.createElement('div');
                day.classList.add('day', 'disabled');
                day.innerHTML = `<div class="day-number">${i}</div>`;
                calendarGrid.appendChild(day);
            }

            // Add days for the current month
            for (let i = 1; i <= daysInMonth; i++) {
                const day = document.createElement('div');
                day.classList.add('day');
                day.innerHTML = `<div class="day-number">${i}</div>`;

                if (i === today.getDate() && month === today.getMonth() && year === today.getFullYear()) {
                    day.classList.add('today');
                }

                const formattedDate = `${year}-${String(month + 1).padStart(2, '0')}-${String(i).padStart(2, '0')}`;

                // Get the user ID
                const userId = document.getElementById('userId').value;

                // Fetch data for each day and display the sum of close_journal values
                fetch(`../System/fetch_journal_data.php?id_users=${userId}&date=${formattedDate}`)
                    .then(response => response.json())
                    .then(data => {
                        const closeTotalHeading = document.createElement('h1');

                        if (data.length > 0) {
                            const totalClose = calculateTotalClose(data);
                            closeTotalHeading.textContent = `${totalClose.toFixed(2)}$`;

                            // Apply color based on the value of totalClose
                            if (totalClose > 0) {
                                closeTotalHeading.style.color = 'green'; // Positive value
                            } else if (totalClose < 0) {
                                closeTotalHeading.style.color = 'red'; // Negative value
                            } else {
                                closeTotalHeading.style.color = 'gray'; // Zero
                            }
                        } else {
                            closeTotalHeading.textContent = '0$';
                            closeTotalHeading.style.color = 'gray'; // No data, default to gray
                        }

                        day.appendChild(closeTotalHeading);
                    })
                    .catch(error => {
                        console.error('Error fetching user data:', error);
                    });

                day.addEventListener('click', () => {
                    selectedDate = {
                        day: i,
                        month: month,
                        year: year
                    };
                    modalDateHeading.textContent = `Data for ${i} ${months[month]} ${year}`;
                    modalDateHeadingShowNote.textContent = `${i} ${months[month]} ${year}`;

                    // Fetch and display data for the selected date
                    fetchUserDataForDate(userId, formattedDate);
                    shownote.style.display = 'block';
                });

                calendarGrid.appendChild(day);
            }

            // Add days from the next month
            for (let i = 1; i <= nextMonthDays; i++) {
                const day = document.createElement('div');
                day.classList.add('day', 'disabled');
                day.innerHTML = `<div class="day-number">${i}</div>`;
                calendarGrid.appendChild(day);
            }
        }

        // Function to fetch and display data for the mini track record
        function fetchUserDataForDate(userId, date) {
            if (!userId) {
                alert('Please select an account first.');
                return;
            }

            fetch(`../System/fetch_journal_data.php?id_users=${userId}&date=${date}`)
                .then(response => response.json())
                .then(data => {
                    const userCapital = parseFloat('<?php echo htmlspecialchars($user["capital"]); ?>');
                    const shownoteContent = document.getElementById('shownoteContent');
                    //

                    if (data.length > 0) {
                        let tableHTML = `
                                        <table id="calandertabel">
                                            <thead>
                                                <tr>
                                                    <th>Pair</th>
                                                    <th>Entry</th>
                                                    <th>SL</th>
                                                    <th>TP</th>
                                                    <th>Profit</th>
                                                    <th>Description</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                    `;

                        data.forEach(item => {
                            const closeValue = parseFloat(item.close_journal || 0);
                            const statusText = closeValue > 0 ? 'Win' : 'Loss'; // Status text for the close value

                            tableHTML += `
                                            <tr>
                                                <td>${item.pair}</td>
                                                <td>${item.entry}</td>
                                                <td>${item.sl}</td>
                                                <td>${item.tp}</td>
                                                <td>${item.close_journal}$</td>
                                                <td>${item.description}</td>
                                                <td>${statusText}</td>
                                            </tr>
                                        `;

                        });

                        tableHTML += `
                                            </tbody>
                                        </table>
                                    `;

                        shownoteContent.innerHTML = tableHTML;


                    } else {
                        shownoteContent.textContent = 'No data available for this date.';

                    }
                })
                .catch(error => {
                    console.error('Error fetching user data:', error);
                    document.getElementById('shownoteContent').textContent = 'Error fetching data.';

                });
        }

        // Update the calendar when month or year changes
        function updateCalendar() {
            const selectedMonth = parseInt(monthSelect.value);
            const selectedYear = parseInt(yearSelect.value);
            generateCalendar(selectedMonth, selectedYear);
        }

        // Event listeners for month and year selection
        monthSelect.addEventListener('change', updateCalendar);
        yearSelect.addEventListener('change', updateCalendar);
        // Previous month button
        prevMonthButton.addEventListener('click', () => {
            let month = parseInt(monthSelect.value);
            let year = parseInt(yearSelect.value);

            if (month === 0) {
                month = 11;
                year -= 1;
            } else {
                month -= 1;
            }

            monthSelect.value = month;
            yearSelect.value = year;
            updateCalendar();
        });
        // Next month button
        nextMonthButton.addEventListener('click', () => {
            let month = parseInt(monthSelect.value);
            let year = parseInt(yearSelect.value);

            if (month === 11) {
                month = 0;
                year += 1;
            } else {
                month += 1;
            }

            monthSelect.value = month;
            yearSelect.value = year;
            updateCalendar();
        });
        // Set today's date in the calendar
        todayButton.addEventListener('click', () => {
            monthSelect.value = today.getMonth();
            yearSelect.value = today.getFullYear();
            updateCalendar();
        });
        // Open new note modal with today's date pre-filled
        newnote.addEventListener('click', () => {
            noteDate.value = `${today.getFullYear()}-${today.getMonth() + 1}-${today.getDate()}`;
            FormenoteModal.style.display = 'block';
        });
      
        // Close the new note modal
        closeNewNote.addEventListener('click', () => {
            FormenoteModal.style.display = 'none';
        });
        // Close the show note modal
        closeShowNote.addEventListener('click', () => {
            shownote.style.display = 'none';
            document.querySelector('#shownote form').style.display = 'none';
        });
        
        function createMiniCalendar(year, month) {
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            const firstDayOfMonth = new Date(year, month, 1).getDay();
            
            const calendarContainer = document.createElement('div');
            calendarContainer.classList.add('miniCalendar');

            const header = document.createElement('div');
            header.classList.add('calendarHeader');
            const monthName = new Date(year, month).toLocaleString('default', { month: 'long' });
            header.textContent = `${monthName} ${year}`;
            calendarContainer.appendChild(header);

            // Create placeholder for days of previous month
            for (let i = 0; i < firstDayOfMonth; i++) {
                const dayElement = document.createElement('div');
                dayElement.classList.add('miniDay', 'disabled');
                calendarContainer.appendChild(dayElement);
            }

            // Create day elements for current month
            for (let day = 1; day <= daysInMonth; day++) {
                const dayElement = document.createElement('div');
                dayElement.classList.add('miniDay');

                const formattedDate = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;

                // Get the user ID
                const userId = document.getElementById('userId').value;

                // Fetch journal data for this day
                fetch(`../System/fetch_journal_data.php?id_users=${userId}&date=${formattedDate}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.length > 0) {
                            const totalClose = calculateTotalClose(data);

                            // Apply color based on the value of totalClose
                            if (totalClose > 0) {
                                dayElement.classList.add('green');  // Positive close
                            } else if (totalClose < 0) {
                                dayElement.classList.add('red');  // Negative close
                            } else {
                                dayElement.classList.add('gray');  // Neutral/zero close
                            }

                            // Tooltip with additional info
                            const tooltip = document.createElement('span');
                            tooltip.classList.add('tooltip');
                            tooltip.textContent = `Profit: ${totalClose}$`;
                            dayElement.appendChild(tooltip);
                        } else {
                            dayElement.classList.add('gray');  // No data
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching journal data:', error);
                    });

                dayElement.innerHTML += `<div>${day}</div>`;
                calendarContainer.appendChild(dayElement);
            }

            miniCalendars.appendChild(calendarContainer);
        }

        // Generate mini calendars for the last 3 months (or any range)
        for (let i = 0; i < 3; i++) {
            const targetMonth = currentMonth - i;
            const targetYear = targetMonth < 0 ? currentYear - 1 : currentYear;
            const adjustedMonth = targetMonth < 0 ? 12 + targetMonth : targetMonth;
            createMiniCalendar(targetYear, adjustedMonth);
        }
        
        // Create a year calendar
        function createYearMiniCalendar(year, month) {
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            const firstDayOfMonth = new Date(year, month, 1).getDay();

            const calendarContainer = document.createElement('div');
            calendarContainer.classList.add('miniCalendar');

            const header = document.createElement('div');
            header.classList.add('calendarHeader');
            const monthName = new Date(year, month).toLocaleString('default', { month: 'long' });
            header.textContent = `${monthName} ${year}`;
            calendarContainer.appendChild(header);

            // Create placeholder for days of previous month
            for (let i = 0; i < firstDayOfMonth; i++) {
                const dayElement = document.createElement('div');
                dayElement.classList.add('miniDay', 'disabled');
                calendarContainer.appendChild(dayElement);
            }

            // Create day elements for current month
            for (let day = 1; day <= daysInMonth; day++) {
                const dayElement = document.createElement('div');
                dayElement.classList.add('miniDay');

                const formattedDate = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;

                // Get the user ID
                const userId = document.getElementById('userId').value;

                // Fetch journal data for this day
                fetch(`../System/fetch_journal_data.php?id_users=${userId}&date=${formattedDate}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.length > 0) {
                            const totalClose = calculateTotalClose(data);

                            // Apply color based on the value of totalClose
                            if (totalClose > 0) {
                                dayElement.classList.add('green');  // Positive close
                            } else if (totalClose < 0) {
                                dayElement.classList.add('red');  // Negative close
                            } else {
                                dayElement.classList.add('gray');  // Neutral/zero close
                            }

                            // Tooltip with additional info
                            const tooltip = document.createElement('span');
                            tooltip.classList.add('tooltip');
                            tooltip.textContent = `Profit: ${totalClose}$`;
                            dayElement.appendChild(tooltip);
                        } else {
                            dayElement.classList.add('gray');  // No data
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching journal data:', error);
                    });

                dayElement.innerHTML += `<div>${day}</div>`;
                calendarContainer.appendChild(dayElement);
            }

            document.getElementById('miniCalendarsYears').appendChild(calendarContainer);
        }

        let year = new Date().getFullYear(); // Default year set to the current year

        // Function to update the year
        function updateYear(change) {
            year += change; // Increment or decrement the year
            // Clear existing mini calendars
            document.getElementById('miniCalendarsYears').innerHTML = '';
            // Create mini calendars for all months in ascending order
            for (let month = 0; month <= 11; month++) {
                createYearMiniCalendar(year, month);
            }
            // Update the year display
            document.getElementById('yearValue').textContent = year;
        }

        // Function to handle new year input with default value
        function addYear(inputYear = new Date().getFullYear()) {
            year = inputYear; // Set the year to the inputted year (or default to current year)
            // Clear existing mini calendars
            document.getElementById('miniCalendarsYears').innerHTML = '';
            // Create mini calendars for all months
            for (let month = 0; month <= 11; month++) {
                createYearMiniCalendar(year, month);
            }
            // Update the year display
            document.getElementById('yearValue').textContent = year;
        }

        // Initial rendering of the calendar for the current year
        addYear(year);

        // Attach event listeners to the buttons
        document.getElementById('prevYearbtn').addEventListener('click', () => {
            updateYear(-1); // Decrease the year by 1
        });

        document.getElementById('nextYearbtn').addEventListener('click', () => {
            updateYear(1); // Increase the year by 1
        });

        // Populate month and year dropdowns, then set initial calendar view
        populateMonthSelect();
        populateYearSelect();
        monthSelect.value = currentMonth;
        yearSelect.value = currentYear;
        updateCalendar();
    });
    function updateVisualization() {
        const entryPrice = parseFloat(document.getElementById('entryriskReward').value);
        const stopLossPrice = parseFloat(document.getElementById('stopLossriskReward').value);
        const targetPrice = parseFloat(document.getElementById('targetriskReward').value);

        if (isNaN(entryPrice) || isNaN(stopLossPrice) || isNaN(targetPrice)) {
            alert('Please enter valid numbers');
            return;
        }

        const pipsStopLoss = Math.abs(entryPrice - stopLossPrice);
        const pipsTarget = Math.abs(targetPrice - entryPrice);

        const baseHeight = 100; // Base height in pixels for stop loss
        const maxHeight = 250; // Maximum height for the divs
        const maxPips = 1000; // Maximum pips for the divs

        if (pipsStopLoss > maxPips || pipsTarget > maxPips) {
            document.getElementById('message').textContent = 'Pips exceed maximum limit of 1,000.';
            document.getElementById('stopLossDiv').style.paddingBottom = '0px';
            document.getElementById('targetDiv').style.paddingBottom = '0px';
            document.getElementById('riskRewardRatio').textContent = 'Risk to Reward Ratio: N/A';
            return;
        }

        // Calculate risk to reward ratio
        const risk = Math.abs(entryPrice - stopLossPrice);
        const reward = Math.abs(targetPrice - entryPrice);
        const riskRewardRatio = reward / risk;

        // Set div heights based on the risk/reward ratio
        let stopLossHeight = baseHeight; // Base height for stop loss
        let targetHeight = baseHeight; // Base height for target

        if (riskRewardRatio === 0.5) { // 1:2 ratio, double target height
            targetHeight = baseHeight * 2;
        } else if (riskRewardRatio > 0.5) { // For ratios lower than 1:2
            targetHeight = baseHeight * riskRewardRatio * 2; // Scale the height accordingly
        }

        // Limit div heights to the max height of 250px
        stopLossHeight = Math.min(stopLossHeight, maxHeight);
        targetHeight = Math.min(targetHeight, maxHeight);

        document.getElementById('stopLossDiv').style.paddingBottom = `${stopLossHeight}px`;
        document.getElementById('targetDiv').style.paddingBottom = `${targetHeight}px`;

        // Adjust layout if entry price > target price
        if (entryPrice < targetPrice || entryPrice < pipsStopLoss) {
            document.getElementById('visualizationContainer').style.flexDirection = 'column-reverse';

            document.getElementById('stopLossDiv').style.borderBottomRightRadius = "20px";
            document.getElementById('stopLossDiv').style.borderBottomLeftRadius = "20px";

            document.getElementById('targetDiv').style.borderTopRightRadius = "20px";
            document.getElementById('targetDiv').style.borderTopLeftRadius = "20px";

            document.getElementById('stopLossDiv').style.borderTopRightRadius = "0px";
            document.getElementById('stopLossDiv').style.borderTopLeftRadius = "0px";

            document.getElementById('targetDiv').style.borderBottomRightRadius = "0px";
            document.getElementById('targetDiv').style.borderBottomLeftRadius = "0px";

        } else {
            document.getElementById('visualizationContainer').style.flexDirection = 'column';
            document.getElementById('stopLossDiv').style.borderBottomRightRadius = "0px";
            document.getElementById('stopLossDiv').style.borderBottomLeftRadius = "0px";

            document.getElementById('targetDiv').style.borderTopRightRadius = "0px";
            document.getElementById('targetDiv').style.borderTopLeftRadius = "0px";
            document.getElementById('stopLossDiv').style.borderTopRightRadius = "20px";
            document.getElementById('stopLossDiv').style.borderTopLeftRadius = "20px";

            document.getElementById('targetDiv').style.borderBottomRightRadius = "20px";
            document.getElementById('targetDiv').style.borderBottomLeftRadius = "20px";
        }
        // Display the risk/reward ratio
        document.getElementById('riskRewardRatio').textContent = `Risk to Reward Ratio: 1/${riskRewardRatio.toFixed(2)}`;
        document.getElementById('message').textContent = '';
    }
    function updateTime() {
        const now = new Date();
        const options = {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: true,
        };
        const timeString = now.toLocaleTimeString(undefined, options);
        document.getElementById('current-time').textContent = `Time: ${timeString}`;
    }
    document.addEventListener("DOMContentLoaded", function () {
        const fileUpload = document.getElementById("fileUpload");
        const formFields = document.querySelectorAll("#Formeno input, #Formeno select, #Formeno textarea");
        const requiredFields = Array.from(formFields).filter(field => field.hasAttribute("required") && field !== fileUpload);

        fileUpload.addEventListener("change", function () {
            if (fileUpload.files.length > 0) {
                requiredFields.forEach(field => field.removeAttribute("required"));
            } else {
                requiredFields.forEach(field => field.setAttribute("required", "required"));
            }
        });
    });
    // Update the time every second
    setInterval(updateTime, 1000);
    // Initial call to display the time immediately
    updateTime();

</script>
<script>
    // Show the loader when the page starts loading
    const load = document.getElementById("load");
    load.style.display = "flex";

    // When the window finishes loading, hide the loader
    window.onload = function() {
        load.style.display = "none";
    };
</script>
<script src=
        "https://code.jquery.com/jquery-3.5.1.slim.min.js">
</script>
<script src=
        "https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js">
</script>
<script src=
        "https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js">
</script>
<script src="../Js/order.js"></script>
<script src="../Js/script.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Function to scroll to the bottom of the chat box independently
    function scrollToBottom() {
        const chatBox = document.getElementById('chatBox');
        chatBox.scrollTop = chatBox.scrollHeight; // Scrolls chat box content to the bottom
    }

    // Function to sync chat box scrolling with the website's scroll
    function scrollToBottomPage() {
        const chatBox = document.getElementById('chatBox');
        const chatBoxY = chatBox.getBoundingClientRect().top + window.scrollY; // Get chat box position relative to the page
        window.scrollTo({ top: chatBoxY + chatBox.scrollHeight, behavior: 'smooth' }); // Scroll the entire page
    }

    // Automatically show/hide the "Scroll to Bottom" button based on chat box scroll
    document.getElementById('chatBox').addEventListener('scroll', function () {
        const scrollToBottomBtn = document.getElementById('scrollToBottomBtn');
        const chatBox = document.getElementById('chatBox');

        // Show the button only if the user is not at the bottom of the chat box
        if (chatBox.scrollHeight - chatBox.scrollTop > chatBox.clientHeight + 50) {
            scrollToBottomBtn.style.display = 'block';
        } else {
            scrollToBottomBtn.style.display = 'none';
        }
    });

    // Optional: Call `scrollToBottom` when the page loads to start at the bottom of the chat box
    window.addEventListener('load', scrollToBottom);

    function showFullScreenImage(imageElement) {
        const overlay = document.getElementById('imageOverlay');
        const fullScreenImage = document.getElementById('fullScreenImage');
        fullScreenImage.src = imageElement.src;
        overlay.style.display = 'flex';
    }

    function closeFullScreenImage() {
        const overlay = document.getElementById('imageOverlay');
        overlay.style.display = 'none';
    }

    const photoInput = document.getElementById('photo-input');
    const photoLabel = document.getElementById('photo-label');

    // Function to handle file selection/deselection
    photoInput.addEventListener('change', function(event) {
        const file = event.target.files[0];

        if (file && file.type.startsWith('image/')) {
            // Change the emoji to ❌
            photoLabel.textContent = '❌';
            // Add a click event to the label to deselect the image
            photoLabel.onclick = function(e) {
                if (photoInput.value) {
                    e.preventDefault(); // Prevent opening the file dialog again
                    photoInput.value = ''; // Clear the file input
                    photoLabel.textContent = '📷'; // Change the emoji back to 📷
                }
            };
        } else {
            // If no valid image is selected, reset to 📷
            photoLabel.textContent = '📷';
            photoLabel.onclick = null; // Remove the custom click handler
        }
    });
    document.getElementById('createPartyButton').addEventListener('click', function () {
        const createPartyFormContainer = document.getElementById('createPartyFormContainer');
        createPartyFormContainer.style.display = createPartyFormContainer.style.display === 'none' ? 'block' : 'none';
    });
    // Generate a random 4-digit code
    function generatePartyCode() {
        const randomCode = Math.floor(1000 + Math.random() * 9000); // Random 4-digit number
        document.getElementById('partyCode').value = randomCode;
    }

    const SESSION_USER_ID = <?= json_encode($_SESSION['user_id']); ?>;

    // Event listener for the cancel reply button
    document.getElementById('cancelReplyBtn')?.addEventListener('click', function () {
        const replayDiv = document.getElementById('replaydiv');
        if (replayDiv) {
            replayDiv.style.display = 'none'; // Hide the replay-div

            // Clear the hidden input fields for reply_to_message_id and original_message
            document.getElementById('replyToMessageId').value = '';
            document.getElementById('originalMessage').value = '';
        }
    });

    // Event delegation to handle multiple replay buttons
    document.addEventListener('click', function (event) {
        // Handle replay button clicks
        if (event.target && (event.target.classList.contains('replay-btn') || event.target.id === 'replay')) {
            const messageElement = event.target.closest('.message'); // Find the closest message container

            if (messageElement) {
                // Get sender's name and message content
                const senderName = messageElement.querySelector('strong')?.textContent;
                const messageText = messageElement.querySelector('.message-text')?.textContent;
                const messageId = messageElement.dataset.messageId; // Get the message ID

                if (senderName && messageText && messageId) {
                    // Update replaydiv content
                    const replayDiv = document.getElementById('replaydiv');
                    if (replayDiv) {
                        replayDiv.style.display = 'flex'; // Make replaydiv visible

                        const nameReplay = replayDiv.querySelector('.name-replay');
                        const messageReplay = replayDiv.querySelector('.message-replay');

                        if (nameReplay && messageReplay) {
                            nameReplay.textContent = senderName; // Set sender's name
                            messageReplay.textContent = messageText; // Set sender's message

                            // Set the hidden input fields for reply_to_message_id and original_message
                            document.getElementById('replyToMessageId').value = messageId;
                            document.getElementById('originalMessage').value = messageText;
                        }
                    }
                }
            }
        }

        // Handle clicks on replaydiv (existing logic)
        if (
            event.target &&
            (event.target.classList.contains('replay') ||
            event.target.classList.contains('name-replay') ||
            event.target.classList.contains('message-replay'))
        ) {
            // Find the closest parent element with the class 'replay'
            const replayDiv = event.target.closest('.replay');

            if (replayDiv) {
                // Get the senderId from the clicked replay div
                const senderIdElement = replayDiv.querySelector('.senderId');
                if (!senderIdElement) {
                    console.error('Sender ID element not found');
                    return;
                }
                const senderId = senderIdElement.value;

                // Get the target element using the senderId
                const targetElement = document.getElementById(`sender_${senderId}`);

                if (targetElement) {
                    // Add a class to change the background color
                    targetElement.classList.add('highlight');

                    // Remove the class after 0.8 seconds
                    setTimeout(() => {
                        targetElement.classList.remove('highlight');
                    }, 800); // 800ms = 0.8 seconds
                }

                // Redirect to the sender's profile
                window.location.href = `#sender_${senderId}`;
            }
        }
    });

    let currentTableName = ''; // Variable to store the current table name

    // Function to load parties and handle party clicks
    async function loadParties() {
        try {
            const response = await fetch('../System/fetch_parties.php');
            const parties = await response.json();

            if (parties.error) {
                console.error('Error fetching parties:', parties.error);
                return;
            }

            const partyList = document.getElementById('partyList');
            if (!partyList) return;

            partyList.innerHTML = ''; // Clear existing content

            let firstPartyActive = false; // Track if the first party has been activated

            parties.forEach(async (party) => {
                const partyDiv = document.createElement('div');
                partyDiv.classList.add('party-div');
                partyDiv.id = `party_${party.code}`;
                partyDiv.style.position = 'relative'; // needed for positioning the delete button
                partyDiv.innerHTML = `
                    <h3>${party.name} : ${party.code}</h3>
                `;

                // ---------- Delete Button (skip for 1111) ----------
                if (party.code !== '1111') {
                    const deleteBtn = document.createElement('button');
                    deleteBtn.innerHTML = '&times;'; // × symbol
                    deleteBtn.title = 'Delete Party';
                    deleteBtn.style.cssText = `
                        position: absolute;
                        top: -7px;       /* closer to the top */
                        right: -7px;     /* closer to the right */
                        border: none;
                        background: #e74c3c;
                        color: white;
                        font-weight: bold;
                        font-size: 16px;
                        width: 24px;
                        height: 24px;
                        border-radius: 50%;
                        cursor: pointer;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        transition: background 0.3s, transform 0.2s;
                        z-index: 10;   /* ensure it's on top */
                    `;
                    deleteBtn.addEventListener('mouseover', () => {
                        deleteBtn.style.background = '#c0392b';
                        deleteBtn.style.transform = 'scale(1.1)';
                    });
                    deleteBtn.addEventListener('mouseout', () => {
                        deleteBtn.style.background = '#e74c3c';
                        deleteBtn.style.transform = 'scale(1)';
                    });

                    deleteBtn.addEventListener('click', async (e) => {
                        e.stopPropagation(); // Prevent triggering the party click

                        if (!confirm(`Are you sure you want to delete party ${party.name}?`)) return;

                        try {
                            const response = await fetch('../System/delete_party.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ partyCode: party.code })
                            });

                            const result = await response.json();

                            if (result.success) {
                                alert(result.message);
                                partyDiv.remove();

                                // Activate first remaining party if deleted party was active
                                if (partyDiv.classList.contains('party-div-active')) {
                                    const firstParty = document.querySelector('.party-div');
                                    if (firstParty) firstParty.click();
                                }
                            } else {
                                alert('Error: ' + result.error);
                            }
                        } catch (err) {
                            console.error('Delete error:', err);
                        }
                    });

                    partyDiv.appendChild(deleteBtn);
                }
                // ---------- End Delete Button ----------

                // Party click event remains unchanged
                partyDiv.addEventListener('click', async () => {
                    document.querySelectorAll('.party-div').forEach(div => div.classList.remove('party-div-active'));
                    partyDiv.classList.add('party-div-active');

                    try {
                        const messagesResponse = await fetch('../System/fetch_messages.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ partyCode: party.code }),
                        });

                        const messagesData = await messagesResponse.json();

                        if (messagesData.success) {
                            currentTableName = `community_${party.code}`;
                            document.getElementById('currentTableName').value = currentTableName;
                            updateChatBox(messagesData.messages);
                        } else {
                            console.error('Error fetching messages:', messagesData.error);
                        }
                    } catch (error) {
                        console.error('Error fetching messages:', error);
                    }
                });

                partyList.appendChild(partyDiv);

                // Activate community_1111 initially
                if (party.code === '1111' && !firstPartyActive) {
                    partyDiv.classList.add('party-div-active');
                    firstPartyActive = true;
                    partyDiv.click(); // simulate click to load messages
                }
            });

        } catch (error) {
            console.error('Error loading parties:', error);
        }
    }
    
    // Function to format date as 'Y-m-d H:i:s'
    function formatDate(date) {
        const pad = num => String(num).padStart(2, '0');
        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ` +
            `${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
    }

    // Function to update the chatBox with new messages
    function updateChatBox(messages) {
        const chatBox = document.getElementById('chatBox');
        if (!chatBox) return;

        // Store the current state of the replaydiv
        const replayDiv = document.getElementById('replaydiv');
        const isReplayDivVisible = replayDiv && replayDiv.style.display === 'flex';
        const replayDivContent = replayDiv ? replayDiv.innerHTML : '';

        // Clear existing content
        chatBox.innerHTML = '';

        if (messages.length === 0) {
            chatBox.innerHTML = `
                <div class="no-messages">
                    <span>No messages to display. Start a conversation!</span>
                </div>`;
        } else {
            let previousDateSignal = null;

            messages.forEach(message => {
                const dateSignal = getDateSignal(message.timestamp);

                if (dateSignal !== previousDateSignal) {
                    chatBox.innerHTML += `
                        <div class="date-signal">
                            <span>${dateSignal}</span>
                        </div>`;
                    previousDateSignal = dateSignal;
                }

                const replayVisible = message.original_message && message.name_sender;
                function makeLinksClickable(text) {
                    const urlPattern = /(https?:\/\/[^\s]+|www\.[^\s]+)/gi;
                    return text.replace(urlPattern, function(url) {
                        let href = url.startsWith("http") ? url : "http://" + url;
                        return `<a href="${href}" target="_blank" style="color:blue; text-decoration:underline;">${url}</a>`;
                    });
                }

                chatBox.innerHTML += `
                    <div class="message ${message.sender_id == SESSION_USER_ID ? 'sent' : 'received'}"
                        style="background-color: ${message.sender_id == SESSION_USER_ID ? 'var(--color-dark)' : 'var(--color-white)'}; 
                            color: ${message.sender_id == SESSION_USER_ID ? 'var(--color-white)' : 'var(--color-dark)'}; 
                            width: 20%;"
                        data-message-id="${message.id}" id="sender_${message.id}">
                        
                        <strong>${message.fullname}</strong><br>
                        <span class="message-text">${makeLinksClickable(message.community_message)}</span><br>
                        
                        ${message.image_path 
                            ? `<img src="${message.image_path}" alt="Uploaded Image" class="chat-image" onclick="showFullScreenImage(this)">` 
                            : ''}
                        
                        <br>
                        <div class="replay" id="replay_${message.reply_to_message_id}" style="display: ${replayVisible ? 'block' : 'none'};">
                            <input type="hidden" class="senderId" value="${message.reply_to_message_id}">
                            <span class="name-replay">${message.name_sender || ''}</span>
                            <br>
                            <span class="message-replay">${message.original_message || ''}</span>
                        </div>
                        <br>
                        <button class="replay-btn" id="replay"
                            data-arabe="رد"
                            data-france="Répondre"
                            data-english="Reply">
                            reply
                        </button>
                        <div style="display: flex; align-items: center; justify-content: space-between; flex-direction: row-reverse;">
                            <span style="font-size: 12px; color: gray;">${formatDate(new Date(message.timestamp))}</span><br>
                        </div>
                    </div>`;

            });
        }

        // Restore the replaydiv state if it was visible
        if (replayDiv && isReplayDivVisible) {
            replayDiv.style.display = 'flex';
            replayDiv.innerHTML = replayDivContent;
        }

        // Scroll to the bottom of the chatBox after updating
        scrollToBottom();
    }

    // Function to calculate the date signal
    function getDateSignal(timestamp) {
        const currentDate = new Date();
        const messageDate = new Date(timestamp);

        const interval = Math.floor((currentDate - messageDate) / (1000 * 60 * 60 * 24));

        if (interval === 0) {
            return "Today";
        } else if (interval === 1) {
            return "Yesterday";
        } else {
            return messageDate.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
        }
    }

    // Function to handle form submission
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.querySelector('.community-input-container form');
        if (!form) return;

        form.addEventListener('submit', function (event) {
            event.preventDefault(); // Prevent the default form submission

            // Add the currentTableName to the form data
            const formData = new FormData(form);
            formData.append('currentTableName', currentTableName);

            // Send the form data to the server
            fetch('dashboard.php', {
                method: 'POST',
                body: formData,
            })
                .then(response => response.text())
                .then(data => {
                    location.reload(); // Reload the page to reflect the new message
                })
                .catch(error => {
                    console.error('Error:', error);
                });
        });
    });

    // Function to handle the JoinParty form submission
    document.getElementById('JoinParty')?.addEventListener('submit', async function (event) {
        event.preventDefault(); // Prevent the default form submission

        const partyNumber = document.querySelector('input[name="partyNumber"]')?.value;

        // Validate the input (must be exactly 4 digits)
        if (!partyNumber || partyNumber.length !== 4 || isNaN(partyNumber)) {
            alert('Please enter a valid 4-digit code.');
            return;
        }

        try {
            // Get the current state of the replaydiv
            const replayDiv = document.getElementById('replaydiv');
            const replayDivState = replayDiv ? {
                isVisible: replayDiv.style.display === 'flex',
                content: replayDiv.innerHTML,
            } : null;

            // Fetch the party details using the last 4 digits
            const response = await fetch('../System/fetch_party_by_code.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    partyCode: partyNumber,
                    replayDivState: replayDivState, // Include the replaydiv state in the request
                }),
            });

            const partyData = await response.json();

            if (partyData.success) {
                // Update the current table name
                currentTableName = `community_${partyNumber}`;

                // Update the hidden input in the form with the current table name
                document.getElementById('currentTableName').value = currentTableName;

                // Update the chatBox with the new messages
                updateChatBox(partyData.messages);

                // Restore the replaydiv state from the server response
                if (partyData.replayDivState) {
                    const replayDiv = document.getElementById('replaydiv');
                    if (replayDiv) {
                        replayDiv.style.display = partyData.replayDivState.isVisible ? 'flex' : 'none';
                        replayDiv.innerHTML = partyData.replayDivState.content;
                    }
                }

                // Update the active party div
                const partyDiv = document.getElementById(`party_${partyNumber}`);
                if (partyDiv) {
                    // Remove active class from all party divs
                    document.querySelectorAll('.party-div').forEach(div => div.classList.remove('party-div-active'));
                    // Add active class to the clicked party div
                    partyDiv.classList.add('party-div-active');
                } else {
                    console.error('Error: Party div not found.');
                }
            } else {
                // No party found with this code
                const chatBox = document.getElementById('chatBox');
                if (chatBox) {
                    chatBox.innerHTML = `
                        <div class="no-messages">
                            <span>No party found with this code.</span>
                        </div>`;
                }
            }
        } catch (error) {
            console.error('Error joining party:', error);
            alert('An error occurred while joining the party.');
        }
    });

    // Load parties when the page loads
    document.addEventListener('DOMContentLoaded', loadParties);
</script>

