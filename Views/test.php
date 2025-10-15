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
        $stmt = $pdo->prepare("SELECT fullname, email, Address, phone FROM login WHERE id = :id");
        $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $userDetails = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($userDetails) {
            $user_fullname = $userDetails['fullname'];
            $user_email = $userDetails['email'];
            $user_address = $userDetails['Address'];
            $user_phone = $userDetails['phone'];
        } else {
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
                        header("Location: dashboard.php?error=1");
                    }
                } else {
                    header("Location: dashboard.php?error=1");
                }
            } else {
                header("Location: dashboard.php?error=1");
            }
        } else {
            header("Location: dashboard.php?error=1");
        }
    }

    // Handle user deletion
    if (isset($_GET['delete'])) {
        // Check if the 'delete' parameter is set in the query string
        $user_id = intval($_GET['delete']);
        if ($user_id > 0) {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            if ($stmt->execute([$user_id])) {
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
            } else {
                header("Location: dashboard.php?error=1");
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

    // Handle form submission for sending a new community message
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['community_image'])) {
        $community_text = trim($_POST['community']);
        $receiver_id = $_POST['receiver_id']; // Set receiver ID as needed
        $reply_to_message_id = $_POST['reply_to_message_id'] ?? null;
        $original_message = $_POST['original_message'] ?? null;
        $name_sender = null; // Initialize name_sender
        $currentTableName = $_POST['currentTableName'] ?? 'community_1111'; // Get the current table name from the form

        // If this is a reply, fetch the name_sender of the original message
        if (!empty($reply_to_message_id)) {
            $stmt = $pdo->prepare("SELECT l.fullname FROM community c JOIN login l ON c.sender_id = l.id WHERE c.id = ?");
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
            $target_file = $target_dir . basename($_FILES['community_image']['name']);
            $image_extension = pathinfo($target_file, PATHINFO_EXTENSION);
            $new_file_name = $target_dir . uniqid("img_", true) . "." . $image_extension; // Unique file name

            // Move the uploaded file
            if (move_uploaded_file($_FILES['community_image']['tmp_name'], $new_file_name)) {
                $image_path = $new_file_name; // Store the relative path to the image
            } else {
                echo "Error uploading the image.";
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

    // Fetch specific user details
    $userId = isset($_GET['id_users']) ? intval($_GET['id_users']) : 0;
    $query = $pdo->prepare("SELECT capital FROM users WHERE id = :id");
    $query->bindParam(':id', $userId, PDO::PARAM_INT);
    $query->execute();
    $user = $query->fetch(PDO::FETCH_ASSOC);

    function calculateAverageTimeBetweenOpenAndClose($pdo) {
        try {
            // Fetch all journal entries with date_open and date_close
            $stmt = $pdo->query("SELECT date_journal, date_close FROM journal WHERE date_journal IS NOT NULL AND date_close IS NOT NULL");
            $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $totalTimeDifference = 0;
            $entryCount = 0;

            foreach ($entries as $entry) {
                $dateOpen = new DateTime($entry['date_journal']);
                $dateClose = new DateTime($entry['date_close']);

                // Calculate the time difference in seconds
                $timeDifference = $dateClose->getTimestamp() - $dateOpen->getTimestamp();

                // Accumulate the total time difference
                $totalTimeDifference += $timeDifference;
                $entryCount++;
            }

            // Calculate the average time difference (in seconds)
            $averageTimeDifference = $entryCount > 0 ? $totalTimeDifference / $entryCount : 0;

            // Convert the average time difference from seconds to a more readable format (e.g., hours)
            $averageTimeInHours = $averageTimeDifference / 3600;

            return $averageTimeInHours;

        } catch (Exception $e) {
            echo "Error: " . $e->getMessage();
            return 0;
        }
    }

    // Get the average time between open and close
    $averageTimeInHours = calculateAverageTimeBetweenOpenAndClose($pdo);
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
    <link rel="stylesheet" href="../Css/dashboard.css">
    <title>ForexPeak</title>
</head>
<main id="CommunityMain" style="display: none;">
    <h1>Community ForexPeak</h1>
    <br>
    <br>
    <div class="tab-container">            
                <!-- Party Controls (General and Create Party Buttons) -->

                <div class="controlsbtn">
                    <!-- Container for Dynamically Created Parties -->
                    <div id="partyList">
                    </div>

                    <!-- Create Party Button -->
                    <div class="party-div" id="createPartyButton">
                        <h3>+</h3>
                    </div>

                    <form id="JoinParty" class="party-form">
                        <input type="number" name="partyNumber" placeholder="Enter last 4 digits of the code" required min="1000" max="9999">
                        <button type="submit" class="send-btn">Join Party</button>
                    </form>
                </div>
                    
                <!-- Create Party Form (Hidden by Default) -->
                <div id="createPartyFormContainer" style="display: none;">
                    <form action="../System/create_parties.php" method="POST" style="margin-bottom: 1rem;" class="party-form">
                        <input type="text" name="partyName" placeholder="Party Name" required>
                        <input type="hidden" name="partyCode" id="partyCode">
                        <button type="submit" class="send-btn" onclick="generatePartyCode()">Create Party</button>
                    </form>
                </div>

                <div class="chat-header">
                    <h1>Community Chat</h1>
                </div>
                
                <div class="chat-box" id="chatBox">
                    <?php if (empty($community_data)): ?>
                        <!-- Display this message if there are no messages -->
                        <div class="no-messages">
                            <span>No messages to display. Start a conversation!</span>
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
                                <?php $previousDateSignal = $dateSignal; // Update the previous date signal ?>
                            <?php endif; ?>

                            <div class="message <?= ($community['sender_id'] == $_SESSION['user_id']) ? 'sent' : 'received' ?>" 
                                style="background-color: <?= ($community['sender_id'] == $_SESSION['user_id']) ? 'var(--color-dark)' : 'var(--color-white)'; ?>; color: <?= ($community['sender_id'] == $_SESSION['user_id']) ? 'var(--color-white)' : 'var(--color-dark)'; ?>; width: 20%;"
                                data-message-id="<?= htmlspecialchars($community['id']) ?>" id="sender_<?= htmlspecialchars($community['id']) ?>">
                                
                                <!-- Display sender's full name and timestamp -->
                                <strong><?= htmlspecialchars($community['fullname']) ?></strong><br>
                                <span class="message-text"><?= htmlspecialchars($community['community_message']) ?></span><br>
                                
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
                                <button class="replay-btn" id="replay">replay</button>
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
                    <button id="cancelReplyBtn" class="cancel-reply-btn">X</button> <!-- Add this button -->
                </div>
                <div class="community-input-container">
                    <button id="scrollToBottomBtn" class="scroll-btn" onclick="scrollToBottom()">⬇️</button>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="text" class="community-input" name="community" id="communityInput" placeholder="Type a community message...">
                        <input type="hidden" name="reply_to_message_id" id="replyToMessageId">
                        <input type="hidden" name="original_message" id="originalMessage">
                        <input type="hidden" name="receiver_id" value="2"> <!-- Set the receiver_id as needed -->
                        <input type="hidden" name="currentTableName" id="currentTableName"> <!-- Hidden input for currentTableName -->
                        <input id="photo-input" type="file" name="community_image" accept="image/*" style="display: none;">
                        <button class="send-btn" type="submit">Send</button>
                        <label class="send-btn" for="photo-input" id="photo-label">📷</label>
                    </form>
                </div>
    </div>
</main>
<script src="../Js/order.js"></script>
<script src="../Js/script.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="path/to/your/script.js"></script>
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

</script>
<script>
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
</script>
<script>
    // Event listener for the cancel reply button
    document.getElementById('cancelReplyBtn').addEventListener('click', function () {
        const replayDiv = document.getElementById('replaydiv');
        replayDiv.style.display = 'none'; // Hide the replay-div

        // Clear the hidden input fields for reply_to_message_id and original_message
        document.getElementById('replyToMessageId').value = '';
        document.getElementById('originalMessage').value = '';
    });
    // Event delegation to handle multiple replay buttons
    document.addEventListener('click', function (event) {
        // Handle replay button clicks
        if (event.target && (event.target.classList.contains('replay-btn') || event.target.id === 'replay')) {
            const messageElement = event.target.closest('.message'); // Find the closest message container

            if (messageElement) {
                // Get sender's name and message content
                const senderName = messageElement.querySelector('strong').textContent;
                const messageText = messageElement.querySelector('.message-text').textContent;
                const messageId = messageElement.dataset.messageId; // Get the message ID

                // Update replaydiv content
                const replayDiv = document.getElementById('replaydiv');
                replayDiv.style.display = 'flex'; // Make replaydiv visible

                const nameReplay = replayDiv.querySelector('.name-replay');
                const messageReplay = replayDiv.querySelector('.message-replay');

                nameReplay.textContent = senderName; // Set sender's name
                messageReplay.textContent = messageText; // Set sender's message

                // Set the hidden input fields for reply_to_message_id and original_message
                document.getElementById('replyToMessageId').value = messageId;
                document.getElementById('originalMessage').value = messageText;
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

                // Log the sender ID for debugging
            }
        }
    });
</script>
<script>
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
</script>
<script>
    const SESSION_USER_ID = <?= json_encode($_SESSION['user_id']); ?>;
</script>
<script>
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
            partyList.innerHTML = ''; // Clear existing content

            parties.forEach(party => {
                const partyDiv = document.createElement('div');
                partyDiv.classList.add('party-div');
                partyDiv.id = `party_${party.code}`;
                partyDiv.innerHTML = `
                    <h3>${party.name} : ${party.code}</h3>
                `;

                // Add click event listener to each party
                partyDiv.addEventListener('click', async () => {
                    // Remove active class from all party divs
                    document.querySelectorAll('.party-div').forEach(div => div.classList.remove('party-div-active'));
                    // Add active class to the clicked party div
                    partyDiv.classList.add('party-div-active');

                    try {
                        // Fetch messages for the selected party
                        const messagesResponse = await fetch('../System/fetch_messages.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({ partyCode: party.code }),
                        });

                        const messagesData = await messagesResponse.json();

                        if (messagesData.success) {
                            // Update the current table name
                            currentTableName = `community_${party.code}`;
                            console.log('Current Table Name:', currentTableName); // Log the table name

                            // Update the hidden input in the form with the current table name
                            document.getElementById('currentTableName').value = currentTableName;

                            // Update the chatBox with the new messages
                            updateChatBox(messagesData.messages);
                        } else {
                            console.error('Error fetching messages:', messagesData.error);
                        }
                    } catch (error) {
                        console.error('Error fetching messages:', error);
                    }
                });

                partyList.appendChild(partyDiv);
            });
        } catch (error) {
            console.error('Error loading parties:', error);
        }
    }

    // Function to update the chatBox with new messages
    function updateChatBox(messages) {
        const chatBox = document.getElementById('chatBox');
        chatBox.innerHTML = ''; // Clear existing content

        if (messages.length === 0) {
            chatBox.innerHTML = `
                <div class="no-messages">
                    <span>No messages to display. Start a conversation!</span>
                </div>`;
            return;
        }

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

            chatBox.innerHTML += `
                <div class="message ${message.sender_id == SESSION_USER_ID ? 'sent' : 'received'}"
                    style="background-color: ${message.sender_id == SESSION_USER_ID ? 'var(--color-dark)' : 'var(--color-white)'}; color: ${message.sender_id == SESSION_USER_ID ? 'var(--color-white)' : 'var(--color-dark)'}; width: 20%;"
                    data-message-id="${message.id}" id="sender_${message.id}">
                    <strong>${message.fullname}</strong><br>
                    <span class="message-text">${message.community_message}</span><br>
                    ${message.image_path ? `<img src="${message.image_path}" alt="Uploaded Image" class="chat-image" onclick="showFullScreenImage(this)">` : ''}
                    <br>
                    <button class="replay-btn" id="replay">replay</button>
                    <div style="display: flex; align-items: center; justify-content: space-between; flex-direction: row-reverse;">
                        <span style="font-size: 12px; color: gray;">${new Date(message.timestamp).toLocaleString()}</span><br>
                    </div>
                </div>`;
        });

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
                    console.log(data); // Handle the response data
                    location.reload(); // Reload the page to reflect the new message
                })
                .catch(error => {
                    console.error('Error:', error);
                });
        });
    });

    // ma3rftch 
    document.getElementById('JoinParty').addEventListener('submit', async function (event) {
        event.preventDefault(); // Prevent the default form submission

        const partyNumber = document.querySelector('input[name="partyNumber"]').value;

        // Validate the input (must be exactly 4 digits)
        if (partyNumber.length !== 4 || isNaN(partyNumber)) {
            alert('Please enter a valid 4-digit code.');
            return;
        }

        try {
            // Fetch the party details using the last 4 digits
            const response = await fetch('../System/fetch_party_by_code.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ partyCode: partyNumber }),
            });

            const partyData = await response.json();

            if (partyData.success) {
                // Update the chatbox with the messages from the selected party
                updateChatBox(partyData.messages);

                // Optionally, update the session or UI to reflect the selected party
                const partyDiv = document.getElementById(`party_${partyData.party.code}`);
                if (partyDiv) {
                    document.querySelectorAll('.party-div').forEach(div => div.classList.remove('party-div-active'));
                    partyDiv.classList.add('party-div-active');
                }
            } else {
                alert('Error: ' + partyData.error);
            }
        } catch (error) {
            console.error('Error joining party:', error);
            alert('An error occurred while joining the party.');
        }
    });
    // Load parties when the page loads
    document.addEventListener('DOMContentLoaded', loadParties);
</script>

