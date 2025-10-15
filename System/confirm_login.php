<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Confirmation</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* Loading spinner style */
        body {
            margin: 0;
        }
        .spinner {
            margin: 100px auto;
            width: 50px;
            height: 50px;
            border: 8px solid #f3f3f3;
            border-top: 8px solid #3498db;
            border-radius: 50%;
            animation: spin 2s linear infinite;
        }
        .swal2-popup {
            background-color: #181a1e;
            color: #ffff;
        }
        .swal2-confirm {
            background-color: #8bcf6c;
            border-color: #8bcf6c;
        }
        .swal2-confirm:hover {
            background-color: #8bcf6c;
            border-color: #8bcf6c;
        }
        div:where(.swal2-container) button:where(.swal2-styled):where(.swal2-confirm):focus-visible {
            border-color: #8bcf6c;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Center the spinner */
        .spinner-container {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-color: #202528;
        }
    </style>
</head>
<body>
    <div class="spinner-container">
        <div class="spinner"></div>
    </div>

    <script>
        // Check URL for status and role parameters
        const urlParams = new URLSearchParams(window.location.search);
        const status = urlParams.get('status');
        const role = urlParams.get('role'); // Added role parameter

        if (status === 'success') {
            Swal.fire({
                title: 'Success!',
                text: 'You have logged in successfully.',
                icon: 'success'
            }).then(function() {
                if (role === 'admin') {
                    window.location = '../Views/admin.php'; // Redirect to admin dashboard
                } else {
                    window.location = '../Views/dashboard.php'; // Redirect to user dashboard
                }
            });
        } else {
            Swal.fire({
                title: 'Error!',
                text: 'Invalid email or password.',
                icon: 'error'
            }).then(function() {
                window.location = '../Views/index.php'; // Redirect back to login page
            });
        }
    </script>
</body>
</html>
