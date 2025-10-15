<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: signin.php");
    exit();
}

include '../DB/config.php'; 

// Handle user deletion
if (isset($_GET['delete'])) {
    $user_id = intval($_GET['delete']);
    if ($user_id > 0) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        echo "<script>
                Swal.fire({
                    title: 'Deleted!',
                    text: 'User has been deleted.',
                    icon: 'success'
                }).then(function() {
                    window.location = 'users_list.php';
                });
              </script>";
    }
}

$adminId = $_SESSION['user_id']; // Get the admin's ID from the session

// Prepare the SQL query to fetch users where id_login matches the logged-in admin's ID
$stmt = $pdo->prepare("SELECT * FROM users WHERE login_id = :adminId");
$stmt->execute(['adminId' => $adminId]);

// Fetch the users
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<script>
    function confirmDelete(userId) {
        Swal.fire({
            title: 'Are you sure?',
            text: "You won't be able to revert this!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'users_list.php?delete=' + userId;
            }
        });
    }

  
    function openJournalModal(userId) {
    Swal.fire({
        title: 'Complete User Registration',
        html: `
            <form id="journalForm">
                <input type="hidden" id="userId" name="userId" value="${userId}">
                <input type="hidden" id="adminId" name="adminId" value="<?php echo $_SESSION['user_id']; ?>">
                <label for="date_journal">Date:</label>
                <input type="date" id="date_journal" name="date_journal" required><br>
                
                <label for="pair">Pair:</label>
                <input type="text" id="pair" name="pair" required><br>
                
                <label for="entry">Entry:</label>
                <input type="text" id="entry" name="entry" required><br>
                
                <label for="sl">SL:</label>
                <input type="text" id="sl" name="sl" required><br>
                
                <label for="tp">TP:</label>
                <input type="text" id="tp" name="tp" required><br>
                
                <label for="close_journal">Close:</label>
                <input type="text" id="close_journal" name="close_journal" required><br>
                
                <label for="description">Description:</label>
                <textarea id="description" name="description" required></textarea><br>
                
                <label for="image">Image:</label>
                <input type="file" id="image" name="image"><br>
            </form>
        `,
        showCancelButton: true,
        confirmButtonText: 'Save',
        cancelButtonText: 'Cancel',
        preConfirm: () => {
            const form = document.getElementById('journalForm');
            const formData = new FormData(form);
            
            return fetch('add_journal.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message);
                }
                return data;
            })
            .catch(error => {
                Swal.showValidationMessage(`Request failed: ${error}`);
            });
        }
    }).then(result => {
        if (result.isConfirmed) {
            Swal.fire('Saved!', '', 'success');
        }
    });
}


</script>
