<?php
    include '../DB/config.php';
    session_start();

    if ($_SESSION['role'] !== 'admin') {
        header('Location: ../Views/dashboard.php');
        exit();
    }

    // Fetch accountusers (non-admins)
    try {
        $stmt = $pdo->query("SELECT id, fullname, email, role FROM login WHERE role != 'admin'");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        echo "Error fetching users: " . htmlspecialchars($e->getMessage());
    }

    // Fetch messages (including sender's email)
    try {
        $stmt = $pdo->query("SELECT m.id, m.user_id, m.account_id, m.account_name, m.name AS sender_name, m.subject,m.prove, m.message, m.created_at, 
                            u.fullname AS sender_fullname, u.email AS sender_email
                            FROM messages m
                            LEFT JOIN login u ON m.user_id = u.id
                            ORDER BY m.created_at DESC");
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        echo "Error fetching messages: " . htmlspecialchars($e->getMessage());
    }

    // Fetch all tables starting with 'community_'
    try {
        $stmt = $pdo->query("
            SELECT table_name
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
            AND table_name LIKE 'community_%'
        ");
        $communityTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        echo "Error fetching community tables: " . htmlspecialchars($e->getMessage());
    }

    // Handle actions like deleting users, updating roles, and sending notifications
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['delete_user_id'])) {
            try {
                $stmt = $pdo->prepare("DELETE FROM login WHERE id = ?");
                $stmt->execute([$_POST['delete_user_id']]);
                header("Location: admin.php");
            } catch (PDOException $e) {
                echo "Error deleting user: " . htmlspecialchars($e->getMessage());
            }
        }

        if (isset($_POST['update_user_id'], $_POST['new_role'])) {
            try {
                $stmt = $pdo->prepare("UPDATE login SET role = ? WHERE id = ?");
                $stmt->execute([$_POST['new_role'], $_POST['update_user_id']]);
                header("Location: admin.php");
            } catch (PDOException $e) {
                echo "Error updating role: " . htmlspecialchars($e->getMessage());
            }
        }

        if (isset($_POST['notify_user_id'], $_POST['notification_message'])) {
            try {
                $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                $stmt->execute([$_POST['notify_user_id'], $_POST['notification_message']]);
                header("Location: admin.php");
            } catch (PDOException $e) {
                echo "Error sending notification: " . htmlspecialchars($e->getMessage());
            }
        }

        if (isset($_POST['delete_message_id'])) {
            try {
                $stmt = $pdo->prepare("UPDATE community SET community_message = ? WHERE id = ?");
                $stmt->execute(["This message is not appropriate for the morals of our society.", $_POST['delete_message_id']]);
                header("Location: admin.php");
                exit();
            } catch (PDOException $e) {
                echo "Error deleting message: " . htmlspecialchars($e->getMessage());
            }
        }
    }
    // Handle table deletion
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_table_name'])) {
        $tableName = $_POST['delete_table_name'];

        // Validate that the table name starts with 'community_'
        if (strpos($tableName, 'community_') === 0) {
            try {
                // Drop the table
                $stmt = $pdo->prepare("DROP TABLE $tableName");
                $stmt->execute();

                // Redirect to refresh the page and reflect the changes
                header("Location: admin.php");
                exit();
            } catch (PDOException $e) {
                echo "Error deleting table: " . htmlspecialchars($e->getMessage());
            }
        } else {
            echo "Invalid table name.";
        }
    }
    // Fetch users (accounts users)
    try {
        $stmt = $pdo->query("SELECT id, fullname, capital, login_id, public FROM users");
        $accountusers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        echo "Error fetching accountusers: " . htmlspecialchars($e->getMessage());
    }
    // modife public_status
    if (isset($_POST['user_id'], $_POST['public_status'])) {
        $userId = $_POST['user_id'];
        $status = $_POST['public_status'];

        $stmt = $pdo->prepare("UPDATE users SET public = :status WHERE id = :id");
        $stmt->execute(['status' => $status, 'id' => $userId]);
    }

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <link rel="stylesheet" href="../CSS/style.css">
</head>
<body>
    <h1>Admin Panel</h1>

    <!-- User Management Section -->
    <section>
        <h2>Manage Users</h2>
        <table border="1">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= htmlspecialchars($user['id']) ?></td>
                    <td><?= htmlspecialchars($user['fullname']) ?></td>
                    <td><?= htmlspecialchars($user['email']) ?></td>
                    <td><?= htmlspecialchars($user['role']) ?></td>
                    <td>
                        <!-- Delete User -->
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="delete_user_id" value="<?= $user['id'] ?>">
                            <button class="delete" type="submit">Delete</button>
                        </form>

                        <!-- Update Role -->
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="update_user_id" value="<?= $user['id'] ?>">
                            <select name="new_role" required>
                                <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>User</option>
                                <option value="manager" <?= $user['role'] === 'manager' ? 'selected' : '' ?>>Manager</option>
                            </select>
                            <button type="submit">Update Role</button>
                        </form>

                        <!-- Send Notification -->
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="notify_user_id" value="<?= $user['id'] ?>">
                            <input type="text" name="notification_message" placeholder="Notification message" required>
                            <button type="submit">Send Notification</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <!-- Messages Section -->
    <section>
        <h2>User Messages</h2>
        <?php if (!empty($messages)): ?>
        <table border="1">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>ID Login</th>
                    <th>account id</th>
                    <th>account name</th>
                    <th>Sender Name</th>
                    <th>Email</th>
                    <th>Subject</th>
                    <th>Message</th>
                    <th>prove</th>
                    <th>Date</th>
                    <th>message</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($messages as $msg): ?>
                <tr>
                    <td><?= htmlspecialchars($msg['id']) ?></td>
                    <td><?= htmlspecialchars($msg['user_id']) ?></td>
                    <td><?= htmlspecialchars($msg['account_id']) ?></td>
                    <td><?= htmlspecialchars($msg['account_name']) ?></td>
                    <td><?= htmlspecialchars($msg['sender_fullname']) ?></td>
                    <td><?= htmlspecialchars($msg['sender_email']) ?></td>
                    <td><?= htmlspecialchars($msg['subject']) ?></td>
                    <td><?= htmlspecialchars($msg['message']) ?></td>
                    <td>
                        <?php if (!empty($msg['prove'])): ?>
                            <img class="prove-thumb" 
                                src="../images/<?= htmlspecialchars($msg['prove']) ?>" 
                                alt="Prove" 
                                width="150" height="auto"
                                onclick="openFullscreen(this)">
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($msg['created_at']) ?></td>
                    <td>
                        <!-- Send Notification -->
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="notify_user_id" value="<?= $msg['user_id'] ?>">
                            <input type="text" name="notification_message" placeholder="Notification message" required>
                            <button type="submit">Send Notification</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p>No messages found.</p>
        <?php endif; ?>
    </section>
    
    <!-- User Section -->
    <section>
        <h2>Manage Account Users</h2>
        <table border="1">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Login_ID</th>
                    <th>Name</th>
                    <th>Capital</th>
                    <th>Public</th>
                    <th>Notify</th>
                    <th>modife</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($accountusers as $accountuser): ?>
                <tr>
                    <td><?= htmlspecialchars($accountuser['id']) ?></td>
                    <td><?= htmlspecialchars($accountuser['login_id']) ?></td>
                    <td><?= htmlspecialchars($accountuser['fullname']) ?></td>
                    <td><?= htmlspecialchars($accountuser['capital']) ?></td>
                    <td><?= htmlspecialchars($accountuser['public']) ?></td>
                    <td>
                        <!-- Send Notification -->
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="notify_user_id" value="<?= $accountuser['login_id'] ?>">
                            <input type="text" name="notification_message" placeholder="Notification message" required>
                            <button type="submit">Send Notification</button>
                        </form>
                    </td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="user_id" value="<?= $accountuser['id'] ?>">
                            <label>
                                <input type="radio" name="public_status" value="private" 
                                    <?= $accountuser['public'] === 'private' ? 'checked' : '' ?>
                                    onchange="this.form.submit()"> Private
                            </label>
                            <label>
                                <input type="radio" name="public_status" value="public" 
                                    <?= $accountuser['public'] === 'public' ? 'checked' : '' ?>
                                    onchange="this.form.submit()"> Public
                            </label>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>

   <!-- Community Tables Section -->
    <section>
        <h2>Community Tables</h2>
        <?php if (!empty($communityTables)): ?>
        <table border="1">
            <thead>
                <tr>
                    <th>Table Name</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($communityTables as $table): ?>
                <tr>
                    <td><?= htmlspecialchars($table) ?></td>
                    <td>
                        <!-- Delete Table Form -->
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="delete_table_name" value="<?= htmlspecialchars($table) ?>">
                            <button type="submit" class="delete" onclick="return confirm('Are you sure you want to delete this table? This action cannot be undone.');">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p>No community tables found.</p>
        <?php endif; ?>
    </section>

    <!-- Community Conversations Section -->
    <section>
        <h2>Community Conversations</h2>
        <?php
            try {
                // Initialize an empty array to store all conversations
                $conversations = [];

                // Loop through each community table
                foreach ($communityTables as $table) {
                    // Fetch conversations from the current table
                    $stmt = $pdo->query("
                        SELECT c.id, c.sender_id, c.receiver_id, c.community_message, c.timestamp, c.image_path,
                            s.fullname AS sender_name, r.fullname AS receiver_name
                        FROM $table c
                        LEFT JOIN login s ON c.sender_id = s.id
                        LEFT JOIN login r ON c.receiver_id = r.id
                        ORDER BY c.timestamp DESC
                    ");

                    // Merge the results into the $conversations array
                    $conversations = array_merge($conversations, $stmt->fetchAll(PDO::FETCH_ASSOC));
                }
            } catch (PDOException $e) {
                echo "Error fetching conversations: " . htmlspecialchars($e->getMessage());
            }
            ?>
        <?php if (!empty($conversations)): ?>
        <table border="1">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Sender</th>
                    <th>Receiver</th>
                    <th>Message</th>
                    <th>Timestamp</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($conversations as $conversation): ?>
                <tr>
                    <td><?= htmlspecialchars($conversation['id']) ?></td>
                    <td><?= htmlspecialchars($conversation['sender_name']) ?></td>
                    <td><?= htmlspecialchars($conversation['receiver_name']) ?></td>
                    <td>
                        <?= htmlspecialchars($conversation['community_message']) ?>
                        <?php if (!empty($conversation['image_path'])): ?>
                        <br><img src="<?= htmlspecialchars($conversation['image_path']) ?>" alt="Message Image" style="width: 150px; height: 100px;">
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($conversation['timestamp']) ?></td>
                    <td>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="delete_message_id" value="<?= $conversation['id'] ?>">
                            <button class="delete" type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p>No conversations found.</p>
        <?php endif; ?>
    </section>

    <button onclick="window.location.href='../System/logout.php'">Logout</button>
</body>
</html>
<!-- Fullscreen Image Modal -->
<div id="imageModal" class="modal" onclick="closeFullscreen()">
    <span class="close">&times;</span>
    <img class="modal-content" id="modalImage">
</div>

<script>
    function openFullscreen(imgElement) {
        const modal = document.getElementById("imageModal");
        const modalImg = document.getElementById("modalImage");
        modal.style.display = "block";
        modalImg.src = imgElement.src;
    }

    function closeFullscreen() {
        document.getElementById("imageModal").style.display = "none";
    }
</script>

<style>
    :root {
        --color-primary: #8bcf6c;
        --color-info-dark: #7d8da1;
        --card-border-radius: 2rem;
        --border-radius-1: 0.4rem;
        --border-radius-2: 1.2rem;
        --card-padding: 1.8rem;
        --padding-1: 1.2rem;
        --color-background: #181a1e;
        --color-background-load: #181a1e81;
        --color-white: #202528;
        --color-dark: #edeffd;
        --color-dark-variant: #a3bdcc;
        --color-light: rgba(0, 0, 0, 0.4);
        --box-shadow: 0 2rem 3rem var(--color-light);
        --color-scheme: dark;
    }

    .dark-mode-variables {
        --color-scheme: light;
        --color-background: #f6f6f9;
        --color-background-load: #f6f6f981;
        --color-white: #fff;
        --color-dark: #363949;
        --color-dark-variant: #677483;
        --color-light: rgba(132, 139, 200, 0.18);
        --box-shadow: 0 2rem 3rem var(--color-light);
    }
    /* General Styles */
    body {
        font-family: Arial, sans-serif;
        display:flex;
        flex-direction:column;
        align-items: center;
        margin: 0;
        padding: 0;
        background-color: var(--color-background);
        color: var(--color-dark);
    }

    h1, h2 {
        text-align: center;
        color: var(--color-primary);
    }

    /* Container Section */
    section {
        margin: 20px auto;
        padding: var(--card-padding);
        background: var(--color-white);
        border-radius: var(--card-border-radius);
        box-shadow: var(--box-shadow);
    }

    /* Table Styles */
    table {
        width: 100%;
        border-collapse: collapse;
        margin: 20px 0;
        font-size: 16px;
        background-color: var(--color-background);
        color: var(--color-dark);
    }

    table th, table td {
        border: 1px solid var(--color-dark-variant);
        padding: 1rem;
        text-align: left;
    }

    table th {
        background-color: var(--color-primary);
        color: var(--color-background);
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    table tr:nth-child(even) {
        background-color: var(--color-background-load);
    }

    table tr:hover {
        background-color: var(--color-light);
    }

    /* Buttons */
    button {
        background-color: var(--color-primary);
        color: var(--color-background);
        border: none;
        padding: var(--padding-1);
        margin: 5px 0;
        font-size: 14px;
        cursor: pointer;
        border-radius: var(--border-radius-1);
        transition: background-color 0.3s ease;
    }

    button:hover {
        background-color: var(--color-info-dark);
    }
    .delete{
        background-color:#cf6c6c;
    }
    form {
        display: inline-block;
        padding: 0;
    }

    input[type="text"], select {
        padding: var(--padding-1);
        margin-right: 5px;
        font-size: 14px;
        border: 1px solid var(--color-dark-variant);
        border-radius: var(--border-radius-1);
        width: 200px;
        background-color: var(--color-background);
        color: var(--color-dark);
    }

    input[type="text"]::placeholder {
        font-style: italic;
        color: var(--color-dark-variant);
    }

    /* Notification and Message Section */
    p {
        font-size: 14px;
        text-align: center;
        color: var(--color-dark-variant);
    }

    table th, table td {
        word-break: break-word;
    }
    /* Fullscreen Modal Styles */
    .modal {
        display: none; 
        position: fixed; 
        z-index: 1000; 
        padding-top: 50px; 
        left: 0;
        top: 0;
        width: 100%; 
        height: 100%; 
        overflow: auto; 
        background-color: rgba(0,0,0,0.9);
        cursor: zoom-out;
    }

    .modal-content {
        display: block;
        margin: auto;
        max-width: 90%;
        max-height: 90%;
        border-radius: 10px;
    }

    .close {
        position: absolute;
        top: 20px;
        right: 45px;
        color: #fff;
        font-size: 40px;
        font-weight: bold;
        cursor: pointer;
    }
    /* Responsive Design */
    @media screen and (max-width: 768px) {
        section {
            padding: var(--padding-1);
        }

        table, table thead, table tbody, table th, table td, table tr {
            display: block;
        }

        table tr {
            margin-bottom: 10px;
        }

        table th {
            display: none;
        }

        table td {
            display: flex;
            justify-content: space-between;
            padding: var(--padding-1);
            font-size: 14px;
            border: 1px solid var(--color-dark-variant);
        }

        table td::before {
            content: attr(data-label);
            font-weight: bold;
            text-transform: uppercase;
            color: var(--color-dark);
            margin-right: 5px;
        }
    }


</style>