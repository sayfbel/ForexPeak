<?php
session_start();

// Destroy the session and clear session variables
session_unset();
session_destroy();

// Redirect to the login page with a loading spinner
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logging Out...</title>
    <style>
        /* Loading spinner style */
        .spinner {
            margin: 100px auto;
            width: 50px;
            height: 50px;
            border: 8px solid #f3f3f3;
            border-top: 8px solid #3498db;
            border-radius: 50%;
            animation: spin 2s linear infinite;
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
        }
    </style>
</head>
<body>
    <div class="spinner-container">
        <div class="spinner"></div>
    </div>

    <script>
        // Redirect to the login page after 2 seconds
        setTimeout(function() {
            window.location.href = '../Views/index.php';
        }, 2000);
    </script>
</body>
</html>
